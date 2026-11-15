<?php

namespace App\Support;

class PasswordConfirmation
{
    public static function recent(?int $seconds = null): bool
    {
        $seconds ??= (int) config('auth.password_timeout', 900);
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        return $confirmedAt > 0 && time() - $confirmedAt <= $seconds;
    }
}
