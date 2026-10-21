<?php

namespace App\Http\Controllers;

use App\Services\PlyrcardSystemEmailService;
use App\Services\StripeBillingService;
use App\Services\SupportAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingCancellationController extends Controller
{
    public function __invoke(
        Request $request,
        StripeBillingService $stripe,
        SupportAlertService $alerts,
        PlyrcardSystemEmailService $systemEmail,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user, 403);

        try {
            $result = $stripe->cancelSubscription($user);
            $billing = $result['billing'] ?? null;
            $meta = is_array($billing?->registration_meta) ? $billing->registration_meta : [];
            $alreadyNotified = filled(data_get($meta, 'notifications.downgrade_requested_at'));

            if (! $alreadyNotified) {
                try {
                    $alerts->sendDowngradeRequest($user, 'My Journey', [
                        'subscription_id' => $billing?->stripe_subscription_id,
                        'payment_provider' => 'stripe',
                        'cancel_at_period_end' => true,
                    ]);
                } catch (\Throwable $exception) {
                    Log::warning('PLYRCARD downgrade admin notification failed.', ['user_id' => $user->getKey(), 'error' => $exception->getMessage()]);
                }

                try {
                    $systemEmail->sendDowngradeRequested($user);
                } catch (\Throwable $exception) {
                    Log::warning('PLYRCARD downgrade player notification failed.', ['user_id' => $user->getKey(), 'error' => $exception->getMessage()]);
                }

                if ($billing) {
                    data_set($meta, 'notifications.downgrade_requested_at', now()->toIso8601String());
                    $billing->forceFill(['registration_meta' => $meta])->save();
                }
            }

            return response()->json($result);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}