<?php

namespace App\Http\Controllers;

use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Pricing\ChallengePricingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ChallengePricingService $pricingService): View
    {
        return view('dashboard.index', $this->dashboardViewData($request, $pricingService));
    }

    public function accounts(Request $request, ChallengePricingService $pricingService): View
    {
        return view('dashboard.accounts', $this->dashboardViewData($request, $pricingService));
    }

    public function payouts(Request $request, ChallengePricingService $pricingService): View
    {
        return view('dashboard.payouts', $this->dashboardViewData($request, $pricingService));
    }

    public function settings(Request $request, ChallengePricingService $pricingService): View
    {
        return view('dashboard.settings', $this->dashboardViewData($request, $pricingService));
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardViewData(Request $request, ChallengePricingService $pricingService): array
    {
        $availablePlans = $this->availablePlans($request, $pricingService);

        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return $this->emptyDashboardState($availablePlans);
        }

        $user->loadMissing([
            'profile',
            'ctraderConnection',
            'challengeTradingAccounts.challengePlan',
            'challengePurchases.order',
        ]);

        $accounts = $user->challengeTradingAccounts
            ->sortByDesc('created_at')
            ->values();

        /** @var TradingAccount|null $primaryAccount */
        $primaryAccount = $accounts->first();
        $primaryPlanDefinition = $primaryAccount instanceof TradingAccount
            ? $this->planDefinitionForAccount($primaryAccount)
            : null;
        $primaryAccountOverview = $primaryAccount ? $this->overviewAccountPayload($primaryAccount) : null;

        return [
            'primaryAccount' => $primaryAccountOverview,
            'primaryPlanDefinition' => $primaryPlanDefinition,
            'summaryCards' => $this->summaryCards($primaryAccount),
            'consistencyBanner' => $this->consistencyBanner($primaryAccount, $user->challengePurchases->count()),
            'dashboardHero' => $this->dashboardHero($primaryAccount),
            'dashboardBadges' => $this->dashboardBadges($primaryAccount),
            'progressTracks' => $this->progressTracks($primaryAccount),
            'performanceChart' => $this->performanceChart($primaryAccount),
            'performanceCards' => $this->performanceCards($primaryAccount),
            'analyticsSummary' => $this->analyticsSummary($primaryAccount),
            'tradesPanel' => $this->tradesPanel($primaryAccount),
            'accounts' => $accounts->map(fn (TradingAccount $account): array => $this->accountCardPayload($account))->all(),
            'payoutSummary' => $this->payoutSummary($primaryAccount),
            'ctraderConnection' => $this->ctraderConnectionPayload($user),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'language' => __('site.languages.'.($user->profile?->preferred_language ?? app()->getLocale())),
                'timezone' => $user->profile?->timezone ?? config('app.timezone', 'UTC'),
            ],
            'purchasedChallenges' => $this->purchasedChallenges(),
            'hasTradingAccounts' => $accounts->isNotEmpty(),
            'availablePlans' => $availablePlans,
            'emptyState' => [
                'title' => 'No challenge accounts linked yet',
                'message' => 'Paid challenges stay visible below. A trading account card appears here once the purchase is provisioned and linked for sync.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardState(array $availablePlans): array
    {
        return [
            'primaryAccount' => null,
            'primaryPlanDefinition' => null,
            'summaryCards' => $this->summaryCards(null),
            'consistencyBanner' => $this->consistencyBanner(null, 0),
            'dashboardHero' => null,
            'dashboardBadges' => [],
            'progressTracks' => [],
            'performanceChart' => $this->performanceChart(null),
            'performanceCards' => [],
            'analyticsSummary' => null,
            'tradesPanel' => $this->tradesPanel(null),
            'accounts' => [],
            'payoutSummary' => $this->payoutSummary(null),
            'ctraderConnection' => $this->ctraderConnectionPayload(null),
            'profile' => [
                'name' => '',
                'email' => '',
                'language' => __('site.languages.'.app()->getLocale()),
                'timezone' => config('app.timezone', 'UTC'),
            ],
            'purchasedChallenges' => collect(),
            'hasTradingAccounts' => false,
            'availablePlans' => $availablePlans,
            'emptyState' => [
                'title' => 'No challenge accounts linked yet',
                'message' => 'Your dashboard will populate here after a paid challenge is provisioned.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewAccountPayload(TradingAccount $account): array
    {
        $plan = $this->planDefinitionForAccount($account);
        $fundedTiming = $this->fundedTiming($account, $plan);
        $syncFreshness = $this->syncFreshness($account->last_synced_at);
        $phaseProfit = round((float) $account->balance - (float) ($account->phase_reference_balance ?: $account->starting_balance ?: 0), 2);

        return [
            'reference' => $account->account_reference ?? 'N/A',
            'plan' => $account->challengePlan?->name ?? $this->challengeTypeLabel((string) $account->challenge_type).' / '.((int) ($account->account_size / 1000)).'K',
            'challenge_type' => $this->challengeTypeLabel((string) $account->challenge_type),
            'challenge_phase' => $this->phaseLabel($account),
            'account_size' => $this->formatMoney((float) $account->account_size),
            'platform' => $account->platform,
            'platform_slug' => $account->platform_slug,
            'stage' => $account->stage,
            'status' => $account->status,
            'challenge_status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status)),
            'account_status' => $this->humanizeStatus((string) $account->account_status),
            'start_date' => $this->formatDate($account->phase_started_at ?? $account->activated_at ?? $account->created_at),
            'platform_account_id' => $account->platform_account_id ?: 'Link pending',
            'platform_login' => $account->platform_login ?: 'Link pending',
            'platform_environment' => $account->platform_environment ?: 'Pending',
            'platform_status' => $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link')),
            'sync_status' => $this->humanizeStatus((string) $account->sync_status),
            'last_synced_at' => $this->formatDateTime($account->last_synced_at),
            'last_evaluated_at' => $this->formatDateTime($account->last_evaluated_at),
            'sync_freshness' => $syncFreshness['label'],
            'sync_freshness_hint' => $syncFreshness['hint'],
            'sync_freshness_tone' => $syncFreshness['tone'],
            'balance' => $this->formatMoney((float) $account->balance),
            'starting_balance' => $this->formatMoney((float) $account->starting_balance),
            'equity' => $this->formatMoney((float) $account->equity),
            'floating_pnl' => $this->formatMoney((float) $account->profit_loss),
            'floating_pnl_tone' => $this->metricTone((float) $account->profit_loss),
            'total_profit' => $this->formatMoney((float) $account->total_profit),
            'phase_profit' => $this->formatMoney($phaseProfit),
            'today_profit' => $this->formatMoney((float) $account->today_profit),
            'daily_drawdown' => $this->formatMoney((float) $account->daily_drawdown),
            'max_drawdown' => $this->formatMoney((float) $account->max_drawdown),
            'daily_loss_used' => $this->formatMoney((float) $account->daily_loss_used),
            'daily_loss_remaining' => $this->formatMoney(max((float) $account->daily_drawdown_limit_amount - (float) $account->daily_loss_used, 0)),
            'max_drawdown_used' => $this->formatMoney((float) $account->max_drawdown_used),
            'max_drawdown_remaining' => $this->formatMoney(max((float) $account->max_drawdown_limit_amount - (float) $account->max_drawdown_used, 0)),
            'drawdown_percent' => number_format((float) $account->drawdown_percent, 1).'%',
            'profit_target_percent' => number_format((float) $account->profit_target_percent, 1).'%',
            'daily_drawdown_limit_percent' => number_format((float) $account->daily_drawdown_limit_percent, 1).'%',
            'max_drawdown_limit_percent' => number_format((float) $account->max_drawdown_limit_percent, 1).'%',
            'minimum_trading_days' => (int) $account->minimum_trading_days,
            'trading_days_completed' => (int) $account->trading_days_completed,
            'progress_value' => max(min((float) $account->profit_target_progress_percent, 100), 0),
            'progress_label' => number_format((float) $account->profit_target_progress_percent, 0).'%',
            'profit_split' => number_format((float) $account->profit_split, 0).'%',
            'payout_eligible_at' => $this->formatDateTime($fundedTiming['payout_eligible_at']),
            'first_payout_eligible_at' => $this->formatDateTime($fundedTiming['first_payout_eligible_at']),
            'sync_error' => $account->sync_error,
            'sync_source' => $account->sync_source ? $this->sourceLabel((string) $account->sync_source) : 'Not available',
            'failure_reason' => $account->failure_reason ? $this->humanizeStatus((string) $account->failure_reason) : null,
            'payout_cycle_days' => $fundedTiming['payout_cycle_days'],
            'first_payout_days' => $fundedTiming['first_payout_days'],
            'leverage' => $plan['phases'][0]['leverage'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dashboardHero(?TradingAccount $account): ?array
    {
        if (! $account instanceof TradingAccount) {
            return null;
        }

        $targetAmount = $this->profitTargetAmount($account);
        $reference = $account->account_reference ?? 'N/A';
        $platformAccountId = $account->platform_account_id ?: 'Link pending';

        return [
            'title' => $account->challengePlan?->name ?? $this->challengeTypeLabel((string) $account->challenge_type).' / '.((int) ($account->account_size / 1000)).'K',
            'subtitle' => implode(' • ', array_filter([
                $reference,
                $platformAccountId,
                $account->platform,
            ])),
            'reference' => $reference,
            'platform' => $account->platform ?: 'Not linked',
            'platform_account_id' => $platformAccountId,
            'start_date' => $this->formatDate($account->phase_started_at ?? $account->activated_at ?? $account->created_at),
            'challenge_phase' => $this->phaseLabel($account),
            'challenge_status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status ?: 'active')),
            'sync_status' => $this->humanizeStatus((string) ($account->sync_status ?: 'pending')),
            'sync_freshness' => $this->syncFreshness($account->last_synced_at),
            'badges' => $this->dashboardBadges($account),
            'metrics' => [
                [
                    'label' => 'Starting balance',
                    'value' => $this->formatMoney((float) $account->starting_balance),
                    'hint' => 'Phase baseline',
                    'tone' => 'slate',
                ],
                [
                    'label' => 'Current balance',
                    'value' => $this->formatMoney((float) $account->balance),
                    'hint' => 'Closed balance',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Equity',
                    'value' => $this->formatMoney((float) $account->equity),
                    'hint' => 'Live equity',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Floating P&L',
                    'value' => $this->formatMoney((float) $account->profit_loss),
                    'hint' => 'Open positions',
                    'tone' => $this->metricTone((float) $account->profit_loss),
                ],
                [
                    'label' => 'Recognized profit',
                    'value' => $this->formatMoney((float) $account->total_profit),
                    'hint' => 'Closed performance',
                    'tone' => $this->metricTone((float) $account->total_profit),
                ],
                [
                    'label' => 'Profit target',
                    'value' => $this->formatMoney($targetAmount),
                    'hint' => number_format((float) $account->profit_target_percent, 1).'%',
                    'tone' => 'emerald',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function dashboardBadges(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [];
        }

        $state = $account->is_funded
            ? 'Funded'
            : match ((string) $account->challenge_status) {
                'passed' => 'Passed',
                'failed' => 'Failed',
                default => 'Evaluation',
            };

        $badges = [
            ['label' => $this->challengeTypeLabel((string) $account->challenge_type), 'tone' => 'amber'],
            ['label' => $state, 'tone' => $this->statusTone($state)],
            ['label' => $this->humanizeStatus((string) ($account->account_status ?: $account->status ?: 'active')), 'tone' => $this->statusTone((string) ($account->account_status ?: $account->status ?: 'active'))],
            ['label' => $this->phaseLabel($account), 'tone' => 'slate'],
            ['label' => strtoupper((string) ($account->platform ?: 'N/A')), 'tone' => 'sky'],
            ['label' => strtoupper((string) ($account->platform_environment ?: 'pending')), 'tone' => 'slate'],
        ];

        return collect($badges)
            ->unique('label')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function progressTracks(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [];
        }

        $targetAmount = $this->profitTargetAmount($account);
        $targetProgress = (float) $account->profit_target_progress_percent;
        $dailyLossLimit = (float) $account->daily_drawdown_limit_amount;
        $maxDrawdownLimit = (float) $account->max_drawdown_limit_amount;
        $minimumTradingDays = max((int) $account->minimum_trading_days, 1);
        $tradingDaysCompleted = (int) $account->trading_days_completed;

        return [
            [
                'label' => 'Profit target progress',
                'value' => max(min($targetProgress, 100), 0),
                'value_label' => number_format($targetProgress, 1).'%',
                'current' => $this->formatMoney((float) $account->total_profit),
                'target' => $this->formatMoney($targetAmount),
                'meta' => 'Remaining '.$this->formatMoney(max($targetAmount - (float) $account->total_profit, 0)),
                'tone' => 'amber',
            ],
            [
                'label' => 'Daily loss usage',
                'value' => $this->safePercentage((float) $account->daily_loss_used, $dailyLossLimit),
                'value_label' => number_format($this->safePercentage((float) $account->daily_loss_used, $dailyLossLimit), 1).'%',
                'current' => $this->formatMoney((float) $account->daily_loss_used),
                'target' => $this->formatMoney($dailyLossLimit),
                'meta' => 'Remaining '.$this->formatMoney(max($dailyLossLimit - (float) $account->daily_loss_used, 0)),
                'tone' => ((float) $account->daily_loss_used) >= ($dailyLossLimit * 0.8) && $dailyLossLimit > 0 ? 'rose' : 'sky',
            ],
            [
                'label' => 'Max drawdown usage',
                'value' => $this->safePercentage((float) $account->max_drawdown_used, $maxDrawdownLimit),
                'value_label' => number_format($this->safePercentage((float) $account->max_drawdown_used, $maxDrawdownLimit), 1).'%',
                'current' => $this->formatMoney((float) $account->max_drawdown_used),
                'target' => $this->formatMoney($maxDrawdownLimit),
                'meta' => 'Remaining '.$this->formatMoney(max($maxDrawdownLimit - (float) $account->max_drawdown_used, 0)),
                'tone' => ((float) $account->max_drawdown_used) >= ($maxDrawdownLimit * 0.8) && $maxDrawdownLimit > 0 ? 'rose' : 'slate',
            ],
            [
                'label' => 'Trading days completed',
                'value' => $this->safePercentage((float) $tradingDaysCompleted, (float) $minimumTradingDays),
                'value_label' => sprintf('%d / %d', $tradingDaysCompleted, (int) $account->minimum_trading_days),
                'current' => (string) $tradingDaysCompleted,
                'target' => (string) $account->minimum_trading_days,
                'meta' => $tradingDaysCompleted >= (int) $account->minimum_trading_days ? 'Minimum requirement met' : 'Keep trading to unlock progression',
                'tone' => $tradingDaysCompleted >= (int) $account->minimum_trading_days ? 'emerald' : 'amber',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performanceChart(?TradingAccount $account): array
    {
        $emptyState = [
            'is_available' => false,
            'default_range' => 'all',
            'ranges' => [],
            'empty_message' => 'The equity curve will appear after the first synced balance snapshot.',
        ];

        if (! $account instanceof TradingAccount) {
            return $emptyState;
        }

        $points = $this->chartPoints($account);

        if ($points->isEmpty()) {
            return $emptyState;
        }

        $rangeDefinitions = [
            'all' => ['label' => 'All', 'days' => null],
            'weekly' => ['label' => 'Weekly', 'days' => 7],
            'monthly' => ['label' => 'Monthly', 'days' => 30],
            'yearly' => ['label' => 'Yearly', 'days' => 365],
        ];

        $ranges = collect($rangeDefinitions)
            ->mapWithKeys(function (array $definition, string $key) use ($points): array {
                $rangePoints = $definition['days'] === null
                    ? $points
                    : $points->filter(fn (array $point): bool => Carbon::parse($point['date_iso'])->greaterThanOrEqualTo(now()->subDays((int) $definition['days'])));

                if ($rangePoints->isEmpty()) {
                    return [$key => [
                        'label' => $definition['label'],
                        'is_available' => false,
                        'points' => [],
                        'summary' => [
                            'change' => $this->formatMoney(0),
                            'change_tone' => 'slate',
                            'range_hint' => 'No synced data yet',
                            'high' => $this->formatMoney(0),
                            'low' => $this->formatMoney(0),
                            'last_balance' => $this->formatMoney(0),
                            'last_equity' => $this->formatMoney(0),
                        ],
                    ]];
                }

                $sampledPoints = $this->sampleChartPoints($rangePoints);
                $firstPoint = $sampledPoints->first();
                $lastPoint = $sampledPoints->last();
                $change = (float) ($lastPoint['equity'] ?? 0) - (float) ($firstPoint['equity'] ?? 0);
                $high = $sampledPoints->max(fn (array $point): float => max((float) $point['balance'], (float) $point['equity']));
                $low = $sampledPoints->min(fn (array $point): float => min((float) $point['balance'], (float) $point['equity']));

                return [$key => [
                    'label' => $definition['label'],
                    'is_available' => true,
                    'points' => $sampledPoints->values()->all(),
                    'summary' => [
                        'change' => $this->formatMoney($change),
                        'change_tone' => $this->metricTone($change),
                        'range_hint' => sprintf('%s to %s', $firstPoint['label'], $lastPoint['label']),
                        'high' => $this->formatMoney((float) $high),
                        'low' => $this->formatMoney((float) $low),
                        'last_balance' => $this->formatMoney((float) $lastPoint['balance']),
                        'last_equity' => $this->formatMoney((float) $lastPoint['equity']),
                    ],
                ]];
            })
            ->all();

        return [
            'is_available' => true,
            'default_range' => 'all',
            'ranges' => $ranges,
            'empty_message' => $emptyState['empty_message'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function performanceCards(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [];
        }

        $weeklyProfit = $this->weeklyProfit($account);
        $phaseProfit = round((float) $account->balance - (float) ($account->phase_reference_balance ?: $account->starting_balance ?: 0), 2);
        $cards = collect([
            [
                'label' => 'Daily profit',
                'value' => $this->formatMoney((float) $account->today_profit),
                'hint' => 'Latest server-day result',
                'tone' => $this->metricTone((float) $account->today_profit),
            ],
            [
                'label' => 'Unrealized profit',
                'value' => $this->formatMoney((float) $account->profit_loss),
                'hint' => 'Open-position exposure',
                'tone' => $this->metricTone((float) $account->profit_loss),
            ],
            [
                'label' => 'Weekly profit',
                'value' => $weeklyProfit === null ? null : $this->formatMoney($weeklyProfit),
                'hint' => '7-day change in synced total profit',
                'tone' => $weeklyProfit === null ? 'slate' : $this->metricTone($weeklyProfit),
            ],
            [
                'label' => 'Net profit',
                'value' => $this->formatMoney((float) $account->total_profit),
                'hint' => 'Closed account performance',
                'tone' => $this->metricTone((float) $account->total_profit),
            ],
            [
                'label' => 'Phase profit',
                'value' => $this->formatMoney($phaseProfit),
                'hint' => 'Current phase performance',
                'tone' => $this->metricTone($phaseProfit),
            ],
            [
                'label' => 'Trading days completed',
                'value' => sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days),
                'hint' => 'Progress toward the minimum rule',
                'tone' => (int) $account->trading_days_completed >= (int) $account->minimum_trading_days ? 'emerald' : 'amber',
            ],
            [
                'label' => 'Daily loss used',
                'value' => $this->formatMoney((float) $account->daily_loss_used),
                'hint' => 'Consumed daily loss room',
                'tone' => 'slate',
            ],
            [
                'label' => 'Max drawdown used',
                'value' => $this->formatMoney((float) $account->max_drawdown_used),
                'hint' => 'Consumed max loss room',
                'tone' => 'slate',
            ],
        ])->filter(fn (array $card): bool => filled($card['value']));

        $tradeAnalytics = $this->tradeAnalytics($account);

        if ($tradeAnalytics !== null) {
            $cards = $cards->concat(array_filter([
                [
                    'label' => 'Gross profit',
                    'value' => $this->formatMoney((float) $tradeAnalytics['gross_profit']),
                    'hint' => 'Closed winning trades',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Gross loss',
                    'value' => $this->formatMoney(-(float) $tradeAnalytics['gross_loss']),
                    'hint' => 'Closed losing trades',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Profit factor',
                    'value' => $tradeAnalytics['profit_factor'] !== null ? number_format((float) $tradeAnalytics['profit_factor'], 2) : null,
                    'hint' => 'Gross profit divided by gross loss',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Best profit',
                    'value' => $this->formatMoney((float) $tradeAnalytics['best_profit']),
                    'hint' => 'Best closed trade',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Biggest loss',
                    'value' => $this->formatMoney((float) $tradeAnalytics['biggest_loss']),
                    'hint' => 'Largest closed-trade loss',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Expectancy',
                    'value' => $tradeAnalytics['expectancy'] !== null ? $this->formatMoney((float) $tradeAnalytics['expectancy']) : null,
                    'hint' => 'Average P&L per closed trade',
                    'tone' => $tradeAnalytics['expectancy'] !== null ? $this->metricTone((float) $tradeAnalytics['expectancy']) : 'slate',
                ],
                [
                    'label' => 'Average trade size',
                    'value' => $tradeAnalytics['average_trade_size'] !== null ? $this->formatNumeric((float) $tradeAnalytics['average_trade_size']) : null,
                    'hint' => 'Mean closed-trade volume',
                    'tone' => 'slate',
                ],
                [
                    'label' => 'Win rate',
                    'value' => number_format((float) $tradeAnalytics['win_rate'], 1).'%',
                    'hint' => 'Winning closed trades',
                    'tone' => (float) $tradeAnalytics['win_rate'] >= 50 ? 'emerald' : 'amber',
                ],
            ], static fn (array $card): bool => filled($card['value'])));
        }

        return $cards->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analyticsSummary(?TradingAccount $account): ?array
    {
        if (! $account instanceof TradingAccount) {
            return null;
        }

        $tradeAnalytics = $this->tradeAnalytics($account);

        if ($tradeAnalytics === null) {
            return [
                'is_available' => false,
                'title' => 'History & analytics',
                'message' => 'Per-trade analytics will appear once detailed trade rows are available inside the synced account snapshot.',
                'cards' => [],
            ];
        }

        return [
            'is_available' => true,
            'title' => 'History & analytics',
            'message' => 'The summary below is calculated from the latest synced trade-history rows attached to this account.',
            'cards' => [
                [
                    'label' => 'Total trades',
                    'value' => (string) $tradeAnalytics['closed_trades'],
                ],
                [
                    'label' => 'Fees',
                    'value' => $this->formatMoney(-(float) $tradeAnalytics['fees']),
                ],
                [
                    'label' => 'Win rate',
                    'value' => number_format((float) $tradeAnalytics['win_rate'], 1).'%',
                ],
                [
                    'label' => 'Total P&L',
                    'value' => $this->formatMoney((float) $tradeAnalytics['total_pnl']),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tradesPanel(?TradingAccount $account): array
    {
        $emptyState = [
            'is_available' => false,
            'rows' => [],
            'filters' => [
                ['key' => 'both', 'label' => 'Both'],
                ['key' => 'open', 'label' => 'Open'],
                ['key' => 'closed', 'label' => 'Closed'],
            ],
            'summary' => [
                'open' => 0,
                'closed' => 0,
                'both' => 0,
            ],
            'message' => 'Detailed trade rows are not available in the current synced snapshot for this account yet.',
            'source' => 'Snapshot payload',
        ];

        if (! $account instanceof TradingAccount) {
            return $emptyState;
        }

        $snapshotTradePayload = $this->latestSnapshotTradePayload($account);
        $openPositions = collect($snapshotTradePayload['open_positions'] ?? []);
        $closedTrades = collect($snapshotTradePayload['trade_history'] ?? []);

        $openRows = $openPositions->map(function (array $row): array {
            $profit = (float) Arr::get($row, 'net_unrealized_pnl', 0);

            return [
                'filter' => 'open',
                'id' => (string) (Arr::get($row, 'position_id') ?: '—'),
                'symbol' => $this->tradeSymbolLabel($row),
                'side' => $this->tradeSideLabel(Arr::get($row, 'trade_side')),
                'open_date' => $this->formatTradeDate(Arr::get($row, 'open_timestamp')),
                'close_date' => 'Live',
                'volume' => $this->formatNumeric((float) Arr::get($row, 'volume', 0)),
                'profit' => $this->formatMoney($profit),
                'profit_tone' => $this->metricTone($profit),
                'status' => 'Open',
                'sort_timestamp' => $this->sortableTradeTimestamp(Arr::get($row, 'open_timestamp')),
            ];
        });

        $closedRows = $closedTrades->map(function (array $row): array {
            $profit = (float) Arr::get($row, 'net_profit', 0);

            return [
                'filter' => 'closed',
                'id' => (string) (Arr::get($row, 'deal_id') ?: Arr::get($row, 'position_id') ?: '—'),
                'symbol' => $this->tradeSymbolLabel($row),
                'side' => $this->tradeSideLabel(Arr::get($row, 'trade_side')),
                'open_date' => 'Not available',
                'close_date' => $this->formatTradeDate(Arr::get($row, 'execution_timestamp')),
                'volume' => $this->formatNumeric((float) Arr::get($row, 'volume', 0)),
                'profit' => $this->formatMoney($profit),
                'profit_tone' => $this->metricTone($profit),
                'status' => 'Closed',
                'sort_timestamp' => $this->sortableTradeTimestamp(Arr::get($row, 'execution_timestamp')),
            ];
        });

        $rows = $openRows
            ->concat($closedRows)
            ->sortByDesc('sort_timestamp')
            ->values()
            ->map(fn (array $row): array => Arr::except($row, ['sort_timestamp']))
            ->all();

        if ($rows === []) {
            $emptyState['source'] = $account->sync_source ? $this->sourceLabel((string) $account->sync_source) : $emptyState['source'];

            return $emptyState;
        }

        return [
            'is_available' => true,
            'rows' => $rows,
            'filters' => $emptyState['filters'],
            'summary' => [
                'open' => $openRows->count(),
                'closed' => $closedRows->count(),
                'both' => count($rows),
            ],
            'message' => 'The latest synced snapshot powers this table. Open and closed rows are shown only when the platform payload includes them.',
            'source' => $account->sync_source ? $this->sourceLabel((string) $account->sync_source) : 'Snapshot payload',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availablePlans(Request $request, ChallengePricingService $pricingService): array
    {
        $launchDiscountApplied = $pricingService->launchDiscountApplied($request);

        return collect($pricingService->catalog(null, $launchDiscountApplied))
            ->flatMap(static fn (array $definition): array => array_values($definition['plans'] ?? []))
            ->sortBy([
                ['challenge_type', 'asc'],
                ['account_size', 'asc'],
                ['currency', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function summaryCards(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                [
                    'label' => __('site.dashboard.cards.balance'),
                    'value' => $this->formatMoney(0),
                    'hint' => 'No challenge account linked yet.',
                ],
                [
                    'label' => 'Equity',
                    'value' => $this->formatMoney(0),
                    'hint' => 'Equity appears after the first MT5 snapshot.',
                ],
                [
                    'label' => 'Floating P&L',
                    'value' => $this->formatMoney(0),
                    'hint' => 'Open-position profit appears after the first live update.',
                ],
                [
                    'label' => 'Sync freshness',
                    'value' => 'Awaiting sync',
                    'hint' => 'The first successful MT5 update will mark this account as live.',
                ],
            ];
        }

        $syncFreshness = $this->syncFreshness($account->last_synced_at);

        return [
            [
                'label' => __('site.dashboard.cards.balance'),
                'value' => $this->formatMoney((float) $account->balance),
                'hint' => 'Latest synced account balance.',
            ],
            [
                'label' => 'Equity',
                'value' => $this->formatMoney((float) $account->equity),
                'hint' => 'Current MT5 equity including open trade exposure.',
            ],
            [
                'label' => 'Floating P&L',
                'value' => $this->formatMoney((float) $account->profit_loss),
                'hint' => 'Open-position floating profit or loss from the latest sync.',
            ],
            [
                'label' => 'Sync freshness',
                'value' => $syncFreshness['label'],
                'hint' => $syncFreshness['hint'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consistencyBanner(?TradingAccount $account, int $purchaseCount): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                'title' => 'Account provisioning',
                'message' => $purchaseCount > 0
                    ? 'Your paid challenge records are saved. Account metrics appear here once the platform account is linked and synced.'
                    : 'Purchase a challenge to create your first tracked trading account.',
                'meta' => [
                    'Paid challenges: '.$purchaseCount,
                    'Platform: cTrader',
                    'Sync status: waiting for account link',
                ],
            ];
        }

        if ($account->platform_slug === 'mt5') {
            $syncFreshness = $this->syncFreshness($account->last_synced_at);

            return [
                'title' => 'MT5 live sync',
                'message' => 'Balance, equity, floating P&L, and rule usage refresh from MT5 trade events with timer fallback so open and closed trades appear quickly in the dashboard.',
                'meta' => [
                    'Sync freshness: '.$syncFreshness['label'],
                    'Last sync: '.$this->formatDateTime($account->last_synced_at),
                    'Data source: '.$this->sourceLabel((string) ($account->sync_source ?: 'mt5_ea')),
                ],
            ];
        }

        $consistencyLimitAmount = (float) $account->total_profit * (((float) $account->consistency_limit_percent) / 100);
        $consistencyUsage = $consistencyLimitAmount > 0
            ? round((((float) $account->today_profit) / $consistencyLimitAmount) * 100, 1)
            : 0.0;

        if ($consistencyUsage >= 80) {
            return [
                'title' => __('site.dashboard.consistency.title'),
                'message' => __('site.dashboard.consistency.message'),
                'meta' => [
                    __('site.dashboard.consistency.meta.today_profit').': '.$this->formatMoney((float) $account->today_profit),
                    __('site.dashboard.consistency.meta.limit').': '.$this->formatMoney($consistencyLimitAmount),
                    __('site.dashboard.consistency.meta.usage').': '.number_format($consistencyUsage, 1).'%',
                ],
            ];
        }

        return [
            'title' => 'Sync health',
            'message' => 'This dashboard now reads from the latest local account snapshot and rule evaluation state instead of preview-only demo data.',
            'meta' => [
                'Last sync: '.$this->formatDateTime($account->last_synced_at),
                'Platform status: '.$this->humanizeStatus((string) ($account->platform_status ?: 'pending_link')),
                'Trading days: '.sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountCardPayload(TradingAccount $account): array
    {
        $syncFreshness = $this->syncFreshness($account->last_synced_at);

        return [
            'id' => $account->id,
            'reference' => $account->account_reference ?? 'N/A',
            'plan' => $account->challengePlan?->name ?? $this->challengeTypeLabel((string) $account->challenge_type).' / '.((int) ($account->account_size / 1000)).'K',
            'challenge_type' => $this->challengeTypeLabel((string) $account->challenge_type),
            'challenge_phase' => $this->phaseLabel($account),
            'platform_slug' => $account->platform_slug,
            'account_size' => $this->formatMoney((float) $account->account_size),
            'status' => $this->humanizeStatus((string) ($account->account_status ?: $account->status ?: 'active')),
            'challenge_status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status ?: 'active')),
            'stage' => $account->stage,
            'balance' => $this->formatMoney((float) $account->balance),
            'equity' => $this->formatMoney((float) $account->equity),
            'floating_pnl' => $this->formatMoney((float) $account->profit_loss),
            'floating_pnl_tone' => $this->metricTone((float) $account->profit_loss),
            'progress' => number_format((float) $account->profit_target_progress_percent, 0).'%',
            'progress_value' => max(min((float) $account->profit_target_progress_percent, 100), 0),
            'sync_status' => $this->humanizeStatus((string) $account->sync_status),
            'last_synced_at' => $this->formatDateTime($account->last_synced_at),
            'last_evaluated_at' => $this->formatDateTime($account->last_evaluated_at),
            'sync_freshness' => $syncFreshness['label'],
            'sync_freshness_hint' => $syncFreshness['hint'],
            'sync_freshness_tone' => $syncFreshness['tone'],
            'daily_drawdown' => $this->formatMoney((float) $account->daily_drawdown),
            'max_drawdown' => $this->formatMoney((float) $account->max_drawdown),
            'daily_loss_used' => $this->formatMoney((float) $account->daily_loss_used),
            'daily_loss_limit' => $this->formatMoney((float) $account->daily_drawdown_limit_amount),
            'daily_loss_remaining' => $this->formatMoney(max((float) $account->daily_drawdown_limit_amount - (float) $account->daily_loss_used, 0)),
            'max_drawdown_used' => $this->formatMoney((float) $account->max_drawdown_used),
            'max_drawdown_limit' => $this->formatMoney((float) $account->max_drawdown_limit_amount),
            'max_drawdown_remaining' => $this->formatMoney(max((float) $account->max_drawdown_limit_amount - (float) $account->max_drawdown_used, 0)),
            'trading_days' => sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days),
            'platform_environment' => strtoupper((string) ($account->platform_environment ?: 'pending')),
            'platform_account_id' => $account->platform_account_id ?: 'Link pending',
            'platform_status' => $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link')),
            'sync_source' => $account->sync_source ? $this->sourceLabel((string) $account->sync_source) : 'Not available',
            'failure_reason' => $account->failure_reason ? $this->humanizeStatus((string) $account->failure_reason) : null,
            'needs_linking' => $account->platform_slug === 'ctrader' && blank($account->platform_account_id),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function payoutSummary(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                'next_window' => 'Not available yet',
                'eligible_profit' => $this->formatMoney(0),
                'cycle_note' => 'Payout windows appear after an account reaches funded status.',
                'status' => 'No funded accounts',
            ];
        }

        if (! $account->is_funded) {
            return [
                'next_window' => 'Available after funding',
                'eligible_profit' => $this->formatMoney(0),
                'cycle_note' => 'The current account is still in the challenge lifecycle and is not payout-eligible yet.',
                'status' => $this->humanizeStatus((string) $account->account_status),
            ];
        }

        $eligibleProfit = max((float) $account->total_profit, 0) * (((float) $account->profit_split) / 100);
        $fundedTiming = $this->fundedTiming($account, $this->planDefinitionForAccount($account));

        return [
            'next_window' => $this->formatDateTime($fundedTiming['payout_eligible_at']),
            'eligible_profit' => $this->formatMoney($eligibleProfit),
            'cycle_note' => 'First payout eligibility: '.$this->formatDateTime($fundedTiming['first_payout_eligible_at']),
            'status' => $this->humanizeStatus((string) $account->account_status),
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{first_payout_days:int,payout_cycle_days:int,first_payout_eligible_at:mixed,payout_eligible_at:mixed}
     */
    private function fundedTiming(TradingAccount $account, array $plan = []): array
    {
        $firstPayoutDays = (int) ($plan['funded']['first_withdrawal_days']
            ?? $account->challengePlan?->first_payout_days
            ?? config('wolforix.challenge_models.one_step.funded.first_withdrawal_days', 21));
        $payoutCycleDays = (int) ($plan['funded']['payout_cycle_days']
            ?? $account->challengePlan?->payout_cycle_days
            ?? 14);
        $firstPayoutEligibleAt = $account->first_payout_eligible_at;
        $payoutEligibleAt = $account->payout_eligible_at;

        if ($account->activated_at !== null) {
            $firstPayoutEligibleAt = $account->activated_at->copy()->addDays($firstPayoutDays);
            $cycleStartedAt = $account->payout_cycle_started_at ?? $account->activated_at;
            $payoutEligibleAt = $cycleStartedAt->copy()->addDays($payoutCycleDays);
        }

        return [
            'first_payout_days' => $firstPayoutDays,
            'payout_cycle_days' => $payoutCycleDays,
            'first_payout_eligible_at' => $firstPayoutEligibleAt,
            'payout_eligible_at' => $payoutEligibleAt,
        ];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function purchasedChallenges(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $user->loadMissing(['challengePurchases.order', 'challengePurchases.tradingAccounts']);

        return $user->challengePurchases
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($purchase): array {
                $order = $purchase->order;
                $linkedAccount = $purchase->tradingAccounts->sortByDesc('created_at')->first();

                return [
                    'reference' => $order?->order_number ?? 'N/A',
                    'plan' => $this->challengeTypeLabel($purchase->challenge_type).' / '.((int) ($purchase->account_size / 1000)).'K',
                    'amount' => $this->formatMoney((float) ($order?->final_price ?? 0), $purchase->currency),
                    'payment_provider' => $order?->payment_provider ? ucfirst($order->payment_provider) : 'N/A',
                    'payment_status' => $order?->payment_status ? ucfirst($order->payment_status) : 'N/A',
                    'account_status' => str($purchase->account_status)->replace('_', ' ')->title()->toString(),
                    'account_reference' => $linkedAccount?->account_reference ?? 'Pending link',
                    'sync_status' => $linkedAccount?->sync_status ? $this->humanizeStatus((string) $linkedAccount->sync_status) : 'Not synced',
                    'created_at' => $purchase->created_at?->format('M d, Y') ?? 'N/A',
                ];
            });
    }

    private function profitTargetAmount(TradingAccount $account): float
    {
        $storedAmount = (float) $account->profit_target_amount;

        if ($storedAmount > 0) {
            return $storedAmount;
        }

        return round(((float) $account->starting_balance * (float) $account->profit_target_percent) / 100, 2);
    }

    private function safePercentage(float $value, float $maximum): float
    {
        if ($maximum <= 0) {
            return 0.0;
        }

        return round(max(min(($value / $maximum) * 100, 100), 0), 1);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function chartPoints(TradingAccount $account): Collection
    {
        $snapshots = $account->balanceSnapshots()
            ->orderBy('snapshot_at')
            ->get(['snapshot_at', 'balance', 'equity', 'total_profit']);

        if ($snapshots->isEmpty()) {
            $fallbackDate = $account->last_synced_at ?? $account->created_at ?? now();

            return collect([
                [
                    'label' => Carbon::parse($fallbackDate)->format('M d'),
                    'date_iso' => Carbon::parse($fallbackDate)->toDateString(),
                    'balance' => round((float) $account->balance, 2),
                    'equity' => round((float) $account->equity, 2),
                    'total_profit' => round((float) $account->total_profit, 2),
                ],
            ]);
        }

        return $snapshots->map(function ($snapshot): array {
            $timestamp = Carbon::parse($snapshot->snapshot_at);

            return [
                'label' => $timestamp->format('M d'),
                'date_iso' => $timestamp->toDateString(),
                'balance' => round((float) $snapshot->balance, 2),
                'equity' => round((float) $snapshot->equity, 2),
                'total_profit' => round((float) $snapshot->total_profit, 2),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $points
     * @return Collection<int, array<string, mixed>>
     */
    private function sampleChartPoints(Collection $points, int $maxPoints = 32): Collection
    {
        $count = $points->count();

        if ($count <= $maxPoints) {
            return $points->values();
        }

        $step = max((int) ceil($count / $maxPoints), 1);
        $lastIndex = $count - 1;

        return $points
            ->values()
            ->filter(fn (array $point, int $index): bool => $index === 0 || $index === $lastIndex || $index % $step === 0)
            ->values();
    }

    private function weeklyProfit(TradingAccount $account): ?float
    {
        $cutoff = now()->subDays(7)->toDateString();
        $points = $this->chartPoints($account)
            ->filter(fn (array $point): bool => $point['date_iso'] >= $cutoff)
            ->values();

        if ($points->count() < 2) {
            return null;
        }

        return round((float) $points->last()['total_profit'] - (float) $points->first()['total_profit'], 2);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tradeAnalytics(TradingAccount $account): ?array
    {
        $payload = $this->latestSnapshotTradePayload($account);
        $tradeHistory = collect($payload['trade_history'] ?? []);

        if ($tradeHistory->isEmpty()) {
            return null;
        }

        $profits = $tradeHistory
            ->map(fn (array $row): float => (float) Arr::get($row, 'net_profit', 0))
            ->values();

        $grossProfit = round($profits->filter(fn (float $value): bool => $value > 0)->sum(), 2);
        $grossLoss = round(abs($profits->filter(fn (float $value): bool => $value < 0)->sum()), 2);
        $closedTrades = $profits->count();
        $wins = $profits->filter(fn (float $value): bool => $value > 0)->count();
        $fees = round($tradeHistory->sum(fn (array $row): float => abs((float) Arr::get($row, 'commission', 0))), 2);
        $averageTradeSize = $tradeHistory->avg(fn (array $row): float => (float) Arr::get($row, 'volume', 0));

        return [
            'closed_trades' => $closedTrades,
            'open_trades' => count($payload['open_positions'] ?? []),
            'total_pnl' => round($profits->sum(), 2),
            'gross_profit' => $grossProfit,
            'gross_loss' => $grossLoss,
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'best_profit' => round((float) ($profits->max() ?? 0), 2),
            'biggest_loss' => round((float) ($profits->min() ?? 0), 2),
            'expectancy' => $closedTrades > 0 ? round((float) $profits->avg(), 2) : null,
            'average_trade_size' => $averageTradeSize !== null ? round((float) $averageTradeSize, 2) : null,
            'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 1) : 0.0,
            'fees' => $fees,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestSnapshotTradePayload(TradingAccount $account): array
    {
        $latestSnapshot = $account->balanceSnapshots()
            ->latest('snapshot_at')
            ->first(['snapshot_at', 'payload']);

        $payload = is_array($latestSnapshot?->payload) ? $latestSnapshot->payload : [];

        return [
            'snapshot_at' => $latestSnapshot?->snapshot_at,
            'open_positions' => collect(Arr::get($payload, 'open_positions', []))
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all(),
            'trade_history' => collect(Arr::get($payload, 'trade_history', []))
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all(),
        ];
    }

    private function tradeSymbolLabel(array $row): string
    {
        $symbol = Arr::get($row, 'symbol')
            ?? Arr::get($row, 'symbol_name')
            ?? Arr::get($row, 'raw.symbolName')
            ?? Arr::get($row, 'raw.symbol')
            ?? Arr::get($row, 'raw.tradeData.symbolName')
            ?? Arr::get($row, 'raw.tradeData.symbol')
            ?? Arr::get($row, 'symbol_id');

        if (blank($symbol)) {
            return '—';
        }

        if (is_numeric($symbol)) {
            return 'Symbol #'.$symbol;
        }

        return (string) $symbol;
    }

    private function tradeSideLabel(mixed $value): string
    {
        $normalized = strtolower((string) $value);

        return match (true) {
            $normalized === '1',
            str_contains($normalized, 'buy'),
            str_contains($normalized, 'long') => 'Buy',
            $normalized === '2',
            str_contains($normalized, 'sell'),
            str_contains($normalized, 'short') => 'Sell',
            $normalized !== '' => str($normalized)->replace('_', ' ')->title()->toString(),
            default => '—',
        };
    }

    private function formatTradeDate(mixed $value): string
    {
        $parsed = $this->parseTradeTimestamp($value);

        return $parsed?->format('M d, Y H:i') ?? '—';
    }

    private function sortableTradeTimestamp(mixed $value): int
    {
        return $this->parseTradeTimestamp($value)?->getTimestamp() ?? 0;
    }

    private function parseTradeTimestamp(mixed $value): ?Carbon
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            if (is_numeric($value)) {
                $numeric = (int) $value;

                return $numeric > 9999999999
                    ? Carbon::createFromTimestampMs($numeric)
                    : Carbon::createFromTimestamp($numeric);
            }

            if (is_string($value) && $value !== '') {
                return Carbon::parse($value);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('M d, Y');
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->format('M d, Y');
        }

        return 'Not available';
    }

    private function formatNumeric(float $value): string
    {
        $decimals = abs($value - round($value)) < 0.01 ? 0 : 2;

        return number_format($value, $decimals);
    }

    private function statusTone(string $status): string
    {
        $normalized = strtolower($status);

        return match (true) {
            str_contains($normalized, 'fail'),
            str_contains($normalized, 'breach'),
            str_contains($normalized, 'error'),
            str_contains($normalized, 'delay') => 'rose',
            str_contains($normalized, 'pass'),
            str_contains($normalized, 'funded'),
            str_contains($normalized, 'success'),
            str_contains($normalized, 'connected'),
            str_contains($normalized, 'live') => 'emerald',
            str_contains($normalized, 'active'),
            str_contains($normalized, 'pending'),
            str_contains($normalized, 'evaluation'),
            str_contains($normalized, 'review') => 'amber',
            default => 'slate',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planDefinitionForAccount(TradingAccount $account): ?array
    {
        $challengeType = (string) $account->challenge_type;
        $accountSize = (int) $account->account_size;

        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}");

        return is_array($definition) ? $definition : null;
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        $formattedAmount = number_format(abs($amount), 2);
        $prefix = match (strtoupper($currency)) {
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        return ($amount < 0 ? '-' : '').$prefix.$formattedAmount;
    }

    private function challengeTypeLabel(string $challengeType): string
    {
        return (string) config(
            'wolforix.challenge_catalog.'.$challengeType.'.label',
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );
    }

    private function phaseLabel(TradingAccount $account): string
    {
        return match (true) {
            $account->challenge_type === 'one_step' => 'Single Phase',
            (int) $account->phase_index > 1 => 'Phase 2',
            default => 'Phase 1',
        };
    }

    private function humanizeStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    /**
     * @return array{label:string,hint:string,tone:string}
     */
    private function syncFreshness(mixed $value): array
    {
        if (! $value instanceof \DateTimeInterface) {
            return [
                'label' => 'Awaiting first sync',
                'hint' => 'No MT5 snapshot has been received yet.',
                'tone' => 'slate',
            ];
        }

        $timestamp = Carbon::instance($value);
        $seconds = now()->diffInSeconds($timestamp);
        $liveSeconds = max((int) config('trading.platforms.mt5.freshness.live_seconds', 15), 1);
        $recentSeconds = max((int) config('trading.platforms.mt5.freshness.recent_seconds', 60), $liveSeconds);
        $relative = $this->formatRelativeAge($timestamp);

        if ($seconds <= $liveSeconds) {
            return [
                'label' => 'Live now',
                'hint' => "Updated {$relative}.",
                'tone' => 'emerald',
            ];
        }

        if ($seconds <= $recentSeconds) {
            return [
                'label' => 'Synced recently',
                'hint' => "Updated {$relative}.",
                'tone' => 'amber',
            ];
        }

        return [
            'label' => 'Sync delayed',
            'hint' => "Updated {$relative}.",
            'tone' => 'rose',
        ];
    }

    private function formatRelativeAge(\DateTimeInterface $value): string
    {
        $seconds = max(now()->diffInSeconds($value), 0);

        return match (true) {
            $seconds < 60 => $seconds.'s ago',
            $seconds < 3600 => floor($seconds / 60).'m ago',
            $seconds < 86400 => floor($seconds / 3600).'h ago',
            default => floor($seconds / 86400).'d ago',
        };
    }

    private function metricTone(float $value): string
    {
        return match (true) {
            $value > 0.009 => 'emerald',
            $value < -0.009 => 'rose',
            default => 'slate',
        };
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'mt5_ea' => 'MT5 EA',
            'ctrader_api' => 'cTrader API',
            'platform_sync' => 'Platform Sync',
            default => $this->humanizeStatus($source),
        };
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('M d, Y H:i');
        }

        return 'Not synced yet';
    }

    /**
     * @return array<string, mixed>
     */
    private function ctraderConnectionPayload(?User $user): array
    {
        $connection = $user?->ctraderConnection;

        return [
            'is_connected' => $connection !== null && filled($connection->access_token),
            'broker_name' => (string) config('services.ctrader.broker_name', 'IC Markets'),
            'authorized_accounts_count' => is_array($connection?->authorized_accounts) ? count($connection->authorized_accounts) : 0,
            'authorized_accounts' => collect($connection?->authorized_accounts ?? [])
                ->filter(fn ($row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    'id' => (string) ($row['ctid_trader_account_id'] ?? ''),
                    'label' => trim(sprintf(
                        '%s%s%s',
                        (string) ($row['trader_login'] ?? 'Account'),
                        filled($row['trader_login'] ?? null) ? ' / ' : '',
                        strtoupper((string) ($row['environment'] ?? 'demo'))
                    )),
                    'broker' => (string) ($row['broker_title_short'] ?? config('services.ctrader.broker_name', 'IC Markets')),
                ])
                ->filter(fn (array $row): bool => $row['id'] !== '')
                ->values()
                ->all(),
            'last_authorized_at' => $this->formatDateTime($connection?->last_authorized_at),
            'last_synced_accounts_at' => $this->formatDateTime($connection?->last_synced_accounts_at),
            'last_error' => $connection?->last_error,
            'connect_url' => route('ctrader.auth.connect'),
            'link_url' => route('ctrader.auth.link-account'),
        ];
    }
}
