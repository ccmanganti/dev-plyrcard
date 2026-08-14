<?php

namespace App\Http\Controllers;

use App\Models\BillingInformation;
use App\Services\GhlBillingService;
use App\Services\GoHighLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GhlBillingWebhookController extends Controller
{
    private const GHL_ED25519_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAi2HR1srL4o18O8BRa7gVJY7G7bupbN3H9AwJrHCDiOg=
-----END PUBLIC KEY-----
PEM;

    private const GHL_LEGACY_RSA_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAokvo/r9tVgcfZ5DysOSC
Frm602qYV0MaAiNnX9O8KxMbiyRKWeL9JpCpVpt4XHIcBOK4u3cLSqJGOLaPuXw6
dO0t6Q/ZVdAV5Phz+ZtzPL16iCGeK9po6D6JHBpbi989mmzMryUnQJezlYJ3DVfB
csedpinheNnyYeFXolrJvcsjDtfAeRx5ByHQmTnSdFUzuAnC9/GepgLT9SM4nCpv
uxmZMxrJt5Rw+VUaQ9B8JSvbMPpez4peKaJPZHBbU3OdeCVx5klVXXZQGNHOs8gF
3kvoV5rTnXV0IknLBXlcKKAQLZcY/Q9rG6Ifi9c+5vqlvHPCUJFT5XUGG5RKgOKU
J062fRtN+rLYZUV+BjafxQauvC8wSWeYja63VSUruvmNj8xkx2zE/Juc+yjLjTXp
IocmaiFeAO6fUtNjDeFVkhf5LNb59vECyrHD2SQIrhgXpO4Q3dVNA5rw576PwTzN
h/AMfHKIjE4xQA1SZuYJmNnmVZLIZBlQAF9Ntd03rfadZ+yDiOXCCs9FkHibELhC
HULgCsnuDJHcrGNd5/Ddm5hxGQ0ASitgHeMZ0kcIOwKDOzOU53lDza6/Y09T7sYJ
PQe7z0cvj7aE4B+Ax1ZoZGPzpJlZtGXCsu9aTEGEnKzmsFqwcSsnw3JB31IGKAyk
T1hhTiaCeIY/OwwwNUY2yvcCAwEAAQ==
-----END PUBLIC KEY-----
PEM;

    public function __invoke(
        Request $request,
        GhlBillingService $billingService,
        GoHighLevelService $ghl,
    ): JsonResponse {
        $rawBody = $request->getContent();

        if (! $this->verifySignature($rawBody, $request)) {
            Log::warning('Rejected GHL billing webhook with invalid signature.', [
                'has_ghl_signature' => filled($request->header('X-GHL-Signature')),
                'has_legacy_signature' => filled($request->header('X-WH-Signature')),
            ]);

            return response()->json(['success' => false, 'message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        $invoice = $this->invoicePayload($payload);
        $eventType = strtolower(trim((string) ($payload['type'] ?? $payload['event'] ?? $payload['eventType'] ?? '')));
        $status = strtolower(trim((string) ($invoice['status'] ?? '')));

        if ($status !== 'paid' && ! str_contains($eventType, 'invoicepaid')) {
            return response()->json(['success' => true, 'ignored' => true]);
        }

        $invoiceId = trim((string) ($invoice['_id'] ?? $invoice['id'] ?? ''));
        $contactId = trim((string) data_get($invoice, 'contactDetails.id', ''));
        $amountPaid = $invoice['amountPaid'] ?? $invoice['total'] ?? null;
        $amountPaidCents = is_numeric($amountPaid) ? (int) round(((float) $amountPaid) * 100) : null;

        $billing = null;

        if ($invoiceId !== '') {
            $billing = BillingInformation::query()
                ->where('ghl_invoice_id', $invoiceId)
                ->first();
        }

        // Defensive fallback for webhook-envelope variations. Prefer exact invoice ID,
        // then GHL contact + pending plan + expected initial amount.
        if (! $billing && $contactId !== '') {
            $billing = BillingInformation::query()
                ->where('ghl_contact_id', $contactId)
                ->whereIn('payment_status', ['pending', 'invoice_created', 'invoice_sent', 'invoice_error'])
                ->when($amountPaidCents !== null, fn ($query) => $query->where('initial_amount_cents', $amountPaidCents))
                ->latest('id')
                ->first();
        }

        if (! $billing) {
            Log::info('GHL paid invoice webhook was valid but did not match a PLYRCARD billing record.', [
                'invoice_id' => $invoiceId,
                'contact_id' => $contactId,
                'amount_paid_cents' => $amountPaidCents,
            ]);

            return response()->json(['success' => true, 'matched' => false]);
        }

        $webhookId = trim((string) ($payload['webhookId'] ?? $payload['webhook_id'] ?? ''));
        $transactionId = $this->firstString($payload, [
            'transactionId', 'transaction_id', 'payment.transactionId', 'payment.transaction_id',
            'data.transactionId', 'data.transaction_id', 'invoice.transactionId',
        ]);
        $paymentMethodId = $this->firstString($payload, [
            'paymentMethodId', 'payment_method_id', 'payment.paymentMethodId',
            'data.paymentMethodId', 'invoice.paymentMethodId',
        ]);
        $customerId = $this->firstString($payload, [
            'customerId', 'customer_id', 'payment.customerId', 'data.customerId',
            'invoice.customerId',
        ]);
        $paymentBrand = $this->firstString($payload, [
            'card.brand', 'payment.card.brand', 'data.card.brand', 'invoice.card.brand',
        ]);
        $lastFour = $this->firstString($payload, [
            'card.last4', 'card.last_four', 'payment.card.last4', 'data.card.last4',
            'invoice.card.last4',
        ]);
        $user = $billing->user;

        if (! $user) {
            return response()->json(['success' => true, 'matched' => true, 'user_missing' => true]);
        }

        $existingSync = is_array($billing->ghl_sync_response) ? $billing->ghl_sync_response : [];

        $billing->forceFill([
            'payment_status' => 'paid',
            'subscription_status' => 'active',
            'ghl_invoice_id' => $billing->ghl_invoice_id ?: ($invoiceId ?: null),
            'ghl_last_webhook_id' => $webhookId ?: $billing->ghl_last_webhook_id,
            'ghl_transaction_id' => $transactionId ?: $billing->ghl_transaction_id,
            'ghl_payment_method_id' => $paymentMethodId ?: $billing->ghl_payment_method_id,
            'ghl_customer_id' => $customerId ?: $billing->ghl_customer_id,
            'payment_brand' => $paymentBrand ?: $billing->payment_brand,
            'card_last_four' => $lastFour ? substr($lastFour, -4) : $billing->card_last_four,
            'ghl_payment_completed_at' => $billing->ghl_payment_completed_at ?: now(),
            'ghl_last_event_at' => now(),
            'ghl_sync_status' => 'payment_confirmed',
            'ghl_sync_response' => array_merge($existingSync, [
                'last_invoice_paid_webhook' => $payload,
            ]),
        ])->save();

        $role = config('plyrcard-registration.plans.' . $billing->plan_key . '.role_after_payment');
        if (filled($role) && method_exists($user, 'syncRoles')) {
            $user->syncRoles([(string) $role]);
        }

        // Keep the GHL contact state aligned with the local entitlement.
        try {
            $ghl->upsertContact([
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'phone' => $user->phone,
                'tags' => [
                    'player-registration',
                    'registration-' . $billing->plan_key,
                    'payment-confirmed',
                    'plan-' . $billing->plan_key,
                ],
                'customFields' => [
                    ['key' => 'selected_plan', 'field_value' => $this->planLabel($billing->plan_key)],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Paid registration succeeded but GHL contact status refresh failed.', [
                'user_id' => $user->id,
                'billing_id' => $billing->id,
                'message' => $e->getMessage(),
            ]);
        }

        // Creating the recurring schedule is intentionally delayed until the first
        // invoice clears, so an abandoned signup never starts future billing.
        $scheduleResult = $billingService->createRecurringSchedule($user, $billing->fresh());

        if ($scheduleResult['success'] ?? false) {
            $billing->forceFill([
                'ghl_invoice_schedule_id' => $scheduleResult['schedule_id'] ?? $billing->ghl_invoice_schedule_id,
                'ghl_sync_status' => 'paid_recurring_schedule_active',
                'ghl_sync_response' => array_merge(
                    is_array($billing->ghl_sync_response) ? $billing->ghl_sync_response : [],
                    ['recurring_schedule' => $scheduleResult],
                ),
            ])->save();
        } else {
            // Do not revoke paid access: the first payment is valid. Flag billing for
            // repair so recurring invoicing can be retried without charging again.
            $billing->forceFill([
                'ghl_sync_status' => 'paid_recurring_schedule_error',
                'ghl_sync_response' => array_merge(
                    is_array($billing->ghl_sync_response) ? $billing->ghl_sync_response : [],
                    ['recurring_schedule' => $scheduleResult],
                ),
            ])->save();

            Log::error('Initial PLYRCARD invoice paid, but recurring GHL invoice schedule failed.', [
                'billing_id' => $billing->id,
                'user_id' => $user->id,
                'result' => $scheduleResult,
            ]);
        }

        return response()->json([
            'success' => true,
            'matched' => true,
            'billing_id' => $billing->id,
            'payment_status' => 'paid',
        ]);
    }

    protected function invoicePayload(array $payload): array
    {
        foreach ([$payload, $payload['data'] ?? null, $payload['invoice'] ?? null, data_get($payload, 'data.invoice')] as $candidate) {
            if (is_array($candidate) && (isset($candidate['_id']) || isset($candidate['status']) || isset($candidate['invoiceNumber']))) {
                return $candidate;
            }
        }

        return $payload;
    }

    protected function verifySignature(string $rawBody, Request $request): bool
    {
        $current = trim((string) $request->header('X-GHL-Signature'));
        if ($current !== '') {
            return $this->verifyEd25519($rawBody, $current);
        }

        $legacy = trim((string) $request->header('X-WH-Signature'));
        if ($legacy !== '') {
            return $this->verifyLegacyRsa($rawBody, $legacy);
        }

        return false;
    }

    protected function verifyEd25519(string $payload, string $signature): bool
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            Log::error('Cannot verify X-GHL-Signature because PHP sodium is unavailable.');
            return false;
        }

        $signatureBytes = base64_decode($signature, true);
        $publicKeyDer = $this->pemToDer(self::GHL_ED25519_PUBLIC_KEY);

        if ($signatureBytes === false || $publicKeyDer === null || strlen($publicKeyDer) < 32) {
            return false;
        }

        // Ed25519 SubjectPublicKeyInfo ends with the raw 32-byte public key.
        $rawPublicKey = substr($publicKeyDer, -32);

        try {
            return sodium_crypto_sign_verify_detached($signatureBytes, $payload, $rawPublicKey);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function verifyLegacyRsa(string $payload, string $signature): bool
    {
        $signatureBytes = base64_decode($signature, true);
        if ($signatureBytes === false) {
            return false;
        }

        $key = openssl_pkey_get_public(self::GHL_LEGACY_RSA_PUBLIC_KEY);
        if ($key === false) {
            return false;
        }

        return openssl_verify($payload, $signatureBytes, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    protected function pemToDer(string $pem): ?string
    {
        $base64 = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $pem);
        if (! is_string($base64) || $base64 === '') {
            return null;
        }

        $decoded = base64_decode($base64, true);

        return $decoded === false ? null : $decoded;
    }

    protected function firstString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function planLabel(?string $planKey): string
    {
        return match ($planKey) {
            'my-journey' => 'My Journey',
            'amplify' => 'Amplify',
            default => 'Free',
        };
    }
}
