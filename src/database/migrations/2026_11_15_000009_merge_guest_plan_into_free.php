<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Visitors without an account now use the Free plan; the separate guest plan row goes away.
return new class extends Migration
{
    private const OLD_FREE_NAME = 'Guest / Free';

    // Guest limits as seeded before the merge, restored on rollback.
    private const GUEST_LIMITS = [
        'max_file_mb' => 2, 'max_files_per_op' => 3, 'file_ops_per_day' => 5, 'links_per_day' => 3,
        'links_active_max' => null, 'link_lifetime_days' => 30, 'custom_alias_max' => 0, 'link_custom_expiry' => false,
        'link_password' => false, 'link_edit' => false, 'analytics' => 'none', 'history_days' => 0,
        'retention_minutes' => 15, 'max_active_ops' => 1, 'queue' => 'default', 'ads' => true,
        'interstitial' => true, 'export_csv' => false, 'api' => false, 'ip_limit_multiplier' => 3,
    ];

    public function up(): void
    {
        $guest = DB::table('plans')->where('slug', 'guest')->first(['limits']);
        $free = DB::table('plans')->where('slug', 'free')->first(['id', 'name', 'limits']);

        if ($free !== null) {
            $limits = json_decode((string) $free->limits, true) ?: [];
            $guestLimits = $guest === null ? [] : (json_decode((string) $guest->limits, true) ?: []);

            // Keeps the shared-IP allowance visitors had (cap per IP = multiplier × daily limit).
            $limits['ip_limit_multiplier'] ??= $guestLimits['ip_limit_multiplier'] ?? self::GUEST_LIMITS['ip_limit_multiplier'];

            DB::table('plans')->where('id', $free->id)->update([
                'limits' => json_encode($limits),
                'name' => $free->name === self::OLD_FREE_NAME ? 'Free' : $free->name,
                'updated_at' => now(),
            ]);
        }

        DB::table('plans')->where('slug', 'guest')->delete();
        $this->forgetCaches();
    }

    public function down(): void
    {
        $freeJson = DB::table('plans')->where('slug', 'free')->value('limits');

        // Nothing to restore on an install that was never seeded.
        if ($freeJson !== null && DB::table('plans')->where('slug', 'guest')->doesntExist()) {
            $free = json_decode((string) $freeJson, true) ?: [];
            // Tool feature switches follow Free; the rest are the old guest defaults.
            $tools = array_filter($free, fn ($key) => str_starts_with($key, 'tool_'), ARRAY_FILTER_USE_KEY);

            DB::table('plans')->insert([
                'id' => (string) Str::uuid(), 'slug' => 'guest', 'name' => 'Guest',
                'limits' => json_encode([...self::GUEST_LIMITS, ...$tools]),
                'price_monthly' => 0, 'price_yearly' => 0, 'is_purchasable' => false,
                'sort' => max(0, (int) DB::table('plans')->min('sort') - 1),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('plans')->where('slug', 'free')->where('name', 'Free')->update(['name' => self::OLD_FREE_NAME]);
        $this->forgetCaches();
    }

    private function forgetCaches(): void
    {
        Cache::forget('plans.limits');
        Cache::forget('plans.catalog');
    }
};
