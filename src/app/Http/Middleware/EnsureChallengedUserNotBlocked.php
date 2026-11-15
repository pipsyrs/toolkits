<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChallengedUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('login.id');

        if ($id !== null && User::query()->whereKey($id)->whereNotNull('blocked_at')->exists()) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login')->withErrors(['email' => 'Akun dinonaktifkan.']);
        }

        return $next($request);
    }
}
