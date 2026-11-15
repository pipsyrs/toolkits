<?php

namespace App\Livewire\Client;

use App\Actions\Billing\HandleXenditPaymentCallback;
use App\Actions\Billing\StartCheckout;
use App\Enums\BillingPeriod;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\QrTemplate;
use App\Exceptions\BillingException;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\ResolvesVisitor;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Billing\PaymentMethodCatalog;
use App\Services\Billing\PlanPeriodCalculator;
use App\Services\Billing\XenditClient;
use App\Services\Qr\QrStyle;
use App\Services\QrCodeService;
use App\Services\Tier\PlanLimits;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::client')]
#[Title('Langganan')]
class Billing extends Component
{
    use GuardsPropertyTypes, ResolvesVisitor, WithPagination;

    // Poll the legacy return page this long after checkout.
    private const POLL_MINUTES = 10;

    // Attempts per user per minute for each action that calls Xendit (checkout, cancel, simulate).
    private const CHECKOUT_PER_MINUTE = 5;

    // Polling asks Xendit for a payment's status at most this often.
    private const SYNC_SECONDS = 10;

    private const QR_SIZE = 320;

    // Codes Xendit can pay in test mode (PRESENT_TO_CUSTOMER actions).
    private const SIMULATABLE = [Payment::DISPLAY_QR, Payment::DISPLAY_VA];

    // Order shown in the checkout modal; initial value from pricing CTA (?plan=&period=).
    #[Url]
    public string $plan = '';

    #[Url]
    public string $period = 'monthly';

    // Selected method in the modal (validated against active methods on pay).
    public string $channel = '';

    // OVO number; sent to Xendit only, never stored.
    public string $mobileNumber = '';

    public bool $checkoutOpen = false;

    // Payment shown in the modal; ownership re-checked on every render.
    #[Locked]
    public ?string $activePaymentId = null;

    // From dashboard.billing.return/{payment}; ownership re-checked on every render.
    #[Locked]
    public ?string $paymentId = null;

    public function mount(?string $payment = null): void
    {
        if ($payment !== null) {
            $this->authorize('view', Payment::query()->findOrFail($payment));
            $this->paymentId = $payment;
        }
    }

    // Opens the modal on the method picker for the chosen plan and period.
    public function startOrder(string $plan, string $period): void
    {
        $this->resetErrorBag();

        if (! app(PlanPeriodCalculator::class)->isPurchasable($plan) || BillingPeriod::tryFrom($period) === null) {
            $this->addError('billing', 'Paket tidak tersedia.');

            return;
        }

        $active = array_keys(app(PaymentMethodCatalog::class)->active());

        $this->plan = $plan;
        $this->period = $period;
        $this->channel = in_array($this->channel, $active, true) ? $this->channel : ($active[0] ?? '');
        $this->activePaymentId = null;
        $this->checkoutOpen = true;
    }

    public function pay(StartCheckout $checkout): void
    {
        $this->resetErrorBag('checkout');
        $this->authorize('create', Payment::class);
        $user = $this->visitor();

        $period = BillingPeriod::tryFrom($this->period);
        $channel = PaymentChannel::tryFrom($this->channel);

        if ($period === null || $channel === null) {
            $this->addError('checkout', 'Pilih paket dan metode pembayaran.');

            return;
        }

        if ($this->throttled('checkout')) {
            return;
        }

        try {
            $this->activePaymentId = $checkout->handle($user, $this->plan, $period, $channel, $this->mobileNumber)->getKey();
        } catch (BillingException $e) {
            $this->addError('checkout', $e->getMessage());
        }
    }

    // Reopens a pending in-app payment (history row or banner).
    public function resume(string $paymentId): void
    {
        $this->resetErrorBag();
        $payment = Str::isUuid($paymentId) ? $this->visitor()->payments()->whereKey($paymentId)->first() : null;

        if ($payment === null || ! $this->isPayable($payment)) {
            $this->addError('billing', 'Pembayaran tidak ditemukan atau sudah kedaluwarsa.');

            return;
        }

        $this->plan = $payment->plan;
        $this->mobileNumber = '';
        $this->period = $payment->period->value;
        $this->channel = (string) $payment->payment_channel;
        $this->activePaymentId = $payment->getKey();
        $this->checkoutOpen = true;
    }

    // From an expired/failed code back to the method picker for the same order.
    public function changeMethod(): void
    {
        $this->resetErrorBag('checkout');
        $this->activePaymentId = null;
    }

    // Polled by the modal and the return page: asks Xendit directly, so a missed webhook cannot leave a paid order pending.
    public function checkStatus(HandleXenditPaymentCallback $status): void
    {
        $payments = $this->visitor()->payments()
            ->whereKey(array_filter([$this->activePaymentId, $this->paymentId]))
            ->where('status', PaymentStatus::Pending->value)
            ->whereNotNull('xendit_payment_request_id')
            ->get();

        foreach ($payments as $payment) {
            if (! Cache::add('payment-sync:'.$payment->getKey(), true, self::SYNC_SECONDS)) {
                continue;
            }

            try {
                $status->sync($payment);
            } catch (BillingException) {
                // Xendit unreachable: the next poll retries.
            }
        }
    }

