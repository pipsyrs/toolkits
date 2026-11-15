<?php

namespace App\Services\Authorization;

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionSet
{
    /**
     * Effective catalog permissions of a user (superadmin = whole catalog).
     *
     * @return Collection<int, string>
     */
    public function for(User $user): Collection
    {
        if ($user->isSuperadmin()) {
            return collect(PermissionName::values());
        }

        return $user->getAllPermissions()
            ->pluck('name')
            ->intersect(PermissionName::values())
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function forRole(Role $role): Collection
    {
        if ($role->name === User::SUPERADMIN_ROLE) {
            return collect(PermissionName::values());
        }

        return $role->permissions->pluck('name')->values();
    }

    /**
     * @param  iterable<string>  $permissions
     */
    public function isSubset(iterable $permissions, User $actor): bool
    {
        return collect($permissions)->diff($this->for($actor))->isEmpty();
    }
}
