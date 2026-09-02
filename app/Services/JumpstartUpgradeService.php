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

class JumpstartUpgradeService
{
    public function __construct(
        protected BillingProfileService $billingProfiles,
        protected BillingAccountService $billingAccount,
        protected SupportAlertService $alerts,
    ) {}

    public function start(User $user): array
    {
        $user->loadMissing('roles');

        if ($this->hasJumpstart($user)) {
            return [
                'success' => true,
                'completed' => true,
                'plan_key' => $this->hasMyJourney($user) ? 'my-journey' : 'free',
                'jumpstart_active' => true,
                'message' => 'Jumpstart is already active on this account.',
            ];
        }

        $billing = $this->billingProfiles->get($user);

        if (! $this->billingProfiles->isComplete($billing)) {
            return array_merge([
                'success' => false,
                'completed' => false,
                'reason' => 'billing_profile_required',
                'message' => 'Complete your billing information to continue with checkout.',
            ], $this->billingProfiles->requirementPayload($user, $billing));
        }

        $contactId = trim((string) ($user->ghl_subscriber_contact_id ?: $billing->ghl_contact_id));
        if ($contactId === '') {
            $contactId = trim((string) ($this->billingAccount->ensureBillingContact($user, $billing) ?: ''));
            $billing->refresh();
            $user->refresh()->loadMissing('roles');
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
                'reason' => 'billing_credentials_unavailable',
                'message' => 'Checkout is temporarily unavailable. Please try again shortly.',
            ];
        }

        $previousPlanKey = $this->basePlanKey($user, $billing);
        $serviceCents = max(1, (int) config('plyrcard-registration.plans.jumpstart.setup_fee_cents', 14900));
        $journeyCents = max(1, (int) config('plyrcard-registration.plans.my-journey.recurring_amount_cents', 4900));
        $needsJourney = $previousPlanKey !== 'my-journey';
        $expectedCents = $serviceCents + ($needsJourney ? $journeyCents : 0);
        $checkoutId = (string) Str::uuid();

        Cache::put($this->cacheKey($user), [
            'checkout_id' => $checkoutId,
            'started_at' => now()->toIso8601String(),
            'expected_amount_cents' => $expectedCents,
            'service_amount_cents' => $serviceCents,
            'journey_amount_cents' => $journeyCents,
            'subscriber_contact_id' => $contactId,
            'billing_id' => $billing->getKey(),
            'previous_plan_key' => $previousPlanKey,
            'previous_subscription_id' => $billing->ghl_subscription_id,
            'previous_subscription_status' => $billing->subscription_status,
            'needs_my_journey' => $needsJourney,
            'status' => 'pending',
        ], now()->addMinutes(30));

