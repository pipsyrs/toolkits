<?php

namespace App\Actions\Admin;

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Authorization\PermissionSet;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class SaveRole
{
    public function __construct(
        protected AuditLogger $audit,
        protected PermissionSet $permissionSet,
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  array<int, string>  $permissions
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, ?Role $role, string $name, array $permissions, ?string $ip = null): Role
    {
        $gate = Gate::forUser($actor);
        $role ? $gate->authorize('update', $role) : $gate->authorize('create', Role::class);

        if (! PasswordConfirmation::recent()) {
            throw new AuthorizationException('Konfirmasi password diperlukan.');
        }

        $data = Validator::make(['name' => trim($name), 'permissions' => array_values($permissions)], [
            'name' => [
                'required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{2,39}$/',
                Rule::notIn([User::SUPERADMIN_ROLE]),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->getKey()),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionName::values())],
        ], [
            'name.regex' => 'Nama role: huruf kecil, angka, - atau _, 3–40 karakter.',
        ], ['name' => 'nama role', 'permissions' => 'permission'])->validate();

        $selected = collect($data['permissions'] ?? []);

        // Any admin permission implies admin.access.
        if ($selected->isNotEmpty()) {
            $selected->push(PermissionName::AdminAccess->value);
        }

        $selected = $selected->unique()->sort()->values();

        if (! $this->permissionSet->isSubset($selected, $actor)) {
            throw ValidationException::withMessages([
                'permissions' => 'Tidak bisa memberi permission yang tidak Anda miliki.',
            ]);
        }

        return DB::transaction(function () use ($actor, $role, $data, $selected, $ip) {
            $old = $role ? $role->permissions->pluck('name')->sort()->values() : collect();
            $oldName = $role?->name;

            // Edit without changes: no write, no audit.
            if ($role !== null && $oldName === $data['name'] && $old->values()->all() === $selected->all()) {
                return $role;
            }

            $role ??= new Role(['guard_name' => 'web']);
            $role->name = $data['name'];
            $role->guard_name = 'web';
            $role->save();
            $role->syncPermissions($selected->all());

            $changes = array_filter([
                'name' => $oldName !== $data['name'] ? ['old' => $oldName, 'new' => $data['name']] : null,
                'added' => $selected->diff($old)->values()->all() ?: null,
                'removed' => $old->diff($selected)->values()->all() ?: null,
            ]);

            $this->audit->log($actor, $oldName === null ? 'role.created' : 'role.updated', $role, $changes, null, $ip);
            $this->registrar->forgetCachedPermissions();

            return $role;
        });
    }
}
