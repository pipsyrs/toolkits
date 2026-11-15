<?php

use App\Livewire\Admin;
use Illuminate\Support\Facades\Route;

// Loaded inside the "web" group (bootstrap/app.php).
Route::middleware(['auth', 'verified', 'not.blocked', 'permission:admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::livewire('/', Admin\Overview::class)->name('dashboard');
        Route::livewire('/users', Admin\Users\Index::class)->middleware('permission:users.view')->name('users.index');
        Route::livewire('/links', Admin\Links\Index::class)->middleware('permission:links.view')->name('links.index');
        Route::livewire('/plans', Admin\Plans\Limits::class)->middleware('permission:plans.manage')->name('plans.edit');
        Route::livewire('/payments', Admin\Payments\Index::class)->middleware('permission:payments.view')->name('payments.index');
        Route::livewire('/payment-methods', Admin\PaymentMethods\Index::class)->middleware('permission:payment_methods.manage')->name('payment-methods.index');
        Route::livewire('/payment-method-types', Admin\PaymentMethodTypes\Index::class)->middleware('permission:payment_methods.manage')->name('payment-method-types.index');
        Route::livewire('/payment-channels', Admin\PaymentChannels\Index::class)->middleware('permission:payment_methods.manage')->name('payment-channels.index');
        Route::livewire('/tool-categories', Admin\ToolCategories\Index::class)->middleware('permission:tools.manage')->name('tool-categories.index');
        Route::livewire('/reports', Admin\Reports\Index::class)->middleware('permission:reports.view')->name('reports.index');
        Route::livewire('/tools', Admin\Tools\Index::class)->middleware('permission:tools.manage')->name('tools.index');
        Route::livewire('/tools/{slug}', Admin\Tools\Show::class)->middleware('permission:tools.manage')
            ->where('slug', '[a-z0-9-]+')->name('tools.show');
        Route::livewire('/audit-logs', Admin\AuditLogs\Index::class)->middleware('permission:audit.view')->name('audit-logs.index');
        Route::livewire('/settings', Admin\Settings\Edit::class)
            ->middleware(['permission:settings.manage', 'password.confirm'])
            ->name('settings.edit');

        Route::middleware(['permission:roles.manage', 'password.confirm'])->group(function () {
            Route::livewire('/roles', Admin\Roles\Index::class)->name('roles.index');
        });
    });
