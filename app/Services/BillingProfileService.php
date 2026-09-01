<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class BillingProfileService
{
    public function __construct(
        protected BillingAccountService $billingAccount,
    ) {
    }

    public function rules(): array
    {
        return [
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:255'],
            'billing_company' => ['nullable', 'string', 'max:255'],
            'billing_address_1' => ['required', 'string', 'max:255'],
            'billing_address_2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['required', 'string', 'max:255'],
            'billing_state' => ['required', 'string', 'max:255'],
            'billing_postal_code' => ['required', 'string', 'max:40'],
            'billing_country' => ['required', 'string', 'max:255'],
        ];
    }


    public function requiredProfileFields(): array
    {
        return [
            'billing_name',
            'billing_email',
            'billing_address_1',
            'billing_city',
            'billing_state',
            'billing_postal_code',
            'billing_country',
        ];
    }

    public function missingRequiredFields(BillingInformation $billing): array
    {
        return collect($this->requiredProfileFields())
            ->filter(fn (string $field): bool => blank($billing->{$field}))
            ->values()
            ->all();
    }

    public function isComplete(BillingInformation $billing): bool
    {
        return $this->missingRequiredFields($billing) === [];
    }

    public function requirementPayload(User $user, ?BillingInformation $billing = null): array
    {
        $billing ??= $this->get($user);

        return [
            'profile_complete' => $this->isComplete($billing),
            'missing_fields' => $this->missingRequiredFields($billing),
            'billing' => Arr::only($billing->toArray(), [
                'billing_name',
                'billing_email',
                'billing_phone',
                'billing_company',
                'billing_address_1',
                'billing_address_2',
                'billing_city',
                'billing_state',
                'billing_postal_code',
                'billing_country',
            ]),
        ];
    }

    public function update(User $user, array $input): BillingInformation
    {
        $data = Validator::make($input, $this->rules())->validate();

        $billing = BillingInformation::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            $data,
        );

        // Billing always synchronizes to PLYRCARD's own billing subaccount.
        // The athlete's User::ghl_contact_id / ghl_location_id / ghl_api_key are
        // intentionally not used or overwritten here.
        $contactId = $this->billingAccount->ensureBillingContact($user, $billing);

        $billing->forceFill([
            'ghl_location_id' => $billing->ghl_location_id ?: config('ghl.location_id'),
            'ghl_contact_id' => $contactId ?: $billing->ghl_contact_id,
            'ghl_sync_status' => $contactId ? 'synced' : 'failed',
            'ghl_synced_at' => $contactId ? now() : $billing->ghl_synced_at,
        ])->save();

        // When a subscription already exists, refresh the authoritative payer
        // contact/customer/payment-method references after every billing update.
        if (filled($billing->ghl_subscription_id)) {
            $this->billingAccount->refreshPaymentIdentity($billing);
        }

        return $billing->fresh();
    }

    public function get(User $user): BillingInformation
    {
        $billing = BillingInformation::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'billing_name' => trim((string) ($user->first_name . ' ' . $user->last_name)),
                'billing_email' => $user->email,
                'billing_phone' => $user->phone,
                'billing_address_1' => $user->street,
                'billing_city' => $user->city,
                'billing_state' => $user->state,
                'billing_country' => $user->country ?: 'US',
                'currency' => 'USD',
                'ghl_location_id' => config('ghl.location_id'),
            ],
        );

        // If a subscriber contact has been assigned, cross-reference it against
        // the billing subaccount. The service internally throttles this lookup so
        // Settings/Locker Room can stay current without hammering the API.
        if (filled($user->ghl_subscriber_contact_id)) {
            $this->billingAccount->syncSubscriberAccount($user, $billing);
            $billing->refresh();
        } else {
            // Even without a billing contact, the displayed plan must follow the
            // user's actual PLYRCARD tier role rather than stale billing metadata.
            $rolePlan = $this->billingAccount->rolePlanKey($user);
            if ($rolePlan !== null && $billing->plan_key !== $rolePlan) {
                $billing->forceFill(['plan_key' => $rolePlan])->save();
            }
        }

        return $billing;
    }

    public function formData(User $user): array
    {
        return Arr::only($this->get($user)->toArray(), [
            'billing_name',
            'billing_email',
            'billing_phone',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postal_code',
            'billing_country',
        ]);
    }

    public function refreshPaymentIdentity(User $user): BillingInformation
    {
        $billing = $this->get($user);
        $this->billingAccount->refreshPaymentIdentity($billing);
        return $billing->fresh();
    }

    public function paymentMethodUpdateUrl(User $user, ?BillingInformation $billing = null): ?string
    {
        $billing ??= $this->get($user);
        $template = trim((string) config('plyrcard-billing.payment_method_update_url'));

        if ($template === '') {
            return null;
        }

        $returnUrl = URL::temporarySignedRoute(
            'billing.payment-method.return',
            now()->addMinutes(60),
        );

        $replace = [
            '{contact_id}' => rawurlencode((string) ($user->ghl_subscriber_contact_id ?: $billing->ghl_contact_id ?: '')),
            '{customer_id}' => rawurlencode((string) ($billing->ghl_customer_id ?? '')),
            '{payment_method_id}' => rawurlencode((string) ($billing->ghl_payment_method_id ?? '')),
            '{subscription_id}' => rawurlencode((string) ($billing->ghl_subscription_id ?? '')),
            '{email}' => rawurlencode((string) ($billing->billing_email ?: $user->email)),
            '{user_id}' => rawurlencode((string) $user->getKey()),
            '{return_url}' => rawurlencode($returnUrl),
        ];

        $url = strtr($template, $replace);

        if ($url === $template) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query([
                'contact_id' => $user->ghl_subscriber_contact_id ?: $billing->ghl_contact_id,
                'customer_id' => $billing->ghl_customer_id,
                'subscription_id' => $billing->ghl_subscription_id,
                'email' => $billing->billing_email ?: $user->email,
                'return_url' => $returnUrl,
            ]);
        }

        return $url;
    }
}