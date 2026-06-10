<?php

return [
    'base_url' => env('GHL_BASE_URL', 'https://services.leadconnectorhq.com'),
    'version' => env('GHL_API_VERSION', '2023-02-21'),
    'timeout' => env('GHL_TIMEOUT', 20),

    'token' => env('GHL_API_TOKEN'),
    'location_id' => env('GHL_LOCATION_ID'),

    'agency_token' => env('GHL_AGENCY_ACCESS_TOKEN'),
    'company_id' => env('GHL_COMPANY_ID'),

    'profile_completion_field_id' => env('GHL_PROFILE_COMPLETION_FIELD_ID'),
    'site_status_field_id' => env('GHL_SITE_STATUS_FIELD_ID'),

    'commands' => [
        'viewed_profile_contacts' => [
            'label' => 'Profile Views',
            'description' => 'Profile Views',
            'tag' => 'viewed profile',
            'type' => 'contacts_by_tag',
        ],

        'viewed_highlights_contacts' => [
            'label' => 'Highlight Views',
            'description' => 'Highlight Views',
            'tag' => 'viewed highlights',
            'type' => 'contacts_by_tag',
        ],
    ],
];