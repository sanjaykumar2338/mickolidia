<?php

return [
    'meta' => [
        'brand' => 'Wolforix',
        'default_title' => 'Wolforix Prop Firm Platform',
        'description' => 'Milestone 1 foundation for the Wolforix prop firm platform with premium dark branding, multilingual public pages, legal structure, and a dashboard preview.',
    ],

    'languages' => [
        'en' => 'English',
        'de' => 'German',
        'es' => 'Spanish',
    ],

    'locale' => [
        'current_label' => 'Language',
        'menu_title' => 'Select language',
        'future_label' => 'Ready for future additions',
    ],

    'public_layout' => [
        'preview_badge' => 'Milestone 1 foundation',
        'simulated_notice' => 'Simulated trading evaluation only',
    ],

    'nav' => [
        'home' => 'Home',
        'plans' => 'Plans',
        'faq' => 'FAQ',
        'legal' => 'Legal',
        'dashboard_preview' => 'Dashboard Preview',
    ],

    'home' => [
        'eyebrow' => 'Wolforix Prop Evaluation',
        'title' => 'Dark, premium challenge infrastructure for disciplined traders.',
        'description' => 'Milestone 1 establishes the Wolforix public presence and dashboard foundation with multilingual structure, legal clarity, payout messaging, and a clean prop-firm SaaS interface.',
        'primary_cta' => 'Start Challenge',
        'secondary_cta' => 'Open Dashboard Preview',
        'days' => 'days',
        'badges' => [
            'EN / DE / ES ready',
            'Consistency rule messaging included',
            'Stripe checkout prepared for later integration',
        ],
        'hero_panel' => [
            'title' => 'Core rule stack',
            'caption' => 'Gold is reserved for key brand moments, important actions, and critical account signals so the interface stays professional instead of poster-like.',
            'status' => 'cTrader first',
            'items' => [
                '8% profit target with a 2-step evaluation structure.',
                '5% daily loss limit surfaced near profit metrics.',
                '10% total loss guardrail prepared for future rule logic.',
                'Bi-weekly payout cadence and 3 minimum trading days.',
            ],
        ],
        'image_caption' => 'Branding integration',
        'image_copy' => 'The supplied client artwork drives the gold identity while the platform UI stays dark, restrained, and SaaS-focused.',
        'metrics' => [
            [
                'label' => 'Capital up to',
                'value' => '€100,000',
            ],
            [
                'label' => 'Profit share',
                'value' => '80%',
            ],
            [
                'label' => 'First withdrawal',
                'value' => '21 days',
            ],
            [
                'label' => 'Minimum trading days',
                'value' => '3',
            ],
        ],
        'challenge_selector' => [
            'type_label' => 'Challenge type',
            'size_label' => 'Account size',
            'insight_title' => 'Challenge overview',
            'entry_fee' => 'Challenge fee',
            'start_button' => 'Start Challenge',
            'profit_share_note' => 'Profit share: 80% / 85% for 100K Challenge.',
            'payout_cycle_note' => 'Payouts are processed in bi-weekly cycles with a maximum limit per cycle. Remaining eligible payouts will be processed in subsequent cycles.',
            'review_policy' => 'Review payout policy',
            'faq_link' => 'Read FAQ',
            'unlimited' => 'Unlimited',
            'highlights' => [
                'Instant challenge switching',
                '21-day first withdrawal',
                'No maximum trading days',
            ],
            'metrics' => [
                'profit_share' => 'Profit share',
                'daily_loss' => 'Daily loss limit',
                'total_loss' => 'Total loss limit',
                'minimum_days' => 'Minimum trading days',
                'first_withdrawal' => 'First withdrawal',
                'max_trading_days' => 'Maximum trading days',
            ],
            'types' => [
                'one_step' => [
                    'label' => '1-Step Challenge',
                    'description' => 'A direct evaluation route with tighter drawdown limits and a single progression step.',
                ],
                'two_step' => [
                    'label' => '2-Step Challenge',
                    'description' => 'A more traditional prop challenge structure with 2 evaluation phases and wider loss thresholds.',
                ],
            ],
        ],
        'plans' => [
            'eyebrow' => 'Challenge plans',
            'title' => 'Plan structure prepared for checkout, payout rules, and later Stripe integration.',
            'description' => 'The pricing layer is modular so live purchase flow, account creation, and payment gateways can connect cleanly in later milestones without replacing the public UI.',
            'badge' => 'Challenge / plans placeholder',
            'entry_fee' => 'Entry fee',
            'profit_target' => 'Profit target',
            'daily_loss' => 'Daily loss',
            'max_loss' => 'Total loss',
            'steps' => 'Steps',
            'profit_share' => 'Profit share',
            'first_payout' => 'First withdrawal',
            'minimum_days' => 'Minimum trading days',
        ],
        'foundation' => [
            'eyebrow' => 'Platform direction',
            'title' => 'Built for trust, rule visibility, and future automation.',
            'description' => 'This first milestone focuses on the structure required before live integrations: clean navigation, multilingual content, policy visibility, and a dashboard that feels operational instead of promotional.',
            'cards' => [
                [
                    'title' => 'Simulated evaluation first',
                    'description' => 'Public wording follows the client disclaimer: Wolforix operates as a proprietary trading evaluation and education company, not a broker or investment firm.',
                ],
                [
                    'title' => 'Payout safeguards visible early',
                    'description' => 'The consistency rule, bi-weekly payout cycle, progressive payout messaging, and review requirements are already surfaced in both the website and dashboard UI.',
                ],
                [
                    'title' => 'Multilingual from the start',
                    'description' => 'English is the default, while German and Spanish are structured from day one so future languages like Hindi, Italian, and Portuguese can be added cleanly.',
                ],
            ],
        ],
        'workflow' => [
            'eyebrow' => 'Milestone 1 scope',
            'title' => 'What this foundation already covers',
            'items' => [
                [
                    'title' => 'Public website core',
                    'description' => 'Landing page, branded challenge plan area, footer/legal structure, and an FAQ experience that uses the client-supplied questions and payout language.',
                ],
                [
                    'title' => 'Dashboard foundation',
                    'description' => 'Sidebar navigation, top header, summary cards, payout placeholder, settings placeholder, and the consistency rule warning banner are all wired with mock data.',
                ],
                [
                    'title' => 'Expansion-ready backend',
                    'description' => 'Locale middleware, reusable Blade layouts, MySQL-ready schema foundations, and seeded plan data prepare the project for Milestone 2 logic and integrations.',
                ],
            ],
        ],
    ],

    'checkout' => [
        'eyebrow' => 'Checkout foundation',
        'title' => 'Purchase UI stub with required legal confirmation.',
        'description' => 'This preview validates essential buyer inputs and the mandatory checkbox before payment. Live Stripe and PayPal processing stay intentionally out of scope for Milestone 1.',
        'supporting_title' => 'Before payment',
        'supporting_copy' => 'The wording below follows the exact client requirement and is enforced as a required agreement in this Milestone 1 checkout foundation.',
        'helper_points' => [
            'Validation is active for buyer name, email, plan selection, and the mandatory agreement checkbox.',
            'Stripe is represented as a placeholder so payment logic can connect cleanly later without redesigning the section.',
            'The consent text explicitly states that the challenge is a simulated trading evaluation.',
        ],
        'full_name' => 'Full name',
        'email' => 'Email address',
        'plan' => 'Challenge plan',
        'select_plan' => 'Select a plan',
        'platform' => 'Platform',
        'platform_value' => 'cTrader in Phase 1, MT4/MT5 later',
        'agreement' => 'I agree to the Terms & Conditions and understand that this is a simulated trading evaluation.',
        'submit' => 'Validate checkout stub',
        'stub_notice' => 'Checkout stub validated. Payment processing is intentionally deferred to a later milestone.',
        'buttons' => [
            'stripe' => 'Stripe connection placeholder',
            'paypal' => 'PayPal optional placeholder',
        ],
        'validation' => [
            'accept_terms' => 'You must accept the simulated evaluation agreement before continuing.',
        ],
    ],

    'faq' => [
        'eyebrow' => 'Frequently Asked Questions',
        'title' => 'Searchable answers built from the client brief.',
        'description' => 'Questions, compliance wording, payout rules, and dashboard behavior are structured directly from the client chat stored locally in this project.',
        'search_label' => 'Search',
        'search_placeholder' => 'Search FAQs...',
        'no_results' => 'No FAQ items matched that search.',
        'sections' => [
            [
                'title' => 'General',
                'items' => [
                    [
                        'question' => 'What is Wolforix?',
                        'answer' => 'Wolforix Ltd. is a proprietary trading evaluation and education company. All trading activities are conducted in a simulated environment for educational purposes.',
                    ],
                    [
                        'question' => 'Is this real money or simulated trading?',
                        'answer' => 'All accounts operate in a simulated trading environment. No real funds are allocated to users.',
                    ],
                    [
                        'question' => 'Who can participate?',
                        'answer' => 'Users must be at least 18 years old and comply with all applicable laws in their jurisdiction.',
                    ],
                ],
            ],
            [
                'title' => 'Trading Rules',
                'items' => [
                    [
                        'question' => 'What is the consistency rule?',
                        'answer' => 'No more than 40% of total profits can be generated in a single trading day. Profits must be spread across multiple trading days to be eligible for payout.',
                    ],
                    [
                        'question' => 'How is daily profit limit calculated?',
                        'answer' => 'The system compares today’s profit to your total account profit. If today’s profit exceeds 40%, it will trigger a dashboard warning and may affect your payout eligibility.',
                    ],
                    [
                        'question' => 'What are maximum drawdowns?',
                        'answer' => 'Each account has defined drawdown limits. Exceeding these limits may result in account disqualification from the evaluation program.',
                    ],
                ],
            ],
            [
                'title' => 'Payouts',
                'items' => [
                    [
                        'question' => 'How often are payouts processed?',
                        'answer' => 'Payouts are processed in bi-weekly cycles with a maximum limit per cycle. Remaining eligible payouts will be processed in subsequent cycles.',
                    ],
                    [
                        'question' => 'How is my payout calculated?',
                        'answer' => 'Payouts depend on total profits, consistency rule compliance, and internal payout limits. The amount eligible for withdrawal may be lower than total profits if daily limits are exceeded.',
                    ],
                    [
                        'question' => 'Why might my payout be split into multiple cycles?',
                        'answer' => 'To maintain long-term sustainability, payouts are distributed progressively. This ensures the company can honor all eligible profits without overextending funds.',
                    ],
                ],
            ],
            [
                'title' => 'Accounts / Dashboard',
                'items' => [
                    [
                        'question' => 'How do I see my profit and balance?',
                        'answer' => 'Your dashboard displays total profit, daily profit, and account balance in real time.',
                    ],
                    [
                        'question' => 'What happens if I approach the consistency limit?',
                        'answer' => 'A dashboard warning will appear: “⚠ You are approaching the consistency rule limit. Profits must be spread across multiple trading days to be eligible for payout.” Additionally, an automated email alert may be sent if you approach the critical threshold.',
                    ],
                    [
                        'question' => 'How can I request a payout?',
                        'answer' => 'The payout request button is in your dashboard. Only profits eligible under the consistency rule and payout limits will be included.',
                    ],
                ],
            ],
            [
                'title' => 'Support / Contact',
                'items' => [
                    [
                        'question' => 'How do I contact support?',
                        'answer' => 'All support requests are handled via email or ticket system within your dashboard.',
                    ],
                    [
                        'question' => 'Do you have a phone number?',
                        'answer' => 'No, Wolforix Ltd. does not provide phone support. All communications are documented via email or tickets for compliance and safety reasons.',
                    ],
                ],
            ],
            [
                'title' => 'Legal / Compliance',
                'items' => [
                    [
                        'question' => 'Do I need to verify my identity?',
                        'answer' => 'Yes, identity verification (KYC) may be required before processing payouts to comply with anti-money laundering regulations.',
                    ],
                    [
                        'question' => 'Are there rules for fraud or abuse?',
                        'answer' => 'Any attempt to manipulate the system, exploit loopholes, or commit fraudulent activity will result in account termination and may be reported to authorities.',
                    ],
                ],
            ],
        ],
    ],

    'legal' => [
        'eyebrow' => 'Wolforix Legal',
        'quick_links' => 'All legal pages',
        'overview_title' => 'Policy overview',
        'overview_copy' => 'These pages format the client-approved Milestone 1 wording into readable dedicated screens instead of placing long legal text directly in the footer.',
        'link_labels' => [
            'terms' => 'Terms & Conditions',
            'risk_disclosure' => 'Risk Disclosure',
            'payout_policy' => 'Payout Policy',
            'refund_policy' => 'Refund Policy',
            'privacy_policy' => 'Privacy Policy',
            'aml_kyc_policy' => 'AML & KYC Policy',
            'company_information' => 'Company Information',
        ],
        'pages' => [
            'terms' => [
                'title' => 'Terms & Conditions',
                'intro' => 'Wolforix Ltd. operates as a proprietary trading evaluation and educational platform. By accessing our services, you agree to the following terms.',
                'sections' => [
                    [
                        'title' => 'Nature of Services',
                        'paragraphs' => [
                            'All trading activities are conducted in a simulated environment. No real funds are allocated to users, and no investment services are provided.',
                        ],
                    ],
                    [
                        'title' => 'Eligibility',
                        'paragraphs' => [
                            'Users must be at least 18 years old and comply with all applicable laws in their jurisdiction.',
                        ],
                    ],
                    [
                        'title' => 'Evaluation Program',
                        'paragraphs' => [
                            'Users participate in a trading evaluation designed to assess trading skills. Successful completion does not constitute employment or investment allocation.',
                        ],
                    ],
                    [
                        'title' => 'Trading Rules',
                        'paragraphs' => [
                            'Users must adhere to all trading rules, including maximum drawdown, daily loss limits, and consistency requirements.',
                        ],
                    ],
                    [
                        'title' => 'Consistency Rule',
                        'paragraphs' => [
                            'To qualify for payouts, no more than 40% of total profits may be generated in a single trading day. If exceeded, additional trading activity is required.',
                        ],
                    ],
                    [
                        'title' => 'Prohibited Practices',
                        'paragraphs' => [
                            'Any form of abuse, arbitrage exploitation, or manipulation of the trading environment will result in account termination.',
                        ],
                    ],
                    [
                        'title' => 'Limitation of Liability',
                        'paragraphs' => [
                            'Wolforix Ltd. shall not be liable for any losses, damages, or inability to use the platform.',
                        ],
                    ],
                ],
            ],
            'risk_disclosure' => [
                'title' => 'Risk Disclosure',
                'intro' => 'Trading financial markets involves significant risk. All activities on this platform are conducted in a simulated environment for educational purposes only.',
                'sections' => [
                    [
                        'title' => 'Simulated Trading Environment',
                        'paragraphs' => [
                            'All trading activities provided by Wolforix take place in a simulated environment using virtual funds. These funds are fictitious, have no monetary value, and cannot be withdrawn, transferred, or used for real market trading.',
                            'Any funded accounts provided are part of a simulated evaluation program. They do not represent real capital, and no trades are executed in live financial markets.',
                        ],
                    ],
                    [
                        'title' => 'No Investment Services',
                        'paragraphs' => [
                            'Wolforix Ltd. does not provide investment advice, portfolio management, brokerage services, or custody of client funds.',
                            'Nothing on this website constitutes financial advice, an investment recommendation, or an offer to buy or sell any financial instrument.',
                        ],
                    ],
                    [
                        'title' => 'Performance & Payouts',
                        'paragraphs' => [
                            'Past performance does not guarantee future results. Hypothetical results have inherent limitations and may differ from real market conditions due to factors such as liquidity, slippage, and execution delays.',
                            'Any payouts, testimonials, or performance examples shown are illustrative only and do not guarantee future results.',
                        ],
                    ],
                    [
                        'title' => 'Restricted Jurisdictions & Liability',
                        'paragraphs' => [
                            'Our services are not available in jurisdictions where such use would violate local laws or regulations.',
                            'Wolforix shall not be held liable for any direct or indirect losses arising from the use of its platform, services, or information provided.',
                        ],
                    ],
                ],
            ],
            'payout_policy' => [
                'title' => 'Payout Policy',
                'intro' => 'Payouts are processed in bi-weekly cycles with a maximum limit per cycle. Remaining eligible payouts will be processed in subsequent cycles.',
                'sections' => [
                    [
                        'title' => 'Payout Eligibility',
                        'paragraphs' => [
                            'Payouts are processed in bi-weekly cycles with a maximum limit per cycle. Remaining eligible payouts will be processed in subsequent cycles.',
                            'Payout eligibility requires compliance with the consistency rule, ensuring profits are distributed across multiple trading days.',
                            'To ensure long-term sustainability, payouts may be distributed progressively across multiple payout cycles.',
                        ],
                    ],
                    [
                        'title' => 'Eligibility Requirements',
                        'bullets' => [
                            'Minimum trading days must be met.',
                            'Consistency rule must be satisfied.',
                            'No rule violations may be present on the account.',
                        ],
                    ],
                    [
                        'title' => 'Review & Approval',
                        'paragraphs' => [
                            'Wolforix Ltd. reserves the right to review all trading activity before approving payouts.',
                        ],
                    ],
                ],
            ],
            'refund_policy' => [
                'title' => 'Refund Policy',
                'intro' => 'All purchases are final and non-refundable once the trading challenge has been accessed.',
                'sections' => [
                    [
                        'title' => 'General Rule',
                        'paragraphs' => [
                            'All purchases are final and non-refundable once the trading challenge has been accessed.',
                        ],
                    ],
                    [
                        'title' => 'Limited Exceptions',
                        'paragraphs' => [
                            'Refunds may only be issued in cases of technical errors or duplicate payments.',
                        ],
                    ],
                ],
            ],
            'privacy_policy' => [
                'title' => 'Privacy Policy',
                'intro' => 'Wolforix Ltd. collects and processes personal data in accordance with applicable data protection laws.',
                'sections' => [
                    [
                        'title' => 'Data Use',
                        'paragraphs' => [
                            'User data is used for account management, verification, and compliance purposes.',
                        ],
                    ],
                    [
                        'title' => 'Data Sharing',
                        'paragraphs' => [
                            'We do not sell or share personal data with third parties without consent.',
                        ],
                    ],
                ],
            ],
            'aml_kyc_policy' => [
                'title' => 'AML & KYC Policy',
                'intro' => 'To comply with anti-money laundering regulations, users may be required to verify their identity before receiving payouts.',
                'sections' => [
                    [
                        'title' => 'Verification Requirement',
                        'paragraphs' => [
                            'To comply with anti-money laundering regulations, users may be required to verify their identity before receiving payouts.',
                        ],
                    ],
                    [
                        'title' => 'Documentation Requests',
                        'paragraphs' => [
                            'Wolforix Ltd. reserves the right to request documentation at any time.',
                        ],
                    ],
                    [
                        'title' => 'Non-Compliance',
                        'paragraphs' => [
                            'Failure to comply may result in account suspension.',
                        ],
                    ],
                ],
            ],
            'company_information' => [
                'title' => 'Company Information',
                'intro' => 'Wolforix Ltd. is a company incorporated in the United Kingdom, with its registered office in London, United Kingdom.',
                'sections' => [
                    [
                        'title' => 'Company Information',
                        'paragraphs' => [
                            'Wolforix Ltd. is a company incorporated in the United Kingdom, with its registered office in London, United Kingdom.',
                        ],
                    ],
                    [
                        'title' => 'Nature of Services',
                        'paragraphs' => [
                            'Wolforix operates as a proprietary trading evaluation and education company. We are not a broker, financial institution, investment firm, or custodian.',
                            'We do not accept deposits, manage client funds, or execute trades on behalf of users.',
                        ],
                    ],
                    [
                        'title' => 'Regulatory Notice',
                        'paragraphs' => [
                            'Wolforix operates outside the scope of financial regulatory authorities as it does not provide brokerage or investment services.',
                            'Users are responsible for ensuring compliance with local laws before using our services.',
                        ],
                    ],
                ],
            ],
        ],
    ],

    'footer' => [
        'disclaimer_title' => 'Simulated environment',
        'company_title' => 'Footer & legal structure',
        'summary' => 'Wolforix Ltd. operates as a proprietary trading evaluation and education company. All trading activities take place in a simulated environment using virtual funds and do not represent brokerage or investment services.',
        'service_copy' => 'Wolforix does not accept deposits, manage client funds, or execute trades on behalf of users. Fees are for software access, evaluation services, and educational tools only.',
        'legal_title' => 'Legal & Policies',
        'operations_title' => 'Operations',
        'operations_copy' => 'Support is handled through email and later dashboard ticketing. Manual withdrawals remain admin-reviewed, and payout approval depends on rule compliance.',
        'simulated_notice' => 'Any funded account shown in this interface is part of a simulated evaluation program. No real capital is traded in live financial markets.',
        'company_location' => 'Wolforix Ltd. | London, United Kingdom',
        'copyright' => 'All rights reserved.',
    ],

    'fixed_disclaimer' => [
        'label' => 'Simulated environment notice',
        'text' => 'Wolforix operates in a simulated trading environment. Review the FAQ and payout policy before purchasing a challenge.',
        'faq_link' => 'FAQ',
        'policy_link' => 'Payout Policy',
    ],

    'dashboard' => [
        'preview_title' => 'Dashboard Foundation',
        'preview_subtitle' => 'Mock data only. The layout is prepared for live challenge sync, payout logic, and future platform integrations.',
        'sidebar_label' => 'Trader workspace',
        'simulated_badge' => 'Simulated evaluation account view',
        'status_badge' => 'Interval sync preview',
        'nav' => [
            'overview' => 'Overview',
            'accounts' => 'Accounts',
            'payouts' => 'Payouts',
            'settings' => 'Settings',
        ],
        'cards' => [
            'balance' => 'Account Balance',
            'total_profit' => 'Total Profit',
            'today_profit' => 'Today Profit',
            'drawdown' => 'Drawdown',
        ],
        'card_hints' => [
            'balance' => 'Mock current balance for the selected evaluation account.',
            'total_profit' => 'Total simulated profit currently tracked on the account.',
            'today_profit' => 'Today’s result is used for the consistency rule warning.',
            'drawdown' => 'Visual placeholder for daily and total risk monitoring.',
        ],
        'account' => [
            'stage' => 'Challenge Step 1',
            'status' => 'Active',
            'next_sync' => 'Mock sync every 5 minutes',
            'review_status' => 'Under review',
            'review_stage' => 'Payout review',
        ],
        'consistency' => [
            'title' => 'Consistency rule warning',
            'message' => '⚠ You are approaching the consistency rule limit. Profits must be spread across multiple trading days to be eligible for payout.',
            'meta' => [
                'today_profit' => 'Today’s profit',
                'limit' => 'Consistency limit',
                'usage' => 'Limit usage',
            ],
        ],
        'labels' => [
            'reference' => 'Account reference',
            'platform' => 'Platform',
            'stage' => 'Stage',
            'next_sync' => 'Next sync',
            'target' => 'Profit target',
            'daily_loss' => 'Daily loss',
            'max_loss' => 'Total loss',
            'min_days' => 'Minimum trading days',
            'cycle' => 'Payout cycle',
            'eligible_profit' => 'Eligible profit',
            'progress' => 'Progress',
        ],
        'overview' => [
            'snapshot_title' => 'Account snapshot',
            'snapshot_copy' => 'This area is ready for future cTrader, MT4, and MT5 sync connections. Milestone 1 intentionally uses demo values only.',
            'rules_title' => 'Rule stack',
            'rules_copy' => 'The most important challenge limits stay near the account metrics so payout eligibility and risk boundaries remain obvious.',
            'payout_title' => 'Payout section',
            'payout_copy' => 'Payout previews already reflect bi-weekly cycles, maximum-per-cycle wording, progressive follow-up cycles, and internal review placeholders.',
            'settings_title' => 'Profile & settings',
            'settings_copy' => 'A placeholder profile area keeps the dashboard ready for language preferences, KYC, and account security controls.',
        ],
        'accounts_page' => [
            'title' => 'Trading Accounts',
            'subtitle' => 'Mock account cards structured for future live sync and evaluation status management.',
        ],
        'payouts_page' => [
            'title' => 'Payouts',
            'subtitle' => 'Eligibility messaging aligned to the payout policy and the client’s consistency rule brief.',
        ],
        'settings_page' => [
            'title' => 'Settings',
            'subtitle' => 'Profile, localization, and compliance structure prepared for future user management.',
        ],
        'payouts' => [
            'next_window' => 'Next payout window',
            'next_window_value' => 'Next bi-weekly review in 3 days',
            'cycle_note' => 'Payouts are processed in bi-weekly cycles with a maximum limit per cycle. Remaining eligible payouts will be processed in subsequent cycles.',
            'placeholder_status' => 'Manual review placeholder',
            'queue_title' => 'Payout queue preview',
            'queue_copy' => 'Bi-weekly cycles, maximum-per-cycle limits, progressive follow-up cycles, and internal review checks are represented here without live payout engine logic.',
            'progressive_note' => 'To support long-term sustainability, payouts may be distributed progressively across multiple payout cycles.',
            'requirements_title' => 'Eligibility checklist',
            'requirements' => [
                'Minimum trading days must be met.',
                'Consistency rule must be satisfied before profit becomes payout-eligible.',
                'No rule violations may be present on the account.',
                'All payout requests remain subject to internal trade review.',
            ],
            'cta' => 'Payout request placeholder',
        ],
        'settings' => [
            'profile_title' => 'Profile placeholder',
            'language_label' => 'Preferred language',
            'timezone_label' => 'Timezone',
            'preferences_title' => 'Language & localization',
            'preferences_copy' => 'The dashboard is already locale-aware so English, German, and Spanish can switch cleanly while future languages are added later.',
            'security_title' => 'Compliance & security placeholders',
            'security_copy' => 'KYC requests, AML controls, audit logging, and sensitive credential handling are reserved for later milestones.',
            'save' => 'Save in later milestone',
        ],
    ],
];
