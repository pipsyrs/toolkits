<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorQaTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private const OTHER_SECRET = 'KRSXG5CTMVRXEZLU';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    private function otp(string $secret = self::SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function twoFactorUser(array $attributes = [], string $secret = self::SECRET): User
    {
        return User::factory()->withTwoFactor($secret)->create($attributes);
    }

    private function pendingUser(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(self::SECRET),
            'two_factor_confirmed_at' => null,
        ]);
    }

    private function confirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function recoveryCodes(User $user): array
    {
        return json_decode(Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_recovery_codes), true);
    }

    private function adminRole(): Role
    {
        return Role::findOrCreate('moderator', 'web')->givePermissionTo(['admin.access', 'links.view']);
    }

    private function mockGoogle(string $id, string $email): void
    {
        $google = (new GoogleUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => true])
            ->map(['id' => $id, 'name' => 'Budi', 'email' => $email, 'avatar' => null]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function googleCallback(bool $remember = false)
    {
        return $this->withSession(['google.intent' => ['mode' => 'login', 'remember' => $remember]])
            ->get(route('auth.google.callback'));
    }

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    // Profile rendering per state.
    public function test_profile_off_state_shows_enable_button_only(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('Autentikasi dua langkah')
            ->assertSee('Nonaktif')
            ->assertSee('action="'.route('two-factor.enable').'"', false)
            ->assertSee('Aktifkan autentikasi dua langkah')
            ->assertDontSee('data:image/svg+xml', false)
            ->assertDontSee(route('two-factor.confirm'), false);
    }

    public function test_profile_pending_without_recent_confirmation_hides_qr_and_secret(): void
    {
        $this->actingAs($this->pendingUser())->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('Belum selesai')
            ->assertSee('href="'.route('password.confirm').'"', false)
            ->assertSee('Konfirmasi kata sandi untuk melanjutkan')
            ->assertDontSee(self::SECRET)
            ->assertDontSee('data:image/svg+xml', false)
            ->assertDontSee(route('two-factor.confirm'), false);
    }

    public function test_profile_pending_for_google_only_user_links_google_confirm(): void
    {
        $user = $this->pendingUser(['password' => null, 'google_id' => 'g-pending']);

        $this->actingAs($user)->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('href="'.route('auth.google.confirm').'"', false)
            ->assertSee('Konfirmasi dengan Google untuk melanjutkan')
            ->assertDontSee(self::SECRET);
    }

    public function test_profile_pending_with_recent_confirmation_shows_qr_secret_and_confirm_form(): void
    {
        $user = $this->pendingUser();

        $this->actingAs($user)->withSession($this->confirmed())->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('src="data:image/svg+xml;base64,', false)
            ->assertSee(self::SECRET)
            ->assertSee('action="'.route('two-factor.confirm').'"', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('Batalkan penyiapan');

        $this->actingAs($user)->withSession($this->confirmed())->from(route('dashboard.profile'))
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertRedirect(route('dashboard.profile'));

        $this->actingAs($user)->withSession($this->confirmed())->get(route('dashboard.profile'))
            ->assertSee('field-error', false);
    }

    public function test_profile_on_state_hides_secret_and_recovery_codes_by_default(): void
    {
        $user = $this->twoFactorUser();
        $codes = $this->recoveryCodes($user);

        $this->actingAs($user)->withSession($this->confirmed())->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('Aktif sejak')
            ->assertSee('Tampilkan kode pemulihan')
            ->assertSee('action="'.route('two-factor.regenerate-recovery-codes').'"', false)
            ->assertSee('Nonaktifkan autentikasi dua langkah')
            ->assertDontSee(self::SECRET)
            ->assertDontSee($codes[0])
            ->assertDontSee('data:image/svg+xml', false);
    }

    public function test_profile_on_state_shows_codes_only_right_after_confirm_or_regenerate(): void
    {
        $user = $this->twoFactorUser();
        $codes = $this->recoveryCodes($user);

        foreach ([Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED, Fortify::RECOVERY_CODES_GENERATED] as $status) {
            $this->actingAs($user)->withSession(['status' => $status])->get(route('dashboard.profile'))
                ->assertOk()
                ->assertSee($codes[0])
                ->assertDontSee(self::SECRET);
        }
    }

    public function test_challenge_page_renders_code_and_recovery_forms(): void
    {
        $user = $this->twoFactorUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertSee('action="'.route('two-factor.login.store').'"', false)
            ->assertSee('name="code"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('name="recovery_code"', false)
            ->assertDontSee(self::SECRET);
    }

    public function test_challenge_page_without_pending_login_redirects(): void
    {
        $this->get(route('two-factor.login'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('two-factor.login'))->assertRedirect();
    }

    public function test_other_pages_show_only_2fa_badge(): void
    {
        $owner = $this->twoFactorUser();
        $plain = User::factory()->create();
        $admin = User::factory()->create()->assignRole(User::SUPERADMIN_ROLE);
        $codes = $this->recoveryCodes($owner);

        $dashboard = $this->actingAs($owner)->withSession($this->confirmed())->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::SECRET, $dashboard);
        $this->assertStringNotContainsString($codes[0], $dashboard);

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'title="Autentikasi dua langkah aktif"'));
        $this->assertStringContainsString($plain->email, $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString($codes[0], $html);
    }

    // Endpoint protection.
    public function test_sensitive_endpoints_require_recent_password_confirmation(): void
    {
        $user = $this->twoFactorUser();
        $codes = $this->recoveryCodes($user);

        foreach ([[], ['auth.password_confirmed_at' => time() - 901]] as $session) {
            foreach (['two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes'] as $route) {
                $this->actingAs($user)->withSession($session)->getJson(route($route))->assertStatus(423);
                $this->actingAs($user)->withSession($session)->get(route($route))->assertRedirect(route('password.confirm'));
            }
        }

        $this->actingAs($user)->post(route('two-factor.regenerate-recovery-codes'))->assertRedirect(route('password.confirm'));
        $this->actingAs($user)->delete(route('two-factor.disable'))->assertRedirect(route('password.confirm'));

        $this->assertSame($codes, $this->recoveryCodes($user));
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_confirm_endpoint_requires_password_confirmation(): void
    {
        $user = $this->pendingUser();

        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $this->otp()])
            ->assertRedirect(route('password.confirm'));
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_guest_cannot_use_2fa_management_endpoints(): void
    {
        foreach (['two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes'] as $route) {
            $this->getJson(route($route))->assertUnauthorized();
        }

        $this->post(route('two-factor.enable'))->assertRedirect(route('login'));
        $this->delete(route('two-factor.disable'))->assertRedirect(route('login'));
    }

    public function test_endpoints_only_expose_and_change_own_2fa(): void
    {
        $owner = $this->twoFactorUser();
        $other = $this->twoFactorUser([], self::OTHER_SECRET);
        $ownerCodes = $this->recoveryCodes($owner);

        $this->actingAs($other)->withSession($this->confirmed())->getJson(route('two-factor.secret-key'))
            ->assertOk()->assertJson(['secretKey' => self::OTHER_SECRET]);

        $codes = $this->actingAs($other)->withSession($this->confirmed())->getJson(route('two-factor.recovery-codes'))->json();
        $this->assertSame($this->recoveryCodes($other), $codes);

        $this->actingAs($other)->withSession($this->confirmed())->delete(route('two-factor.disable'));
        $this->assertFalse($other->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertTrue($owner->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertSame($ownerCodes, $this->recoveryCodes($owner));
    }

    // Challenge flow.
    public function test_pending_challenge_cannot_access_protected_areas(): void
    {
        $user = $this->twoFactorUser();
        $user->assignRole($this->adminRole());

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard.profile'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_remember_is_kept_through_password_challenge(): void
    {
        $user = $this->twoFactorUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);
        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])
            ->assertCookie($this->recallerName());
    }

    public function test_no_remember_cookie_when_not_requested(): void
    {
        $user = $this->twoFactorUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])
            ->assertRedirect(route('dashboard'))
            ->assertCookieMissing($this->recallerName());
    }

    public function test_remember_is_kept_through_google_challenge(): void
    {
        $user = $this->twoFactorUser(['google_id' => 'g-rem']);
        $this->mockGoogle('g-rem', $user->email);

        $this->googleCallback(remember: true)->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])
            ->assertCookie($this->recallerName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_admin_redirects_to_admin_after_challenge(): void
    {
        $user = $this->twoFactorUser(['google_id' => 'g-adm']);
        $user->assignRole($this->adminRole());
        $this->mockGoogle('g-adm', $user->email);

        $this->googleCallback()->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_intended_url_is_honoured_after_challenge(): void
    {
        $user = $this->twoFactorUser();

        $this->get(route('dashboard.profile'))->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])->assertRedirect(route('dashboard.profile'));
    }

    public function test_invalid_codes_are_rejected(): void
    {
        $user = $this->twoFactorUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.login.store'), ['code' => 'abcdef'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.login.store'), ['code' => $this->otp(self::OTHER_SECRET)])->assertSessionHasErrors('code');
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'not-a-code'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_challenge_is_throttled_per_ip_across_accounts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->withSession(['login.id' => (string) Str::uuid()])
                ->post(route('two-factor.login.store'), ['code' => '000000'])
                ->assertStatus(302);
        }

        $user = $this->twoFactorUser();
        $this->withSession(['login.id' => $user->id])
            ->post(route('two-factor.login.store'), ['code' => $this->otp()])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        $user = $this->twoFactorUser();
        $code = $this->otp();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_blocked_user_cannot_open_challenge_page(): void
    {
        $user = $this->twoFactorUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $user->forceFill(['blocked_at' => now()])->save();

        $this->get(route('two-factor.login'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertNull(session('login.id'));
        $this->assertNull(session('login.remember'));
    }

    public function test_blocked_user_via_google_never_reaches_challenge(): void
    {
        $user = $this->twoFactorUser(['google_id' => 'g-blk', 'blocked_at' => now()]);
        $this->mockGoogle('g-blk', $user->email);

        $this->googleCallback()->assertRedirect(route('login'));
        $this->assertNull(session('login.id'));
        $this->assertGuest();
    }

    public function test_login_event_fires_only_after_successful_password_challenge(): void
    {
        Event::fake([Login::class]);
        $user = $this->twoFactorUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['code' => '000000']);
        Event::assertNotDispatched(Login::class);

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()]);
        Event::assertDispatchedTimes(Login::class, 1);
        Event::assertDispatched(Login::class, fn (Login $e) => $e->user->is($user));
    }

    public function test_login_event_fires_only_after_successful_google_challenge(): void
    {
        Event::fake([Login::class]);
        $user = $this->twoFactorUser(['google_id' => 'g-evt']);
        $this->mockGoogle('g-evt', $user->email);

        $this->googleCallback();
        Event::assertNotDispatched(Login::class);

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()]);
        Event::assertDispatched(Login::class, fn (Login $e) => $e->user->is($user));
    }

    // Recovery command.
    public function test_disable_command_force_skips_prompt_and_rotates_remember_token(): void
    {
        $user = $this->twoFactorUser(['remember_token' => 'old-token']);

        $this->artisan('users:disable-2fa', ['email' => $user->email, '--force' => true])->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
        $this->assertNotSame('old-token', $user->remember_token);

        $log = AdminAuditLog::where('action', 'user.2fa_reset_cli')->firstOrFail();
        $this->assertNull($log->admin_id);
        $this->assertSame('artisan users:disable-2fa', $log->reason);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_disable_command_on_user_without_2fa_is_noop(): void
    {
        $user = User::factory()->create();

        $this->artisan('users:disable-2fa', ['email' => $user->email, '--force' => true])->assertSuccessful();

        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_cli_audit_entry_renders_in_audit_log_page(): void
    {
        $user = $this->twoFactorUser();
        $admin = User::factory()->create()->assignRole(User::SUPERADMIN_ROLE);

        $this->artisan('users:disable-2fa', ['email' => $user->email, '--force' => true])->assertSuccessful();

        $this->actingAs($admin)->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('user.2fa_reset_cli');
    }
}
