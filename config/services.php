<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
    ],

    'ghl' => [
        'token' => env('GHL_API_TOKEN'),
        'location_id' => env('GHL_LOCATION_ID'),
        'webhook_secret' => env('GHL_WEBHOOK_SECRET'),
        'profile_completion_field_id' => env('GHL_PROFILE_COMPLETION_FIELD_ID'),
        'site_status_field_id' => env('GHL_SITE_STATUS_FIELD_ID'),

        // Optional: only needed if player calendars live in multiple GHL sub-accounts.
        'agency_token' => env('GHL_AGENCY_ACCESS_TOKEN'),
        'company_id' => env('GHL_COMPANY_ID'),
    ],

    'tracking' => [
        'base_url' => env('TRACKING_BASE_URL', env('APP_URL', 'https://plyrcard.com')),
    ],


];
