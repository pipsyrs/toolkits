<?php

namespace App\Services\Tier;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

// Plans from the database (name, limits, purchasable), cached; the single source of tier rules.
class PlanLimits
{
    public const CACHE_KEY = 'plans.catalog';

    /**
     * @return array<string, mixed>
     */
    public function for(Tier $tier): array
    {
        return $this->limits($tier->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function limits(string $slug): array
    {
        return $this->plans()[$slug]['limits'] ?? [];
    }

    // Paid tier for a stored users.plan value; null for system or unknown slugs.
    public function paidTier(?string $slug): ?Tier
    {
        if ($slug === null || Tier::isSystem($slug) || ! isset($this->plans()[$slug])) {
            return null;
        }

        return new Tier($slug, $this->plans()[$slug]['name']);
    }

    public function label(?string $slug): string
    {
        return $slug === Tier::FREE ? Tier::free()->label() : ($this->plans()[(string) $slug]['name'] ?? (string) $slug);
    }

    /**
     * @return list<string>
     */
    public function paidSlugs(): array
    {
        return array_values(array_filter(array_keys($this->plans()), fn (string $slug) => ! Tier::isSystem($slug)));
    }

    /**
     * @return list<string>
     */
    public function purchasableSlugs(): array
    {
        return array_values(array_filter($this->paidSlugs(), fn (string $slug) => $this->plans()[$slug]['purchasable']));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{name: string, limits: array<string, mixed>, purchasable: bool}> keyed by slug, in sort order
     */
    private function plans(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => Plan::query()
            ->orderBy('sort')
            ->get(['slug', 'name', 'limits', 'is_purchasable'])
            ->mapWithKeys(fn (Plan $plan) => [$plan->slug => [
                'name' => $plan->name,
                'limits' => is_array($plan->limits) ? $plan->limits : [],
                'purchasable' => $plan->is_purchasable,
            ]])
            ->all());
    }
}
