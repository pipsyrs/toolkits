<?php

use App\Http\Middleware\EnsureGuestId;
use App\Http\Middleware\EnsureNotBlocked;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
            // Lean: no session/CSRF/cookies.
            Route::group([], base_path('routes/short.php'));
            Route::group([], base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'not.blocked' => EnsureNotBlocked::class,
        ]);

        // Only a proxy on this machine (ngrok agent, local nginx) may set X-Forwarded-*; remote clients cannot spoof IP or https.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->web(append: [
            EnsureGuestId::class,
            EnsureNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
