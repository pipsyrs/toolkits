<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    // One provider mock serves both redirect() and user().
    private function mockGoogle(string $id, string $email, bool $verified = true): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
            ->map(['id' => $id, 'name' => 'Budi', 'email' => $email, 'avatar' => 'https://lh3.example.com/a.png']);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirects_to_google(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect'))->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_new_user_is_created_verified_without_password(): void
    {
        Event::fake([Login::class]);
        $this->mockGoogle('g-1', 'Budi@Example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame('g-1', $user->google_id);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(Login::class);
    }

    public function test_links_existing_account_when_google_email_verified(): void
    {
        $user = User::factory()->create(['email' => 'ani@example.com']);
        $this->mockGoogle('g-2', 'ani@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame('g-2', $user->fresh()->google_id);
        $this->assertNotNull($user->fresh()->password);
        $this->assertSame(1, User::count());
    }

    public function test_linking_unverified_local_account_drops_its_password(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'pre@example.com']);
        $this->mockGoogle('g-3', 'pre@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertNull($user->fresh()->password);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_rejects_unverified_google_email(): void
    {
        $this->mockGoogle('g-4', 'x@example.com', verified: false);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_rejects_blocked_user(): void
    {
        User::factory()->blocked()->create(['email' => 'b@example.com', 'google_id' => 'g-5']);
        $this->mockGoogle('g-5', 'b@example.com');

        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_superadmin_redirects_to_admin(): void
    {
        User::factory()->create(['email' => 'sa@example.com', 'google_id' => 'g-6'])->assignRole('superadmin');
        $this->mockGoogle('g-6', 'sa@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_invalid_state_returns_error(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_only_user_cannot_password_login(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-7']);

        $this->post('/login', ['email' => $user->email, 'password' => ''])->assertSessionHasErrors();
        $this->post('/login', ['email' => $user->email, 'password' => 'anything'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_google_only_user_sets_password_only_after_recent_reauth(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-8']);
        $payload = ['password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123'];

        $this->actingAs($user)->put(route('user-password.update'), $payload)
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->assertNull($user->fresh()->password);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), $payload)
            ->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->password);
    }

    public function test_password_confirm_does_not_error_for_google_only_user(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-9']);

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'x'])
            ->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));
    }

    public function test_confirm_via_google_marks_password_confirmed(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-10']);
        $this->mockGoogle('g-10', $user->email);

        $this->actingAs($user)->withSession(['google.intent' => ['mode' => 'confirm']])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_blocked_local_account_is_not_modified_when_linking(): void
    {
        $user = User::factory()->unverified()->blocked()->create(['email' => 'blk@example.com']);
        $hash = $user->password;
        $this->mockGoogle('g-11', 'blk@example.com');

        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');

        $fresh = $user->fresh();
        $this->assertNull($fresh->google_id);
        $this->assertNull($fresh->email_verified_at);
        $this->assertSame($hash, $fresh->password);
        $this->assertGuest();
    }

    public function test_superadmin_is_not_auto_linked_by_email(): void
    {
        $admin = User::factory()->create(['email' => 'root@example.com'])->assignRole('superadmin');
        $this->mockGoogle('g-12', 'root@example.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google' => 'Masuk dengan password lalu hubungkan Google dari profil.']);

        $this->assertNull($admin->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_remember_flag_is_stored_and_used(): void
    {
        $user = User::factory()->create(['google_id' => 'g-13', 'remember_token' => null]);
        $this->mockGoogle('g-13', $user->email);

        $this->get(route('auth.google.redirect', ['remember' => 1]));
        $this->assertTrue(session('google.intent')['remember']);

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_confirm_failure_returns_to_password_confirm_and_stays_logged_in(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-14']);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->actingAs($user)->withSession(['google.intent' => ['mode' => 'confirm']])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHasErrors('google');

        $this->assertAuthenticatedAs($user);
    }

    public function test_confirm_returns_to_originating_page(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-15']);
        $this->mockGoogle('g-15', $user->email);

        $this->actingAs($user)->from(route('dashboard.profile'))->get(route('auth.google.confirm'));
        $this->actingAs($user)->get(route('auth.google.callback'))->assertRedirect(route('dashboard.profile'));
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }
}
