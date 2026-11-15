<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // SVG access is now a per-tool Pro feature (tool_settings.features), not a plan limit.
    public function up(): void
    {
        $this->rewrite(function (array $limits) {
            unset($limits['qr_svg']);

            return $limits;
        });
    }

    public function down(): void
    {
        $this->rewrite(fn (array $limits, string $slug) => [...$limits, 'qr_svg' => $slug === 'pro']);
    }

    private function rewrite(callable $change): void
    {
        foreach (DB::table('plans')->get(['id', 'slug', 'limits']) as $plan) {
            $limits = json_decode((string) $plan->limits, true) ?: [];

            DB::table('plans')->where('id', $plan->id)->update(['limits' => json_encode($change($limits, $plan->slug))]);
        }

        Cache::forget('plans.limits');
    }
};
