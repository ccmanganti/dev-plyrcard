<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RegistrationPaymentSyncService
{
    /**
     * Persist safe HighLevel payment metadata after registration payment has
     * already been verified. Raw card numbers/CVC are never requested or saved.
     */
    public function sync(
        User $user,
        BillingInformation $billing,
        array $matchedRows,
        string $verificationSource,
    ): array {
        $contactId = trim((string) ($billing->ghl_contact_id ?: ($user->ghl_contact_id ?? '')));
        $locationId = trim((string) ($billing->ghl_location_id ?: config('ghl.location_id')));
        $token = trim((string) config('ghl.token'));

        $matchedRows = collect($matchedRows)
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();

        $orders = [];
        $transactions = [];

        if ($verificationSource === 'ghl_orders_api') {
            $orders = $matchedRows;
        } else {
            $transactions = $matchedRows;
        }

        // Resolve the related order IDs from either an order match or the
        // transaction entity. HighLevel documents entityId as the order ID for
        // order-backed transactions.
        $orderIds = collect($orders)
            ->map(fn (array $row): string => $this->rowId($row))
            ->merge(collect($transactions)->map(function (array $row): string {
                $entityType = strtolower(trim((string) ($row['entityType'] ?? $row['entity_type'] ?? '')));
                return $entityType === 'order'
                    ? trim((string) ($row['entityId'] ?? $row['entity_id'] ?? ''))
                    : '';
            }))
            ->filter()
            ->unique()
            ->values();

        // If verification succeeded from an order before a transaction became
        // visible, query transactions again by contact/order so the durable local
        // transaction table is populated as soon as HighLevel exposes it.
        if ($transactions === [] && $contactId !== '' && $locationId !== '' && $token !== '') {
            $transactionRows = $this->fetchPaymentRows(
                '/payments/transactions',
                $locationId,
                $token,
                ['contactId' => $contactId],
            );

            $transactions = collect($transactionRows)
                ->filter(fn ($row): bool => is_array($row))
                ->filter(function (array $row) use ($orderIds): bool {
                    if ($orderIds->isEmpty()) {
                        return true;
                    }

                    return $orderIds->contains(trim((string) ($row['entityId'] ?? $row['entity_id'] ?? '')));
                })
                ->values()
                ->all();
        }

        // Fetch authoritative order payloads when transaction verification gave
        // us only the entity ID. Failure here must never reverse a paid checkout.
        if ($orders === [] && $orderIds->isNotEmpty() && $locationId !== '' && $token !== '') {
            foreach ($orderIds as $orderId) {
                $row = $this->fetchPaymentRecord('/payments/orders/' . rawurlencode($orderId), $locationId, $token);
                if (is_array($row) && $row !== []) {
                    $orders[] = $row;
                }
            }
        }

        $subscriptionIds = collect($transactions)
            ->map(fn (array $row): string => trim((string) ($row['subscriptionId'] ?? $row['subscription_id'] ?? '')))
            ->merge(collect($orders)->map(fn (array $row): string => trim((string) ($row['subscriptionId'] ?? $row['subscription_id'] ?? ''))))
            ->filter()
            ->unique()
            ->values();

        $subscriptions = [];
        if ($subscriptionIds->isNotEmpty() && $locationId !== '' && $token !== '') {
            foreach ($subscriptionIds as $subscriptionId) {
                $row = $this->fetchPaymentRecord('/payments/subscriptions/' . rawurlencode($subscriptionId), $locationId, $token);
                if (is_array($row) && $row !== []) {
                    $subscriptions[] = $row;
                }
            }
        }

        $savedTransactions = 0;
        foreach ($transactions as $row) {
            if ($this->persistTransaction($user, $billing, $row)) {
                $savedTransactions++;
            }
        }

        $summary = $this->buildSummary($billing, $transactions, $orders, $subscriptions);
        $this->updateBillingSummary($billing, $summary, $verificationSource, $transactions, $orders, $subscriptions);

        Log::info('PLYRCARD synced verified HighLevel payment details locally.', [
            'user_id' => $user->getKey(),
            'billing_id' => $billing->getKey(),
            'verification_source' => $verificationSource,
            'transactions_saved' => $savedTransactions,
            'transaction_ids' => collect($transactions)->map(fn (array $row): string => $this->rowId($row))->filter()->values()->all(),
            'order_ids' => $orderIds->all(),
            'subscription_ids' => $subscriptionIds->all(),
        ]);

        return [
            'synced' => true,
            'transactions_saved' => $savedTransactions,
            'transaction_ids' => collect($transactions)->map(fn (array $row): string => $this->rowId($row))->filter()->values()->all(),
            'order_ids' => $orderIds->all(),
            'subscription_ids' => $subscriptionIds->all(),
            'summary' => $summary,
        ];
    }

    protected function persistTransaction(User $user, BillingInformation $billing, array $row): bool
    {
        if (! Schema::hasTable('payment_transactions')) {
            return false;
        }

        $transactionId = $this->rowId($row);
        if ($transactionId === '') {
            return false;
        }

        [$cardBrand, $cardLastFour] = $this->paymentMethodSummary($row);
        $amountCents = $this->moneyToCents($row['amount'] ?? $row['amountPaid'] ?? $row['total'] ?? 0);
        $refundedCents = $this->moneyToCents($row['amountRefunded'] ?? $row['amount_refunded'] ?? 0);
        $entityType = trim((string) ($row['entityType'] ?? $row['entity_type'] ?? ''));
        $entityId = trim((string) ($row['entityId'] ?? $row['entity_id'] ?? ''));
        $orderId = strtolower($entityType) === 'order' ? $entityId : trim((string) ($row['orderId'] ?? $row['order_id'] ?? ''));

        $createdAt = $this->dateOrNull($row['createdAt'] ?? $row['created_at'] ?? null);
        $updatedAt = $this->dateOrNull($row['updatedAt'] ?? $row['updated_at'] ?? null);

        PaymentTransaction::query()->updateOrCreate(
            ['ghl_transaction_id' => $transactionId],
            [
                'user_id' => $user->getKey(),
                'billing_information_id' => $billing->getKey(),
                'plan_key' => $billing->plan_key,
                'ghl_location_id' => $billing->ghl_location_id ?: config('ghl.location_id'),
                'ghl_contact_id' => trim((string) ($row['contactId'] ?? $row['contact_id'] ?? $billing->ghl_contact_id)),
                'ghl_order_id' => $orderId !== '' ? $orderId : null,
                'ghl_subscription_id' => $this->nullableString($row['subscriptionId'] ?? $row['subscription_id'] ?? null),
                'ghl_charge_id' => $this->nullableString($row['chargeId'] ?? $row['charge_id'] ?? null),
                'status' => $this->nullableString($row['paymentStatus'] ?? $row['transactionStatus'] ?? $row['status'] ?? null),
                'currency' => $this->normalizeCurrency($row['currency'] ?? $billing->currency),
                'amount_cents' => max(0, $amountCents),
                'refunded_amount_cents' => max(0, $refundedCents),
                'payment_provider' => $this->nullableString($row['paymentProviderType'] ?? $row['paymentProvider'] ?? null),
                'payment_mode' => $this->nullableString($row['paymentMode'] ?? $row['mode'] ?? null),
                'live_mode' => $this->boolOrNull($row['liveMode'] ?? $row['live_mode'] ?? null),
                'card_brand' => $cardBrand,
                'card_last_four' => $cardLastFour,
                'entity_type' => $this->nullableString($entityType),
                'entity_id' => $this->nullableString($entityId),
                'source_type' => $this->nullableString($row['entitySourceType'] ?? $row['sourceType'] ?? null),
                'source_sub_type' => $this->nullableString($row['entitySourceSubType'] ?? $row['sourceSubType'] ?? null),
                'source_name' => $this->nullableString($row['entitySourceName'] ?? $row['sourceName'] ?? null),
                'ghl_created_at' => $createdAt,
                'ghl_updated_at' => $updatedAt,
                'paid_at' => $createdAt ?: now(),
                'synced_at' => now(),
                'ghl_payload' => $this->sanitizePayload($row),
            ],
        );

        return true;
    }

    protected function buildSummary(
        BillingInformation $billing,
        array $transactions,
        array $orders,
        array $subscriptions,
    ): array {
        $primaryTransaction = collect($transactions)
            ->sortByDesc(fn (array $row): string => (string) ($row['createdAt'] ?? $row['created_at'] ?? ''))
            ->first() ?: [];
        $primaryOrder = collect($orders)->first() ?: [];
        $primarySubscription = collect($subscriptions)->first() ?: [];

        [$cardBrand, $cardLastFour] = $this->paymentMethodSummary($primaryTransaction);

        $amountPaidCents = collect($transactions)->sum(fn (array $row): int => $this->moneyToCents(
            $row['amount'] ?? $row['amountPaid'] ?? $row['total'] ?? 0
        ));
        if ($amountPaidCents <= 0 && $primaryOrder !== []) {
            $amountPaidCents = $this->moneyToCents($primaryOrder['amount'] ?? $primaryOrder['total'] ?? 0);
        }

        $amountRefundedCents = collect($transactions)->sum(fn (array $row): int => $this->moneyToCents(
            $row['amountRefunded'] ?? $row['amount_refunded'] ?? 0
        ));

        $orderId = collect($transactions)
            ->map(function (array $row): string {
                $entityType = strtolower(trim((string) ($row['entityType'] ?? $row['entity_type'] ?? '')));
                if ($entityType === 'order') {
                    return trim((string) ($row['entityId'] ?? $row['entity_id'] ?? ''));
                }
                return trim((string) ($row['orderId'] ?? $row['order_id'] ?? ''));
            })
            ->filter()
            ->first()
            ?: $this->rowId($primaryOrder);

        $subscriptionId = collect($transactions)
            ->map(fn (array $row): string => trim((string) ($row['subscriptionId'] ?? $row['subscription_id'] ?? '')))
            ->filter()
            ->first()
            ?: $this->rowId($primarySubscription);

        $subscriptionStatus = $this->nullableString(
            $primarySubscription['status']
            ?? $primarySubscription['subscriptionStatus']
            ?? $billing->subscription_status
        );

        return [
            'ghl_transaction_id' => $this->rowId($primaryTransaction) ?: $billing->ghl_transaction_id,
            'ghl_order_id' => $orderId ?: null,
            'ghl_subscription_id' => $subscriptionId ?: null,
            'amount_paid_cents' => max(0, (int) $amountPaidCents),
            'amount_refunded_cents' => max(0, (int) $amountRefundedCents),
            'currency' => $this->normalizeCurrency(
                $primaryTransaction['currency']
                ?? $primaryOrder['currency']
                ?? $billing->currency
            ),
            'payment_provider' => $this->nullableString(
                $primaryTransaction['paymentProviderType']
                ?? $primaryTransaction['paymentProvider']
                ?? $billing->payment_provider
            ),
            'payment_mode' => $this->nullableString(
                $primaryTransaction['paymentMode']
                ?? $primaryOrder['paymentMode']
                ?? null
            ),
            'payment_live_mode' => $this->boolOrNull(
                $primaryTransaction['liveMode']
                ?? $primaryOrder['liveMode']
                ?? null
            ),
            'payment_brand' => $cardBrand ?: ($billing->payment_brand ?: null),
            'card_last_four' => $cardLastFour ?: ($billing->card_last_four ?: null),
            'subscription_status' => $subscriptionStatus,
        ];
    }

    protected function updateBillingSummary(
        BillingInformation $billing,
        array $summary,
        string $verificationSource,
        array $transactions,
        array $orders,
        array $subscriptions,
    ): void {
        $values = [];

        foreach ($summary as $column => $value) {
            if (Schema::hasColumn('billing_information', $column)) {
                $values[$column] = $value;
            }
        }

        if (Schema::hasColumn('billing_information', 'payment_synced_at')) {
            $values['payment_synced_at'] = now();
        }
        if (Schema::hasColumn('billing_information', 'ghl_synced_at')) {
            $values['ghl_synced_at'] = now();
        }

        if (Schema::hasColumn('billing_information', 'ghl_sync_response')) {
            $existing = is_array($billing->ghl_sync_response ?? null) ? $billing->ghl_sync_response : [];
            $values['ghl_sync_response'] = array_merge($existing, [
                'payment_detail_sync' => [
                    'synced_at' => now()->toIso8601String(),
                    'verification_source' => $verificationSource,
                    'transactions' => collect($transactions)->map(fn (array $row): array => $this->sanitizePayload($row))->values()->all(),
                    'orders' => collect($orders)->map(fn (array $row): array => $this->sanitizePayload($row))->values()->all(),
                    'subscriptions' => collect($subscriptions)->map(fn (array $row): array => $this->sanitizePayload($row))->values()->all(),
                ],
            ]);
        }

        if ($values !== []) {
            $billing->forceFill($values)->save();
        }
    }

    protected function fetchPaymentRows(string $endpoint, string $locationId, string $token, array $extra = []): array
    {
        $response = $this->request($endpoint, $locationId, $token, array_merge([
            'locationId' => $locationId,
            'altId' => $locationId,
            'altType' => 'location',
            'limit' => 100,
            'offset' => 0,
        ], $extra));

        if (! $response) {
            return [];
        }

        $data = $response->json();
        $rows = is_array($data) ? data_get($data, 'data', []) : [];

        return is_array($rows) ? $rows : [];
    }

    protected function fetchPaymentRecord(string $endpoint, string $locationId, string $token): array
    {
        $response = $this->request($endpoint, $locationId, $token, [
            'locationId' => $locationId,
            'altId' => $locationId,
            'altType' => 'location',
        ]);

        if (! $response) {
            return [];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [];
        }

        $record = data_get($data, 'data', $data);
        return is_array($record) ? $record : [];
    }

    protected function request(string $endpoint, string $locationId, string $token, array $query): ?Response
    {
        $baseUrl = rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
        $timeout = max(4, (int) config('plyrcard-registration.ghl.payment_verification.timeout', 8));
        $versions = array_values(array_unique(array_filter([
            trim((string) config('plyrcard-registration.ghl.payment_verification.api_version', 'v3')),
            'v3',
            '2021-04-15',
        ])));

        $lastError = null;
        foreach ($versions as $version) {
            try {
                $response = Http::withHeaders(['Version' => $version])
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->get($baseUrl . $endpoint, $query);

                if ($response->successful()) {
                    return $response;
                }

                $lastError = 'HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 500);
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
            }
        }

        Log::warning('PLYRCARD could not enrich verified HighLevel payment details.', [
            'endpoint' => $endpoint,
            'location_id' => $locationId,
            'error' => $lastError,
        ]);

        return null;
    }

    protected function paymentMethodSummary(array $row): array
    {
        $method = $row['paymentMethod'] ?? $row['payment_method'] ?? [];

        if (is_string($method)) {
            $decoded = json_decode($method, true);
            if (is_array($decoded)) {
                $method = $decoded;
            } else {
                $method = [];
            }
        }

        if (! is_array($method)) {
            $method = [];
        }

        $card = is_array($method['card'] ?? null) ? $method['card'] : $method;
        $brand = $this->nullableString($card['brand'] ?? $card['type'] ?? null);
        $last4 = preg_replace('/\D+/', '', (string) ($card['last4'] ?? $card['last_four'] ?? '')) ?: '';
        $last4 = $last4 !== '' ? substr($last4, -4) : null;

        return [$brand, $last4];
    }

    protected function sanitizePayload(array $payload): array
    {
        $blocked = [
            'cardnumber', 'card_number', 'number', 'pan', 'cvc', 'cvv',
            'securitycode', 'security_code', 'expiry', 'expiration',
        ];

        $walk = function ($value, ?string $key = null) use (&$walk, $blocked) {
            $normalizedKey = strtolower(str_replace(['-', ' '], '_', (string) $key));
            $compactKey = str_replace('_', '', $normalizedKey);

            if ($key !== null && (in_array($normalizedKey, $blocked, true) || in_array($compactKey, $blocked, true))) {
                // last4 is explicitly safe and handled before this generic key block.
                if (! in_array($normalizedKey, ['last4', 'last_four'], true)) {
                    return null;
                }
            }

            if (is_string($value) && in_array($compactKey, ['paymentmethod', 'card'], true)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $walk($decoded, $key) : null;
            }

            if (is_array($value)) {
                $clean = [];
                foreach ($value as $childKey => $childValue) {
                    if (in_array(strtolower((string) $childKey), ['last4', 'last_four'], true)) {
                        $clean[$childKey] = $childValue;
                        continue;
                    }
                    $sanitized = $walk($childValue, (string) $childKey);
                    if ($sanitized !== null) {
                        $clean[$childKey] = $sanitized;
                    }
                }
                return $clean;
            }

            if (is_object($value)) {
                return $walk((array) $value, $key);
            }

            return $value;
        };

        return $walk($payload) ?: [];
    }

    protected function moneyToCents(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        // HighLevel's Payments API documents amount/refund values in currency
        // units (e.g. 49 or 100), so persist them as integer cents locally.
        return (int) round(((float) $value) * 100);
    }

    protected function rowId(array $row): string
    {
        return trim((string) ($row['_id'] ?? $row['id'] ?? ''));
    }

    protected function normalizeCurrency(mixed $value): ?string
    {
        $currency = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    protected function boolOrNull(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    protected function dateOrNull(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
