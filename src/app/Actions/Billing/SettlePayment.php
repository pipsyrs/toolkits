<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Services\Billing\PlanPeriodCalculator;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

// Shared by the Xendit webhooks: event dedupe and the only paths that change a payment's final state.
class SettlePayment
{
    public const DUPLICATE = 'duplicate';

    public const PROCESSED = 'processed';

    public const IGNORED = 'ignored';

    public const MISMATCH = 'mismatch';

    public function __construct(private readonly PlanPeriodCalculator $calculator) {}

    /**
     * Runs $process once per event key (layer 1 idempotency).
     *
     * @param  array<string, mixed>  $payload
     * @param  Closure(): array{0: string, 1: ?Payment}  $process
     */
    public function once(string $eventKey, string $eventType, array $payload, Closure $process): string
    {
        $inserted = PaymentEvent::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'event_key' => $eventKey,
            'event_type' => mb_substr($eventType, 0, 32),
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            return self::DUPLICATE;
        }

        try {
            [$result, $payment] = $process();
        } catch (Throwable $e) {
            // Let Xendit retry the same event.
            PaymentEvent::query()->where('event_key', $eventKey)->delete();

            throw $e;
        }

        PaymentEvent::query()->where('event_key', $eventKey)->update([
            'payment_id' => $payment?->getKey(),
            'result' => $result,
            'processed_at' => now(),
        ]);

        return $result;
    }

    /**
     * Marks the payment paid and activates the plan. Amount/currency must be verified by the caller.
     *
     * @param  mixed  $paidAt  Provider timestamp; falls back to now.
     * @param  array<string, mixed>  $attributes  Extra columns from the provider (invoice id, method).
     */
    public function paid(Payment $payment, mixed $paidAt, array $attributes = []): string
    {
        $paidAt = $this->time($paidAt) ?? CarbonImmutable::now();

        $activated = DB::transaction(function () use ($payment, $paidAt, $attributes) {
            $user = $payment->user_id === null ? null : User::query()->lockForUpdate()->find($payment->user_id);
            $window = $user === null ? null : $this->calculator->apply($user, $payment->plan, $payment->period, CarbonImmutable::now());

            // Layer 2: only one request may move the row to paid (expired/failed/canceled still count if money arrived).
            $affected = Payment::query()
                ->whereKey($payment->getKey())
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Expired->value, PaymentStatus::Failed->value, PaymentStatus::Canceled->value])
                ->update([
                    ...$attributes,
                    'status' => PaymentStatus::Paid->value,
                    'paid_at' => $paidAt,
                    'period_start' => $window['start'] ?? null,
                    'period_end' => $window['until'] ?? null,
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                return false;
            }

            $user?->forceFill([
                'plan' => $window['plan'],
                'plan_until' => $window['until'],
                'plan_reminder_for' => null,
            ])->save();

            return true;
        });

        if (! $activated) {
            return self::IGNORED;
        }

        $payment->refresh();
        $payment->user?->notify(new PaymentReceivedNotification($payment));

        return self::PROCESSED;
    }

    // Closes a still-pending payment as expired, failed or canceled.
    public function close(Payment $payment, PaymentStatus $status): string
    {
        $affected = Payment::query()
            ->whereKey($payment->getKey())
            ->where('status', PaymentStatus::Pending->value)
            ->update(['status' => $status->value, 'updated_at' => now()]);

        return $affected === 1 ? self::PROCESSED : self::IGNORED;
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->utc() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
