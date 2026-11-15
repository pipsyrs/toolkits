<?php

namespace App\Support;

use Illuminate\Http\Request;

class IntendedUrl
{
    // Return to the originating page after confirmation unless an intended URL is already set.
    public static function rememberPrevious(Request $request): void
    {
        if ($request->session()->has('url.intended')) {
            return;
        }

        $previous = url()->previous();
        $path = (string) parse_url($previous, PHP_URL_PATH);

        if (parse_url($previous, PHP_URL_HOST) !== $request->getHost()
            || str_contains($path, '/auth/google')
            || rtrim($path, '/') === rtrim((string) parse_url(route('password.confirm'), PHP_URL_PATH), '/')) {
            return;
        }

        redirect()->setIntendedUrl($previous);
    }
}
