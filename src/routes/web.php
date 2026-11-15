<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Files\DownloadFileOperationController;
use App\Http\Controllers\Files\PreviewFileOperationController;
use App\Http\Controllers\PricingController;
use App\Livewire\Client;
use App\Livewire\Tools;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/tools', 'pages.tools.index')->name('tools.index');

Route::prefix('tools')->name('tools.')->group(function () {
    Route::livewire('/shorten', Tools\Shorten::class)->name('shorten');
    Route::livewire('/qr-code', Tools\QrCode::class)->name('qr');
    Route::livewire('/image-convert', Tools\ImageConvert::class)->name('image-convert');
    Route::livewire('/image-compress', Tools\ImageCompress::class)->name('image-compress');
    Route::livewire('/image-resize', Tools\ImageResize::class)->name('image-resize');
    Route::livewire('/pdf-merge', Tools\PdfMerge::class)->name('pdf-merge');
    Route::livewire('/pdf-split', Tools\PdfSplit::class)->name('pdf-split');
    Route::livewire('/pdf-compress', Tools\PdfCompress::class)->name('pdf-compress');
    Route::livewire('/image-to-pdf', Tools\ImageToPdf::class)->name('image-to-pdf');
});

Route::get('/files/{operation}/download', DownloadFileOperationController::class)
    ->whereUuid('operation')->middleware('throttle:60,1')->name('files.download');
// Higher limit: one result page loads several previews.
Route::get('/files/{operation}/preview/{entry?}', PreviewFileOperationController::class)
    ->whereUuid('operation')->whereNumber('entry')->middleware('throttle:240,1')->name('files.preview');

Route::get('/pricing', PricingController::class)->name('pricing');
Route::view('/terms', 'pages.legal.terms')->name('legal.terms');
Route::view('/privacy', 'pages.legal.privacy')->name('legal.privacy');

Route::middleware('throttle:10,1')->prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('/', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('redirect');
    // Handles both login (guest) and re-auth (logged in) callbacks.
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('callback');
    Route::get('/confirm', [GoogleAuthController::class, 'confirm'])->middleware(['auth', 'not.blocked'])->name('confirm');
});

Route::middleware(['auth', 'verified', 'not.blocked'])->prefix('dashboard')->group(function () {
    Route::livewire('/', Client\Overview::class)->name('dashboard');
    Route::livewire('/links', Client\Links\Index::class)->name('dashboard.links.index');
    Route::livewire('/files', Client\Files\Index::class)->name('dashboard.files.index');
    Route::livewire('/billing', Client\Billing::class)->name('dashboard.billing');
    // Legacy invoice return page (display only); activation happens via webhook.
    Route::livewire('/billing/return/{payment}', Client\Billing::class)->whereUuid('payment')->name('dashboard.billing.return');
    Route::view('/profile', 'client.profile')->name('dashboard.profile');
});
