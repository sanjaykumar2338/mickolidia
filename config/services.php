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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 20),
        'tts' => [
            'enabled' => filter_var(env('OPENAI_TTS_ENABLED', true), FILTER_VALIDATE_BOOL),
            'model' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
            'voice' => env('OPENAI_TTS_VOICE', 'onyx'),
            'format' => env('OPENAI_TTS_FORMAT', 'mp3'),
            'speed' => (float) env('OPENAI_TTS_SPEED', 0.94),
        ],
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER'),
        'fallback_voice_id' => env('ELEVENLABS_FALLBACK_VOICE_ID', 'IKne3meq5aSn9XLyUdCD'),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
        'timeout' => (int) env('ELEVENLABS_TIMEOUT', 20),
        'tts' => [
            'enabled' => filter_var(env('ELEVENLABS_TTS_ENABLED', true), FILTER_VALIDATE_BOOL),
            'model' => env('ELEVENLABS_MODEL_ID', env('ELEVENLABS_TTS_MODEL', 'eleven_multilingual_v2')),
            'output_format' => env('ELEVENLABS_TTS_OUTPUT_FORMAT', 'mp3_44100_128'),
        ],
    ],

    'google_tts' => [
        'enabled' => filter_var(env('GOOGLE_TTS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'api_key' => env('GOOGLE_TTS_API_KEY'),
        'base_url' => env('GOOGLE_TTS_BASE_URL', 'https://texttospeech.googleapis.com'),
        'timeout' => (int) env('GOOGLE_TTS_TIMEOUT', 20),
        'audio_encoding' => env('GOOGLE_TTS_AUDIO_ENCODING', 'MP3'),
    ],

    'azure_tts' => [
        'enabled' => filter_var(env('AZURE_TTS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'api_key' => env('AZURE_TTS_API_KEY'),
        'region' => env('AZURE_TTS_REGION'),
        'endpoint' => env('AZURE_TTS_ENDPOINT'),
        'timeout' => (int) env('AZURE_TTS_TIMEOUT', 20),
        'output_format' => env('AZURE_TTS_OUTPUT_FORMAT', 'audio-24khz-96kbitrate-mono-mp3'),
    ],

    'mt5_ingestion' => [
        'token' => env('MT5_INGESTION_TOKEN'),
    ],

    'mt5_deactivation' => [
        'endpoint' => env('MT5_DEACTIVATION_ENDPOINT'),
        'token' => env('MT5_DEACTIVATION_TOKEN'),
        'timeout' => (int) env('MT5_DEACTIVATION_TIMEOUT', 10),
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
