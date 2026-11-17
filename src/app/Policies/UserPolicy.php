<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\PermissionSet;

class UserPolicy
{
    public function __construct(protected PermissionSet $permissions) {}

    public function block(User $admin, User $target): bool
    {
        return $admin->can('users.update') && $this->outranks($admin, $target);
    }

    public function unblock(User $admin, User $target): bool
    {
        return $admin->can('users.update') && $this->outranks($admin, $target);
    }

    // Changes the user and grants roles, so both permissions; per role checked again in AssignUserRoles.
    public function assignRoles(User $admin, User $target): bool
    {
        return $admin->can('users.update') && $admin->can('roles.update')
            && $this->outranks($admin, $target, allowSuperadminTarget: true);
    }

    // Never self or a superadmin; admin targets only by a permission superset.
    private function outranks(User $admin, User $target, bool $allowSuperadminTarget = false): bool
    {
        if ($admin->is($target)) {
            return false;
        }

        if ($target->isSuperadmin()) {
            return $allowSuperadminTarget && $admin->isSuperadmin();
        }

        return ! $target->can('admin.access')
            || $this->permissions->isSubset($this->permissions->for($target), $admin);
    }
}
