<?php

namespace Tests\Feature;

use App\Livewire\Admin\PaymentMethods\Index as MethodsIndex;
use App\Livewire\Admin\PaymentMethodTypes\Index;
use App\Models\AdminAuditLog;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodTypesTest extends TestCase
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

    private function type(string $code): PaymentMethodType
    {
        return PaymentMethodType::query()->where('code', $code)->sole();
    }

    public function test_page_requires_payment_methods_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/payment-method-types')->assertForbidden();

        $finance = User::factory()->create()->assignRole(Role::create(['name' => 'finance', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'payments.view']));
        $this->actingAs($finance)->get('/admin/payment-method-types')->assertForbidden();
        Livewire::actingAs($finance)->test(Index::class)->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/payment-method-types')->assertOk()->assertSee('Virtual Account')->assertSee('E-wallet');
    }

    public function test_existing_methods_are_backfilled_with_their_kind_type(): void
    {
        $this->assertSame($this->type('va')->id, PaymentMethod::query()->where('channel', 'BCA')->value('payment_method_type_id'));
        $this->assertSame($this->type('qris')->id, PaymentMethod::query()->where('channel', 'QRIS')->value('payment_method_type_id'));
        $this->assertSame(0, PaymentMethod::query()->whereNull('payment_method_type_id')->count());
    }

    public function test_create_rename_and_delete_are_audited(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('openCreate')
            ->assertSet('formOpen', true)
            ->set('code', 'PayLater')
            ->set('name', 'Paylater')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false)
            ->assertSet('code', '');

        $type = $this->type('paylater');
        $this->assertSame('Paylater', $type->name);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('openEdit', $type->id)
            ->assertSet('name', 'Paylater')
            ->set('name', '  Bayar Nanti ')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Bayar Nanti', $type->fresh()->name);
        $this->assertSame('paylater', $type->fresh()->code);

        Livewire::actingAs($admin)->test(Index::class)->call('delete', $type->id)->assertHasNoErrors();
        $this->assertNull($type->fresh());

        $this->assertSame(
            ['payment_method_type.create', 'payment_method_type.delete', 'payment_method_type.update'],
            AdminAuditLog::query()->where('action', 'like', 'payment_method_type.%')->orderBy('action')->pluck('action')->all(),
        );
    }

    public function test_create_rejects_invalid_or_duplicate_code(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Index::class)->call('openCreate');

        $component->set('code', 'va')->set('name', 'VA lagi')->call('save')->assertHasErrors('code')->assertSet('formOpen', true);
        $component->set('code', 'bad code!')->set('name', 'X')->call('save')->assertHasErrors('code');
        $component->set('code', 'ok')->set('name', '')->call('save')->assertHasErrors('name');

        $this->assertSame(4, PaymentMethodType::count());
    }

    public function test_type_in_use_cannot_be_deleted(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)->call('delete', $this->type('va')->id)->assertHasErrors('list');

        $this->assertNotNull($this->type('va'));
    }

    public function test_move_swaps_order(): void
    {
        [$first, $second] = PaymentMethodType::query()->orderBy('sort')->get()->all();

        Livewire::actingAs($this->admin())->test(Index::class)->call('move', $second->id, true);

        $this->assertSame([$second->id, $first->id], PaymentMethodType::query()->orderBy('sort')->limit(2)->pluck('id')->all());
    }

    public function test_method_type_is_set_in_edit_modal(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::query()->where('channel', 'BCA')->sole();

        $component = Livewire::actingAs($admin)->test(MethodsIndex::class)
            ->call('openEdit', $method->id)
            ->assertSet('typeId', $this->type('va')->id)
            ->set('typeId', $this->type('ewallet')->id)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame($this->type('ewallet')->id, $method->fresh()->payment_method_type_id);

        $component->call('openEdit', $method->id)->set('typeId', '')->call('save')->assertHasNoErrors()->assertSee('Tanpa tipe');
        $this->assertNull($method->fresh()->payment_method_type_id);

        $component->call('openEdit', $method->id)->set('typeId', '0190c8f1-0000-7000-8000-000000000000')->call('save')
            ->assertHasErrors('type')->assertSet('formOpen', true);
        $this->assertSame(2, AdminAuditLog::where('action', 'payment_method.update')->count());
    }

    public function test_added_method_gets_chosen_or_kind_type(): void
    {
        PaymentMethod::query()->whereIn('channel', ['DANA', 'OVO'])->delete();
        $component = Livewire::actingAs($this->admin())->test(MethodsIndex::class);

        $component->call('openCreate')->set('channel', 'DANA')->call('save')->assertHasNoErrors();
        $this->assertSame($this->type('ewallet')->id, PaymentMethod::query()->where('channel', 'DANA')->value('payment_method_type_id'));

        $component->call('openCreate')->set('channel', 'OVO')->set('typeId', $this->type('qris')->id)->call('save')->assertHasNoErrors();
        $this->assertSame($this->type('qris')->id, PaymentMethod::query()->where('channel', 'OVO')->value('payment_method_type_id'));
    }

    public function test_editing_id_cannot_be_tampered(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->admin())->test(Index::class)->call('openCreate')->set('editingId', $this->type('va')->id);
    }
}
