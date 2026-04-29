<?php

$images = [
    'homepage' => 'new-wolfy.webp',
    'homepage_right' => 'new-wolfy.webp',
    'dashboard' => 'new-wolfy.webp',
    'talk' => 'new-wolfy.webp',
    'shortcut' => 'new-wolfy.webp',
];

return [
    'images' => $images,

    'assistant' => [
        'name' => 'Wolfi',
        'eyebrow' => 'Wolfi supports your',
        'title' => 'Trading workspace',
        'description' => 'Ready to support your next step inside Wolfi Hub without taking over the dashboard workspace.',
        'avatar_asset' => $images['dashboard'],
        'sources_title' => 'Live response',
        'sources_copy' => 'Rule-aware, metric-aware, and ready for future voice playback.',
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
            'title' => 'Rule-aware',
            'description' => 'Guidance based on platform rules.',
        ],
        [
            'title' => 'Metric-aware',
            'description' => 'Insights that track what matters.',
        ],
        [
            'title' => 'Payout timing',
            'description' => 'Stay on track with payout schedules.',
        ],
        [
            'title' => 'Always ready',
            'description' => 'Get instant support when you need it.',
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
        'dashboard.wolfi.voices' => [
            'title' => 'Wolfi Voices',
            'summary' => 'Use this page to compare Wolfi voices, play the same sample phrase, and save one platform voice for future speech playback.',
            'sections' => [
                [
                    'title' => 'Voice comparison cards',
                    'description' => 'Each voice card includes a short profile and one-click preview playback.',
                ],
                [
                    'title' => 'Single platform selection',
                    'description' => 'Only one voice can be active at a time and the selected card is highlighted.',
                ],
                [
                    'title' => 'Saved for platform speech',
                    'description' => 'The selected voice is stored in platform settings and used as the default for Wolfi TTS generation.',
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

    'voices' => [
        'provider' => env('WOLFI_TTS_PROVIDER', 'elevenlabs'),
        'default' => env('WOLFI_TTS_VOICE', 'elevenlabs-david'),
        'sample_text' => "Hello, I'm Wolfi. I can help guide you through your dashboard, rules, payouts, and next steps.",
        'options' => [
            [
                'id' => 'elevenlabs-david',
                'name' => 'David - Intense, Rapid, and Expressive',
                'provider' => 'elevenlabs',
                'provider_label' => 'ElevenLabs',
                'provider_voice_id' => env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER'),
                'locale_voice_ids' => [
                    'en' => env('ELEVENLABS_VOICE_ID_EN', env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER')),
                    'de' => env('ELEVENLABS_VOICE_ID_DE', env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER')),
                    'es' => env('ELEVENLABS_VOICE_ID_ES', env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER')),
                    'fr' => env('ELEVENLABS_VOICE_ID_FR', env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER')),
                    'hi' => env('ELEVENLABS_VOICE_ID_HI', env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER')),
                    'it' => env('ELEVENLABS_VOICE_ID_IT', env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER')),
                    'pt' => env('ELEVENLABS_VOICE_ID_PT', env('ELEVENLABS_VOICE_ID', 'id7LQ3n0ft94moeTT1ER')),
                ],
                'label' => 'Expressive multilingual Wolfi voice',
                'description' => 'Selected ElevenLabs David voice using the multilingual model for natural Wolfi speech.',
            ],
            [
                'id' => 'webspeech-en-guide',
                'name' => 'Web Speech Guide',
                'provider' => 'web_speech',
                'provider_label' => 'Web Speech API (Browser Native)',
                'provider_voice_id' => 'en-US',
                'locale_voice_ids' => [
                    'en' => 'en-US',
                    'de' => 'de-DE',
                    'es' => 'es-ES',
                    'fr' => 'fr-FR',
                    'hi' => 'hi-IN',
                    'it' => 'it-IT',
                    'pt' => 'pt-PT',
                ],
                'label' => 'Always-free browser voice',
                'description' => 'Uses your browser-native speech engine for instant free previews.',
            ],
            [
                'id' => 'ash',
                'name' => 'Ash',
                'provider' => 'web_speech',
                'provider_label' => 'Web Speech API (Browser Native)',
                'provider_voice_id' => 'en-US',
                'locale_voice_ids' => [
                    'en' => 'en-US',
                    'de' => 'de-DE',
                    'es' => 'es-ES',
                    'fr' => 'fr-FR',
                    'hi' => 'hi-IN',
                    'it' => 'it-IT',
                    'pt' => 'pt-PT',
                ],
                'label' => 'Legacy profile',
                'description' => 'Legacy Wolfi profile restored for side-by-side comparison.',
            ],
            [
                'id' => 'ballad',
                'name' => 'Ballad',
                'provider' => 'web_speech',
                'provider_label' => 'Web Speech API (Browser Native)',
                'provider_voice_id' => 'en-US',
                'locale_voice_ids' => [
                    'en' => 'en-US',
                    'de' => 'de-DE',
                    'es' => 'es-ES',
                    'fr' => 'fr-FR',
                    'hi' => 'hi-IN',
                    'it' => 'it-IT',
                    'pt' => 'pt-PT',
                ],
                'label' => 'Legacy profile',
                'description' => 'Legacy Wolfi profile restored for side-by-side comparison.',
            ],
            [
                'id' => 'echo',
                'name' => 'Echo',
                'provider' => 'web_speech',
                'provider_label' => 'Web Speech API (Browser Native)',
                'provider_voice_id' => 'en-US',
                'locale_voice_ids' => [
                    'en' => 'en-US',
                    'de' => 'de-DE',
                    'es' => 'es-ES',
                    'fr' => 'fr-FR',
                    'hi' => 'hi-IN',
                    'it' => 'it-IT',
                    'pt' => 'pt-PT',
                ],
                'label' => 'Legacy profile',
                'description' => 'Legacy Wolfi profile restored for side-by-side comparison.',
            ],
            [
                'id' => 'nova',
                'name' => 'Nova',
                'provider' => 'web_speech',
                'provider_label' => 'Web Speech API (Browser Native)',
                'provider_voice_id' => 'en-US',
                'locale_voice_ids' => [
                    'en' => 'en-US',
                    'de' => 'de-DE',
                    'es' => 'es-ES',
                    'fr' => 'fr-FR',
                    'hi' => 'hi-IN',
                    'it' => 'it-IT',
                    'pt' => 'pt-PT',
                ],
                'label' => 'Legacy profile',
                'description' => 'Legacy Wolfi profile restored for side-by-side comparison.',
            ],
            [
                'id' => 'shimmer',
                'name' => 'Shimmer',
                'provider' => 'web_speech',
                'provider_label' => 'Web Speech API (Browser Native)',
                'provider_voice_id' => 'en-US',
                'locale_voice_ids' => [
                    'en' => 'en-US',
                    'de' => 'de-DE',
                    'es' => 'es-ES',
                    'fr' => 'fr-FR',
                    'hi' => 'hi-IN',
                    'it' => 'it-IT',
                    'pt' => 'pt-PT',
                ],
                'label' => 'Legacy profile',
                'description' => 'Legacy Wolfi profile restored for side-by-side comparison.',
            ],
            [
                'id' => 'elevenlabs-adam',
                'name' => 'ElevenLabs Adam',
                'provider' => 'elevenlabs',
                'provider_label' => 'ElevenLabs (Free Tier)',
                'provider_voice_id' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB'),
                'locale_voice_ids' => [
                    'en' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID_EN', env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB')),
                    'de' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID_DE', env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB')),
                    'es' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID_ES', env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB')),
                    'fr' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID_FR', env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB')),
                    'hi' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID_HI', env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB')),
                    'it' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID_IT', env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB')),
                    'pt' => env('WOLFI_ELEVENLABS_ADAM_VOICE_ID_PT', env('WOLFI_ELEVENLABS_ADAM_VOICE_ID', 'pNInz6obpgDQGcFmaJgB')),
                ],
                'label' => 'Studio-quality narration',
                'description' => 'Cloud synthesis with a generous free monthly character allowance.',
            ],
            [
                'id' => 'google-neural2-d',
                'name' => 'Google Neural2 D',
                'provider' => 'google_cloud',
                'provider_label' => 'Google Cloud Text-to-Speech (Free Tier)',
                'provider_voice_id' => env('WOLFI_GOOGLE_TTS_VOICE', 'en-US-Neural2-D'),
                'locale_voice_ids' => [
                    'en' => env('WOLFI_GOOGLE_TTS_VOICE_EN', env('WOLFI_GOOGLE_TTS_VOICE', 'en-US-Neural2-D')),
                    'de' => env('WOLFI_GOOGLE_TTS_VOICE_DE', 'de-DE-Neural2-B'),
                    'es' => env('WOLFI_GOOGLE_TTS_VOICE_ES', 'es-ES-Neural2-B'),
                    'fr' => env('WOLFI_GOOGLE_TTS_VOICE_FR', 'fr-FR-Neural2-B'),
                    'hi' => env('WOLFI_GOOGLE_TTS_VOICE_HI', 'hi-IN-Neural2-B'),
                    'it' => env('WOLFI_GOOGLE_TTS_VOICE_IT', 'it-IT-Neural2-C'),
                    'pt' => env('WOLFI_GOOGLE_TTS_VOICE_PT', 'pt-PT-Neural2-B'),
                ],
                'label' => 'Neural clarity',
                'description' => 'Google neural voice tuned for crisp, clean dashboard narration.',
            ],
            [
                'id' => 'azure-guy-neural',
                'name' => 'Azure Guy Neural',
                'provider' => 'azure_speech',
                'provider_label' => 'Microsoft Azure Speech (Free Tier)',
                'provider_voice_id' => env('WOLFI_AZURE_TTS_VOICE', 'en-US-GuyNeural'),
                'locale_voice_ids' => [
                    'en' => env('WOLFI_AZURE_TTS_VOICE_EN', env('WOLFI_AZURE_TTS_VOICE', 'en-US-GuyNeural')),
                    'de' => env('WOLFI_AZURE_TTS_VOICE_DE', 'de-DE-ConradNeural'),
                    'es' => env('WOLFI_AZURE_TTS_VOICE_ES', 'es-ES-AlvaroNeural'),
                    'fr' => env('WOLFI_AZURE_TTS_VOICE_FR', 'fr-FR-HenriNeural'),
                    'hi' => env('WOLFI_AZURE_TTS_VOICE_HI', 'hi-IN-MadhurNeural'),
                    'it' => env('WOLFI_AZURE_TTS_VOICE_IT', 'it-IT-DiegoNeural'),
                    'pt' => env('WOLFI_AZURE_TTS_VOICE_PT', 'pt-PT-DuarteNeural'),
                ],
                'label' => 'Balanced enterprise tone',
                'description' => 'Natural voice from Azure Speech using SSML-based synthesis.',
            ],
            [
                'id' => 'amazon-polly-joey',
                'name' => 'Amazon Polly Joey',
                'provider' => 'amazon_polly',
                'provider_label' => 'Amazon Polly (Free Tier)',
                'provider_voice_id' => env('WOLFI_POLLY_VOICE', 'Joey'),
                'label' => 'AWS free-tier option',
                'description' => 'Reserved for Polly setup. Falls back to browser preview until enabled.',
            ],
            [
                'id' => 'kokoro-local',
                'name' => 'Kokoro-82M Local',
                'provider' => 'kokoro_82m',
                'provider_label' => 'Kokoro-82M (Open Source)',
                'provider_voice_id' => env('WOLFI_KOKORO_VOICE', 'en_male'),
                'label' => 'Open-source self-hosted',
                'description' => 'Use your own hosted Kokoro endpoint for private voice generation.',
            ],
            [
                'id' => 'sherpa-local',
                'name' => 'Sherpa ONNX Local',
                'provider' => 'sherpa_onnx',
                'provider_label' => 'Sherpa-ONNX (Open Source)',
                'provider_voice_id' => env('WOLFI_SHERPA_VOICE', 'en-us'),
                'label' => 'Realtime local synthesis',
                'description' => 'For self-hosted ONNX pipelines. Browser fallback is used by default.',
            ],
            [
                'id' => 'responsivevoice-us-male',
                'name' => 'ResponsiveVoice US Male',
                'provider' => 'responsive_voice',
                'provider_label' => 'ResponsiveVoice.js',
                'provider_voice_id' => env('WOLFI_RESPONSIVE_VOICE', 'US English Male'),
                'label' => 'Client-side quick preview',
                'description' => 'Client playback option useful for non-commercial voice testing.',
            ],
        ],
    ],
];
