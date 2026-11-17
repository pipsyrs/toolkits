<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionSet;

class RolePolicy
{
    public function __construct(protected PermissionSet $permissions) {}

    public function viewAny(User $user): bool
    {
        return $user->can('roles.view_any');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    // Locked: superadmin role, roles the actor holds, roles with permissions beyond the actor's.
    public function update(User $user, Role $role): bool
    {
        return $this->canManage($user, $role, 'roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->canManage($user, $role, 'roles.delete');
    }

    private function canManage(User $user, Role $role, string $permission): bool
    {
        return $user->can($permission)
            && $role->name !== User::SUPERADMIN_ROLE
            && ! $user->roles->contains('id', $role->getKey())
            && $this->permissions->isSubset($this->permissions->forRole($role), $user);
    }
}
