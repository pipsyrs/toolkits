<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Checkout catalog cached in the old shape (with names) must not be read by the new code.
    private const CATALOG_CACHE_KEY = 'payment_methods.active';

    // Checkout shows the logo or the channel badge; the display name lives on payment_channels.
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Cache::forget(self::CATALOG_CACHE_KEY);
    }

    // Custom names are gone; the column comes back filled with the channel label.
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('name', 40)->default('')->after('payment_method_type_id');
        });

        $channels = DB::table('payment_channels')->get(['code', 'name', 'kind'])->keyBy('code');

        DB::table('payment_methods')->select(['id', 'channel'])->orderBy('id')->each(function (object $method) use ($channels) {
            $channel = $channels[$method->channel] ?? null;
            $name = $channel === null ? $method->channel : ($channel->kind === 'va' ? $channel->name.' Virtual Account' : $channel->name);

            DB::table('payment_methods')->where('id', $method->id)->update(['name' => mb_substr($name, 0, 40)]);
        });

        Cache::forget(self::CATALOG_CACHE_KEY);
    }
};
