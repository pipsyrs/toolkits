<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Pro Plus is gone: holders keep their remaining period as Pro.
        DB::table('users')->where('plan', 'pro_plus')->update(['plan' => 'pro']);
        DB::table('payments')->where('plan', 'pro_plus')->update(['plan' => 'pro']);
        DB::table('plans')->where('slug', 'pro_plus')->delete();

        Cache::forget('plans.limits');
    }

    public function down(): void
    {
        // Restores the plan row only; users and payments moved to Pro stay on Pro.
        if (DB::table('plans')->where('slug', 'pro_plus')->exists()) {
            return;
        }

        DB::table('plans')->insert([
            'id' => (string) Str::uuid7(),
            'slug' => 'pro_plus',
            'name' => 'Pro Plus',
            'limits' => DB::table('plans')->where('slug', 'pro')->value('limits') ?? '{}',
            'price_monthly' => 59000,
            'price_yearly' => 590000,
            'is_purchasable' => false,
            'sort' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('plans.limits');
    }
};
