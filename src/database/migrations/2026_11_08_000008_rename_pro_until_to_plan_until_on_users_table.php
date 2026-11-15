<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('pro_until', 'plan_until');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameIndex('users_pro_until_index', 'users_plan_until_index');
            $table->string('plan', 20)->nullable()->after('blocked_reason');
        });

        // Existing paid users were all Pro.
        DB::table('users')->whereNotNull('plan_until')->update(['plan' => 'pro']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plan');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameIndex('users_plan_until_index', 'users_pro_until_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('plan_until', 'pro_until');
        });
    }
};
