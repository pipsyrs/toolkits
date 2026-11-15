<?php

namespace App\Livewire\Client\Links;

use App\Actions\Links\DeleteShortLink;
use App\Models\Link;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::client')]
#[Title('Link Saya')]
class Index extends Component
{
    use WithPagination;

    private const SORTS = ['created_at', 'clicks_total'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTS, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'desc' ? 'asc' : 'desc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function delete(string $linkId, DeleteShortLink $action): void
    {
        Validator::make(['linkId' => $linkId], ['linkId' => ['required', 'uuid']])->validate();

        $link = Link::query()->findOrFail($linkId);
        $this->authorize('delete', $link);

        $action->handle(auth()->user(), $link);
    }

    public function render(): View
    {
        $sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'created_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';
        $search = mb_substr(trim($this->search), 0, 100);

        $links = Link::query()
            ->where('user_id', auth()->id())
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('target_url', 'like', $like));
            })
            ->orderBy($sort, $direction)
            ->paginate(20, ['id', 'code', 'is_custom', 'target_url', 'target_host', 'expires_at', 'disabled_at', 'clicks_total', 'last_clicked_at', 'created_at']);

        return view('livewire.client.links.index', ['links' => $links]);
    }
}
