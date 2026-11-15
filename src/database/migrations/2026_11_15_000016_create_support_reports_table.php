<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Reports sent by users to customer service.
    public function up(): void
    {
        Schema::create('support_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('subject', 120);
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->text('admin_reply')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_reports');
    }
};
