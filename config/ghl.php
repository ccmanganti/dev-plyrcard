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

    'coach_database' => [
        'enabled' => env('GHL_COACH_DATABASE_ENABLED', true),
        'page_limit' => env('GHL_COACH_DATABASE_PAGE_LIMIT', 100),
        'max_pages' => env('GHL_COACH_DATABASE_MAX_PAGES', 25),

        'tags' => [
            'coach' => env('GHL_TAG_COACH', 'coach'),
            'coach_database' => env('GHL_TAG_COACH_DATABASE', 'coach database'),

            'saved_school' => env('GHL_TAG_SAVED_SCHOOL', 'saved school'),
            'favorite_school' => env('GHL_TAG_FAVORITE_SCHOOL', 'favorite school'),
            'saved_coach' => env('GHL_TAG_SAVED_COACH', 'saved coach'),
            'favorite_coach' => env('GHL_TAG_FAVORITE_COACH', 'favorite coach'),

            'viewed_profile' => env('GHL_TAG_VIEWED_PROFILE', 'viewed profile'),
            'viewed_highlights' => env('GHL_TAG_VIEWED_HIGHLIGHTS', 'viewed highlights'),
            'engaged' => env('GHL_TAG_ENGAGED', 'engaged'),
            'replied' => env('GHL_TAG_REPLIED', 'replied'),
            'trigger_link_clicked' => env('GHL_TAG_TRIGGER_LINK_CLICKED', 'trigger link clicked'),

            'needs_audit' => env('GHL_TAG_NEEDS_AUDIT', 'needs audit'),
            'verified_coach' => env('GHL_TAG_VERIFIED_COACH', 'verified coach'),
        ],

        'lists' => [
            'dream' => [
                'label' => 'Dream Schools',
                'tag' => env('GHL_TAG_LIST_DREAM', 'dream school'),
                'description' => 'Top choice programs.',
            ],
            'target' => [
                'label' => 'Target Schools',
                'tag' => env('GHL_TAG_LIST_TARGET', 'target school'),
                'description' => 'Realistic recruiting targets.',
            ],
            'safety' => [
                'label' => 'Safety Schools',
                'tag' => env('GHL_TAG_LIST_SAFETY', 'safety school'),
                'description' => 'Backup options.',
            ],
            'camp_follow_up' => [
                'label' => 'Camp Follow-Up',
                'tag' => env('GHL_TAG_LIST_CAMP_FOLLOW_UP', 'camp follow-up'),
                'description' => 'Schools to follow up with after camps.',
            ],
            'showcase_follow_up' => [
                'label' => 'Showcase Follow-Up',
                'tag' => env('GHL_TAG_LIST_SHOWCASE_FOLLOW_UP', 'showcase follow-up'),
                'description' => 'Schools to follow up with after showcases.',
            ],
            'general' => [
                'label' => 'General Recruiting',
                'tag' => env('GHL_TAG_LIST_GENERAL', 'general recruiting'),
                'description' => 'General recruiting list.',
            ],
        ],

        'custom_fields' => [
            'coach_title' => env('GHL_CF_COACH_TITLE', 'r0iC4KEiNp0JFygWViui'),
            'coach_sport' => env('GHL_CF_COACH_SPORT'),
            'school_name' => env('GHL_CF_SCHOOL_NAME', 'mVRCvtpkuGo8eCgj2EkW'),
            'school_division' => env('GHL_CF_SCHOOL_DIVISION'),
            'school_conference' => env('GHL_CF_SCHOOL_CONFERENCE', '0fPOQNgzOiFmemKNwQ4k'),
            'school_state' => env('GHL_CF_SCHOOL_STATE'),
            'school_city' => env('GHL_CF_SCHOOL_CITY'),
            'coach_external_id' => env('GHL_CF_COACH_EXTERNAL_ID', 'D5Ca9PLSFG3dZdrsaIlV'),
            'coach_database_status' => env('GHL_CF_COACH_DATABASE_STATUS'),
            'last_audited_at' => env('GHL_CF_LAST_AUDITED_AT'),
        ],
    ],
];