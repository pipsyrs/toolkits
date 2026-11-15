<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Tool feature gates move from tool_settings.features (Pro-only | free for all) to per-plan switches in plans.limits.
return new class extends Migration
{
    private const SYSTEM_PLANS = ['guest', 'free'];

    public function up(): void
    {
        $free = DB::table('tool_settings')->whereNotNull('features')->pluck('features', 'slug')
            ->map(fn ($json) => json_decode((string) $json, true) ?: []);

        foreach (DB::table('plans')->get(['id', 'slug', 'limits']) as $plan) {
            $limits = json_decode((string) $plan->limits, true) ?: [];
            $paid = ! in_array($plan->slug, self::SYSTEM_PLANS, true);

            foreach ($this->features() as [$tool, $feature, $key]) {
                // Old rule: paid plans had every feature; others only those made free (override false).
                $limits[$key] = $paid || (($free[$tool][$feature] ?? true) === false);
            }

            DB::table('plans')->where('id', $plan->id)->update(['limits' => json_encode($limits)]);
        }

        // Rows that only held feature overrides keep nothing else.
        DB::table('tool_settings')->whereNull('status')->delete();

        Schema::table('tool_settings', function (Blueprint $table) {
            $table->dropColumn('features');
        });

        $this->forgetCaches();
    }

    public function down(): void
    {
        Schema::table('tool_settings', function (Blueprint $table) {
            $table->json('features')->nullable()->after('status');
        });

        $freeLimits = json_decode((string) DB::table('plans')->where('slug', 'free')->value('limits'), true) ?: [];
        $overrides = [];

        foreach ($this->features() as [$tool, $feature, $key]) {
            if (! empty($freeLimits[$key])) {
                $overrides[$tool][$feature] = false;
            }
        }

        foreach ($overrides as $tool => $features) {
            $updated = DB::table('tool_settings')->where('slug', $tool)->update(['features' => json_encode($features)]);

            if ($updated === 0) {
                DB::table('tool_settings')->insert([
                    'id' => (string) Str::uuid(), 'slug' => $tool, 'status' => null, 'features' => json_encode($features),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach (DB::table('plans')->get(['id', 'limits']) as $plan) {
            $limits = json_decode((string) $plan->limits, true) ?: [];

            foreach ($this->features() as [, , $key]) {
                unset($limits[$key]);
            }

            DB::table('plans')->where('id', $plan->id)->update(['limits' => json_encode($limits)]);
        }

        $this->forgetCaches();
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}> tool slug, feature, plan limit key
     */
    private function features(): array
    {
        $list = [];

        foreach (config('toolkits.tools', []) as $tool) {
            foreach (array_keys($tool['features'] ?? []) as $feature) {
                $list[] = [$tool['slug'], $feature, 'tool_'.str_replace('-', '_', $tool['slug']).'_'.$feature];
            }
        }

        return $list;
    }

    private function forgetCaches(): void
    {
        Cache::forget('tool_settings');
        Cache::forget('plans.limits');
        Cache::forget('plans.catalog');
    }
};
