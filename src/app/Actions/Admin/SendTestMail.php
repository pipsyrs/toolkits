<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendTestMail
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor): void
    {
        Gate::forUser($actor)->authorize('settings.manage');

        $limiterKey = 'settings-test-mail:'.$actor->getKey();

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            throw ValidationException::withMessages([
                'testMail' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($limiterKey).' detik.',
            ]);
        }

        RateLimiter::hit($limiterKey, 60);

        try {
            Mail::raw('Email uji dari '.config('app.name').'. Konfigurasi email berfungsi.', function ($message) use ($actor) {
                $message->to($actor->email)->subject('Email uji '.config('app.name'));
            });
        } catch (Throwable $e) {
            // Transport errors may contain host details: log, show generic message.
            report($e);

            throw ValidationException::withMessages([
                'testMail' => 'Gagal mengirim email uji. Periksa konfigurasi SMTP.',
            ]);
        }
    }
}
