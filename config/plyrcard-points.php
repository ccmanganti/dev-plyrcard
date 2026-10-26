<?php

return [
    'catalog_version' => (int) env('PLYRCARD_POINTS_CATALOG_VERSION', 3),
    'catalog' => [
        'outreach' => [
            'name' => 'Coach Outreach Program',
            'points' => (int) env('PLYRCARD_POINTS_OUTREACH', 80),
            'description' => 'Targeted outreach to the coaches on your list, from your own recruiting email.',
            'cta' => 'Request an outreach program',
        ],
        'reel' => [
            'name' => 'Highlight Reel',
            'points' => (int) env('PLYRCARD_POINTS_REEL', 90),
            'description' => 'Your best moments cut from VEO or game film.',
            'cta' => 'Request a reel',
        ],
        'graphic' => [
            'name' => 'Custom Graphic',
            'points' => (int) env('PLYRCARD_POINTS_GRAPHIC', 50),
            'description' => 'Commitment posts, CV graphics, game day and stat cards.',
            'cta' => 'Request a graphic',
        ],
        'production_hour' => [
            'name' => 'Rep Hour',
            'points' => (int) env('PLYRCARD_POINTS_PRODUCTION_HOUR', 50),
            'description' => 'Have your rep review your profile, or hand off the work when you are short on time.',
            'cta' => 'Book a rep hour',
        ],
    ],
    'point_packs' => [
        '50' => [
            'label' => '50 Point Pack',
            'points' => (int) env('PLYRCARD_POINT_PACK_50_POINTS', 50),
            'price_cents' => (int) env('PLYRCARD_POINT_PACK_50_PRICE_CENTS', 5000),
            'stripe_product_id' => trim((string) env('PLYRCARD_POINT_PACK_50_STRIPE_PRODUCT_ID', env('PLYRCARD_POINT_PACK_100_STRIPE_PRODUCT_ID', ''))),
            'enabled' => filter_var(env('PLYRCARD_POINT_PACK_50_ENABLED', true), FILTER_VALIDATE_BOOL),
            'note' => 'Enough for one Custom Graphic or Rep Hour.',
        ],
        '100' => [
            'label' => '100 Point Pack',
            'points' => (int) env('PLYRCARD_POINT_PACK_100_POINTS', 100),
            'price_cents' => (int) env('PLYRCARD_POINT_PACK_100_PRICE_CENTS', 9000),
            'stripe_product_id' => trim((string) env('PLYRCARD_POINT_PACK_100_STRIPE_PRODUCT_ID', '')),
            'enabled' => filter_var(env('PLYRCARD_POINT_PACK_100_ENABLED', true), FILTER_VALIDATE_BOOL),
            'note' => 'Quick top-up for smaller service requests.',
        ],
        '250' => [
            'label' => '250 Point Pack',
            'points' => (int) env('PLYRCARD_POINT_PACK_250_POINTS', 250),
            'price_cents' => (int) env('PLYRCARD_POINT_PACK_250_PRICE_CENTS', 21000),
            'stripe_product_id' => trim((string) env('PLYRCARD_POINT_PACK_250_STRIPE_PRODUCT_ID', '')),
            'enabled' => filter_var(env('PLYRCARD_POINT_PACK_250_ENABLED', true), FILTER_VALIDATE_BOOL),
            'note' => 'More room to combine services in one recruiting push.',
        ],
        '500' => [
            'label' => '500 Point Pack',
            'points' => (int) env('PLYRCARD_POINT_PACK_500_POINTS', 500),
            'price_cents' => (int) env('PLYRCARD_POINT_PACK_500_PRICE_CENTS', 40000),
            'stripe_product_id' => trim((string) env('PLYRCARD_POINT_PACK_500_STRIPE_PRODUCT_ID', '')),
            'enabled' => filter_var(env('PLYRCARD_POINT_PACK_500_ENABLED', true), FILTER_VALIDATE_BOOL),
            'note' => 'Best point-pack value for larger service needs.',
        ],
    ],
    'custom_point_purchase' => [
        'enabled' => filter_var(env('PLYRCARD_CUSTOM_POINT_PURCHASE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'min_points' => max(1, (int) env('PLYRCARD_CUSTOM_POINT_MIN', 1)),
        'max_points' => max(1, (int) env('PLYRCARD_CUSTOM_POINT_MAX', 1000)),
        'price_per_point_cents' => max(1, (int) env('PLYRCARD_CUSTOM_POINT_PRICE_CENTS', 100)),
        // Prefer a generic Stripe product named something like "PLYR Points".
        // The controller can fall back to the 50-point product during rollout.
        'stripe_product_id' => trim((string) env('PLYRCARD_POINT_CUSTOM_STRIPE_PRODUCT_ID', '')),
    ],
    'packages' => [
        'jumpstart' => (int) env('PLYRCARD_POINTS_JUMPSTART', 320),
        'amplify' => (int) env('PLYRCARD_POINTS_AMPLIFY', 1280),
    ],
];