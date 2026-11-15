<?php

namespace App\Services\Billing;

use App\Enums\PaymentChannel;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Cache;

// Active payment methods in display order, cached until an admin changes them.
class PaymentMethodCatalog
{
    public const CACHE_KEY = 'payment_methods.active';

    /**
     * @return array<string, string> channel value => display name
     */
    public function active(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->get(['channel', 'name'])
            ->mapWithKeys(fn (PaymentMethod $method) => [$method->channel->value => $method->name])
            ->all());
    }

    public function isActive(PaymentChannel $channel): bool
    {
        return array_key_exists($channel->value, $this->active());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
