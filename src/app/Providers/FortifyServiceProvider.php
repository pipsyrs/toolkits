<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Middleware\EnsureChallengedUserNotBlocked;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Models\User;
use App\Services\Quota\QuotaService;
use App\Support\IntendedUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(function (Request $request) {
            IntendedUrl::rememberPrevious($request);

            return view('auth.confirm-password');
        });
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower((string) $request->input(Fortify::username())))->first();

            // Google-only accounts (password null) cannot use password login.
            if (! $user || $user->password === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if ($user->blocked_at !== null) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Akun dinonaktifkan.',
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // 2FA challenge: per pending login (password or Google) and per IP (/64).
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('2fa|id:'.(string) $request->session()->get('login.id')),
            Limit::perMinute(20)->by('2fa|ip:'.$this->ipKey($request)),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(6)->by('register:'.$this->ipKey($request)));

        // Sensitive password routes: per IP (/64) and per user/email, separate bucket per route.
        RateLimiter::for('auth-sensitive', function (Request $request) {
            $route = (string) $request->route()?->getName();
            $limits = [Limit::perMinute(10)->by($route.'|ip:'.$this->ipKey($request))];
            $subject = $request->user()?->getKey() ?? Str::lower((string) $request->input('email'));

            if ($subject !== '') {
                $limits[] = Limit::perMinute(5)->by($route.'|u:'.$subject);
            }

            return $limits;
        });

        // Fortify has no limiter option for these routes; attach after routes are registered.
        $this->app->booted(function () {
            $throttles = [
                'register.store' => 'register',
                'password.confirm.store' => 'auth-sensitive',
                'user-password.update' => 'auth-sensitive',
                'password.email' => 'auth-sensitive',
                'password.update' => 'auth-sensitive',
            ];

            foreach (Route::getRoutes()->getRoutes() as $route) {
                if (isset($throttles[$route->getName()])) {
                    $route->middleware('throttle:'.$throttles[$route->getName()]);
                }

                // Pending login user may be blocked between the first factor and the challenge.
                if (in_array($route->getName(), ['two-factor.login', 'two-factor.login.store'], true)) {
                    $route->middleware(EnsureChallengedUserNotBlocked::class);
                }
            }
        });
    }

    private function ipKey(Request $request): string
    {
        return app(QuotaService::class)->normalizeIp((string) $request->ip());
    }
}
