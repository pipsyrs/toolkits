<?php

namespace App\Actions\Auth;

use App\Exceptions\GoogleAuthException;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class LoginWithGoogle
{
    // Resolve (find, link or create) the local user from Google data only.
    public function handle(SocialiteUser $google): User
    {
        $googleId = (string) $google->getId();
        $email = Str::lower(trim((string) $google->getEmail()));

        if ($googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GoogleAuthException('Data akun Google tidak lengkap.');
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user !== null) {
            $this->ensureNotBlocked($user);

            return $user;
        }

        return $this->linkOrCreate($google, $googleId, $email);
    }

    protected function linkOrCreate(SocialiteUser $google, string $googleId, string $email): User
    {
        if (! $this->emailVerified($google)) {
            throw new GoogleAuthException('Email akun Google belum terverifikasi.');
        }

        return DB::transaction(function () use ($google, $googleId, $email) {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null) {
                return $this->link($user, $google, $googleId);
            }

            $user = new User;
            $user->forceFill([
                'name' => $this->name($google, $email),
                'email' => $email,
                'password' => null,
                'google_id' => $googleId,
                'avatar_url' => $this->avatar($google),
                'email_verified_at' => now(),
            ])->save();

            event(new Registered($user));

            return $user;
        });
    }

    // All checks run before any write.
    protected function link(User $user, SocialiteUser $google, string $googleId): User
    {
        $this->ensureNotBlocked($user);

        // Any admin account: no automatic Google linking.
        if ($user->can('admin.access')) {
            throw new GoogleAuthException('Masuk dengan password lalu hubungkan Google dari profil.');
        }

        if ($user->google_id !== null && $user->google_id !== $googleId) {
            throw new GoogleAuthException('Email ini sudah tertaut ke akun Google lain.');
        }

        $attributes = ['google_id' => $googleId];

        if ($user->avatar_url === null) {
            $attributes['avatar_url'] = $this->avatar($google);
        }

        // Unverified account may be pre-registered by someone else: drop its password and sessions.
        if ($user->email_verified_at === null) {
            $attributes['email_verified_at'] = now();
            $attributes['password'] = null;
            $attributes['remember_token'] = null;
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }

        $user->forceFill($attributes)->save();

        return $user;
    }

    protected function ensureNotBlocked(User $user): void
    {
        if ($user->blocked_at !== null) {
            throw new GoogleAuthException('Akun dinonaktifkan.');
        }
    }

    protected function emailVerified(SocialiteUser $google): bool
    {
        $raw = method_exists($google, 'getRaw') ? $google->getRaw() : [];
        $value = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    protected function name(SocialiteUser $google, string $email): string
    {
        $name = trim(strip_tags((string) $google->getName()));

        return mb_substr($name !== '' ? $name : Str::before($email, '@'), 0, 255);
    }

    protected function avatar(SocialiteUser $google): ?string
    {
        $url = (string) $google->getAvatar();

        return strlen($url) <= 2048 && preg_match('#^https://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
