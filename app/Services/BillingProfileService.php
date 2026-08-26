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
        protected GoHighLevelService $ghl,
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

    public function update(User $user, array $input): BillingInformation
    {
        $data = Validator::make($input, $this->rules())->validate();

        // Never accept PAN/CVC, payment method IDs, card brand, last four, or any
        // other provider-owned payment metadata from the browser.
        $billing = BillingInformation::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            $data,
        );

        try {
            $sync = $this->ghl->upsertContact($user, [
                'name' => $data['billing_name'],
                'email' => $data['billing_email'],
                'phone' => $data['billing_phone'] ?? $user->phone,
                'address1' => trim($data['billing_address_1'] . ' ' . ($data['billing_address_2'] ?? '')),
                'city' => $data['billing_city'],
                'state' => $data['billing_state'],
                'postalCode' => $data['billing_postal_code'],
                'country' => $data['billing_country'],
                'companyName' => $data['billing_company'] ?? null,
            ], [], 'PlyrCard Billing Information');

            $billing->forceFill([
                'ghl_contact_id' => $sync['contact_id'] ?? $billing->ghl_contact_id,
                'ghl_sync_status' => ($sync['ok'] ?? false)
                    ? 'synced'
                    : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
                'ghl_sync_response' => $sync,
                'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : $billing->ghl_synced_at,
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            $billing->forceFill([
                'ghl_sync_status' => 'failed',
                'ghl_sync_response' => [
                    'message' => 'Billing profile synchronization failed.',
                ],
            ])->save();
        }

        return $billing->fresh();
    }

    public function get(User $user): BillingInformation
    {
        return BillingInformation::query()->firstOrCreate(
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
            ],
        );
    }

    public function formData(User $user): array
    {
        $billing = $this->get($user);

        return Arr::only($billing->toArray(), [
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
            '{contact_id}' => rawurlencode((string) ($billing->ghl_contact_id ?? '')),
            '{customer_id}' => rawurlencode((string) ($billing->ghl_customer_id ?? '')),
            '{email}' => rawurlencode((string) ($billing->billing_email ?: $user->email)),
            '{user_id}' => rawurlencode((string) $user->getKey()),
            '{return_url}' => rawurlencode($returnUrl),
        ];

        $url = strtr($template, $replace);

        // If no placeholders are used, append non-sensitive context that most
        // hosted payment forms can ignore safely if unsupported.
        if ($url === $template) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query([
                'email' => $billing->billing_email ?: $user->email,
                'return_url' => $returnUrl,
            ]);
        }

        return $url;
    }
}
