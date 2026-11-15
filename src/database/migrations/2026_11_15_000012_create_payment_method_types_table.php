<?php

use App\Enums\PaymentChannelKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed groups for payment methods; Xendit behaviour still follows the channel.
        Schema::create('payment_method_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 40);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('payment_method_types')->insert(array_map(fn (PaymentChannelKind $kind, int $index) => [
            'id' => (string) Str::uuid7(),
            'code' => $kind->value,
            'name' => $kind->label(),
            'sort' => $index + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], PaymentChannelKind::cases(), array_keys(PaymentChannelKind::cases())));
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_types');
    }
};
