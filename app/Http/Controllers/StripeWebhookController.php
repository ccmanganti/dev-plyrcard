<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $stripe): JsonResponse
    {
        $secret = trim((string) config('services.stripe.webhook_secret'));
        if ($secret === '') {
            Log::error('Stripe webhook rejected because STRIPE_WEBHOOK_SECRET is not configured.');
            return response()->json(['message' => 'Webhook not configured.'], 503);
        }

        $payload = $request->getContent();
        $signatureHeader = (string) $request->header('Stripe-Signature', '');

        if (! $this->signatureIsValid($payload, $signatureHeader, $secret)) {
            return response()->json(['message' => 'Invalid Stripe signature.'], 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        try {
            $stripe->handleWebhook($event);
        } catch (\Throwable $exception) {
            Log::error('Stripe webhook processing failed.', [
                'stripe_event_id' => $event['id'] ?? null,
                'stripe_event_type' => $event['type'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    protected function signatureIsValid(string $payload, string $header, string $secret): bool
    {
        if ($payload === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && ctype_digit((string) $value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && is_string($value) && $value !== '') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || $signatures === []) {
            return false;
        }

        $tolerance = max(60, (int) config('services.stripe.webhook_tolerance', 300));
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
