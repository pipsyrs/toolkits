<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spatie Permission v8 stub, UUID variant (ADR-004); teams disabled.
return new class extends Migration
{
    public function up(): void
    {
        [$tableNames, $columnNames] = $this->names();
        $pivotRole = $columnNames['role_pivot_key'];
        $pivotPermission = $columnNames['permission_pivot_key'];
        $morphKey = $columnNames['model_morph_key'];

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotPermission, $morphKey) {
            $table->uuid($pivotPermission);
            $table->string('model_type');
            $table->uuid($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->primary([$pivotPermission, $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $pivotRole, $morphKey) {
            $table->uuid($pivotRole);
            $table->string('model_type');
            $table->uuid($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary([$pivotRole, $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->uuid($pivotPermission);
            $table->uuid($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        [$tableNames] = $this->names();

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);

        $this->forgetPermissionCache();
    }

    // Falls back to Spatie defaults so the migration runs even before config/permission.php exists.
    private function names(): array
    {
        $tableNames = array_merge([
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ], config('permission.table_names') ?? []);

        $columnNames = array_merge([
            'role_pivot_key' => 'role_id',
            'permission_pivot_key' => 'permission_id',
            'model_morph_key' => 'model_id',
        ], array_filter(config('permission.column_names') ?? []));

        return [$tableNames, $columnNames];
    }

    private function forgetPermissionCache(): void
    {
        $key = config('permission.cache.key');

        if ($key === null) {
            return;
        }

        $store = config('permission.cache.store', 'default');
        app('cache')->store($store !== 'default' ? $store : null)->forget($key);
    }
};
