<?php

use App\Http\Controllers\GhlWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/ghl/subscription', [GhlWebhookController::class, 'handle']);