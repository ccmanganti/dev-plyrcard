<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Native Registration Plans
    |--------------------------------------------------------------------------
    |
    | Amounts are stored in cents so Laravel and GHL billing use integers.
    | Paid registrations intentionally receive Free access until a verified
    | GHL InvoicePaid webhook confirms the first payment.
    |
    */
    'plans' => [
        'free' => [
            'label' => 'Free',
            'recurring_amount_cents' => 0,
            'setup_fee_cents' => 0,
            'charge_first_month_upfront' => false,
            'role_after_registration' => 'Free',
            'role_after_payment' => 'Free',
        ],

        'my-journey' => [
            'label' => 'My Journey',
            'recurring_amount_cents' => 4900,
            'setup_fee_cents' => 0,
            'charge_first_month_upfront' => true,
            'role_after_registration' => 'Free',
            'role_after_payment' => 'My Journey',
        ],

        'amplify' => [
            'label' => 'Amplify',
            'recurring_amount_cents' => 4900,
            'setup_fee_cents' => 50000,
            'charge_first_month_upfront' => false,
            'role_after_registration' => 'Free',
            'role_after_payment' => 'My Journey',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Availability (RDAP)
    |--------------------------------------------------------------------------
    |
    | PLYRCARD resolves the authoritative RDAP service for each TLD using the
    | IANA bootstrap registry, then queries the registry server-side. No API key
    | is required. A 404 means RDAP has no current registration record; final
    | registrar purchasability is still checked during provisioning.
    |
    */
    'domain_lookup' => [
        'bootstrap_url' => env('RDAP_BOOTSTRAP_URL', 'https://data.iana.org/rdap/dns.json'),
        'bootstrap_cache_hours' => (int) env('RDAP_BOOTSTRAP_CACHE_HOURS', 24),
        'result_cache_minutes' => (int) env('RDAP_RESULT_CACHE_MINUTES', 10),
        'connect_timeout' => (int) env('RDAP_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('RDAP_TIMEOUT', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | HighLevel Billing
    |--------------------------------------------------------------------------
    */
    'ghl' => [
        'live_mode' => env('GHL_LIVE_MODE', true),
        'schedule_version' => env('GHL_INVOICE_SCHEDULE_VERSION', '2023-02-21'),
        'connect_timeout' => (int) env('GHL_BILLING_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('GHL_BILLING_TIMEOUT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Business Details
    |--------------------------------------------------------------------------
    |
    | These values are safe defaults. Override them in .env if your GHL invoice
    | template/API setup requires different business information.
    |
    */
    'business' => [
        'name' => env('PLYRCARD_BUSINESS_NAME', 'PLYRCARD'),
        'phone' => env('PLYRCARD_BUSINESS_PHONE', '+1 571-888-0852'),
        'website' => env('PLYRCARD_BUSINESS_WEBSITE', env('APP_URL')),
        'logo_url' => env('PLYRCARD_BUSINESS_LOGO_URL'),
        'address_1' => env('PLYRCARD_BUSINESS_ADDRESS_1'),
        'address_2' => env('PLYRCARD_BUSINESS_ADDRESS_2'),
        'city' => env('PLYRCARD_BUSINESS_CITY'),
        'state' => env('PLYRCARD_BUSINESS_STATE'),
        'postal_code' => env('PLYRCARD_BUSINESS_POSTAL_CODE'),
        'country_code' => env('PLYRCARD_BUSINESS_COUNTRY_CODE', 'US'),
    ],
];