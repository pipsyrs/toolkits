<?php

namespace App\Services\Links;

use App\Models\BlockedDomain;
use App\Rules\SafeTargetUrl;
use Illuminate\Support\Facades\Cache;

// Cached set of blocked domains (flushed by BlockedDomainObserver).
class BlockedDomainList
{
    public const CACHE_KEY = 'blocked_domains.set';

    public function isBlocked(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $set = $this->set();

        foreach (SafeTargetUrl::hostSuffixes(strtolower($host)) as $suffix) {
            if (isset($set[$suffix])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    public function set(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => array_fill_keys(
            BlockedDomain::query()->pluck('domain')->map(fn ($d) => strtolower((string) $d))->all(),
            true,
        ));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
