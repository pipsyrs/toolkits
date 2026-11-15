<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        if ($request->user()?->can('admin.access')) {
            $intended = $request->session()->pull('url.intended');

            return redirect()->to($this->isAdminUrl($intended) ? $intended : route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    // Only honour intended URLs under /admin for admin users.
    protected function isAdminUrl(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }

        $admin = url('/admin');

        return $url === $admin || str_starts_with($url, $admin.'/') || str_starts_with($url, $admin.'?');
    }
}
