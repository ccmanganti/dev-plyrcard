<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GHL merge fields
    |--------------------------------------------------------------------------
    |
    | These values are deliberately kept as literal merge fields in generated
    | links. GHL replaces them per recipient when the campaign is sent.
    |
    */
    'ghl_contact_id_merge_field' => '{{contact.id}}',
    'ghl_contact_email_merge_field' => '{{contact.email}}',

    /*
    | Platform domains serve profiles under /{slug}. Parked/custom domains
    | serve the selected player's profile from the root URL.
    */
    'platform_hosts' => [
        '127.0.0.1',
        'localhost',
        'dev.plyrcard.com',
        'plyrcard.com',
        'www.plyrcard.com',
    ],

    'default_source' => 'ghl',
    'default_medium' => 'email',
    'default_campaign' => 'recruiting',
];
