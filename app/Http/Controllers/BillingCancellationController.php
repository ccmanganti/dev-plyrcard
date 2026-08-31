<?php

namespace App\Http\Controllers;

use App\Models\BillingInformation;
use App\Services\BillingProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingCancellationController extends Controller
{
    public function __invoke(Request $request, BillingProfileService $billingService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $user->loadMissing('roles');

        $roleNames = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->map(fn ($role) => strtolower(trim((string) $role)))
            : collect();

        $hasMyJourney = $roleNames->contains('my journey')
            || $roleNames->contains('my-journey')
            || $roleNames->contains('my_journey');
        $hasAmplify = $roleNames->contains('amplify');
        $hasPaidRole = $hasMyJourney || $hasAmplify;

        // PLYRCARD roles are the authoritative plan/access state. Do not block a
        // downgrade request merely because the remote billing lookup is stale or
        // currently unable to identify the subscription.
        if (! $hasPaidRole) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is already on the Free plan.',
            ], 422);
        }

        $billing = BillingInformation::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'billing_name' => trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                'billing_email' => $user->email,
                'billing_phone' => $user->phone,
                'billing_address_1' => $user->street,
                'billing_city' => $user->city,
                'billing_state' => $user->state,
                'billing_country' => $user->country ?: 'US',
                'currency' => 'USD',
                'plan_key' => 'my-journey',
            ],
        );

        // Refresh remote payment identity when possible, but the result is supporting
        // billing metadata only. It is not the authority for whether this user is on
        // a paid PLYRCARD plan; the user's role is.
        try {
            $billing = $billingService->refreshPaymentIdentity($user);
        } catch (\Throwable $exception) {
            Log::warning('PLYRCARD downgrade request could not refresh remote billing identity.', [
                'user_id' => $user->getKey(),
                'billing_id' => $billing->getKey(),
                'error' => $exception->getMessage(),
            ]);
            $billing->refresh();
        }

        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        $meta['cancellation_requested_at'] = now()->toIso8601String();
        $meta['cancellation_requested_by_user_id'] = $user->getKey();
        $meta['cancellation_subscription_id'] = $billing->ghl_subscription_id;
        $meta['cancellation_target_plan'] = 'free';
        $meta['cancellation_source'] = 'my_journey_page';
        $meta['cancellation_source_roles'] = $roleNames->values()->all();
        $meta['cancellation_source_plan'] = $hasAmplify ? 'amplify' : 'my-journey';

        $billing->forceFill([
            'plan_key' => 'my-journey',
            'registration_meta' => $meta,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Your downgrade request has been submitted. Your current access stays active until the billing cancellation is confirmed, then your account can be moved to Free.',
            'current_role' => $hasAmplify ? 'Amplify' : 'My Journey',
            'target_role' => 'Free',
        ]);
    }
}