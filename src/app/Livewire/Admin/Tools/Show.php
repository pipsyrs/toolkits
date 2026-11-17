<?php

namespace App\Livewire\Admin\Tools;

use App\Actions\Admin\ManagePlanLimits;
use App\Actions\Admin\UpdateToolCategory;
use App\Actions\Admin\UpdateToolStatus;
use App\Enums\ToolStatus;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Models\Plan;
use App\Services\Tier\Entitlements;
use App\Services\Tools\ToolCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

// All settings of one tool: status (with the reason shown while off) and category. Feature access lives in the plan matrix.
#[Layout('layouts::admin')]
class Show extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes;

    public const PERMISSION = 'tools.view';

    #[Locked]
    public string $slug;

    // Reason applied when the tool is off; kept while it is live so the next "off" uses it.
    public string $reason = '';

    public string $category = '';

    /** @var array<string, string> tool feature => plan slug that owns it ('' = locked) */
    public array $featurePlan = [];

    public function mount(string $slug, ToolCatalog $catalog): void
    {
        $tool = $catalog->find($slug);
        abort_if($tool === null, 404);

        $this->slug = $slug;
        $this->fillReason($catalog);
        $this->category = (string) $tool['category'];
        $this->fillFeaturePlan($tool['features'] ?? []);
    }

    // Each paid feature is assigned to one plan from the tool page; access lives in plans.limits.
    public function updatedFeaturePlan(mixed $value, string $key): void
    {
        $catalog = app(ToolCatalog::class);
        $features = $catalog->find($this->slug)['features'] ?? [];
        $this->resetErrorBag('featurePlan.'.$key);

        if (! isset($features[$key])) {
            return;
        }

        try {
            app(ManagePlanLimits::class)->setFeature($this->adminUser(), $this->slug, $key, (string) $value, request()->ip());
            session()->flash('status', 'Akses fitur diperbarui.');
        } catch (ValidationException $e) {
            $this->addError('featurePlan.'.$key, collect($e->errors())->flatten()->first());
        } finally {
            $this->fillFeaturePlan($features);
        }
    }

    public function updatedCategory(mixed $value): void
    {
        $catalog = app(ToolCatalog::class);
        $this->resetErrorBag('category');

        try {
            app(UpdateToolCategory::class)->handle($this->adminUser(), $this->slug, (string) $value, request()->ip());
            session()->flash('status', 'Kategori alat diperbarui.');
        } catch (ValidationException $e) {
            $this->addError('category', collect($e->errors())->flatten()->first());
        } finally {
            $this->category = (string) $catalog->find($this->slug)['category'];
        }
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
            'categories' => $catalog->categories(),
            'plans' => Plan::query()->orderBy('sort')->get(['slug', 'name']),
        ])->title($tool['name']);
    }

    /**
     * @param  array<string, string>  $features
     */
    private function fillFeaturePlan(array $features): void
    {
        $plans = Plan::query()->orderBy('sort')->get(['slug', 'limits']);

        $this->featurePlan = [];
        foreach (array_keys($features) as $feature) {
            $key = Entitlements::toolKey($this->slug, $feature);
            $this->featurePlan[$feature] = $plans->first(fn (Plan $plan) => ! empty(($plan->limits ?? [])[$key]))?->slug ?? '';
        }
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
