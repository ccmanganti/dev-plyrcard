<?php

return [

    'background' => [
        // auto: async Laravel queue first, detached shell second, scheduled command fallback last.
        // queue: queue only, shell: detached shell only, scheduler: scheduled command only.
        'driver' => env('COACH_DATABASE_SYNC_DRIVER', 'auto'),
        'queue_enabled' => (bool) env('COACH_DATABASE_QUEUE_ENABLED', true),
        'queue_connection' => env('COACH_DATABASE_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
        'queue_name' => env('COACH_DATABASE_QUEUE_NAME', 'recruiting'),
        'shell_enabled' => (bool) env('COACH_DATABASE_SHELL_ENABLED', true),
        'worker_start_grace_seconds' => (int) env('COACH_DATABASE_WORKER_START_GRACE_SECONDS', 45),
        'worker_stale_seconds' => (int) env('COACH_DATABASE_WORKER_STALE_SECONDS', 180),
    ],

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
