<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Livewire\Admin\Plans\Limits;
use App\Models\Link;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\TierResolver;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadmin');
    }

    private function limits(string $slug): array
    {
        return Plan::query()->where('slug', $slug)->firstOrFail()->limits;
    }

    public function test_matrix_lists_every_plan_and_fixed_key(): void
    {
        $this->actingAs($this->superadmin())->get(route('admin.plans.edit'))
            ->assertOk()
            ->assertSee('Ukuran file maks (MB)')
            // Fitur berbayar alat pindah ke halaman alatnya, tidak lagi di matriks.
            ->assertDontSee('Unduh format SVG')
            ->assertSeeInOrder(['free', 'pro'])
            ->assertDontSee('wire:model="values.guest.ads"', false);
    }

    public function test_saving_updates_limits_keeps_unknown_keys_and_is_audited(): void
    {
        $admin = $this->superadmin();
        $component = Livewire::actingAs($admin)->test(Limits::class)
            ->set('values.free.max_file_mb', 5)
            ->set('values.free.file_ops_per_day', '')
            ->set('values.free.ads', false)
            // Not a registry key: must be ignored.
            ->set('values.free.is_admin', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('limits-saved');

        $free = $this->limits('free');
        $this->assertSame(5, $free['max_file_mb']);
        $this->assertNull($free['file_ops_per_day']);
        $this->assertFalse($free['ads']);
        $this->assertArrayNotHasKey('is_admin', $free);
        // Seeded keys outside the matrix survive.
        $this->assertSame('total', $free['analytics']);
        $this->assertSame(5, app(PlanLimits::class)->limits('free')['max_file_mb']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'plan.update_limits', 'admin_id' => $admin->id]);

        // Effect is live: the ad gate follows the matrix.
        $this->actingAs(User::factory()->create())->get('/tools/shorten')
            ->assertDontSee('aria-labelledby="ad-gate-title"', false);
        $component->assertSet('values.free.max_file_mb', 5);
    }

    public function test_invalid_values_are_rejected_and_nothing_is_saved(): void
    {
        $before = $this->limits('pro');

        Livewire::actingAs($this->superadmin())->test(Limits::class)
            ->set('values.pro.max_file_mb', 0)
            ->set('values.pro.queue', 'vip')
            ->set('values.free.max_active_ops', 0)
            ->call('save')
            ->assertHasErrors(['values.pro.max_file_mb', 'values.pro.queue', 'values.free.max_active_ops']);

        $this->assertSame($before, $this->limits('pro'));
    }

    public function test_file_limits_can_be_left_empty_for_unlimited(): void
    {
        $keys = ['max_file_mb', 'max_files_per_op', 'max_active_ops', 'retention_minutes'];
        $component = Livewire::actingAs($this->superadmin())->test(Limits::class);

        foreach ($keys as $key) {
            $component->set("values.pro.{$key}", '');
        }

        $component->call('save')->assertHasNoErrors();

        $limits = $this->limits('pro');
        $this->assertSame([null, null, null, null], array_map(fn (string $key) => $limits[$key], $keys));
        $this->get(route('pricing'))->assertSee('File hingga 100 MB')->assertSee('Hingga 100 file per proses')->assertSee('Hasil disimpan permanen');
    }

    public function test_new_plan_becomes_a_column_copied_from_free(): void
    {
        $admin = $this->superadmin();

        Livewire::actingAs($admin)->test(Limits::class)
            ->set('draft', ['name' => 'Bisnis', 'slug' => 'bisnis', 'price_monthly' => 99000, 'price_yearly' => 990000])
            ->call('createPlan')
            ->assertHasNoErrors()
            ->assertSet('values.bisnis.max_file_mb', $this->limits('free')['max_file_mb'])
            ->assertSee('Bisnis');

        $plan = Plan::query()->where('slug', 'bisnis')->firstOrFail();
        $this->assertTrue($plan->is_purchasable);
        $this->assertSame(99000, $plan->price_monthly);
        $this->assertFalse($plan->limits['tool_qr_code_svg']);
        $this->assertContains('bisnis', app(PlanLimits::class)->purchasableSlugs());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'plan.created', 'subject_id' => $plan->id]);

        $this->get(route('pricing'))->assertSee('Bisnis');
    }

    public function test_new_plan_slug_and_price_are_validated(): void
    {
        $component = Livewire::actingAs($this->superadmin())->test(Limits::class);

        foreach (['guest', 'free', 'pro', 'Bisnis', 'b', 'bisnis-plus', '1bisnis'] as $slug) {
            $component->set('draft', ['name' => 'Bisnis', 'slug' => $slug, 'price_monthly' => 1000, 'price_yearly' => 10000])
                ->call('createPlan')
                ->assertHasErrors('draft.slug');
        }

        $component->set('draft', ['name' => 'Bisnis', 'slug' => 'bisnis', 'price_monthly' => 0, 'price_yearly' => ''])
            ->call('createPlan')
            ->assertHasErrors(['draft.price_monthly', 'draft.price_yearly']);

        $this->assertSame(2, Plan::count());
    }

    public function test_matrix_requires_plans_permission(): void
    {
        $role = Role::findOrCreate('moderator', 'web');
        $role->givePermissionTo('admin.access');
        $user = User::factory()->create()->assignRole($role);

        $this->actingAs($user)->get(route('admin.plans.edit'))->assertForbidden();
        Livewire::actingAs($user)->test(Limits::class)->assertForbidden();
    }

    public function test_interstitial_follows_the_creator_plan(): void
    {
        $link = Link::create(['code' => 'abc123', 'target_url' => 'https://example.com', 'target_host' => 'example.com', 'created_tier' => 'free']);

        $this->get('/s/abc123')->assertRedirect('https://example.com');

        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $free->forceFill(['limits' => [...$free->limits, 'interstitial' => true]])->save();
        app(PlanLimits::class)->flush();

        $this->get('/s/'.$link->code)->assertOk()->assertSee('example.com');
    }

    public function test_column_header_edits_name_and_prices_but_not_free_prices(): void
    {
        $admin = $this->superadmin();

        Livewire::actingAs($admin)->test(Limits::class)
            ->set('meta.pro.price_monthly', 0)
            ->set('meta.free.name', '')
            ->call('save')
            ->assertHasErrors(['meta.pro.price_monthly', 'meta.free.name']);
        $this->assertSame(29000, Plan::query()->where('slug', 'pro')->value('price_monthly'));

        Livewire::actingAs($admin)->test(Limits::class)
            ->set('meta.pro.name', 'Pro Kreator')
            ->set('meta.pro.price_monthly', '35000')
            ->set('meta.free.name', 'Gratis')
            ->set('meta.free.price_monthly', 9999)
            ->call('save')
            ->assertHasNoErrors();

        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $this->assertSame(['Pro Kreator', 35000], [$pro->name, $pro->price_monthly]);
        $this->assertSame(['Gratis', 0], [$free->name, $free->price_monthly]);
        $this->get(route('pricing'))->assertSee('Pro Kreator')->assertSee('Rp35.000');
    }

    public function test_delete_plan_is_refused_while_in_use_and_for_system_plans(): void
    {
        $admin = $this->superadmin();
        $component = Livewire::actingAs($admin)->test(Limits::class)
            ->set('draft', ['name' => 'Bisnis', 'slug' => 'bisnis', 'price_monthly' => 99000, 'price_yearly' => 990000])
            ->call('createPlan');

        $component->call('deletePlan', 'free')->assertHasErrors('plan');
        Livewire::actingAs($admin)->test(Limits::class)->call('deletePlan', 'nope')->assertNotFound();

        $subscriber = User::factory()->create(['plan' => 'bisnis', 'plan_until' => now()->addDay()]);
        $component->call('deletePlan', 'bisnis')->assertHasErrors('plan')->assertSee('1 pelanggan aktif');

        $subscriber->forceFill(['plan_until' => now()->subDay()])->save();
        $payment = Payment::factory()->create(['plan' => 'bisnis']);
        $component->call('deletePlan', 'bisnis')->assertHasErrors('plan');

        $payment->forceFill(['status' => PaymentStatus::Expired])->save();
        $component->call('deletePlan', 'bisnis')->assertHasNoErrors()->assertDontSee('values.bisnis.ads', false);

        $this->assertNull(Plan::query()->where('slug', 'bisnis')->first());
        $this->assertNotContains('bisnis', app(PlanLimits::class)->paidSlugs());
        $this->assertModelExists($payment);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'plan.deleted', 'admin_id' => $admin->id]);
        // Expired subscriber of a deleted plan resolves to Free.
        $this->assertSame('free', app(TierResolver::class)->forUser($subscriber->fresh())->value);
        $this->get(route('pricing'))->assertDontSee('Bisnis');
    }
}
