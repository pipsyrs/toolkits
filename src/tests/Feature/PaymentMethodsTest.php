<?php

namespace Tests\Feature;

use App\Livewire\Admin\PaymentMethods\Index;
use App\Models\AdminAuditLog;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\PaymentMethodCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('superadmin');
    }

    private function method(string $channel): PaymentMethod
    {
        return PaymentMethod::query()->where('channel', $channel)->sole();
    }

    public function test_page_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/payment-methods')->assertForbidden();

        $finance = User::factory()->create()->assignRole(Role::create(['name' => 'finance', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'payments.view']));
        $this->actingAs($finance)->get('/admin/payment-methods')->assertForbidden();
        Livewire::actingAs($finance)->test(Index::class)->assertForbidden();

        $manager = User::factory()->create()->assignRole(Role::create(['name' => 'billing', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'payment_methods.manage']));
        $this->actingAs($manager)->get('/admin/payment-methods')->assertOk()->assertSee('BCA Virtual Account')->assertSee('OVO');
        $this->actingAs($this->admin())->get('/admin/payment-methods')->assertOk();
    }

    public function test_defaults_offer_qris_and_va_with_ewallets_off(): void
    {
        $this->assertSame(['QRIS', 'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'BSI'], array_keys(app(PaymentMethodCatalog::class)->active()));
        $this->assertFalse($this->method('DANA')->is_active);
    }

    public function test_toggle_updates_checkout_catalog_and_is_audited(): void
    {
        $catalog = app(PaymentMethodCatalog::class);
        $this->assertArrayNotHasKey('DANA', $catalog->active());

        Livewire::actingAs($this->admin())->test(Index::class)->call('toggle', $this->method('DANA')->id)->assertHasNoErrors();

        $this->assertTrue($this->method('DANA')->is_active);
        $this->assertArrayHasKey('DANA', $catalog->active());
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_method.toggle')->count());

        Livewire::actingAs($this->admin())->test(Index::class)->call('toggle', $this->method('DANA')->id);
        $this->assertArrayNotHasKey('DANA', $catalog->active());
    }

    public function test_rename_validates_and_is_shown_in_checkout(): void
    {
        $id = $this->method('BCA')->id;
        $component = Livewire::actingAs($this->admin())->test(Index::class);

        foreach (['', '   ', str_repeat('a', 41), "BCA\u{0007}"] as $invalid) {
            $component->call('rename', $id, $invalid)->assertHasErrors('methods');
        }
        $this->assertSame('BCA Virtual Account', $this->method('BCA')->name);

        $component->call('rename', $id, '  VA BCA  ')->assertHasNoErrors();
        $this->assertSame('VA BCA', $this->method('BCA')->name);
        $this->assertSame('VA BCA', app(PaymentMethodCatalog::class)->active()['BCA']);
    }

    public function test_move_reorders_within_kind_only(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Index::class);

        $component->call('move', $this->method('BNI')->id, true);
        $this->assertSame(['QRIS', 'BNI', 'BCA'], array_slice(array_keys(app(PaymentMethodCatalog::class)->active()), 0, 3));

        // First VA cannot move above QRIS (different kind): nothing changes.
        $component->call('move', $this->method('BNI')->id, true);
        $this->assertSame(['QRIS', 'BNI', 'BCA'], array_slice(array_keys(app(PaymentMethodCatalog::class)->active()), 0, 3));
    }

    public function test_delete_and_add_back_from_catalog(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Index::class);

        $component->call('delete', $this->method('BSI')->id)->assertHasNoErrors();
        $this->assertNull(PaymentMethod::query()->where('channel', 'BSI')->first());
        $this->assertArrayNotHasKey('BSI', app(PaymentMethodCatalog::class)->active());

        $component->set('newChannel', 'PAYPAL')->call('add')->assertHasErrors('methods');
        $component->set('newChannel', 'BCA')->call('add')->assertHasErrors('methods');

        $component->set('newChannel', 'BSI')->call('add')->assertHasNoErrors()->assertSet('newChannel', '');
        $added = $this->method('BSI');
        $this->assertFalse($added->is_active);
        $this->assertSame(PaymentMethod::max('sort'), $added->sort);
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_method.create')->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_method.delete')->count());
    }

    public function test_unknown_id_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('toggle', '00000000-0000-7000-8000-000000000000')
            ->assertHasErrors('methods');
    }
}
