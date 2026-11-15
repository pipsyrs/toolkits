<?php

namespace App\Livewire\Admin\Roles;

use App\Actions\Admin\DeleteRole;
use App\Actions\Admin\SaveRole;
use App\Enums\PermissionName;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\WithFormModal;
use App\Models\Role;
use App\Services\Authorization\PermissionSet;
use App\Support\PasswordConfirmation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Roles list; create/edit happen in a modal form, delete in a confirmation dialog.
#[Layout('layouts::admin')]
#[Title('Role')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithFormModal;

    public const PERMISSION = 'roles.manage';

    // Shown with a "sensitif" label in the form.
    public const SENSITIVE = ['settings.manage', 'roles.manage', 'users.roles'];

    public string $name = '';

    /** @var list<string> */
    public array $selected = [];

    public function save(SaveRole $action): void
    {
        if (! $this->requirePasswordConfirmation()) {
            return;
        }

        try {
            $role = $action->handle($this->adminUser(), $this->editingRole(), $this->name, array_values(array_filter($this->selected, 'is_string')), request()->ip());
        } catch (ValidationException $e) {
            // Action uses "permissions"; the form binds "selected".
            $errors = [];

            foreach ($e->errors() as $key => $messages) {
                $errors[preg_replace('/^permissions/', 'selected', $key)] = $messages;
            }

            $this->showFormErrors(ValidationException::withMessages($errors));

            return;
        }

        $this->closeForm();
        session()->flash('status', "Role \"{$role->name}\" disimpan.");
    }

    public function delete(mixed $roleId, DeleteRole $action): void
    {
        if (! $this->requirePasswordConfirmation()) {
            return;
        }

        Validator::make(['roleId' => $roleId], ['roleId' => ['required', 'string', 'uuid']])->validate();
        $role = Role::query()->where('guard_name', 'web')->findOrFail($roleId);

        $action->handle($this->adminUser(), $role, request()->ip());

        session()->flash('status', "Role \"{$role->name}\" dihapus.");
    }

    public function render(): View
    {
        $actor = $this->adminUser();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        // Per-role flag: actor may edit/delete (policy: not superadmin, not own role, subset).
        $editable = $roles->mapWithKeys(fn (Role $role) => [$role->getKey() => $actor->can('update', $role)])->all();
        $editing = $this->editingId === null ? null : $roles->firstWhere('id', $this->editingId);

        $catalog = [];

        foreach (PermissionName::cases() as $permission) {
            $catalog[$permission->group()][$permission->value] = $permission->label();
        }

        return view('livewire.admin.roles.index', [
            'roles' => $roles,
            'editable' => $editable,
            'editing' => $editing,
            'catalog' => $catalog,
            'grantable' => app(PermissionSet::class)->for($actor)->all(),
            'sensitive' => self::SENSITIVE,
            'formLocked' => $editing !== null && ! ($editable[$editing->getKey()] ?? false),
        ]);
    }

    protected function resetForm(): void
    {
        $this->reset('name', 'selected');
    }

    protected function fillForm(string $id): bool
    {
        $role = Role::query()->where('guard_name', 'web')->with('permissions:id,name')->find($id);

        if ($role === null) {
            return false;
        }

        $this->name = $role->name;
        $this->selected = $role->permissions->pluck('name')->values()->all();

        return true;
    }

    protected function formFields(): array
    {
        return ['name', 'selected'];
    }

    // Role changes need a recent password confirmation; otherwise send the admin there and back.
    private function requirePasswordConfirmation(): bool
    {
        if (PasswordConfirmation::recent()) {
            return true;
        }

        redirect()->setIntendedUrl(route('admin.roles.index'));
        $this->redirectRoute('password.confirm');

        return false;
    }

    private function editingRole(): ?Role
    {
        return $this->editingId === null ? null : Role::query()->where('guard_name', 'web')->findOrFail($this->editingId);
    }
}
