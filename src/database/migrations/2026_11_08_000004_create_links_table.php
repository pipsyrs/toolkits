<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable();
            $table->uuid('guest_id')->nullable()->index();
            $table->string('code', 30)->unique();
            $table->boolean('is_custom')->default(false);
            $table->text('target_url');
            $table->string('target_host')->index();
            $table->string('password_hash')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->string('created_tier', 10);
            $table->unsignedBigInteger('clicks_total')->default(0);
            $table->dateTime('last_clicked_at')->nullable();
            $table->timestamps();

            // Dashboard list (WHERE user_id ORDER BY created_at); also backs the user_id FK.
            $table->index(['user_id', 'created_at']);
            // Free custom-alias count (WHERE user_id AND is_custom).
            $table->index(['user_id', 'is_custom']);

            // FK declared after composite indexes so no redundant single-column index is created.
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
