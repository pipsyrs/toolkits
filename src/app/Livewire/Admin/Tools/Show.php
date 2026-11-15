<?php

namespace App\Livewire\Admin\Tools;

use App\Actions\Admin\UpdateToolStatus;
use App\Enums\ToolStatus;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Services\Tools\ToolCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

// All settings of one tool: status (with the reason shown while off). Feature access lives in the plan matrix.
#[Layout('layouts::admin')]
class Show extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes;

    public const PERMISSION = 'tools.manage';

    #[Locked]
    public string $slug;

    // Reason applied when the tool is off; kept while it is live so the next "off" uses it.
    public string $reason = '';

    public function mount(string $slug, ToolCatalog $catalog): void
    {
        abort_if($catalog->find($slug) === null, 404);

        $this->slug = $slug;
        $this->fillReason($catalog);
    }

    public function toggle(UpdateToolStatus $action, ToolCatalog $catalog): void
    {
        $isLive = $catalog->status($this->slug) === ToolStatus::Live;

        $this->applyStatus($isLive ? $this->reason : ToolStatus::Live->value, $action, $catalog);
    }

    // Changing the reason of a tool that is already off applies immediately.
    public function updatedReason(mixed $value): void
    {
        $catalog = app(ToolCatalog::class);
        $reason = ToolStatus::tryFrom((string) $value);

        if ($reason === null || $reason === ToolStatus::Live) {
            $this->fillReason($catalog);
            $this->addError('reason', 'Keterangan tidak valid.');

            return;
        }

        if ($catalog->status($this->slug) !== ToolStatus::Live) {
            $this->applyStatus($reason->value, app(UpdateToolStatus::class), $catalog);
        }
    }

    public function render(ToolCatalog $catalog): View
    {
        $tool = $catalog->find($this->slug);

        return view('livewire.admin.tools.show', [
            'tool' => $tool,
            'status' => ToolStatus::from($tool['status']),
            'reasonOptions' => ToolStatus::inactiveReasons(),
        ])->title($tool['name']);
    }

    private function applyStatus(string $status, UpdateToolStatus $action, ToolCatalog $catalog): void
    {
        $this->resetErrorBag('reason');

        try {
            $action->handle($this->adminUser(), $this->slug, $status, request()->ip());
            session()->flash('status', 'Status alat diperbarui.');
        } catch (ValidationException $e) {
            $this->addError('reason', collect($e->errors())->flatten()->first());
        } finally {
            $this->fillReason($catalog);
        }
    }

    private function fillReason(ToolCatalog $catalog): void
    {
        $current = $catalog->status($this->slug);
        $kept = ToolStatus::tryFrom($this->reason);

        $this->reason = match (true) {
            $current !== ToolStatus::Live => $current->value,
            $kept !== null && $kept !== ToolStatus::Live => $kept->value,
            default => ToolStatus::Soon->value,
        };
    }
}
