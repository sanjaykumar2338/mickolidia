<?php

return [
    'platforms' => [
        'default' => env('TRADING_PLATFORM_DEFAULT', 'ctrader'),
        'ctrader' => [
            'enabled' => env('CTRADER_SYNC_ENABLED', false),
            'use_mock_data' => env('CTRADER_USE_MOCK_DATA', false),
        ],
    ],

    'sync' => [
        'enabled' => env('TRADING_SYNC_ENABLED', false),
        'use_queue' => env('TRADING_SYNC_USE_QUEUE', true),
        'queue' => env('TRADING_SYNC_QUEUE', 'trading-sync'),
        'chunk_size' => (int) env('TRADING_SYNC_CHUNK_SIZE', 50),
        'cron' => env('TRADING_SYNC_CRON', '*/15 * * * *'),
    ],
];
