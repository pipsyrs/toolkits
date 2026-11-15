<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuestId
{
    public const COOKIE = 'tk_gid';

    public const ATTRIBUTE = 'guest_id';

    public function handle(Request $request, Closure $next): Response
    {
        $guestId = $request->cookie(self::COOKIE);
        $isNew = ! is_string($guestId) || ! Str::isUuid($guestId);

        if ($isNew) {
            $guestId = (string) Str::uuid();
        }

        $request->attributes->set(self::ATTRIBUTE, $guestId);

        $response = $next($request);

        if ($isNew) {
            // Value is encrypted by EncryptCookies (web group).
            $response->headers->setCookie(new Cookie(
                self::COOKIE,
                $guestId,
                now()->addYear(),
                '/',
                config('session.domain'),
                (bool) config('session.secure'),
                true,
                false,
                Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }

    public static function idFrom(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE) ?? $request->cookie(self::COOKIE);

        return is_string($id) && Str::isUuid($id) ? $id : (string) Str::uuid();
    }
}
