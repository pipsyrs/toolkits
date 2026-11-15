<?php

namespace App\Livewire\Admin\Plans;

use App\Actions\Admin\ManagePlanLimits;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Models\Plan;
use App\Services\Tier\Entitlements;
use App\Services\Tier\Tier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// The Plans page: one column per plan (name, prices, delete) × fixed limit keys as rows; new paid plans become columns.
#[Layout('layouts::admin')]
#[Title('Paket')]
class Limits extends Component
{
    use AuthorizesAdmin, GuardsPropertyTypes;

    public const PERMISSION = 'plans.manage';

    /** @var array<string, array<string, mixed>> keyed by plan slug, then limit key */
    public array $values = [];

    /** @var array<string, array{name: string, price_monthly?: int|string|null, price_yearly?: int|string|null}> keyed by plan slug */
    public array $meta = [];

    /** @var array{name: string, slug: string, price_monthly: int|string|null, price_yearly: int|string|null} */
    public array $draft = ['name' => '', 'slug' => '', 'price_monthly' => '', 'price_yearly' => ''];

    public function mount(): void
    {
        $this->fillValues();
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->orderBy('sort')->get(['id', 'slug', 'name', 'limits', 'price_monthly', 'price_yearly']);
    }

    public function save(ManagePlanLimits $action): void
    {
        $this->resetErrorBag();
        // Errors come back keyed values.{slug}.{key} / meta.{slug}.{field}, matching the bindings.
        $action->update($this->adminUser(), $this->values, $this->meta, request()->ip());

        unset($this->plans);
        $this->fillValues();
        $this->dispatch('limits-saved');
    }

    public function createPlan(ManagePlanLimits $action): void
    {
        $this->resetErrorBag();

        try {
            $plan = $action->create($this->adminUser(), $this->draft, request()->ip());
        } catch (ValidationException $e) {
            throw $this->prefixed($e, 'draft.');
        }

        $this->reset('draft');
        unset($this->plans);
        $this->fillValues();
        session()->flash('status', "Paket {$plan->name} ditambahkan. Atur batasnya di kolom paling kanan.");
    }

    public function deletePlan(string $slug, ManagePlanLimits $action): void
    {
        $this->resetErrorBag();
        $plan = Plan::query()->where('slug', $slug)->firstOrFail();
        // Refusals come back under the "plan" error key.
        $action->delete($this->adminUser(), $plan, request()->ip());

        unset($this->plans);
        $this->fillValues();
        session()->flash('status', "Paket {$plan->name} dihapus.");
    }

    public function render(Entitlements $entitlements): View
    {
        return view('livewire.admin.plans.limits', [
            'plans' => $this->plans,
            'groups' => collect($entitlements->all())->groupBy('group', preserveKeys: true),
        ]);
    }

    private function fillValues(): void
    {
        $entitlements = app(Entitlements::class);

        $this->values = $this->plans
            ->mapWithKeys(fn (Plan $plan) => [$plan->slug => $entitlements->fill($plan->limits ?? [])])
            ->all();

        $this->meta = $this->plans
            ->mapWithKeys(fn (Plan $plan) => [$plan->slug => Tier::isSystem($plan->slug)
                ? ['name' => $plan->name]
                : ['name' => $plan->name, 'price_monthly' => $plan->price_monthly, 'price_yearly' => $plan->price_yearly]])
            ->all();
    }

    private function prefixed(ValidationException $e, string $prefix): ValidationException
    {
        $errors = [];

        foreach ($e->errors() as $key => $messages) {
            $errors[$prefix.$key] = $messages;
        }

        return ValidationException::withMessages($errors);
    }
}
