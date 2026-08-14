<?php

use App\Http\Controllers\GhlBillingWebhookController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.index')->name('marketing.home');
Route::view('/about', 'pages.about')->name('marketing.about');
Route::view('/pricing', 'pages.pricing')->name('marketing.pricing');
Route::view('/book-demo', 'pages.book-demo')->name('marketing.book-demo');

// Native PLYRCARD registration. Replaces the old iframe-based registration view.
Route::get('/registration', [RegistrationController::class, 'show'])
    ->name('marketing.registration');

Route::post('/registration', [RegistrationController::class, 'store'])
    ->name('marketing.registration.store');

Route::get('/registration/check-handle', [RegistrationController::class, 'checkHandle'])
    ->name('marketing.registration.check-handle');

Route::get('/registration/check-domain', [RegistrationController::class, 'checkDomain'])
    ->middleware('throttle:60,1')
    ->name('marketing.registration.check-domain');

Route::middleware('auth')->get('/registration/payment-status', [RegistrationController::class, 'paymentStatus'])
    ->name('marketing.registration.payment-status');

// HighLevel signs webhook bodies. CSRF is intentionally disabled ONLY for this endpoint;
// GhlBillingWebhookController rejects requests that fail GHL signature verification.
Route::post('/webhooks/ghl/billing', GhlBillingWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.ghl.billing');

Route::view('/podcast', 'pages.podcast')->name('marketing.podcast');