<?php

namespace Tests\Feature;

use App\Actions\Admin\ManagePlanLimits;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Exceptions\BillingException;
use App\Livewire\Admin;
use App\Livewire\Client\Billing;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PlanExpiringNotification;
use App\Services\Billing\PaymentMethodCatalog;
use App\Services\Tier\TierResolver;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'cb_token_0123456789abcdef';

    /** @var array<string, array<string, mixed>> Payment request state returned by GET /v3/payment_requests/{id}. */
    private array $requests = [];

    /** @var array<string, string> idempotency-key => payment request id. */
    private array $idempotency = [];

    /** @var array<string, array<string, mixed>> Legacy invoice state returned by GET /v2/invoices/{id}. */
    private array $invoices = [];

    private ?int $apiStatus = null;

    // Xendit error_code returned when creating a payment request.
    private ?string $createError = null;

    /** @var list<array{id: string, amount: mixed}> Simulate calls received. */
    private array $simulated = [];

    // Link returned for e-wallet requests.
    private string $walletUrl = 'https://ewallet.example/authorize/abc';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
        config([
            'services.xendit.secret_key' => 'xnd_development_secret',
            'services.xendit.callback_token' => self::TOKEN,
            'services.xendit.base_url' => 'https://api.xendit.co',
            'services.xendit.expiry_minutes' => 60,
        ]);
        Notification::fake();
        Sleep::fake();
        $this->fakeXendit();
    }

    private function fakeXendit(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($this->apiStatus !== null) {
                return Http::response(['error_code' => 'SERVER_ERROR'], $this->apiStatus);
            }

            if ($request->method() === 'POST' && $path === '/v3/payment_requests') {
                if ($this->createError !== null) {
                    return Http::response(['error_code' => $this->createError], 503);
                }

                $key = $request->header('idempotency-key')[0] ?? '';

                if (! isset($this->idempotency[$key])) {
                    $id = 'pr-'.Str::uuid();
                    $n = count($this->requests);
                    $this->idempotency[$key] = $id;
                    $this->requests[$id] = [
                        'payment_request_id' => $id,
                        'reference_id' => $request['reference_id'],
                        'status' => $request['channel_code'] === 'OVO' ? 'REQUIRES_ACTION' : 'ACCEPTING_PAYMENTS',
                        'request_amount' => $request['request_amount'],
                        'currency' => $request['currency'],
                        'channel_code' => $request['channel_code'],
                        'channel_properties' => $request['channel_properties'],
                        'actions' => match (true) {
                            $request['channel_code'] === 'QRIS' => [['type' => 'PRESENT_TO_CUSTOMER', 'descriptor' => 'QR_STRING', 'value' => '00020101021226660014ID.CO.QRIS.WWW'.$n]],
                            str_ends_with($request['channel_code'], '_VIRTUAL_ACCOUNT') => [['type' => 'PRESENT_TO_CUSTOMER', 'descriptor' => 'VIRTUAL_ACCOUNT_NUMBER', 'value' => '8808123456789'.$n]],
                            $request['channel_code'] === 'OVO' => [],
                            default => [['type' => 'REDIRECT_CUSTOMER', 'descriptor' => 'WEB_URL', 'value' => $this->walletUrl]],
                        },
                    ];
                }

                return Http::response($this->requests[$this->idempotency[$key]]);
            }

            if (preg_match('#^/v3/payment_requests/([^/]+)(/cancel|/simulate)?$#', $path, $m) === 1) {
                $id = $m[1];

                if (! isset($this->requests[$id])) {
                    return Http::response(['error_code' => 'DATA_NOT_FOUND'], 404);
                }

                // Real Xendit settles asynchronously; the fake settles at once and answers like the API.
                if (($m[2] ?? '') === '/simulate') {
                    $this->simulated[] = ['id' => $id, 'amount' => $request['amount']];
                    $this->requests[$id] = array_merge($this->requests[$id], ['status' => 'SUCCEEDED', 'updated' => now()->toIso8601ZuluString()]);

                    return Http::response(['status' => 'PENDING', 'message' => 'Payment simulation is being processed']);
                }

                if (($m[2] ?? '') === '/cancel') {
                    if ($this->requests[$id]['status'] === 'SUCCEEDED') {
                        return Http::response(['error_code' => 'INVALID_STATUS'], 409);
                    }

                    $this->requests[$id]['status'] = 'CANCELED';
                }

                return Http::response($this->requests[$id]);
            }

            if ($request->method() === 'GET' && str_starts_with($path, '/v2/invoices/')) {
                $id = substr($path, strlen('/v2/invoices/'));

                return isset($this->invoices[$id]) ? Http::response($this->invoices[$id]) : Http::response(['error_code' => 'INVOICE_NOT_FOUND_ERROR'], 404);
            }

            return Http::response([], 500);
        });
    }

    private function checkout(User $user, string $plan = 'pro', string $period = 'monthly', string $channel = 'QRIS', string $mobile = ''): Testable
    {
        return Livewire::actingAs($user)->test(Billing::class)
            ->call('startOrder', $plan, $period)
            ->set('channel', $channel)
            ->set('mobileNumber', $mobile)
            ->call('pay');
    }

    private function activate(PaymentChannel ...$channels): void
    {
        PaymentMethod::query()->whereIn('channel', array_column($channels, 'value'))->update(['is_active' => true]);
        app(PaymentMethodCatalog::class)->forget();
    }

    private function pendingPayment(User $user, string $plan = 'pro', string $period = 'monthly', string $channel = 'QRIS'): Payment
    {
        $this->checkout($user, $plan, $period, $channel)->assertHasNoErrors();

        return $user->payments()->latest('created_at')->firstOrFail();
    }

    // Simulates Xendit settling the payment request, then sends the callback.
    private function pay(Payment $payment, array $override = [], string $event = 'payment.capture'): TestResponse
    {
        $this->requests[$payment->xendit_payment_request_id] = array_merge(
            $this->requests[$payment->xendit_payment_request_id],
            ['status' => 'SUCCEEDED', 'updated' => now()->toIso8601ZuluString()],
            $override,
        );

        return $this->paymentWebhook($payment, $event);
    }

    private function paymentWebhook(Payment $payment, string $event, string $token = self::TOKEN): TestResponse
    {
        return $this->postJson('/webhooks/xendit/payments', [
            'event' => $event,
            'business_id' => 'biz',
            'data' => [
                'payment_request_id' => $payment->xendit_payment_request_id,
                'reference_id' => $payment->order_id,
                'status' => 'SUCCEEDED',
                'request_amount' => $payment->amount,
            ],
        ], ['x-callback-token' => $token]);
    }

    // Legacy invoice created before in-app checkout.
    private function legacyInvoice(User $user, array $invoice = []): Payment
    {
        $payment = Payment::factory()->for($user)->create();
        $this->invoices[$payment->xendit_invoice_id] = array_merge([
            'id' => $payment->xendit_invoice_id,
            'external_id' => $payment->order_id,
            'amount' => $payment->amount,
            'paid_amount' => $payment->amount,
            'currency' => 'IDR',
            'status' => 'PAID',
            'payment_method' => 'BANK_TRANSFER',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601ZuluString(),
        ], $invoice);

        return $payment;
    }

    private function invoiceWebhook(Payment $payment, string $status, string $token = self::TOKEN, ?string $externalId = null): TestResponse
    {
        return $this->postJson('/webhooks/xendit', [
            'id' => $payment->xendit_invoice_id,
            'external_id' => $externalId ?? $payment->order_id,
            'status' => $status,
        ], ['x-callback-token' => $token]);
    }

    public function test_qris_checkout_creates_pending_payment_with_db_price(): void
    {
        Carbon::setTestNow('2026-11-15 03:00:00');
        $user = User::factory()->create();

        $component = $this->checkout($user, 'pro', 'yearly')
            ->assertHasNoErrors()
            ->assertSet('checkoutOpen', true)
            ->assertSee('src="data:image/png;base64,iVBORw0KGgo', false)
            ->assertSee('Menunggu pembayaran');

        $payment = Payment::sole();
        $component->assertSet('activePaymentId', $payment->id);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(290000, $payment->amount);
        $this->assertSame('new', $payment->kind);
        $this->assertSame('QRIS', $payment->payment_channel);
        $this->assertSame('QRIS', $payment->payment_method);
        $this->assertStringStartsWith('00020101', $payment->pay_code);
        $this->assertStringStartsWith('pr-', $payment->xendit_payment_request_id);
        $this->assertStringStartsWith('TK-', $payment->order_id);
        $this->assertNull($payment->invoice_url);
        $this->assertTrue($payment->expires_at->equalTo(now()->addMinutes(60)));

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r['request_amount'] === 290000
            && $r['reference_id'] === $payment->order_id
            && $r['channel_code'] === 'QRIS'
            && $r['type'] === 'PAY'
            && ! isset($r['channel_properties']['display_name'])
            && $r['channel_properties']['expires_at'] === '2026-11-15T04:00:00Z'
            && $r->hasHeader('api-version', '2024-11-11')
            && $r->hasHeader('idempotency-key', $payment->order_id)
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_secret:')));
    }

    public function test_va_checkout_shows_account_number(): void
    {
        Carbon::setTestNow('2026-11-15 03:00:00');
        $user = User::factory()->create();

        $component = $this->checkout($user, 'pro', 'monthly', 'BCA')->assertHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('BCA', $payment->payment_channel);
        $this->assertSame('VIRTUAL_ACCOUNT', $payment->payment_method);
        $this->assertTrue($payment->expires_at->equalTo(now()->addMinutes(60)));
        $component->assertSee('Nomor Virtual Account BCA')
            ->assertSee(trim(chunk_split($payment->pay_code, 4, ' ')))
            ->assertDontSee('data:image/png;base64,', false);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r['channel_code'] === 'BCA_VIRTUAL_ACCOUNT'
            && is_string($r['channel_properties']['display_name'] ?? null));
    }

    public function test_double_click_reuses_payment_and_switching_method_cancels_old_code(): void
    {
        $user = User::factory()->create();

        $component = $this->checkout($user)->call('pay');
        $this->assertSame(1, Payment::count());
        Http::assertSentCount(1);

        // Switching method: new code, the old one is canceled so it cannot be paid twice.
        $first = Payment::sole();
        $component->call('closeCheckout')->call('startOrder', 'pro', 'monthly')->set('channel', 'BNI')->call('pay')->assertHasNoErrors();

        $this->assertSame(2, Payment::count());
        $this->assertSame(PaymentStatus::Canceled, $first->fresh()->status);
        $this->assertSame('CANCELED', $this->requests[$first->xendit_payment_request_id]['status']);
        $this->assertSame('BNI', $user->payments()->where('status', 'pending')->sole()->payment_channel);
    }

    public function test_switching_method_after_old_code_was_paid_does_not_create_new_code(): void
    {
        $user = User::factory()->create();
        $component = $this->checkout($user);
        $first = Payment::sole();
        $this->requests[$first->xendit_payment_request_id]['status'] = 'SUCCEEDED';

        // New order with another method: checkout re-checks the open code before replacing it.
        $component->call('closeCheckout')->call('startOrder', 'pro', 'monthly')
            ->set('channel', 'BNI')->call('pay')->assertHasErrors('checkout');

        $this->assertStringContainsString('sudah diterima', $component->errors()->first('checkout'));
        $this->assertSame(1, Payment::count());
        $this->assertSame(PaymentStatus::Paid, $first->fresh()->status);
        $this->assertSame('pro', $user->fresh()->plan);
    }

    public function test_unactivated_channel_shows_friendly_message(): void
    {
        $this->activate(PaymentChannel::LinkAja);
        $this->createError = 'CHANNEL_UNAVAILABLE';

        $component = $this->checkout(User::factory()->create(), 'pro', 'monthly', 'LINKAJA')->assertHasErrors('checkout');

        $this->assertSame(BillingException::channelUnavailable()->getMessage(), $component->errors()->first('checkout'));
        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
    }

    public function test_inactive_or_unknown_method_is_rejected(): void
    {
        PaymentMethod::query()->where('channel', 'BCA')->update(['is_active' => false]);
        $user = User::factory()->create();

        $this->checkout($user, 'pro', 'monthly', 'BCA')->assertHasErrors('checkout')->assertSet('activePaymentId', null);
        // E-wallets start inactive.
        $this->checkout($user, 'pro', 'monthly', 'DANA')->assertHasErrors('checkout');
        $this->checkout($user, 'pro', 'monthly', 'PAYPAL')->assertHasErrors('checkout');

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_ewallet_checkout_shows_link_and_returns_to_status_page(): void
    {
        $this->activate(PaymentChannel::Dana);
        $user = User::factory()->create();

        $component = $this->checkout($user, 'pro', 'monthly', 'DANA')->assertHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('EWALLET', $payment->payment_method);
        $this->assertSame($this->walletUrl, $payment->pay_code);
        $component->assertSee('Buka DANA')->assertSee('href="'.$this->walletUrl.'"', false)->assertSee('target="_blank"', false);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r['channel_code'] === 'DANA'
            && $r['channel_properties']['success_return_url'] === route('dashboard.billing.return', $payment)
            && ! isset($r['channel_properties']['expires_at']));
    }

    public function test_ewallet_link_must_be_https(): void
    {
        $this->activate(PaymentChannel::Dana);
        $this->walletUrl = 'javascript:alert(document.cookie)';

        $this->checkout(User::factory()->create(), 'pro', 'monthly', 'DANA')->assertHasErrors('checkout')->assertSet('activePaymentId', null);

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
        $this->assertNull(Payment::sole()->pay_code);
    }

    public function test_ovo_sends_normalized_number_and_waits_for_push(): void
    {
        Carbon::setTestNow('2026-11-15 03:00:00');
        $this->activate(PaymentChannel::Ovo);
        $user = User::factory()->create();

        foreach (['', '12345', '0812', '+15551234567', str_repeat('8', 40)] as $invalid) {
            $this->checkout($user, 'pro', 'monthly', 'OVO', $invalid)->assertHasErrors('checkout');
        }
        $this->assertSame(0, Payment::count());
        RateLimiter::clear('checkout:'.$user->getKey());

        $this->checkout($user, 'pro', 'monthly', 'OVO', '0812-3456 7890')
            ->assertHasNoErrors()
            ->assertSee('Cek notifikasi di aplikasi OVO');

        $payment = Payment::sole();
        $this->assertNull($payment->pay_code);
        $this->assertTrue($payment->expires_at->equalTo(now()->addMinutes(15)));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['channel_properties']['account_mobile_number'] === '+6281234567890');

        // A new push goes out on every attempt (number may differ).
        $this->checkout($user, 'pro', 'monthly', 'OVO', '6281234567890')->assertHasNoErrors();
        $this->assertSame(2, Payment::count());
    }

    public function test_checkout_rejects_invalid_plan_period_and_unverified_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Billing::class)
            ->call('startOrder', 'pro_plus', 'monthly')
            ->assertHasErrors('billing')
            ->assertSet('checkoutOpen', false);

        Livewire::actingAs($user)->test(Billing::class)
            ->call('startOrder', 'pro', 'monthly')
            ->set('plan', 'enterprise')
            ->call('pay')
            ->assertHasErrors('checkout');

        Livewire::actingAs($user)->test(Billing::class)
            ->call('startOrder', 'pro', 'monthly')
            ->set('period', 'weekly')
            ->call('pay')
            ->assertHasErrors('checkout');

        Livewire::actingAs(User::factory()->unverified()->create())->test(Billing::class)
            ->call('startOrder', 'pro', 'monthly')
            ->call('pay')
            ->assertForbidden();

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_checkout_is_rate_limited_per_user(): void
    {
        $component = $this->checkout(User::factory()->create());

        foreach (range(1, 4) as $ignored) {
            $component->call('pay')->assertHasNoErrors();
        }

        $component->call('pay')->assertHasErrors('checkout');
        $this->assertSame(1, Payment::count());
    }

    public function test_checkout_api_failure_marks_payment_failed(): void
    {
        $this->apiStatus = 500;

        $this->checkout(User::factory()->create())->assertHasErrors('checkout')->assertSet('activePaymentId', null);

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
    }

    public function test_checkout_disabled_when_xendit_not_configured(): void
    {
        config(['services.xendit.secret_key' => null]);

        $this->checkout(User::factory()->create())->assertHasErrors('checkout');
        $this->assertSame(0, Payment::count());
    }

    public function test_cannot_open_another_users_payment(): void
    {
        $owner = User::factory()->create();
        $payment = $this->pendingPayment($owner);
        $attacker = User::factory()->create();

        Livewire::actingAs($attacker)->test(Billing::class)
            ->call('resume', $payment->id)
            ->assertHasErrors('billing')
            ->assertSet('activePaymentId', null)
            ->assertDontSee($payment->pay_code);

        $this->assertThrows(fn () => Livewire::actingAs($attacker)->test(Billing::class)
            ->set('activePaymentId', $payment->id), CannotUpdateLockedPropertyException::class);

        // Owner can reopen it from history.
        Livewire::actingAs($owner)->test(Billing::class)
            ->call('resume', $payment->id)
            ->assertHasNoErrors()
            ->assertSet('activePaymentId', $payment->id)
            ->assertSet('checkoutOpen', true);

        // Expired codes cannot be reopened.
        $payment->forceFill(['expires_at' => now()->subMinute()])->save();
        Livewire::actingAs($owner)->test(Billing::class)->call('resume', $payment->id)->assertHasErrors('billing');
        Livewire::actingAs($owner)->test(Billing::class)->call('resume', 'not-a-uuid')->assertHasErrors('billing');
    }

    public function test_status_poll_syncs_from_xendit_without_webhook(): void
    {
        $user = User::factory()->create();
        $component = $this->checkout($user);
        $payment = Payment::sole();

        $component->call('checkStatus');
        $this->requests[$payment->xendit_payment_request_id]['status'] = 'SUCCEEDED';

        // Throttled: one Xendit lookup per payment per 10 s.
        $component->call('checkStatus');
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->travel(11)->seconds();
        $component->call('checkStatus')->assertSee('Pembayaran diterima');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('pro', $user->fresh()->plan);
        $this->assertSame(0, PaymentEvent::count());
        Notification::assertSentTo($user, PaymentReceivedNotification::class);

        // E-wallet return page polls the same way.
        $this->activate(PaymentChannel::Dana);
        $walletUser = User::factory()->create();
        $wallet = $this->pendingPayment($walletUser, 'pro', 'monthly', 'DANA');
        $this->requests[$wallet->xendit_payment_request_id]['status'] = 'EXPIRED';
        Livewire::actingAs($walletUser)->test(Billing::class, ['payment' => $wallet->id])->call('checkStatus');
        $this->assertSame(PaymentStatus::Expired, $wallet->fresh()->status);
    }

    public function test_user_can_cancel_unpaid_payment(): void
    {
        $user = User::factory()->create();
        // Waiting screen: cancel goes through the confirm dialog; switching method means cancel + new order.
        $component = $this->checkout($user)
            ->assertSee('Batalkan pembayaran?')
            ->assertSeeHtml('role="alertdialog"')
            ->assertDontSee('Ganti metode');
        $payment = Payment::sole();

        $component->call('cancelPayment')->assertHasNoErrors()->assertSet('checkoutOpen', false)->assertSet('activePaymentId', null);

        $this->assertSame(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertSame('CANCELED', $this->requests[$payment->xendit_payment_request_id]['status']);
        $component->assertSee('Dibatalkan');

        // A canceled code cannot be reopened or canceled again.
        $component->call('resume', $payment->id)->assertHasErrors('billing');
        $component->call('cancelPayment')->assertHasErrors('checkout');
    }

    public function test_cancel_after_payment_keeps_it_paid(): void
    {
        $user = User::factory()->create();
        $component = $this->checkout($user);
        $payment = Payment::sole();
        $this->requests[$payment->xendit_payment_request_id]['status'] = 'SUCCEEDED';

        $component->call('cancelPayment')->assertHasNoErrors()->assertSee('Pembayaran diterima');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('pro', $user->fresh()->plan);
    }

    public function test_simulate_payment_only_in_test_mode_for_qr_and_va(): void
    {
        $user = User::factory()->create();
        $component = $this->checkout($user)->assertSee('Simulasikan pembayaran');
        $payment = Payment::sole();

        // Checkout attempts do not use up the simulate limit.
        foreach (range(1, 5) as $ignored) {
            RateLimiter::hit('checkout:'.$user->getKey());
        }

        $component->call('simulatePayment')->assertHasNoErrors();
        $this->assertSame([['id' => $payment->xendit_payment_request_id, 'amount' => $payment->amount]], $this->simulated);

        $component->call('checkStatus')->assertSee('Pembayaran diterima');
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);

        // E-wallet links use Xendit's own sandbox page instead.
        $this->activate(PaymentChannel::Dana);
        $this->checkout(User::factory()->create(), 'pro', 'monthly', 'DANA')
            ->assertDontSee('Simulasikan pembayaran')
            ->call('simulatePayment')->assertHasErrors('checkout');

        // Live key: no button, and the action refuses.
        $live = $this->checkout(User::factory()->create());
        config(['services.xendit.secret_key' => 'xnd_production_secret']);
        $live->call('$refresh')->assertDontSee('Simulasikan pembayaran')->call('simulatePayment')->assertHasErrors('checkout');

        $this->assertCount(1, $this->simulated);
    }

    public function test_webhook_rejects_wrong_or_missing_token(): void
    {
        $payment = $this->pendingPayment(User::factory()->create());
        $this->requests[$payment->xendit_payment_request_id]['status'] = 'SUCCEEDED';

        $this->paymentWebhook($payment, 'payment.capture', 'wrong-token')->assertStatus(401);
        $this->postJson('/webhooks/xendit/payments', ['event' => 'payment.capture', 'data' => ['payment_request_id' => $payment->xendit_payment_request_id]])->assertStatus(401);
        $this->postJson('/webhooks/xendit', ['id' => 'inv1', 'external_id' => $payment->order_id, 'status' => 'PAID'])->assertStatus(401);

        config(['services.xendit.callback_token' => '']);
        $this->paymentWebhook($payment, 'payment.capture', '')->assertStatus(401);

        $this->assertSame(0, PaymentEvent::count());
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_paid_webhook_activates_plan_and_modal_shows_success(): void
    {
        Carbon::setTestNow('2026-11-09 03:00:00');
        $user = User::factory()->create();
        $component = $this->checkout($user);
        $payment = Payment::sole();

        $this->pay($payment)->assertOk()->assertJson(['ok' => true]);

        $user->refresh();
        $payment->refresh();
        $this->assertSame('pro', $user->plan);
        $this->assertTrue($user->plan_until->equalTo(CarbonImmutable::parse('2026-12-09 03:00:00')));
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('QRIS', $payment->payment_channel);
        $this->assertTrue($payment->period_end->equalTo($user->plan_until));
        $this->assertSame('processed', PaymentEvent::sole()->result);
        Notification::assertSentTo($user, PaymentReceivedNotification::class);

        // Poll re-render reads the new state from the DB.
        $component->call('$refresh')->assertSee('Pembayaran diterima')->assertDontSee('Menunggu pembayaran');
    }

    public function test_admin_created_plan_can_be_bought_and_activates_its_tier(): void
    {
        Carbon::setTestNow('2026-11-09 03:00:00');
        $admin = User::factory()->create()->assignRole('superadmin');
        app(ManagePlanLimits::class)->create($admin, ['name' => 'Bisnis', 'slug' => 'bisnis', 'price_monthly' => 99000, 'price_yearly' => 990000]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Billing::class)->assertSee('Bisnis');
        $this->checkout($user, 'bisnis')->assertHasNoErrors();
        $payment = Payment::sole();
        $this->assertSame(99000, $payment->amount);

        $this->pay($payment)->assertOk();

        $tier = app(TierResolver::class)->forUser($user->refresh());
        $this->assertSame('bisnis', $tier->value);
        $this->assertTrue($tier->isPaid());
    }

    public function test_active_plan_blocks_buying_a_different_plan(): void
    {
        $admin = User::factory()->create()->assignRole('superadmin');
        app(ManagePlanLimits::class)->create($admin, ['name' => 'Bisnis', 'slug' => 'bisnis', 'price_monthly' => 99000, 'price_yearly' => 990000]);
        $user = User::factory()->pro()->create();

        $this->checkout($user, 'bisnis')->assertHasErrors('checkout');
        $this->assertSame(0, Payment::count());

        // Renewing the active plan still works.
        $this->checkout($user, 'pro')->assertHasNoErrors();
    }

    public function test_forged_paid_payload_is_rejected_when_api_says_pending(): void
    {
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        // API still reports ACCEPTING_PAYMENTS.
        $this->paymentWebhook($payment, 'payment.capture')->assertOk();

        $this->assertNull($user->fresh()->plan);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame('ignored', PaymentEvent::sole()->result);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        Carbon::setTestNow('2026-11-09 03:00:00');
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        $this->pay($payment)->assertOk();
        $this->paymentWebhook($payment, 'payment.capture')->assertOk();
        // Different event, same request: layer 2 blocks double activation.
        $this->paymentWebhook($payment, 'payment_request.expiry')->assertOk();

        $this->assertTrue($user->fresh()->plan_until->equalTo(CarbonImmutable::parse('2026-12-09 03:00:00')));
        $this->assertSame(2, PaymentEvent::count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        Notification::assertSentToTimes($user, PaymentReceivedNotification::class, 1);
    }

    public function test_amount_currency_or_reference_mismatch_does_not_activate(): void
    {
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);

        $this->pay($payment, ['request_amount' => 1000])->assertOk();
        $this->assertNull($user->fresh()->plan);
        $this->assertSame('mismatch', PaymentEvent::sole()->result);

        $other = $this->pendingPayment($user, 'pro', 'yearly');
        $this->pay($other, ['currency' => 'USD'])->assertOk();
        $this->assertSame(PaymentStatus::Pending, $other->fresh()->status);

        $third = $this->pendingPayment($user, 'pro', 'monthly', 'BRI');
        $this->pay($third, ['reference_id' => $payment->order_id])->assertOk();
        $this->assertSame(PaymentStatus::Pending, $third->fresh()->status);
        $this->assertNull($user->fresh()->plan);
    }

    public function test_expired_or_failed_request_closes_payment_without_changing_tier(): void
    {
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);
        $this->requests[$payment->xendit_payment_request_id]['status'] = 'EXPIRED';

        $this->paymentWebhook($payment, 'payment_request.expiry')->assertOk();
        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);

        $failed = $this->pendingPayment($user, 'pro', 'yearly', 'BCA');
        $this->requests[$failed->xendit_payment_request_id]['status'] = 'FAILED';
        $this->paymentWebhook($failed, 'payment.failure')->assertOk();
        $this->assertSame(PaymentStatus::Failed, $failed->fresh()->status);

        $this->assertNull($user->fresh()->plan);
    }

    public function test_payment_webhook_ignores_unrelated_events_and_validates_payload(): void
    {
        $payment = $this->pendingPayment(User::factory()->create());
        $headers = ['x-callback-token' => self::TOKEN];

        $this->postJson('/webhooks/xendit/payments', ['event' => 'payment_token.activation', 'data' => []], $headers)->assertOk();
        $this->postJson('/webhooks/xendit/payments', ['event' => 'payment.capture', 'data' => ['payment_request_id' => '../x']], $headers)->assertStatus(422);
        $this->postJson('/webhooks/xendit/payments', ['event' => 'payment.capture'], $headers)->assertStatus(422);
        // Unknown request id: recorded, nothing changes.
        $this->postJson('/webhooks/xendit/payments', ['event' => 'payment.capture', 'data' => ['payment_request_id' => 'pr-unknown']], $headers)->assertOk();

        $this->assertSame('ignored', PaymentEvent::sole()->result);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_api_error_during_webhook_returns_500_and_allows_retry(): void
    {
        $user = User::factory()->create();
        $payment = $this->pendingPayment($user);
        $this->apiStatus = 503;

        $this->withoutExceptionHandling();

        try {
            $this->paymentWebhook($payment, 'payment.capture');
            $this->fail('Expected exception');
        } catch (BillingException) {
            // Bubbles up → 500.
        }

        $this->assertSame(0, PaymentEvent::count());
    }

    public function test_renewal_extends_from_active_plan_until(): void
    {
        Carbon::setTestNow('2026-11-09 03:00:00');
        $user = User::factory()->create();
        $user->forceFill(['plan' => 'pro', 'plan_until' => '2026-11-19 03:00:00'])->save();
        $payment = $this->pendingPayment($user);
        $this->assertSame('renewal', $payment->kind);

        $this->pay($payment)->assertOk();

        $this->assertTrue($user->fresh()->plan_until->equalTo(CarbonImmutable::parse('2026-12-19 03:00:00')));
    }

    public function test_legacy_invoice_webhook_still_settles(): void
    {
        Carbon::setTestNow('2026-11-09 03:00:00');
        $user = User::factory()->create();
        $payment = $this->legacyInvoice($user);

        $this->invoiceWebhook($payment, 'PAID')->assertOk();
        $this->invoiceWebhook($payment, 'PAID')->assertOk();
        $this->invoiceWebhook($payment, 'SETTLED')->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('BCA', $payment->payment_channel);
        $this->assertTrue($user->fresh()->plan_until->equalTo(CarbonImmutable::parse('2026-12-09 03:00:00')));
        $this->assertSame('ignored', PaymentEvent::where('event_key', 'like', '%:settled')->value('result'));
        Notification::assertSentToTimes($user, PaymentReceivedNotification::class, 1);
    }

    public function test_legacy_invoice_webhook_rejects_forged_mismatched_and_expired(): void
    {
        $user = User::factory()->create();

        $pending = $this->legacyInvoice($user, ['status' => 'PENDING']);
        $this->invoiceWebhook($pending, 'PAID')->assertOk();
        $this->assertSame(PaymentStatus::Pending, $pending->fresh()->status);

        $cheap = $this->legacyInvoice($user, ['amount' => 1000, 'paid_amount' => 1000]);
        $this->invoiceWebhook($cheap, 'PAID')->assertOk();
        $this->assertSame(PaymentStatus::Pending, $cheap->fresh()->status);

        // Invoice of one payment presented with the order of another.
        $other = $this->legacyInvoice($user, ['status' => 'PENDING']);
        $this->invoiceWebhook($cheap, 'PAID', externalId: $other->order_id)->assertOk();
        $this->assertSame(PaymentStatus::Pending, $other->fresh()->status);

        $expired = $this->legacyInvoice($user, ['status' => 'EXPIRED']);
        $this->invoiceWebhook($expired, 'EXPIRED')->assertOk();
        $this->assertSame(PaymentStatus::Expired, $expired->fresh()->status);

        $this->assertNull($user->fresh()->plan);
    }

    public function test_return_url_does_not_activate_and_is_owner_only(): void
    {
        $owner = User::factory()->create();
        $payment = $this->legacyInvoice($owner, ['status' => 'PENDING']);

        $this->actingAs($owner)->get(route('dashboard.billing.return', $payment))->assertOk()->assertSee($payment->order_id);
        $this->assertNull($owner->fresh()->plan);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->actingAs(User::factory()->create())->get(route('dashboard.billing.return', $payment))->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get(route('dashboard.billing.return', $payment))->assertForbidden();
    }

    public function test_billing_history_shows_only_own_payments(): void
    {
        $user = User::factory()->create();
        $mine = Payment::factory()->for($user)->paid()->create();
        $theirs = Payment::factory()->paid()->create();

        $this->actingAs($user)->get(route('dashboard.billing'))
            ->assertOk()
            ->assertSee($mine->order_id)
            ->assertDontSee($theirs->order_id);
        $this->actingAs($user)->get(route('dashboard.billing', ['plan' => 'pro', 'period' => 'yearly']))->assertOk();
        $this->actingAs($user)->get(route('dashboard.billing', ['plan' => 'x', 'period' => 'weekly']))->assertOk();

        // Locked paymentId cannot be swapped to another user's payment.
        $this->assertThrows(fn () => Livewire::actingAs($user)->test(Billing::class)
            ->set('paymentId', $theirs->id), CannotUpdateLockedPropertyException::class);
    }

    public function test_webhook_route_is_outside_web_group(): void
    {
        foreach (['webhooks.xendit', 'webhooks.xendit.payments'] as $name) {
            $this->assertSame(['throttle:120,1'], Route::getRoutes()->getByName($name)->gatherMiddleware());
        }

        // Blocked owner: payment still settles (no not.blocked / session on webhook).
        $user = User::factory()->blocked()->create();
        $payment = Payment::factory()->for($user)->create(['xendit_payment_request_id' => 'pr-blocked', 'pay_code' => 'x']);
        $this->requests['pr-blocked'] = ['payment_request_id' => 'pr-blocked', 'reference_id' => $payment->order_id, 'request_amount' => $payment->amount, 'currency' => 'IDR'];
        $this->pay($payment)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_reminder_sent_once_per_plan_until(): void
    {
        $wib = fn (string $at) => CarbonImmutable::parse($at, 'Asia/Jakarta')->utc();
        Carbon::setTestNow($wib('2026-11-09 09:00:00'));
        $due = User::factory()->create();
        $due->forceFill(['plan' => 'pro', 'plan_until' => $wib('2026-11-12 20:00:00')])->save();
        $later = User::factory()->create();
        $later->forceFill(['plan' => 'pro', 'plan_until' => $wib('2026-11-13 08:00:00')])->save();

        $this->artisan('billing:send-renewal-reminders')->assertSuccessful();
        $this->artisan('billing:send-renewal-reminders')->assertSuccessful();

        Notification::assertSentToTimes($due, PlanExpiringNotification::class, 1);
        Notification::assertNotSentTo($later, PlanExpiringNotification::class);
        $this->assertTrue($due->fresh()->plan_reminder_for->equalTo($due->fresh()->plan_until));

        // A new plan_until (after renewal) gets its own reminder.
        $due->forceFill(['plan_until' => $wib('2026-11-12 22:00:00'), 'plan_reminder_for' => null])->save();
        $this->artisan('billing:send-renewal-reminders')->assertSuccessful();
        Notification::assertSentToTimes($due, PlanExpiringNotification::class, 2);
    }

    public function test_admin_payments_requires_permission(): void
    {
        $payment = Payment::factory()->paid()->create();

        $this->actingAs(User::factory()->create())->get('/admin/payments')->assertForbidden();

        $auditor = User::factory()->create()->assignRole(Role::create(['name' => 'auditor', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'audit.view']));
        $this->actingAs($auditor)->get('/admin/payments')->assertForbidden();

        $finance = User::factory()->create()->assignRole(Role::create(['name' => 'finance', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'payments.view']));
        $this->actingAs($finance)->get('/admin/payments')->assertOk()->assertSee($payment->order_id);

        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get('/admin/payments')->assertOk();

        Livewire::actingAs($finance)->test(Admin\Payments\Index::class)
            ->set('search', $payment->order_id)
            ->assertSee($payment->order_id)
            ->set('status', 'pending')
            ->assertDontSee($payment->order_id);
    }
}
