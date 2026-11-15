<?php

namespace App\Actions\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class DeleteRole
{
    public function __construct(
        protected AuditLogger $audit,
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * Roles still assigned to users are rejected (unassign first).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, Role $role, ?string $ip = null): void
    {
        Gate::forUser($actor)->authorize('delete', $role);

        if (! PasswordConfirmation::recent()) {
            throw new AuthorizationException('Konfirmasi password diperlukan.');
        }

        DB::transaction(function () use ($actor, $role, $ip) {
            $users = DB::table(config('permission.table_names.model_has_roles'))
                ->where('role_id', $role->getKey())
                ->lockForUpdate()
                ->count();

            if ($users > 0) {
                throw ValidationException::withMessages([
                    'role' => "Role \"{$role->name}\" masih dipakai {$users} pengguna. Cabut role dari pengguna terlebih dahulu.",
                ]);
            }

            $permissions = $role->permissions->pluck('name')->sort()->values()->all();
            $this->audit->log($actor, 'role.deleted', $role, ['name' => $role->name, 'permissions' => $permissions], null, $ip);
            $role->delete();
        });

        $this->registrar->forgetCachedPermissions();
    }
}
