<?php

namespace Tests\Feature;

use App\Livewire\Admin\Overview;
use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\OverviewStats;
use App\Support\ChartAxis;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, RoleSeeder::class]);
        // 14 Nov 2026 10:00 WIB.
        Carbon::setTestNow('2026-11-14 03:00:00');
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadmin');
    }

    public function test_summary_counts_and_previous_period(): void
    {
        $admin = $this->superadmin();
        User::factory()->count(2)->create();
        User::factory()->blocked()->create();
        User::factory()->create(['created_at' => now()->subDays(10)]);
        User::factory()->pro(3)->create();
        User::factory()->pro(20)->create();
        User::factory()->create(['plan' => 'pro', 'plan_until' => now()->subDay()]);
        Link::factory()->count(3)->create(['user_id' => $admin->id, 'clicks_total' => 4]);
        Link::factory()->create(['user_id' => $admin->id, 'created_at' => now()->subDays(10), 'disabled_at' => now()]);

        $s = app(OverviewStats::class)->summary(7);

        $this->assertSame(8, $s['users']);
        $this->assertSame(7, $s['users_new']);
        $this->assertSame(1, $s['users_new_prev']);
        $this->assertSame(1, $s['users_blocked']);
        $this->assertSame([2, 1], [$s['pro_active'], $s['pro_expiring']]);
        $this->assertSame([4, 3, 1, 1, 12], [$s['links'], $s['links_new'], $s['links_new_prev'], $s['links_disabled'], $s['clicks']]);
    }

    public function test_daily_series_buckets_by_wib_day_and_zero_fills(): void
    {
        // 13 Nov 18:30 UTC = 14 Nov 01:30 WIB; 13 Nov 16:00 UTC = 13 Nov 23:00 WIB.
        User::factory()->create(['created_at' => '2026-11-13 18:30:00']);
        User::factory()->create(['created_at' => '2026-11-13 16:00:00']);
        Link::factory()->guest()->create(['created_at' => '2026-11-13 18:30:00', 'created_tier' => 'guest']);
        Link::factory()->guest()->create(['created_at' => '2026-11-14 02:00:00', 'created_tier' => 'pro_plus']);
        Link::factory()->guest()->create(['created_at' => '2026-11-14 02:00:00', 'created_tier' => 'free']);

        $stats = app(OverviewStats::class);
        $signups = $stats->dailySignups(7);
        $links = $stats->dailyLinksByTier(7);

        $this->assertSame(['2026-11-08', '2026-11-14'], [array_key_first($signups), array_key_last($signups)]);
        $this->assertCount(7, $signups);
        $this->assertSame(1, $signups['2026-11-14']);
        $this->assertSame(1, $signups['2026-11-13']);
        $this->assertSame(0, $signups['2026-11-12']);
        $this->assertSame(['guest' => 1, 'free' => 1, 'pro' => 1], $links['2026-11-14']);
    }

    public function test_revenue_sums_paid_by_wib_month(): void
    {
        Payment::factory()->paid()->create(['amount' => 29000, 'paid_at' => '2026-11-02 05:00:00']);
        // 31 Oct 20:00 UTC = 1 Nov 03:00 WIB.
        Payment::factory()->paid()->create(['amount' => 290000, 'paid_at' => '2026-10-31 20:00:00']);
        Payment::factory()->paid()->create(['amount' => 29000, 'paid_at' => '2026-09-10 05:00:00']);
        Payment::factory()->create(['amount' => 99999]);

        $r = app(OverviewStats::class)->revenue();

        $this->assertSame(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'], array_keys($r['months']));
        $this->assertSame([0, 0, 0, 29000, 0, 319000], array_values($r['months']));
        $this->assertSame(1, $r['pending']);
    }

    public function test_page_renders_charts_tables_and_audit(): void
    {
        $admin = $this->superadmin();
        Link::factory()->create(['code' => 'popular1', 'target_host' => 'spam.example', 'clicks_total' => 50]);
        AdminAuditLog::create(['admin_id' => $admin->id, 'action' => 'user.block', 'subject_type' => User::class, 'subject_id' => $admin->id, 'reason' => 'Spam <b>x</b>']);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Pendaftaran per hari')
            ->assertSee('Link dibuat per hari')
            ->assertSee('Pendapatan per bulan')
            ->assertSee('/s/popular1')
            ->assertSee('spam.example')
            ->assertSee('User block')
            ->assertSee('Spam &lt;b&gt;x&lt;/b&gt;', false)
            ->assertSee('Lihat tabel');
    }

    public function test_sections_follow_permissions(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $role->givePermissionTo('admin.access');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viewer = User::factory()->create()->assignRole('viewer');

        $this->actingAs($viewer)->get('/admin')->assertOk()
            ->assertSee('Pendaftaran per hari')
            ->assertDontSee('Pendapatan per bulan')
            ->assertDontSee('Link paling banyak diklik')
            ->assertDontSee('Aktivitas admin terbaru');
    }

    public function test_period_is_clamped(): void
    {
        $admin = $this->superadmin();

        Livewire::actingAs($admin)->withQueryParams(['hari' => 999])->test(Overview::class)->assertSet('days', 30);
        Livewire::actingAs($admin)->test(Overview::class)->set('days', 90)->assertSet('days', 90)->set('days', 5)->assertSet('days', 30);
        Livewire::actingAs($admin)->test(Overview::class)->set('days', 'abc')->assertStatus(422);
    }

    public function test_requires_admin_access(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_chart_axis_helpers(): void
    {
        $this->assertSame([1, 1, 5, 10, 20, 500], array_map([ChartAxis::class, 'niceMax'], [0, 1, 4, 7, 11, 320]));
        $this->assertSame('Rp1,2 jt', ChartAxis::format(1_200_000, 'rupiah', true));
        $this->assertSame('12 rb', ChartAxis::format(12_000, 'number', true));
        $this->assertSame('1.240', ChartAxis::format(1240));
        $this->assertSame([0, 7, 15, 22, 29], ChartAxis::ticks(30));
        $this->assertSame([0, 1, 2, 3, 4, 5], ChartAxis::ticks(6));
        $this->assertInstanceOf(CarbonImmutable::class, CarbonImmutable::now());
    }
}
