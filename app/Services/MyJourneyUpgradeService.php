<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MyJourneyUpgradeService
{
    public function __construct(
        protected BillingAccountService $billingAccounts,
        protected BillingProfileService $billingProfiles,
        protected RegistrationPaymentVerificationService $paymentVerification,
        protected SupportAlertService $alerts,
    ) {
    }

    public function start(User $user): array
    {
        $user->loadMissing('roles');

        if ($this->hasMyJourney($user)) {
            return [
                'completed' => true,
                'message' => 'My Journey is already active on this account.',
            ];
        }

        $billing = $this->billingProfiles->get($user);
        if (! $this->billingProfiles->isComplete($billing)) {
            return array_merge([
                'success' => false,
                'completed' => false,
                'error' => true,
                'reason' => 'billing_profile_required',
                'message' => 'Complete your billing information to continue with secure checkout.',
            ], $this->billingProfiles->requirementPayload($user, $billing));
        }

        $plan = $this->plan();
        $recurring = (int) ($plan['recurring_amount_cents'] ?? 4900);
        $setup = (int) ($plan['setup_fee_cents'] ?? 0);
        $initial = $setup + ((bool) ($plan['charge_first_month_upfront'] ?? true) ? $recurring : 0);
        $checkoutId = (string) Str::uuid();

        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        $meta['my_journey_upgrade'] = [
            'checkout_id' => $checkoutId,
            'started_at' => now()->toIso8601String(),
            'expected_amount_cents' => $initial,
            'source' => 'authenticated_account_upgrade',
            'roles_before_checkout' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [],
        ];

        $billing->forceFill([
            'billing_name' => $billing->billing_name ?: trim((string) $user->first_name . ' ' . (string) $user->last_name),
            'billing_email' => $billing->billing_email ?: ($user->email ?: $user->personal_email),
            'billing_phone' => $billing->billing_phone ?: $user->phone,
            'billing_address_1' => $billing->billing_address_1 ?: ($user->street ?? null),
            'billing_city' => $billing->billing_city ?: $user->city,
            'billing_state' => $billing->billing_state ?: $user->state,
            'billing_country' => $billing->billing_country ?: ($user->country ?: 'US'),
            'plan_key' => 'my-journey',
            'billing_cycle' => 'monthly',
            'currency' => strtoupper((string) ($plan['currency'] ?? $billing->currency ?? 'USD')),
            'recurring_amount_cents' => $recurring,
            'setup_fee_cents' => $setup,
            'initial_amount_cents' => $initial,
            'payment_status' => 'payment_form_ready',
            'subscription_status' => 'pending',
            'payment_provider' => 'ghl_survey',
            'payment_type' => 'card',
            'ghl_location_id' => $billing->ghl_location_id ?: config('ghl.location_id'),
            'ghl_sync_status' => 'my_journey_upgrade_checkout_ready',
            'registration_meta' => $meta,
        ])->save();

        // Use the same payer/subscriber identity architecture as paid registration.
        // This populates users.ghl_subscriber_contact_id and mirrors the reference
        // to BillingInformation without ever storing raw card details in Laravel.
        $subscriberContactId = $this->billingAccounts->ensureBillingContact($user, $billing);
        $billing->refresh();
        $user->refresh();

        if (! $subscriberContactId) {
            $billing->forceFill([
                'ghl_sync_status' => 'my_journey_upgrade_contact_error',
            ])->save();

            return array_merge([
                'success' => false,
                'completed' => false,
                'error' => true,
                'reason' => 'billing_contact_unavailable',
                'message' => 'Your billing information was saved, but the billing contact could not be connected yet. Please review it and try again.',
            ], $this->billingProfiles->requirementPayload($user, $billing));
        }

        $checkoutUrl = $this->checkoutUrl($user, $billing, $subscriberContactId, $plan);

        if (! $checkoutUrl) {
            return [
                'completed' => false,
                'error' => true,
                'message' => 'The My Journey checkout is currently unavailable.',
            ];
        }

        return [
            'completed' => false,
            'checkout_id' => $checkoutId,
            'checkout_url' => $checkoutUrl,
            'expected_amount_cents' => $initial,
            'message' => 'Complete the ' . $this->money($initial) . ' My Journey checkout below. Payment confirmation is checked automatically.',
        ];
    }

    public function status(User $user): array
    {
        $user->loadMissing('roles');

        if ($this->hasMyJourney($user)) {
            return [
                'completed' => true,
                'message' => 'My Journey is active.',
            ];
        }

        $billing = BillingInformation::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->first();

        if (! $billing || $billing->plan_key !== 'my-journey') {
            return [
                'completed' => false,
                'message' => 'Waiting for My Journey checkout to start.',
            ];
        }

        $rolesBefore = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->values()
            : collect();

        try {
            $verification = $this->paymentVerification->verify($user, $billing);
            $billing->refresh();
            $user->refresh();
        } catch (\Throwable $exception) {
            Log::warning('My Journey upgrade payment is not verified yet.', [
                'user_id' => $user->getKey(),
                'billing_id' => $billing->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return [
                'completed' => false,
                'message' => 'Still checking payment confirmation…',
            ];
        }

        $verified = (bool) ($verification['verified'] ?? false) || $billing->payment_status === 'paid';

        if (! $verified) {
            return [
                'completed' => false,
                'message' => 'Waiting for payment confirmation…',
                'payment_status' => $billing->payment_status,
                'subscription_status' => $billing->subscription_status,
            ];
        }

        // Some older registration verifiers use syncRoles(). Restore unrelated roles
        // and make My Journey authoritative without accidentally removing admin or
        // other application roles from an existing account.
        if (method_exists($user, 'syncRoles')) {
            $preserved = $rolesBefore
                ->reject(fn ($role) => in_array(strtolower(trim((string) $role)), ['free', 'my journey', 'my-journey'], true))
                ->push('My Journey')
                ->unique(fn ($role) => strtolower((string) $role))
                ->values()
                ->all();

            $user->syncRoles($preserved);
            $user->refresh();
        }

        $billing->forceFill([
            'plan_key' => 'my-journey',
            'billing_cycle' => 'monthly',
            'payment_status' => 'paid',
            'subscription_status' => $billing->subscription_status ?: 'active',
            'ghl_sync_status' => 'my_journey_upgrade_payment_confirmed',
            'payment_synced_at' => now(),
        ])->save();

        // Re-hydrate subscription status, payer identity, saved payment references,
        // and safe masked card metadata from the billing account after confirmation.
        try {
            $this->billingAccounts->syncSubscriberAccount($user->fresh('roles'), $billing->fresh(), true);
        } catch (\Throwable $exception) {
            Log::warning('My Journey payment was confirmed, but billing enrichment was delayed.', [
                'user_id' => $user->getKey(),
                'billing_id' => $billing->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $this->alerts->sendUpgradeCompleted($user->fresh('roles'), 'my-journey', (int) $billing->fresh()->initial_amount_cents, [
                'previous_plan' => 'free',
                'transaction_id' => $billing->fresh()->ghl_transaction_id,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('My Journey upgrade completed but the admin alert could not be sent.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        return [
            'completed' => true,
            'message' => 'Payment confirmed. My Journey is now active and your billing information has been updated.',
            'payment_status' => 'paid',
            'subscription_status' => $billing->fresh()->subscription_status,
        ];
    }

    protected function money(int $cents): string
    {
        $amount = $cents / 100;
        return '$' . (floor($amount) === $amount ? number_format($amount, 0) : number_format($amount, 2));
    }

    protected function hasMyJourney(User $user): bool
    {
        if (! method_exists($user, 'getRoleNames')) {
            return false;
        }

        return $user->getRoleNames()
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->contains(fn ($role) => in_array($role, ['my journey', 'my-journey'], true));
    }

    protected function plan(): array
    {
        return array_merge([
            'label' => 'My Journey',
            'recurring_amount_cents' => 4900,
            'setup_fee_cents' => 0,
            'charge_first_month_upfront' => true,
            'currency' => 'USD',
            'role_after_payment' => 'My Journey',
            'payment_form_url' => 'https://systems.plyrcard.com/widget/survey/82L4a2pfvspbMYWeD0zo?notrack=true',
        ], (array) config('plyrcard-registration.plans.my-journey', []));
    }

    protected function checkoutUrl(User $user, BillingInformation $billing, string $contactId, array $plan): ?string
    {
        $baseUrl = trim((string) ($plan['payment_form_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = 'https://systems.plyrcard.com/widget/survey/82L4a2pfvspbMYWeD0zo?notrack=true';
        }

        if ($baseUrl === '') {
            return null;
        }

        $billingName = trim((string) ($billing->billing_name ?: ($user->first_name . ' ' . $user->last_name)));
        $nameParts = preg_split('/\s+/', $billingName) ?: [];
        $firstName = array_shift($nameParts) ?: $user->first_name;
        $lastName = trim(implode(' ', $nameParts)) ?: $user->last_name;
        $email = $billing->billing_email ?: $user->email;
        $phone = $billing->billing_phone ?: $user->phone;

        $params = array_filter([
            'notrack' => 'true',
            'utm_plan' => 'my-journey',
            'selected_plan' => 'My Journey',
            'plan' => 'my-journey',
            'first_name' => $firstName,
            'firstName' => $firstName,
            'contact.first_name' => $firstName,
            'last_name' => $lastName,
            'lastName' => $lastName,
            'contact.last_name' => $lastName,
            'email' => $email,
            'contact.email' => $email,
            'phone' => $phone,
            'contact.phone' => $phone,
            'billing_name' => $billingName,
            'billing_email' => $email,
            'billing_phone' => $phone,
            'billing_address_1' => $billing->billing_address_1,
            'billing_address_2' => $billing->billing_address_2,
            'billing_city' => $billing->billing_city,
            'billing_state' => $billing->billing_state,
            'billing_postal_code' => $billing->billing_postal_code,
            'billing_country' => $billing->billing_country,
            'requested_domain' => $billing->requested_domain,
            'user_id' => $user->getKey(),
            'contact_id' => $contactId,
            'athlete_first_name' => $user->first_name,
            'athlete_last_name' => $user->last_name,
            'athlete_email' => $user->email,
        ], fn ($value) => filled($value));

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . http_build_query($params);
    }
}