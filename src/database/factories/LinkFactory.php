<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    public function definition(): array
    {
        $host = fake()->domainName();

        return [
            'user_id' => User::factory(),
            'guest_id' => null,
            'code' => Str::lower(Str::random(7)),
            'is_custom' => false,
            'target_url' => 'https://'.$host.'/'.fake()->slug(),
            'target_host' => $host,
            'expires_at' => null,
            'created_tier' => 'free',
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_id' => (string) Str::uuid(),
            'expires_at' => now()->addDays(30),
            'created_tier' => 'guest',
        ]);
    }

    public function custom(?string $alias = null): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $alias ?? Str::lower(fake()->unique()->bothify('alias-####')),
            'is_custom' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function disabled(string $reason = 'Test disable'): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled_at' => now(),
            'disabled_reason' => $reason,
        ]);
    }
}
