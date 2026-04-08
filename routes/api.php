<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GhlWebhookController;

Route::post('/webhooks/ghl/subscription', [GhlWebhookController::class, 'handle']);