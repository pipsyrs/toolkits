<?php

namespace App\Livewire\Admin\ToolCategories;

use App\Actions\Admin\ManageToolCategory;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\WithFormModal;
use App\Models\ToolCategory;
use App\Services\Tools\ToolCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master data: tool categories (add, edit, reorder, delete).
#[Layout('layouts::admin')]
#[Title('Kategori alat')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithFormModal;

    public const PERMISSION = 'tools.manage';

    // Only used when adding; the slug is fixed afterwards.
    public string $slug = '';

    public string $name = '';

    public function save(ManageToolCategory $action): void
    {
        try {
            if ($this->editingId === null) {
                $action->create($this->adminUser(), $this->slug, $this->name, request()->ip());
                $flash = 'Kategori ditambahkan.';
            } else {
                $action->update($this->adminUser(), $this->editingId, $this->name, request()->ip());
                $flash = 'Kategori diperbarui.';
            }
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return;
        }

        $this->closeForm();
        session()->flash('status', $flash);
    }

    public function move(string $id, bool $up, ManageToolCategory $action): void
    {
        $this->runListAction(fn () => $action->move($this->adminUser(), $id, $up, request()->ip()));
    }

    public function delete(string $id, ManageToolCategory $action): void
    {
        $this->runListAction(fn () => $action->delete($this->adminUser(), $id, request()->ip()), 'Kategori dihapus.');
    }

    public function render(ToolCatalog $catalog): View
    {
        $categories = ToolCategory::query()->orderBy('sort')->orderBy('id')->get(['id', 'slug', 'name', 'sort']);

        return view('livewire.admin.tool-categories.index', [
            'categories' => $categories,
            'usage' => collect($catalog->all())->countBy('category')->all(),
            'editing' => $this->editingId === null ? null : $categories->firstWhere('id', $this->editingId),
        ]);
    }

    protected function resetForm(): void
    {
        $this->reset('slug', 'name');
    }

    protected function fillForm(string $id): bool
    {
        $category = ToolCategory::query()->find($id, ['id', 'name']);

        if ($category === null) {
            return false;
        }

        $this->name = $category->name;

        return true;
    }

    protected function formFields(): array
    {
        return ['slug', 'name'];
    }
}
