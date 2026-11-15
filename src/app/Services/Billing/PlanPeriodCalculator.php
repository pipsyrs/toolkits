<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use Carbon\CarbonImmutable;

// Active period math for invoice-per-period billing (ADR-007).
class PlanPeriodCalculator
{
    public function __construct(private readonly PlanLimits $plans) {}

    /**
     * Resulting plan + period window after paying $plan for $period.
     *
     * @return array{plan: string, start: CarbonImmutable, until: CarbonImmutable}
     */
    public function apply(User $user, string $plan, BillingPeriod $period, CarbonImmutable $now): array
    {
        // Renewal extends from plan_until; a different plan never inherits the remaining time.
        $start = $this->activePlan($user, $now) === $plan ? CarbonImmutable::instance($user->plan_until) : $now;

        return ['plan' => $plan, 'start' => $start, 'until' => $period->addTo($start)];
    }

    public function nextUntil(User $user, string $plan, BillingPeriod $period, CarbonImmutable $now): CarbonImmutable
    {
        return $this->apply($user, $plan, $period, $now)['until'];
    }

    public function canPurchase(User $user, string $plan, ?CarbonImmutable $now = null): bool
    {
        return $this->denyReason($user, $plan, $now) === null;
    }

    public function isPurchasable(string $plan): bool
    {
        return in_array($plan, $this->plans->purchasableSlugs(), true);
    }

    public function denyReason(User $user, string $plan, ?CarbonImmutable $now = null): ?string
    {
        if (! $this->isPurchasable($plan)) {
            return 'Paket tidak tersedia.';
        }

        $active = $this->activePlan($user, $now ?? CarbonImmutable::now());

        return $active !== null && $active !== $plan
            ? 'Paket '.$this->plans->label($active).' masih aktif. Paket lain bisa dibeli setelah masa aktifnya berakhir.'
            : null;
    }

    // new | renewal
    public function kind(User $user, string $plan, ?CarbonImmutable $now = null): string
    {
        return $this->activePlan($user, $now ?? CarbonImmutable::now()) === null ? 'new' : 'renewal';
    }

    public function activePlan(User $user, CarbonImmutable $now): ?string
    {
        $plan = $user->plan;

        return in_array($plan, $this->plans->paidSlugs(), true) && $user->plan_until !== null && $user->plan_until->getTimestamp() > $now->getTimestamp()
            ? $plan
            : null;
    }
}
