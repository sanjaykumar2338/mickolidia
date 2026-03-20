<?php

return [
    'default_locale' => 'en',

    'supported_locales' => [
        'en' => [
            'native' => 'English',
            'short' => 'EN',
        ],
        'de' => [
            'native' => 'Deutsch',
            'short' => 'DE',
        ],
        'es' => [
            'native' => 'Español',
            'short' => 'ES',
        ],
    ],

    'legal_pages' => [
        'terms' => [
            'content_key' => 'terms',
            'route_name' => 'terms',
        ],
        'risk-disclosure' => [
            'content_key' => 'risk_disclosure',
            'route_name' => 'risk-disclosure',
        ],
        'payout-policy' => [
            'content_key' => 'payout_policy',
            'route_name' => 'payout-policy',
        ],
        'refund-policy' => [
            'content_key' => 'refund_policy',
            'route_name' => 'refund-policy',
        ],
        'privacy-policy' => [
            'content_key' => 'privacy_policy',
            'route_name' => 'privacy-policy',
        ],
        'aml-kyc' => [
            'content_key' => 'aml_kyc_policy',
            'route_name' => 'aml-kyc',
        ],
        'company-info' => [
            'content_key' => 'company_information',
            'route_name' => 'company-info',
        ],
    ],

    'challenge_plans' => [
        [
            'slug' => 'wolf-10000',
            'name' => 'Wolf 10K',
            'account_size' => 10000,
            'currency' => 'EUR',
            'entry_fee' => 89,
            'profit_target' => 8,
            'daily_loss_limit' => 5,
            'max_loss_limit' => 10,
            'steps' => 2,
            'profit_share' => 80,
            'first_payout_days' => 14,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
        ],
        [
            'slug' => 'wolf-25000',
            'name' => 'Wolf 25K',
            'account_size' => 25000,
            'currency' => 'EUR',
            'entry_fee' => 159,
            'profit_target' => 8,
            'daily_loss_limit' => 5,
            'max_loss_limit' => 10,
            'steps' => 2,
            'profit_share' => 80,
            'first_payout_days' => 14,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
        ],
        [
            'slug' => 'wolf-50000',
            'name' => 'Wolf 50K',
            'account_size' => 50000,
            'currency' => 'EUR',
            'entry_fee' => 289,
            'profit_target' => 8,
            'daily_loss_limit' => 5,
            'max_loss_limit' => 10,
            'steps' => 2,
            'profit_share' => 80,
            'first_payout_days' => 14,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
        ],
        [
            'slug' => 'wolf-100000',
            'name' => 'Wolf 100K',
            'account_size' => 100000,
            'currency' => 'EUR',
            'entry_fee' => 489,
            'profit_target' => 8,
            'daily_loss_limit' => 5,
            'max_loss_limit' => 10,
            'steps' => 2,
            'profit_share' => 80,
            'first_payout_days' => 14,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
        ],
    ],
];
