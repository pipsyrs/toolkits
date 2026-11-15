<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject', 80);
            $table->string('metric', 32);
            $table->date('period_date');
            $table->unsignedInteger('used')->default(0);
            $table->timestamp('updated_at')->nullable();

            // Lookup key for reserve/refund (ADR-002) and insertOrIgnore target.
            $table->unique(['subject', 'metric', 'period_date']);
            // Cleanup of old periods.
            $table->index('period_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
