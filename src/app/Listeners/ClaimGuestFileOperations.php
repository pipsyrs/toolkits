<?php

namespace App\Listeners;

use App\Http\Middleware\EnsureGuestId;
use App\Models\FileOperation;
use App\Models\User;
use App\Services\Tier\Entitlements;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\TierResolver;
use Illuminate\Auth\Events\Login;

// Results made as a guest move to the account on login/register (ADR-003), with the account's retention.
class ClaimGuestFileOperations
{
    public function __construct(
        protected TierResolver $tiers,
        protected PlanLimits $plans,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $guestId = EnsureGuestId::idFrom(request());
        $retention = Entitlements::retentionMinutes($this->plans->for($this->tiers->forAccount($user)));

        FileOperation::query()
            ->whereNull('user_id')
            ->where('guest_id', $guestId)
            ->unexpired()
            ->get(['id', 'created_at', 'expires_at'])
            ->each(function (FileOperation $operation) use ($user, $retention) {
                // Conditional update: a concurrent login claims each row once.
                FileOperation::query()->whereKey($operation->getKey())->whereNull('user_id')->update([
                    'user_id' => $user->getKey(),
                    // null = the account keeps results permanently.
                    'expires_at' => $retention === null || $operation->expires_at === null
                        ? null
                        : $operation->expires_at->max($operation->created_at->copy()->addMinutes($retention)),
                    'updated_at' => now(),
                ]);
            });
    }
}
