<?php

namespace App\Actions\Billing;

use App\Enums\BillingPeriod;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Exceptions\BillingException;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\PaymentMethodCatalog;
use App\Services\Billing\PlanPeriodCalculator;
use App\Services\Billing\XenditClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class StartCheckout
{
    // Pending payment code must still be valid this long to be reused.
    private const REUSE_MARGIN_MINUTES = 10;

    // payments.pay_code column size.
    private const PAY_CODE_MAX = 1024;

    // Indonesian mobile number in E.164 (+628…), as OVO expects.
    private const MOBILE_PATTERN = '/^\+628[1-9][0-9]{6,11}$/';

    // Descriptors whose value is a link the customer opens.
    private const URL_DESCRIPTORS = ['WEB_URL', 'DEEPLINK_URL'];

    // Request states in which the customer can still pay.
    private const PAYABLE_STATUSES = ['ACCEPTING_PAYMENTS', 'REQUIRES_ACTION'];

    public function __construct(
        private readonly XenditClient $xendit,
        private readonly PlanPeriodCalculator $calculator,
        private readonly SettlePayment $settle,
        private readonly PaymentMethodCatalog $methods,
        private readonly HandleXenditPaymentCallback $status,
    ) {}

    // Cancels an unpaid request at Xendit. When Xendit refuses (already paid or closed), the real state is synced instead.
    public function cancel(Payment $payment): PaymentStatus
    {
        try {
            $this->xendit->cancelPaymentRequest((string) $payment->xendit_payment_request_id);
            $this->settle->close($payment, PaymentStatus::Canceled);
        } catch (BillingException) {
            try {
                $this->status->sync($payment);
            } catch (BillingException) {
                // Xendit unreachable: stays pending until a webhook or the next sync.
            }
        }

        return $payment->refresh()->status;
    }

    /**
     * Returns the pending payment holding what the modal shows: QR string, VA number, e-wallet link, or nothing (OVO push).
     *
     * @throws BillingException
     */
    public function handle(User $user, string $plan, BillingPeriod $period, PaymentChannel $channel, ?string $mobileNumber = null): Payment
    {
        if (! $user->hasVerifiedEmail() || $user->blocked_at !== null) {
            throw new BillingException('Akun belum dapat melakukan pembayaran.');
        }

        if (! $this->calculator->isPurchasable($plan)) {
            throw new BillingException('Paket tidak tersedia.');
        }

        if (! $this->xendit->isConfigured()) {
            throw BillingException::unavailable();
        }

        if (! $this->methods->isActive($channel)) {
            throw new BillingException('Metode pembayaran tidak tersedia.');
        }

        $mobileNumber = $channel->needsMobileNumber() ? $this->mobileNumber($mobileNumber) : null;

        $model = Plan::query()->where('slug', $plan)->first();
        $amount = (int) ($period === BillingPeriod::Yearly ? $model?->price_yearly : $model?->price_monthly);

        if ($model === null || ! $model->is_purchasable || $amount <= 0) {
            throw new BillingException('Paket tidak tersedia.');
        }

        if (($reason = $this->calculator->denyReason($user, $plan)) !== null) {
            throw new BillingException($reason);
        }

        // Serialize per user so double submits reuse one payment request.
        $lock = Cache::lock('checkout:'.$user->getKey(), 30);

        if (! $lock->get()) {
            throw new BillingException('Pembayaran sedang diproses. Coba lagi sebentar.');
        }

        try {
            // A push notification goes to the number just entered, so OVO always starts a new request.
            $reusable = $channel->needsMobileNumber() ? null : $this->reusable($user, $plan, $period, $channel, $amount);

            return $reusable ?? $this->create($user, $plan, $period, $channel, $amount, $mobileNumber);
        } finally {
            $lock->release();
        }
    }

    private function reusable(User $user, string $plan, BillingPeriod $period, PaymentChannel $channel, int $amount): ?Payment
    {
        return Payment::query()
            ->where('user_id', $user->getKey())
            ->where('status', PaymentStatus::Pending->value)
            ->where('plan', $plan)
            ->where('period', $period->value)
            ->where('payment_channel', $channel->value)
            ->where('amount', $amount)
            ->where('expires_at', '>', now()->addMinutes(self::REUSE_MARGIN_MINUTES))
            ->whereNotNull('xendit_payment_request_id')
            ->latest('created_at')
            ->first();
    }

    private function create(User $user, string $plan, BillingPeriod $period, PaymentChannel $channel, int $amount, ?string $mobileNumber): Payment
    {
        $this->cancelOpenRequests($user);

        $expiresAt = $this->xendit->expiresAt($channel, CarbonImmutable::now());
        $payment = Payment::create([
            'user_id' => $user->getKey(),
            'order_id' => 'TK-'.Str::ulid(),
            'plan' => $plan,
            'period' => $period,
            'kind' => $this->calculator->kind($user, $plan),
            'amount' => $amount,
            'currency' => 'IDR',
            'payment_method' => $channel->method(),
            'payment_channel' => $channel->value,
            'expires_at' => $expiresAt,
        ]);

        try {
            $request = $this->xendit->createPaymentRequest($payment, $channel, $expiresAt, $mobileNumber);
            $code = $this->payCode($request, $channel);

            if (($code === null && ! $channel->needsMobileNumber())
                || ($request['reference_id'] ?? null) !== $payment->order_id
                || ! in_array($request['status'] ?? null, self::PAYABLE_STATUSES, true)) {
                report(new BillingException('Xendit create payment request: unexpected response'));

                throw BillingException::unavailable();
            }

            $payment->update([
                'xendit_payment_request_id' => (string) $request['payment_request_id'],
                'pay_code' => $code,
                'expires_at' => $this->expiry($request['channel_properties']['expires_at'] ?? null, $expiresAt),
            ]);
        } catch (BillingException $e) {
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();

            throw $e;
        }

        return $payment;
    }

    // One open payment request per user, so switching method or plan cannot be paid twice.
    private function cancelOpenRequests(User $user): void
    {
        $open = Payment::query()
            ->where('user_id', $user->getKey())
            ->where('status', PaymentStatus::Pending->value)
            ->whereNotNull('xendit_payment_request_id')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($open as $payment) {
            if ($this->cancel($payment) === PaymentStatus::Paid) {
                throw new BillingException('Pembayaran sebelumnya sudah diterima. Muat ulang halaman untuk melihat paket aktif.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function payCode(array $request, PaymentChannel $channel): ?string
    {
        $actions = array_filter((array) ($request['actions'] ?? []), 'is_array');

        foreach ($channel->descriptors() as $descriptor) {
            foreach ($actions as $action) {
                $value = ($action['descriptor'] ?? null) === $descriptor ? ($action['value'] ?? null) : null;

                if ($this->usableCode($value, $descriptor)) {
                    return $value;
                }
            }
        }

        return null;
    }

    // Links are rendered as href: only https, never javascript:/app schemes.
    private function usableCode(mixed $value, string $descriptor): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > self::PAY_CODE_MAX) {
            return false;
        }

        if (! in_array($descriptor, self::URL_DESCRIPTORS, true)) {
            return ! str_starts_with(strtolower($value), 'https://');
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https';
    }

    // Accepts 08…, 628… or +628…, with spaces or dashes.
    private function mobileNumber(?string $value): string
    {
        $digits = preg_replace('/[\s-]/', '', (string) $value);
        $normalized = match (true) {
            str_starts_with($digits, '+') => $digits,
            str_starts_with($digits, '62') => '+'.$digits,
            str_starts_with($digits, '0') => '+62'.substr($digits, 1),
            default => $digits,
        };

        if (preg_match(self::MOBILE_PATTERN, $normalized) !== 1) {
            throw new BillingException('Nomor OVO tidak valid. Gunakan nomor HP yang terdaftar di OVO, mis. 0812xxxxxxx.');
        }

        return $normalized;
    }

    private function expiry(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        try {
            if (is_string($value) && $value !== '') {
                return CarbonImmutable::parse($value)->utc();
            }
        } catch (Throwable) {
            // Fall through to the requested expiry.
        }

        return $fallback;
    }
}
