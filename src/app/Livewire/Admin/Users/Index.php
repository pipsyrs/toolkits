<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Admin\AssignUserRoles;
use App\Actions\Admin\BlockUser;
use App\Actions\Admin\UnblockUser;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionSet;
use App\Support\PasswordConfirmation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
#[Title('Users')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithPagination;

    public const PERMISSION = 'users.view';

    public const STATUSES = ['all', 'active', 'blocked', 'unverified'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    // Role modal: open state, target user + selected role ids.
    public bool $rolesOpen = false;

    #[Locked]
    public ?string $editingUserId = null;

    /** @var list<string> */
    public array $roleSelection = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function block(mixed $userId, mixed $reason, BlockUser $action): void
    {
        $action->handle($this->adminUser(), $this->findUser($userId), is_string($reason) ? $reason : '', request()->ip());
    }

    public function unblock(mixed $userId, UnblockUser $action): void
    {
        $action->handle($this->adminUser(), $this->findUser($userId), request()->ip());
    }

    public function editRoles(mixed $userId): void
    {
        $target = $this->findUser($userId);
        $this->authorize('assignRoles', $target);

        if (! $this->passwordConfirmed()) {
            return;
        }

        $this->resetErrorBag();
        $this->editingUserId = $target->getKey();
        $this->roleSelection = $target->roles()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->rolesOpen = true;
    }

    public function saveRoles(AssignUserRoles $action): void
    {
        if ($this->editingUserId === null || ! $this->passwordConfirmed()) {
            return;
        }

        $target = $this->findUser($this->editingUserId);
        $changes = $action->handle($this->adminUser(), $target, array_values(array_filter($this->roleSelection, 'is_string')), request()->ip());

        $this->cancelRoles();
        session()->flash('status', $changes['added'] === [] && $changes['removed'] === [] ? 'Tidak ada perubahan role.' : "Role {$target->email} diperbarui.");
    }

    public function cancelRoles(): void
    {
        $this->rolesOpen = false;
        $this->editingUserId = null;
        $this->roleSelection = [];
    }

    public function render(): View
    {
        $status = in_array($this->status, self::STATUSES, true) ? $this->status : 'all';
        $search = mb_substr(trim($this->search), 0, 100);

        $users = User::query()
            ->with(['roles:id,name', 'roles.permissions:id,name', 'permissions:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->when($status === 'active', fn ($q) => $q->whereNull('blocked_at')->whereNotNull('email_verified_at'))
            ->when($status === 'blocked', fn ($q) => $q->whereNotNull('blocked_at'))
            ->when($status === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->latest()
            ->paginate(25, ['id', 'name', 'email', 'email_verified_at', 'blocked_at', 'blocked_reason', 'plan', 'plan_until', 'two_factor_confirmed_at', 'created_at']);

        $actor = $this->adminUser();
        $canAssign = $actor->can('users.roles');

        return view('livewire.admin.users.index', [
            'users' => $users,
            'assignableRoles' => $canAssign ? $this->assignableRoles() : collect(),
            // user id => actor may open the role modal for that row
            'roleEditable' => $canAssign
                ? $users->getCollection()->mapWithKeys(fn (User $u) => [$u->getKey() => $actor->can('assignRoles', $u)])->all()
                : [],
        ]);
    }

    // Roles the actor may grant/revoke (superadmin role only for superadmin).
    private function assignableRoles(): Collection
    {
        $actor = $this->adminUser();
        $set = app(PermissionSet::class);

        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (Role $role) => $role->name === User::SUPERADMIN_ROLE
                ? $actor->isSuperadmin()
                : $set->isSubset($set->forRole($role), $actor))
            ->values();
    }

    private function passwordConfirmed(): bool
    {
        if (PasswordConfirmation::recent()) {
            return true;
        }

        redirect()->setIntendedUrl(route('admin.users.index'));
        $this->redirectRoute('password.confirm');

        return false;
    }

    private function findUser(mixed $userId): User
    {
        Validator::make(['userId' => $userId], ['userId' => ['required', 'string', 'uuid']])->validate();

        return User::query()->findOrFail($userId);
    }
}
