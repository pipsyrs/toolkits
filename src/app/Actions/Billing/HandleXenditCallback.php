<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Exceptions\BillingException;
use App\Models\Payment;
use App\Services\Billing\XenditClient;

// Legacy invoice webhook (Invoice v2): payload is untrusted; state comes from a re-fetch of the invoice (ADR-007).
class HandleXenditCallback
{
    public function __construct(
        private readonly XenditClient $xendit,
        private readonly SettlePayment $settle,
    ) {}

    /**
     * @param  array{id: string, external_id: string, status: string}&array<string, mixed>  $payload
     */
    public function handle(array $payload): string
    {
        $invoiceId = (string) $payload['id'];
        $status = strtolower((string) $payload['status']);

        // One row per invoice+status.
        return $this->settle->once('invoice:'.$invoiceId.':'.$status, 'invoice.'.$status, $payload,
            fn () => $this->process($invoiceId, (string) $payload['external_id']));
    }

    /**
     * @return array{0: string, 1: ?Payment}
     */
    private function process(string $invoiceId, string $externalId): array
    {
        $payment = Payment::query()->where('order_id', $externalId)->first();

        // Invoice id may be unknown only if our create call failed after Xendit created it.
        if ($payment === null || ($payment->xendit_invoice_id !== null && $payment->xendit_invoice_id !== $invoiceId)) {
            return [SettlePayment::IGNORED, $payment];
        }

        $invoice = $this->xendit->getInvoice($invoiceId);

        if (($invoice['id'] ?? null) !== $invoiceId || ($invoice['external_id'] ?? null) !== $payment->order_id) {
            report(new BillingException("Xendit invoice {$invoiceId} does not match payment {$payment->order_id}"));

            return [SettlePayment::MISMATCH, $payment];
        }

        return match (strtoupper((string) ($invoice['status'] ?? ''))) {
            'PAID', 'SETTLED' => [$this->markPaid($payment, $invoice), $payment],
            'EXPIRED' => [$this->settle->close($payment, PaymentStatus::Expired), $payment],
            default => [SettlePayment::IGNORED, $payment],
        };
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function markPaid(Payment $payment, array $invoice): string
    {
        $amount = (int) ($invoice['amount'] ?? 0);
        $paidAmount = isset($invoice['paid_amount']) ? (int) $invoice['paid_amount'] : $amount;

        if ($amount < $payment->amount || $paidAmount < $payment->amount || ($invoice['currency'] ?? null) !== $payment->currency) {
            report(new BillingException("Xendit invoice {$payment->order_id} amount/currency mismatch"));

            return SettlePayment::MISMATCH;
        }

        return $this->settle->paid($payment, $invoice['paid_at'] ?? null, [
            'xendit_invoice_id' => $invoice['id'],
            'payment_method' => $this->short($invoice['payment_method'] ?? null),
            'payment_channel' => $this->short($invoice['payment_channel'] ?? null),
        ]);
    }

    private function short(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 32) : null;
    }
}
