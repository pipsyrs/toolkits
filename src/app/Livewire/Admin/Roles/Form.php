<?php

namespace App\Livewire\Admin\Roles;

use App\Actions\Admin\SaveRole;
use App\Enums\PermissionName;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Models\Role;
use App\Services\Authorization\PermissionSet;
use App\Support\PasswordConfirmation;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::admin')]
#[Title('Role')]
class Form extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes;

    public const PERMISSION = 'roles.manage';

    // Shown with a "sensitif" label in the form.
    public const SENSITIVE = ['settings.manage', 'roles.manage', 'users.roles'];

    #[Locked]
    public ?string $roleId = null;

    public string $name = '';

    /** @var list<string> */
    public array $selected = [];

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->roleId = $role->getKey();
            $this->name = $role->name;
            $this->selected = $role->permissions->pluck('name')->values()->all();
        }
    }

    public function save(SaveRole $action): void
    {
        if (! PasswordConfirmation::recent()) {
            redirect()->setIntendedUrl($this->roleId ? route('admin.roles.edit', $this->roleId) : route('admin.roles.create'));
            $this->redirectRoute('password.confirm');

            return;
        }

        try {
            $role = $action->handle($this->adminUser(), $this->role(), $this->name, array_values(array_filter($this->selected, 'is_string')), request()->ip());
        } catch (ValidationException $e) {
            // Action uses "permissions"; the form binds "selected".
            $errors = [];

            foreach ($e->errors() as $key => $messages) {
                $errors[preg_replace('/^permissions/', 'selected', $key)] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        session()->flash('status', "Role \"{$role->name}\" disimpan.");
        $this->redirectRoute('admin.roles.index');
    }

    public function render(): View
    {
        $role = $this->role();
        $catalog = [];

        foreach (PermissionName::cases() as $permission) {
            $catalog[$permission->group()][$permission->value] = $permission->label();
        }

        return view('livewire.admin.roles.form', [
            'catalog' => $catalog,
            'grantable' => app(PermissionSet::class)->for($this->adminUser())->all(),
            'sensitive' => self::SENSITIVE,
            'locked' => $role !== null && ! $this->adminUser()->can('update', $role),
            'isEdit' => $role !== null,
        ]);
    }

    protected function role(): ?Role
    {
        return $this->roleId === null ? null : Role::query()->where('guard_name', 'web')->findOrFail($this->roleId);
    }
}
