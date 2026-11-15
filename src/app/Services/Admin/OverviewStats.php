<?php

namespace App\Services\Admin;

use App\Enums\PaymentStatus;
use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\Payment;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Aggregates for the admin overview; buckets by local (WIB) day, cached briefly.
class OverviewStats
{
    public const TTL = 600;

    public const TIERS = ['guest', 'free', 'pro'];

    /**
     * Totals plus "new in period" with the previous period for comparison.
     *
     * @return array<string, int>
     */
    public function summary(int $days): array
    {
        return Cache::remember("admin.overview.summary.{$days}", self::TTL, function () use ($days) {
            [$start, $prevStart] = $this->window($days);
            $now = CarbonImmutable::now();

            // $to null = up to now (rows stamped "now" included).
            $usersNew = fn ($from, $to = null) => User::query()->where('created_at', '>=', $from)->when($to, fn ($q) => $q->where('created_at', '<', $to))->count();
            $linksNew = fn ($from, $to = null) => Link::query()->where('created_at', '>=', $from)->when($to, fn ($q) => $q->where('created_at', '<', $to))->count();
            $paid = User::query()->whereIn('plan', app(PlanLimits::class)->paidSlugs())->where('plan_until', '>', $now);

            return [
                'users' => User::query()->count(),
                'users_new' => $usersNew($start),
                'users_new_prev' => $usersNew($prevStart, $start),
                'users_blocked' => User::query()->whereNotNull('blocked_at')->count(),
                'pro_active' => (clone $paid)->count(),
                'pro_expiring' => (clone $paid)->where('plan_until', '<=', $now->addDays(7))->count(),
                'links' => Link::query()->count(),
                'links_new' => $linksNew($start),
                'links_new_prev' => $linksNew($prevStart, $start),
                'links_disabled' => Link::query()->whereNotNull('disabled_at')->count(),
                'clicks' => (int) Link::query()->sum('clicks_total'),
            ];
        });
    }

    /**
     * New users per local day, oldest first, zero-filled.
     *
     * @return array<string, int> Y-m-d => count
     */
    public function dailySignups(int $days): array
    {
        return Cache::remember("admin.overview.signups.{$days}", self::TTL, function () use ($days) {
            $rows = User::query()
                ->where('created_at', '>=', $this->window($days)[0])
                ->selectRaw($this->localDay('created_at').' as day, count(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');

            $keys = $this->days($days);

            return array_combine($keys, array_map(fn ($day) => (int) ($rows[$day] ?? 0), $keys));
        });
    }

    /**
     * Links created per local day split by the creator's tier.
     *
     * @return array<string, array<string, int>> Y-m-d => [tier => count]
     */
    public function dailyLinksByTier(int $days): array
    {
        return Cache::remember("admin.overview.links.{$days}", self::TTL, function () use ($days) {
            $series = array_fill_keys($this->days($days), array_fill_keys(self::TIERS, 0));

            Link::query()
                ->where('created_at', '>=', $this->window($days)[0])
                ->selectRaw($this->localDay('created_at').' as day, created_tier, count(*) as total')
                ->groupBy('day', 'created_tier')
                ->get()
                ->each(function ($row) use (&$series) {
                    // Retired paid tiers count as Pro.
                    $tier = in_array($row->created_tier, ['guest', 'free'], true) ? $row->created_tier : 'pro';

                    if (isset($series[$row->day])) {
                        $series[$row->day][$tier] += (int) $row->total;
                    }
                });

            return $series;
        });
    }

    /**
     * Paid revenue per local month (oldest first) and this month's pending invoices.
     *
     * @return array{months: array<string, int>, pending: int}
     */
    public function revenue(int $months = 6): array
    {
        return Cache::remember("admin.overview.revenue.{$months}", self::TTL, function () use ($months) {
            $thisMonth = CarbonImmutable::now(config('toolkits.timezone'))->startOfMonth();
            $keys = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $keys[] = $thisMonth->subMonthsNoOverflow($i)->format('Y-m');
            }

            $rows = Payment::query()
                ->where('status', PaymentStatus::Paid->value)
                ->where('paid_at', '>=', $thisMonth->subMonthsNoOverflow($months - 1)->utc())
                ->selectRaw($this->localMonth('paid_at').' as month, sum(amount) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            return [
                'months' => array_combine($keys, array_map(fn ($month) => (int) ($rows[$month] ?? 0), $keys)),
                'pending' => Payment::query()
                    ->where('status', PaymentStatus::Pending->value)
                    ->where('expires_at', '>', now())
                    ->count(),
            ];
        });
    }

    /**
     * @return Collection<int, Link>
     */
    public function topLinks(int $limit = 5): Collection
    {
        return Cache::remember("admin.overview.top-links.{$limit}", self::TTL, fn () => Link::query()
            ->where('clicks_total', '>', 0)
            ->orderByDesc('clicks_total')
            ->limit($limit)
            ->get(['id', 'code', 'target_host', 'clicks_total', 'disabled_at']));
    }

    /**
     * Destination hosts with the most new links in the period (spam spotting).
     *
     * @return list<array{host: string, links: int, clicks: int}>
     */
    public function topHosts(int $days, int $limit = 5): array
    {
        return Cache::remember("admin.overview.top-hosts.{$days}.{$limit}", self::TTL, fn () => Link::query()
            ->where('created_at', '>=', $this->window($days)[0])
            ->selectRaw('target_host, count(*) as links, sum(clicks_total) as clicks')
            ->groupBy('target_host')
            ->orderByDesc('links')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['host' => (string) $row->target_host, 'links' => (int) $row->links, 'clicks' => (int) $row->clicks])
            ->all());
    }

    /**
     * @return Collection<int, AdminAuditLog>
     */
    public function recentAudit(int $limit = 6): Collection
    {
        return AdminAuditLog::query()
            ->with('admin:id,name,email')
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'admin_id', 'action', 'subject_type', 'subject_id', 'reason', 'created_at']);
    }

    /**
     * Start of the period (local midnight, as UTC) and start of the period before it.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(int $days): array
    {
        $start = CarbonImmutable::now(config('toolkits.timezone'))->startOfDay()->subDays($days - 1);

        return [$start->utc(), $start->subDays($days)->utc()];
    }

    /**
     * @return list<string>
     */
    private function days(int $days): array
    {
        $start = CarbonImmutable::now(config('toolkits.timezone'))->startOfDay()->subDays($days - 1);

        return array_map(fn ($i) => $start->addDays($i)->format('Y-m-d'), range(0, $days - 1));
    }

    // Columns are fixed identifiers; offset is an integer from the configured timezone.
    private function localDay(string $column): string
    {
        $offset = $this->offsetMinutes();

        return DB::connection()->getDriverName() === 'sqlite'
            ? "date({$column}, '{$offset} minutes')"
            : "DATE(DATE_ADD({$column}, INTERVAL {$offset} MINUTE))";
    }

    private function localMonth(string $column): string
    {
        $offset = $this->offsetMinutes();

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column}, '{$offset} minutes')"
            : "DATE_FORMAT(DATE_ADD({$column}, INTERVAL {$offset} MINUTE), '%Y-%m')";
    }

    private function offsetMinutes(): int
    {
        return (int) CarbonImmutable::now(config('toolkits.timezone'))->utcOffset();
    }
}
