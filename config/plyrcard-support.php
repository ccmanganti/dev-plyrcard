<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Internal alert recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails in PLYRCARD_ADMIN_ALERT_EMAILS receive both new
    | support-ticket alerts and downgrade-request alerts.
    |
    */
    'admin_emails' => array_values(array_filter(array_map(
        static fn ($email) => strtolower(trim((string) $email)),
        explode(',', (string) env('PLYRCARD_ADMIN_ALERT_EMAILS', 'support@plyrcard.com'))
    ))),

    'categories' => [
        'website' => 'Website / PLYRSite',
        'inbox' => 'Inbox / Messaging',
        'coach_database' => 'Coach Database / Schools',
        'profile' => 'Profile / Athlete Information',
        'account' => 'Account / Login',
        'billing' => 'Billing / Plan',
        'technical_issue' => 'Bug / Technical Issue',
        'custom_request' => 'Custom Request',
        'feature_request' => 'Feature Request',
        'other' => 'Other',
    ],

    'statuses' => [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'waiting_on_user' => 'Waiting on User',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ],

    'priorities' => [
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ],
];
