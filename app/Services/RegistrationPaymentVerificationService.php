<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegistrationPaymentVerificationService
{
    /**
     * Verify a paid native-registration checkout against HighLevel's own
     * Payments records. This service never receives, stores, or proxies raw
     * card numbers/CVC values; those remain inside the HighLevel payment UI.
     */
    public function verify(User $user, BillingInformation $billing): array
    {
        if ($billing->payment_status === 'paid') {
            return [
                'verified' => true,
                'source' => 'local_paid_state',
                'payment_status' => 'paid',
            ];
        }

        if (! config('plyrcard-registration.ghl.payment_verification.enabled', true)) {
            return $this->pending('verification_disabled');
        }

        $contactId = trim((string) ($billing->ghl_contact_id ?: ($user->ghl_contact_id ?? '')));
        $locationId = trim((string) ($billing->ghl_location_id ?: config('ghl.location_id')));
        $token = trim((string) config('ghl.token'));

        if ($contactId === '' || $locationId === '' || $token === '') {
            return $this->pending('missing_ghl_payment_credentials');
        }

        $expectedCents = $this->expectedInitialAmountCents($billing);
        if ($expectedCents <= 0) {
            return $this->pending('missing_expected_amount');
        }

        $windowMinutes = max(5, (int) config('plyrcard-registration.ghl.payment_verification.window_minutes', 90));
        $createdAt = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $since = $createdAt->copy()->subMinutes(5);
        $notBefore = now()->subMinutes($windowMinutes);
        if ($since->lt($notBefore)) {
            $since = $notBefore;
        }
        $until = now()->addMinutes(2);

        $transactionResult = $this->fetchRows(
            endpoint: '/payments/transactions',
            locationId: $locationId,
            contactId: $contactId,
            token: $token,
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

        if ($match) {
            return $this->confirmPayment(
                user: $user,
                billing: $billing,
                source: 'ghl_transactions_api',
                rows: $match['rows'],
                expectedCents: $expectedCents,
                matchedCents: $match['matched_cents'],
            );
        }

        // Orders are a defensive second source. Survey/form payments can surface
        // as an order before/alongside the corresponding transaction record.
        $orderResult = $this->fetchRows(
            endpoint: '/payments/orders',
            locationId: $locationId,
            contactId: $contactId,
            token: $token,
            since: $since,
            until: $until,
            extra: ['paymentStatus' => 'paid'],
        );

        $orders = $this->successfulRows(
            $orderResult['rows'] ?? [],
            $contactId,
            $since,
            ['completed', 'paid', 'succeeded', 'success'],
            // The endpoint itself is filtered to paymentStatus=paid. Some order
            // payloads expose only status=completed rather than paymentStatus.
            true,
        );

        $match = $this->matchExpectedAmount($orders, $expectedCents);

        if ($match) {
            return $this->confirmPayment(
                user: $user,
                billing: $billing,
                source: 'ghl_orders_api',
                rows: $match['rows'],
                expectedCents: $expectedCents,
                matchedCents: $match['matched_cents'],
            );
        }

        return [
            'verified' => false,
            'source' => 'ghl_payments_api',
            'reason' => 'payment_not_found_yet',
            'payment_status' => $billing->payment_status,
            'expected_amount_cents' => $expectedCents,
            'transaction_api_status' => $transactionResult['status'] ?? null,
            'order_api_status' => $orderResult['status'] ?? null,
        ];
    }

    protected function expectedInitialAmountCents(BillingInformation $billing): int
    {
        $explicit = (int) ($billing->initial_amount_cents ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        $plan = config('plyrcard-registration.plans.' . $billing->plan_key, []);
        $recurring = (int) ($billing->recurring_amount_cents ?? ($plan['recurring_amount_cents'] ?? 0));
        $setup = (int) ($billing->setup_fee_cents ?? ($plan['setup_fee_cents'] ?? 0));
        $firstMonth = (bool) ($plan['charge_first_month_upfront'] ?? true);

        return $setup + ($firstMonth ? $recurring : 0);
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

        $lastStatus = null;
        $lastError = null;

        foreach ($versions as $version) {
            foreach ([$paramsWithWindow, $baseParams] as $index => $params) {
                try {
                    /** @var Response $response */
                    $response = Http::withHeaders(['Version' => $version])
                        ->withToken($token)
                        ->acceptJson()
                        ->timeout($timeout)
                        ->get($baseUrl . $endpoint, $params);

                    $lastStatus = $response->status();
                    $data = $response->json();

                    if ($response->successful() && is_array($data)) {
                        $rows = data_get($data, 'data', []);

                        return [
                            'success' => true,
                            'status' => $response->status(),
                            'version' => $version,
                            'rows' => is_array($rows) ? $rows : [],
                        ];
                    }

                    // If a server rejects startAt/endAt formatting, immediately
                    // retry the same API version without those server-side dates;
                    // we still enforce the time window locally below.
                    if ($index === 0 && in_array($response->status(), [400, 404, 422], true)) {
                        continue;
                    }

                    $lastError = is_array($data)
                        ? (data_get($data, 'message') ?? data_get($data, 'error'))
                        : $response->body();
                } catch (\Throwable $exception) {
                    $lastError = $exception->getMessage();
                }
            }
        }

        Log::warning('PLYRCARD could not query HighLevel registration payment records.', [
            'endpoint' => $endpoint,
            'location_id' => $locationId,
            'contact_id' => $contactId,
            'status' => $lastStatus,
            'error' => $lastError,
        ]);

        return [
            'success' => false,
            'status' => $lastStatus,
            'rows' => [],
            'error' => $lastError,
        ];
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
            ->map(function (array $row) use ($expectedCents): array {
                return [
                    'row' => $row,
                    'cents' => $this->rowAmountCents($row, $expectedCents),
                ];
            })
            ->filter(fn (array $item): bool => $item['cents'] > 0)
            ->values();

        // Prefer an exact single charge/order.
        $single = $normalized->first(fn (array $item): bool => abs($item['cents'] - $expectedCents) <= 2);
        if ($single) {
            return [
                'rows' => [$single['row']],
                'matched_cents' => $single['cents'],
            ];
        }

        // HighLevel can represent a checkout containing a one-time setup item
        // plus the first recurring item as separate successful transactions.
        // Accept their sum only when it exactly reaches today's configured due.
        $sum = (int) $normalized->sum('cents');
        if ($sum > 0 && abs($sum - $expectedCents) <= 2) {
            return [
                'rows' => $normalized->pluck('row')->all(),
                'matched_cents' => $sum,
            ];
        }

        return null;
    }

    protected function rowAmountCents(array $row, int $expectedCents): int
    {
        $raw = $row['amount']
            ?? $row['amountPaid']
            ?? $row['total']
            ?? $row['subtotal']
            ?? null;

        if (! is_numeric($raw)) {
            return 0;
        }

        $value = (float) $raw;
        $asDollars = (int) round($value * 100);
        $asCents = (int) round($value);

        // GHL's documented Payments examples express amount as currency units,
        // but this tolerant comparison also handles integrations that return cents.
        return abs($asDollars - $expectedCents) <= abs($asCents - $expectedCents)
            ? $asDollars
            : $asCents;
    }

    protected function confirmPayment(
        User $user,
        BillingInformation $billing,
        string $source,
        array $rows,
        int $expectedCents,
        int $matchedCents,
    ): array {
        $ids = collect($rows)
            ->map(fn (array $row): string => trim((string) ($row['_id'] ?? $row['id'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $subscriptionId = collect($rows)
            ->map(fn (array $row): string => trim((string) ($row['subscriptionId'] ?? $row['subscription_id'] ?? '')))
            ->first(fn (string $id): bool => $id !== '');

        $existingSync = is_array($billing->ghl_sync_response ?? null)
            ? $billing->ghl_sync_response
            : [];

        $safeRows = collect($rows)->map(fn (array $row): array => [
            'id' => $row['_id'] ?? $row['id'] ?? null,
            'status' => $row['paymentStatus'] ?? $row['transactionStatus'] ?? $row['status'] ?? null,
            'amount' => $row['amount'] ?? $row['amountPaid'] ?? $row['total'] ?? null,
            'currency' => $row['currency'] ?? null,
            'created_at' => $row['createdAt'] ?? $row['created_at'] ?? null,
            'entity_type' => $row['entityType'] ?? null,
            'entity_id' => $row['entityId'] ?? null,
            'source_type' => $row['entitySourceType'] ?? $row['sourceType'] ?? null,
            'source_sub_type' => $row['entitySourceSubType'] ?? $row['sourceSubType'] ?? null,
            'subscription_id' => $row['subscriptionId'] ?? null,
            'payment_provider' => $row['paymentProviderType'] ?? null,
        ])->values()->all();

        $billing->forceFill([
            'payment_status' => 'paid',
            'subscription_status' => 'active',
            'ghl_transaction_id' => $billing->ghl_transaction_id ?: ($ids[0] ?? null),
            'ghl_subscription_id' => $billing->ghl_subscription_id ?: ($subscriptionId ?: null),
            'ghl_payment_completed_at' => $billing->ghl_payment_completed_at ?: now(),
            'ghl_last_event_at' => now(),
            'ghl_sync_status' => 'payment_confirmed_via_payments_api',
            'ghl_sync_response' => array_merge($existingSync, [
                'registration_payment_verification' => [
                    'verified_at' => now()->toIso8601String(),
                    'source' => $source,
                    'expected_amount_cents' => $expectedCents,
                    'matched_amount_cents' => $matchedCents,
                    'record_ids' => $ids,
                    'records' => $safeRows,
                ],
            ]),
        ])->save();

        $role = config('plyrcard-registration.plans.' . $billing->plan_key . '.role_after_payment');
        if (filled($role) && method_exists($user, 'syncRoles')) {
            $user->syncRoles([(string) $role]);
        }

        // Keep GHL contact tags aligned with the entitlement, but never let a tag
        // sync failure undo a verified payment.
        try {
            app(GoHighLevelService::class)->upsertContact([
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
                    [
                        'key' => 'selected_plan',
                        'field_value' => config(
                            'plyrcard-registration.plans.' . $billing->plan_key . '.label',
                            ucfirst((string) $billing->plan_key),
                        ),
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Registration payment verified, but GHL paid-contact tags could not be refreshed.', [
                'user_id' => $user->getKey(),
                'billing_id' => $billing->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        Log::info('PLYRCARD registration payment verified through HighLevel Payments API.', [
            'user_id' => $user->getKey(),
            'billing_id' => $billing->getKey(),
            'plan_key' => $billing->plan_key,
            'source' => $source,
            'expected_amount_cents' => $expectedCents,
            'matched_amount_cents' => $matchedCents,
            'record_ids' => $ids,
        ]);

        return [
            'verified' => true,
            'source' => $source,
            'payment_status' => 'paid',
            'expected_amount_cents' => $expectedCents,
            'matched_amount_cents' => $matchedCents,
            'record_ids' => $ids,
        ];
    }

    protected function pending(string $reason): array
    {
        return [
            'verified' => false,
            'source' => 'local',
            'reason' => $reason,
        ];
    }
}
