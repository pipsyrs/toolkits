<?php

namespace App\Livewire\Admin\PaymentChannels;

use App\Actions\Admin\ManagePaymentChannel;
use App\Enums\PaymentChannelKind;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\WithFormModal;
use App\Models\PaymentChannel;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master data: payment channels offered by the gateway (add, edit, reorder, delete).
#[Layout('layouts::admin')]
#[Title('Payment channel')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithFormModal;

    public const PERMISSION = 'payment_channels.view_any';

    private const DEFAULT_COLOR = '#15181d';

    // code and kind are only used when adding; both are fixed afterwards.
    public string $code = '';

    public string $kind = '';

    public string $gatewayCode = '';

    public string $name = '';

    public string $color = self::DEFAULT_COLOR;

    public bool $needsMobileNumber = false;

    // Empty = the expiry from Pengaturan.
    public string $fixedExpiryMinutes = '';

    public function save(ManagePaymentChannel $action): void
    {
        $input = [
            'code' => $this->code,
            'kind' => $this->kind,
            'gateway_code' => $this->gatewayCode,
            'name' => $this->name,
            'color' => $this->color,
            'needs_mobile_number' => $this->needsMobileNumber,
            'fixed_expiry_minutes' => $this->fixedExpiryMinutes,
        ];

        try {
            if ($this->editingId === null) {
                $action->create($this->adminUser(), $input, request()->ip());
                $flash = 'Channel ditambahkan. Tambahkan sebagai metode pembayaran agar muncul di checkout.';
            } else {
                $action->update($this->adminUser(), $this->editingId, $input, request()->ip());
                $flash = 'Channel diperbarui.';
            }
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return;
        }

        $this->closeForm();
        session()->flash('status', $flash);
    }

    public function move(string $id, bool $up, ManagePaymentChannel $action): void
    {
        $this->runListAction(fn () => $action->move($this->adminUser(), $id, $up, request()->ip()));
    }

    public function delete(string $id, ManagePaymentChannel $action): void
    {
        $this->runListAction(fn () => $action->delete($this->adminUser(), $id, request()->ip()), 'Channel dihapus.');
    }

    public function render(): View
    {
        $channels = PaymentChannel::query()->withCount('methods')->orderBy('sort')->orderBy('id')->get();

        return view('livewire.admin.payment-channels.index', [
            'channels' => $channels,
            'kinds' => PaymentChannelKind::cases(),
            'editing' => $this->editingId === null ? null : $channels->firstWhere('id', $this->editingId),
        ]);
    }

    protected function resetForm(): void
    {
        $this->reset('code', 'kind', 'gatewayCode', 'name', 'color', 'needsMobileNumber', 'fixedExpiryMinutes');
    }

    protected function fillForm(string $id): bool
    {
        $channel = PaymentChannel::query()->find($id);

        if ($channel === null) {
            return false;
        }

        $this->kind = $channel->kind->value;
        $this->gatewayCode = $channel->gateway_code;
        $this->name = $channel->name;
        $this->color = $channel->color;
        $this->needsMobileNumber = $channel->needs_mobile_number;
        $this->fixedExpiryMinutes = (string) $channel->fixed_expiry_minutes;

        return true;
    }

    protected function formFields(): array
    {
        return ['code', 'kind', 'gateway_code', 'name', 'color', 'fixed_expiry_minutes'];
    }
}
