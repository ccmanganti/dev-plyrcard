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
    | {contact_id} {customer_id} {email} {user_id} {return_url}
    |
    */
    'payment_method_update_url' => env('PLYRCARD_PAYMENT_METHOD_UPDATE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Amplify checkout surveys
    |--------------------------------------------------------------------------
    |
    | Registration/new enrollment: $500 setup + $49 first monthly payment.
    | Existing My Journey upgrade: $500 setup only; the existing $49/mo
    | My Journey subscription remains in place.
    |
    */
    'amplify_registration_url' => env(
        'PLYRCARD_AMPLIFY_REGISTRATION_URL',
        'https://systems.plyrcard.com/widget/survey/FPx6oTagczUr0jH1X0ES'
    ),

    'amplify_my_journey_upgrade_url' => env(
        'PLYRCARD_AMPLIFY_MY_JOURNEY_UPGRADE_URL',
        'https://systems.plyrcard.com/widget/survey/xmVLm5DhFeIqSNCfUAO0'
    ),

];