<?php

namespace App\Services\Tier;

use App\Models\User;

class TierResolver
{
    public function __construct(protected PlanLimits $plans) {}

    // Unverified accounts are treated as guest.
    public function forUser(?User $user): Tier
    {
        if ($user === null || ! $user->hasVerifiedEmail()) {
            return Tier::guest();
        }

        return $this->forAccount($user);
    }

    // Ignores email verification (used for claims, ADR-003).
    public function forAccount(User $user): Tier
    {
        $paid = $this->plans->paidTier($user->plan);

        return $paid !== null && $user->plan_until !== null && $user->plan_until->isFuture() ? $paid : Tier::free();
    }
}
