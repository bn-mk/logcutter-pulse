<?php

return [
    'log_path' => env('LOGPULSE_LOG_PATH', storage_path('logs')),

    'routes' => [
        'enabled' => (bool) env('LOGPULSE_ROUTES_ENABLED', true),
        'prefix' => env('LOGPULSE_ROUTE_PREFIX', 'logpulse'),
        'name_prefix' => env('LOGPULSE_ROUTE_NAME_PREFIX', 'logpulse.'),
        'middleware' => ['web', 'auth', 'verified'],
    ],

    'debug_routes' => [
        'enabled' => (bool) env('LOGPULSE_DEBUG_ROUTES_ENABLED', true),
        'prefix' => env('LOGPULSE_DEBUG_ROUTE_PREFIX', '_debug/log-bugs'),
        'name_prefix' => env('LOGPULSE_DEBUG_ROUTE_NAME_PREFIX', 'debug.log-bugs.'),
    ],

    'max_read_bytes' => (int) env('LOGPULSE_MAX_READ_BYTES', 2 * 1024 * 1024),

    'default_level' => env('LOGPULSE_DEFAULT_LEVEL', 'error'),
    'default_hours' => (int) env('LOGPULSE_DEFAULT_HOURS', 24),
    'max_groups' => (int) env('LOGPULSE_MAX_GROUPS', 100),

    'live_updates' => [
        'enabled' => (bool) env('LOGPULSE_LIVE_UPDATES_ENABLED', true),
        'poll_interval_seconds' => (int) env('LOGPULSE_POLL_INTERVAL_SECONDS', 5),
    ],

    'notifications' => [
        'enabled' => (bool) env('LOGPULSE_NOTIFICATIONS_ENABLED', true),
        'threshold' => (int) env('LOGPULSE_NOTIFICATION_THRESHOLD', 3),
        'level' => env('LOGPULSE_NOTIFICATION_LEVEL', 'error'),
    ],

    'ui' => [
        'driver' => env('LOGPULSE_UI_DRIVER', 'blade'),
    ],

    'inertia' => [
        'root_view' => env('LOGPULSE_INERTIA_ROOT_VIEW', 'logpulse::app'),
    ],
];
