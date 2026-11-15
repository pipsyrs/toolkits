<?php

namespace App\Livewire\Admin\Links;

use App\Actions\Admin\DisableLink;
use App\Actions\Admin\EnableLink;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Link;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
#[Title('Links')]
class Index extends Component
{
    use AuthorizesAdmin, WithPagination;

    public const PERMISSION = 'links.view';

    public const STATUSES = ['all', 'active', 'disabled', 'guest'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function disable(string $linkId, string $reason, DisableLink $action): void
    {
        $action->handle($this->adminUser(), $this->findLink($linkId), $reason, request()->ip());
    }

    public function enable(string $linkId, EnableLink $action): void
    {
        $action->handle($this->adminUser(), $this->findLink($linkId), request()->ip());
    }

    public function render(): View
    {
        $status = in_array($this->status, self::STATUSES, true) ? $this->status : 'all';
        $search = mb_substr(trim($this->search), 0, 100);

        $links = Link::query()
            ->with('user:id,email')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('target_host', 'like', $like));
            })
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'disabled', fn ($q) => $q->whereNotNull('disabled_at'))
            ->when($status === 'guest', fn ($q) => $q->where('created_tier', 'guest'))
            ->latest()
            ->paginate(25, ['id', 'user_id', 'code', 'is_custom', 'target_url', 'target_host', 'expires_at', 'disabled_at', 'disabled_reason', 'created_tier', 'clicks_total', 'created_at']);

        return view('livewire.admin.links.index', ['links' => $links]);
    }

    private function findLink(string $linkId): Link
    {
        Validator::make(['linkId' => $linkId], ['linkId' => ['required', 'uuid']])->validate();

        return Link::query()->findOrFail($linkId);
    }
}
