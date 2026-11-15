<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 09:00 WIB.
Schedule::command('billing:send-renewal-reminders')->dailyAt('02:00')->withoutOverlapping();

// Uploads and results past their plan retention.
Schedule::command('files:prune')->everyFifteenMinutes()->withoutOverlapping();
