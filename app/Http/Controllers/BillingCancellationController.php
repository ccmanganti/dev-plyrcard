<?php

namespace App\Http\Controllers;

use App\Services\BillingProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingCancellationController extends Controller
{
    public function __invoke(Request $request, BillingProfileService $billingService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $billing = $billingService->refreshPaymentIdentity($user);
        $status = strtolower(trim((string) $billing->subscription_status));

        if (! in_array($status, ['active', 'trialing', 'trial', 'past_due'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'There is no active subscription available to cancel.',
            ], 422);
        }

        // HighLevel's public subscription endpoints available to this app are read-only.
        // Record an explicit cancellation request rather than pretending the upstream
        // recurring subscription has already been terminated.
        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        $meta['cancellation_requested_at'] = now()->toIso8601String();
        $meta['cancellation_requested_by_user_id'] = $user->getKey();
        $meta['cancellation_subscription_id'] = $billing->ghl_subscription_id;
        $meta['cancellation_target_plan'] = 'free';
        $meta['cancellation_source'] = 'my_journey_page';

        $billing->forceFill([
            'registration_meta' => $meta,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Your plan cancellation request has been submitted. Your current access remains active until cancellation is confirmed.',
        ]);
    }
}