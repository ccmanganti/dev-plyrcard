<?php

use App\Http\Controllers\GhlWebhookController;

Route::post('/webhooks/ghl/subscription', [GhlWebhookController::class, 'handle']);