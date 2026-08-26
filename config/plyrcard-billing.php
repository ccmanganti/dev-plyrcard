<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Secure payment method update URL
    |--------------------------------------------------------------------------
    |
    | This must point to a provider-hosted / PCI-compliant card-on-file flow.
    | PLYRCARD must never collect a full card number or CVC in Laravel.
    |
    | Supported placeholders:
    | {contact_id} {customer_id} {payment_method_id} {subscription_id} {email} {user_id} {return_url}
    |
    */
    'payment_method_update_url' => env('PLYRCARD_PAYMENT_METHOD_UPDATE_URL'),

    // Secure Amplify checkout used by both Locker Room and Recruiting Center.
    'amplify_checkout_url' => env(
        'PLYRCARD_AMPLIFY_CHECKOUT_URL',
        'https://systems.plyrcard.com/widget/survey/FPx6oTagczUr0jH1X0ES'
    ),
];