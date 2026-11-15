<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Built-in channels at the time of this migration: code, gateway code, name, kind, color, needs mobile number, fixed expiry.
    private const CHANNELS = [
        ['QRIS', 'QRIS', 'QRIS', 'qris', '#15181d', false, null],
        ['BCA', 'BCA_VIRTUAL_ACCOUNT', 'BCA', 'va', '#0060af', false, null],
        ['BNI', 'BNI_VIRTUAL_ACCOUNT', 'BNI', 'va', '#f15a23', false, null],
        ['BRI', 'BRI_VIRTUAL_ACCOUNT', 'BRI', 'va', '#00529c', false, null],
        ['MANDIRI', 'MANDIRI_VIRTUAL_ACCOUNT', 'Mandiri', 'va', '#003d79', false, null],
        ['PERMATA', 'PERMATA_VIRTUAL_ACCOUNT', 'Permata', 'va', '#d71920', false, null],
        ['BSI', 'BSI_VIRTUAL_ACCOUNT', 'BSI', 'va', '#00a39d', false, null],
        ['OVO', 'OVO', 'OVO', 'ewallet', '#4c3494', true, 15],
        ['DANA', 'DANA', 'DANA', 'ewallet', '#108ee9', false, 1440],
        ['SHOPEEPAY', 'SHOPEEPAY', 'ShopeePay', 'ewallet', '#ee4d2d', false, 30],
        ['LINKAJA', 'LINKAJA', 'LinkAja', 'ewallet', '#e82127', false, 30],
        ['ASTRAPAY', 'ASTRAPAY', 'AstraPay', 'ewallet', '#0066b3', false, 15],
        ['CARD', 'CARDS', 'Kartu', 'card', '#1a1f36', false, null],
    ];

    public function up(): void
    {
        Schema::create('payment_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Same size as payment_methods.channel, which references it.
            $table->string('code', 20)->unique();
            $table->string('gateway_code', 60);
            $table->string('name', 40);
            $table->string('kind', 20);
            $table->string('color', 7);
            $table->boolean('needs_mobile_number')->default(false);
            $table->unsignedSmallInteger('fixed_expiry_minutes')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('payment_channels')->insert(array_map(fn (array $c, int $index) => [
            'id' => (string) Str::uuid7(),
            'code' => $c[0],
            'gateway_code' => $c[1],
            'name' => $c[2],
            'kind' => $c[3],
            'color' => $c[4],
            'needs_mobile_number' => $c[5],
            'fixed_expiry_minutes' => $c[6],
            'sort' => $index + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::CHANNELS, array_keys(self::CHANNELS)));

        // Fresh installs already get the card type from the type seed; older ones get it here.
        if (! DB::table('payment_method_types')->where('code', 'card')->exists()) {
            DB::table('payment_method_types')->insert([
                'id' => (string) Str::uuid7(),
                'code' => 'card',
                'name' => 'Kartu kredit/debit',
                'sort' => (int) DB::table('payment_method_types')->max('sort') + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Card is offered as an inactive method: switch it on once cards are enabled at the payment gateway.
        if (! DB::table('payment_methods')->where('channel', 'CARD')->exists()) {
            DB::table('payment_methods')->insert([
                'id' => (string) Str::uuid7(),
                'channel' => 'CARD',
                'payment_method_type_id' => DB::table('payment_method_types')->where('code', 'card')->value('id'),
                'name' => 'Kartu kredit/debit',
                'is_active' => false,
                'sort' => (int) DB::table('payment_methods')->max('sort') + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // The card type stays: it may predate this migration and is harmless without methods.
        DB::table('payment_methods')->where('channel', 'CARD')->delete();
        Schema::dropIfExists('payment_channels');
    }
};
