<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthEdgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    private function mockGoogle(string $id, string $email, bool $verified = true, string $avatar = 'https://lh3.example.com/a.png'): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
            ->map(['id' => $id, 'name' => 'Budi', 'email' => $email, 'avatar' => $avatar]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_unverified_google_email_does_not_link_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'ani@example.com']);
        $this->mockGoogle('g-20', 'ani@example.com', verified: false);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertNull($user->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_email_already_linked_to_other_google_account_rejected(): void
    {
        $user = User::factory()->create(['email' => 'cici@example.com', 'google_id' => 'g-old']);
        $this->mockGoogle('g-new', 'cici@example.com');

        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');

        $this->assertSame('g-old', $user->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_linking_verified_local_account_keeps_password(): void
    {
        $user = User::factory()->create(['email' => 'dodi@example.com']);
        $hash = $user->password;
        $this->mockGoogle('g-21', 'dodi@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame('g-21', $user->fresh()->google_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_blocked_user_by_google_id_rejected_without_mutation(): void
    {
        $user = User::factory()->blocked()->create(['google_id' => 'g-22', 'remember_token' => null]);
        $before = $user->fresh()->getAttributes();
        $this->mockGoogle('g-22', $user->email);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertEquals($before, $user->fresh()->getAttributes());
        $this->assertGuest();
    }

    public function test_new_user_without_remember_gets_no_remember_cookie(): void
    {
        $this->mockGoogle('g-23', 'eko@example.com');

        $response = $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringStartsNotWith('remember_web_', $cookie->getName());
        }
        $this->assertFalse(User::where('email', 'eko@example.com')->first()->hasRole('superadmin'));
    }

    public function test_remember_sets_remember_cookie(): void
    {
        $this->mockGoogle('g-24', 'fani@example.com');

        $this->get(route('auth.google.redirect', ['remember' => 1]));
        $response = $this->get(route('auth.google.callback'));

        $names = array_map(fn ($c) => $c->getName(), $response->headers->getCookies());
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'remember_web_')));
    }

    public function test_non_https_avatar_is_dropped(): void
    {
        $this->mockGoogle('g-25', 'gita@example.com', avatar: 'javascript:alert(1)');

        $this->get(route('auth.google.callback'));

        $this->assertNull(User::where('email', 'gita@example.com')->first()->avatar_url);
    }

    public function test_linked_superadmin_lands_on_admin(): void
    {
        $admin = User::factory()->create(['google_id' => 'g-26'])->assignRole('superadmin');
        $this->mockGoogle('g-26', $admin->email);

        $this->get(route('auth.google.callback'))->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    // Window = auth.password_timeout (900 s since iteration 1b).
    public function test_set_password_window_follows_password_timeout(): void
    {
        $timeout = (int) config('auth.password_timeout');
        $this->assertSame(900, $timeout);
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-27']);
        $payload = ['password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123'];

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time() - $timeout - 1])
            ->put(route('user-password.update'), $payload)
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->assertNull($user->fresh()->password);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time() - $timeout + 100])
            ->put(route('user-password.update'), $payload)
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasPassword());
    }

    public function test_user_with_password_still_needs_current_password(): void
    {
        $user = User::factory()->create(['google_id' => 'g-28']);
        $hash = $user->password;

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), ['password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123'])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_confirm_password_page_offers_google_for_passwordless_user(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-29']);

        $this->actingAs($user)->get(route('password.confirm'))
            ->assertOk()
            ->assertSee(route('auth.google.confirm'), false);
    }

    public function test_confirm_password_with_correct_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'password'])->assertSessionHasNoErrors();

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_google_confirm_requires_auth(): void
    {
        $this->get(route('auth.google.confirm'))->assertRedirect(route('login'));
    }
}