    public function cancelPayment(StartCheckout $checkout): void
    {
        $this->resetErrorBag('checkout');
        $payment = $this->openPayment();

        if ($payment === null) {
            $this->addError('checkout', 'Pembayaran ini sudah tidak bisa dibatalkan.');

            return;
        }

        if ($this->throttled('payment-cancel')) {
            return;
        }

        match ($checkout->cancel($payment)) {
            PaymentStatus::Canceled => $this->closeCheckout(),
            PaymentStatus::Pending => $this->addError('checkout', 'Pembatalan gagal. Coba lagi sebentar.'),
            // Paid/expired meanwhile: the modal now shows that state.
            default => null,
        };
    }

    // Test mode only: Xendit pays the QR/VA code, then status polling picks up the result.
    public function simulatePayment(XenditClient $xendit): void
    {
        $this->resetErrorBag('checkout');
        $payment = $this->openPayment();

        if ($payment === null || ! $xendit->isTestMode() || ! in_array($payment->payDisplay(), self::SIMULATABLE, true)) {
            $this->addError('checkout', 'Simulasi pembayaran tidak tersedia.');

            return;
        }

        if ($this->throttled('payment-simulate')) {
            return;
        }

        try {
            $xendit->simulatePayment((string) $payment->xendit_payment_request_id, $payment->amount);
            Cache::forget('payment-sync:'.$payment->getKey());
        } catch (BillingException $e) {
            $this->addError('checkout', $e->getMessage());
        }
    }

    public function closeCheckout(): void
    {
        $this->resetErrorBag('checkout');
        $this->activePaymentId = null;
        $this->checkoutOpen = false;
    }

    public function render(): View
    {
        $calculator = app(PlanPeriodCalculator::class);
        $user = $this->visitor();
        $now = CarbonImmutable::now();

        $plans = Plan::query()
            ->whereIn('slug', app(PlanLimits::class)->purchasableSlugs())
            ->orderBy('sort')
            ->get(['id', 'slug', 'name', 'price_monthly', 'price_yearly', 'is_purchasable'])
            ->keyBy('slug');

        $canPurchase = [];
        $upgradePreview = [];

        foreach ($plans as $slug => $plan) {
            $reason = $plan->is_purchasable ? $calculator->denyReason($user, $slug, $now) : 'Paket tidak tersedia.';
            $canPurchase[$slug] = ['allowed' => $reason === null, 'reason' => $reason, 'kind' => $calculator->kind($user, $slug, $now)];

            foreach (BillingPeriod::cases() as $period) {
                $upgradePreview[$slug][$period->value] = $reason === null ? $calculator->nextUntil($user, $slug, $period, $now) : null;
            }
        }

        $payments = $user->payments()
            ->latest('created_at')
            ->paginate(10, ['id', 'order_id', 'invoice_url', 'xendit_payment_request_id', 'plan', 'period', 'kind', 'amount', 'currency', 'status', 'payment_method', 'payment_channel', 'expires_at', 'paid_at', 'period_end', 'created_at']);

        $pendingPayment = $this->paymentId === null ? null : $user->payments()->whereKey($this->paymentId)->first();
        $activePayment = $this->activePaymentId === null ? null : $user->payments()->whereKey($this->activePaymentId)->first();
        $methods = app(PaymentMethodCatalog::class)->active();

        return view('livewire.client.billing', [
            'tier' => $this->tier,
            'planUntil' => $user->plan_until,
            'plans' => $plans,
            'payments' => $payments,
            'pendingPayment' => $pendingPayment,
            'pollPending' => $pendingPayment?->status === PaymentStatus::Pending
                && $pendingPayment->created_at->greaterThan($now->subMinutes(self::POLL_MINUTES)),
            'canPurchase' => $canPurchase,
            'billingEnabled' => app(XenditClient::class)->isConfigured() && $methods !== [],
            'canSimulate' => $activePayment !== null && app(XenditClient::class)->isTestMode() && in_array($activePayment->payDisplay(), self::SIMULATABLE, true),
            'methods' => $methods,
            'activePayment' => $activePayment,
            'activePayable' => $activePayment !== null && $this->isPayable($activePayment),
            'qrImage' => $this->qrImage($activePayment),
            'upgradePreview' => $upgradePreview,
            'selectedPeriod' => BillingPeriod::tryFrom($this->period) ?? BillingPeriod::Monthly,
            'selectedPlan' => $calculator->isPurchasable($this->plan) ? $this->plan : null,
        ]);
    }

    // The modal's payment, if it belongs to the visitor and can still be paid.
    private function openPayment(): ?Payment
    {
        $payment = $this->activePaymentId === null ? null : $this->visitor()->payments()->whereKey($this->activePaymentId)->first();

        return $payment !== null && $this->isPayable($payment) ? $payment : null;
    }

    // Per-user, per-action limit for actions that call Xendit; adds the error itself.
    private function throttled(string $action): bool
    {
        $key = $action.':'.$this->visitor()->getKey();

        if (RateLimiter::tooManyAttempts($key, self::CHECKOUT_PER_MINUTE)) {
            $this->addError('checkout', 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');

            return true;
        }

        RateLimiter::hit($key);

        return false;
    }

    private function isPayable(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Pending
            && $payment->payDisplay() !== null
            && $payment->expires_at?->isFuture() === true;
    }

    private function qrImage(?Payment $payment): ?string
    {
        if ($payment === null || $payment->payDisplay() !== Payment::DISPLAY_QR || ! $this->isPayable($payment)) {
            return null;
        }

        $style = new QrStyle(QrTemplate::Classic, QrTemplate::Classic->colors());

        return app(QrCodeService::class)->png($payment->pay_code, self::QR_SIZE, 'M', $style);
    }
}
