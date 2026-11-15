<?php

namespace App\Services\Quota;

use App\Models\User;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use App\Services\Tier\TierResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Atomic daily counters (ADR-002), portable across MariaDB and SQLite.
class QuotaService
{
    public function __construct(
        protected TierResolver $tiers,
        protected PlanLimits $plans,
    ) {}

    /**
     * Reserve $n units on every subject or none.
     *
     * @param  list<string>  $subjects
     * @param  array<string, int|null>  $limits  limit per subject (null = no cap)
     */
    public function reserve(array $subjects, string $metric, int $n, array $limits): bool
    {
        if ($n <= 0) {
            return true;
        }

        $period = $this->period();

        try {
            DB::transaction(function () use ($subjects, $metric, $n, $limits, $period) {
                foreach ($subjects as $subject) {
                    DB::table('usage_counters')->insertOrIgnore([
                        'id' => (string) Str::uuid7(),
                        'subject' => $subject,
                        'metric' => $metric,
                        'period_date' => $period,
                        'used' => 0,
                        'updated_at' => now(),
                    ]);

                    $limit = $limits[$subject] ?? null;

                    $query = DB::table('usage_counters')
                        ->where('subject', $subject)
                        ->where('metric', $metric)
                        ->where('period_date', $period);

                    if ($limit !== null) {
                        $query->whereRaw('used + ? <= ?', [$n, $limit]);
                    }

                    $affected = $query->update([
                        'used' => DB::raw('used + '.(int) $n),
                        'updated_at' => now(),
                    ]);

                    if ($affected !== 1) {
                        throw new QuotaRollback;
                    }
                }
            });
        } catch (QuotaRollback) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $subjects
     */
    public function refund(array $subjects, string $metric, int $n): void
    {
        if ($n <= 0 || $subjects === []) {
            return;
        }

        DB::table('usage_counters')
            ->whereIn('subject', $subjects)
            ->where('metric', $metric)
            ->where('period_date', $this->period())
            ->update([
                'used' => DB::raw('CASE WHEN used >= '.(int) $n.' THEN used - '.(int) $n.' ELSE 0 END'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Smallest remaining across subjects; null when no subject is capped.
     *
     * @param  list<string>  $subjects
     * @param  array<string, int|null>  $limits
     */
    public function remaining(array $subjects, string $metric, array $limits): ?int
    {
        $used = DB::table('usage_counters')
            ->whereIn('subject', $subjects)
            ->where('metric', $metric)
            ->where('period_date', $this->period())
            ->pluck('used', 'subject');

        $remaining = null;

        foreach ($subjects as $subject) {
            $limit = $limits[$subject] ?? null;

            if ($limit === null) {
                continue;
            }

            $left = max(0, $limit - (int) ($used[$subject] ?? 0));
            $remaining = $remaining === null ? $left : min($remaining, $left);
        }

        return $remaining;
    }

    /**
     * Verified free/pro: u:{id}; guest/unverified: g:{guest} + ip:{ip}.
     *
     * @return list<string>
     */
    public function subjectsFor(?User $user, Tier $tier, string $guestId, string $ip): array
    {
        if ($user !== null && ! $tier->isGuest()) {
            return ['u:'.$user->getKey()];
        }

        return ['g:'.$guestId, 'ip:'.$this->normalizeIp($ip)];
    }

    /**
     * Enforced limit per subject (plan null → fair use cap); the IP subject gets the same cap.
     *
     * @param  list<string>  $subjects
     * @param  array<string, mixed>  $planLimits
     * @return array<string, int|null>
     */
    public function limitsFor(array $subjects, string $metric, array $planLimits): array
    {
        $base = $planLimits[$metric.'_per_day'] ?? null;
        $base ??= config("toolkits.fair_use.{$metric}_per_day");

        return array_fill_keys($subjects, $base === null ? null : (int) $base);
    }

    /**
     * Display summary: plan limit (null = unlimited) and remaining.
     *
     * @return array{tier: Tier, limit: int|null, remaining: int|null}
     */
    public function summary(?User $user, string $guestId, string $ip, string $metric): array
    {
        $tier = $this->tiers->forUser($user);
        $planLimits = $this->plans->for($tier);
        $planLimit = $planLimits[$metric.'_per_day'] ?? null;
        $subjects = $this->subjectsFor($user, $tier, $guestId, $ip);

        return [
            'tier' => $tier,
            'limit' => $planLimit === null ? null : (int) $planLimit,
            'remaining' => $planLimit === null
                ? null
                : $this->remaining($subjects, $metric, $this->limitsFor($subjects, $metric, $planLimits)),
        ];
    }

    // Quota day in Asia/Jakarta.
    public function period(): string
    {
        return now(config('toolkits.timezone'))->toDateString();
    }

    // IPv6 grouped by /64.
    public function normalizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            if ($packed !== false) {
                return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
            }
        }

        return $ip;
    }
}
