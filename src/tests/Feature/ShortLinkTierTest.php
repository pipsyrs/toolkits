<?php

namespace Tests\Feature;

use App\Actions\Links\CreateShortLink;
use App\Exceptions\QuotaExceededException;
use App\Livewire\Tools\Shorten;
use App\Models\BlockedDomain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Services\Quota\QuotaService;
use App\Services\Tier\PlanLimits;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ShortLinkTierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config(['app.url' => 'https://toolkits.test']);
    }

    private function create(?User $user, array $data, ?string $guestId = null, string $ip = '203.0.113.5'): Link
    {
        return app(CreateShortLink::class)->handle($user, $guestId ?? 'g-fixed', $ip, $data);
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    private function setFreeLimits(array $limits): void
    {
        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $free->forceFill(['limits' => [...$free->limits, ...$limits]])->save();
        app(PlanLimits::class)->flush();
    }

    private function errorsFor(?User $user, array $data): array
    {
        try {
            $this->create($user, $data);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Expected ValidationException');
    }

    public function test_free_daily_quota_is_twenty(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->create($user, ['target_url' => 'https://example.com/'.$i]);
        }

        $this->expectException(QuotaExceededException::class);
        $this->create($user, ['target_url' => 'https://example.com/21']);
    }

    public function test_free_active_links_capped_at_hundred_excluding_inactive(): void
    {
        $user = User::factory()->create();
        Link::factory()->count(99)->for($user)->create();
        Link::factory()->for($user)->expired()->create();
        Link::factory()->for($user)->disabled()->create();

        $this->create($user, ['target_url' => 'https://example.com/100th']);

        $this->assertArrayHasKey('target_url', $this->errorsFor($user, ['target_url' => 'https://example.com/101st']));
        $this->assertSame(102, Link::where('user_id', $user->id)->count());
    }

    public function test_free_cannot_set_custom_expiry_and_gets_no_expiry(): void
    {
        $user = User::factory()->create();

        $this->assertArrayHasKey('expires_at', $this->errorsFor($user, [
            'target_url' => 'https://example.com', 'expires_at' => now()->addDays(3)->toDateTimeString(),
        ]));

        $this->assertNull($this->create($user, ['target_url' => 'https://example.com'])->expires_at);
    }

    public function test_pro_custom_expiry_bounds(): void
    {
        $pro = User::factory()->pro()->create();

        $link = $this->create($pro, ['target_url' => 'https://example.com/a', 'expires_at' => now()->addDays(7)->format('Y-m-d H:i')]);
        $this->assertSame('pro', $link->created_tier);
        $this->assertTrue($link->expires_at->between(now()->addDays(6), now()->addDays(8)));

        $this->assertArrayHasKey('expires_at', $this->errorsFor($pro, [
            'target_url' => 'https://example.com/b', 'expires_at' => now()->subDay()->toDateTimeString(),
        ]));
        $this->assertArrayHasKey('expires_at', $this->errorsFor($pro, [
            'target_url' => 'https://example.com/c', 'expires_at' => now()->addYears(5)->addDays(2)->toDateTimeString(),
        ]));
    }

    public function test_pro_alias_unlimited_and_no_expiry_by_default(): void
    {
        $pro = User::factory()->pro()->create();

        for ($i = 1; $i <= 6; $i++) {
            $link = $this->create($pro, ['target_url' => 'https://example.com/'.$i, 'alias' => 'pro-'.$i]);
        }

        $this->assertTrue($link->is_custom);
        $this->assertNull($link->expires_at);
    }

    public function test_pro_daily_links_capped_by_fair_use(): void
    {
        $pro = User::factory()->pro()->create();
        app(QuotaService::class)->reserve(['u:'.$pro->id], 'links', 999, ['u:'.$pro->id => 1000]);

        $this->create($pro, ['target_url' => 'https://example.com/1000th']);

        $this->expectException(QuotaExceededException::class);
        $this->create($pro, ['target_url' => 'https://example.com/1001st']);
    }

    public function test_guest_ip_limit_applies_across_guest_ids(): void
    {
        // Visitors without an account use Free: IP cap = links_per_day × ip_limit_multiplier (3 × 3).
        $this->setFreeLimits(['links_per_day' => 3]);

        for ($g = 0; $g < 3; $g++) {
            for ($i = 0; $i < 3; $i++) {
                $this->create(null, ['target_url' => "https://example.com/{$g}/{$i}"], 'g-'.$g);
            }
        }

        $this->expectException(QuotaExceededException::class);
        $this->create(null, ['target_url' => 'https://example.com/new'], 'g-fresh');
    }

    public function test_unverified_user_is_treated_as_guest(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertArrayHasKey('alias', $this->errorsFor($user, ['target_url' => 'https://example.com', 'alias' => 'promo']));
        $this->assertSame('guest', $this->create($user, ['target_url' => 'https://example.com'])->created_tier);
    }

    public function test_duplicate_alias_rejected_case_insensitively(): void
    {
        $this->create(User::factory()->create(), ['target_url' => 'https://example.com', 'alias' => 'promo']);

        $other = User::factory()->create();
        $this->assertArrayHasKey('alias', $this->errorsFor($other, ['target_url' => 'https://example.com', 'alias' => 'promo']));
        $this->assertArrayHasKey('alias', $this->errorsFor($other, ['target_url' => 'https://example.com', 'alias' => 'PROMO']));
        $this->assertSame(1, Link::where('code', 'promo')->count());
    }

    public function test_reserved_and_malformed_aliases_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['login', 'dashboard', 's', 'livewire', 'ab', '-abc', 'abc-', 'ab_cd', str_repeat('a', 31)] as $alias) {
            $this->assertArrayHasKey('alias', $this->errorsFor($user, ['target_url' => 'https://example.com', 'alias' => $alias]), $alias);
        }

        $this->assertSame(0, Link::count());
    }

    public function test_dangerous_targets_rejected(): void
    {
        BlockedDomain::create(['domain' => 'evil.com']);
        $user = User::factory()->create();

        foreach ([
            'JavaScript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'ftp://example.com/x', 'vbscript:msgbox',
            'http://10.0.0.1/', 'http://192.168.1.1/', 'http://169.254.169.254/latest', 'http://[::1]/', 'https://evil.com',
            'https://EVIL.com/x', 'https://a.b.evil.com', 'https://www.toolkits.test/x', 'https://example.com/'.str_repeat('a', 2050),
            '//example.com', 'https://',
        ] as $url) {
            $this->assertArrayHasKey('target_url', $this->errorsFor($user, ['target_url' => $url]), $url);
        }

        $this->assertSame(0, Link::count());
    }

    public function test_blocked_domain_does_not_block_lookalike(): void
    {
        BlockedDomain::create(['domain' => 'evil.com']);

        $link = $this->create(User::factory()->create(), ['target_url' => 'https://notevil.com/x']);

        $this->assertSame('notevil.com', $link->target_host);
    }

    public function test_rejected_link_does_not_consume_quota(): void
    {
        $user = User::factory()->create();

        $this->errorsFor($user, ['target_url' => 'javascript:alert(1)']);

        $this->assertSame(20, app(QuotaService::class)->remaining(['u:'.$user->id], 'links', ['u:'.$user->id => 20]));
    }

    public function test_shorten_component_reports_quota_error(): void
    {
        // Livewire::test has no tk_gid cookie, so the per-IP cap (3 x 3) is what binds.
        $this->setFreeLimits(['links_per_day' => 3]);

        for ($i = 0; $i < 9; $i++) {
            Livewire::test(Shorten::class)->set('targetUrl', 'https://example.com/'.$i)->call('shorten')->assertHasNoErrors();
        }

        Livewire::test(Shorten::class)
            ->set('targetUrl', 'https://example.com/x')
            ->call('shorten')
            ->assertHasErrors('quota')
            ->assertSet('result', null);
    }

    public function test_guest_interstitial_shows_target_and_counts_click(): void
    {
        $this->setFreeLimits(['interstitial' => true]);
        $link = Link::factory()->guest()->create(['target_url' => 'https://example.com/landing']);

        $this->get('/s/'.$link->code)
            ->assertOk()
            ->assertSee('https://example.com/landing')
            ->assertSee('content="5;url=https://example.com/landing"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $fresh = $link->fresh();
        $this->assertSame(1, $fresh->clicks_total);
        $this->assertNotNull($fresh->last_clicked_at);
    }

    public function test_redirect_is_lean_and_not_cached(): void
    {
        $link = Link::factory()->create();

        $response = $this->get('/s/'.$link->code)->assertStatus(302);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertEmpty($response->headers->getCookies(), 'short redirect must not set cookies');
    }

    public function test_unavailable_links_do_not_count_clicks(): void
    {
        $expired = Link::factory()->expired()->create();
        $disabled = Link::factory()->disabled()->create();

        $this->get('/s/'.$expired->code)->assertStatus(410);
        $this->get('/s/'.$disabled->code)->assertNotFound();

        $this->assertSame(0, $expired->fresh()->clicks_total);
        $this->assertSame(0, $disabled->fresh()->clicks_total);
    }

    public function test_multiple_clicks_accumulate(): void
    {
        $link = Link::factory()->create();

        $this->get('/s/'.$link->code);
        $this->get('/s/'.$link->code);
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/s/'.$link->code);

        $this->assertSame(2, $link->fresh()->clicks_total);
    }

    public function test_redirect_code_pattern_rejects_bad_codes(): void
    {
        $this->get('/s/ab')->assertNotFound();
        $this->get('/s/'.Str::repeat('a', 31))->assertNotFound();
        $this->get('/s/abc%27or1')->assertNotFound();
    }
}
