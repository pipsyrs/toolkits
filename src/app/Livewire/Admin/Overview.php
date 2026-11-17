<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Services\Admin\OverviewStats;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
#[Title('Dashboard')]
class Overview extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes;

    public const PERIODS = [7, 30, 90];

    #[Url(as: 'hari')]
    public int $days = 30;

    public function mount(): void
    {
        $this->clampDays();
    }

    public function updatedDays(): void
    {
        $this->clampDays();
    }

    public function render(OverviewStats $stats): View
    {
        $admin = $this->adminUser();
        $tz = config('toolkits.timezone');
        $dayLabel = fn (string $day) => CarbonImmutable::parse($day, $tz)->locale('id')->translatedFormat('j M');

        $signups = $stats->dailySignups($this->days);
        $links = $stats->dailyLinksByTier($this->days);

        $revenue = $admin->can('payments.view_any') ? $stats->revenue() : null;

        return view('livewire.admin.overview', [
            'summary' => $stats->summary($this->days),
            'dayLabels' => array_map($dayLabel, array_keys($signups)),
            'signups' => array_values($signups),
            'linksByTier' => collect(OverviewStats::TIERS)->mapWithKeys(fn ($tier) => [$tier => array_column(array_values($links), $tier)])->all(),
            'revenue' => $revenue === null ? null : [
                'labels' => array_map(fn ($m) => CarbonImmutable::parse($m.'-01', $tz)->locale('id')->translatedFormat('M Y'), array_keys($revenue['months'])),
                'values' => array_values($revenue['months']),
                'pending' => $revenue['pending'],
            ],
            'topLinks' => $admin->can('links.view_any') ? $stats->topLinks() : null,
            'topHosts' => $admin->can('links.view_any') ? $stats->topHosts($this->days) : null,
            'audit' => $admin->can('audit.view_any') ? $stats->recentAudit() : null,
        ]);
    }

    private function clampDays(): void
    {
        if (! in_array($this->days, self::PERIODS, true)) {
            $this->days = 30;
        }
    }
}
