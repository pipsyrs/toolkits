<?php

namespace Tests\Feature;

use App\Enums\PaymentChannelKind;
use App\Livewire\Admin\PaymentChannels\Index;
use App\Livewire\Admin\PaymentMethods\Index as MethodsIndex;
use App\Models\AdminAuditLog;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentChannelsTest extends TestCase
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

    private function channel(string $code): PaymentChannel
    {
        return PaymentChannel::query()->where('code', $code)->sole();
    }

    public function test_page_requires_payment_methods_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/payment-channels')->assertForbidden();

        $finance = User::factory()->create()->assignRole(Role::create(['name' => 'finance', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'payments.view']));
        $this->actingAs($finance)->get('/admin/payment-channels')->assertForbidden();
        Livewire::actingAs($finance)->test(Index::class)->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/payment-channels')->assertOk()
            ->assertSee('BCA_VIRTUAL_ACCOUNT')->assertSee('Kartu kredit/debit')->assertDontSee('Xendit');
    }

    public function test_seeded_channels_match_existing_methods_and_card_is_offered_inactive(): void
    {
        $this->assertSame(13, PaymentChannel::count());
        $this->assertSame(0, PaymentMethod::query()->whereNotIn('channel', PaymentChannel::query()->pluck('code'))->count());

        $card = PaymentMethod::query()->where('channel', 'CARD')->sole();
        $this->assertFalse($card->is_active);
        $this->assertSame('card', $card->type->code);
        $this->assertSame(PaymentChannelKind::Card, $this->channel('CARD')->kind);
        $this->assertSame('CARDS', $this->channel('CARD')->gateway_code);
    }

    public function test_create_new_channel_and_offer_it_as_method(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('openCreate')
            ->set('code', 'cimb')
            ->set('kind', 'va')
            ->set('gatewayCode', 'cimb_virtual_account')
            ->set('name', 'CIMB Niaga')
            ->set('color', '#7A0019')
            ->set('needsMobileNumber', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false);

        $cimb = $this->channel('CIMB');
        $this->assertSame(['CIMB_VIRTUAL_ACCOUNT', 'CIMB Niaga', '#7a0019', PaymentChannelKind::VirtualAccount], [$cimb->gateway_code, $cimb->name, $cimb->color, $cimb->kind]);
        // Only e-wallets ask for a number.
        $this->assertFalse($cimb->needs_mobile_number);
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_channel.create')->count());

        Livewire::actingAs($admin)->test(MethodsIndex::class)->call('openCreate')->set('channel', 'CIMB')->call('save')->assertHasNoErrors();
        $method = PaymentMethod::query()->where('channel', 'CIMB')->sole();
        $this->assertSame('va', $method->type->code);
    }

    public function test_create_validates_input(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Index::class)->call('openCreate')
            ->set('kind', 'va')->set('gatewayCode', 'X_VA')->set('name', 'X');

        $component->set('code', 'BCA')->call('save')->assertHasErrors('code')->assertSet('formOpen', true);
        $component->set('code', 'bad code!')->call('save')->assertHasErrors('code');
        $component->set('code', 'NEW')->set('kind', 'paypal')->call('save')->assertHasErrors('kind');
        $component->set('kind', 'va')->set('color', 'red')->call('save')->assertHasErrors('color');
        $component->set('color', '#000000')->set('gatewayCode', 'bad-code')->call('save')->assertHasErrors('gateway_code');
        $component->set('gatewayCode', 'NEW_VA')->set('fixedExpiryMinutes', '2')->call('save')->assertHasErrors('fixed_expiry_minutes');
        $component->set('fixedExpiryMinutes', '')->set('name', "A\u{0007}")->call('save')->assertHasErrors('name');

        $this->assertSame(13, PaymentChannel::count());
    }

    public function test_update_keeps_code_and_kind_and_refreshes_checkout_labels(): void
    {
        $admin = $this->admin();
        $dana = $this->channel('DANA');
        $payment = Payment::factory()->for(User::factory()->create())->create(['payment_channel' => 'DANA']);
        $this->assertSame('DANA', $payment->channel()->name);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('openEdit', $dana->id)
            ->assertSet('gatewayCode', 'DANA')
            ->assertSet('fixedExpiryMinutes', '1440')
            ->set('name', 'Dana')
            ->set('fixedExpiryMinutes', '')
            ->call('save')
            ->assertHasNoErrors();

        $dana->refresh();
        $this->assertSame(['DANA', PaymentChannelKind::EWallet, 'Dana', null], [$dana->code, $dana->kind, $dana->name, $dana->fixed_expiry_minutes]);
        $this->assertSame('Dana', $payment->fresh()->channel()->name);
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_channel.update')->count());
    }

    public function test_channel_in_use_cannot_be_deleted_but_unused_can(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Index::class)->call('delete', $this->channel('BCA')->id)->assertHasErrors('list');
        $this->assertNotNull($this->channel('BCA'));

        PaymentMethod::query()->where('channel', 'BSI')->delete();
        $payment = Payment::factory()->for(User::factory()->create())->create(['payment_channel' => 'BSI']);

        Livewire::actingAs($admin)->test(Index::class)->call('delete', $this->channel('BSI')->id)->assertHasNoErrors();
        $this->assertSame(0, PaymentChannel::where('code', 'BSI')->count());
        // History keeps the code; the label is simply gone.
        $this->assertSame('BSI', $payment->fresh()->payment_channel);
        $this->assertNull($payment->fresh()->channel());
    }

    public function test_move_swaps_order_and_editing_id_is_locked(): void
    {
        [$first, $second] = PaymentChannel::query()->orderBy('sort')->get()->all();

        Livewire::actingAs($this->admin())->test(Index::class)->call('move', $second->id, true);
        $this->assertSame([$second->id, $first->id], PaymentChannel::query()->orderBy('sort')->limit(2)->pluck('id')->all());

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->admin())->test(Index::class)->call('openCreate')->set('editingId', $first->id);
    }
}
