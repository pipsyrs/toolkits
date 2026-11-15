<?php

namespace App\Livewire;

use App\Livewire\Concerns\ResolvesVisitor;
use App\Services\Quota\QuotaService;
use App\Services\Tier\Tier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuotaBadge extends Component
{
    use ResolvesVisitor;

    public const METRICS = ['links', 'file_ops'];

    #[Locked]
    public string $metric = 'links';

    public function mount(string $metric = 'links'): void
    {
        abort_unless(in_array($metric, self::METRICS, true), 404);

        $this->metric = $metric;
    }

    /**
     * @return array{tier: Tier, limit: int|null, remaining: int|null}
     */
    #[Computed]
    public function summary(): array
    {
        return app(QuotaService::class)->summary($this->visitor(), $this->guestId(), $this->clientIp(), $this->metric);
    }

    public function render(): View
    {
        return view('livewire.quota-badge', [
            'remaining' => $this->summary['remaining'],
            'limit' => $this->summary['limit'],
            'tier' => $this->summary['tier'],
        ]);
    }
}
