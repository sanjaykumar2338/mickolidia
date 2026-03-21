<?php

$supportedLocales = [
    'en' => [
        'native' => 'English',
        'short' => 'EN',
        'flag' => '🇬🇧',
    ],
    'de' => [
        'native' => 'Deutsch',
        'short' => 'DE',
        'flag' => '🇩🇪',
    ],
    'es' => [
        'native' => 'Español',
        'short' => 'ES',
        'flag' => '🇪🇸',
    ],
];

$challengeCatalog = [
    'one_step' => [
        'steps' => 1,
        'plans' => [
            5000 => [
                'slug' => 'one-step-5000',
                'name' => '1-Step 5K',
                'account_size' => 5000,
                'currency' => 'EUR',
                'entry_fee' => 59,
                'profit_target' => 10,
                'daily_loss_limit' => 4,
                'max_loss_limit' => 6,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            10000 => [
                'slug' => 'one-step-10000',
                'name' => '1-Step 10K',
                'account_size' => 10000,
                'currency' => 'EUR',
                'entry_fee' => 99,
                'profit_target' => 10,
                'daily_loss_limit' => 4,
                'max_loss_limit' => 6,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            25000 => [
                'slug' => 'one-step-25000',
                'name' => '1-Step 25K',
                'account_size' => 25000,
                'currency' => 'EUR',
                'entry_fee' => 189,
                'profit_target' => 10,
                'daily_loss_limit' => 4,
                'max_loss_limit' => 6,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            50000 => [
                'slug' => 'one-step-50000',
                'name' => '1-Step 50K',
                'account_size' => 50000,
                'currency' => 'EUR',
                'entry_fee' => 329,
                'profit_target' => 10,
                'daily_loss_limit' => 4,
                'max_loss_limit' => 6,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            100000 => [
                'slug' => 'one-step-100000',
                'name' => '1-Step 100K',
                'account_size' => 100000,
                'currency' => 'EUR',
                'entry_fee' => 549,
                'profit_target' => 10,
                'daily_loss_limit' => 4,
                'max_loss_limit' => 6,
                'profit_share' => 85,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
        ],
    ],
    'two_step' => [
        'steps' => 2,
        'plans' => [
            5000 => [
                'slug' => 'two-step-5000',
                'name' => '2-Step 5K',
                'account_size' => 5000,
                'currency' => 'EUR',
                'entry_fee' => 49,
                'profit_target' => 8,
                'daily_loss_limit' => 5,
                'max_loss_limit' => 10,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            10000 => [
                'slug' => 'two-step-10000',
                'name' => '2-Step 10K',
                'account_size' => 10000,
                'currency' => 'EUR',
                'entry_fee' => 79,
                'profit_target' => 8,
                'daily_loss_limit' => 5,
                'max_loss_limit' => 10,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            25000 => [
                'slug' => 'two-step-25000',
                'name' => '2-Step 25K',
                'account_size' => 25000,
                'currency' => 'EUR',
                'entry_fee' => 149,
                'profit_target' => 8,
                'daily_loss_limit' => 5,
                'max_loss_limit' => 10,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            50000 => [
                'slug' => 'two-step-50000',
                'name' => '2-Step 50K',
                'account_size' => 50000,
                'currency' => 'EUR',
                'entry_fee' => 269,
                'profit_target' => 8,
                'daily_loss_limit' => 5,
                'max_loss_limit' => 10,
                'profit_share' => 80,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
            100000 => [
                'slug' => 'two-step-100000',
                'name' => '2-Step 100K',
                'account_size' => 100000,
                'currency' => 'EUR',
                'entry_fee' => 449,
                'profit_target' => 8,
                'daily_loss_limit' => 5,
                'max_loss_limit' => 10,
                'profit_share' => 85,
                'first_payout_days' => 21,
                'minimum_trading_days' => 3,
                'payout_cycle_days' => 14,
                'maximum_trading_days' => null,
            ],
        ],
    ],
];

$challengeSizes = [];
$challengePlans = [];

foreach ($challengeCatalog as $challengeType => $challengeTypeData) {
    foreach ($challengeTypeData['plans'] as $size => $plan) {
        $challengeSizes[(string) $size] = (int) $size;
        $challengePlans[] = array_merge($plan, [
            'challenge_type' => $challengeType,
            'steps' => $challengeTypeData['steps'],
        ]);
    }
}

ksort($challengeSizes);

return [
    'default_locale' => 'en',

    'supported_locales' => $supportedLocales,

    'future_locales' => [
        [
            'native' => 'हिंदी',
            'short' => 'HI',
            'flag' => '🇮🇳',
        ],
        [
            'native' => 'Italiano',
            'short' => 'IT',
            'flag' => '🇮🇹',
        ],
        [
            'native' => 'Português',
            'short' => 'PT',
            'flag' => '🇵🇹',
        ],
    ],

    'checkout_countries' => [
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'BR' => 'Brazil',
        'CA' => 'Canada',
        'CH' => 'Switzerland',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DE' => 'Germany',
        'DK' => 'Denmark',
        'EE' => 'Estonia',
        'ES' => 'Spain',
        'FI' => 'Finland',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'GR' => 'Greece',
        'HR' => 'Croatia',
        'HU' => 'Hungary',
        'IE' => 'Ireland',
        'IN' => 'India',
        'IT' => 'Italy',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'LV' => 'Latvia',
        'MT' => 'Malta',
        'MX' => 'Mexico',
        'NL' => 'Netherlands',
        'NO' => 'Norway',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RO' => 'Romania',
        'SE' => 'Sweden',
        'SG' => 'Singapore',
        'SI' => 'Slovenia',
        'SK' => 'Slovakia',
        'TR' => 'Turkey',
        'US' => 'United States',
        'ZA' => 'South Africa',
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

    'challenge_catalog' => $challengeCatalog,

    'challenge_sizes' => array_values($challengeSizes),

    'challenge_plans' => $challengePlans,
];
