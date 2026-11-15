<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function pro(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'pro',
            'plan_until' => now()->addDays($days),
        ]);
    }

    public function blocked(string $reason = 'Test block'): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ]);
    }

    // Confirmed 2FA with a real TOTP secret so tests can generate valid codes.
    public function withTwoFactor(?string $secret = null): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
                $secret ?? app(TwoFactorAuthenticationProvider::class)->generateSecretKey()
            ),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(
                Collection::times(8, fn () => RecoveryCode::generate())->all()
            )),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
