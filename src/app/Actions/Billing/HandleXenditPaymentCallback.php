<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Exceptions\BillingException;
use App\Models\Payment;
use App\Services\Billing\XenditClient;

// Payments API v3: webhook payload is untrusted; state always comes from a re-fetch of the payment request (ADR-007).
// sync() runs the same check on demand (status polling), so a missing webhook cannot leave a paid order pending.
class HandleXenditPaymentCallback
{
    public function __construct(
        private readonly XenditClient $xendit,
        private readonly SettlePayment $settle,
        private readonly HandleCardSessionCallback $sessions,
    ) {}

    /**
     * @param  array{event: string, data: array{payment_request_id: string}}&array<string, mixed>  $payload
     */
    public function handle(array $payload): string
    {
        $requestId = (string) $payload['data']['payment_request_id'];
        $event = strtolower((string) $payload['event']);

        // One row per payment request+event.
        return $this->settle->once('pr:'.$requestId.':'.$event, $event, $payload, fn () => $this->process($requestId));
    }

    /**
     * @return array{0: string, 1: ?Payment}
     */
    private function process(string $requestId): array
    {
        $payment = Payment::query()->where('xendit_payment_request_id', $requestId)->first();

        return [$payment === null ? SettlePayment::IGNORED : $this->sync($payment), $payment];
    }

    /**
     * @throws BillingException when Xendit is unreachable
     */
    public function sync(Payment $payment): string
    {
        if ($payment->xendit_payment_session_id !== null) {
            return $this->sessions->sync($payment);
        }

        $requestId = (string) $payment->xendit_payment_request_id;

        if ($requestId === '') {
            return SettlePayment::IGNORED;
        }

        $request = $this->xendit->getPaymentRequest($requestId);

        if (($request['payment_request_id'] ?? null) !== $requestId || ($request['reference_id'] ?? null) !== $payment->order_id) {
            report(new BillingException("Xendit payment request {$requestId} does not match payment {$payment->order_id}"));

            return SettlePayment::MISMATCH;
        }

        return match (strtoupper((string) ($request['status'] ?? ''))) {
            'SUCCEEDED' => $this->markPaid($payment, $request),
            'EXPIRED' => $this->settle->close($payment, PaymentStatus::Expired),
            'CANCELED' => $this->settle->close($payment, PaymentStatus::Canceled),
            'FAILED' => $this->settle->close($payment, PaymentStatus::Failed),
            default => SettlePayment::IGNORED,
        };
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function markPaid(Payment $payment, array $request): string
    {
        $amount = $request['request_amount'] ?? null;

        if (! is_numeric($amount) || (float) $amount < $payment->amount || ($request['currency'] ?? null) !== $payment->currency) {
            report(new BillingException("Xendit payment request {$payment->order_id} amount/currency mismatch"));

            return SettlePayment::MISMATCH;
        }

        return $this->settle->paid($payment, $request['updated'] ?? null);
    }
}
