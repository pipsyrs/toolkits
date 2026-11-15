<?php

namespace Tests\Feature;

use App\Actions\Links\CreateShortLink;
use App\Exceptions\QuotaExceededException;
use App\Models\User;
use App\Services\Quota\QuotaService;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\TierResolver;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuotaRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_consumers_at_limit_minus_one_only_one_wins(): void
    {
        $limits = ['u:a' => 3];
        app(QuotaService::class)->reserve(['u:a'], 'links', 2, $limits);

        // Two independent service instances (separate "requests") hit the boundary.
        $first = (new QuotaService(app(TierResolver::class), app(PlanLimits::class)))->reserve(['u:a'], 'links', 1, $limits);
        $second = app()->make(QuotaService::class)->reserve(['u:a'], 'links', 1, $limits);

        $this->assertSame([true, false], [$first, $second]);
        $this->assertSame(3, (int) DB::table('usage_counters')->where('subject', 'u:a')->value('used'));
    }

    public function test_counter_row_created_once_and_never_exceeds_limit(): void
    {
        $quota = app(QuotaService::class);
        $limits = ['u:b' => 5];
        $results = [];

        for ($i = 0; $i < 8; $i++) {
            $results[] = $quota->reserve(['u:b'], 'links', 1, $limits);
        }

        $this->assertSame(5, count(array_filter($results)));
        $this->assertSame(1, DB::table('usage_counters')->where('subject', 'u:b')->count());
        $this->assertSame(5, (int) DB::table('usage_counters')->where('subject', 'u:b')->value('used'));
    }

    public function test_bulk_reserve_over_remaining_rejected_without_partial_use(): void
    {
        $quota = app(QuotaService::class);
        $limits = ['u:c' => 3];
        $quota->reserve(['u:c'], 'links', 2, $limits);

        $this->assertFalse($quota->reserve(['u:c'], 'links', 2, $limits));
        $this->assertSame(1, $quota->remaining(['u:c'], 'links', $limits));
    }

    public function test_failed_second_subject_rolls_back_first(): void
    {
        $quota = app(QuotaService::class);
        $limits = ['g:z' => 3, 'ip:9.9.9.9' => 0];

        $this->assertFalse($quota->reserve(['g:z', 'ip:9.9.9.9'], 'links', 1, $limits));
        $this->assertSame(3, $quota->remaining(['g:z'], 'links', $limits));
    }

    public function test_free_user_quota_resets_on_wib_date_not_utc(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $action = app(CreateShortLink::class);

        // 2026-11-09 06:00 WIB = 2026-11-08 23:00 UTC.
        $this->travelTo(Carbon::parse('2026-11-08 23:00:00', 'UTC'));
        for ($i = 0; $i < 20; $i++) {
            $action->handle($user, 'g', '203.0.113.5', ['target_url' => 'https://example.com/'.$i]);
        }
        $this->assertSame('2026-11-09', DB::table('usage_counters')->value('period_date'));

        // Same WIB day although UTC date changed: still exhausted.
        $this->travelTo(Carbon::parse('2026-11-09 16:59:00', 'UTC'));
        try {
            $action->handle($user, 'g', '203.0.113.5', ['target_url' => 'https://example.com/x']);
            $this->fail('quota should still be exhausted');
        } catch (QuotaExceededException) {
        }

        // 00:00 WIB 2026-11-10.
        $this->travelTo(Carbon::parse('2026-11-09 17:00:00', 'UTC'));
        $action->handle($user, 'g', '203.0.113.5', ['target_url' => 'https://example.com/y']);
        $this->assertSame(19, app(QuotaService::class)->remaining(['u:'.$user->id], 'links', ['u:'.$user->id => 20]));
    }
}
