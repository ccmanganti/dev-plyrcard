<?php

return [
    // Small API pages prevent a single upstream request from monopolizing a worker.
    'pages' => [
        'businesses' => (int) env('COACH_DATABASE_BUSINESS_PAGE_SIZE', 25),
        'contacts' => (int) env('COACH_DATABASE_CONTACT_PAGE_SIZE', 50),
    ],

    'max_pages' => [
        'businesses' => (int) env('COACH_DATABASE_MAX_BUSINESS_PAGES', 500),
        'contacts' => (int) env('COACH_DATABASE_MAX_CONTACT_PAGES', 500),
    ],

    'page_attempts' => (int) env('COACH_DATABASE_PAGE_ATTEMPTS', 3),
    'retry_sleep_ms' => (int) env('COACH_DATABASE_RETRY_SLEEP_MS', 800),
    'cli_memory_limit' => env('COACH_DATABASE_CLI_MEMORY_LIMIT', '512M'),

    'http' => [
        'connect_timeout' => (int) env('COACH_DATABASE_HTTP_CONNECT_TIMEOUT', 5),
        'request_timeout' => (int) env('COACH_DATABASE_HTTP_REQUEST_TIMEOUT', 15),
    ],

    'tags' => [
        'sync_minutes' => (int) env('COACH_DATABASE_TAG_SYNC_MINUTES', 5),
        'max_pages_per_tag' => (int) env('COACH_DATABASE_TAG_MAX_PAGES', 20),
    ],

    // Existing Blade Load more controls remain compatible, but Livewire is never
    // allowed to serialize an unlimited number of cards in one response.
    'ui' => [
        'school_row_cap' => (int) env('COACH_DATABASE_UI_SCHOOL_ROW_CAP', 96),
        'coach_row_cap' => (int) env('COACH_DATABASE_UI_COACH_ROW_CAP', 120),
        'conversation_row_cap' => (int) env('COACH_DATABASE_UI_CONVERSATION_ROW_CAP', 25),
        'message_page_size' => (int) env('COACH_DATABASE_MESSAGE_PAGE_SIZE', 25),
        'message_row_cap' => (int) env('COACH_DATABASE_UI_MESSAGE_ROW_CAP', 100),
        'template_row_cap' => (int) env('COACH_DATABASE_UI_TEMPLATE_ROW_CAP', 100),
    ],
];