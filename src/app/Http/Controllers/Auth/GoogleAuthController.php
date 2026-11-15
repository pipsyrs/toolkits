<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginWithGoogle;
use App\Exceptions\GoogleAuthException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\IntendedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    private const INTENT = 'google.intent';

    public function redirect(Request $request): Response
    {
        $request->session()->put(self::INTENT, ['mode' => 'login', 'remember' => $request->boolean('remember')]);

        return Socialite::driver('google')->redirect();
    }

    // Re-auth for accounts without password (replaces password confirmation).
    public function confirm(Request $request): Response
    {
        $request->session()->put(self::INTENT, ['mode' => 'confirm']);

        // Return to the originating page (e.g. profile) unless password.confirm already set one.
        IntendedUrl::rememberPrevious($request);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, LoginWithGoogle $action): Response
    {
        $intent = (array) $request->session()->pull(self::INTENT, ['mode' => 'login']);
        $confirming = ($intent['mode'] ?? null) === 'confirm' && $request->user() !== null;

        if ($request->filled('error')) {
            return $this->fail('Login Google dibatalkan.', $confirming);
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->fail('Sesi login Google kedaluwarsa. Silakan coba lagi.', $confirming);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Login Google gagal. Silakan coba lagi.', $confirming);
        }

        $current = $request->user();

        if ($current instanceof User) {
            return $confirming
                ? $this->confirmed($request, $current, (string) $google->getId())
                : app(LoginResponse::class)->toResponse($request);
        }

        try {
            $user = $action->handle($google);
        } catch (GoogleAuthException $e) {
            return $this->fail($e->getMessage());
        }

        $remember = (bool) ($intent['remember'] ?? false);

        // 2FA protects the account, not the login method: same challenge as password login.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => $remember]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $request->session()->passwordConfirmed();

        return app(LoginResponse::class)->toResponse($request);
    }

    private function confirmed(Request $request, User $user, string $googleId): RedirectResponse
    {
        if ($user->google_id === null || ! hash_equals($user->google_id, $googleId)) {
            return $this->fail('Akun Google tidak cocok dengan akun ini.', true);
        }

        $request->session()->passwordConfirmed();

        return redirect()->intended(route('dashboard'));
    }

    // Confirm mode keeps the user logged in on password.confirm.
    private function fail(string $message, bool $confirming = false): RedirectResponse
    {
        return redirect()->route($confirming ? 'password.confirm' : 'login')->withErrors(['google' => $message]);
    }
}
