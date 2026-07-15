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
        'driver' => env('COACH_DATABASE_SYNC_DRIVER', 'incremental_livewire'),
        'queue_enabled' => false,
        'queue_connection' => env('COACH_DATABASE_QUEUE_CONNECTION'),
        'queue_name' => env('COACH_DATABASE_QUEUE_NAME', 'recruiting'),
        'queue_heartbeat_wait_ms' => (int) env('COACH_DATABASE_QUEUE_HEARTBEAT_WAIT_MS', 2200),
        'shell_enabled' => false,
        'allow_shell_in_production' => (bool) env('COACH_DATABASE_ALLOW_SHELL_IN_PRODUCTION', false),
        'allow_fallback' => (bool) env('COACH_DATABASE_ALLOW_FALLBACK', true),
        'prefer_shell_locally' => (bool) env('COACH_DATABASE_PREFER_SHELL_LOCALLY', true),
        'shell_heartbeat_wait_ms' => (int) env('COACH_DATABASE_SHELL_HEARTBEAT_WAIT_MS', 2800),
        'scheduler_healthy_seconds' => (int) env('COACH_DATABASE_SCHEDULER_HEALTHY_SECONDS', 180),
        'worker_start_grace_seconds' => (int) env('COACH_DATABASE_WORKER_START_GRACE_SECONDS', 45),
        'worker_stale_seconds' => (int) env('COACH_DATABASE_WORKER_STALE_SECONDS', 180),
    ],

    // One consistent full-dataset runner for local and production.
    // It processes small checkpointed pages through passive Livewire polling, so
    // no queue worker, detached process, Supervisor, or cron is required.
    'incremental' => [
        'business_page_size' => (int) env('COACH_DATABASE_INCREMENTAL_BUSINESS_PAGE_SIZE', 10),
        'contact_page_size' => (int) env('COACH_DATABASE_INCREMENTAL_CONTACT_PAGE_SIZE', 25),
        'connect_timeout' => (int) env('COACH_DATABASE_INCREMENTAL_CONNECT_TIMEOUT', 3),
        'request_timeout' => (int) env('COACH_DATABASE_INCREMENTAL_REQUEST_TIMEOUT', 8),
        'business_failure_limit' => (int) env('COACH_DATABASE_INCREMENTAL_BUSINESS_FAILURE_LIMIT', 3),
        'contact_failure_limit' => (int) env('COACH_DATABASE_INCREMENTAL_CONTACT_FAILURE_LIMIT', 6),
        'tick_lock_seconds' => (int) env('COACH_DATABASE_INCREMENTAL_TICK_LOCK_SECONDS', 30),
        'pages_per_tick' => (int) env('COACH_DATABASE_INCREMENTAL_PAGES_PER_TICK', 2),
        'time_budget_seconds' => (int) env('COACH_DATABASE_INCREMENTAL_TIME_BUDGET_SECONDS', 12),
        'finalize_reserve_seconds' => (int) env('COACH_DATABASE_INCREMENTAL_FINALIZE_RESERVE_SECONDS', 2),
    ],

    'web_fallback' => [
        'enabled' => (bool) env('COACH_DATABASE_WEB_FALLBACK_ENABLED', true),
        'business_page_size' => (int) env('COACH_DATABASE_WEB_BUSINESS_PAGE_SIZE', 20),
        'contact_page_size' => (int) env('COACH_DATABASE_WEB_CONTACT_PAGE_SIZE', 50),
        'connect_timeout' => (int) env('COACH_DATABASE_WEB_CONNECT_TIMEOUT', 2),
        'request_timeout' => (int) env('COACH_DATABASE_WEB_REQUEST_TIMEOUT', 5),
        'business_failure_limit' => (int) env('COACH_DATABASE_WEB_BUSINESS_FAILURE_LIMIT', 2),
        'contact_failure_limit' => (int) env('COACH_DATABASE_WEB_CONTACT_FAILURE_LIMIT', 3),
        'tick_lock_seconds' => (int) env('COACH_DATABASE_WEB_TICK_LOCK_SECONDS', 25),
        'pages_per_tick' => (int) env('COACH_DATABASE_WEB_PAGES_PER_TICK', 2),
        'time_budget_seconds' => (int) env('COACH_DATABASE_WEB_TIME_BUDGET_SECONDS', 11),
        'finalize_reserve_seconds' => (int) env('COACH_DATABASE_WEB_FINALIZE_RESERVE_SECONDS', 2),
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

    'coach_database' => [
        'bulk_list_max_schools' => 2000,

        // Number of resolved coach contacts sent in each bulk tag request.
        'bulk_list_contacts_per_request' => 70,

        // Maximum paced bulk requests started each second.
        'bulk_list_requests_per_second' => 8,

        // Retry temporary API failures and rate-limit responses.
        'bulk_list_retry_attempts' => 4,
    ],

];