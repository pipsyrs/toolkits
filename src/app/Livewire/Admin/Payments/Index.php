<?php

namespace App\Livewire\Admin\Payments;

use App\Enums\PaymentStatus;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Read-only.
#[Layout('layouts::admin')]
#[Title('Transaksi')]
class Index extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes, WithPagination;

    public const PERMISSION = 'payments.view_any';

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Paid this month (WIB), cached 10 minutes.
     *
     * @return array{count: int, amount: int, month: string}
     */
    private function totals(): array
    {
        $start = CarbonImmutable::now('Asia/Jakarta')->startOfMonth();

        return Cache::remember('admin.payments.totals.'.$start->format('Y-m'), 600, function () use ($start) {
            $row = Payment::query()
                ->where('status', PaymentStatus::Paid->value)
                ->whereBetween('paid_at', [$start->utc(), $start->endOfMonth()->utc()])
                ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(amount), 0) as aggregate_amount')
                ->toBase()
                ->first();

            return [
                'count' => (int) ($row->aggregate_count ?? 0),
                'amount' => (int) ($row->aggregate_amount ?? 0),
                'month' => $start->translatedFormat('F Y'),
            ];
        });
    }

    public function render(): View
    {
        $status = PaymentStatus::tryFrom($this->status);
        $search = mb_substr(trim($this->search), 0, 100);

        $payments = Payment::query()
            ->with('user:id,name,email')
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('order_id', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like)));
            })
            ->latest('created_at')
            ->paginate(25);

        return view('livewire.admin.payments.index', [
            'payments' => $payments,
            'totals' => $this->totals(),
            'statuses' => PaymentStatus::cases(),
        ]);
    }
}
