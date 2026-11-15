<?php

namespace App\Actions\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Authorization\PermissionSet;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AssignUserRoles
{
    public function __construct(
        protected AuditLogger $audit,
        protected PermissionSet $permissionSet,
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  array<int, string>  $roleIds
     * @return array{added: list<string>, removed: list<string>}
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, User $target, array $roleIds, ?string $ip = null): array
    {
        Gate::forUser($actor)->authorize('assignRoles', $target);

        if (! PasswordConfirmation::recent()) {
            throw new AuthorizationException('Konfirmasi password diperlukan.');
        }

        $ids = Validator::make(['roles' => array_values($roleIds)], [
            'roles' => ['array', 'max:50'],
            'roles.*' => ['uuid', 'distinct'],
        ])->validate()['roles'] ?? [];

        return DB::transaction(function () use ($actor, $target, $ids, $ip) {
            $wanted = Role::query()->with('permissions:id,name')->where('guard_name', 'web')->whereIn('id', $ids)->get();

            if ($wanted->count() !== count($ids)) {
                throw ValidationException::withMessages(['roles' => 'Role tidak ditemukan.']);
            }

            $current = $target->roles()->with('permissions:id,name')->get();
            $added = $wanted->reject(fn (Role $r) => $current->contains('id', $r->getKey()));
            $removed = $current->reject(fn (Role $r) => $wanted->contains('id', $r->getKey()));

            foreach ($added->merge($removed) as $role) {
                $this->ensureGrantable($actor, $role);
            }

            if ($removed->contains('name', User::SUPERADMIN_ROLE)) {
                $this->ensureNotLastSuperadmin($removed->firstWhere('name', User::SUPERADMIN_ROLE));
            }

            $changes = [
                'added' => $added->pluck('name')->sort()->values()->all(),
                'removed' => $removed->pluck('name')->sort()->values()->all(),
            ];

            if ($changes['added'] === [] && $changes['removed'] === []) {
                return $changes;
            }

            $target->syncRoles($wanted);
            $this->audit->log($actor, 'user.roles_updated', $target, $changes, null, $ip);
            $this->registrar->forgetCachedPermissions();

            return $changes;
        });
    }

    private function ensureGrantable(User $actor, Role $role): void
    {
        $allowed = $role->name === User::SUPERADMIN_ROLE
            ? $actor->isSuperadmin()
            : $this->permissionSet->isSubset($this->permissionSet->forRole($role), $actor);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'roles' => "Anda tidak bisa memberi atau mencabut role \"{$role->name}\".",
            ]);
        }
    }

    private function ensureNotLastSuperadmin(Role $superadmin): void
    {
        $count = DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $superadmin->getKey())
            ->lockForUpdate()
            ->count();

        if ($count <= 1) {
            throw ValidationException::withMessages(['roles' => 'Superadmin terakhir tidak bisa dicabut.']);
        }
    }
}
