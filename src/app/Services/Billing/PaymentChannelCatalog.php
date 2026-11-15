<?php

namespace App\Services\Billing;

use App\Models\PaymentChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

// All payment channels keyed by code, cached as plain rows until an admin changes them.
// Scoped binding: one lookup per request even when every history row asks for its channel.
class PaymentChannelCatalog
{
    public const CACHE_KEY = 'payment_channels.all';

    /** @var Collection<string, PaymentChannel>|null */
    private ?Collection $channels = null;

    /**
     * @return Collection<string, PaymentChannel>
     */
    public function all(): Collection
    {
        return $this->channels ??= PaymentChannel::hydrate(Cache::rememberForever(self::CACHE_KEY, fn () => PaymentChannel::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->toBase()
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all()))->keyBy('code');
    }

    public function find(?string $code): ?PaymentChannel
    {
        return $code === null ? null : $this->all()->get($code);
    }

    public function forget(): void
    {
        $this->channels = null;
        Cache::forget(self::CACHE_KEY);
    }
}
