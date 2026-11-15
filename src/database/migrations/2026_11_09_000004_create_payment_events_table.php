<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->nullable()->index();
            // Idempotency key: invoice:{id}:{status}.
            $table->string('event_key', 150)->unique();
            $table->string('event_type', 32);
            $table->json('payload');
            $table->string('result', 16)->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
