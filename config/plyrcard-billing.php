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
];
