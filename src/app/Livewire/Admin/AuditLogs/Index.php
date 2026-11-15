<?php

namespace App\Livewire\Admin\AuditLogs;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\AdminAuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
#[Title('Audit log')]
class Index extends Component
{
    use AuthorizesAdmin, WithPagination;

    public const PERMISSION = 'audit.view';

    #[Url]
    public string $action = '';

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function actions(): array
    {
        return AdminAuditLog::query()->distinct()->orderBy('action')->pluck('action')->all();
    }

    public function render(): View
    {
        $logs = AdminAuditLog::query()
            ->with('admin:id,email')
            ->when($this->action !== '', fn ($q) => $q->where('action', mb_substr($this->action, 0, 64)))
            ->latest('created_at')
            ->paginate(50);

        return view('livewire.admin.audit-logs.index', ['logs' => $logs, 'actions' => $this->actions]);
    }
}
