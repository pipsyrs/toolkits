<?php

namespace App\Livewire\Admin\PaymentMethods;

use App\Actions\Admin\ManagePaymentMethod;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\WithFormModal;
use App\Models\PaymentChannel;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// Payment methods offered in checkout: add from payment channels, edit type/logo, switch, reorder, delete.
#[Layout('layouts::admin')]
#[Title('Metode pembayaran')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithFileUploads, WithFormModal;

    public const PERMISSION = 'payment_methods.view_any';

    // Only used when adding; the channel of an existing method is fixed.
    public string $channel = '';

    // Empty = no type.
    public string $typeId = '';

    // New logo for the method being added/edited; validated and re-encoded by the action.
    public $logo = null;

    public function save(ManagePaymentMethod $action): void
    {
        $typeId = $this->typeId === '' ? null : $this->typeId;
        // Anything other than an uploaded file (tampered payload) is ignored, never stored.
        $logo = $this->logo instanceof UploadedFile ? $this->logo : null;

        try {
            if ($this->editingId === null) {
                $action->add($this->adminUser(), $this->channel, $typeId, request()->ip(), $logo);
                $flash = 'Metode ditambahkan. Aktifkan bila channel sudah aktif di akun payment gateway.';
            } else {
                $action->update($this->adminUser(), $this->editingId, $typeId, request()->ip(), $logo);
                $flash = 'Metode diperbarui.';
            }
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return;
        }

        $this->closeForm();
        session()->flash('status', $flash);
    }

    public function removeLogo(ManagePaymentMethod $action): void
    {
        if ($this->editingId === null) {
            return;
        }

        try {
            $action->removeLogo($this->adminUser(), $this->editingId, request()->ip());
        } catch (ValidationException $e) {
            $this->showFormErrors($e);
        }
    }

    public function toggle(string $id, ManagePaymentMethod $action): void
    {
        $method = PaymentMethod::query()->find($id);

        $this->runListAction(fn () => $action->setActive($this->adminUser(), $id, ! ($method?->is_active ?? true), request()->ip()), 'Status metode diperbarui.');
    }

    public function move(string $id, bool $up, ManagePaymentMethod $action): void
    {
        $this->runListAction(fn () => $action->move($this->adminUser(), $id, $up, request()->ip()));
    }

    public function delete(string $id, ManagePaymentMethod $action): void
    {
        $this->runListAction(fn () => $action->delete($this->adminUser(), $id, request()->ip()), 'Metode dihapus.');
    }

    public function render(): View
    {
        $methods = PaymentMethod::query()->with(['type:id,name', 'paymentChannel'])->orderBy('sort')->orderBy('id')
            ->get(['id', 'channel', 'payment_method_type_id', 'logo_path', 'is_active', 'sort']);

        return view('livewire.admin.payment-methods.index', [
            'methods' => $methods,
            'types' => PaymentMethodType::query()->orderBy('sort')->orderBy('id')->get(['id', 'name']),
            'available' => PaymentChannel::query()->whereNotIn('code', $methods->pluck('channel'))->orderBy('sort')->orderBy('id')->get(),
            'editing' => $this->editingId === null ? null : $methods->firstWhere('id', $this->editingId),
            'activeCount' => $methods->where('is_active', true)->count(),
        ]);
    }

    protected function resetForm(): void
    {
        $this->reset('channel', 'typeId', 'logo');
    }

    protected function fillForm(string $id): bool
    {
        $method = PaymentMethod::query()->find($id, ['id', 'payment_method_type_id']);

        if ($method === null) {
            return false;
        }

        $this->typeId = (string) $method->payment_method_type_id;

        return true;
    }

    protected function formFields(): array
    {
        return ['channel', 'type', 'logo'];
    }
}
