<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row may now hold only feature overrides, so status falls back to the config default.
        Schema::table('tool_settings', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->change();
            $table->json('features')->nullable()->after('status');
        });

        Cache::forget('tool_statuses');
    }

    public function down(): void
    {
        // Feature overrides are dropped with the column; rows that held nothing else go too.
        DB::table('tool_settings')->whereNull('status')->delete();

        Schema::table('tool_settings', function (Blueprint $table) {
            $table->dropColumn('features');
            $table->string('status', 20)->nullable(false)->change();
        });

        Cache::forget('tool_settings');
    }
};
