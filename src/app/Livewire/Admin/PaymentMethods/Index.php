<?php

namespace App\Livewire\Admin\PaymentMethods;

use App\Actions\Admin\ManagePaymentMethod;
use App\Enums\PaymentChannel;
use App\Enums\PaymentChannelKind;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Models\PaymentMethod;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Payment methods offered in checkout: add from the supported catalog, rename, switch, reorder, delete.
#[Layout('layouts::admin')]
#[Title('Metode pembayaran')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes;

    public const PERMISSION = 'payment_methods.manage';

    // Channel picked in the "add" form.
    public string $newChannel = '';

    public function add(ManagePaymentMethod $action): void
    {
        $this->run(fn () => $action->add($this->adminUser(), $this->newChannel, request()->ip()), 'Metode ditambahkan. Aktifkan bila channel sudah aktif di Xendit.');
        $this->newChannel = '';
    }

    public function rename(string $id, string $name, ManagePaymentMethod $action): void
    {
        $this->run(fn () => $action->rename($this->adminUser(), $id, $name, request()->ip()), 'Nama metode diperbarui.');
    }

    public function toggle(string $id, ManagePaymentMethod $action): void
    {
        $method = PaymentMethod::query()->find($id);

        $this->run(fn () => $action->setActive($this->adminUser(), $id, ! ($method?->is_active ?? true), request()->ip()), 'Status metode diperbarui.');
    }

    public function move(string $id, bool $up, ManagePaymentMethod $action): void
    {
        $this->run(fn () => $action->move($this->adminUser(), $id, $up, request()->ip()));
    }

    public function delete(string $id, ManagePaymentMethod $action): void
    {
        $this->run(fn () => $action->delete($this->adminUser(), $id, request()->ip()), 'Metode dihapus.');
    }

    public function render(): View
    {
        $methods = PaymentMethod::query()->orderBy('sort')->orderBy('id')->get(['id', 'channel', 'name', 'is_active', 'sort']);
        $used = $methods->map(fn (PaymentMethod $m) => $m->channel)->all();

        return view('livewire.admin.payment-methods.index', [
            'groups' => $methods->groupBy(fn (PaymentMethod $m) => $m->channel->kind()->value),
            'kinds' => PaymentChannelKind::cases(),
            'available' => array_values(array_filter(PaymentChannel::cases(), fn (PaymentChannel $c) => ! in_array($c, $used, true))),
            'activeCount' => $methods->where('is_active', true)->count(),
        ]);
    }

    private function run(Closure $change, ?string $flash = null): void
    {
        $this->resetErrorBag();

        try {
            $change();

            if ($flash !== null) {
                session()->flash('status', $flash);
            }
        } catch (ValidationException $e) {
            $this->addError('methods', collect($e->errors())->flatten()->first());
        }
    }
}
