<?php

return [
    'assistant' => [
        'name' => 'Wolfi',
        'eyebrow' => 'AI desk assistant',
        'title' => 'Wolfi supports your trading workspace',
        'description' => 'Ask Wolfi about MT5 sync, dashboard navigation, challenge rules, payout timing, performance metrics, and common support questions from the dedicated Wolfi Hub.',
        'avatar_asset' => 'newfolder/IMG_8542.png',
        'sources_title' => 'Grounded in Wolforix data',
        'sources_copy' => 'Wolfi answers from stored dashboard structure, challenge rules, payout logic, and support guidance instead of generic chatbot filler.',
        'response_label' => 'Live response',
        'response_hint' => 'Rule-aware, metric-aware, and ready for future voice playback.',
        'status_idle' => 'Ready to guide your next step',
        'status_thinking' => 'Wolfi is reviewing your dashboard context',
        'status_error' => 'Wolfi hit a temporary issue. Please try again.',
        'input_placeholder' => 'Ask about your dashboard, rules, payouts, metrics, or support...',
        'submit_label' => 'Ask Wolfi',
        'input_help' => 'Wolfi uses your current dashboard page and selected account when that data is available.',
        'voice_label' => 'Voice slot ready',
        'voice_copy' => 'Reserved for future approved voice samples and playback controls.',
    ],

    'pillars' => [
        [
            'title' => 'Platform guidance',
            'description' => 'Step-by-step help for overview, accounts, payouts, Wolfi Hub, and settings.',
        ],
        [
            'title' => 'Challenge rules',
            'description' => 'Explain drawdown, trading-day requirements, consistency, and pass or fail logic.',
        ],
        [
            'title' => 'Performance insights',
            'description' => 'Translate balance, equity, floating P&L, drawdown usage, and payout readiness.',
        ],
        [
            'title' => 'Support assistant',
            'description' => 'Handle common operational questions and point users to the right Wolforix flow.',
        ],
    ],

    'quick_actions' => [
        [
            'key' => 'dashboard',
            'label' => 'Explain my dashboard',
            'prompt' => 'Explain my dashboard',
        ],
        [
            'key' => 'rules',
            'label' => 'What are the challenge rules?',
            'prompt' => 'What are the challenge rules?',
        ],
        [
            'key' => 'metrics',
            'label' => 'Explain my metrics',
            'prompt' => 'Explain my metrics',
        ],
        [
            'key' => 'payouts',
            'label' => 'How do payouts work?',
            'prompt' => 'How do payouts work?',
        ],
        [
            'key' => 'consistency',
            'label' => 'What is the consistency rule?',
            'prompt' => 'What is the consistency rule?',
        ],
    ],

    'smart_insights' => [
        'title' => 'Smart Insights',
        'description' => 'Wolfi proactively watches your live account context and surfaces the signals that deserve attention before you even ask.',
        'thresholds' => [
            'drawdown_usage' => 70,
            'profit_target_progress' => 70,
            'consistency_ratio' => 35,
        ],
    ],

    'pages' => [
        'dashboard' => [
            'title' => 'Overview workspace',
            'summary' => 'Use this page to scan the account summary, challenge progress, payout readiness, analytics, daily activity, and trade details.',
            'sections' => [
                [
                    'title' => 'Account summary',
                    'description' => 'The hero section shows your current plan, challenge phase, sync freshness, balance, equity, and progress toward the target.',
                ],
                [
                    'title' => 'Command center',
                    'description' => 'Quickly check win ratio, first-trade timing, symbols traded, and the latest balance context.',
                ],
                [
                    'title' => 'Rule monitoring',
                    'description' => 'Track profit-target progress, daily loss usage, max drawdown usage, and trading-day completion.',
                ],
                [
                    'title' => 'Payout readiness',
                    'description' => 'See the next payout window, eligible profit, and the account status that controls payout timing.',
                ],
                [
                    'title' => 'Performance and trade history',
                    'description' => 'Review the performance chart, statistics, daily summary, and detailed trade panel for synced activity.',
                ],
            ],
        ],
        'dashboard.accounts' => [
            'title' => 'Accounts workspace',
            'summary' => 'Use this page to review live sync health, linked challenges, billing records, and account-level challenge progress.',
            'sections' => [
                [
                    'title' => 'Live sync',
                    'description' => 'MT5 connection status explains whether account metrics are updating correctly.',
                ],
                [
                    'title' => 'Challenge inventory',
                    'description' => 'Each account card summarizes balance, equity, status, progress, and the most important rule usage values.',
                ],
                [
                    'title' => 'Billing documents',
                    'description' => 'Invoices and purchase history stay attached to the dashboard for permanent download.',
                ],
            ],
        ],
        'dashboard.payouts' => [
            'title' => 'Payout workspace',
            'summary' => 'Use this page to understand the next withdrawal window, current eligibility, and the requirements that still need to be satisfied.',
            'sections' => [
                [
                    'title' => 'Eligibility cards',
                    'description' => 'The top cards show next payout timing, eligible profit, and the current payout status.',
                ],
                [
                    'title' => 'Timing notes',
                    'description' => 'The payout timeline explains first withdrawal timing and the recurring payout cycle.',
                ],
                [
                    'title' => 'Requirements',
                    'description' => 'This checklist keeps the funded-account rules and internal review conditions visible before payout requests.',
                ],
            ],
        ],
        'dashboard.wolfi' => [
            'title' => 'Wolfi Hub',
            'summary' => 'Use this page for the full Wolfi assistant, account-aware explanations, smart insights, support routes, and platform guidance.',
            'sections' => [
                [
                    'title' => 'Personal briefing',
                    'description' => 'Wolfi explains the selected account status, MT5 data, rules, progress, and payout context in plain language.',
                ],
                [
                    'title' => 'Smart prompts',
                    'description' => 'Quick actions help you ask about the dashboard, challenge rules, metrics, payouts, and consistency.',
                ],
                [
                    'title' => 'Support context',
                    'description' => 'Wolfi can point you toward billing, support, dashboard navigation, or the next operational step.',
                ],
            ],
        ],
        'dashboard.settings' => [
            'title' => 'Settings workspace',
            'summary' => 'Use this page to confirm profile details, preferred language and timezone, and the roadmap for account preferences and security actions.',
            'sections' => [
                [
                    'title' => 'Profile details',
                    'description' => 'Read-only profile fields show what is currently stored for your dashboard account.',
                ],
                [
                    'title' => 'Preferences',
                    'description' => 'This card previews where personal platform preferences will expand later.',
                ],
                [
                    'title' => 'Security',
                    'description' => 'This area is reserved for future security controls and account-protection actions.',
                ],
            ],
        ],
    ],

    'support' => [
        'common_topics' => [
            'Billing records stay in the dashboard and invoices remain downloadable after a successful purchase.',
            'Payout approval depends on funded status, rule compliance, and Wolforix review checks.',
            'If account data is missing, Wolfi can still explain the workflow and the likely next operational step.',
        ],
    ],

    'rules' => [
        'default_consistency_percent' => 40,
        'pass_fail_items' => [
            'Pass the current phase by reaching the profit target and minimum trading-day requirement without breaking the active loss rules.',
            'Fail when the daily loss or max drawdown limit is breached, or when Wolforix locks the account after a rule violation.',
        ],
    ],

    'voice' => [
        // Keep the placeholder wiring ready for a future voice milestone,
        // but do not expose the unfinished UI in the current dashboard.
        'placeholder_enabled' => false,
        'action_label' => 'Voice actions soon',
        'action_note' => 'The layout and response payloads already reserve a clean place for future playback controls and approved voice samples.',
    ],
];
