<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Exceptions\BillingException;
use App\Models\Payment;
use App\Services\Billing\XenditClient;

// Card payments (Payment Sessions): like payment requests, the webhook body is untrusted and state comes from a re-fetch.
class HandleCardSessionCallback
{
    public function __construct(
        private readonly XenditClient $xendit,
        private readonly SettlePayment $settle,
    ) {}

    /**
     * @param  array{event: string, data: array{payment_session_id: string}}&array<string, mixed>  $payload
     */
    public function handle(array $payload): string
    {
        $sessionId = (string) $payload['data']['payment_session_id'];
        $event = strtolower((string) $payload['event']);

        return $this->settle->once('ps:'.$sessionId.':'.$event, $event, $payload, function () use ($sessionId) {
            $payment = Payment::query()->where('xendit_payment_session_id', $sessionId)->first();

            return [$payment === null ? SettlePayment::IGNORED : $this->sync($payment), $payment];
        });
    }

    /**
     * @throws BillingException when Xendit is unreachable
     */
    public function sync(Payment $payment): string
    {
        $sessionId = (string) $payment->xendit_payment_session_id;

        if ($sessionId === '') {
            return SettlePayment::IGNORED;
        }

        $session = $this->xendit->getSession($sessionId);

        if (($session['payment_session_id'] ?? null) !== $sessionId || ($session['reference_id'] ?? null) !== $payment->order_id) {
            report(new BillingException("Xendit session {$sessionId} does not match payment {$payment->order_id}"));

            return SettlePayment::MISMATCH;
        }

        return match (strtoupper((string) ($session['status'] ?? ''))) {
            'COMPLETED' => $this->markPaid($payment, $session),
            'EXPIRED' => $this->settle->close($payment, PaymentStatus::Expired),
            'CANCELED' => $this->settle->close($payment, PaymentStatus::Canceled),
            default => SettlePayment::IGNORED,
        };
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function markPaid(Payment $payment, array $session): string
    {
        $amount = $session['amount'] ?? null;

        if (! is_numeric($amount) || (float) $amount < $payment->amount || ($session['currency'] ?? null) !== $payment->currency) {
            report(new BillingException("Xendit session {$payment->order_id} amount/currency mismatch"));

            return SettlePayment::MISMATCH;
        }

        return $this->settle->paid($payment, $session['updated'] ?? null);
    }
}
