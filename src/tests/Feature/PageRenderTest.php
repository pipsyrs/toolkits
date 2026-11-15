<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\User;
use App\Services\Quota\QuotaService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    private function superadmin(): User
    {
        return User::factory()->create(['email' => 'root@example.com'])->assignRole('superadmin');
    }

    private function assertSingleDocument(TestResponse $response): TestResponse
    {
        $html = (string) $response->getContent();

        $this->assertSame(1, preg_match_all('/<html[\s>]/i', $html), 'expected exactly one <html>');
        $this->assertSame(1, substr_count(strtolower($html), '</html>'));

        return $response;
    }

    public function test_public_pages_render_single_document(): void
    {
        foreach (['/', '/tools', '/pricing', '/terms', '/privacy', '/login', '/register', '/forgot-password',
            '/tools/shorten', '/tools/qr-code', '/tools/image-convert', '/tools/image-compress', '/tools/image-resize',
            '/tools/pdf-merge', '/tools/pdf-split', '/tools/pdf-compress', '/tools/image-to-pdf'] as $uri) {
            $this->assertSingleDocument($this->get($uri)->assertOk());
        }
    }

    public function test_home_lists_tool_catalog(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (config('toolkits.tools') as $tool) {
            $response->assertSee($tool['name']);
        }
    }

    // Two cards: Free and Pro, bullets generated from each plan's limits.
    public function test_pricing_shows_plans_and_prices(): void
    {
        $this->get('/pricing')->assertOk()
            ->assertSeeInOrder(['Free', 'Pro'])
            ->assertDontSee('Pro Plus')
            ->assertSee('Rp29.000')
            ->assertSee('Rp290.000')
            ->assertSee('File hingga 2 MB')
            ->assertSee('Bebas iklan');
    }

    public function test_client_pages_render_single_document(): void
    {
        $user = User::factory()->create();

        foreach (['/dashboard', '/dashboard/links', '/dashboard/files', '/dashboard/billing', '/dashboard/profile'] as $uri) {
            $this->assertSingleDocument($this->actingAs($user)->get($uri)->assertOk());
        }
    }

    public function test_admin_pages_render_single_document(): void
    {
        $admin = $this->superadmin();

        foreach (['/admin', '/admin/users', '/admin/links', '/admin/plans', '/admin/audit-logs', '/admin/tools', '/admin/tools/qr-code', '/admin/payment-methods'] as $uri) {
            $this->assertSingleDocument($this->actingAs($admin)->get($uri)->assertOk());
        }
    }

    public function test_client_links_table_lists_only_own_rows(): void
    {
        $user = User::factory()->create();
        Link::factory()->for($user)->create(['code' => 'mine-one', 'target_host' => 'own.example', 'clicks_total' => 1234]);
        Link::factory()->for($user)->create(['code' => 'mine-two']);
        Link::factory()->create(['code' => 'other-one', 'target_host' => 'other.example']);

        $this->actingAs($user)->get('/dashboard/links')->assertOk()
            ->assertSee('/s/mine-one')
            ->assertSee('/s/mine-two')
            ->assertSee('own.example')
            ->assertSee('1,234')
            ->assertDontSee('/s/other-one')
            ->assertDontSee('other.example');
    }

    public function test_client_overview_shows_recent_links_and_quota(): void
    {
        $user = User::factory()->create();
        Link::factory()->for($user)->create(['code' => 'recent-1', 'target_host' => 'recent.example']);
        app(QuotaService::class)->reserve(['u:'.$user->id], 'links', 2, ['u:'.$user->id => 20]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('/s/recent-1')
            ->assertSee('recent.example')
            ->assertSee('Link: 18/20');
    }

    public function test_quota_badge_shows_numbers_for_free(): void
    {
        $user = User::factory()->create();
        app(QuotaService::class)->reserve(['u:'.$user->id], 'links', 5, ['u:'.$user->id => 20]);

        $this->actingAs($user)->get('/dashboard/links')->assertOk()
            ->assertSee('Link: 15/20')
            ->assertDontSee('Link: Tanpa batas');
    }

    public function test_quota_badge_shows_numbers_for_guest(): void
    {
        // Visitors without an account get the Free quota.
        $this->get('/tools/shorten')->assertOk()->assertSee('Link: 20/20');
    }

    public function test_quota_badge_unlimited_for_pro(): void
    {
        $this->actingAs(User::factory()->pro()->create())->get('/dashboard')->assertOk()->assertSee('Link: Tanpa batas');
    }

    public function test_admin_users_table_lists_rows(): void
    {
        $admin = $this->superadmin();
        User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        User::factory()->blocked()->create(['email' => 'blk@example.com']);

        $this->actingAs($admin)->get('/admin/users')->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('budi@example.com')
            ->assertSee('blk@example.com')
            ->assertSee('root@example.com');
    }

    public function test_admin_links_table_lists_rows_with_owner(): void
    {
        $admin = $this->superadmin();
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        Link::factory()->for($owner)->create(['code' => 'owned-1', 'target_host' => 'owned.example']);
        Link::factory()->guest()->create(['code' => 'guest-1']);
        Link::factory()->disabled()->create(['code' => 'dis-1']);

        $this->actingAs($admin)->get('/admin/links')->assertOk()
            ->assertSee('/s/owned-1')
            ->assertSee('owned.example')
            ->assertSee('owner@example.com')
            ->assertSee('/s/guest-1')
            ->assertSee('Tamu')
            ->assertSee('Nonaktif');
    }

    public function test_admin_audit_log_table_lists_rows(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'user.block',
            'subject_type' => User::class,
            'subject_id' => $target->id,
            'changes' => ['blocked_at' => [null, now()->toDateTimeString()]],
            'reason' => 'Spam berulang',
            'ip' => '203.0.113.9',
        ]);

        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk()
            ->assertSee('root@example.com')
            ->assertSee('Spam berulang')
            ->assertSee('User')
            ->assertSee(substr($target->id, 0, 8));
    }
}
