<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function update(User $user, Plan $plan): bool
    {
        return $user->can('plans.manage');
    }
}
