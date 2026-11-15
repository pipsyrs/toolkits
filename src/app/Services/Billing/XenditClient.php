<?php

namespace App\Services\Billing;

use App\Enums\PaymentChannel;
use App\Enums\PaymentChannelKind;
use App\Exceptions\BillingException;
use App\Models\Payment;
use App\Services\Tier\PlanLimits;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

// Xendit via Http facade (no SDK): Payments API v3 for in-app checkout; Invoice v2 GET for legacy invoices.
class XenditClient
{
    private const API_VERSION = '2024-11-11';

    private const VA_DISPLAY_NAME_MAX = 50;

    private const EXPIRY_MIN_MINUTES = 5;

    private const EXPIRY_MAX_MINUTES = 10080;

    // OVO number field in channel_properties (Payments API v3).
    private const OVO_MOBILE_FIELD = 'account_mobile_number';

    // Channel not activated on the Xendit account (or temporarily down).
    private const CHANNEL_DOWN_CODES = ['CHANNEL_UNAVAILABLE', 'CHANNEL_NOT_ACTIVATED'];

    private const TEST_KEY_PREFIX = 'xnd_development_';

    public function isConfigured(): bool
    {
        return filled(config('services.xendit.secret_key'));
    }

    public function isTestMode(): bool
    {
        return str_starts_with((string) config('services.xendit.secret_key'), self::TEST_KEY_PREFIX);
    }

    public function expiresAt(PaymentChannel $channel, CarbonImmutable $from): CarbonImmutable
    {
        $minutes = $channel->fixedExpiryMinutes()
            ?? max(self::EXPIRY_MIN_MINUTES, min(self::EXPIRY_MAX_MINUTES, (int) config('services.xendit.expiry_minutes', 60)));

        return $from->addMinutes($minutes);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BillingException
     */
    public function createPaymentRequest(Payment $payment, PaymentChannel $channel, CarbonImmutable $expiresAt, ?string $mobileNumber = null): array
    {
        $body = [
            'reference_id' => $payment->order_id,
            'type' => 'PAY',
            'country' => 'ID',
            'currency' => $payment->currency,
            'request_amount' => $payment->amount,
            'capture_method' => 'AUTOMATIC',
            'channel_code' => $channel->code(),
            'channel_properties' => $this->channelProperties($payment, $channel, $expiresAt, $mobileNumber),
            'description' => config('app.name').' '.app(PlanLimits::class)->label($payment->plan).' — '.$payment->period->label(),
        ];

        // Idempotency key = order id, so retrying after a lost response cannot create a second charge.
        $request = $this->v3()
            ->withHeaders(['idempotency-key' => $payment->order_id])
            ->retry(2, 300, fn (Throwable $e) => $e instanceof ConnectionException);

        return $this->send(fn () => $request->post('/v3/payment_requests', $body), 'create payment request', 'payment_request_id');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BillingException
     */
    public function getPaymentRequest(string $id): array
    {
        $request = $this->v3()->retry(3, 300, $this->retryableRead(...));

        return $this->send(fn () => $request->get('/v3/payment_requests/'.rawurlencode($id)), 'get payment request', 'payment_request_id');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BillingException
     */
    public function cancelPaymentRequest(string $id): array
    {
        return $this->send(fn () => $this->v3()->post('/v3/payment_requests/'.rawurlencode($id).'/cancel'), 'cancel payment request', 'payment_request_id');
    }

    /**
     * Test mode only: pays a QR/VA request as if the customer did; the outcome arrives like a real payment.
     *
     * @return array<string, mixed>
     *
     * @throws BillingException
     */
    public function simulatePayment(string $id, int $amount): array
    {
        if (! $this->isTestMode()) {
            throw BillingException::unavailable();
        }

        return $this->send(fn () => $this->v3()->post('/v3/payment_requests/'.rawurlencode($id).'/simulate', ['amount' => $amount]), 'simulate payment', 'status');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BillingException
     */
    public function getInvoice(string $id): array
    {
        $request = $this->request()->retry(3, 300, $this->retryableRead(...));

        return $this->send(fn () => $request->get('/v2/invoices/'.rawurlencode($id)), 'get invoice', 'id');
    }

    /**
     * @return array<string, string>
     */
    private function channelProperties(Payment $payment, PaymentChannel $channel, CarbonImmutable $expiresAt, ?string $mobileNumber): array
    {
        if ($channel->kind() !== PaymentChannelKind::EWallet) {
            $properties = ['expires_at' => $expiresAt->utc()->toIso8601ZuluString()];

            return $channel->isQris() ? $properties : $properties + ['display_name' => $this->displayName()];
        }

        // After approving in the e-wallet, the customer lands on the status page of this payment.
        $returnUrl = route('dashboard.billing.return', $payment);
        $properties = ['success_return_url' => $returnUrl, 'failure_return_url' => $returnUrl];

        return $channel->needsMobileNumber() ? $properties + [self::OVO_MOBILE_FIELD => (string) $mobileNumber] : $properties;
    }

    private function retryableRead(Throwable $e): bool
    {
        return $e instanceof ConnectionException || ($e instanceof RequestException && $e->response->serverError());
    }

    // Banks reject punctuation in the VA holder name.
    private function displayName(): string
    {
        $name = trim(mb_substr((string) preg_replace('/[^A-Za-z0-9 ]/', '', (string) config('app.name')), 0, self::VA_DISPLAY_NAME_MAX));

        return $name !== '' ? $name : 'ToolKits';
    }

    private function v3(): PendingRequest
    {
        return $this->request()->withHeaders(['api-version' => self::API_VERSION]);
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw BillingException::unavailable();
        }

        return Http::baseUrl((string) config('services.xendit.base_url', 'https://api.xendit.co'))
            ->withBasicAuth((string) config('services.xendit.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(5);
    }

    /**
     * @param  callable(): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call, string $operation, string $idKey): array
    {
        try {
            $response = $call()->throw();
        } catch (RequestException $e) {
            // Log status + Xendit error code only (never headers/secret).
            $code = (string) $e->response->json('error_code');
            report(new BillingException(sprintf('Xendit %s failed: HTTP %d %s', $operation, $e->response->status(), $code)));

            throw in_array($code, self::CHANNEL_DOWN_CODES, true) ? BillingException::channelUnavailable() : BillingException::unavailable();
        } catch (ConnectionException) {
            report(new BillingException(sprintf('Xendit %s failed: connection error', $operation)));

            throw BillingException::unavailable();
        }

        $data = $response->json();

        if (! is_array($data) || ! is_string($data[$idKey] ?? null)) {
            report(new BillingException(sprintf('Xendit %s failed: unexpected response', $operation)));

            throw BillingException::unavailable();
        }

        return $data;
    }
}
