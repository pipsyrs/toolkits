<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pricing card bullets are now generated from plans.limits (PlanHighlights).
    public function up(): void
    {
        Schema::dropIfExists('plan_features');
    }

    // Restores the table only; the old labels are not recoverable.
    public function down(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'sort']);
        });
    }
};
