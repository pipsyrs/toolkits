<?php

use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

// No "web" group: no session, CSRF or cookies.
Route::get('/s/{code}', RedirectController::class)
    ->where('code', '[A-Za-z0-9-]{3,30}')
    ->name('links.redirect');
