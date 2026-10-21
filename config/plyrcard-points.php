<?php

return [
    'catalog_version' => (int) env('PLYRCARD_POINTS_CATALOG_VERSION', 2),
    'catalog' => [
        'graphic' => [
            'name' => 'Social graphic',
            'points' => (int) env('PLYRCARD_POINTS_GRAPHIC', 50),
            'description' => 'One social graphic.',
        ],
        'reel' => [
            'name' => 'Highlight reel',
            'points' => (int) env('PLYRCARD_POINTS_REEL', 90),
            'description' => 'One highlight reel, up to 2 minutes.',
        ],
        'outreach' => [
            'name' => 'Coach outreach campaign',
            'points' => (int) env('PLYRCARD_POINTS_OUTREACH', 80),
            'description' => 'One outreach campaign to up to 25 coaches.',
        ],
        'production_hour' => [
            'name' => 'Production hour',
            'points' => (int) env('PLYRCARD_POINTS_PRODUCTION_HOUR', 50),
            'description' => 'One shoot or editing hour.',
        ],
        'photo_batch' => [
            'name' => 'Photo retouch batch',
            'points' => (int) env('PLYRCARD_POINTS_PHOTO_BATCH', 15),
            'description' => 'One photo retouch batch.',
        ],
        'site_refresh' => [
            'name' => 'Profile site refresh',
            'points' => (int) env('PLYRCARD_POINTS_SITE_REFRESH', 15),
            'description' => 'One profile-site refresh.',
        ],
        'film_breakdown' => [
            'name' => 'Match film breakdown',
            'points' => (int) env('PLYRCARD_POINTS_FILM_BREAKDOWN', 35),
            'description' => 'One match-film breakdown.',
        ],
    ],
    'point_packs' => [
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
    'packages' => [
        'jumpstart' => (int) env('PLYRCARD_POINTS_JUMPSTART', 320),
        'amplify' => (int) env('PLYRCARD_POINTS_AMPLIFY', 1280),
    ],
];