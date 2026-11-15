<?php

namespace App\Livewire\Admin\Roles;

use App\Actions\Admin\DeleteRole;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Role;
use App\Support\PasswordConfirmation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::admin')]
#[Title('Role')]
class Index extends Component
{
    use AuthorizesAdmin;

    public const PERMISSION = 'roles.manage';

    public function delete(mixed $roleId, DeleteRole $action): void
    {
        if (! PasswordConfirmation::recent()) {
            redirect()->setIntendedUrl(route('admin.roles.index'));
            $this->redirectRoute('password.confirm');

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

        return view('livewire.admin.roles.index', [
            'roles' => $roles,
            'editable' => $editable,
        ]);
    }
}
