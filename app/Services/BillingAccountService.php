<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingAccountService
{
    public function __construct(
        protected GoHighLevelService $ghl,
    ) {
    }

    /**
     * PLYRCARD billing always belongs to the PLYRCARD billing subaccount.
     * Never use the athlete's own ghl_location_id / ghl_api_key here.
     */
    public function credentials(BillingInformation $billing): array
    {
        $locationId = trim((string) ($billing->ghl_location_id ?: config('ghl.location_id')));
        $token = trim((string) config('ghl.token'));

        return [
            'location_id' => $locationId,
            'token' => $token,
        ];
    }

    /**
     * Create or update the payer/subscriber contact in PLYRCARD's billing
     * subaccount. User::ghl_subscriber_contact_id is the authoritative user-level
     * pointer. BillingInformation::ghl_contact_id is mirrored for billing records
     * and existing payment services.
     */
    public function ensureBillingContact(User $user, BillingInformation $billing): ?string
    {
        $credentials = $this->credentials($billing);
        $locationId = $credentials['location_id'];
        $token = $credentials['token'];

        if ($locationId === '' || $token === '') {
            return null;
        }

        $payload = array_filter([
            'firstName' => $this->firstName($billing->billing_name),
            'lastName' => $this->lastName($billing->billing_name),
            'name' => trim((string) $billing->billing_name),
            'email' => $billing->billing_email ?: $user->email,
            'phone' => $billing->billing_phone ?: $user->phone,
            'address1' => trim((string) $billing->billing_address_1 . ' ' . (string) $billing->billing_address_2),
            'city' => $billing->billing_city,
            'state' => $billing->billing_state,
            'postalCode' => $billing->billing_postal_code,
            'country' => $billing->billing_country,
            'companyName' => $billing->billing_company,
        ], fn ($value) => filled($value));

        $existingContactId = trim((string) ($user->ghl_subscriber_contact_id ?: $billing->ghl_contact_id));

        try {
            if ($existingContactId !== '') {
                $response = Http::withHeaders(['Version' => '2021-07-28'])
                    ->withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout((int) config('ghl.timeout', 20))
                    ->put(rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/') . '/contacts/' . rawurlencode($existingContactId), $payload);

                if ($response->successful()) {
                    $this->persistSubscriberContact($user, $billing, $existingContactId, $locationId);
                    return $existingContactId;
                }

                Log::warning('Billing contact update failed; falling back to contact upsert.', [
                    'user_id' => $user->getKey(),
                    'billing_id' => $billing->getKey(),
                    'status' => $response->status(),
                ]);
            }

            $contactId = $this->ghl->upsertContact($payload, $locationId, $token);

            if ($contactId) {
                $this->persistSubscriberContact($user, $billing, $contactId, $locationId);
            }

            return $contactId;
        } catch (\Throwable $exception) {
            Log::warning('Billing contact synchronization failed.', [
                'user_id' => $user->getKey(),
                'billing_id' => $billing->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Hydrate the subscriber identity and reusable payment references from the
     * actual subscription. This is the authoritative post-payment association.
     */
    public function refreshPaymentIdentity(BillingInformation $billing): array
    {
        $subscriptionId = trim((string) $billing->ghl_subscription_id);
        $credentials = $this->credentials($billing);

        if ($subscriptionId === '' || $credentials['location_id'] === '' || $credentials['token'] === '') {
            return ['success' => false, 'reason' => 'missing_subscription_or_credentials'];
        }

        try {
            $response = Http::withHeaders(['Version' => 'v3'])
                ->withToken($credentials['token'])
                ->acceptJson()
                ->timeout((int) config('ghl.timeout', 20))
                ->get(
                    rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/')
                    . '/payments/subscriptions/' . rawurlencode($subscriptionId),
                    [
                        'altId' => $credentials['location_id'],
                        'altType' => 'location',
                    ],
                );

            $body = $response->json();
            $body = is_array($body) ? $body : [];

            if ($response->failed()) {
                return [
                    'success' => false,
                    'reason' => 'subscription_lookup_failed',
                    'status' => $response->status(),
                ];
            }

            $contactId = trim((string) (data_get($body, 'contactId') ?? data_get($body, 'contact.id') ?? ''));
            $customerId = trim((string) (data_get($body, 'autoPayment.customerId') ?? ''));
            $paymentMethodId = trim((string) (data_get($body, 'autoPayment.paymentMethodId') ?? ''));
            $provider = data_get($body, 'paymentProvider.type');
            $status = data_get($body, 'status');
            $liveMode = data_get($body, 'liveMode');

            $updates = [
                'ghl_location_id' => $credentials['location_id'],
                'payment_synced_at' => now(),
            ];

            if ($contactId !== '') {
                $updates['ghl_contact_id'] = $contactId;
            }
            if ($customerId !== '') {
                $updates['ghl_customer_id'] = $customerId;
            }
            if ($paymentMethodId !== '') {
                $updates['ghl_payment_method_id'] = $paymentMethodId;
            }
            if (filled($provider)) {
                $updates['payment_provider'] = $provider;
            }
            if (filled($status)) {
                $updates['subscription_status'] = is_string($status) ? strtolower($status) : $billing->subscription_status;
            }
            if (! is_null($liveMode)) {
                $updates['payment_live_mode'] = filter_var($liveMode, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $liveMode;
            }

            $billing->forceFill($updates)->save();

            if ($contactId !== '') {
                $user = $billing->user;
                if ($user) {
                    $user->forceFill(['ghl_subscriber_contact_id' => $contactId])->save();
                }
            }

            return [
                'success' => true,
                'contact_id' => $contactId ?: null,
                'customer_id' => $customerId ?: null,
                'payment_method_id' => $paymentMethodId ?: null,
                'subscription_id' => $subscriptionId,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Billing subscription identity refresh failed.', [
                'billing_id' => $billing->getKey(),
                'subscription_id' => $subscriptionId,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'reason' => 'exception'];
        }
    }

    protected function persistSubscriberContact(User $user, BillingInformation $billing, string $contactId, string $locationId): void
    {
        $user->forceFill([
            'ghl_subscriber_contact_id' => $contactId,
        ])->save();

        $billing->forceFill([
            'ghl_contact_id' => $contactId,
            'ghl_location_id' => $locationId,
        ])->save();
    }

    protected function firstName(?string $name): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        return $parts[0] ?? null;
    }

    protected function lastName(?string $name): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        if (count($parts) <= 1) {
            return null;
        }
        array_shift($parts);
        return implode(' ', $parts);
    }
}