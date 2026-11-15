<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// The per-IP cap for visitors without an account now equals the daily limit; the plan setting goes away.
return new class extends Migration
{
    private const KEY = 'ip_limit_multiplier';

    public function up(): void
    {
        $this->rewriteLimits(function (array $limits): array {
            unset($limits[self::KEY]);

            return $limits;
        });
    }

    public function down(): void
    {
        // 1 matches the behavior after up(), so rollback changes no quota.
        $this->rewriteLimits(fn (array $limits): array => [...$limits, self::KEY => 1]);
    }

    private function rewriteLimits(callable $change): void
    {
        DB::table('plans')->orderBy('id')->select(['id', 'limits'])->each(function (object $plan) use ($change) {
            $limits = json_decode((string) $plan->limits, true) ?: [];

            DB::table('plans')->where('id', $plan->id)->update([
                'limits' => json_encode($change($limits)),
                'updated_at' => now(),
            ]);
        });

        Cache::forget('plans.limits');
        Cache::forget('plans.catalog');
    }
};
