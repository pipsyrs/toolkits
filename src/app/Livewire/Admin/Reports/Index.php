<?php

namespace App\Livewire\Admin\Reports;

use App\Actions\Admin\HandleSupportReport;
use App\Enums\SupportReportCategory;
use App\Enums\SupportReportStatus;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Models\SupportReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Customer service inbox: user reports, filtered; replying needs reports.manage.
#[Layout('layouts::admin')]
#[Title('Reports')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithPagination;

    public const PERMISSION = 'reports.view';

    private const CURSOR = 'cursor';

    private const SEARCH_MAX = 100;

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $search = '';

    // Detail + reply modal; only open() sets the report.
    public bool $detailOpen = false;

    #[Locked]
    public ?string $selectedId = null;

    public string $replyStatus = '';

    public string $reply = '';

    public function updatedStatus(): void
    {
        $this->resetCursor();
    }

    public function updatedCategory(): void
    {
        $this->resetCursor();
    }

    public function updatedSearch(): void
    {
        $this->resetCursor();
    }

    public function open(string $id): void
    {
        $report = Str::isUuid($id) ? SupportReport::query()->find($id, ['id', 'status', 'admin_reply']) : null;

        if ($report === null) {
            $this->addError('report', 'Laporan tidak ditemukan.');

            return;
        }

        $this->resetErrorBag();
        $this->selectedId = $report->id;
        $this->replyStatus = $report->status->value;
        $this->reply = (string) $report->admin_reply;
        $this->detailOpen = true;
    }

    public function close(): void
    {
        $this->reset('detailOpen', 'selectedId', 'replyStatus', 'reply');
        $this->resetErrorBag();
    }

    public function save(HandleSupportReport $action): void
    {
        if ($this->selectedId === null) {
            return;
        }

        $this->resetErrorBag();

        try {
            $action->handle($this->adminUser(), $this->selectedId, $this->replyStatus, $this->reply, request()->ip());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError(in_array($key, ['status', 'reply'], true) ? $key : 'form', $messages[0]);
            }

            return;
        }

        $this->close();
        session()->flash('status', 'Laporan diperbarui.');
    }

    public function render(): View
    {
        $status = SupportReportStatus::tryFrom($this->status);
        $category = SupportReportCategory::tryFrom($this->category);
        $search = mb_substr(trim($this->search), 0, self::SEARCH_MAX);

        $reports = SupportReport::query()
            ->with('user:id,name,email')
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->when($category !== null, fn ($q) => $q->where('category', $category->value))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('subject', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like)));
            })
            ->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate(25, ['id', 'user_id', 'category', 'subject', 'status', 'created_at'], self::CURSOR);

        return view('livewire.admin.reports.index', [
            'reports' => $reports,
            'selected' => $this->selectedId === null ? null : SupportReport::query()->with(['user:id,name,email', 'handler:id,name'])->find($this->selectedId),
            'openCount' => SupportReport::query()->where('status', SupportReportStatus::Open->value)->count(),
            'statuses' => SupportReportStatus::cases(),
            'categories' => SupportReportCategory::cases(),
            'searchMax' => self::SEARCH_MAX,
        ]);
    }

    private function resetCursor(): void
    {
        $this->setPage('', self::CURSOR);
    }
}
