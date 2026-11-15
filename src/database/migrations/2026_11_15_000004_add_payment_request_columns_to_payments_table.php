<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // In-app payments (Payments API v3): request id for webhook lookup, pay_code = QR string or VA number.
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('xendit_payment_request_id', 64)->nullable()->unique()->after('xendit_invoice_id');
            $table->string('pay_code', 1024)->nullable()->after('invoice_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['xendit_payment_request_id']);
            $table->dropColumn(['xendit_payment_request_id', 'pay_code']);
        });
    }
};
