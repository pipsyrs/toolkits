<?php

namespace App\Livewire\Concerns;

use Closure;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

// Create/edit state for a CRUD form shown in <x-dashboard.modal wire:model="formOpen">.
// List-level errors use the "list" key, errors inside the modal use field keys or "form".
trait WithFormModal
{
    public bool $formOpen = false;

    // Null while creating; only openEdit() sets it.
    #[Locked]
    public ?string $editingId = null;

    public function openCreate(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm();
        $this->formOpen = true;
    }

    public function openEdit(string $id): void
    {
        $this->resetErrorBag();
        $this->resetForm();

        if (! $this->fillForm($id)) {
            $this->formOpen = false;
            $this->addError('list', 'Data tidak ditemukan atau sudah dihapus.');

            return;
        }

        $this->editingId = $id;
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->editingId = null;
        $this->resetForm();
        $this->resetErrorBag();
    }

    // Field errors stay next to their input; anything else is shown on top of the modal.
    protected function showFormErrors(ValidationException $e): void
    {
        $this->resetErrorBag();

        foreach ($e->errors() as $key => $messages) {
            $this->addError(in_array($key, $this->formFields(), true) ? $key : 'form', $messages[0]);
        }
    }

    // Runs a list action (toggle, move, delete); its error is shown above the list.
    protected function runListAction(Closure $change, ?string $flash = null): void
    {
        $this->resetErrorBag();

        try {
            $change();

            if ($flash !== null) {
                session()->flash('status', $flash);
            }
        } catch (ValidationException $e) {
            $this->addError('list', collect($e->errors())->flatten()->first());
        }
    }

    // Puts the form properties back to their empty state.
    abstract protected function resetForm(): void;

    // Loads the record into the form; false when it does not exist.
    abstract protected function fillForm(string $id): bool;

    /**
     * Form property names that have their own error message under the input.
     *
     * @return list<string>
     */
    abstract protected function formFields(): array;
}
