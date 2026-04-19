<?php

$stripeProviderClass = \App\Services\Payments\StripePaymentGateway::class;
$payPalProviderClass = \App\Services\Payments\PayPalGateway::class;
$payPalConfigured = filled((string) env('PAYPAL_CLIENT_ID')) && filled((string) env('PAYPAL_CLIENT_SECRET'));

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
    'hi' => [
        'native' => 'हिंदी',
        'short' => 'HI',
        'flag' => '🇮🇳',
    ],
    'it' => [
        'native' => 'Italiano',
        'short' => 'IT',
        'flag' => '🇮🇹',
    ],
    'pt' => [
        'native' => 'Português',
        'short' => 'PT',
        'flag' => '🇵🇹',
    ],
];

$launchDiscount = [
    'enabled' => true,
    'type' => 'percentage',
    'percent' => 20,
    'code' => env('LAUNCH_PROMO_CODE', 'Wolforix2026'),
    'badge' => '20% OFF - Limited Launch Offer',
    'urgency_text' => 'Launch Discount - Limited Time Only',
];

$currencies = [
    'USD' => [
        'rate' => 1,
        'symbol' => '$',
        'flag' => '🇺🇸',
    ],
    'EUR' => [
        'rate' => 0.92,
        'symbol' => '€',
        'flag' => '🇪🇺',
    ],
    'GBP' => [
        'rate' => 0.78,
        'symbol' => '£',
        'flag' => '🇬🇧',
    ],
];

$challengeModels = [
    'one_step' => [
        'label' => '1-Step Instant',
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
        'funded_overrides' => [
            100000 => [
                'profit_split' => 85,
                'profit_split_upgrade' => [
                    'after_consecutive_payouts' => 2,
                    'profit_split' => 90,
                ],
            ],
        ],
    ],
    'two_step' => [
        'label' => '2-Step Pro',
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
        'funded_overrides' => [
            100000 => [
                'profit_split' => 85,
                'profit_split_upgrade' => [
                    'after_consecutive_payouts' => 2,
                    'profit_split' => 90,
                ],
            ],
        ],
    ],
];

$challengeCatalog = [];

$challengeSizes = [];
$challengePlans = [];

foreach ($challengeModels as $challengeType => $challengeTypeData) {
    $challengeCatalog[$challengeType] = [
        'label' => $challengeTypeData['label'],
        'steps' => $challengeTypeData['steps'],
        'phases' => array_values($challengeTypeData['phases']),
        'funded' => $challengeTypeData['funded'],
        'plans' => [],
    ];

    foreach ($challengeTypeData['pricing'] as $size => $listPrice) {
        $fundedRules = array_merge(
            $challengeTypeData['funded'],
            $challengeTypeData['funded_overrides'][$size] ?? [],
        );
        $discountedPrice = $launchDiscount['enabled']
            ? (int) round($listPrice * ((100 - $launchDiscount['percent']) / 100))
            : $listPrice;

        $firstPhase = $challengeTypeData['phases'][0];
        $plan = [
            'slug' => str_replace('_', '-', $challengeType).'-'.$size,
            'name' => $challengeTypeData['label'].' '.((int) ($size / 1000)).'K',
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
            'funded' => $fundedRules,
            'profit_target' => $firstPhase['profit_target'],
            'daily_loss_limit' => $firstPhase['daily_loss_limit'],
            'max_loss_limit' => $firstPhase['max_loss_limit'],
            'profit_share' => $fundedRules['profit_split'],
            'first_payout_days' => $fundedRules['first_withdrawal_days'] ?? $fundedRules['payout_cycle_days'],
            'minimum_trading_days' => $firstPhase['minimum_trading_days'],
            'payout_cycle_days' => $fundedRules['payout_cycle_days'],
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
    'default_currency' => 'USD',

    'support' => [
        'email' => env('SUPPORT_EMAIL', 'support@wolforix.com'),
        'business_hours' => env('SUPPORT_BUSINESS_HOURS', 'Mon-Fri, 09:00-18:00 UTC'),
    ],

    'mt5_account_pool' => [
        'default_client_source' => env('WOLFORIX_MT5_CLIENT_POOL_SOURCE', 'public/Accounts List 2 Wolforix.ods'),
        'default_pool' => env('WOLFORIX_MT5_DEFAULT_POOL', 'client_pool'),
    ],

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
                'class' => $payPalProviderClass,
                'enabled' => $payPalConfigured,
                'label' => 'PayPal',
                'description' => 'Checkout with PayPal account approval and server-side order capture.',
                'coming_soon' => ! $payPalConfigured,
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
            'profit_target' => 8,
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

    'future_locales' => [],

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

    'economic_calendar' => [
        'provider' => env('ECONOMIC_CALENDAR_PROVIDER', 'tradingeconomics'),
        'display_timezone' => env('ECONOMIC_CALENDAR_TIMEZONE', 'Europe/Berlin'),
        'default_range' => env('ECONOMIC_CALENDAR_DEFAULT_RANGE', 'this_week'),
    ],

    'launch_discount' => $launchDiscount,

    'default_currency' => 'USD',

    'currencies' => $currencies,

    'challenge_models' => $challengeModels,

    'challenge_catalog' => $challengeCatalog,

    'challenge_sizes' => array_values($challengeSizes),

    'challenge_plans' => $challengePlans,
];
