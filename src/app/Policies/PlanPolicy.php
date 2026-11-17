<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function create(User $user): bool
    {
        return $user->can('plans.create');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->can('plans.update');
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->can('plans.delete');
    }
}
