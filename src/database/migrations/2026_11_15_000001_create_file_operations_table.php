<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('guest_id')->nullable();
            $table->string('tool', 40);
            $table->string('status', 20);
            $table->json('options')->nullable();
            $table->json('inputs');
            $table->json('quota_subjects');
            $table->string('output_path')->nullable();
            $table->string('output_name')->nullable();
            $table->unsignedBigInteger('output_size')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Active-op limit per owner and owner history.
            $table->index(['user_id', 'status']);
            $table->index(['guest_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_operations');
    }
};
