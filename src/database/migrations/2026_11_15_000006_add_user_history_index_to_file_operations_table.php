<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dashboard file history: newest first per user.
        Schema::table('file_operations', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('file_operations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
