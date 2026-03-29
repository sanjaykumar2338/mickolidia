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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'ctrader' => [
        'base_url' => env('CTRADER_BASE_URL'),
        'access_token' => env('CTRADER_ACCESS_TOKEN'),
        'client_id' => env('CTRADER_CLIENT_ID'),
        'client_secret' => env('CTRADER_CLIENT_SECRET'),
        'account_endpoint' => env('CTRADER_ACCOUNT_ENDPOINT', '/accounts/{account}'),
        'timeout' => env('CTRADER_TIMEOUT', 10),
        'environment' => env('CTRADER_ENVIRONMENT', 'demo'),
    ],

    'trading_economics' => [
        'base_url' => env('TRADING_ECONOMICS_BASE_URL', 'https://api.tradingeconomics.com'),
        'api_key' => env('TRADING_ECONOMICS_API_KEY'),
    ],

    'financial_modeling_prep' => [
        'api_key' => env('FMP_API_KEY'),
    ],

    'econoday' => [
        'api_key' => env('ECONODAY_API_KEY'),
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

];
