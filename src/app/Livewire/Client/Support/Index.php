<?php

namespace App\Livewire\Client\Support;

use App\Actions\Support\SubmitSupportReport;
use App\Enums\SupportReportCategory;
use App\Livewire\Concerns\GuardsPropertyTypes;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// User reports to customer service: send a new one, follow status and CS replies of own reports.
#[Layout('layouts::client')]
#[Title('Bantuan')]
class Index extends Component
{
    use GuardsPropertyTypes, WithPagination;

    // Report form shown in <x-dashboard.modal>.
    public bool $formOpen = false;

    public string $category = '';

    public string $subject = '';

    public string $message = '';

    public function openCreate(): void
    {
        $this->resetErrorBag();
        $this->formOpen = true;
    }

    public function submit(SubmitSupportReport $action): void
    {
        $this->resetErrorBag();

        try {
            $action->handle(auth()->user(), [
                'category' => $this->category,
                'subject' => $this->subject,
                'message' => $this->message,
            ]);
        } catch (ValidationException $e) {
            // Rate-limit message ("report") is shown on top of the modal.
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key === 'report' ? 'form' : $key, $messages[0]);
            }

            return;
        }

        $this->reset('formOpen', 'category', 'subject', 'message');
        $this->resetPage();
        session()->flash('status', 'Laporan terkirim. Tim CS akan membalas di halaman ini.');
    }

    public function render(): View
    {
        return view('livewire.client.support.index', [
            'reports' => auth()->user()->supportReports()
                ->latest('created_at')->latest('id')
                ->paginate(10, ['id', 'category', 'subject', 'message', 'status', 'admin_reply', 'created_at']),
            'categories' => SupportReportCategory::cases(),
        ]);
    }
}
