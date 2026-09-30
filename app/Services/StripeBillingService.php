<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StripeBillingService
{
    public function startRegistration(User $user, BillingInformation $billing, string $planKey, array $plan): array
    {
        $this->assertConfigured();

        if ($billing->payment_status === 'paid') {
            return $this->checkoutPayload($billing, null, true);
        }

        $customerId = $this->ensureCustomer($user, $billing);

        if (filled($billing->stripe_subscription_id)) {
            try {
                $existing = $this->retrieveSubscription((string) $billing->stripe_subscription_id);
                $status = (string) ($existing['status'] ?? '');

                if (! in_array($status, ['canceled', 'incomplete_expired'], true)) {
                    $sync = $this->syncSubscription($billing, $existing);
                    $billing->refresh();

                    if (($sync['paid'] ?? false) === true) {
                        return $this->checkoutPayload($billing, null, true);
                    }

                    $secret = $this->clientSecretFromSubscription($existing);
                    if ($secret) {
                        return $this->checkoutPayload($billing, $secret, false);
                    }
                }
            } catch (\Throwable $exception) {
                Log::warning('Stripe registration could not reuse existing subscription.', [
                    'billing_id' => $billing->getKey(),
                    'subscription_id' => $billing->stripe_subscription_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $journeyPlan = (array) config('plyrcard-registration.plans.my-journey', []);
        $journeyProductId = trim((string) ($journeyPlan['stripe_product_id'] ?? ''));
        $journeyAmount = max(1, (int) ($journeyPlan['recurring_amount_cents'] ?? 4900));
        $journeyPriceId = $this->resolvePriceId(
            $journeyProductId,
            $journeyAmount,
            'month',
            trim((string) ($journeyPlan['stripe_price_id'] ?? '')) ?: null,
        );

        $setupAmount = max(0, (int) ($plan['setup_fee_cents'] ?? 0));
        $setupPriceId = null;

        if ($setupAmount > 0) {
            $setupProductId = trim((string) ($plan['stripe_product_id'] ?? ''));
            $setupPriceId = $this->resolvePriceId(
                $setupProductId,
                $setupAmount,
                null,
                trim((string) ($plan['stripe_price_id'] ?? '')) ?: null,
            );
        }

        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        $attempt = max(0, (int) data_get($meta, 'stripe.checkout_attempt', 0)) + 1;
        data_set($meta, 'stripe.checkout_attempt', $attempt);
        data_set($meta, 'stripe.checkout_plan_key', $planKey);
        data_set($meta, 'stripe.started_at', now()->toIso8601String());
        $billing->forceFill(['registration_meta' => $meta])->save();

        $params = [
            'customer' => $customerId,
            'collection_method' => 'charge_automatically',
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
                'payment_method_types' => ['card'],
            ],
            'items' => [
                ['price' => $journeyPriceId, 'quantity' => 1],
            ],
            'metadata' => $this->metadata($user, $billing, $planKey),
            'expand' => [
                'latest_invoice.confirmation_secret',
                'latest_invoice.payment_intent',
            ],
        ];

        if ($setupPriceId) {
            $params['add_invoice_items'] = [[
                'price' => $setupPriceId,
                'quantity' => 1,
                'metadata' => [
                    'plyrcard_billing_id' => (string) $billing->getKey(),
                    'plyrcard_registration_plan' => $planKey,
                    'plyrcard_charge_type' => 'registration_setup',
                ],
            ]];
        }

        $subscription = $this->post(
            '/v1/subscriptions',
            $params,
            'plyrcard-registration-subscription-' . $billing->getKey() . '-' . $attempt,
        );

        $clientSecret = $this->clientSecretFromSubscription($subscription);
        $invoice = $this->invoiceFromSubscription($subscription);
        $paymentIntentId = $this->paymentIntentId($invoice, $clientSecret);

        $billing->forceFill([
            'payment_provider' => 'stripe',
            'payment_type' => 'card',
            'payment_status' => in_array((string) ($subscription['status'] ?? ''), ['active', 'trialing'], true)
                ? 'paid'
                : 'payment_form_ready',
            'subscription_status' => (string) ($subscription['status'] ?? 'incomplete'),
            'payment_mode' => ! empty($subscription['livemode']) ? 'live' : 'test',
            'payment_live_mode' => (bool) ($subscription['livemode'] ?? false),
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscription['id'] ?? null,
            'stripe_invoice_id' => is_array($invoice) ? ($invoice['id'] ?? null) : null,
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_recurring_price_id' => $journeyPriceId,
            'stripe_setup_price_id' => $setupPriceId,
            'stripe_synced_at' => now(),
            'payment_synced_at' => now(),
        ])->save();

        if (in_array((string) ($subscription['status'] ?? ''), ['active', 'trialing'], true)) {
            $this->activateEntitlements($billing->fresh(), $invoice);
            $billing->refresh();
            return $this->checkoutPayload($billing, null, true);
        }

        if (! $clientSecret) {
            throw new RuntimeException('Stripe created the subscription but did not return a payment client secret.');
        }

        return $this->checkoutPayload($billing->fresh(), $clientSecret, false);
    }

    public function refreshRegistration(User $user, BillingInformation $billing): array
    {
        if ($billing->payment_status === 'paid') {
            return [
                'paid' => true,
                'payment_status' => $billing->payment_status,
                'subscription_status' => $billing->subscription_status,
                'source' => 'local',
            ];
        }

        if (blank($billing->stripe_subscription_id)) {
            return [
                'paid' => false,
                'payment_status' => $billing->payment_status,
                'subscription_status' => $billing->subscription_status,
                'source' => 'stripe',
                'reason' => 'subscription_not_created',
            ];
        }

        $subscription = $this->retrieveSubscription((string) $billing->stripe_subscription_id);
        $result = $this->syncSubscription($billing, $subscription);

        return array_merge($result, ['source' => 'stripe']);
    }

    public function handleWebhook(array $event): void
    {
        $eventId = trim((string) ($event['id'] ?? ''));
        $type = trim((string) ($event['type'] ?? ''));
        $object = (array) data_get($event, 'data.object', []);

        if ($eventId === '' || $type === '' || $object === []) {
            return;
        }

        $billing = $this->billingForStripeObject($object);
        if (! $billing) {
            Log::info('Stripe webhook did not match a PLYRCARD billing record.', [
                'stripe_event_id' => $eventId,
                'stripe_event_type' => $type,
                'stripe_object_id' => $object['id'] ?? null,
            ]);
            return;
        }

        if ($billing->stripe_last_event_id === $eventId) {
            return;
        }

        try {
            if (str_starts_with($type, 'customer.subscription.')) {
                $this->syncSubscription($billing, $object);
            } elseif (in_array($type, ['invoice.paid', 'invoice.payment_succeeded'], true)) {
                $subscriptionId = $this->subscriptionIdFromStripeObject($object)
                    ?: (string) $billing->stripe_subscription_id;

                if ($subscriptionId !== '') {
                    $subscription = $this->retrieveSubscription($subscriptionId);
                    $this->syncSubscription($billing, $subscription, $object);
                } else {
                    $billing->forceFill([
                        'payment_status' => 'paid',
                        'subscription_status' => 'active',
                        'stripe_invoice_id' => $object['id'] ?? $billing->stripe_invoice_id,
                        'amount_paid_cents' => (int) ($object['amount_paid'] ?? $billing->amount_paid_cents ?? 0),
                        'stripe_synced_at' => now(),
                        'payment_synced_at' => now(),
                    ])->save();
                    $this->activateEntitlements($billing->fresh(), $object);
                }
            } elseif ($type === 'invoice.payment_failed') {
                $billing->forceFill([
                    'payment_status' => 'failed',
                    'subscription_status' => 'past_due',
                    'stripe_invoice_id' => $object['id'] ?? $billing->stripe_invoice_id,
                    'stripe_synced_at' => now(),
                    'payment_synced_at' => now(),
                ])->save();
            }
        } finally {
            $billing->forceFill([
                'stripe_last_event_id' => $eventId,
                'stripe_last_event_at' => now(),
            ])->save();
        }
    }

    protected function syncSubscription(BillingInformation $billing, array $subscription, ?array $invoiceOverride = null): array
    {
        $status = trim((string) ($subscription['status'] ?? 'unknown'));
        $invoice = $invoiceOverride ?: $this->invoiceFromSubscription($subscription);
        $invoiceStatus = is_array($invoice) ? trim((string) ($invoice['status'] ?? '')) : '';
        $clientSecret = $this->clientSecretFromInvoice($invoice);
        $paymentIntentId = $this->paymentIntentId($invoice, $clientSecret)
            ?: $billing->stripe_payment_intent_id;

        $paid = $invoiceStatus === 'paid' || in_array($status, ['active', 'trialing'], true);
        $paymentStatus = $paid
            ? 'paid'
            : (in_array($status, ['canceled', 'incomplete_expired', 'unpaid'], true) ? 'failed' : 'payment_form_ready');

        $updates = [
            'payment_provider' => 'stripe',
            'payment_status' => $paymentStatus,
            'subscription_status' => $status ?: 'unknown',
            'payment_mode' => ! empty($subscription['livemode']) ? 'live' : 'test',
            'payment_live_mode' => (bool) ($subscription['livemode'] ?? false),
            'stripe_customer_id' => $this->idValue($subscription['customer'] ?? null) ?: $billing->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $billing->stripe_subscription_id,
            'stripe_invoice_id' => is_array($invoice) ? ($invoice['id'] ?? $billing->stripe_invoice_id) : $billing->stripe_invoice_id,
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_synced_at' => now(),
            'payment_synced_at' => now(),
        ];

        if (is_array($invoice) && isset($invoice['amount_paid'])) {
            $updates['amount_paid_cents'] = (int) $invoice['amount_paid'];
        }
        if (is_array($invoice) && isset($invoice['currency'])) {
            $updates['currency'] = strtoupper((string) $invoice['currency']);
        }

        $billing->forceFill($updates)->save();

        if ($paid) {
            $this->activateEntitlements($billing->fresh(), $invoice);
            $this->syncPaymentMethodMetadata($billing->fresh());
        }

        return [
            'paid' => $paid,
            'payment_status' => $paymentStatus,
            'subscription_status' => $status,
            'invoice_status' => $invoiceStatus ?: null,
        ];
    }

    protected function activateEntitlements(BillingInformation $billing, ?array $invoice = null): void
    {
        $user = $billing->user;
        if (! $user) {
            return;
        }

        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        $checkoutPlan = (string) (
            data_get($meta, 'stripe.checkout_plan_key')
            ?: data_get($meta, 'source_utm_plan')
            ?: $billing->plan_key
        );

        if (! in_array($checkoutPlan, ['my-journey', 'jumpstart', 'amplify'], true)) {
            $checkoutPlan = 'my-journey';
        }

        if (method_exists($user, 'assignRole')) {
            if (! $user->hasRole('My Journey')) {
                $user->assignRole('My Journey');
            }

            if ($checkoutPlan === 'jumpstart' && ! $user->hasRole('Jumpstart')) {
                $user->assignRole('Jumpstart');
            }

            if ($checkoutPlan === 'amplify' && ! $user->hasRole('Amplify')) {
                $user->assignRole('Amplify');
            }
        } elseif (method_exists($user, 'syncRoles')) {
            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
            $roles[] = 'My Journey';
            if ($checkoutPlan === 'jumpstart') {
                $roles[] = 'Jumpstart';
            }
            if ($checkoutPlan === 'amplify') {
                $roles[] = 'Amplify';
            }
            $user->syncRoles(array_values(array_unique($roles)));
        }

        $journeyRecurring = (int) config('plyrcard-registration.plans.my-journey.recurring_amount_cents', 4900);
        $amountPaid = is_array($invoice) && isset($invoice['amount_paid'])
            ? (int) $invoice['amount_paid']
            : (int) $billing->amount_paid_cents;

        $billing->forceFill([
            'plan_key' => 'my-journey',
            'billing_cycle' => 'monthly',
            'recurring_amount_cents' => $journeyRecurring,
            'payment_status' => 'paid',
            'subscription_status' => 'active',
            'amount_paid_cents' => $amountPaid,
            'stripe_synced_at' => now(),
            'payment_synced_at' => now(),
        ])->save();
    }

    protected function syncPaymentMethodMetadata(BillingInformation $billing): void
    {
        $intentId = trim((string) $billing->stripe_payment_intent_id);
        if ($intentId === '') {
            return;
        }

        try {
            $intent = $this->get('/v1/payment_intents/' . rawurlencode($intentId), [
                'expand' => ['payment_method'],
            ]);
            $paymentMethod = $intent['payment_method'] ?? null;

            if (is_string($paymentMethod) && $paymentMethod !== '') {
                $paymentMethod = $this->get('/v1/payment_methods/' . rawurlencode($paymentMethod));
            }

            if (! is_array($paymentMethod)) {
                return;
            }

            $card = (array) ($paymentMethod['card'] ?? []);
            $billing->forceFill([
                'stripe_payment_method_id' => $paymentMethod['id'] ?? $billing->stripe_payment_method_id,
                'cardholder_name' => data_get($paymentMethod, 'billing_details.name') ?: $billing->cardholder_name,
                'card_last_four' => $card['last4'] ?? $billing->card_last_four,
                'card_expiration' => isset($card['exp_month'], $card['exp_year'])
                    ? sprintf('%02d/%d', (int) $card['exp_month'], (int) $card['exp_year'])
                    : $billing->card_expiration,
                'payment_brand' => $card['brand'] ?? $billing->payment_brand,
                'payment_type' => $paymentMethod['type'] ?? $billing->payment_type,
            ])->save();
        } catch (\Throwable $exception) {
            Log::info('Stripe payment method metadata could not be refreshed.', [
                'billing_id' => $billing->getKey(),
                'payment_intent_id' => $intentId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function ensureCustomer(User $user, BillingInformation $billing): string
    {
        if (filled($billing->stripe_customer_id)) {
            return (string) $billing->stripe_customer_id;
        }

        $customer = $this->post('/v1/customers', [
            'email' => $billing->billing_email ?: $user->email,
            'name' => $billing->billing_name ?: trim((string) $user->first_name . ' ' . (string) $user->last_name),
            'phone' => $billing->billing_phone ?: $user->phone,
            'address' => [
                'line1' => $billing->billing_address_1,
                'line2' => $billing->billing_address_2,
                'city' => $billing->billing_city,
                'state' => $billing->billing_state,
                'postal_code' => $billing->billing_postal_code,
                'country' => strtoupper((string) ($billing->billing_country ?: 'US')),
            ],
            'metadata' => $this->metadata($user, $billing, (string) data_get($billing->registration_meta, 'stripe.checkout_plan_key', $billing->plan_key)),
        ], 'plyrcard-registration-customer-' . $billing->getKey());

        $customerId = trim((string) ($customer['id'] ?? ''));
        if ($customerId === '') {
            throw new RuntimeException('Stripe did not return a customer ID.');
        }

        $billing->forceFill([
            'stripe_customer_id' => $customerId,
            'payment_provider' => 'stripe',
            'payment_mode' => ! empty($customer['livemode']) ? 'live' : 'test',
            'payment_live_mode' => (bool) ($customer['livemode'] ?? false),
            'stripe_synced_at' => now(),
        ])->save();

        return $customerId;
    }

    protected function resolvePriceId(string $productId, int $amountCents, ?string $interval, ?string $configuredPriceId = null): string
    {
        if ($configuredPriceId) {
            return $configuredPriceId;
        }

        if ($productId === '') {
            throw new RuntimeException('A Stripe Product ID is missing from the PLYRCARD plan configuration.');
        }

        $prices = $this->get('/v1/prices', [
            'product' => $productId,
            'active' => 'true',
            'limit' => 100,
        ]);

        foreach ((array) ($prices['data'] ?? []) as $price) {
            if (! is_array($price)) {
                continue;
            }

            $priceInterval = data_get($price, 'recurring.interval');
            $matchesInterval = $interval === null ? blank($priceInterval) : $priceInterval === $interval;

            if (
                (int) ($price['unit_amount'] ?? -1) === $amountCents
                && strtolower((string) ($price['currency'] ?? '')) === 'usd'
                && $matchesInterval
            ) {
                return (string) $price['id'];
            }
        }

        $params = [
            'product' => $productId,
            'currency' => 'usd',
            'unit_amount' => $amountCents,
        ];

        if ($interval !== null) {
            $params['recurring'] = ['interval' => $interval, 'interval_count' => 1];
        }

        $price = $this->post(
            '/v1/prices',
            $params,
            'plyrcard-price-' . $productId . '-' . $amountCents . '-' . ($interval ?: 'one-time'),
        );

        $priceId = trim((string) ($price['id'] ?? ''));
        if ($priceId === '') {
            throw new RuntimeException('Stripe did not return a Price ID.');
        }

        return $priceId;
    }

    protected function retrieveSubscription(string $subscriptionId): array
    {
        return $this->get('/v1/subscriptions/' . rawurlencode($subscriptionId), [
            'expand' => [
                'latest_invoice.confirmation_secret',
                'latest_invoice.payment_intent',
            ],
        ]);
    }

    protected function invoiceFromSubscription(array $subscription): ?array
    {
        $invoice = $subscription['latest_invoice'] ?? null;

        if (is_array($invoice)) {
            return $invoice;
        }

        if (is_string($invoice) && $invoice !== '') {
            return $this->get('/v1/invoices/' . rawurlencode($invoice), [
                'expand' => ['confirmation_secret', 'payment_intent'],
            ]);
        }

        return null;
    }

    protected function clientSecretFromSubscription(array $subscription): ?string
    {
        return $this->clientSecretFromInvoice($this->invoiceFromSubscription($subscription));
    }

    protected function clientSecretFromInvoice(?array $invoice): ?string
    {
        if (! is_array($invoice)) {
            return null;
        }

        $secret = data_get($invoice, 'confirmation_secret.client_secret')
            ?: data_get($invoice, 'payment_intent.client_secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    protected function paymentIntentId(?array $invoice, ?string $clientSecret = null): ?string
    {
        $intent = is_array($invoice) ? ($invoice['payment_intent'] ?? null) : null;
        $id = $this->idValue($intent);

        if ($id) {
            return $id;
        }

        if ($clientSecret && str_contains($clientSecret, '_secret_')) {
            return strstr($clientSecret, '_secret_', true) ?: null;
        }

        return null;
    }

    protected function billingForStripeObject(array $object): ?BillingInformation
    {
        $metadata = array_merge(
            (array) ($object['metadata'] ?? []),
            (array) data_get($object, 'subscription_details.metadata', []),
            (array) data_get($object, 'parent.subscription_details.metadata', []),
        );

        $billingId = (int) ($metadata['plyrcard_billing_id'] ?? 0);
        if ($billingId > 0) {
            $billing = BillingInformation::query()->find($billingId);
            if ($billing) {
                return $billing;
            }
        }

        $subscriptionId = $this->subscriptionIdFromStripeObject($object);
        if ($subscriptionId) {
            $billing = BillingInformation::query()->where('stripe_subscription_id', $subscriptionId)->latest('id')->first();
            if ($billing) {
                return $billing;
            }
        }

        $customerId = $this->idValue($object['customer'] ?? null);
        if ($customerId) {
            return BillingInformation::query()->where('stripe_customer_id', $customerId)->latest('id')->first();
        }

        return null;
    }

    protected function subscriptionIdFromStripeObject(array $object): ?string
    {
        if (($object['object'] ?? null) === 'subscription') {
            return $this->idValue($object['id'] ?? null);
        }

        return $this->idValue($object['subscription'] ?? null)
            ?: $this->idValue(data_get($object, 'parent.subscription_details.subscription'))
            ?: $this->idValue(data_get($object, 'subscription_details.subscription'));
    }

    protected function idValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value) && filled($value['id'] ?? null)) {
            return (string) $value['id'];
        }

        return null;
    }

    protected function metadata(User $user, BillingInformation $billing, string $planKey): array
    {
        return [
            'plyrcard_user_id' => (string) $user->getKey(),
            'plyrcard_billing_id' => (string) $billing->getKey(),
            'plyrcard_registration_plan' => $planKey,
            'plyrcard_source' => 'native_registration',
        ];
    }

    protected function checkoutPayload(BillingInformation $billing, ?string $clientSecret, bool $paid): array
    {
        return [
            'paid' => $paid,
            'client_secret' => $clientSecret,
            'publishable_key' => (string) config('services.stripe.key'),
            'customer_id' => $billing->stripe_customer_id,
            'subscription_id' => $billing->stripe_subscription_id,
            'payment_status' => $billing->payment_status,
            'subscription_status' => $billing->subscription_status,
            'amount_due_cents' => (int) $billing->initial_amount_cents,
            'currency' => strtolower((string) ($billing->currency ?: 'USD')),
        ];
    }

    protected function assertConfigured(): void
    {
        if (blank(config('services.stripe.secret'))) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in the environment.');
        }

        if (blank(config('services.stripe.key'))) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_KEY in the environment.');
        }
    }

    protected function get(string $path, array $query = []): array
    {
        $response = $this->request()->get($this->apiUrl($path), $query);
        return $this->decode($response->status(), $response->json());
    }

    protected function post(string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        $request = $this->request()->asForm();

        if ($idempotencyKey) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post($this->apiUrl($path), $payload);
        return $this->decode($response->status(), $response->json());
    }

    protected function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withToken((string) config('services.stripe.secret'))
            ->connectTimeout((int) config('services.stripe.connect_timeout', 5))
            ->timeout((int) config('services.stripe.timeout', 20));

        $version = trim((string) config('services.stripe.version'));
        if ($version !== '') {
            $request = $request->withHeaders(['Stripe-Version' => $version]);
        }

        return $request;
    }

    protected function apiUrl(string $path): string
    {
        return rtrim((string) config('services.stripe.api_base', 'https://api.stripe.com'), '/') . '/' . ltrim($path, '/');
    }

    protected function decode(int $status, mixed $json): array
    {
        $data = is_array($json) ? $json : [];

        if ($status >= 200 && $status < 300) {
            return $data;
        }

        $message = (string) data_get($data, 'error.message', 'Stripe request failed.');
        throw new RuntimeException($message . ' [HTTP ' . $status . ']');
    }
}
