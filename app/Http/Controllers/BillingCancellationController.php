<?php
namespace App\Http\Controllers;
use App\Services\StripeBillingService;
use App\Services\SupportAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class BillingCancellationController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $stripe, SupportAlertService $alerts): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        try {
            $result = $stripe->cancelSubscription($user);
            try {
                $billing = $result['billing'] ?? null;
                $alerts->sendDowngradeRequest($user, 'My Journey', [
                    'subscription_id' => $billing?->stripe_subscription_id,
                    'payment_provider' => 'stripe',
                    'cancel_at_period_end' => true,
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
            return response()->json($result);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}