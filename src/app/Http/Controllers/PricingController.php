<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\Tier\PlanHighlights;
use Illuminate\Contracts\View\View;

class PricingController extends Controller
{
    public function __invoke(PlanHighlights $highlights): View
    {
        $plans = Plan::query()
            ->listed()
            ->get(['id', 'slug', 'name', 'limits', 'price_monthly', 'price_yearly', 'is_purchasable']);

        return view('pages.pricing', [
            'plans' => $plans,
            'highlights' => $plans->mapWithKeys(fn (Plan $plan) => [$plan->slug => $highlights->for($plan->limits ?? [])])->all(),
        ]);
    }
}
