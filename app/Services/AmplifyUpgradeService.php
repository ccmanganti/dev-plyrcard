<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AmplifyUpgradeService
{
    public function __construct(
        protected BillingProfileService $billingProfiles,
        protected BillingAccountService $billingAccount,
        protected SupportAlertService $alerts,
    ) {
    }

    public function start(User $user): array
    {
        $user->loadMissing('roles');

        if ($this->isAmplify($user)) {
            return [
                'success' => true,
                'completed' => true,
                'plan_key' => 'amplify',
                'message' => 'Amplify is already active on this account.',
            ];
        }

        $billing = $this->billingProfiles->get($user);

        if (! $this->billingProfiles->isComplete($billing)) {
            return array_merge([
                'success' => false,
                'completed' => false,
                'reason' => 'billing_profile_required',
                'message' => 'Complete your billing information to continue with secure checkout.',
            ], $this->billingProfiles->requirementPayload($user, $billing));
        }

        $contactId = trim((string) ($user->ghl_subscriber_contact_id ?: $billing->ghl_contact_id));

        if ($contactId === '') {
            $contactId = trim((string) ($this->billingAccount->ensureBillingContact($user, $billing) ?: ''));
            $billing->refresh();
            $user->refresh();
        }

        if ($contactId === '') {
            return array_merge([
                'success' => false,
                'completed' => false,
                'reason' => 'billing_contact_unavailable',
                'message' => 'Your billing information was saved, but the billing contact could not be connected yet. Please review it and try again.',
            ], $this->billingProfiles->requirementPayload($user, $billing));
        }

        $credentials = $this->billingAccount->credentials($billing);
        if (($credentials['location_id'] ?? '') === '' || ($credentials['token'] ?? '') === '') {
            return [
                'success' => false,
                'completed' => false,
                'message' => 'Secure checkout is temporarily unavailable. Please try again shortly.',
            ];
        }

        $plan = $this->planConfig();
        $currentPlanKey = $this->currentPlanKey($user, $billing);
        $expectedCents = $this->expectedAmountCents($plan, $currentPlanKey);
        $startedAt = now();
        $checkoutId = (string) Str::uuid();

        Cache::put($this->cacheKey($user), [
            'checkout_id' => $checkoutId,
            'started_at' => $startedAt->toIso8601String(),
            'expected_amount_cents' => $expectedCents,
            'subscriber_contact_id' => $contactId,
            'previous_plan_key' => $currentPlanKey,
            'previous_subscription_id' => $billing->ghl_subscription_id,
            'billing_id' => $billing->getKey(),
            'status' => 'pending',
        ], now()->addMinutes(30));

        return [
            'success' => true,
            'completed' => false,
            'checkout_id' => $checkoutId,
            'checkout_url' => $this->checkoutUrl($user, $billing, $contactId, $checkoutId, $currentPlanKey),
            'expected_amount_cents' => $expectedCents,
            'display_due_today' => '$' . number_format($expectedCents / 100, 2),
            'checkout_mode' => $currentPlanKey === 'my-journey' ? 'my_journey_upgrade' : 'new_amplify_enrollment',
            'message' => $currentPlanKey === 'my-journey'
                ? 'Complete the ' . $this->money($expectedCents) . ' Amplify upgrade below. Your existing My Journey monthly subscription stays in place.'
                : 'Complete Amplify enrollment below. ' . $this->money($expectedCents) . ' is due today based on the currently configured setup and first-month pricing.',
        ];
    }

    public function status(User $user): array
    {
        $user->loadMissing('roles');

        if ($this->isAmplify($user)) {
            return [
                'success' => true,
                'completed' => true,
                'plan_key' => 'amplify',
                'message' => 'Amplify is active.',
            ];
        }

        $checkout = Cache::get($this->cacheKey($user), []);
        $checkout = is_array($checkout) ? $checkout : [];

        if (empty($checkout['started_at']) || empty($checkout['subscriber_contact_id'])) {
            return [
                'success' => false,
                'completed' => false,
                'reason' => 'checkout_not_started',
                'message' => 'Start the Amplify checkout first.',
            ];
        }

        $billing = BillingInformation::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->first();

        if (! $billing) {
            return [
                'success' => false,
                'completed' => false,
                'reason' => 'billing_not_found',
                'message' => 'Billing information could not be loaded.',
            ];
        }

        $contactId = trim((string) $checkout['subscriber_contact_id']);
        $credentials = $this->billingAccount->credentials($billing);
        if (($credentials['location_id'] ?? '') === '' || ($credentials['token'] ?? '') === '') {
            return $this->pending('missing_payment_credentials');
        }

        try {
            $since = Carbon::parse((string) $checkout['started_at'])->subSeconds(10);
        } catch (\Throwable) {
            $since = now()->subMinutes(30);
        }

        $until = now()->addMinutes(2);
        $expectedCents = max(1, (int) ($checkout['expected_amount_cents'] ?? $this->expectedAmountCents($this->planConfig(), (string) ($checkout['previous_plan_key'] ?? 'free'))));

        $transactionResult = $this->fetchRows(
            endpoint: '/payments/transactions',
            locationId: (string) $credentials['location_id'],
            contactId: $contactId,
            token: (string) $credentials['token'],
            since: $since,
            until: $until,
        );

        $transactions = $this->successfulRows(
            $transactionResult['rows'] ?? [],
            $contactId,
            $since,
            ['succeeded', 'success', 'successful', 'paid', 'completed', 'captured'],
        );

        $match = $this->matchExpectedAmount($transactions, $expectedCents);

        if (! $match) {
            $orderResult = $this->fetchRows(
                endpoint: '/payments/orders',
                locationId: (string) $credentials['location_id'],
                contactId: $contactId,
                token: (string) $credentials['token'],
                since: $since,
                until: $until,
                extra: ['paymentStatus' => 'paid'],
            );

            $orders = $this->successfulRows(
                $orderResult['rows'] ?? [],
                $contactId,
                $since,
                ['completed', 'paid', 'succeeded', 'success'],
                true,
            );

            $match = $this->matchExpectedAmount($orders, $expectedCents);
            if ($match) {
                return $this->complete($user, $billing, $checkout, $match, 'orders_api');
            }

            return [
                'success' => true,
                'completed' => false,
                'reason' => 'payment_not_found_yet',
                'message' => 'Waiting for payment confirmation…',
            ];
        }

        return $this->complete($user, $billing, $checkout, $match, 'transactions_api');
    }

    protected function complete(User $user, BillingInformation $billing, array $checkout, array $match, string $source): array
    {
        $rows = $match['rows'] ?? [];
        $ids = collect($rows)
            ->map(fn (array $row): string => trim((string) ($row['_id'] ?? $row['id'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $subscriptionId = collect($rows)
            ->map(fn (array $row): string => trim((string) ($row['subscriptionId'] ?? $row['subscription_id'] ?? '')))
            ->first(fn (string $id): bool => $id !== '');

        $plan = $this->planConfig();
        $expectedCents = (int) ($checkout['expected_amount_cents'] ?? $this->expectedAmountCents($plan, (string) ($checkout['previous_plan_key'] ?? 'free')));
        $existingSync = is_array($billing->ghl_sync_response ?? null) ? $billing->ghl_sync_response : [];

        $safeRows = collect($rows)->map(fn (array $row): array => [
            'id' => $row['_id'] ?? $row['id'] ?? null,
            'status' => $row['paymentStatus'] ?? $row['transactionStatus'] ?? $row['status'] ?? null,
            'amount' => $row['amount'] ?? $row['amountPaid'] ?? $row['total'] ?? null,
            'currency' => $row['currency'] ?? null,
            'created_at' => $row['createdAt'] ?? $row['created_at'] ?? null,
            'subscription_id' => $row['subscriptionId'] ?? $row['subscription_id'] ?? null,
            'payment_provider' => $row['paymentProviderType'] ?? null,
        ])->values()->all();

        $fromMyJourney = ($checkout['previous_plan_key'] ?? 'free') === 'my-journey';
        $journeyPlan = (array) config('plyrcard-registration.plans.my-journey', []);
        $journeyRecurring = (int) ($journeyPlan['recurring_amount_cents'] ?? $plan['recurring_amount_cents'] ?? 4900);

        $billing->forceFill([
            'plan_key' => 'my-journey',
            'billing_cycle' => $journeyPlan['billing_cycle'] ?? $plan['billing_cycle'] ?? ($billing->billing_cycle ?: 'monthly'),
            'currency' => strtoupper((string) ($plan['currency'] ?? $billing->currency ?? 'USD')),
            'recurring_amount_cents' => $journeyRecurring,
            'setup_fee_cents' => (int) ($plan['setup_fee_cents'] ?? 50000),
            // A confirmed Amplify checkout is always paid. If the player came
            // from Free, this checkout also starts My Journey, so subscription
            // state becomes active. Existing My Journey state is preserved.
            'payment_status' => 'paid',
            'subscription_status' => $fromMyJourney ? ($billing->subscription_status ?: 'active') : 'active',
            'ghl_transaction_id' => $ids[0] ?? $billing->ghl_transaction_id,
            'ghl_subscription_id' => $fromMyJourney ? $billing->ghl_subscription_id : ($subscriptionId ?: $billing->ghl_subscription_id),
            'ghl_payment_completed_at' => now(),
            'ghl_last_event_at' => now(),
            'ghl_sync_status' => 'amplify_upgrade_payment_confirmed',
            'ghl_sync_response' => array_merge($existingSync, [
                'amplify_purchase' => [
                    'checkout_id' => $checkout['checkout_id'] ?? null,
                    'verified_at' => now()->toIso8601String(),
                    'source' => $source,
                    'previous_plan_key' => $checkout['previous_plan_key'] ?? null,
                    'previous_subscription_id' => $checkout['previous_subscription_id'] ?? null,
                    'expected_amount_cents' => $expectedCents,
                    'matched_amount_cents' => (int) ($match['matched_cents'] ?? 0),
                    'record_ids' => $ids,
                    'records' => $safeRows,
                ],
            ]),
        ])->save();

        // Reuse the same safe payment synchronization used by registration when
        // that service is available in the installed application.
        try {
            if (class_exists(RegistrationPaymentSyncService::class)) {
                app(RegistrationPaymentSyncService::class)->sync(
                    $user,
                    $billing,
                    $rows,
                    'amplify_upgrade_' . $source,
                );
                $billing->refresh();
            }
        } catch (\Throwable $exception) {
            Log::warning('Amplify payment was confirmed, but safe payment metadata sync was delayed.', [
                'user_id' => $user->getKey(),
                'billing_id' => $billing->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        // Amplify is a one-time entitlement layered on top of My Journey.
        // A Free -> Amplify enrollment therefore receives both My Journey and
        // Amplify, while an existing My Journey member simply gains Amplify.
        $requiredRoles = ['My Journey', 'Amplify'];
        if (method_exists($user, 'assignRole')) {
            foreach ($requiredRoles as $role) {
                if (! method_exists($user, 'hasRole') || ! $user->hasRole($role)) {
                    $user->assignRole($role);
                }
            }
            $user->refresh();
        } elseif (method_exists($user, 'syncRoles')) {
            $existingRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
            $user->syncRoles(array_values(array_unique(array_merge($existingRoles, $requiredRoles))));
            $user->refresh();
        }

        // Refresh subscription/customer/payment-method identity only after the
        // entitlement role has changed, because the billing service intentionally
        // treats Laravel roles as the source of truth for the displayed tier.
        try {
            $this->billingAccount->syncSubscriberAccount($user, $billing, true);
            $billing->refresh();
        } catch (\Throwable $exception) {
            Log::warning('Amplify upgrade completed but billing identity refresh was delayed.', [
                'user_id' => $user->getKey(),
                'billing_id' => $billing->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $this->alerts->sendUpgradeCompleted($user->fresh('roles'), 'amplify', $expectedCents, [
                'previous_plan' => (string) ($checkout['previous_plan_key'] ?? 'free'),
                'transaction_id' => $ids[0] ?? null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Amplify upgrade completed but the admin alert could not be sent.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        Cache::put($this->cacheKey($user), array_merge($checkout, [
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
            'record_ids' => $ids,
        ]), now()->addMinutes(10));

        return [
            'success' => true,
            'completed' => true,
            'plan_key' => 'my-journey',
            'payment_status' => 'paid',
            'subscription_status' => $billing->subscription_status ?: 'active',
            'message' => 'Purchase confirmed. PLYRCARD will verify your Amplify purchase and you will receive a message soon.',
        ];
    }

    protected function checkoutUrl(User $user, BillingInformation $billing, string $contactId, string $checkoutId, string $currentPlanKey): string
    {
        $isMyJourneyUpgrade = $currentPlanKey === 'my-journey';
        $base = $isMyJourneyUpgrade
            ? trim((string) config(
                'plyrcard-billing.amplify_my_journey_upgrade_url',
                'https://systems.plyrcard.com/widget/survey/xmVLm5DhFeIqSNCfUAO0'
            ))
            : trim((string) config(
                'plyrcard-billing.amplify_registration_url',
                'https://systems.plyrcard.com/widget/survey/FPx6oTagczUr0jH1X0ES'
            ));

        $name = trim((string) ($billing->billing_name ?: ($user->first_name . ' ' . $user->last_name)));
        $parts = preg_split('/\s+/', $name) ?: [];
        $firstName = array_shift($parts) ?: $user->first_name;
        $lastName = trim(implode(' ', $parts)) ?: $user->last_name;

        $params = array_filter([
            'contact_id' => $contactId,
            'contactId' => $contactId,
            'first_name' => $firstName,
            'firstName' => $firstName,
            'last_name' => $lastName,
            'lastName' => $lastName,
            'email' => $billing->billing_email ?: $user->email,
            'phone' => $billing->billing_phone ?: $user->phone,
            'user_id' => $user->getKey(),
            'checkout_id' => $checkoutId,
            'plan' => 'amplify',
            'source' => $isMyJourneyUpgrade ? 'plyrcard_my_journey_upgrade' : 'plyrcard_amplify_enrollment',
            'upgrade_from' => $currentPlanKey,
        ], fn ($value): bool => filled($value));

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
    }

    protected function planConfig(): array
    {
        $plan = config('plyrcard-registration.plans.amplify', []);
        return is_array($plan) ? $plan : [];
    }

    protected function money(int $cents): string
    {
        $amount = $cents / 100;
        return '$' . (floor($amount) === $amount ? number_format($amount, 0) : number_format($amount, 2));
    }

    protected function expectedAmountCents(array $plan, string $currentPlanKey = 'free'): int
    {
        $setup = (int) ($plan['setup_fee_cents'] ?? 50000);

        // A My Journey subscriber already has the $49/mo subscription. Their
        // dedicated Amplify upgrade survey charges only the $500 setup fee.
        if ($currentPlanKey === 'my-journey') {
            return $setup;
        }

        // New Amplify enrollment uses the registration survey: $500 setup plus
        // the first $49 monthly payment when first-month-upfront is enabled.
        $journey = (array) config('plyrcard-registration.plans.my-journey', []);
        $recurring = (int) ($journey['recurring_amount_cents'] ?? $plan['recurring_amount_cents'] ?? 4900);
        $firstMonth = (bool) ($plan['charge_first_month_upfront'] ?? true);
        return $setup + ($firstMonth ? $recurring : 0);
    }

    protected function currentPlanKey(User $user, BillingInformation $billing): string
    {
        $user->loadMissing('roles');
        if (method_exists($user, 'hasRole') && ($user->hasRole('My Journey') || $user->hasRole('my journey'))) {
            return 'my-journey';
        }

        return (string) ($this->billingAccount->rolePlanKey($user) ?: $billing->plan_key ?: 'free');
    }

    protected function isAmplify(User $user): bool
    {
        $user->loadMissing('roles');
        return method_exists($user, 'hasRole') && ($user->hasRole('Amplify') || $user->hasRole('amplify'));
    }

    protected function fetchRows(
        string $endpoint,
        string $locationId,
        string $contactId,
        string $token,
        Carbon $since,
        Carbon $until,
        array $extra = [],
    ): array {
        $baseUrl = rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
        $timeout = max(4, (int) config('plyrcard-registration.ghl.payment_verification.timeout', 8));
        $limit = min(100, max(10, (int) config('plyrcard-registration.ghl.payment_verification.limit', 50)));

        $baseParams = array_merge([
            'locationId' => $locationId,
            'altId' => $locationId,
            'altType' => 'location',
            'contactId' => $contactId,
            'limit' => $limit,
            'offset' => 0,
        ], $extra);

        $paramsWithWindow = array_merge($baseParams, [
            'startAt' => $since->toIso8601String(),
            'endAt' => $until->toIso8601String(),
        ]);

        $versions = array_values(array_unique(array_filter([
            trim((string) config('plyrcard-registration.ghl.payment_verification.api_version', 'v3')),
            'v3',
            '2021-04-15',
        ])));

        foreach ($versions as $version) {
            foreach ([$paramsWithWindow, $baseParams] as $index => $params) {
                try {
                    /** @var Response $response */
                    $response = Http::withHeaders(['Version' => $version])
                        ->withToken($token)
                        ->acceptJson()
                        ->timeout($timeout)
                        ->get($baseUrl . $endpoint, $params);

                    $data = $response->json();
                    if ($response->successful() && is_array($data)) {
                        $rows = data_get($data, 'data', []);
                        return [
                            'success' => true,
                            'status' => $response->status(),
                            'rows' => is_array($rows) ? $rows : [],
                        ];
                    }

                    if ($index === 0 && in_array($response->status(), [400, 404, 422], true)) {
                        continue;
                    }
                } catch (\Throwable $exception) {
                    Log::debug('Amplify payment lookup retry.', [
                        'user_contact_id' => $contactId,
                        'endpoint' => $endpoint,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return ['success' => false, 'rows' => []];
    }

    protected function successfulRows(
        array $rows,
        string $contactId,
        Carbon $since,
        array $successStatuses,
        bool $trustPaidEndpointFilter = false,
    ): array {
        $successStatuses = array_map('strtolower', $successStatuses);

        return collect($rows)
            ->filter(fn ($row): bool => is_array($row))
            ->filter(function (array $row) use ($contactId): bool {
                $rowContact = trim((string) ($row['contactId'] ?? $row['contact_id'] ?? ''));
                return $rowContact === '' || hash_equals($contactId, $rowContact);
            })
            ->filter(function (array $row) use ($successStatuses, $trustPaidEndpointFilter): bool {
                $status = strtolower(trim((string) (
                    $row['paymentStatus']
                    ?? $row['transactionStatus']
                    ?? $row['status']
                    ?? ''
                )));

                return $trustPaidEndpointFilter
                    ? ($status === '' || in_array($status, $successStatuses, true))
                    : in_array($status, $successStatuses, true);
            })
            ->filter(function (array $row) use ($since): bool {
                $created = $row['createdAt'] ?? $row['created_at'] ?? $row['updatedAt'] ?? null;
                if (! $created) {
                    return false;
                }

                try {
                    return Carbon::parse($created)->gte($since);
                } catch (\Throwable) {
                    return false;
                }
            })
            ->unique(fn (array $row): string => (string) ($row['_id'] ?? $row['id'] ?? sha1(json_encode($row))))
            ->values()
            ->all();
    }

    protected function matchExpectedAmount(array $rows, int $expectedCents): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $normalized = collect($rows)
            ->map(fn (array $row): array => [
                'row' => $row,
                'cents' => $this->rowAmountCents($row, $expectedCents),
            ])
            ->filter(fn (array $item): bool => $item['cents'] > 0)
            ->values();

        $single = $normalized->first(fn (array $item): bool => abs($item['cents'] - $expectedCents) <= 2);
        if ($single) {
            return ['rows' => [$single['row']], 'matched_cents' => $single['cents']];
        }

        $sum = (int) $normalized->sum('cents');
        if ($sum > 0 && abs($sum - $expectedCents) <= 2) {
            return ['rows' => $normalized->pluck('row')->all(), 'matched_cents' => $sum];
        }

        return null;
    }

    protected function rowAmountCents(array $row, int $expectedCents): int
    {
        $raw = $row['amount'] ?? $row['amountPaid'] ?? $row['total'] ?? $row['subtotal'] ?? null;
        if (! is_numeric($raw)) {
            return 0;
        }

        $value = (float) $raw;
        $asDollars = (int) round($value * 100);
        $asCents = (int) round($value);

        return abs($asDollars - $expectedCents) <= abs($asCents - $expectedCents)
            ? $asDollars
            : $asCents;
    }

    protected function cacheKey(User $user): string
    {
        return 'plyrcard:amplify-upgrade:' . $user->getKey();
    }

    protected function pending(string $reason): array
    {
        return [
            'success' => true,
            'completed' => false,
            'reason' => $reason,
            'message' => 'Waiting for payment confirmation…',
        ];
    }
}