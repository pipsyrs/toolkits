<?php

namespace Tests\Feature;

use App\Livewire\Tools\QrCode;
use App\Livewire\Tools\Shorten;
use App\Models\Plan;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_no_banner_ad_and_gate_rendered_for_plans_with_ads(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('aria-labelledby="ad-gate-title"', false)
            ->assertDontSee('data-ad-position="top"', false);

        $this->actingAs(User::factory()->create())->get('/tools/shorten')->assertOk()
            ->assertSee('aria-labelledby="ad-gate-title"', false);
    }

    public function test_pro_gets_no_gate(): void
    {
        $this->actingAs(User::factory()->pro()->create())->get('/tools/qr-code')->assertOk()
            ->assertDontSee('aria-labelledby="ad-gate-title"', false)
            ->assertDontSee('data-ad-position', false);
    }

    public function test_gate_follows_admin_ads_limit(): void
    {
        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $free->forceFill(['limits' => [...$free->limits, 'ads' => false]])->save();
        app(PlanLimits::class)->flush();

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertDontSee('aria-labelledby="ad-gate-title"', false);
    }

    public function test_operations_dispatch_only_on_success(): void
    {
        Livewire::test(Shorten::class)->set('targetUrl', '')->call('shorten')
            ->assertHasErrors('targetUrl')
            ->assertNotDispatched('operation-done');

        Livewire::test(Shorten::class)->set('targetUrl', 'https://example.com/page')->call('shorten')
            ->assertHasNoErrors()
            ->assertDispatched('operation-done');

        Livewire::test(QrCode::class)->set('text', '')->call('generate')->assertNotDispatched('operation-done');
        Livewire::test(QrCode::class)->set('text', 'hello')->call('generate')->assertDispatched('operation-done');
    }
}
