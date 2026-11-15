<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            // Pricing card list per plan, in display order.
            $table->index(['plan_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
