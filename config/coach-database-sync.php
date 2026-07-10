<?php

return [
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
    'business_failure_limit' => (int) env('COACH_DATABASE_BUSINESS_FAILURE_LIMIT', 2),
    'contact_failure_limit' => (int) env('COACH_DATABASE_CONTACT_FAILURE_LIMIT', 3),
    'cli_memory_limit' => env('COACH_DATABASE_CLI_MEMORY_LIMIT', '512M'),

    'http' => [
        'connect_timeout' => (int) env('COACH_DATABASE_HTTP_CONNECT_TIMEOUT', 5),
        'request_timeout' => (int) env('COACH_DATABASE_HTTP_REQUEST_TIMEOUT', 20),
    ],

    'background' => [
        'driver' => env('COACH_DATABASE_SYNC_DRIVER', 'auto'),
        'queue_enabled' => (bool) env('COACH_DATABASE_QUEUE_ENABLED', true),
        'queue_connection' => env('COACH_DATABASE_QUEUE_CONNECTION'),
        'queue_name' => env('COACH_DATABASE_QUEUE_NAME', 'recruiting'),
        'shell_enabled' => (bool) env('COACH_DATABASE_SHELL_ENABLED', true),
        'allow_shell_in_production' => (bool) env('COACH_DATABASE_ALLOW_SHELL_IN_PRODUCTION', false),
        'allow_fallback' => (bool) env('COACH_DATABASE_ALLOW_FALLBACK', true),
        'prefer_shell_locally' => (bool) env('COACH_DATABASE_PREFER_SHELL_LOCALLY', true),
        'shell_heartbeat_wait_ms' => (int) env('COACH_DATABASE_SHELL_HEARTBEAT_WAIT_MS', 1600),
        'scheduler_healthy_seconds' => (int) env('COACH_DATABASE_SCHEDULER_HEALTHY_SECONDS', 180),
        'worker_start_grace_seconds' => (int) env('COACH_DATABASE_WORKER_START_GRACE_SECONDS', 45),
        'worker_stale_seconds' => (int) env('COACH_DATABASE_WORKER_STALE_SECONDS', 180),
    ],

    // Last-resort compatibility runner. It processes one small remote page on each
    // passive Livewire poll. This means the same code works on local machines and shared
    // hosting even when no queue worker, cron task, or durable detached process exists.
    'web_fallback' => [
        'enabled' => (bool) env('COACH_DATABASE_WEB_FALLBACK_ENABLED', true),
        'business_page_size' => (int) env('COACH_DATABASE_WEB_BUSINESS_PAGE_SIZE', 10),
        'contact_page_size' => (int) env('COACH_DATABASE_WEB_CONTACT_PAGE_SIZE', 25),
        'connect_timeout' => (int) env('COACH_DATABASE_WEB_CONNECT_TIMEOUT', 2),
        'request_timeout' => (int) env('COACH_DATABASE_WEB_REQUEST_TIMEOUT', 6),
        'business_failure_limit' => (int) env('COACH_DATABASE_WEB_BUSINESS_FAILURE_LIMIT', 2),
        'contact_failure_limit' => (int) env('COACH_DATABASE_WEB_CONTACT_FAILURE_LIMIT', 3),
        'tick_lock_seconds' => (int) env('COACH_DATABASE_WEB_TICK_LOCK_SECONDS', 20),
    ],

    'tags' => [
        'sync_minutes' => (int) env('COACH_DATABASE_TAG_SYNC_MINUTES', 5),
        'max_pages_per_tag' => (int) env('COACH_DATABASE_TAG_MAX_PAGES', 20),
    ],

    'ui' => [
        'school_row_cap' => (int) env('COACH_DATABASE_UI_SCHOOL_ROW_CAP', 96),
        'coach_row_cap' => (int) env('COACH_DATABASE_UI_COACH_ROW_CAP', 120),
        'conversation_row_cap' => (int) env('COACH_DATABASE_UI_CONVERSATION_ROW_CAP', 25),
        'message_page_size' => (int) env('COACH_DATABASE_MESSAGE_PAGE_SIZE', 25),
        'message_row_cap' => (int) env('COACH_DATABASE_UI_MESSAGE_ROW_CAP', 100),
        'template_row_cap' => (int) env('COACH_DATABASE_UI_TEMPLATE_ROW_CAP', 100),
    ],
];
