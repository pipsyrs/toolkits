<?php

namespace App\Providers;

use App\Enums\PermissionName;
use App\Http\Middleware\EnsureNotBlocked;
use App\Listeners\AuditTwoFactorChanges;
use App\Models\BlockedDomain;
use App\Models\User;
use App\Observers\BlockedDomainObserver;
use App\Services\Billing\PaymentChannelCatalog;
use App\Services\Settings\SettingRegistry;
use App\Services\Settings\SettingsRepository;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingRegistry::class);
        $this->app->singleton(SettingsRepository::class);
        $this->app->scoped(PaymentChannelCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Re-run route middleware on /livewire/update requests.
        Livewire::addPersistentMiddleware([
            PermissionMiddleware::class,
            EnsureNotBlocked::class,
            EnsureEmailIsVerified::class,
        ]);

        // Superadmin gets catalog permissions only (unknown abilities stay denied).
        Gate::before(fn (User $user, string $ability) => $user->isSuperadmin() && PermissionName::tryFrom($ability) ? true : null);

        BlockedDomain::observe(BlockedDomainObserver::class);
        Event::subscribe(AuditTwoFactorChanges::class);

        $this->app->make(SettingsRepository::class)->applyToConfig();
    }
}
