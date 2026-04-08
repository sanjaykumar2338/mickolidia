<?php

return [
    'platforms' => [
        'default' => env('TRADING_PLATFORM_DEFAULT', 'ctrader'),
        'mt5' => [
            'enabled' => env('MT5_SYNC_ENABLED', true),
            'freshness' => [
                'live_seconds' => (int) env('MT5_SYNC_LIVE_SECONDS', 15),
                'recent_seconds' => (int) env('MT5_SYNC_RECENT_SECONDS', 60),
            ],
        ],
        'ctrader' => [
            'enabled' => env('CTRADER_SYNC_ENABLED', false),
            'use_mock_data' => env('CTRADER_USE_MOCK_DATA', false),
            'history_days' => (int) env('CTRADER_HISTORY_DAYS', 90),
            'history_max_rows' => (int) env('CTRADER_HISTORY_MAX_ROWS', 500),
        ],
    ],

    'sync' => [
        'enabled' => env('TRADING_SYNC_ENABLED', false),
        'use_queue' => env('TRADING_SYNC_USE_QUEUE', true),
        'queue' => env('TRADING_SYNC_QUEUE', 'trading-sync'),
        'chunk_size' => (int) env('TRADING_SYNC_CHUNK_SIZE', 50),
        'cron' => env('TRADING_SYNC_CRON', '*/5 * * * *'),
    ],
];
