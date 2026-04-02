<?php

$paypalBaseUrl = env('PAYPAL_BASE_URL');
$paypalMode = env('PAYPAL_MODE', 'sandbox');

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect_uri' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect_uri' => env('APPLE_REDIRECT_URI'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => $paypalMode,
        'base_url' => is_string($paypalBaseUrl) && $paypalBaseUrl !== ''
            ? $paypalBaseUrl
            : ($paypalMode === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'timeout' => (int) env('PAYPAL_TIMEOUT', 15),
    ],

    'ctrader' => [
        'broker_name' => env('CTRADER_BROKER_NAME', 'IC Markets'),
        'auth_url' => env('CTRADER_AUTH_URL', 'https://id.ctrader.com/my/settings/openapi/grantingaccess/'),
        'token_url' => env('CTRADER_TOKEN_URL', 'https://openapi.ctrader.com/apps/token'),
        'base_url' => env('CTRADER_BASE_URL', 'https://openapi.ctrader.com'),
        'access_token' => env('CTRADER_ACCESS_TOKEN'),
        'client_id' => env('CTRADER_CLIENT_ID'),
        'client_secret' => env('CTRADER_CLIENT_SECRET'),
        'redirect_uri' => env('CTRADER_REDIRECT_URI'),
        'scope' => env('CTRADER_SCOPE', 'accounts'),
        'account_endpoint' => env('CTRADER_ACCOUNT_ENDPOINT', '/accounts/{account}'),
        'timeout' => env('CTRADER_TIMEOUT', 15),
        'environment' => env('CTRADER_ENVIRONMENT', 'demo'),
        'transport' => [
            'scheme' => env('CTRADER_TRANSPORT_SCHEME', 'wss'),
            'json_port' => (int) env('CTRADER_JSON_PORT', 5036),
            'demo_host' => env('CTRADER_DEMO_HOST', 'demo.ctraderapi.com'),
            'live_host' => env('CTRADER_LIVE_HOST', 'live.ctraderapi.com'),
        ],
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
