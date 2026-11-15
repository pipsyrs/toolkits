<?php

namespace Tests\Feature;

use App\Actions\Admin\SaveRole;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    private function otp(string $secret = self::SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function twoFactorUser(array $attributes = []): User
    {
        return User::factory()->withTwoFactor(self::SECRET)->create($attributes);
    }

    private function confirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function recoveryCodes(User $user): array
    {
        return json_decode(Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_recovery_codes), true);
    }

    private function fakeSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time(),
        ]);
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

    public function test_enable_requires_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect(route('password.confirm'));
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_enable_confirm_and_disable_flow_with_audit(): void
    {
        $user = User::factory()->create();
        $this->fakeSession($user, 'other-session');

        $this->actingAs($user)->withSession($this->confirmed())->post(route('two-factor.enable'))->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        $this->actingAs($user)->withSession($this->confirmed())
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user)->withSession($this->confirmed())
            ->post(route('two-factor.confirm'), ['code' => $this->otp($secret)])
            ->assertSessionHas('status', Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED);
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.2fa_enabled', 'admin_id' => $user->id, 'subject_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);

        $this->actingAs($user)->withSession($this->confirmed())->delete(route('two-factor.disable'))->assertRedirect();
        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.2fa_disabled', 'subject_id' => $user->id]);
    }

    public function test_cancelling_unconfirmed_setup_is_not_audited_as_disable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->confirmed())->post(route('two-factor.enable'));
        $this->actingAs($user)->withSession($this->confirmed())->delete(route('two-factor.disable'));

        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'user.2fa_disabled']);
    }

    public function test_google_only_user_can_enable_after_google_confirmation(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-only']);

        // Session stamp is what auth.google.confirm sets.
        $this->actingAs($user)->withSession($this->confirmed())->post(route('two-factor.enable'))->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_secret_and_qr_never_leak_to_other_users(): void
    {
        $owner = $this->twoFactorUser();
        $other = User::factory()->create();
        $admin = User::factory()->create()->assignRole(User::SUPERADMIN_ROLE);
        $codes = $this->recoveryCodes($owner);

        foreach (['two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes'] as $route) {
            $content = $this->actingAs($other)->withSession($this->confirmed())->getJson(route($route))->getContent();
            $this->assertStringNotContainsString(self::SECRET, $content);
            $this->assertStringNotContainsString($codes[0], $content);
        }

        $this->assertArrayNotHasKey('two_factor_secret', $owner->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $owner->toArray());

        $html = $this->actingAs($admin)->get('/admin/users')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString($owner->two_factor_secret, $html);
        $this->assertStringNotContainsString($codes[0], $html);
    }

    public function test_password_login_is_challenged_and_admin_goes_to_admin(): void
    {
        $user = $this->twoFactorUser();
        $user->assignRole(Role::findOrCreate('moderator', 'web')->givePermissionTo(['admin.access', 'links.view']));

        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));

        $this->get(route('two-factor.login'))->assertOk();

        $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_recovery_code_is_single_use(): void
    {
        $user = $this->twoFactorUser();
        $code = $this->recoveryCodes($user)[0];

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($code, $this->recoveryCodes($user));
        $this->assertCount(8, $this->recoveryCodes($user));

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => $code])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_regenerate_recovery_codes(): void
    {
        $user = $this->twoFactorUser();
        $old = $this->recoveryCodes($user);

        $this->actingAs($user)->withSession($this->confirmed())->post(route('two-factor.regenerate-recovery-codes'))->assertRedirect();

        $this->assertEmpty(array_intersect($old, $this->recoveryCodes($user)));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.2fa_recovery_regenerated']);
    }

    public function test_challenge_is_throttled(): void
    {
        $user = $this->twoFactorUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertStatus(302);
        }

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_user_blocked_after_first_factor_cannot_pass_challenge(): void
    {
        $user = $this->twoFactorUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));

        $user->forceFill(['blocked_at' => now()])->save();

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('login.id'));
    }

    public function test_google_login_is_challenged_when_2fa_enabled(): void
    {
        $user = $this->twoFactorUser(['google_id' => 'g-2fa']);
        $this->mockGoogle('g-2fa', $user->email);

        $this->withSession(['google.intent' => ['mode' => 'login', 'remember' => true]])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
        $this->assertTrue(session('login.remember'));

        $this->post(route('two-factor.login.store'), ['code' => $this->otp()])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_login_without_2fa_is_not_challenged(): void
    {
        $user = User::factory()->create(['google_id' => 'g-plain']);
        $this->mockGoogle('g-plain', $user->email);

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_disable_command_resets_2fa_sessions_and_audits(): void
    {
        $user = $this->twoFactorUser();
        $this->fakeSession($user, 'sess-1');

        $this->artisan('users:disable-2fa', ['email' => strtoupper($user->email)])
            ->expectsConfirmation("Nonaktifkan 2FA untuk {$user->email} dan keluarkan semua sesinya?", 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-1']);

        $log = AdminAuditLog::where('action', 'user.2fa_reset_cli')->firstOrFail();
        $this->assertNull($log->admin_id);
        $this->assertSame($user->id, $log->subject_id);

        $this->artisan('users:disable-2fa', ['email' => 'missing@example.com'])->assertFailed();
    }

    public function test_disable_command_can_be_cancelled(): void
    {
        $user = $this->twoFactorUser();

        $this->artisan('users:disable-2fa', ['email' => $user->email])
            ->expectsConfirmation("Nonaktifkan 2FA untuk {$user->email} dan keluarkan semua sesinya?", 'no')
            ->assertFailed();

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_password_confirm_link_returns_to_previous_page(): void
    {
        $user = User::factory()->create();
        $profile = route('dashboard.profile');

        $this->actingAs($user)->withHeader('referer', $profile)->get(route('password.confirm'))->assertOk();
        $this->assertSame($profile, session('url.intended'));

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'password'])->assertRedirect($profile);
    }

    public function test_password_confirm_keeps_existing_intended_and_ignores_self_referer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withHeader('referer', route('password.confirm'))->get(route('password.confirm'));
        $this->assertNull(session('url.intended'));

        $this->actingAs($user)->withSession(['url.intended' => route('admin.settings.edit')])
            ->withHeader('referer', route('dashboard.profile'))
            ->get(route('password.confirm'));
        $this->assertSame(route('admin.settings.edit'), session('url.intended'));
    }

    // QA F2 follow-ups.
    public function test_save_role_without_changes_is_not_audited(): void
    {
        $admin = User::factory()->create()->assignRole(User::SUPERADMIN_ROLE);
        session($this->confirmed());

        $role = app(SaveRole::class)->handle($admin, null, 'viewer', ['links.view'], null);
        app(SaveRole::class)->handle($admin, $role->fresh(), 'viewer', ['links.view', 'admin.access'], null);

        $this->assertSame(0, AdminAuditLog::where('action', 'role.updated')->count());
    }

    public function test_tampered_types_give_validation_errors_not_500(): void
    {
        $admin = User::factory()->create()->assignRole(User::SUPERADMIN_ROLE);
        $this->actingAs($admin);
        session($this->confirmed());

        Livewire::test(RolesIndex::class)->set('selected', 'not-an-array')->assertStatus(422);
        Livewire::test(RolesIndex::class)->set('name', ['x'])->assertStatus(422);
        Livewire::test(RolesIndex::class)->set('selected.0', ['nested'])->assertStatus(422);
        Livewire::test(RolesIndex::class)->set('selected', ['links.view'])->assertOk()->assertSet('selected', ['links.view']);

        Livewire::test(UsersIndex::class)->set('roleSelection', 'x')->assertStatus(422);
        Livewire::test(UsersIndex::class)->call('editRoles', ['x'])->assertHasErrors('userId');
        Livewire::test(UsersIndex::class)->call('block', ['x'], 'reason')->assertHasErrors('userId');

        Livewire::test(RolesIndex::class)->call('delete', ['x'])->assertHasErrors('roleId');
    }
}
