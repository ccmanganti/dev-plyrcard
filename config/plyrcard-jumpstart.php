<?php

return [
    'price_cents' => (int) env('PLYRCARD_JUMPSTART_PRICE_CENTS', 14900),
    'currency' => 'USD',
    'payment_form_url' => env(
        'PLYRCARD_JUMPSTART_CHECKOUT_URL',
        'https://systems.plyrcard.com/widget/survey/CXioZTT8ncW1xtwZuLVt?notrack=true'
    ),
];
