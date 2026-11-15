<?php

use App\Enums\PaymentChannel;
use App\Enums\PaymentChannelKind;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Settings replaced by payment_methods and by the single xendit.expiry_minutes setting.
    private const RETIRED_SETTINGS = ['xendit.channels', 'xendit.qris_minutes', 'xendit.invoice_duration_hours'];

    private const SETTINGS_CACHE_KEY = 'app_settings.v1';

    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20)->unique();
            $table->string('name', 40);
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $enabled = $this->previouslyEnabled();
        $now = now();

        DB::table('payment_methods')->insert(array_map(fn (PaymentChannel $channel, int $index) => [
            'id' => (string) Str::uuid7(),
            'channel' => $channel->value,
            'name' => $channel->fullLabel(),
            'is_active' => in_array($channel->value, $enabled, true),
            'sort' => $index + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], PaymentChannel::cases(), array_keys(PaymentChannel::cases())));

        DB::table('app_settings')->whereIn('key', self::RETIRED_SETTINGS)->delete();
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    public function down(): void
    {
        // Retired settings fall back to their config defaults.
        Schema::dropIfExists('payment_methods');
    }

    /**
     * Channels enabled through the old xendit.channels setting; default QRIS + VA (e-wallets need activation at Xendit).
     *
     * @return list<string>
     */
    private function previouslyEnabled(): array
    {
        $stored = DB::table('app_settings')->where('key', 'xendit.channels')->value('value');

        try {
            $value = is_string($stored) ? Crypt::decryptString($stored) : null;
        } catch (DecryptException) {
            $value = null;
        }

        if ($value !== null) {
            return array_map('trim', explode(',', strtoupper($value)));
        }

        return array_column(array_filter(PaymentChannel::cases(), fn (PaymentChannel $c) => $c->kind() !== PaymentChannelKind::EWallet), 'value');
    }
};
