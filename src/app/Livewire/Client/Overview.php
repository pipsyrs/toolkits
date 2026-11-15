<?php

namespace App\Livewire\Client;

use App\Livewire\Concerns\ResolvesVisitor;
use App\Models\Link;
use App\Services\Quota\QuotaService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::client')]
#[Title('Dashboard')]
class Overview extends Component
{
    use ResolvesVisitor;

    /**
     * @return array{links: array, file_ops: array}
     */
    #[Computed]
    public function quotas(): array
    {
        $quota = app(QuotaService::class);

        return [
            'links' => $quota->summary($this->visitor(), $this->guestId(), $this->clientIp(), 'links'),
            'file_ops' => $quota->summary($this->visitor(), $this->guestId(), $this->clientIp(), 'file_ops'),
        ];
    }

    #[Computed]
    public function recentLinks()
    {
        return Link::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(5)
            ->get(['id', 'code', 'target_host', 'clicks_total', 'created_at']);
    }

    public function render(): View
    {
        return view('livewire.client.overview', [
            'tier' => $this->tier,
            'planUntil' => $this->visitor()?->plan_until,
            'quotas' => $this->quotas,
            'remainingLinks' => $this->quotas['links']['remaining'],
            'remainingFileOps' => $this->quotas['file_ops']['remaining'],
            'recentLinks' => $this->recentLinks,
        ]);
    }
}
