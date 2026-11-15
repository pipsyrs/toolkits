<?php

use App\Enums\PaymentChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreignUuid('payment_method_type_id')->nullable()->after('channel')
                ->constrained('payment_method_types')->restrictOnDelete();
        });

        // Existing methods start in the type matching their channel kind.
        $types = DB::table('payment_method_types')->pluck('id', 'code');

        DB::table('payment_methods')->select(['id', 'channel'])->orderBy('id')->each(function (object $method) use ($types) {
            $kind = PaymentChannel::tryFrom($method->channel)?->kind()->value;

            if ($kind !== null && isset($types[$kind])) {
                DB::table('payment_methods')->where('id', $method->id)->update(['payment_method_type_id' => $types[$kind]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_type_id');
        });
    }
};
