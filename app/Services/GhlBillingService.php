<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhlBillingService
{
    public function __construct(
        protected GoHighLevelService $ghl,
    ) {
    }

    public function createInitialRegistrationInvoice(User $user, BillingInformation $billing): array
    {
        $locationId = trim((string) ($billing->ghl_location_id ?: config('ghl.location_id')));
        $token = $this->ghl->tokenForLocation($locationId ?: null);

        if ($locationId === '' || ! $token || blank($billing->ghl_contact_id)) {
            return $this->failure('Cannot create HighLevel invoice without a location, token, and GHL contact ID.');
        }

        $items = $this->initialItems($billing);

        if ($items === []) {
            return $this->failure('This registration does not require an initial invoice.');
        }

        $payload = [
            'altId' => $locationId,
            'altType' => 'location',
            'name' => $this->planLabel($billing->plan_key) . ' Registration',
            'businessDetails' => $this->businessDetails(),
            'currency' => strtoupper((string) ($billing->currency ?: 'USD')),
            'items' => $items,
            'discount' => [
                'value' => 0,
                'type' => 'percentage',
            ],
            'termsNotes' => '<p>PLYRCARD registration payment. Your paid access activates after payment is confirmed.</p>',
            'title' => 'PLYRCARD REGISTRATION',
            'contactDetails' => $this->contactDetails($user, $billing),
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->toDateString(),
            'sentTo' => [
                'email' => array_values(array_filter([$billing->billing_email ?: $user->email])),
                'phoneNo' => array_values(array_filter([$billing->billing_phone ?: $user->phone])),
            ],
            'liveMode' => (bool) config('plyrcard-registration.ghl.live_mode', true),
            'automaticTaxesEnabled' => false,
        ];

        try {
            $response = $this->client($token, 'v3')
                ->post($this->baseUrl() . '/invoices/', $payload);
        } catch (\Throwable $e) {
            Log::error('GHL native registration invoice request failed before response.', [
                'user_id' => $user->id,
                'billing_id' => $billing->id,
                'message' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage(), ['payload' => $payload]);
        }

        $body = $this->jsonBody($response);

        if ($response->failed()) {
            Log::error('GHL native registration invoice creation failed.', [
                'user_id' => $user->id,
                'billing_id' => $billing->id,
                'status' => $response->status(),
                'body' => $body,
            ]);

            return $this->failure('HighLevel invoice creation failed.', [
                'status' => $response->status(),
                'response' => $body,
                'payload' => $payload,
            ]);
        }

        $invoiceId = $this->firstString($body, [
            '_id',
            'id',
            'invoice._id',
            'invoice.id',
            'data._id',
            'data.id',
        ]);

        if (! $invoiceId) {
            return $this->failure('HighLevel created the invoice but did not return an invoice ID.', [
                'response' => $body,
            ]);
        }

        $send = $this->sendInvoice($invoiceId, $locationId, $token);

        return [
            'success' => true,
            'invoice_id' => $invoiceId,
            'invoice_sent' => (bool) ($send['success'] ?? false),
            'invoice_response' => $body,
            'send_response' => $send,
            'payment_url' => $this->firstString($body, [
                'paymentUrl',
                'payment_url',
                'invoiceUrl',
                'invoice_url',
                'url',
                'data.paymentUrl',
                'data.invoiceUrl',
                'invoice.paymentUrl',
                'invoice.invoiceUrl',
            ]),
        ];
    }

    public function sendInvoice(string $invoiceId, string $locationId, ?string $token = null): array
    {
        $token = $token ?: $this->ghl->tokenForLocation($locationId);

        if (! $token) {
            return $this->failure('Missing HighLevel token while sending invoice.');
        }

        try {
            $response = $this->client($token, 'v3')
                ->post($this->baseUrl() . '/invoices/' . rawurlencode($invoiceId) . '/send');
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage());
        }

        $body = $this->jsonBody($response);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $body,
        ];
    }

    /**
     * Creates the $49/month invoice schedule only AFTER the first paid invoice
     * is confirmed. Without a reusable GHL paymentMethodId/customerId this
     * schedule sends recurring invoices; it does not pretend to auto-charge.
     */
    public function createRecurringSchedule(User $user, BillingInformation $billing): array
    {
        if (($billing->recurring_amount_cents ?? 0) <= 0) {
            return $this->failure('No recurring amount is configured for this plan.');
        }

        if (filled($billing->ghl_invoice_schedule_id)) {
            return [
                'success' => true,
                'schedule_id' => $billing->ghl_invoice_schedule_id,
                'already_exists' => true,
            ];
        }

        $locationId = trim((string) ($billing->ghl_location_id ?: config('ghl.location_id')));
        $token = $this->ghl->tokenForLocation($locationId ?: null);

        if ($locationId === '' || ! $token || blank($billing->ghl_contact_id)) {
            return $this->failure('Cannot create recurring HighLevel schedule without a location, token, and GHL contact ID.');
        }

        $startDate = ($billing->ghl_payment_completed_at ?: now())->copy()->addMonthNoOverflow()->toDateString();

        $payload = [
            'altId' => $locationId,
            'altType' => 'location',
            'name' => $this->planLabel($billing->plan_key) . ' Monthly',
            'contactDetails' => $this->contactDetails($user, $billing),
            'schedule' => [
                'rrule' => [
                    'intervalType' => 'monthly',
                    'interval' => 1,
                    'startDate' => $startDate,
                ],
            ],
            'businessDetails' => $this->businessDetails(),
            'currency' => strtoupper((string) ($billing->currency ?: 'USD')),
            'discount' => [
                'value' => 0,
                'type' => 'percentage',
            ],
            'items' => [[
                'name' => $this->planLabel($billing->plan_key) . ' Monthly Membership',
                'description' => 'Monthly PLYRCARD membership',
                'currency' => strtoupper((string) ($billing->currency ?: 'USD')),
                'amount' => $this->moneyFromCents((int) $billing->recurring_amount_cents),
                'qty' => 1,
                'taxes' => [],
                'type' => 'recurring',
                'taxInclusive' => false,
            ]],
            'title' => 'PLYRCARD MONTHLY',
            'termsNotes' => '<p>Monthly PLYRCARD membership. Cancel before the next billing cycle to stop future invoices.</p>',
            'liveMode' => (bool) config('plyrcard-registration.ghl.live_mode', true),
            'automaticTaxesEnabled' => false,
        ];

        $version = (string) config('plyrcard-registration.ghl.schedule_version', '2023-02-21');

        try {
            $response = $this->client($token, $version)
                ->post($this->baseUrl() . '/invoices/schedule', $payload);
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage(), ['payload' => $payload]);
        }

        $body = $this->jsonBody($response);

        if ($response->failed()) {
            return $this->failure('HighLevel recurring invoice schedule creation failed.', [
                'status' => $response->status(),
                'response' => $body,
                'payload' => $payload,
            ]);
        }

        $scheduleId = $this->firstString($body, [
            '_id',
            'id',
            'schedule._id',
            'schedule.id',
            'data._id',
            'data.id',
        ]);

        if (! $scheduleId) {
            return $this->failure('HighLevel created a schedule but did not return its ID.', ['response' => $body]);
        }

        $scheduleActivation = $this->activateSchedule($scheduleId, $billing, $locationId, $token, $version);

        return [
            'success' => (bool) ($scheduleActivation['success'] ?? false),
            'schedule_id' => $scheduleId,
            'create_response' => $body,
            'schedule_response' => $scheduleActivation,
        ];
    }

    protected function activateSchedule(
        string $scheduleId,
        BillingInformation $billing,
        string $locationId,
        string $token,
        string $version,
    ): array {
        $payload = [
            'altId' => $locationId,
            'altType' => 'location',
            'liveMode' => (bool) config('plyrcard-registration.ghl.live_mode', true),
        ];

        // Only request automatic charging when GHL payment method identifiers
        // have actually been captured from a supported payment-provider flow.
        if (filled($billing->ghl_payment_method_id) && filled($billing->ghl_customer_id)) {
            $payload['autoPayment'] = [
                'enable' => true,
                // The registration starts as an invoice flow, so payment_type may
                // contain "invoice". HighLevel auto-payment expects the saved method's
                // provider type; default to card when reusable card identifiers exist.
                'type' => in_array($billing->payment_type, ['card'], true) ? $billing->payment_type : 'card',
                'paymentMethodId' => $billing->ghl_payment_method_id,
                'customerId' => $billing->ghl_customer_id,
                'card' => [
                    'brand' => $billing->payment_brand,
                    'last4' => $billing->card_last_four,
                ],
            ];
        }

        try {
            $response = $this->client($token, $version)
                ->post($this->baseUrl() . '/invoices/schedule/' . rawurlencode($scheduleId) . '/schedule', $payload);
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage());
        }

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $this->jsonBody($response),
            'auto_payment_requested' => isset($payload['autoPayment']),
        ];
    }

    protected function initialItems(BillingInformation $billing): array
    {
        $currency = strtoupper((string) ($billing->currency ?: 'USD'));
        $items = [];

        if (($billing->recurring_amount_cents ?? 0) > 0) {
            $items[] = [
                'name' => $this->planLabel($billing->plan_key) . ' - First Month',
                'description' => 'First month of PLYRCARD membership',
                'currency' => $currency,
                'amount' => $this->moneyFromCents((int) $billing->recurring_amount_cents),
                'qty' => 1,
                'taxes' => [],
                'type' => 'one_time',
                'taxInclusive' => false,
            ];
        }

        if (($billing->setup_fee_cents ?? 0) > 0) {
            $items[] = [
                'name' => $this->planLabel($billing->plan_key) . ' Setup Fee',
                'description' => 'One-time onboarding and production setup',
                'currency' => $currency,
                'amount' => $this->moneyFromCents((int) $billing->setup_fee_cents),
                'qty' => 1,
                'taxes' => [],
                'type' => 'one_time',
                'taxInclusive' => false,
            ];
        }

        return $items;
    }

    protected function contactDetails(User $user, BillingInformation $billing): array
    {
        $email = trim((string) ($billing->billing_email ?: $user->email ?: $user->personal_email));
        $phone = trim((string) ($billing->billing_phone ?: $user->phone));
        $name = trim((string) ($billing->billing_name ?: ($user->first_name . ' ' . $user->last_name)));

        $details = [
            'id' => (string) $billing->ghl_contact_id,
            'name' => $name,
            'phoneNo' => $phone,
            'email' => $email,
            'additionalEmails' => [],
            'customFields' => [],
            'address' => array_filter([
                'addressLine1' => $billing->billing_address_1,
                'addressLine2' => $billing->billing_address_2,
                'city' => $billing->billing_city,
                'state' => $billing->billing_state,
                'countryCode' => $this->countryCode($billing->billing_country),
                'postalCode' => $billing->billing_postal_code,
            ], fn ($value) => filled($value)),
        ];

        if (filled($billing->billing_company)) {
            $details['companyName'] = $billing->billing_company;
        }

        return $details;
    }

    protected function businessDetails(): array
    {
        return array_filter([
            'name' => (string) config('plyrcard-registration.business.name', 'PLYRCARD'),
            'phoneNo' => config('plyrcard-registration.business.phone'),
            'website' => config('plyrcard-registration.business.website', config('app.url')),
            'logoUrl' => config('plyrcard-registration.business.logo_url'),
            'address' => array_filter([
                'addressLine1' => config('plyrcard-registration.business.address_1'),
                'addressLine2' => config('plyrcard-registration.business.address_2'),
                'city' => config('plyrcard-registration.business.city'),
                'state' => config('plyrcard-registration.business.state'),
                'countryCode' => config('plyrcard-registration.business.country_code', 'US'),
                'postalCode' => config('plyrcard-registration.business.postal_code'),
            ], fn ($value) => filled($value)),
        ], fn ($value) => is_array($value) ? $value !== [] : filled($value));
    }

    protected function client(string $token, string $version): PendingRequest
    {
        return Http::withHeaders(['Version' => $version])
            ->connectTimeout((int) config('plyrcard-registration.ghl.connect_timeout', 5))
            ->timeout((int) config('plyrcard-registration.ghl.timeout', 20))
            ->retry(1, 300, throw: false)
            ->withToken($token)
            ->acceptJson()
            ->asJson();
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');
    }

    protected function moneyFromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    protected function countryCode(?string $country): string
    {
        $country = strtoupper(trim((string) $country));

        return match ($country) {
            '', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA' => 'US',
            default => strlen($country) === 2 ? $country : $country,
        };
    }

    protected function planLabel(?string $planKey): string
    {
        return match ($planKey) {
            'my-journey' => 'My Journey',
            'amplify' => 'Amplify',
            default => 'PLYRCARD',
        };
    }

    protected function firstString(array $body, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($body, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function jsonBody($response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : ['raw' => $response->body()];
    }

    protected function failure(string $message, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'message' => $message,
        ], $extra);
    }
}
