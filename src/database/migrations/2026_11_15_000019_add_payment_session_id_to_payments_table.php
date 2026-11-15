<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Card payments run through a hosted payment session instead of a payment request.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('xendit_payment_session_id', 64)->nullable()->unique()->after('xendit_payment_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['xendit_payment_session_id']);
            $table->dropColumn('xendit_payment_session_id');
        });
    }
};
