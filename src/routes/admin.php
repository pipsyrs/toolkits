<?php

use App\Livewire\Admin;
use Illuminate\Support\Facades\Route;

// Loaded inside the "web" group (bootstrap/app.php).
Route::middleware(['auth', 'verified', 'not.blocked', 'permission:admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::livewire('/', Admin\Overview::class)->name('dashboard');
        Route::livewire('/users', Admin\Users\Index::class)->middleware('permission:users.view_any')->name('users.index');
        Route::livewire('/links', Admin\Links\Index::class)->middleware('permission:links.view_any')->name('links.index');
        Route::livewire('/plans', Admin\Plans\Limits::class)->middleware('permission:plans.view_any')->name('plans.edit');
        Route::livewire('/payments', Admin\Payments\Index::class)->middleware('permission:payments.view_any')->name('payments.index');
        Route::livewire('/payment-methods', Admin\PaymentMethods\Index::class)->middleware('permission:payment_methods.view_any')->name('payment-methods.index');
        Route::livewire('/payment-method-types', Admin\PaymentMethodTypes\Index::class)->middleware('permission:payment_method_types.view_any')->name('payment-method-types.index');
        Route::livewire('/payment-channels', Admin\PaymentChannels\Index::class)->middleware('permission:payment_channels.view_any')->name('payment-channels.index');
        Route::livewire('/tool-categories', Admin\ToolCategories\Index::class)->middleware('permission:tool_categories.view_any')->name('tool-categories.index');
        Route::livewire('/reports', Admin\Reports\Index::class)->middleware('permission:reports.view_any')->name('reports.index');
        Route::livewire('/tools', Admin\Tools\Index::class)->middleware('permission:tools.view_any')->name('tools.index');
        Route::livewire('/tools/{slug}', Admin\Tools\Show::class)->middleware('permission:tools.view')
            ->where('slug', '[a-z0-9-]+')->name('tools.show');
        Route::livewire('/audit-logs', Admin\AuditLogs\Index::class)->middleware('permission:audit.view_any')->name('audit-logs.index');
        Route::livewire('/settings', Admin\Settings\Edit::class)
            ->middleware(['permission:settings.view_any', 'password.confirm'])
            ->name('settings.edit');

        Route::middleware(['permission:roles.view_any', 'password.confirm'])->group(function () {
            Route::livewire('/roles', Admin\Roles\Index::class)->name('roles.index');
        });
    });
