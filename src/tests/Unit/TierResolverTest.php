<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use App\Services\Tier\TierResolver;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    private function resolve(?User $user): string
    {
        return app(TierResolver::class)->forUser($user)->value;
    }

    // No (verified) account: Free limits, counted per guest cookie and IP.
    private function assertAnonymousFree(?User $user): void
    {
        $tier = app(TierResolver::class)->forUser($user);

        $this->assertSame(['free', true, 'Free'], [$tier->value, $tier->isGuest(), $tier->label()]);
    }

    public function test_resolves_tiers(): void
    {
        $this->assertAnonymousFree(null);
        $this->assertAnonymousFree(User::factory()->unverified()->make());
        $this->assertFalse(app(TierResolver::class)->forUser(User::factory()->make())->isGuest());
        $this->assertSame('free', $this->resolve(User::factory()->make()));
        $this->assertSame('pro', $this->resolve(User::factory()->pro()->make()));
        $this->assertSame('free', $this->resolve(User::factory()->make(['plan' => 'pro', 'plan_until' => now()->subDay()])));
    }

    public function test_for_account_ignores_verification(): void
    {
        $user = User::factory()->unverified()->pro()->make();

        $this->assertSame('pro', app(TierResolver::class)->forAccount($user)->value);
    }

    public function test_expiry_and_unknown_plans_fall_back_to_free(): void
    {
        $this->assertSame('free', $this->resolve(User::factory()->make(['plan' => 'pro', 'plan_until' => now()->subMinute()])));
        $this->assertSame('free', $this->resolve(User::factory()->make(['plan' => 'bogus', 'plan_until' => now()->addDay()])));
        // Removed tier must not resolve to a paid one.
        $this->assertSame('free', $this->resolve(User::factory()->make(['plan' => 'pro_plus', 'plan_until' => now()->addDay()])));
        // A system slug stored in users.plan is never paid.
        $this->assertSame('free', $this->resolve(User::factory()->make(['plan' => 'guest', 'plan_until' => now()->addDay()])));
        $this->assertAnonymousFree(User::factory()->unverified()->pro()->make());
    }

    public function test_admin_created_plan_is_a_paid_tier(): void
    {
        Plan::create(['slug' => 'bisnis', 'name' => 'Bisnis', 'limits' => ['ads' => false], 'price_monthly' => 99000, 'price_yearly' => 990000, 'is_purchasable' => true, 'sort' => 9]);
        app(PlanLimits::class)->flush();

        $tier = app(TierResolver::class)->forUser(User::factory()->make(['plan' => 'bisnis', 'plan_until' => now()->addDay()]));

        $this->assertSame('bisnis', $tier->value);
        $this->assertSame('Bisnis', $tier->label());
        $this->assertTrue($tier->isPaid());
        $this->assertSame(['ads' => false], app(PlanLimits::class)->for($tier));
    }

    public function test_is_paid(): void
    {
        $this->assertFalse(Tier::guest()->isPaid());
        $this->assertFalse(Tier::free()->isPaid());
        $this->assertTrue(app(PlanLimits::class)->paidTier('pro')->isPaid());
        $this->assertNull(app(PlanLimits::class)->paidTier('pro_plus'));
        $this->assertNull(app(PlanLimits::class)->paidTier('free'));
    }
}
