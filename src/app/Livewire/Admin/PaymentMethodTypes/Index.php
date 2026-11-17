<?php

namespace App\Livewire\Admin\PaymentMethodTypes;

use App\Actions\Admin\ManagePaymentMethodType;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\WithFormModal;
use App\Models\PaymentMethodType;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master data: payment method types (add, edit, reorder, delete).
#[Layout('layouts::admin')]
#[Title('Tipe metode bayar')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithFormModal;

    public const PERMISSION = 'payment_method_types.view_any';

    // Only used when adding; the code is fixed afterwards.
    public string $code = '';

    public string $name = '';

    public function save(ManagePaymentMethodType $action): void
    {
        try {
            if ($this->editingId === null) {
                $action->create($this->adminUser(), $this->code, $this->name, request()->ip());
                $flash = 'Tipe ditambahkan.';
            } else {
                $action->update($this->adminUser(), $this->editingId, $this->name, request()->ip());
                $flash = 'Tipe diperbarui.';
            }
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return;
        }

        $this->closeForm();
        session()->flash('status', $flash);
    }

    public function move(string $id, bool $up, ManagePaymentMethodType $action): void
    {
        $this->runListAction(fn () => $action->move($this->adminUser(), $id, $up, request()->ip()));
    }

    public function delete(string $id, ManagePaymentMethodType $action): void
    {
        $this->runListAction(fn () => $action->delete($this->adminUser(), $id, request()->ip()), 'Tipe dihapus.');
    }

    public function render(): View
    {
        $types = PaymentMethodType::query()->withCount('methods')->orderBy('sort')->orderBy('id')->get(['id', 'code', 'name', 'sort']);

        return view('livewire.admin.payment-method-types.index', [
            'types' => $types,
            'editing' => $this->editingId === null ? null : $types->firstWhere('id', $this->editingId),
        ]);
    }

    protected function resetForm(): void
    {
        $this->reset('code', 'name');
    }

    protected function fillForm(string $id): bool
    {
        $type = PaymentMethodType::query()->find($id, ['id', 'name']);

        if ($type === null) {
            return false;
        }

        $this->name = $type->name;

        return true;
    }

    protected function formFields(): array
    {
        return ['code', 'name'];
    }
}
