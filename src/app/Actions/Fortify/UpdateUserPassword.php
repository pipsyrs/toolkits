<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Auth\SessionRevoker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        // Google-only account sets its first password after a recent Google re-auth.
        if (! $user->hasPassword()) {
            $confirmedAt = (int) session('auth.password_confirmed_at', 0);

            if (time() - $confirmedAt > (int) config('auth.password_timeout', 900)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Konfirmasi ulang lewat Google sebelum membuat password.',
                ])->errorBag('updatePassword');
            }

            Validator::make($input, [
                'password' => $this->passwordRules(),
            ])->validateWithBag('updatePassword');
        } else {
            Validator::make($input, [
                'current_password' => ['required', 'string', 'current_password:web'],
                'password' => $this->passwordRules(),
            ], [
                'current_password.current_password' => __('The provided password does not match your current password.'),
            ])->validateWithBag('updatePassword');
        }

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // Sign out other devices, keep the current session.
        app(SessionRevoker::class)->revoke($user, session()->getId());
    }
}
