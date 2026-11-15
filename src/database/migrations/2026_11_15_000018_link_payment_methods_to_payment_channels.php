<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A channel cannot be deleted while a payment method offers it.
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreign('channel')->references('code')->on('payment_channels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropForeign(['channel']);
        });
    }
};