        return [
            'success' => true,
            'completed' => false,
            'checkout_id' => $checkoutId,
            'checkout_url' => $this->checkoutUrl($user, $billing, $contactId, $checkoutId, $needsJourney),
            'expected_amount_cents' => $expectedCents,
            'display_due_today' => $this->money($expectedCents),
            'checkout_mode' => $needsJourney ? 'jumpstart_plus_my_journey' : 'jumpstart_service_only',
            'message' => $needsJourney
                ? 'Complete the ' . $this->money($expectedCents) . ' checkout: ' . $this->money($serviceCents) . ' Jumpstart plus your first ' . $this->money($journeyCents) . ' My Journey month.'
                : 'Complete the ' . $this->money($serviceCents) . ' Jumpstart service checkout. Your existing My Journey subscription stays active.',
        ];
    }

    public function status(User $user): array
    {
        $user->loadMissing('roles');
        if ($this->hasJumpstart($user)) {
            return [
                'success' => true,
                'completed' => true,
                'plan_key' => $this->hasMyJourney($user) ? 'my-journey' : 'free',
                'jumpstart_active' => true,
                'message' => 'Jumpstart is active.',
            ];
        }

        $checkout = Cache::get($this->cacheKey($user), []);
        $checkout = is_array($checkout) ? $checkout : [];
        if (empty($checkout['started_at']) || empty($checkout['subscriber_contact_id'])) {
            return ['success' => false, 'completed' => false, 'reason' => 'checkout_not_started', 'message' => 'Start the Jumpstart checkout first.'];
        }

        $billing = BillingInformation::query()->where('user_id', $user->getKey())->latest('id')->first();
        if (! $billing) {
            return ['success' => false, 'completed' => false, 'reason' => 'billing_not_found', 'message' => 'Billing information could not be loaded.'];
        }

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
        $expectedCents = max(1, (int) ($checkout['expected_amount_cents'] ?? 14900));
        $contactId = trim((string) $checkout['subscriber_contact_id']);
        $transactionResult = $this->fetchRows('/payments/transactions', (string) $credentials['location_id'], $contactId, (string) $credentials['token'], $since, $until);
        $rows = $this->successfulRows($transactionResult['rows'] ?? [], $contactId, $since, ['succeeded', 'success', 'successful', 'paid', 'completed', 'captured']);
        $match = $this->matchExpectedAmount($rows, $expectedCents);

        if (! $match) {
            $orderResult = $this->fetchRows('/payments/orders', (string) $credentials['location_id'], $contactId, (string) $credentials['token'], $since, $until, ['paymentStatus' => 'paid']);
            $orders = $this->successfulRows($orderResult['rows'] ?? [], $contactId, $since, ['completed', 'paid', 'succeeded', 'success'], true);
            $match = $this->matchExpectedAmount($orders, $expectedCents);
            if ($match) {
                return $this->complete($user, $billing, $checkout, $match, 'orders_api');
            }

            return ['success' => true, 'completed' => false, 'reason' => 'payment_not_found_yet', 'message' => 'Waiting for payment confirmation…'];
        }

        return $this->complete($user, $billing, $checkout, $match, 'transactions_api');
    }

    protected function complete(User $user, BillingInformation $billing, array $checkout, array $match, string $source): array
    {
        $rows = $match['rows'] ?? [];
        $ids = collect($rows)
            ->map(fn (array $row): string => trim((string) ($row['_id'] ?? $row['id'] ?? '')))
            ->filter()->unique()->values()->all();
        $subscriptionId = collect($rows)
            ->map(fn (array $row): string => trim((string) ($row['subscriptionId'] ?? $row['subscription_id'] ?? '')))
            ->first(fn (string $id): bool => $id !== '');

        $expectedCents = (int) ($checkout['expected_amount_cents'] ?? 14900);
        $serviceCents = (int) ($checkout['service_amount_cents'] ?? config('plyrcard-registration.plans.jumpstart.setup_fee_cents', 14900));
        $journeyCents = (int) ($checkout['journey_amount_cents'] ?? config('plyrcard-registration.plans.my-journey.recurring_amount_cents', 4900));
        $needsJourney = (bool) ($checkout['needs_my_journey'] ?? false);
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

        $resolvedSubscriptionId = $needsJourney
            ? ($subscriptionId ?: $billing->ghl_subscription_id)
            : ($billing->ghl_subscription_id ?: ($checkout['previous_subscription_id'] ?? null));

        $billing->forceFill([
            'plan_key' => 'my-journey',
            'billing_cycle' => 'monthly',
            'recurring_amount_cents' => $journeyCents,
            'setup_fee_cents' => $serviceCents,
            'initial_amount_cents' => $expectedCents,
            'payment_status' => 'paid',
            'subscription_status' => 'active',
            'ghl_subscription_id' => $resolvedSubscriptionId,
            'ghl_transaction_id' => $ids[0] ?? $billing->ghl_transaction_id,
            'ghl_payment_completed_at' => now(),
            'ghl_last_event_at' => now(),
            'ghl_sync_status' => 'jumpstart_payment_confirmed',
            'ghl_sync_response' => array_merge($existingSync, [
                'jumpstart_purchase' => [
                    'checkout_id' => $checkout['checkout_id'] ?? null,
                    'verified_at' => now()->toIso8601String(),
                    'source' => $source,
                    'checkout_mode' => $needsJourney ? 'jumpstart_plus_my_journey' : 'jumpstart_service_only',
                    'service_amount_cents' => $serviceCents,
                    'journey_amount_cents' => $needsJourney ? $journeyCents : 0,
                    'expected_amount_cents' => $expectedCents,
                    'matched_amount_cents' => (int) ($match['matched_cents'] ?? 0),
                    'record_ids' => $ids,
                    'records' => $safeRows,
                ],
            ]),
        ])->save();

        try {
            if (class_exists(RegistrationPaymentSyncService::class)) {
                app(RegistrationPaymentSyncService::class)->sync($user, $billing, $rows, 'jumpstart_' . $source);
                $billing->refresh();
                // Payment metadata sync must not turn the service extension into a
                // separate plan. My Journey remains the subscription layer.
                $billing->forceFill([
                    'plan_key' => 'my-journey',
                    'billing_cycle' => 'monthly',
                    'recurring_amount_cents' => $journeyCents,
                    'setup_fee_cents' => $serviceCents,
                    'initial_amount_cents' => $expectedCents,
                    'subscription_status' => 'active',
                    'ghl_subscription_id' => $resolvedSubscriptionId ?: $billing->ghl_subscription_id,
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Jumpstart payment verified but safe payment metadata sync was delayed.', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        if (method_exists($user, 'assignRole')) {
            if (! $user->hasRole('My Journey')) {
                $user->assignRole('My Journey');
            }
            if (! $user->hasRole('Jumpstart')) {
                $user->assignRole('Jumpstart');
            }
        } elseif (method_exists($user, 'syncRoles')) {
            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
            $roles[] = 'My Journey';
            $roles[] = 'Jumpstart';
            $user->syncRoles(array_values(array_unique($roles)));
        }

        $user->refresh();
        try {
            $this->alerts->sendUpgradeCompleted($user->fresh('roles'), 'jumpstart', $expectedCents, [
                'previous_plan' => (string) ($checkout['previous_plan_key'] ?? 'free'),
                'transaction_id' => $ids[0] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Jumpstart completed but admin alert could not be sent.', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
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
            'jumpstart_active' => true,
            'message' => 'Jumpstart purchase confirmed. My Journey is active and your Jumpstart service is ready.',
        ];
    }

    protected function checkoutUrl(User $user, BillingInformation $billing, string $contactId, string $checkoutId, bool $needsJourney): string
    {
        $base = trim((string) ($needsJourney
            ? config('plyrcard-registration.plans.jumpstart.payment_form_url', 'https://systems.plyrcard.com/widget/survey/KmE9cOWtXltjhFEPw27w?notrack=true')
            : config('plyrcard-registration.plans.jumpstart.my_journey_upgrade_form_url', 'https://systems.plyrcard.com/widget/survey/CXioZTT8ncW1xtwZuLVt?notrack=true')));

        $name = trim((string) ($billing->billing_name ?: ($user->first_name . ' ' . $user->last_name)));
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = array_shift($parts) ?: $user->first_name;
        $last = trim(implode(' ', $parts)) ?: $user->last_name;
        $params = array_filter([
            'notrack' => 'true',
            'contact_id' => $contactId,
            'contactId' => $contactId,
            'first_name' => $first,
            'firstName' => $first,
            'last_name' => $last,
            'lastName' => $last,
            'email' => $billing->billing_email ?: $user->email,
            'phone' => $billing->billing_phone ?: $user->phone,
            'user_id' => $user->getKey(),
            'checkout_id' => $checkoutId,
            'plan' => 'jumpstart',
            'service' => 'jumpstart',
            'checkout_mode' => $needsJourney ? 'jumpstart_plus_my_journey' : 'jumpstart_service_only',
            'existing_my_journey' => $needsJourney ? '0' : '1',
            'source' => 'plyrcard_jumpstart_purchase',
        ], fn ($v) => filled($v));

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
    }

    protected function money(int $cents): string
    {
        $amount = $cents / 100;
        return '$' . (floor($amount) === $amount ? number_format($amount, 0) : number_format($amount, 2));
    }

    protected function basePlanKey(User $user, BillingInformation $billing): string
    {
        $user->loadMissing('roles');
        if ($this->hasMyJourney($user)) {
            return 'my-journey';
        }

        return 'free';
    }

    protected function hasMyJourney(User $user): bool
    {
        $user->loadMissing('roles');
        try {
            return method_exists($user, 'hasRole') && $user->hasRole('My Journey');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function hasJumpstart(User $user): bool
    {
        $user->loadMissing('roles');
        try {
            if (method_exists($user, 'hasRole') && $user->hasRole('Jumpstart')) {
                return true;
            }
        } catch (\Throwable) {
        }

        return method_exists($user, 'getRoleNames')
            && $user->getRoleNames()->contains(fn ($r) => strcasecmp(trim((string) $r), 'Jumpstart') === 0);
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
                    Log::debug('Jumpstart payment lookup retry.', [
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
        return 'plyrcard:jumpstart-upgrade:' . $user->getKey();
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