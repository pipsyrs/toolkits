<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A row may now override only the category, so status becomes optional.
    public function up(): void
    {
        Schema::table('tool_settings', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->change();
            $table->string('category', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        // Rows that only carried a category have nothing left to keep.
        DB::table('tool_settings')->whereNull('status')->delete();

        Schema::table('tool_settings', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->string('status', 20)->nullable(false)->change();
        });
    }
};
