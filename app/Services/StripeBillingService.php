<?php
namespace App\Services;
use App\Models\BillingInformation;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $savedPaymentMethodId = $this->resolveSavedPaymentMethodId($billing);
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
                        $payload = $this->checkoutPayload($billing, $secret, false);
                        $payload['saved_payment_method'] = (bool) $savedPaymentMethodId;
                        $payload['saved_payment_method_id'] = $savedPaymentMethodId;
                        return $payload;
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
        if ($savedPaymentMethodId) {
            $params['default_payment_method'] = $savedPaymentMethodId;
        }
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
        $payload = $this->checkoutPayload($billing->fresh(), $clientSecret, false);
        $payload['saved_payment_method'] = (bool) $savedPaymentMethodId;
        $payload['saved_payment_method_id'] = $savedPaymentMethodId;
        return $payload;
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
    public function startUpgrade(User $user, string $planKey): array
    {
        $this->assertConfigured();
        $user->loadMissing('roles');
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
            ],
        );
        $hasJourney = method_exists($user, 'hasRole') && ($user->hasRole('My Journey') || $user->hasRole('my journey'));
        if ($planKey === 'my-journey' && $hasJourney) {
            return array_merge($this->billingSummary($user), ['success' => true, 'completed' => true, 'message' => 'My Journey is already active.']);
        }
        $plan = (array) config('plyrcard-registration.plans.' . $planKey, []);
        if (! in_array($planKey, ['my-journey', 'jumpstart', 'amplify'], true) || $plan === []) {
            throw new RuntimeException('Unsupported upgrade plan.');
        }
        if (! $hasJourney) {
            $billing->forceFill([
                'plan_key' => $planKey,
                'billing_cycle' => 'monthly',
                'recurring_amount_cents' => (int) config('plyrcard-registration.plans.my-journey.recurring_amount_cents', 4900),
                'setup_fee_cents' => max(0, (int) ($plan['setup_fee_cents'] ?? 0)),
                'initial_amount_cents' => (int) config('plyrcard-registration.plans.my-journey.recurring_amount_cents', 4900) + max(0, (int) ($plan['setup_fee_cents'] ?? 0)),
                'payment_status' => 'pending',
                'subscription_status' => 'pending',
            ])->save();
            $payload = $this->startRegistration($user, $billing->fresh(), $planKey, $plan);
            return array_merge($payload, [
                'success' => true,
                'checkout_mode' => $planKey === 'my-journey' ? 'my_journey_subscription' : $planKey . '_plus_my_journey',
                'message' => 'Complete payment securely with Stripe.',
            ]);
        }
        if ($planKey === 'my-journey') {
            return array_merge($this->billingSummary($user), ['success' => true, 'completed' => true, 'message' => 'My Journey is already active.']);
        }
        $customerId = $this->ensureCustomer($user, $billing);
        $savedPaymentMethodId = $this->resolveSavedPaymentMethodId($billing);
        $amount = max(1, (int) ($plan['setup_fee_cents'] ?? 0));
        $meta = $this->metadata($user, $billing, $planKey);
        $meta['plyrcard_source'] = 'authenticated_upgrade';
        $meta['plyrcard_charge_type'] = 'one_time_credit_package';
        $attempt = now()->format('YmdHis') . '-' . $user->getKey();
        $intentParams = [
            'amount' => $amount,
            'currency' => strtolower((string) ($billing->currency ?: 'USD')),
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'metadata' => $meta,
            'description' => 'PLYRCARD ' . ucfirst($planKey) . ' credit package',
        ];
        if ($savedPaymentMethodId) {
            $intentParams['payment_method'] = $savedPaymentMethodId;
            $intentParams['confirm'] = 'true';
            $intentParams['off_session'] = 'true';
        } else {
            $intentParams['setup_future_usage'] = 'off_session';
        }
        $intent = $this->post('/v1/payment_intents', $intentParams, 'plyrcard-upgrade-' . $planKey . '-' . $attempt);
        $metaLocal = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        data_set($metaLocal, 'stripe_upgrade.' . $planKey, [
            'payment_intent_id' => $intent['id'] ?? null,
            'started_at' => now()->toIso8601String(),
            'amount_cents' => $amount,
        ]);
        $intentStatus = (string) ($intent['status'] ?? '');
        $billing->forceFill([
            'payment_provider' => 'stripe',
            'payment_type' => 'card',
            'payment_status' => $intentStatus === 'succeeded' ? 'paid' : 'payment_form_ready',
            'stripe_customer_id' => $customerId,
            'stripe_payment_intent_id' => $intent['id'] ?? $billing->stripe_payment_intent_id,
            'registration_meta' => $metaLocal,
            'stripe_synced_at' => now(),
        ])->save();
        if ($intentStatus === 'succeeded') {
            $this->handleOneTimePaymentSucceeded($billing->fresh(), $intent);
            return array_merge($this->billingSummary($user), [
                'success' => true,
                'completed' => true,
                'used_saved_payment_method' => true,
                'message' => ucfirst($planKey) . ' was charged to your saved card and the credits were added to your account.',
            ]);
        }
        return [
            'success' => true,
            'completed' => false,
            'client_secret' => $intent['client_secret'] ?? null,
            'publishable_key' => (string) config('services.stripe.key'),
            'payment_intent_id' => $intent['id'] ?? null,
            'expected_amount_cents' => $amount,
            'amount_due_cents' => $amount,
            'currency' => strtolower((string) ($billing->currency ?: 'USD')),
            'checkout_mode' => $planKey . '_service_only',
            'saved_payment_method' => (bool) $savedPaymentMethodId,
            'saved_payment_method_id' => $savedPaymentMethodId,
            'saved_card' => $this->savedCardPayload($billing->fresh()),
            'requires_action' => $intentStatus === 'requires_action',
            'message' => $savedPaymentMethodId
                ? 'Confirm the saved card charge if Stripe asks for additional verification.'
                : 'Complete the one-time ' . ucfirst($planKey) . ' credit purchase below.',
        ];
    }
    public function upgradeStatus(User $user, string $planKey): array
    {
        $user->refresh()->loadMissing('roles');
        $billing = BillingInformation::query()->where('user_id', $user->getKey())->latest('id')->first();
        if (! $billing) {
            return ['success' => true, 'completed' => false, 'message' => 'Waiting for Stripe payment confirmation…'];
        }
        if (in_array($planKey, ['jumpstart', 'amplify'], true)) {
            $intentId = (string) data_get($billing->registration_meta ?? [], 'stripe_upgrade.' . $planKey . '.payment_intent_id', '');
            if ($intentId !== '') {
                try {
                    $intent = $this->get('/v1/payment_intents/' . rawurlencode($intentId));
                    $stripeSucceeded = (string) ($intent['status'] ?? '') === 'succeeded';
                    $completed = false;
                    if ($stripeSucceeded) {
                        $this->handleOneTimePaymentSucceeded($billing, $intent);
                        $user->refresh()->loadMissing('roles');
                        $completed = app(CreditPointService::class)->hasPackageGrant($user, $planKey, $intentId);
                    }
                    return array_merge($this->billingSummary($user), [
                        'success' => true,
                        'completed' => $completed,
                        'message' => $completed
                            ? ucfirst($planKey) . ' credits were added to your account.'
                            : ($stripeSucceeded ? 'Stripe payment succeeded. Finishing your credit grant…' : 'Waiting for Stripe payment confirmation…'),
                    ]);
                } catch (\Throwable $exception) {
                    Log::info('Stripe one-time upgrade status refresh delayed.', [
                        'user_id' => $user->getKey(),
                        'plan' => $planKey,
                        'payment_intent_id' => $intentId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }
        if (filled($billing->stripe_subscription_id)) {
            try {
                $subscription = $this->retrieveSubscription((string) $billing->stripe_subscription_id);
                $sync = $this->syncSubscription($billing, $subscription);
                $billing->refresh();
                $user->refresh()->loadMissing('roles');
                if (($sync['paid'] ?? false) === true) {
                    $grantSourceId = (string) ($billing->fresh()->stripe_invoice_id ?? '');
                    $completed = match ($planKey) {
                        'my-journey' => method_exists($user, 'hasRole') && $user->hasRole('My Journey'),
                        'jumpstart' => method_exists($user, 'hasRole')
                            && $user->hasRole('Jumpstart')
                            && app(CreditPointService::class)->hasPackageGrant($user, 'jumpstart', $grantSourceId),
                        'amplify' => method_exists($user, 'hasRole')
                            && $user->hasRole('Amplify')
                            && app(CreditPointService::class)->hasPackageGrant($user, 'amplify', $grantSourceId),
                        default => false,
                    };
                    if ($completed) {
                        return array_merge($this->billingSummary($user), [
                            'success' => true,
                            'completed' => true,
                            'message' => $planKey === 'my-journey'
                                ? 'My Journey is active.'
                                : ucfirst($planKey) . ' credits were added to your account.',
                        ]);
                    }
                }
            } catch (\Throwable $exception) {
                Log::info('Stripe subscription upgrade status refresh delayed.', [
                    'user_id' => $user->getKey(),
                    'plan' => $planKey,
                    'subscription_id' => $billing->stripe_subscription_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
        $fallbackSourceId = in_array($planKey, ['jumpstart', 'amplify'], true)
            ? (string) (data_get($billing->registration_meta ?? [], 'stripe_upgrade.' . $planKey . '.payment_intent_id') ?: $billing->stripe_invoice_id ?: '')
            : '';
        $active = match ($planKey) {
            'my-journey' => method_exists($user, 'hasRole') && $user->hasRole('My Journey'),
            'jumpstart' => method_exists($user, 'hasRole')
                && $user->hasRole('Jumpstart')
                && app(CreditPointService::class)->hasPackageGrant($user, 'jumpstart', $fallbackSourceId),
            'amplify' => method_exists($user, 'hasRole')
                && $user->hasRole('Amplify')
                && app(CreditPointService::class)->hasPackageGrant($user, 'amplify', $fallbackSourceId),
            default => false,
        };
        return array_merge($this->billingSummary($user), [
            'success' => true,
            'completed' => $active,
            'message' => $active ? ucfirst(str_replace('-', ' ', $planKey)) . ' is active.' : 'Waiting for Stripe payment confirmation…',
        ]);
    }
    public function refreshBilling(User $user): BillingInformation
    {
        $billing = BillingInformation::query()->firstOrCreate(['user_id' => $user->getKey()], [
            'billing_name' => trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
            'billing_email' => $user->email,
            'currency' => 'USD',
        ]);

        if (filled($billing->stripe_subscription_id)) {
            try {
                $subscription = $this->retrieveSubscription((string) $billing->stripe_subscription_id);
                $this->syncSubscription($billing, $subscription);
                $this->syncSubscriptionDefaultPaymentMethod($billing->fresh(), $subscription);
            } catch (\Throwable $e) {
                Log::info('Stripe billing refresh delayed.', ['billing_id'=>$billing->getKey(),'error'=>$e->getMessage()]);
            }
        }

        // A subscription can have no explicit default while the Stripe customer does.
        // Always resolve the effective saved card so Billing Settings can show a hint.
        try {
            $fresh = $billing->fresh();
            $paymentMethodId = $this->resolveSavedPaymentMethodId($fresh);
            if ($paymentMethodId) {
                $this->syncPaymentMethodById($fresh, $paymentMethodId);
            }
        } catch (\Throwable $e) {
            Log::info('Stripe saved payment method refresh delayed.', ['billing_id'=>$billing->getKey(),'error'=>$e->getMessage()]);
        }

        return $billing->fresh();
    }
    public function billingSummary(User $user): array
    {
        $billing = $this->refreshBilling($user);
        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        if (filled($billing->stripe_customer_id) && Schema::hasTable('payment_transactions')) {
            try { $this->syncRecentStripeHistory($billing); } catch (\Throwable $e) { Log::info('Stripe billing history refresh delayed.', ['billing_id'=>$billing->getKey(),'error'=>$e->getMessage()]); }
        }
        $history = Schema::hasTable('payment_transactions') ? PaymentTransaction::query()->where('user_id', $user->getKey())->where('payment_provider', 'stripe')->latest('paid_at')->latest('id')->limit(25)->get()->map(fn (PaymentTransaction $row) => [
            'id' => $row->id,
            'status' => $row->status,
            'currency' => $row->currency ?: 'USD',
            'amount_cents' => (int) $row->amount_cents,
            'refunded_amount_cents' => (int) $row->refunded_amount_cents,
            'source_name' => $row->source_name,
            'paid_at' => optional($row->paid_at)->toIso8601String(),
            'stripe_invoice_id' => $row->stripe_invoice_id,
            'stripe_payment_intent_id' => $row->stripe_payment_intent_id,
        ])->all() : [];
        $subscription = [];
        if (filled($billing->stripe_subscription_id)) {
            try { $subscription = $this->retrieveSubscription((string) $billing->stripe_subscription_id); } catch (\Throwable) { $subscription = []; }
        }
        return [
            'billing' => $billing,
            'payment_status' => $billing->payment_status,
            'subscription_status' => $billing->subscription_status,
            'cancel_at_period_end' => (bool) ($subscription['cancel_at_period_end'] ?? data_get($meta,'stripe.cancel_at_period_end',false)),
            'current_period_end' => isset($subscription['current_period_end']) ? date(DATE_ATOM, (int) $subscription['current_period_end']) : null,
            'card' => [
                'brand' => $billing->payment_brand,
                'last_four' => $billing->card_last_four,
                'expiration' => $billing->card_expiration,
            ],
            'history' => $history,
            'points_available' => (int) ($user->fresh()->points_available ?? 0),
        ];
    }
    public function createPaymentMethodSetup(User $user): array
    {
        $billing = BillingInformation::query()->firstOrCreate(['user_id' => $user->getKey()], ['billing_email'=>$user->email,'currency'=>'USD']);
        $customerId = $this->ensureCustomer($user, $billing);
        $intent = $this->post('/v1/setup_intents', [
            'customer' => $customerId,
            'usage' => 'off_session',
            'payment_method_types' => ['card'],
            'metadata' => $this->metadata($user, $billing, 'payment-method-update'),
        ], 'plyrcard-card-update-' . $billing->getKey() . '-' . now()->format('YmdHis'));
        return ['success'=>true,'client_secret'=>$intent['client_secret'] ?? null,'publishable_key'=>(string) config('services.stripe.key')];
    }
    public function completePaymentMethodSetup(User $user, string $setupIntentId): array
    {
        $setupIntentId = trim($setupIntentId);
        if ($setupIntentId === '') {
            throw new RuntimeException('The Stripe SetupIntent ID is required.');
        }
        $billing = BillingInformation::query()->firstOrCreate(['user_id' => $user->getKey()], [
            'billing_email' => $user->email,
            'currency' => 'USD',
        ]);
        $intent = $this->get('/v1/setup_intents/' . rawurlencode($setupIntentId));
        $intentCustomerId = $this->idValue($intent['customer'] ?? null);
        if (filled($billing->stripe_customer_id) && $intentCustomerId !== (string) $billing->stripe_customer_id) {
            throw new RuntimeException('This payment-method update does not belong to your Stripe customer.');
        }
        if ((string) ($intent['status'] ?? '') !== 'succeeded') {
            throw new RuntimeException('Stripe has not confirmed the new payment method yet.');
        }
        $this->handleSetupIntentSucceeded($billing, $intent);
        return array_merge($this->billingSummary($user), [
            'success' => true,
            'message' => 'Your payment method has been updated.',
        ]);
    }
    public function cancelSubscription(User $user): array
    {
        $billing = $this->refreshBilling($user);
        if (blank($billing->stripe_subscription_id)) throw new RuntimeException('No active Stripe subscription was found.');
        $subscription = $this->post('/v1/subscriptions/' . rawurlencode((string) $billing->stripe_subscription_id), ['cancel_at_period_end' => 'true']);
        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        data_set($meta,'stripe.cancel_at_period_end',true);
        data_set($meta,'stripe.cancellation_requested_at',now()->toIso8601String());
        $billing->forceFill(['registration_meta'=>$meta,'subscription_status'=>(string)($subscription['status'] ?? $billing->subscription_status)])->save();
        return array_merge($this->billingSummary($user), ['success'=>true,'message'=>'Your My Journey subscription will cancel at the end of the current billing period. Your purchased credit points do not expire.']);
    }
    public function resumeSubscription(User $user): array
    {
        $billing = $this->refreshBilling($user);
        if (blank($billing->stripe_subscription_id)) throw new RuntimeException('No Stripe subscription was found.');
        $subscription = $this->post('/v1/subscriptions/' . rawurlencode((string) $billing->stripe_subscription_id), ['cancel_at_period_end' => 'false']);
        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        data_set($meta,'stripe.cancel_at_period_end',false);
        data_forget($meta,'stripe.cancellation_requested_at');
        $billing->forceFill(['registration_meta'=>$meta,'subscription_status'=>(string)($subscription['status'] ?? $billing->subscription_status)])->save();
        return array_merge($this->billingSummary($user), ['success'=>true,'message'=>'Your My Journey subscription will continue normally.']);
    }
    public function updateCustomerProfile(User $user, BillingInformation $billing): void
    {
        if (blank($billing->stripe_customer_id)) return;
        $this->post('/v1/customers/' . rawurlencode((string) $billing->stripe_customer_id), [
            'name' => $billing->billing_name,
            'email' => $billing->billing_email,
            'phone' => $billing->billing_phone,
            'address' => [
                'line1' => $billing->billing_address_1,
                'line2' => $billing->billing_address_2,
                'city' => $billing->billing_city,
                'state' => $billing->billing_state,
                'postal_code' => $billing->billing_postal_code,
                'country' => strtoupper((string) ($billing->billing_country ?: 'US')),
            ],
        ]);
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
            if ($type === 'setup_intent.succeeded') {
                $this->handleSetupIntentSucceeded($billing, $object);
            } elseif ($type === 'payment_intent.succeeded') {
                $this->handleOneTimePaymentSucceeded($billing, $object);
            } elseif ($type === 'payment_intent.payment_failed') {
                $billing->forceFill(['payment_status' => 'failed', 'stripe_synced_at' => now(), 'payment_synced_at' => now()])->save();
                $this->persistStripePayment($billing, $object, 'failed');
            } elseif (str_starts_with($type, 'customer.subscription.')) {
                $this->syncSubscription($billing, $object);
                if (in_array($type, ['customer.subscription.deleted'], true)) {
                    $this->downgradeAfterSubscriptionEnd($billing);
                }
            } elseif (in_array($type, ['invoice.paid', 'invoice.payment_succeeded'], true)) {
                $subscriptionId = $this->subscriptionIdFromStripeObject($object)
                    ?: (string) $billing->stripe_subscription_id;
                if ($subscriptionId !== '') {
                    $subscription = $this->retrieveSubscription($subscriptionId);
                    $this->syncSubscription($billing, $subscription, $object);
                    $this->persistStripeInvoice($billing->fresh(), $object);
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
    protected function handleOneTimePaymentSucceeded(BillingInformation $billing, array $intent): void
    {
        $plan = (string) data_get($intent, 'metadata.plyrcard_registration_plan', '');
        $billing->forceFill([
            'payment_provider' => 'stripe',
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => $intent['id'] ?? $billing->stripe_payment_intent_id,
            'amount_paid_cents' => (int) ($intent['amount_received'] ?? $intent['amount'] ?? $billing->amount_paid_cents),
            'payment_mode' => ! empty($intent['livemode']) ? 'live' : 'test',
            'payment_live_mode' => (bool) ($intent['livemode'] ?? false),
            'stripe_synced_at' => now(),
            'payment_synced_at' => now(),
        ])->save();
        if (in_array($plan, ['jumpstart','amplify'], true)) {
            $this->activateServiceEntitlement($billing->fresh(), $plan, (string) ($intent['id'] ?? ''));
        }
        $this->persistStripePayment($billing->fresh(), $intent, 'succeeded');
        $this->syncPaymentMethodMetadata($billing->fresh());
    }
    protected function handleSetupIntentSucceeded(BillingInformation $billing, array $intent): void
    {
        $paymentMethodId = $this->idValue($intent['payment_method'] ?? null);
        if (! $paymentMethodId) {
            return;
        }

        if (filled($billing->stripe_subscription_id)) {
            $this->post('/v1/subscriptions/' . rawurlencode((string) $billing->stripe_subscription_id), [
                'default_payment_method' => $paymentMethodId,
            ]);
        }
        if (filled($billing->stripe_customer_id)) {
            $this->post('/v1/customers/' . rawurlencode((string) $billing->stripe_customer_id), [
                'invoice_settings' => ['default_payment_method' => $paymentMethodId],
            ]);
        }

        $this->syncPaymentMethodById($billing, $paymentMethodId);
    }
    protected function activateServiceEntitlement(BillingInformation $billing, string $plan, string $sourceId): void
    {
        $user = $billing->user;
        $plan = strtolower(trim($plan));
        $sourceId = trim($sourceId);
        if (! $user || ! in_array($plan, ['jumpstart', 'amplify'], true) || $sourceId === '') {
            return;
        }

        // Credits and the role are one entitlement. Commit them together so we can
        // never end up with the add-on role present while the credit grant failed.
        DB::transaction(function () use ($billing, $user, $plan, $sourceId): void {
            app(CreditPointService::class)->grantPackage(
                $user,
                $plan,
                $sourceId,
                ['stripe_billing_id' => $billing->getKey()]
            );

            $role = $plan === 'jumpstart' ? 'Jumpstart' : 'Amplify';
            if (method_exists($user, 'assignRole') && ! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        });
    }
    protected function downgradeAfterSubscriptionEnd(BillingInformation $billing): void
    {
        $user = $billing->user;
        if (! $user || ! method_exists($user,'syncRoles')) return;
        $roles = $user->getRoleNames()->reject(fn ($role) => in_array(strtolower(trim((string)$role)), ['my journey','my-journey','my_journey','free'], true))->push('Free')->unique()->values()->all();
        $user->syncRoles($roles);
        $billing->forceFill(['plan_key'=>'free','subscription_status'=>'canceled'])->save();
    }
    protected function syncRecentStripeHistory(BillingInformation $billing): void
    {
        $customerId = trim((string) $billing->stripe_customer_id);
        if ($customerId === '') return;
        $invoices = $this->get('/v1/invoices', ['customer'=>$customerId,'limit'=>20]);
        foreach ((array) ($invoices['data'] ?? []) as $invoice) {
            if (is_array($invoice) && in_array((string) ($invoice['status'] ?? ''), ['paid','open','uncollectible','void'], true)) {
                $this->persistStripeInvoice($billing, $invoice);
            }
        }
        $intents = $this->get('/v1/payment_intents', ['customer'=>$customerId,'limit'=>20]);
        foreach ((array) ($intents['data'] ?? []) as $intent) {
            if (! is_array($intent)) continue;
            $source = (string) data_get($intent, 'metadata.plyrcard_source', '');
            $chargeType = (string) data_get($intent, 'metadata.plyrcard_charge_type', '');
            if ($source === 'authenticated_upgrade' || $chargeType === 'one_time_credit_package') {
                $this->persistStripePayment($billing, $intent, (string) ($intent['status'] ?? 'unknown'));
            }
        }
    }
    protected function persistStripeInvoice(BillingInformation $billing, array $invoice): void
    {
        if (! class_exists(PaymentTransaction::class) || ! Schema::hasTable('payment_transactions') || empty($invoice['id'])) return;
        PaymentTransaction::query()->updateOrCreate(['stripe_invoice_id'=>(string)$invoice['id']], [
            'user_id'=>$billing->user_id,'billing_information_id'=>$billing->getKey(),'plan_key'=>$billing->plan_key,
            'stripe_payment_intent_id'=>$this->paymentIntentId($invoice), 'stripe_subscription_id'=>$this->subscriptionIdFromStripeObject($invoice), 'stripe_customer_id'=>$this->idValue($invoice['customer'] ?? null),
            'status'=>$invoice['status'] ?? 'paid','currency'=>strtoupper((string)($invoice['currency'] ?? 'USD')),'amount_cents'=>(int)($invoice['amount_paid'] ?? $invoice['total'] ?? 0),
            'payment_provider'=>'stripe','payment_mode'=>!empty($invoice['livemode'])?'live':'test','live_mode'=>(bool)($invoice['livemode'] ?? false),
            'source_type'=>'invoice','source_name'=>(($invoice['billing_reason'] ?? '') === 'subscription_create' && in_array((string) data_get($billing->registration_meta ?? [], 'stripe.checkout_plan_key'), ['jumpstart','amplify'], true)) ? ucfirst((string) data_get($billing->registration_meta ?? [], 'stripe.checkout_plan_key')) . ' + My Journey' : 'My Journey subscription','paid_at'=>isset($invoice['status_transitions']['paid_at']) ? date('Y-m-d H:i:s',(int)$invoice['status_transitions']['paid_at']) : now(), 'synced_at'=>now(),'stripe_payload'=>$invoice,
        ]);
    }
    protected function persistStripePayment(BillingInformation $billing, array $intent, string $status): void
    {
        if (! class_exists(PaymentTransaction::class) || ! Schema::hasTable('payment_transactions') || empty($intent['id'])) return;
        $plan=(string)data_get($intent,'metadata.plyrcard_registration_plan','');
        PaymentTransaction::query()->updateOrCreate(['stripe_payment_intent_id'=>(string)$intent['id']], [
            'user_id'=>$billing->user_id,'billing_information_id'=>$billing->getKey(),'plan_key'=>$billing->plan_key,
            'stripe_customer_id'=>$this->idValue($intent['customer'] ?? null),'status'=>$status,'currency'=>strtoupper((string)($intent['currency'] ?? 'USD')),
            'amount_cents'=>(int)($intent['amount_received'] ?? $intent['amount'] ?? 0),'payment_provider'=>'stripe','payment_mode'=>!empty($intent['livemode'])?'live':'test','live_mode'=>(bool)($intent['livemode'] ?? false),
            'source_type'=>'one_time_purchase','source_sub_type'=>$plan,'source_name'=>$plan ? ucfirst($plan) . ' credit package' : 'One-time purchase','paid_at'=>$status==='succeeded'?now():null,'synced_at'=>now(),'stripe_payload'=>$intent,
        ]);
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
        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        data_set($meta, 'stripe.cancel_at_period_end', (bool) ($subscription['cancel_at_period_end'] ?? false));
        data_set($meta, 'stripe.current_period_end', isset($subscription['current_period_end']) ? (int) $subscription['current_period_end'] : null);
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
            'registration_meta' => $meta,
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
            if (method_exists($user, 'removeRole') && $user->hasRole('Free')) {
                $user->removeRole('Free');
            }
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
            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->reject(fn ($role) => strcasecmp(trim((string) $role), 'Free') === 0)->all() : [];
            $roles[] = 'My Journey';
            if ($checkoutPlan === 'jumpstart') {
                $roles[] = 'Jumpstart';
            }
            if ($checkoutPlan === 'amplify') {
                $roles[] = 'Amplify';
            }
            $user->syncRoles(array_values(array_unique($roles)));
        }
        if (in_array($checkoutPlan, ['jumpstart', 'amplify'], true)) {
            $billingReason = is_array($invoice) ? (string) ($invoice['billing_reason'] ?? '') : '';
            $isInitialPackageInvoice = $billingReason === '' || $billingReason === 'subscription_create';
            $sourceId = is_array($invoice) ? (string) ($invoice['id'] ?? $billing->stripe_invoice_id ?? '') : (string) ($billing->stripe_invoice_id ?? '');
            if ($isInitialPackageInvoice && $sourceId !== '') {
                $this->activateServiceEntitlement($billing, $checkoutPlan, $sourceId);
            }
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
    protected function syncSubscriptionDefaultPaymentMethod(BillingInformation $billing, array $subscription): void
    {
        $paymentMethodId = $this->idValue($subscription['default_payment_method'] ?? null);
        if (! $paymentMethodId) {
            $paymentMethodId = $this->resolveSavedPaymentMethodId($billing);
        }
        if ($paymentMethodId) {
            $this->syncPaymentMethodById($billing, $paymentMethodId);
        }
    }

    protected function syncPaymentMethodById(BillingInformation $billing, string $paymentMethodId): void
    {
        $paymentMethodId = trim($paymentMethodId);
        if ($paymentMethodId === '') {
            return;
        }

        $paymentMethod = $this->get('/v1/payment_methods/' . rawurlencode($paymentMethodId));
        $card = (array) ($paymentMethod['card'] ?? []);
        $billing->forceFill([
            'stripe_payment_method_id' => $paymentMethodId,
            'cardholder_name' => data_get($paymentMethod, 'billing_details.name') ?: $billing->cardholder_name,
            'card_last_four' => $card['last4'] ?? $billing->card_last_four,
            'card_expiration' => isset($card['exp_month'], $card['exp_year'])
                ? sprintf('%02d/%d', (int) $card['exp_month'], (int) $card['exp_year'])
                : $billing->card_expiration,
            'payment_brand' => $card['brand'] ?? $billing->payment_brand,
            'payment_type' => $paymentMethod['type'] ?? $billing->payment_type,
            'stripe_synced_at' => now(),
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
            $paymentMethodId = $this->idValue($paymentMethod);
            if ($paymentMethodId) {
                $this->syncPaymentMethodById($billing, $paymentMethodId);
            }
        } catch (\Throwable $exception) {
            Log::info('Stripe payment method metadata could not be refreshed.', [
                'billing_id' => $billing->getKey(),
                'payment_intent_id' => $intentId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
    protected function resolveSavedPaymentMethodId(BillingInformation $billing): ?string
    {
        $paymentMethodId = trim((string) $billing->stripe_payment_method_id);
        if ($paymentMethodId !== '') {
            return $paymentMethodId;
        }
        if (filled($billing->stripe_subscription_id)) {
            try {
                $subscription = $this->retrieveSubscription((string) $billing->stripe_subscription_id);
                $paymentMethodId = trim((string) $this->idValue($subscription['default_payment_method'] ?? null));
                if ($paymentMethodId !== '') {
                    $billing->forceFill(['stripe_payment_method_id' => $paymentMethodId])->save();
                    $this->syncPaymentMethodById($billing->fresh(), $paymentMethodId);
                    return $paymentMethodId;
                }
            } catch (\Throwable) {
            }
        }
        if (filled($billing->stripe_customer_id)) {
            try {
                $customer = $this->get('/v1/customers/' . rawurlencode((string) $billing->stripe_customer_id), [
                    'expand' => ['invoice_settings.default_payment_method'],
                ]);
                $paymentMethodId = trim((string) $this->idValue(data_get($customer, 'invoice_settings.default_payment_method')));
                if ($paymentMethodId === '') {
                    $methods = $this->get('/v1/payment_methods', [
                        'customer' => (string) $billing->stripe_customer_id,
                        'type' => 'card',
                        'limit' => 1,
                    ]);
                    $paymentMethodId = trim((string) $this->idValue(data_get($methods, 'data.0')));
                }
                if ($paymentMethodId !== '') {
                    $billing->forceFill(['stripe_payment_method_id' => $paymentMethodId])->save();
                    $this->syncPaymentMethodById($billing->fresh(), $paymentMethodId);
                    return $paymentMethodId;
                }
            } catch (\Throwable) {
            }
        }
        return null;
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
        $secret = data_get($invoice, 'payment_intent.client_secret')
            ?: data_get($invoice, 'confirmation_secret.client_secret');
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
            'saved_card' => $this->savedCardPayload($billing),
        ];
    }

    protected function savedCardPayload(BillingInformation $billing): ?array
    {
        if (blank($billing->card_last_four)) {
            return null;
        }

        return [
            'brand' => strtolower((string) ($billing->payment_brand ?: 'card')),
            'last_four' => (string) $billing->card_last_four,
            'expiration' => (string) ($billing->card_expiration ?: ''),
            'cardholder_name' => (string) ($billing->cardholder_name ?: ''),
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