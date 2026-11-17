<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

// Old coarse permissions -> per-action permissions ({module}.{view_any|view|create|update|delete}).
// Up: every role/user holding an old permission gets all of its new ones.
// Down: an old permission comes back only where all of its new ones are held, so rollback never widens access.
return new class extends Migration
{
    private const GUARD = 'web';

    private const MAP = [
        'users.view' => ['users.view_any'],
        'users.block' => ['users.update'],
        'users.roles' => ['users.update', 'roles.update'],
        'links.view' => ['links.view_any'],
        'links.moderate' => ['links.update'],
        'plans.manage' => ['plans.view_any', 'plans.create', 'plans.update', 'plans.delete'],
        'audit.view' => ['audit.view_any'],
        'roles.manage' => ['roles.view_any', 'roles.create', 'roles.update', 'roles.delete'],
        'settings.manage' => ['settings.view_any', 'settings.update'],
        'payments.view' => ['payments.view_any'],
        'payment_methods.manage' => [
            'payment_methods.view_any', 'payment_methods.create', 'payment_methods.update', 'payment_methods.delete',
            'payment_method_types.view_any', 'payment_method_types.create', 'payment_method_types.update', 'payment_method_types.delete',
            'payment_channels.view_any', 'payment_channels.create', 'payment_channels.update', 'payment_channels.delete',
        ],
        'tools.manage' => [
            'tools.view_any', 'tools.view', 'tools.update',
            'tool_categories.view_any', 'tool_categories.create', 'tool_categories.update', 'tool_categories.delete',
        ],
        'reports.view' => ['reports.view_any', 'reports.view'],
        'reports.manage' => ['reports.update'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $news = array_values(array_unique(array_merge(...array_values(self::MAP))));
            $ids = $this->ensure($news);
            $oldIds = $this->ids(array_keys(self::MAP));

            foreach ($this->pivots() as [$table, $holderKeys]) {
                foreach (self::MAP as $old => $targets) {
                    if (! isset($oldIds[$old])) {
                        continue;
                    }

                    foreach ($this->holders($table, $holderKeys, [$oldIds[$old]]) as $holder) {
                        $this->grant($table, $holder, array_map(fn ($name) => $ids[$name], $targets));
                    }
                }
            }

            $this->drop(array_keys(self::MAP));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::transaction(function () {
            $oldIds = $this->ensure(array_keys(self::MAP));
            $newIds = $this->ids(array_merge(...array_values(self::MAP)));

            foreach ($this->pivots() as [$table, $holderKeys]) {
                foreach (self::MAP as $old => $targets) {
                    $required = array_values(array_intersect_key($newIds, array_flip($targets)));

                    if (count($required) !== count($targets)) {
                        continue;
                    }

                    foreach ($this->holders($table, $holderKeys, $required, all: true) as $holder) {
                        $this->grant($table, $holder, [$oldIds[$old]]);
                    }
                }
            }

            $this->drop(array_values(array_unique(array_merge(...array_values(self::MAP)))));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<array{string, list<string>}> pivot table and the columns naming the holder (role or model)
     */
    private function pivots(): array
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        return [
            [$tables['role_has_permissions'], [$columns['role_pivot_key'] ?? 'role_id']],
            [$tables['model_has_permissions'], [$columns['model_morph_key'] ?? 'model_id', 'model_type']],
        ];
    }

    private function permissionKey(): string
    {
        return config('permission.column_names.permission_pivot_key') ?? 'permission_id';
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string> name => id
     */
    private function ids(array $names): array
    {
        return DB::table(config('permission.table_names.permissions'))
            ->where('guard_name', self::GUARD)->whereIn('name', $names)
            ->pluck('id', 'name')->all();
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string> name => id
     */
    private function ensure(array $names): array
    {
        $existing = $this->ids($names);
        $now = now();
        $rows = [];

        foreach (array_diff($names, array_keys($existing)) as $name) {
            $rows[] = ['id' => (string) Str::uuid7(), 'name' => $name, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table(config('permission.table_names.permissions'))->insert($rows);

        return $this->ids($names);
    }

    /**
     * Holders with any (or all) of the given permission ids.
     *
     * @param  list<string>  $holderKeys
     * @param  list<string>  $permissionIds
     * @return list<array<string, mixed>>
     */
    private function holders(string $table, array $holderKeys, array $permissionIds, bool $all = false): array
    {
        $query = DB::table($table)->whereIn($this->permissionKey(), $permissionIds)->select($holderKeys)->groupBy($holderKeys);

        if ($all) {
            $query->havingRaw('COUNT(DISTINCT '.$this->permissionKey().') = ?', [count($permissionIds)]);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @param  array<string, mixed>  $holder
     * @param  list<string>  $permissionIds
     */
    private function grant(string $table, array $holder, array $permissionIds): void
    {
        DB::table($table)->insertOrIgnore(array_map(
            fn ($id) => [...$holder, $this->permissionKey() => $id],
            $permissionIds,
        ));
    }

    /**
     * @param  list<string>  $names
     */
    private function drop(array $names): void
    {
        $ids = array_values($this->ids($names));

        foreach ($this->pivots() as [$table]) {
            DB::table($table)->whereIn($this->permissionKey(), $ids)->delete();
        }

        DB::table(config('permission.table_names.permissions'))->whereIn('id', $ids)->delete();
    }
};
