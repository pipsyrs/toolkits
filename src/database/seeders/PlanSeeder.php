<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Services\Tier\Entitlements;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    // null limit = unlimited (fair use from config). Free also covers visitors without an account.
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'is_purchasable' => false,
                'sort' => 2,
                'limits' => [
                    'max_file_mb' => 2,
                    'max_files_per_op' => 10,
                    'file_ops_per_day' => 25,
                    'links_per_day' => 20,
                    'links_active_max' => 100,
                    'link_lifetime_days' => null,
                    'custom_alias_max' => 5,
                    'link_custom_expiry' => false,
                    'link_password' => false,
                    'link_edit' => true,
                    'analytics' => 'total',
                    'history_days' => 7,
                    'retention_minutes' => 1440,
                    'max_active_ops' => 2,
                    'queue' => 'default',
                    'ads' => true,
                    'interstitial' => false,
                    'export_csv' => false,
                    'api' => false,
                    // Visitors without an account share an IP (NAT); the IP cap is this × the daily limit.
                    'ip_limit_multiplier' => 3,
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'price_monthly' => 29000,
                'price_yearly' => 290000,
                'is_purchasable' => true,
                'sort' => 3,
                'limits' => [
                    'max_file_mb' => 100,
                    'max_files_per_op' => 50,
                    'file_ops_per_day' => 500,
                    'links_per_day' => null,
                    'links_active_max' => null,
                    'link_lifetime_days' => null,
                    'custom_alias_max' => null,
                    'link_custom_expiry' => true,
                    'link_password' => true,
                    'link_edit' => true,
                    'analytics' => 'full',
                    'history_days' => 30,
                    'retention_minutes' => 10080,
                    'max_active_ops' => 5,
                    'queue' => 'pro',
                    'ads' => false,
                    'interstitial' => false,
                    'export_csv' => false,
                    'api' => false,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            // Gated tool features: paid plans get them all.
            foreach (config('toolkits.tools', []) as $tool) {
                foreach (array_keys($tool['features'] ?? []) as $feature) {
                    $plan['limits'][Entitlements::toolKey($tool['slug'], $feature)] = ! Tier::isSystem($plan['slug']);
                }
            }

            // Admin owns plans after the first seed: a reseed only adds missing plans and limit keys.
            $model = Plan::firstOrCreate(['slug' => $plan['slug']], $plan);

            if (! $model->wasRecentlyCreated && ($missing = array_diff_key($plan['limits'], $model->limits ?? [])) !== []) {
                $model->forceFill(['limits' => [...($model->limits ?? []), ...$missing]])->save();
            }
        }

        app(PlanLimits::class)->flush();
    }
}
