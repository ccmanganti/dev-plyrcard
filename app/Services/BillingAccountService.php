<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
    public function credentials(?BillingInformation $billing = null): array
    {
        return [
            'location_id' => trim((string) (($billing?->ghl_location_id) ?: config('ghl.location_id'))),
            'token' => trim((string) config('ghl.token')),
        ];
    }

    /**
     * Create or update the payer/subscriber contact in PLYRCARD's billing
     * subaccount. User::ghl_subscriber_contact_id is the authoritative user-level
     * pointer. BillingInformation::ghl_contact_id is mirrored for billing records.
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
                    ->put($this->baseUrl() . '/contacts/' . rawurlencode($existingContactId), $payload);

                if ($response->successful()) {
                    $this->persistSubscriberContact($user, $billing, $existingContactId, $locationId);
                    $this->forgetSyncCache($user);
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
                $this->forgetSyncCache($user);
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
     * Cross-reference the manually stored subscriber contact against PLYRCARD's
     * billing subaccount. This hydrates billing contact details, checks the real
     * subscription status, refreshes reusable payment references and aligns the
     * displayed plan with the user's actual PLYRCARD role.
     */
    public function syncSubscriberAccount(User $user, ?BillingInformation $billing = null, bool $force = false): array
    {
        $user->loadMissing('roles');
        $subscriberContactId = trim((string) $user->ghl_subscriber_contact_id);

        if ($subscriberContactId === '') {
            return [
                'success' => false,
                'reason' => 'missing_subscriber_contact_id',
                'plan_key' => $this->rolePlanKey($user),
            ];
        }

        $billing ??= BillingInformation::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            $this->defaultBillingData($user),
        );

        $credentials = $this->credentials($billing);
        if ($credentials['location_id'] === '' || $credentials['token'] === '') {
            return ['success' => false, 'reason' => 'missing_billing_credentials'];
        }

        $cacheKey = $this->syncCacheKey($user, $subscriberContactId);
        if (! $force && Cache::has($cacheKey)) {
            return (array) Cache::get($cacheKey);
        }

        $transactionContactId = $subscriberContactId;

        $result = [
            'success' => true,
            'contact_id' => $subscriberContactId,
            'contact_found' => false,
            'subscription_found' => false,
            'subscription_active' => false,
            'subscription_status' => null,
            'subscription_id' => null,
            'plan_key' => $this->rolePlanKey($user),
        ];

        try {
            // Keep the user-level subscriber ID and the billing record in sync.
            $this->persistSubscriberContact($user, $billing, $subscriberContactId, $credentials['location_id']);

            $contactResult = $this->fetchContact($subscriberContactId, $credentials);
            if ($contactResult['success']) {
                $result['contact_found'] = true;
                $this->hydrateBillingFromContact($billing, $contactResult['contact']);
            } else {
                $result['success'] = false;
                $result['contact_error'] = $contactResult['reason'];
            }

            $subscriptionsResult = $this->listSubscriptions($subscriberContactId, $credentials);
            if ($subscriptionsResult['success']) {
                $subscription = $this->chooseSubscription($subscriptionsResult['subscriptions']);
                if ($subscription) {
                    $subscriptionState = $this->hydrateBillingFromSubscription($billing, $subscription, $credentials['location_id']);
                    $result = array_merge($result, $subscriptionState, [
                        'subscription_found' => true,
                    ]);
                    $transactionContactId = trim((string) ($subscriptionState['contact_id'] ?? '')) ?: $subscriberContactId;
                } else {
                    $billing->forceFill([
                        'subscription_status' => 'not_subscribed',
                        'payment_synced_at' => now(),
                    ])->save();
                }
            } else {
                $result['success'] = false;
                $result['subscription_error'] = $subscriptionsResult['reason'];
            }

            // Role is authoritative for the current PLYRCARD tier. Billing plan_key
            // is kept aligned so Locker Room/Admin never show a stale plan.
            $rolePlanKey = $this->rolePlanKey($user);
            if ($rolePlanKey !== null) {
                $billing->forceFill(['plan_key' => $rolePlanKey])->save();
                $result['plan_key'] = $rolePlanKey;
            }

            $this->refreshLatestTransactionMetadata($user, $billing, $transactionContactId, $credentials);
            $billing->refresh();

            $result['billing_id'] = $billing->getKey();
            $result['subscription_status'] = $billing->subscription_status;
            $result['payment_method_id'] = $billing->ghl_payment_method_id;
            $result['customer_id'] = $billing->ghl_customer_id;
            $result['card_last_four'] = $billing->card_last_four;

            Cache::put($cacheKey, $result, now()->addSeconds(60));
            return $result;
        } catch (\Throwable $exception) {
            Log::warning('Subscriber billing cross-reference failed.', [
                'user_id' => $user->getKey(),
                'contact_id' => $subscriberContactId,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'reason' => 'exception',
                'error' => $exception->getMessage(),
                'plan_key' => $this->rolePlanKey($user),
            ];
        }
    }

    /**
     * Hydrate payment identity from a known subscription ID. Kept for checkout
     * completion / payment-method return flows. If the stored subscription ID is
     * missing or stale, fall back to a contact-based cross-reference.
     */
    public function refreshPaymentIdentity(BillingInformation $billing): array
    {
        $billing->loadMissing('user.roles');
        $user = $billing->user;
        $subscriptionId = trim((string) $billing->ghl_subscription_id);
        $credentials = $this->credentials($billing);

        if ($credentials['location_id'] === '' || $credentials['token'] === '') {
            return ['success' => false, 'reason' => 'missing_credentials'];
        }

        if ($subscriptionId === '') {
            return $user
                ? $this->syncSubscriberAccount($user, $billing, true)
                : ['success' => false, 'reason' => 'missing_subscription'];
        }

        try {
            $response = Http::withHeaders(['Version' => 'v3'])
                ->withToken($credentials['token'])
                ->acceptJson()
                ->timeout((int) config('ghl.timeout', 20))
                ->get($this->baseUrl() . '/payments/subscriptions/' . rawurlencode($subscriptionId), [
                    'altId' => $credentials['location_id'],
                    'altType' => 'location',
                ]);

            $body = $response->json();
            $body = is_array($body) ? $body : [];

            if ($response->failed()) {
                return $user
                    ? $this->syncSubscriberAccount($user, $billing, true)
                    : ['success' => false, 'reason' => 'subscription_lookup_failed', 'status' => $response->status()];
            }

            $state = $this->hydrateBillingFromSubscription($billing, $body, $credentials['location_id']);

            if ($user) {
                $contactId = trim((string) ($state['contact_id'] ?? ''));
                if ($contactId !== '') {
                    $user->forceFill(['ghl_subscriber_contact_id' => $contactId])->save();
                }
                $rolePlanKey = $this->rolePlanKey($user);
                if ($rolePlanKey !== null) {
                    $billing->forceFill(['plan_key' => $rolePlanKey])->save();
                }
                $this->forgetSyncCache($user);
            }

            return array_merge(['success' => true], $state);
        } catch (\Throwable $exception) {
            Log::warning('Billing subscription identity refresh failed.', [
                'billing_id' => $billing->getKey(),
                'subscription_id' => $subscriptionId,
                'error' => $exception->getMessage(),
            ]);

            return $user
                ? $this->syncSubscriberAccount($user, $billing, true)
                : ['success' => false, 'reason' => 'exception'];
        }
    }

    public function rolePlanKey(User $user): ?string
    {
        $user->loadMissing('roles');
        $roles = $user->getRoleNames()->map(fn ($role) => strtolower(trim((string) $role)));

        if ($roles->contains('amplify')) {
            return 'amplify';
        }
        if ($roles->contains('my journey') || $roles->contains('my-journey') || $roles->contains('my_journey')) {
            return 'my-journey';
        }
        if ($roles->contains('free')) {
            return 'free';
        }

        return null;
    }

    protected function fetchContact(string $contactId, array $credentials): array
    {
        $response = Http::withHeaders(['Version' => '2021-07-28'])
            ->withToken($credentials['token'])
            ->acceptJson()
            ->timeout((int) config('ghl.timeout', 20))
            ->get($this->baseUrl() . '/contacts/' . rawurlencode($contactId));

        if ($response->failed()) {
            return ['success' => false, 'reason' => 'contact_lookup_failed', 'status' => $response->status()];
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $contact = data_get($body, 'contact', $body);

        return ['success' => is_array($contact), 'contact' => is_array($contact) ? $contact : []];
    }

    protected function listSubscriptions(string $contactId, array $credentials): array
    {
        $baseParams = [
            'altId' => $credentials['location_id'],
            'altType' => 'location',
            'limit' => 50,
            'offset' => 0,
        ];

        // First use the documented v3 contactId filter.
        $response = $this->paymentsGet('/payments/subscriptions', array_merge($baseParams, [
            'contactId' => $contactId,
        ]), $credentials);

        $rows = $response->successful()
            ? $this->paymentRows($response->json())
            : [];

        $rows = $this->filterSubscriptionsForContact($rows, $contactId);

        // Some older payment backends/tenants have accepted `contact` instead of
        // `contactId`. Do this fallback even when the first request returns 200 +
        // an empty data set, because a silently ignored filter otherwise looks
        // exactly like "not subscribed".
        if ($rows === []) {
            $legacy = $this->paymentsGet('/payments/subscriptions', array_merge($baseParams, [
                'contact' => $contactId,
            ]), $credentials);

            if ($legacy->successful()) {
                $rows = $this->filterSubscriptionsForContact(
                    $this->paymentRows($legacy->json()),
                    $contactId,
                );
            }
        }

        // Most reliable fallback for older/merged contacts: transactions are
        // filterable by contactId and expose subscriptionId. Follow those IDs
        // back to the authoritative subscription record instead of declaring the
        // account unsubscribed just because the list endpoint missed it.
        if ($rows === []) {
            $transactionRows = $this->listTransactionRows($contactId, $credentials, 50);
            $subscriptionIds = collect($transactionRows)
                ->filter(fn ($row) => is_array($row))
                ->flatMap(function (array $row) use ($contactId): array {
                    $rowContactId = trim((string) (data_get($row, 'contactId') ?? ''));
                    $mergedFrom = trim((string) (data_get($row, 'mergedFromContactId') ?? ''));
                    if ($rowContactId !== '' && $rowContactId !== $contactId && $mergedFrom !== $contactId) {
                        return [];
                    }

                    return array_values(array_filter([
                        trim((string) (data_get($row, 'subscriptionId') ?? '')),
                        trim((string) (data_get($row, 'entityId') ?? '')),
                    ]));
                })
                ->filter()
                ->unique()
                ->values();

            foreach ($subscriptionIds as $subscriptionId) {
                $detail = $this->getSubscriptionById((string) $subscriptionId, $credentials);
                if (! is_array($detail)) {
                    continue;
                }

                $detailContactId = trim((string) (data_get($detail, 'contactId') ?? ''));
                if ($detailContactId === '' || $detailContactId === $contactId) {
                    $rows[] = $detail;
                    continue;
                }

                // A merged transaction is still valid evidence that this user
                // belongs to the subscription even if HighLevel retained the old
                // contact ID on the subscription itself.
                $transactionMatches = collect($transactionRows)->contains(function ($row) use ($subscriptionId, $contactId): bool {
                    return is_array($row)
                        && trim((string) (data_get($row, 'subscriptionId') ?? '')) === (string) $subscriptionId
                        && in_array($contactId, array_filter([
                            trim((string) (data_get($row, 'contactId') ?? '')),
                            trim((string) (data_get($row, 'mergedFromContactId') ?? '')),
                        ]), true);
                });

                if ($transactionMatches) {
                    $rows[] = $detail;
                }
            }
        }

        if ($rows !== []) {
            return ['success' => true, 'subscriptions' => collect($rows)->unique(function ($row) {
                return (string) (data_get($row, '_id') ?? data_get($row, 'id') ?? data_get($row, 'subscriptionId') ?? md5(json_encode($row)));
            })->values()->all()];
        }

        if (isset($response) && $response->failed()) {
            return [
                'success' => false,
                'reason' => 'subscription_list_failed',
                'status' => $response->status(),
            ];
        }

        return ['success' => true, 'subscriptions' => []];
    }

    protected function paymentsGet(string $path, array $params, array $credentials)
    {
        return Http::withHeaders(['Version' => 'v3'])
            ->withToken($credentials['token'])
            ->acceptJson()
            ->timeout((int) config('ghl.timeout', 20))
            ->get($this->baseUrl() . $path, $params);
    }

    protected function paymentRows(mixed $body): array
    {
        $body = is_array($body) ? $body : [];
        $rows = data_get($body, 'data', data_get($body, 'subscriptions', []));

        // A few API wrappers return { data: { data: [...] } }.
        if (is_array($rows) && array_key_exists('data', $rows) && is_array($rows['data'])) {
            $rows = $rows['data'];
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    protected function filterSubscriptionsForContact(array $rows, string $contactId): array
    {
        return collect($rows)->filter(function ($row) use ($contactId): bool {
            if (! is_array($row)) {
                return false;
            }

            $rowContact = trim((string) (data_get($row, 'contactId') ?? data_get($row, 'contact.id') ?? ''));
            return $rowContact === '' || $rowContact === $contactId;
        })->values()->all();
    }

    protected function listTransactionRows(string $contactId, array $credentials, int $limit = 50): array
    {
        $response = $this->paymentsGet('/payments/transactions', [
            'altId' => $credentials['location_id'],
            'altType' => 'location',
            'locationId' => $credentials['location_id'],
            'contactId' => $contactId,
            'limit' => $limit,
            'offset' => 0,
        ], $credentials);

        if ($response->failed()) {
            return [];
        }

        return $this->paymentRows($response->json());
    }

    protected function getSubscriptionById(string $subscriptionId, array $credentials): ?array
    {
        if ($subscriptionId === '') {
            return null;
        }

        $response = $this->paymentsGet('/payments/subscriptions/' . rawurlencode($subscriptionId), [
            'altId' => $credentials['location_id'],
            'altType' => 'location',
        ], $credentials);

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();
        return is_array($body) ? $body : null;
    }

    protected function chooseSubscription(array $subscriptions): ?array
    {
        if ($subscriptions === []) {
            return null;
        }

        return collect($subscriptions)
            ->filter(fn ($row) => is_array($row))
            ->sortByDesc(function (array $row): string {
                $active = $this->isActiveSubscriptionStatus($this->subscriptionStatus($row)) ? '2' : '1';
                $updated = (string) (data_get($row, 'updatedAt') ?? data_get($row, 'createdAt') ?? '');
                return $active . '|' . $updated;
            })
            ->first();
    }

    protected function hydrateBillingFromContact(BillingInformation $billing, array $contact): void
    {
        $firstName = trim((string) (data_get($contact, 'firstName') ?? data_get($contact, 'first_name') ?? ''));
        $lastName = trim((string) (data_get($contact, 'lastName') ?? data_get($contact, 'last_name') ?? ''));
        $fullName = trim((string) (data_get($contact, 'name') ?? trim($firstName . ' ' . $lastName)));

        $updates = array_filter([
            'billing_name' => $fullName,
            'billing_email' => data_get($contact, 'email'),
            'billing_phone' => data_get($contact, 'phone'),
            'billing_company' => data_get($contact, 'companyName') ?? data_get($contact, 'company_name'),
            'billing_address_1' => data_get($contact, 'address1') ?? data_get($contact, 'address'),
            'billing_city' => data_get($contact, 'city'),
            'billing_state' => data_get($contact, 'state'),
            'billing_postal_code' => data_get($contact, 'postalCode') ?? data_get($contact, 'postal_code'),
            'billing_country' => data_get($contact, 'country'),
        ], fn ($value) => filled($value));

        if ($updates !== []) {
            $billing->forceFill($updates)->save();
        }
    }

    protected function hydrateBillingFromSubscription(BillingInformation $billing, array $subscription, string $locationId): array
    {
        $contactId = trim((string) (data_get($subscription, 'contactId') ?? data_get($subscription, 'contact.id') ?? ''));
        $subscriptionId = trim((string) (data_get($subscription, '_id') ?? data_get($subscription, 'id') ?? data_get($subscription, 'subscriptionId') ?? ''));
        $customerId = trim((string) (data_get($subscription, 'autoPayment.customerId')
            ?? data_get($subscription, 'autoPayment.customer.id')
            ?? data_get($subscription, 'customerId')
            ?? ''));
        $paymentMethodId = trim((string) (data_get($subscription, 'autoPayment.paymentMethodId')
            ?? data_get($subscription, 'autoPayment.paymentMethod.id')
            ?? data_get($subscription, 'paymentMethodId')
            ?? ''));
        $provider = data_get($subscription, 'paymentProvider.type') ?? data_get($subscription, 'paymentProviderType');
        $status = $this->subscriptionStatus($subscription);
        $liveMode = data_get($subscription, 'liveMode');
        $currency = strtoupper(trim((string) (data_get($subscription, 'currency') ?? '')));
        $amount = data_get($subscription, 'amount');

        $updates = [
            'ghl_location_id' => $locationId,
            'subscription_status' => $status ?: 'unknown',
            'payment_synced_at' => now(),
        ];

        if ($this->isActiveSubscriptionStatus($status)) {
            // An active recurring subscription is positive evidence that billing
            // has been established. Keep the high-level payment indicator from
            // remaining at the stale/null "Not Available" state.
            $updates['payment_status'] = 'paid';
        }

        if ($contactId !== '') {
            $updates['ghl_contact_id'] = $contactId;
        }
        if ($subscriptionId !== '') {
            $updates['ghl_subscription_id'] = $subscriptionId;
        }
        if ($customerId !== '') {
            $updates['ghl_customer_id'] = $customerId;
        }
        if ($paymentMethodId !== '') {
            $updates['ghl_payment_method_id'] = $paymentMethodId;
        }
        if (filled($provider)) {
            $updates['payment_provider'] = is_scalar($provider) ? (string) $provider : $billing->payment_provider;
        }
        if ($currency !== '') {
            $updates['currency'] = $currency;
        }
        if (is_numeric($amount)) {
            $numericAmount = (float) $amount;
            // HighLevel subscription amount can arrive as dollars in the API.
            // Only populate recurring amount when it was previously blank/zero.
            if ((int) $billing->recurring_amount_cents <= 0) {
                $updates['recurring_amount_cents'] = (int) round($numericAmount * 100);
            }
        }
        if (! is_null($liveMode)) {
            $updates['payment_live_mode'] = filter_var($liveMode, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $liveMode;
        }

        $billing->forceFill($updates)->save();

        return [
            'contact_id' => $contactId ?: null,
            'subscription_id' => $subscriptionId ?: null,
            'customer_id' => $customerId ?: null,
            'payment_method_id' => $paymentMethodId ?: null,
            'subscription_status' => $status ?: 'unknown',
            'subscription_active' => $this->isActiveSubscriptionStatus($status),
        ];
    }

    protected function refreshLatestTransactionMetadata(User $user, BillingInformation $billing, string $contactId, array $credentials): void
    {
        try {
            $rows = $this->listTransactionRows($contactId, $credentials, 25);
            if ($rows === []) {
                return;
            }

            $latest = collect($rows)
                ->filter(fn ($row) => is_array($row))
                ->sortByDesc(fn ($row) => (string) (data_get($row, 'updatedAt') ?? data_get($row, 'createdAt') ?? ''))
                ->first();

            if (! is_array($latest)) {
                return;
            }

            $paymentMethod = $this->normalizePaymentMethod(data_get($latest, 'paymentMethod'));
            $brand = data_get($paymentMethod, 'card.brand')
                ?? data_get($paymentMethod, 'brand')
                ?? data_get($latest, 'card.brand');
            $lastFour = data_get($paymentMethod, 'card.last4')
                ?? data_get($paymentMethod, 'last4')
                ?? data_get($latest, 'card.last4');
            $expMonth = data_get($paymentMethod, 'card.expMonth')
                ?? data_get($paymentMethod, 'expMonth')
                ?? data_get($latest, 'card.expMonth');
            $expYear = data_get($paymentMethod, 'card.expYear')
                ?? data_get($paymentMethod, 'expYear')
                ?? data_get($latest, 'card.expYear');
            $transactionId = trim((string) (data_get($latest, '_id') ?? data_get($latest, 'id') ?? ''));
            $status = trim((string) (data_get($latest, 'status') ?? ''));
            $amount = data_get($latest, 'amount');
            $paidAt = data_get($latest, 'createdAt') ?? data_get($latest, 'paidAt');

            $billingUpdates = array_filter([
                'payment_brand' => $brand,
                'card_last_four' => $lastFour,
                'card_expiration' => ($expMonth && $expYear) ? sprintf('%02d/%s', (int) $expMonth, substr((string) $expYear, -2)) : null,
                'ghl_transaction_id' => $transactionId,
            ], fn ($value) => filled($value));

            if ($billingUpdates !== []) {
                $billing->forceFill($billingUpdates)->save();
            }

            if (class_exists(PaymentTransaction::class) && $transactionId !== '') {
                PaymentTransaction::query()->updateOrCreate(
                    ['ghl_transaction_id' => $transactionId],
                    [
                        'user_id' => $user->getKey(),
                        'billing_information_id' => $billing->getKey(),
                        'plan_key' => $billing->plan_key,
                        'ghl_location_id' => $credentials['location_id'],
                        'ghl_contact_id' => $contactId,
                        'ghl_subscription_id' => $billing->ghl_subscription_id,
                        'status' => $status ?: null,
                        'currency' => strtoupper((string) (data_get($latest, 'currency') ?? $billing->currency ?? 'USD')),
                        'amount_cents' => is_numeric($amount) ? (int) round(((float) $amount) * 100) : 0,
                        'payment_provider' => data_get($latest, 'paymentProviderType') ?? $billing->payment_provider,
                        'payment_mode' => data_get($latest, 'paymentMode') ?? $billing->payment_mode,
                        'live_mode' => (bool) (data_get($latest, 'liveMode') ?? false),
                        'card_brand' => $brand,
                        'card_last_four' => $lastFour,
                        'paid_at' => $paidAt ? Carbon::parse($paidAt) : null,
                        'synced_at' => now(),
                        'ghl_payload' => $latest,
                    ],
                );
            }
        } catch (\Throwable $exception) {
            Log::debug('Latest billing transaction metadata refresh skipped.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function normalizePaymentMethod(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // The Payments API documentation also shows paymentMethod as a serialized
        // object string. Extract only safe display metadata from that form.
        $safe = [];
        if (preg_match('/["\']?brand["\']?\s*[:=]\s*["\']?([a-z0-9 _-]+)/i', $value, $match)) {
            $safe['brand'] = trim($match[1]);
        }
        if (preg_match('/["\']?last4["\']?\s*[:=]\s*["\']?(\d{4})/i', $value, $match)) {
            $safe['last4'] = $match[1];
        }
        if (preg_match('/["\']?expMonth["\']?\s*[:=]\s*["\']?(\d{1,2})/i', $value, $match)) {
            $safe['expMonth'] = (int) $match[1];
        }
        if (preg_match('/["\']?expYear["\']?\s*[:=]\s*["\']?(\d{2,4})/i', $value, $match)) {
            $safe['expYear'] = (int) $match[1];
        }

        return $safe;
    }

    protected function subscriptionStatus(array $subscription): string
    {
        $raw = data_get($subscription, 'status');
        if (is_string($raw) || is_numeric($raw)) {
            return strtolower(trim((string) $raw));
        }
        if (is_array($raw)) {
            foreach (['status', 'value', 'name', 'label'] as $key) {
                if (filled($raw[$key] ?? null)) {
                    return strtolower(trim((string) $raw[$key]));
                }
            }
        }

        if (filled(data_get($subscription, 'canceledAt'))) {
            return 'cancelled';
        }

        return 'unknown';
    }

    protected function isActiveSubscriptionStatus(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), [
            'active', 'trial', 'trialing', 'current',
        ], true);
    }

    protected function persistSubscriberContact(User $user, BillingInformation $billing, string $contactId, string $locationId): void
    {
        if ($user->ghl_subscriber_contact_id !== $contactId) {
            $user->forceFill(['ghl_subscriber_contact_id' => $contactId])->saveQuietly();
        }

        $billing->forceFill([
            'ghl_contact_id' => $contactId,
            'ghl_location_id' => $locationId,
        ])->save();
    }

    protected function defaultBillingData(User $user): array
    {
        return [
            'billing_name' => trim((string) ($user->first_name . ' ' . $user->last_name)),
            'billing_email' => $user->email,
            'billing_phone' => $user->phone,
            'billing_address_1' => $user->street,
            'billing_city' => $user->city,
            'billing_state' => $user->state,
            'billing_country' => $user->country ?: 'US',
            'currency' => 'USD',
            'ghl_location_id' => config('ghl.location_id'),
        ];
    }

    protected function syncCacheKey(User $user, string $contactId): string
    {
        return 'plyrcard:billing-sync:v10-49:' . $user->getKey() . ':' . sha1($contactId);
    }

    protected function forgetSyncCache(User $user): void
    {
        $contactId = trim((string) $user->ghl_subscriber_contact_id);
        if ($contactId !== '') {
            Cache::forget($this->syncCacheKey($user, $contactId));
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
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