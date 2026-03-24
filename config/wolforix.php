<?php

$stripeProviderClass = \App\Services\Payments\StripePaymentGateway::class;

$supportedLocales = [
    'en' => [
        'native' => 'English',
        'short' => 'EN',
        'flag' => '🇬🇧',
        'flag_asset' => 'flags/gb.svg',
    ],
    'de' => [
        'native' => 'Deutsch',
        'short' => 'DE',
        'flag' => '🇩🇪',
        'flag_asset' => 'flags/de.svg',
    ],
    'es' => [
        'native' => 'Español',
        'short' => 'ES',
        'flag' => '🇪🇸',
        'flag_asset' => 'flags/es.svg',
    ],
    'fr' => [
        'native' => 'Français',
        'short' => 'FR',
        'flag' => '🇫🇷',
        'flag_asset' => 'flags/fr.svg',
    ],
];

$launchDiscount = [
    'enabled' => true,
    'type' => 'percentage',
    'percent' => 20,
    'badge' => '20% OFF - Limited Launch Offer',
    'urgency_text' => 'Launch Discount - Limited Time Only',
];

$currencies = [
    'USD' => [
        'rate' => 1,
    ],
    'EUR' => [
        'rate' => 0.92,
    ],
    'GBP' => [
        'rate' => 0.78,
    ],
];

$challengeModels = [
    'one_step' => [
        'steps' => 1,
        'pricing' => [
            5000 => 49,
            10000 => 99,
            25000 => 199,
            50000 => 349,
            100000 => 599,
        ],
        'phases' => [
            [
                'key' => 'single_phase',
                'profit_target' => 10,
                'daily_loss_limit' => 4,
                'max_loss_limit' => 8,
                'minimum_trading_days' => 3,
                'maximum_trading_days' => null,
                'leverage' => null,
            ],
        ],
        'funded' => [
            'profit_split' => 80,
            'payout_cycle_days' => 14,
            'first_withdrawal_days' => 21,
            'scaling_capital_percent' => null,
            'scaling_interval_months' => null,
            'consistency_rule_required' => true,
        ],
    ],
    'two_step' => [
        'steps' => 2,
        'pricing' => [
            5000 => 39,
            10000 => 79,
            25000 => 169,
            50000 => 289,
            100000 => 489,
        ],
        'phases' => [
            [
                'key' => 'phase_1',
                'profit_target' => 10,
                'daily_loss_limit' => 5,
                'max_loss_limit' => 10,
                'minimum_trading_days' => 3,
                'maximum_trading_days' => null,
                'leverage' => '1:100',
            ],
            [
                'key' => 'phase_2',
                'profit_target' => 5,
                'daily_loss_limit' => 5,
                'max_loss_limit' => 10,
                'minimum_trading_days' => 3,
                'maximum_trading_days' => null,
                'leverage' => null,
            ],
        ],
        'funded' => [
            'profit_split' => 80,
            'payout_cycle_days' => 14,
            'first_withdrawal_days' => 21,
            'scaling_capital_percent' => 25,
            'scaling_interval_months' => 3,
            'consistency_rule_required' => false,
        ],
    ],
];

$challengeCatalog = [];

$challengeSizes = [];
$challengePlans = [];

foreach ($challengeModels as $challengeType => $challengeTypeData) {
    $challengeCatalog[$challengeType] = [
        'steps' => $challengeTypeData['steps'],
        'phases' => array_values($challengeTypeData['phases']),
        'funded' => $challengeTypeData['funded'],
        'plans' => [],
    ];

    foreach ($challengeTypeData['pricing'] as $size => $listPrice) {
        $discountedPrice = $launchDiscount['enabled']
            ? (int) round($listPrice * ((100 - $launchDiscount['percent']) / 100))
            : $listPrice;

        $firstPhase = $challengeTypeData['phases'][0];
        $plan = [
            'slug' => str_replace('_', '-', $challengeType).'-'.$size,
            'name' => $challengeTypeData['steps'].'-Step '.((int) ($size / 1000)).'K',
            'account_size' => $size,
            'currency' => 'USD',
            'list_price' => $listPrice,
            'discounted_price' => $discountedPrice,
            'entry_fee' => $discountedPrice,
            'discount' => [
                'enabled' => $launchDiscount['enabled'],
                'type' => $launchDiscount['type'],
                'percent' => $launchDiscount['percent'],
                'amount' => $launchDiscount['enabled'] ? $listPrice - $discountedPrice : 0,
            ],
            'steps' => $challengeTypeData['steps'],
            'phases' => array_values($challengeTypeData['phases']),
            'funded' => $challengeTypeData['funded'],
            'profit_target' => $firstPhase['profit_target'],
            'daily_loss_limit' => $firstPhase['daily_loss_limit'],
            'max_loss_limit' => $firstPhase['max_loss_limit'],
            'profit_share' => $challengeTypeData['funded']['profit_split'],
            'first_payout_days' => $challengeTypeData['funded']['first_withdrawal_days'] ?? $challengeTypeData['funded']['payout_cycle_days'],
            'minimum_trading_days' => $firstPhase['minimum_trading_days'],
            'payout_cycle_days' => $challengeTypeData['funded']['payout_cycle_days'],
            'maximum_trading_days' => $firstPhase['maximum_trading_days'],
            'leverage' => $firstPhase['leverage'],
        ];

        $challengeCatalog[$challengeType]['plans'][$size] = $plan;
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

    'admin_auth' => [
        'username' => env('ADMIN_USERNAME', 'admin'),
        'password' => env('ADMIN_PASSWORD', 'wolforix-admin'),
        'realm' => env('ADMIN_REALM', 'Wolforix Admin'),
    ],

    'payments' => [
        'default_provider' => 'stripe',
        'providers' => [
            'stripe' => [
                'class' => $stripeProviderClass,
                'enabled' => true,
                'label' => 'Stripe',
                'description' => 'Secure card checkout powered by Stripe.',
                'coming_soon' => false,
            ],
            'paypal' => [
                'class' => null,
                'enabled' => false,
                'label' => 'PayPal',
                'description' => 'PayPal will be connected in a later milestone.',
                'coming_soon' => true,
            ],
        ],
    ],

    'client_statuses' => [
        'active',
        'cancelled',
        'completed',
    ],

    'trial' => [
        'starting_balance' => 10000,
        'account_type' => 'Trial (Demo)',
        'allowed_symbols' => [
            'XAU/USD',
            'EUR/USD',
            'USD/JPY',
        ],
        'display_rules' => [
            'daily_drawdown_limit' => 5,
            'max_drawdown_limit' => 10,
            'minimum_trading_days' => 3,
        ],
        'profit_milestones' => [
            3,
            5,
        ],
        'encouragement_after_days' => 3,
    ],

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

    'launch_discount' => $launchDiscount,

    'default_currency' => 'USD',

    'currencies' => $currencies,

    'challenge_models' => $challengeModels,

    'challenge_catalog' => $challengeCatalog,

    'challenge_sizes' => array_values($challengeSizes),

    'challenge_plans' => $challengePlans,
];
