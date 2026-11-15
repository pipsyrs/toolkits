<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable();
            $table->string('order_id', 64)->unique();
            $table->string('xendit_invoice_id', 64)->nullable()->unique();
            $table->string('invoice_url', 2048)->nullable();
            $table->string('plan', 20);
            $table->string('period', 10);
            $table->string('kind', 10);
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 16)->default('pending');
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_channel', 32)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->timestamps();

            // User billing history (paginate, newest first); also backs the user_id FK.
            $table->index(['user_id', 'created_at']);
            // Admin list filtered by status, newest first.
            $table->index(['status', 'created_at']);
            // Admin "paid this month" totals: status = paid AND paid_at range.
            $table->index(['status', 'paid_at']);
            // Reuse pending invoice: equality on 4 cols + range on expires_at.
            $table->index(['user_id', 'status', 'plan', 'period', 'expires_at'], 'payments_reuse_pending_index');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
