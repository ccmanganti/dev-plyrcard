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
    'packages' => [
        'jumpstart' => (int) env('PLYRCARD_POINTS_JUMPSTART', 320),
        'amplify' => (int) env('PLYRCARD_POINTS_AMPLIFY', 1280),
    ],
];
