<?php

namespace Tests\Feature;

use App\Actions\Admin\BlockUser;
use App\Models\BlockedDomain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    // Free has no interstitial by default; these tests need the page.
    private function enableInterstitial(): void
    {
        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $free->forceFill(['limits' => [...$free->limits, 'interstitial' => true]])->save();
        app(PlanLimits::class)->flush();
    }

    private function dbSession(User $user, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    }

    public function test_blocked_user_is_logged_out_on_tools_and_password_routes(): void
    {
        $user = User::factory()->blocked()->create();

        $this->actingAs($user)->get('/tools/shorten')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password', 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_blocked_user_with_remember_cookie_is_not_relogged_on_tools(): void
    {
        $user = User::factory()->blocked()->create(['remember_token' => 'remember-me']);
        $cookie = $user->id.'|remember-me|'.$user->password;

        $this->withCookie(auth()->guard('web')->getRecallerName(), $cookie)
            ->get('/tools/shorten')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_blocked_user_livewire_update_on_tool_is_rejected(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get('/tools/shorten')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m);

        $user->forceFill(['blocked_at' => now()])->save();

        $this->actingAs($user->fresh())->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => html_entity_decode($m[1], ENT_QUOTES),
                'updates' => ['targetUrl' => 'https://example.com'],
                'calls' => [['method' => 'shorten', 'params' => [], 'metadata' => []]],
            ]],
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Link::count());
    }

    public function test_block_rotates_remember_token_and_deletes_sessions(): void
    {
        $admin = User::factory()->create()->assignRole('superadmin');
        $target = User::factory()->create(['remember_token' => 'old-token']);
        $this->dbSession($target, 's-a');
        $this->dbSession($target, 's-b');

        app(BlockUser::class)->handle($admin, $target, 'Spam link');

        $this->assertNotSame('old-token', $target->fresh()->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_superadmin_seeder_creates_new_account(): void
    {
        config(['toolkits.superadmin.email' => 'Root@Example.com', 'toolkits.superadmin.password' => 'Secret-12345']);

        $this->seed(SuperadminSeeder::class);

        $user = User::where('email', 'root@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('superadmin'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Secret-12345', $user->password));
    }

    public function test_superadmin_seeder_refuses_to_promote_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'victim@example.com']);
        config(['toolkits.superadmin.email' => 'victim@example.com', 'toolkits.superadmin.password' => 'Secret-12345']);

        try {
            $this->seed(SuperadminSeeder::class);
            $this->fail('Seeder should abort.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('refusing to promote', $e->getMessage());
        }

        $this->assertFalse($user->fresh()->hasRole('superadmin'));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_superadmin_seeder_is_idempotent_for_existing_superadmin(): void
    {
        $admin = User::factory()->create(['email' => 'sa@example.com'])->assignRole('superadmin');
        config(['toolkits.superadmin.email' => 'sa@example.com', 'toolkits.superadmin.password' => 'Other-12345']);

        $this->seed(SuperadminSeeder::class);

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_sensitive_password_routes_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'a@example.com'])->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'a@example.com'])->assertStatus(429);

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'wrong']);
        }

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'wrong'])->assertStatus(429);
    }

    public function test_password_update_route_is_throttled(): void
    {
        $user = User::factory()->create();
        $payload = ['current_password' => 'wrong', 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123'];

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->put(route('user-password.update'), $payload)->assertStatus(302);
        }

        $this->actingAs($user)->put(route('user-password.update'), $payload)->assertStatus(429);
    }

    public function test_password_change_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $this->dbSession($user, 'other-device');

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password', 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_password_reset_revokes_all_sessions(): void
    {
        $user = User::factory()->create();
        $this->dbSession($user, 'device-1');
        $token = app('auth.password.broker')->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'device-1']);
    }

    public function test_redirect_rechecks_blocked_domains_with_cache_invalidation(): void
    {
        $link = Link::factory()->create(['target_url' => 'https://bad.example.org/x', 'target_host' => 'bad.example.org']);

        $this->get('/s/'.$link->code)->assertStatus(302);

        $domain = BlockedDomain::create(['domain' => 'example.org']);
        $this->get('/s/'.$link->code)->assertNotFound();

        $domain->delete();
        $this->get('/s/'.$link->code)->assertStatus(302);
    }

    public function test_interstitial_shows_ascii_host(): void
    {
        $this->enableInterstitial();
        $link = Link::factory()->guest()->create(['target_url' => 'https://Example.com/a', 'target_host' => 'example.com']);

        $this->get('/s/'.$link->code)->assertOk()
            ->assertViewHas('displayHost', 'example.com')
            ->assertViewHas('isIdn', false)
            ->assertViewHas('unicodeHost', null)
            ->assertSee('example.com');
    }

    public function test_interstitial_shows_punycode_for_idn_host(): void
    {
        $this->enableInterstitial();
        $link = Link::factory()->guest()->create([
            'target_url' => 'https://xn--80ak6aa92e.com/login',
            'target_host' => 'xn--80ak6aa92e.com',
        ]);

        $this->get('/s/'.$link->code)->assertOk()
            ->assertViewHas('displayHost', 'xn--80ak6aa92e.com')
            ->assertViewHas('isIdn', true)
            ->assertViewHas('unicodeHost', 'аррӏе.com')
            ->assertSee('xn--80ak6aa92e.com');
    }
}
