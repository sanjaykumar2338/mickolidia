<?php

namespace App\Services\Wolfi;

use App\Models\TradingAccount;
use App\Models\User;

class WolfiAssistantService
{
    public function __construct(
        private readonly WolfiKnowledgeBase $knowledgeBase,
        private readonly WolfiPromptContextBuilder $contextBuilder,
        private readonly WolfiInsightService $insightService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panelData(User $user, ?TradingAccount $account, string $page): array
    {
        $context = $this->contextBuilder->build($user, $account, $page);

        return [
            'assistant' => $this->knowledgeBase->assistantMeta(),
            'pillars' => $this->knowledgeBase->pillars(),
            'quick_actions' => $this->knowledgeBase->quickActions(),
            'voice' => $this->knowledgeBase->voiceMeta(),
            'smart_insights' => $this->knowledgeBase->smartInsights(),
            'insights' => $this->insightService->generate($context),
            'page' => $context['page'],
            'endpoint' => route('dashboard.wolfi.respond'),
            'account_id' => $context['account']['id'] ?? null,
            'welcome' => $this->welcomeResponse($context),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function respond(User $user, ?TradingAccount $account, string $page, string $message): array
    {
        $context = $this->contextBuilder->build($user, $account, $page);
        $intent = $this->detectIntent($message);

        $response = match ($intent) {
            'consistency' => $this->consistencyResponse($context),
            'challenge_rules' => $this->challengeRulesResponse($context),
            'performance_insights' => $this->performanceInsightsResponse($context),
            'payouts' => $this->payoutResponse($context),
            'support' => $this->supportResponse($context),
            default => $this->platformGuidanceResponse($context),
        };

        return [
            ...$response,
            'intent' => $intent,
            'voice' => [
                'placeholder_enabled' => (bool) data_get($context, 'voice.placeholder_enabled', true),
                'action_label' => (string) data_get($context, 'voice.action_label', 'Voice actions soon'),
                'action_note' => (string) data_get($context, 'voice.action_note', ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function welcomeResponse(array $context): array
    {
        $account = $context['account'] ?? null;
        $pageTitle = (string) data_get($context, 'page.title', __('site.dashboard.wolfi.fallbacks.dashboard_workspace'));

        if (is_array($account)) {
            $message = __('site.dashboard.wolfi.welcome.account_message', [
                'plan' => $account['plan_label'],
                'page' => $pageTitle,
            ]);

            $bullets = [
                __('site.dashboard.wolfi.welcome.account_bullets.status', [
                    'status' => $account['status'],
                    'progress' => number_format((float) $account['target_progress_percent'], 1).'%',
                ]),
                __('site.dashboard.wolfi.welcome.account_bullets.balance', [
                    'balance' => $account['balance'],
                    'equity' => $account['equity'],
                    'pnl' => $account['floating_pnl'],
                ]),
                __('site.dashboard.wolfi.welcome.account_bullets.trading_days', [
                    'days' => $account['trading_days'],
                ]),
            ];

            $stats = [
                [
                    'label' => __('site.dashboard.wolfi.stat_labels.status'),
                    'value' => $account['status'],
                    'tone' => 'amber',
                ],
                [
                    'label' => __('site.dashboard.wolfi.stat_labels.balance'),
                    'value' => $account['balance'],
                    'tone' => 'amber',
                ],
                [
                    'label' => __('site.dashboard.wolfi.stat_labels.equity'),
                    'value' => $account['equity'],
                    'tone' => 'sky',
                ],
            ];
        } else {
            $message = __('site.dashboard.wolfi.welcome.empty_message', [
                'page' => $pageTitle,
            ]);

            $bullets = array_values((array) trans('site.dashboard.wolfi.welcome.empty_bullets'));

            $stats = [
                [
                    'label' => __('site.dashboard.wolfi.stat_labels.page'),
                    'value' => $pageTitle,
                    'tone' => 'amber',
                ],
                [
                    'label' => __('site.dashboard.wolfi.stat_labels.rules'),
                    'value' => __('site.dashboard.wolfi.stat_values.structured'),
                    'tone' => 'sky',
                ],
                [
                    'label' => __('site.dashboard.wolfi.stat_labels.support'),
                    'value' => __('site.dashboard.wolfi.stat_values.ready'),
                    'tone' => 'emerald',
                ],
            ];
        }

        return [
            'group' => 'welcome',
            'title' => __('site.dashboard.wolfi.welcome.title'),
            'message' => $message,
            'bullets' => $bullets,
            'stats' => $stats,
            'suggestions' => $this->suggestionsFor('platform_guidance'),
        ];
    }

    private function detectIntent(string $message): string
    {
        $normalized = $this->normalize($message);

        $intentKeywords = [
            'consistency' => ['consistency', 'consisten', 'single day', '40%', '40 percent'],
            'payouts' => ['payout', 'withdraw', 'withdrawal', 'profit split', 'paid', 'eligible profit'],
            'performance_insights' => ['metric', 'metrics', 'balance', 'equity', 'floating', 'p&l', 'pnl', 'drawdown', 'win ratio', 'performance'],
            'challenge_rules' => ['rule', 'rules', 'profit target', 'daily loss', 'max drawdown', 'trading days', 'pass', 'fail', 'phase'],
            'support' => ['support', 'help', 'invoice', 'billing', 'contact', 'login', 'email', 'ticket'],
            'platform_guidance' => ['dashboard', 'page', 'where', 'find', 'navigate', 'overview', 'accounts', 'settings', 'section'],
        ];

        foreach ($intentKeywords as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $this->normalize($keyword))) {
                    return $intent;
                }
            }
        }

        return 'platform_guidance';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function platformGuidanceResponse(array $context): array
    {
        $pageTitle = (string) data_get($context, 'page.title', 'Dashboard workspace');
        $pageSummary = (string) data_get($context, 'page.summary', 'Wolfi can guide you through the dashboard.');
        $pageSections = array_values((array) data_get($context, 'page.sections', []));
        $account = $context['account'] ?? null;

        $message = $pageSummary;

        if (is_array($account)) {
            $message .= sprintf(
                ' For your %s account, the fastest sequence is summary first, rule usage second, then payout and trade details.',
                $account['plan_label'],
            );
        }

        $bullets = collect($pageSections)
            ->map(fn (array $section): string => sprintf('%s: %s', $section['title'] ?? 'Section', $section['description'] ?? ''))
            ->filter()
            ->values()
            ->all();

        return [
            'group' => 'platform_guidance',
            'title' => sprintf('%s, explained step by step', $pageTitle),
            'message' => $message,
            'bullets' => $bullets,
            'stats' => [
                [
                    'label' => 'Current page',
                    'value' => $pageTitle,
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Sections',
                    'value' => (string) count($pageSections),
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Linked account',
                    'value' => is_array($account) ? $account['plan_label'] : 'Not synced yet',
                    'tone' => is_array($account) ? 'emerald' : 'slate',
                ],
            ],
            'suggestions' => $this->suggestionsFor('platform_guidance'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function challengeRulesResponse(array $context): array
    {
        $currentRules = data_get($context, 'rules.current');
        $passFailItems = array_values((array) data_get($context, 'rules.pass_fail_items', []));

        if (is_array($currentRules)) {
            $message = sprintf(
                'Your current %s rules are tied to %s. Keep the loss limits intact while reaching the target and minimum trading days.',
                $currentRules['plan_label'],
                $currentRules['phase_label'],
            );

            $bullets = [
                sprintf('Profit target: %s%% for the current phase.', $this->trimTrailingZero((float) $currentRules['profit_target'])),
                sprintf('Max daily loss: %s%%.', $this->trimTrailingZero((float) $currentRules['daily_loss_limit'])),
                sprintf('Max drawdown: %s%%.', $this->trimTrailingZero((float) $currentRules['max_loss_limit'])),
                sprintf('Minimum trading days: %d.', (int) $currentRules['minimum_trading_days']),
                sprintf('Funded profit split: %s%%.', $this->trimTrailingZero((float) $currentRules['funded_profit_split'])),
                sprintf(
                    'Funded payout timing: first withdrawal after %d days, then every %d days.',
                    (int) $currentRules['first_payout_days'],
                    (int) $currentRules['payout_cycle_days'],
                ),
                $currentRules['consistency_rule_required']
                    ? 'This model keeps the consistency rule active for funded payout approval.'
                    : 'This model does not mark the consistency rule as obligatory in the current funded configuration.',
                ...$passFailItems,
            ];

            $stats = [
                [
                    'label' => 'Target',
                    'value' => $this->trimTrailingZero((float) $currentRules['profit_target']).'%',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Daily loss',
                    'value' => $this->trimTrailingZero((float) $currentRules['daily_loss_limit']).'%',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Max drawdown',
                    'value' => $this->trimTrailingZero((float) $currentRules['max_loss_limit']).'%',
                    'tone' => 'rose',
                ],
            ];
        } else {
            $models = array_values((array) data_get($context, 'rules.models', []));
            $message = 'Wolforix rules differ by model, so I will summarize the current 1-Step and 2-Step structure instead of guessing.';
            $bullets = collect($models)
                ->map(function (array $model): string {
                    $phaseTwo = (float) ($model['phase_two_profit_target'] ?? 0) > 0
                        ? sprintf(' Phase 2 target is %s%% with the same %s%% / %s%% loss limits.',
                            $this->trimTrailingZero((float) $model['phase_two_profit_target']),
                            $this->trimTrailingZero((float) $model['phase_two_daily_loss_limit']),
                            $this->trimTrailingZero((float) $model['phase_two_max_loss_limit']),
                        )
                        : '';

                    $consistency = $model['consistency_rule_required']
                        ? 'Consistency is obligatory for funded payouts.'
                        : 'Consistency is not marked obligatory in funded mode.';

                    return sprintf(
                        '%s: %s%% target, %s%% max daily loss, %s%% max total loss, minimum %d trading days.%s %s',
                        $model['label'],
                        $this->trimTrailingZero((float) $model['profit_target']),
                        $this->trimTrailingZero((float) $model['daily_loss_limit']),
                        $this->trimTrailingZero((float) $model['max_loss_limit']),
                        (int) $model['minimum_trading_days'],
                        $phaseTwo,
                        $consistency,
                    );
                })
                ->push(...$passFailItems)
                ->all();

            $stats = [
                [
                    'label' => 'Models',
                    'value' => (string) count($models),
                    'tone' => 'amber',
                ],
                [
                    'label' => 'First payout',
                    'value' => '21 days',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Cycle',
                    'value' => '14 days',
                    'tone' => 'emerald',
                ],
            ];
        }

        return [
            'group' => 'challenge_rules',
            'title' => 'Challenge rules, made practical',
            'message' => $message,
            'bullets' => $bullets,
            'stats' => $stats,
            'suggestions' => $this->suggestionsFor('challenge_rules'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function performanceInsightsResponse(array $context): array
    {
        $account = $context['account'] ?? null;

        if (! is_array($account)) {
            return [
                'group' => 'performance_insights',
                'title' => 'How Wolforix metrics work',
                'message' => 'Once an account is linked, I can explain your live numbers. For now, here is how the core labels work.',
                'bullets' => [
                    'Balance shows the challenge-relative account balance after realized profit or loss.',
                    'Equity adds floating P&L to balance, so it changes with open positions.',
                    'Floating P&L isolates the unrealized gain or loss on open trades.',
                    'Daily loss room and max drawdown room show how much buffer remains before a breach.',
                    'Target progress and trading days tell you how close the account is to satisfying pass conditions.',
                ],
                'stats' => [
                    [
                        'label' => 'Balance',
                        'value' => 'Realized',
                        'tone' => 'amber',
                    ],
                    [
                        'label' => 'Equity',
                        'value' => 'Live',
                        'tone' => 'sky',
                    ],
                    [
                        'label' => 'Drawdown',
                        'value' => 'Rule-linked',
                        'tone' => 'rose',
                    ],
                ],
                'suggestions' => $this->suggestionsFor('performance_insights'),
            ];
        }

        return [
            'group' => 'performance_insights',
            'title' => 'Your metrics in plain English',
            'message' => sprintf(
                'Your %s account currently shows %s balance, %s equity, and %s floating P&L. That means realized performance and live open-trade performance are moving separately.',
                $account['plan_label'],
                $account['balance'],
                $account['equity'],
                $account['floating_pnl'],
            ),
            'bullets' => [
                sprintf('Balance is the challenge-relative realized result: %s.', $account['balance']),
                sprintf('Equity includes open positions, so it is currently %s.', $account['equity']),
                sprintf('Floating P&L is the unrealized movement on open trades: %s.', $account['floating_pnl']),
                sprintf('Daily loss room remaining: %s.', $account['daily_loss_remaining']),
                sprintf('Max drawdown room remaining: %s.', $account['max_drawdown_remaining']),
                sprintf('Trading days currently read %s, with target progress at %s%%.', $account['trading_days'], number_format((float) $account['target_progress_percent'], 1)),
            ],
            'stats' => [
                [
                    'label' => 'Balance',
                    'value' => $account['balance'],
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Equity',
                    'value' => $account['equity'],
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Floating P&L',
                    'value' => $account['floating_pnl'],
                    'tone' => str_starts_with((string) $account['floating_pnl'], '-') ? 'rose' : 'emerald',
                ],
            ],
            'suggestions' => $this->suggestionsFor('performance_insights'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function payoutResponse(array $context): array
    {
        $account = $context['account'] ?? null;
        $currentRules = data_get($context, 'rules.current');
        $consistencyRequired = (bool) ($currentRules['consistency_rule_required'] ?? false);

        if (! is_array($account)) {
            return [
                'group' => 'payouts',
                'title' => 'Payout timing and approval',
                'message' => 'Wolforix funded accounts unlock the first withdrawal after 21 days. After that, payout requests follow a 14-day cycle and still depend on rule compliance and internal review.',
                'bullets' => [
                    'Profit becomes payout-eligible only after the account reaches funded conditions.',
                    'The funded profit split is applied before calculating the eligible amount.',
                    'Payout requests remain subject to Wolforix review checks.',
                    $consistencyRequired
                        ? 'For 1-Step funded accounts, the consistency rule must still be satisfied before payout approval.'
                        : 'The current funded configuration does not automatically require the consistency rule for every model.',
                ],
                'stats' => [
                    [
                        'label' => 'First withdrawal',
                        'value' => '21 days',
                        'tone' => 'amber',
                    ],
                    [
                        'label' => 'Cycle',
                        'value' => '14 days',
                        'tone' => 'sky',
                    ],
                    [
                        'label' => 'Review',
                        'value' => 'Required',
                        'tone' => 'rose',
                    ],
                ],
                'suggestions' => $this->suggestionsFor('payouts'),
            ];
        }

        $message = $account['is_funded']
            ? sprintf(
                'Your account is funded, so payout timing is now anchored to the first withdrawal window and recurring cycle. Profit split is %s%% for this account.',
                $this->trimTrailingZero((float) $account['profit_split_percent']),
            )
            : 'Your current account is still in the challenge lifecycle, so payout timing is informational until funding is reached.';

        $bullets = [
            sprintf('First withdrawal timing for this model: %d days.', (int) $account['first_payout_days']),
            sprintf('Recurring payout cycle after that: every %d days.', (int) $account['payout_cycle_days']),
            sprintf('Stored first payout eligible date: %s.', $account['first_payout_eligible_at']),
            sprintf('Stored next payout window: %s.', $account['payout_eligible_at']),
            sprintf('Profit split on this account: %s%%.', $this->trimTrailingZero((float) $account['profit_split_percent'])),
            $consistencyRequired
                ? 'This model keeps the consistency rule in the payout approval path.'
                : 'This model does not currently mark consistency as obligatory for payout approval.',
        ];

        if (! $account['is_funded']) {
            $bullets[] = 'Because the account is not funded yet, eligible profit will stay locked until the challenge lifecycle is completed successfully.';
        }

        return [
            'group' => 'payouts',
            'title' => 'How payouts work for this account',
            'message' => $message,
            'bullets' => $bullets,
            'stats' => [
                [
                    'label' => 'Profit split',
                    'value' => $this->trimTrailingZero((float) $account['profit_split_percent']).'%',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'First window',
                    'value' => (string) $account['first_payout_days'].' days',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Cycle',
                    'value' => (string) $account['payout_cycle_days'].' days',
                    'tone' => 'emerald',
                ],
            ],
            'suggestions' => $this->suggestionsFor('payouts'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function consistencyResponse(array $context): array
    {
        $account = $context['account'] ?? null;
        $currentRules = data_get($context, 'rules.current');
        $threshold = (float) ($account['consistency_limit_percent'] ?? data_get($context, 'rules.default_consistency_limit', 40));
        $consistencyRequired = (bool) ($currentRules['consistency_rule_required'] ?? false);

        $message = sprintf(
            'The consistency rule limits how much of your total profit can come from one trading day. In the current Wolforix setup, the reference threshold is %s%%.',
            $this->trimTrailingZero($threshold),
        );

        $bullets = [
            sprintf('Default threshold: no more than %s%% of total profit from a single trading day.', $this->trimTrailingZero($threshold)),
            'The goal is to keep profits distributed across multiple trading days instead of concentrating them in one spike.',
            $consistencyRequired
                ? 'For the current model, consistency remains part of funded payout approval.'
                : 'For the current model, consistency is not marked obligatory in funded mode, but Wolfi can still explain the rule if you need it.',
        ];

        $stats = [
            [
                'label' => 'Threshold',
                'value' => $this->trimTrailingZero($threshold).'%',
                'tone' => 'amber',
            ],
        ];

        if (is_array($account)) {
            $bullets[] = sprintf('Current stored consistency ratio: %s%%.', number_format((float) $account['consistency_ratio_percent'], 2));
            $bullets[] = sprintf('Highest recorded single-day profit in the stored rule state: %s.', $account['consistency_highest_day_profit']);
            $stats[] = [
                'label' => 'Current ratio',
                'value' => number_format((float) $account['consistency_ratio_percent'], 2).'%',
                'tone' => (float) $account['consistency_ratio_percent'] >= $threshold ? 'rose' : 'sky',
            ];
            $stats[] = [
                'label' => 'Stored status',
                'value' => str($account['consistency_status'])->replace('_', ' ')->title()->toString(),
                'tone' => (string) $account['consistency_status'] === 'breach' ? 'rose' : 'emerald',
            ];
        } else {
            $stats[] = [
                'label' => 'Model example',
                'value' => '1-Step funded',
                'tone' => 'sky',
            ];
            $stats[] = [
                'label' => 'Purpose',
                'value' => 'Profit distribution',
                'tone' => 'emerald',
            ];
        }

        return [
            'group' => 'consistency',
            'title' => 'Consistency rule, explained simply',
            'message' => $message,
            'bullets' => $bullets,
            'stats' => $stats,
            'suggestions' => $this->suggestionsFor('consistency'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function supportResponse(array $context): array
    {
        $supportEmail = (string) data_get($context, 'support.email', 'support@wolforix.com');
        $businessHours = (string) data_get($context, 'support.business_hours', 'Mon-Fri, 09:00-18:00 UTC');
        $commonTopics = array_values((array) data_get($context, 'support.common_topics', []));
        $pageTitle = (string) data_get($context, 'page.title', 'dashboard');

        return [
            'group' => 'support',
            'title' => 'Support help without leaving the dashboard',
            'message' => sprintf(
                'Wolfi can cover common process questions here. If you need team follow-up, the support route is %s during %s.',
                $supportEmail,
                $businessHours,
            ),
            'bullets' => [
                sprintf('For account-specific team help, contact %s.', $supportEmail),
                sprintf('Current support hours: %s.', $businessHours),
                sprintf('If you are on the %s page, I can first explain what you should check before you escalate.', $pageTitle),
                ...$commonTopics,
            ],
            'stats' => [
                [
                    'label' => 'Support email',
                    'value' => $supportEmail,
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Hours',
                    'value' => $businessHours,
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Wolfi scope',
                    'value' => 'Rules + workflow',
                    'tone' => 'emerald',
                ],
            ],
            'suggestions' => $this->suggestionsFor('support'),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function suggestionsFor(string $intent): array
    {
        $keyMap = [
            'platform_guidance' => ['rules', 'metrics', 'payouts'],
            'challenge_rules' => ['consistency', 'metrics', 'payouts'],
            'performance_insights' => ['payouts', 'rules', 'dashboard'],
            'payouts' => ['consistency', 'rules', 'metrics'],
            'consistency' => ['rules', 'metrics', 'payouts'],
            'support' => ['dashboard', 'payouts', 'rules'],
        ];

        $allowed = $keyMap[$intent] ?? ['dashboard', 'rules', 'metrics'];

        return collect($this->knowledgeBase->quickActions())
            ->filter(fn (array $action): bool => in_array($action['key'] ?? '', $allowed, true))
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return str($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();
    }

    private function trimTrailingZero(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
