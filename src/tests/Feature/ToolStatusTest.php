<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Livewire\Tools;
use App\Models\Role;
use App\Models\ToolSetting;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use App\Services\Tools\ToolCatalog;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ToolStatusTest extends TestCase
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

    public function test_superadmin_turns_tool_off_with_reason_and_it_is_audited(): void
    {
        $admin = $this->superadmin();

        Livewire::actingAs($admin)->test(Admin\Tools\Show::class, ['slug' => 'shorten'])
            ->set('reason', 'maintenance')
            ->call('toggle')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tool_settings', ['slug' => 'shorten', 'status' => 'maintenance']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'tool.status', 'admin_id' => $admin->id]);

        $this->get('/tools/shorten')->assertStatus(503)->assertSee('sedang dalam perbaikan');
        $this->get('/tools')->assertOk()->assertSee('Maintenance');
    }

    public function test_changing_reason_of_inactive_tool_applies_immediately(): void
    {
        ToolSetting::create(['slug' => 'image-convert', 'status' => 'soon']);

        Livewire::actingAs($this->superadmin())->test(Admin\Tools\Show::class, ['slug' => 'image-convert'])
            ->assertSet('reason', 'soon')
            ->set('reason', 'disabled')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tool_settings', ['slug' => 'image-convert', 'status' => 'disabled']);
    }

    public function test_superadmin_turns_soon_tool_on(): void
    {
        ToolSetting::create(['slug' => 'image-convert', 'status' => 'soon']);
        $this->get('/tools/image-convert')->assertStatus(503);

        Livewire::actingAs($this->superadmin())->test(Admin\Tools\Show::class, ['slug' => 'image-convert'])
            ->call('toggle');

        $this->get('/tools/image-convert')->assertOk();
    }

    public function test_reason_cannot_be_live_or_unknown(): void
    {
        Livewire::actingAs($this->superadmin())->test(Admin\Tools\Show::class, ['slug' => 'image-convert'])
            ->set('reason', 'live')
            ->assertHasErrors('reason')
            ->set('reason', 'bogus')
            ->assertHasErrors('reason');

        $this->assertDatabaseCount('tool_settings', 0);
    }

    public function test_unknown_tool_page_is_not_found(): void
    {
        $this->actingAs($this->superadmin())->get('/admin/tools/nope')->assertNotFound();

        $this->assertDatabaseCount('tool_settings', 0);
    }

    public function test_tool_list_links_to_detail_pages(): void
    {
        $this->actingAs($this->superadmin())->get('/admin/tools')
            ->assertOk()
            ->assertSee(route('admin.tools.show', 'qr-code'))
            ->assertSee('QR Code Generator');

        $this->actingAs($this->superadmin())->get('/admin/tools/qr-code')
            ->assertOk()
            ->assertSee('Fitur per paket')
            ->assertSee('Unduh format SVG');
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $role = Role::findOrCreate('moderator', 'web');
        $role->givePermissionTo('admin.access');
        $user = User::factory()->create()->assignRole($role);

        $this->actingAs($user)->get('/admin/tools')->assertForbidden();
        $this->actingAs($user)->get('/admin/tools/qr-code')->assertForbidden();
        Livewire::actingAs($user)->test(Admin\Tools\Index::class)->assertForbidden();
        Livewire::actingAs($user)->test(Admin\Tools\Show::class, ['slug' => 'qr-code'])->assertForbidden();
    }

    public function test_unknown_feature_stays_locked_even_for_paid_plans(): void
    {
        $pro = app(PlanLimits::class)->paidTier('pro');

        $this->assertTrue(app(ToolCatalog::class)->canUseFeature('qr-code', 'svg', $pro));
        $this->assertFalse(app(ToolCatalog::class)->canUseFeature('qr-code', 'teleport', $pro));
    }

    public function test_actions_on_disabled_tool_are_blocked(): void
    {
        $component = Livewire::test(Tools\Shorten::class)->set('targetUrl', 'https://example.com');

        ToolSetting::create(['slug' => 'shorten', 'status' => 'disabled']);
        app(ToolCatalog::class)->forget();

        $component->call('shorten')->assertStatus(503);
        $this->assertDatabaseCount('links', 0);
    }
}
