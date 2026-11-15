<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_requires_recent_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)->put(route('user-profile-information.update'), ['name' => 'Baru', 'email' => 'new@example.com'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'email');

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_name_change_does_not_require_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)->put(route('user-profile-information.update'), ['name' => 'Nama Baru', 'email' => 'old@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', $user->fresh()->name);
    }

    public function test_confirmed_email_change_resets_verification_and_sends_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-profile-information.update'), ['name' => $user->name, 'email' => 'New@Example.com'])
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('new@example.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        Notification::assertSentTo($fresh, VerifyEmail::class);
    }

    public function test_google_only_user_gets_google_confirmation_message(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-x']);

        $this->actingAs($user)->put(route('user-profile-information.update'), ['name' => 'X', 'email' => 'other@example.com'])
            ->assertSessionHasErrorsIn('updateProfileInformation', ['email' => 'Konfirmasi ulang lewat Google sebelum mengganti email.']);
    }
}
