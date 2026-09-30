<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Native Registration Plans
    |--------------------------------------------------------------------------
    |
    | Amounts are stored in cents. Registration now bills paid plans directly
    | through Stripe. My Journey is the recurring subscription underneath both
    | Jumpstart and Amplify. GHL payment URLs remain temporarily because the
    | authenticated upgrade screens have not been migrated yet.
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
            'stripe_product_id' => env('STRIPE_MY_JOURNEY_PRODUCT_ID', 'prod_VM4TUueP1ezl7C'),
            // Optional. If blank, PLYRCARD finds or creates a matching $49/mo Price under the product.
            'stripe_price_id' => env('STRIPE_MY_JOURNEY_PRICE_ID'),
            'payment_form_url' => env('GHL_MY_JOURNEY_PAYMENT_FORM_URL', 'https://systems.plyrcard.com/widget/survey/82L4a2pfvspbMYWeD0zo?notrack=true'),
        ],

        'jumpstart' => [
            'label' => 'Jumpstart',
            // Registration charges $149 once + starts My Journey at $49/mo.
            'recurring_amount_cents' => 4900,
            'setup_fee_cents' => 14900,
            'charge_first_month_upfront' => true,
            'role_after_registration' => 'Free',
            'role_after_payment' => 'My Journey',
            'stripe_product_id' => env('STRIPE_JUMPSTART_PRODUCT_ID', 'prod_VM4UB8sqGHo4b2'),
            // Optional. If blank, PLYRCARD finds or creates a matching $149 one-time Price.
            'stripe_price_id' => env('STRIPE_JUMPSTART_PRICE_ID'),
            'payment_form_url' => env('GHL_JUMPSTART_PAYMENT_FORM_URL', 'https://systems.plyrcard.com/widget/survey/KmE9cOWtXltjhFEPw27w?notrack=true'),
            'my_journey_upgrade_form_url' => env('GHL_JUMPSTART_MY_JOURNEY_UPGRADE_FORM_URL', 'https://systems.plyrcard.com/widget/survey/CXioZTT8ncW1xtwZuLVt?notrack=true'),
        ],

        'amplify' => [
            'label' => 'Amplify',
            // Registration charges $500 once + starts My Journey at $49/mo.
            'recurring_amount_cents' => 4900,
            'setup_fee_cents' => 50000,
            'charge_first_month_upfront' => true,
            'role_after_registration' => 'Free',
            'role_after_payment' => 'My Journey',
            'stripe_product_id' => env('STRIPE_AMPLIFY_PRODUCT_ID', 'prod_VM4UnjyEp0h0LY'),
            // Optional. If blank, PLYRCARD finds or creates a matching $500 one-time Price.
            'stripe_price_id' => env('STRIPE_AMPLIFY_PRICE_ID'),
            'payment_form_url' => env('GHL_AMPLIFY_PAYMENT_FORM_URL', 'https://systems.plyrcard.com/widget/survey/FPx6oTagczUr0jH1X0ES?notrack=true'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Availability (RDAP)
    |--------------------------------------------------------------------------
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
    | HighLevel Billing (legacy authenticated upgrade flow)
    |--------------------------------------------------------------------------
    |
    | Registration no longer uses these payment verification settings. Keep
    | them until My Journey / Jumpstart / Amplify authenticated upgrades are
    | migrated in the next billing phase.
    |
    */
    'ghl' => [
        'live_mode' => env('GHL_LIVE_MODE', true),
        'schedule_version' => env('GHL_INVOICE_SCHEDULE_VERSION', '2023-02-21'),
        'connect_timeout' => (int) env('GHL_BILLING_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('GHL_BILLING_TIMEOUT', 20),
        'payment_verification' => [
            'enabled' => env('GHL_REGISTRATION_PAYMENT_VERIFICATION', true),
            'api_version' => env('GHL_PAYMENTS_API_VERSION', 'v3'),
            'timeout' => (int) env('GHL_PAYMENT_VERIFY_TIMEOUT', 8),
            'limit' => (int) env('GHL_PAYMENT_VERIFY_LIMIT', 50),
            'window_minutes' => (int) env('GHL_PAYMENT_VERIFY_WINDOW_MINUTES', 90),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Business Details
    |--------------------------------------------------------------------------
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