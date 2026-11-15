<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Rollback date for permanent results, so they are not pruned right after the rollback.
    private const ROLLBACK_YEARS = 10;

    // null expires_at = result kept permanently (plan retention left empty).
    public function up(): void
    {
        Schema::table('file_operations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('file_operations')->whereNull('expires_at')->update(['expires_at' => now()->addYears(self::ROLLBACK_YEARS)]);

        Schema::table('file_operations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
