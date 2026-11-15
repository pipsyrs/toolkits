<?php

namespace Tests\Feature;

use App\Services\Quota\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_respects_limit_boundaries(): void
    {
        $quota = app(QuotaService::class);
        $subjects = ['u:a'];
        $limits = ['u:a' => 3];

        $this->assertTrue($quota->reserve($subjects, 'links', 2, $limits)); // limit-1
        $this->assertSame(1, $quota->remaining($subjects, 'links', $limits));
        $this->assertTrue($quota->reserve($subjects, 'links', 1, $limits)); // limit
        $this->assertFalse($quota->reserve($subjects, 'links', 1, $limits)); // limit+1
        $this->assertSame(0, $quota->remaining($subjects, 'links', $limits));
    }

    public function test_reserve_is_all_or_nothing_across_subjects(): void
    {
        $quota = app(QuotaService::class);
        $limits = ['g:x' => 5, 'ip:1.2.3.4' => 1];

        $this->assertTrue($quota->reserve(['g:x', 'ip:1.2.3.4'], 'links', 1, $limits));
        $this->assertFalse($quota->reserve(['g:x', 'ip:1.2.3.4'], 'links', 1, $limits));
        $this->assertSame(4, $quota->remaining(['g:x'], 'links', $limits));
    }

    public function test_refund_never_goes_negative(): void
    {
        $quota = app(QuotaService::class);
        $quota->reserve(['u:a'], 'links', 1, ['u:a' => 3]);
        $quota->refund(['u:a'], 'links', 5);

        $this->assertSame(3, $quota->remaining(['u:a'], 'links', ['u:a' => 3]));
    }

    public function test_period_resets_at_midnight_wib(): void
    {
        $quota = app(QuotaService::class);
        $limits = ['u:a' => 1];

        // 23:59 WIB = 16:59 UTC.
        $this->travelTo(Carbon::parse('2026-11-08 16:59:00', 'UTC'));
        $this->assertTrue($quota->reserve(['u:a'], 'links', 1, $limits));
        $this->assertFalse($quota->reserve(['u:a'], 'links', 1, $limits));

        // 00:00 WIB next day.
        $this->travelTo(Carbon::parse('2026-11-08 17:00:00', 'UTC'));
        $this->assertTrue($quota->reserve(['u:a'], 'links', 1, $limits));
    }

    public function test_ipv6_grouped_by_64(): void
    {
        $quota = app(QuotaService::class);

        $this->assertSame(
            $quota->normalizeIp('2001:db8:1:2:aaaa::1'),
            $quota->normalizeIp('2001:db8:1:2:bbbb::9'),
        );
    }
}
