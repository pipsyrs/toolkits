<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\HandleXenditCallback;
use App\Actions\Billing\HandleXenditPaymentCallback;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

// Outside the web group: no session, CSRF, guest cookie or not.blocked.
// Exceptions from the handlers bubble up as 500 so Xendit retries.
class XenditWebhookController extends Controller
{
    // Payments API v3 events that can change a payment request; others are acknowledged and dropped.
    private const PAYMENT_EVENT_PATTERN = '/^(payment|payment_request)\.[a-z_]+$/';

    // Legacy Invoice v2 callback.
    public function __invoke(Request $request, HandleXenditCallback $handler): JsonResponse
    {
        if (! $this->hasValidToken($request)) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->json()->all();

        $validator = Validator::make($payload, [
            'id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'external_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z_]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false], 422);
        }

        $handler->handle($payload);

        return response()->json(['ok' => true]);
    }

    public function payments(Request $request, HandleXenditPaymentCallback $handler): JsonResponse
    {
        if (! $this->hasValidToken($request)) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if (! is_string($event) || strlen($event) > 40 || preg_match(self::PAYMENT_EVENT_PATTERN, $event) !== 1) {
            return response()->json(['ok' => true]);
        }

        $validator = Validator::make($payload, [
            'data' => ['required', 'array'],
            'data.payment_request_id' => ['required', 'string', 'max:64', 'regex:/^pr-[A-Za-z0-9-]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false], 422);
        }

        $handler->handle($payload);

        return response()->json(['ok' => true]);
    }

    private function hasValidToken(Request $request): bool
    {
        $expected = (string) config('services.xendit.callback_token');
        $given = $request->header('x-callback-token');

        if ($expected === '' || ! is_string($given) || ! hash_equals($expected, $given)) {
            Log::warning('Xendit webhook rejected: invalid callback token', ['ip' => $request->ip()]);

            return false;
        }

        return true;
    }
}
