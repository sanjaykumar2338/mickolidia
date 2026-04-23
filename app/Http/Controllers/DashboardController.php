<?php

namespace App\Http\Controllers;

use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Pricing\ChallengePricingService;
use App\Services\TradingAccounts\TradeHistoryPanelBuilder;
use App\Services\Voice\OpenAiTextToSpeechService;
use App\Services\Wolfi\WolfiAssistantService;
use App\Services\Wolfi\WolfiVoiceSettings;
use App\Support\ChallengeAccountMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TradeHistoryPanelBuilder $tradeHistoryPanelBuilder,
        private readonly WolfiAssistantService $wolfiAssistantService,
    ) {}

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

    public function wolfi(Request $request, ChallengePricingService $pricingService): View
    {
        return view('dashboard.wolfi', $this->dashboardViewData($request, $pricingService));
    }

    public function wolfiVoices(
        Request $request,
        ChallengePricingService $pricingService,
        WolfiVoiceSettings $wolfiVoiceSettings,
        OpenAiTextToSpeechService $speechService,
    ): View {
        $isAdminRoute = $request->routeIs('admin.*');
        $selectedVoice = $wolfiVoiceSettings->selectedVoice();

        $voiceViewData = [
            'voiceOptions' => $wolfiVoiceSettings->voiceOptions(),
            'selectedVoiceId' => (string) ($selectedVoice['id'] ?? $wolfiVoiceSettings->selectedVoiceId()),
            'selectedVoice' => $selectedVoice,
            'voiceSampleText' => $wolfiVoiceSettings->sampleText(),
            'voicePreviewEndpoint' => route($isAdminRoute ? 'admin.wolfi.voices.preview' : 'dashboard.wolfi.voices.preview', [], false),
            'voiceUpdateEndpoint' => route($isAdminRoute ? 'admin.wolfi.voices.update' : 'dashboard.wolfi.voices.update', [], false),
            'voiceBackEndpoint' => $isAdminRoute
                ? route('admin.clients.index', [], false)
                : route('dashboard.wolfi', [], false),
            'voiceBackLabel' => $isAdminRoute ? __('Back to Clients') : __('Back to Wolfi Hub'),
            'showVoicePageFlash' => ! $isAdminRoute,
        ];
        $voiceViewData['ttsConfigured'] = $speechService->isConfigured($voiceViewData['selectedVoiceId']);

        if ($isAdminRoute) {
            return view('admin.wolfi-voices', $voiceViewData);
        }

        $viewData = array_merge(
            $this->dashboardViewData($request, $pricingService),
            $voiceViewData,
        );

        return view('dashboard.wolfi-voices', $viewData);
    }

    public function updateWolfiVoice(Request $request, WolfiVoiceSettings $wolfiVoiceSettings): RedirectResponse
    {
        $validated = $request->validate([
            'voice_id' => ['required', 'string', Rule::in($wolfiVoiceSettings->voiceIds())],
        ]);

        $voiceId = $wolfiVoiceSettings->saveSelectedVoiceId((string) $validated['voice_id']);
        $voice = $wolfiVoiceSettings->voiceById($voiceId);
        $voiceName = (string) ($voice['name'] ?? $voiceId);

        $redirectRoute = $request->routeIs('admin.*')
            ? 'admin.wolfi.voices'
            : 'dashboard.wolfi.voices';

        return redirect()
            ->route($redirectRoute)
            ->with('status', __("Wolfi voice updated to :voice.", ['voice' => $voiceName]));
    }

    public function previewWolfiVoice(
        Request $request,
        WolfiVoiceSettings $wolfiVoiceSettings,
        OpenAiTextToSpeechService $speechService,
    ): Response|JsonResponse {
        $validated = $request->validate([
            'voice_id' => ['required', 'string', Rule::in($wolfiVoiceSettings->voiceIds())],
            'text' => ['nullable', 'string', 'max:300'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        if (! $speechService->isConfigured((string) $validated['voice_id'])) {
            return response()->json([
                'message' => __('site.contact.voice_audio_unavailable'),
            ], 503);
        }

        $sampleText = trim((string) ($validated['text'] ?? ''));

        if ($sampleText === '') {
            $sampleText = $wolfiVoiceSettings->sampleText();
        }

        try {
            $speech = $speechService->synthesize(
                $sampleText,
                (string) ($validated['locale'] ?? ''),
                (string) $validated['voice_id'],
            );
        } catch (Throwable $error) {
            Log::warning('Wolfi voice preview synthesis failed.', [
                'voice_id' => $validated['voice_id'] ?? null,
                'locale' => $validated['locale'] ?? null,
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'message' => __('site.contact.voice_audio_unavailable'),
            ], 502);
        }

        return response($speech['audio'], 200, [
            'Content-Type' => $speech['content_type'],
            'Cache-Control' => 'no-store, max-age=0',
            'X-Wolfi-TTS-Provider' => $speech['provider'],
            'X-Wolfi-TTS-Voice' => $speech['voice'],
            'X-Wolfi-TTS-Model' => $speech['model'],
            'X-Wolfi-TTS-Locale' => $speech['locale'],
        ]);
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
            'challengeTradingAccounts.challengePlan',
            'challengePurchases.order.invoice',
        ]);

        $accounts = $user->challengeTradingAccounts
            ->sortByDesc('created_at')
            ->values();

        /** @var TradingAccount|null $primaryAccount */
        $primaryAccount = null;
        $requestedAccountId = (int) $request->query('account', 0);

        if ($requestedAccountId > 0) {
            $primaryAccount = $accounts->firstWhere('id', $requestedAccountId);
        }

        $primaryAccount ??= $accounts->first();
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
            'mt5Access' => $this->mt5AccessPanel($primaryAccount),
            'dashboardInsights' => $this->dashboardInsights($primaryAccount),
            'statisticsGrid' => $this->statisticsGrid($primaryAccount),
            'dailySummary' => $this->dailySummary($primaryAccount),
            'tradesPanel' => $this->tradesPanel($primaryAccount),
            'accounts' => $accounts->map(fn (TradingAccount $account): array => $this->accountCardPayload($account))->all(),
            'payoutSummary' => $this->payoutSummary($primaryAccount),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'language' => __('site.languages.'.($user->profile?->preferred_language ?? app()->getLocale())),
                'timezone' => $user->profile?->timezone ?? config('app.timezone', 'UTC'),
            ],
            'purchasedChallenges' => $this->purchasedChallenges(),
            'hasTradingAccounts' => $accounts->isNotEmpty(),
            'availablePlans' => $availablePlans,
            'wolfiPanel' => $this->wolfiAssistantService->panelData(
                $user,
                $primaryAccount,
                (string) ($request->route()?->getName() ?: 'dashboard'),
            ),
            'emptyState' => [
                'title' => __('No challenge accounts linked yet'),
                'message' => __('Paid challenges stay visible below. A trading account card appears here once the purchase is provisioned and linked for sync.'),
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
            'mt5Access' => $this->mt5AccessPanel(null),
            'dashboardInsights' => $this->dashboardInsights(null),
            'statisticsGrid' => $this->statisticsGrid(null),
            'dailySummary' => $this->dailySummary(null),
            'tradesPanel' => $this->tradesPanel(null),
            'accounts' => [],
            'payoutSummary' => $this->payoutSummary(null),
            'profile' => [
                'name' => '',
                'email' => '',
                'language' => __('site.languages.'.app()->getLocale()),
                'timezone' => config('app.timezone', 'UTC'),
            ],
            'purchasedChallenges' => collect(),
            'hasTradingAccounts' => false,
            'availablePlans' => $availablePlans,
            'wolfiPanel' => null,
            'emptyState' => [
                'title' => __('No challenge accounts linked yet'),
                'message' => __('Your dashboard will populate here after a paid challenge is provisioned.'),
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
        $challengeMetrics = $this->challengeMetrics($account);
        $phaseProfit = (float) $challengeMetrics['realized_profit'];
        $challengeBalance = (float) $challengeMetrics['challenge_balance'];
        $challengeEquity = (float) $challengeMetrics['challenge_equity'];
        $challengeStartingBalance = (float) $challengeMetrics['challenge_starting_balance'];

        return [
            'id' => $account->id,
            'reference' => $account->account_reference ?? __('N/A'),
            'plan' => $this->planLabel((string) $account->challenge_type, (int) $account->account_size),
            'challenge_type' => $this->challengeTypeLabel((string) $account->challenge_type),
            'challenge_phase' => $this->phaseLabel($account),
            'account_size' => $this->formatMoney((float) $account->account_size),
            'platform' => $account->platform,
            'platform_slug' => $account->platform_slug,
            'stage' => $account->stage,
            'status' => $account->status,
            'challenge_status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status)),
            'status_tone' => $this->statusTone((string) ($account->challenge_status ?: $account->account_status ?: 'active')),
            'account_status' => $this->humanizeStatus((string) $account->account_status),
            'start_date' => $this->formatDate($account->phase_started_at ?? $account->activated_at ?? $account->created_at),
            'platform_account_id' => $account->platform_account_id ?: __('Link pending'),
            'platform_login' => $account->platform_login ?: __('Link pending'),
            'platform_environment' => $this->humanizeStatus((string) ($account->platform_environment ?: 'pending')),
            'platform_status' => $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link')),
            'sync_status' => $this->humanizeStatus((string) $account->sync_status),
            'last_synced_at' => $this->formatDateTime($account->last_synced_at),
            'last_evaluated_at' => $this->formatDateTime($account->last_evaluated_at),
            'sync_freshness' => $syncFreshness['label'],
            'sync_freshness_hint' => $syncFreshness['hint'],
            'sync_freshness_tone' => $syncFreshness['tone'],
            'balance' => $this->formatMoney($challengeBalance),
            'starting_balance' => $this->formatMoney($challengeStartingBalance),
            'equity' => $this->formatMoney($challengeEquity),
            'floating_pnl' => $this->formatMoney((float) $account->profit_loss),
            'floating_pnl_tone' => $this->metricTone((float) $account->profit_loss),
            'total_profit' => $this->formatMoney($phaseProfit),
            'phase_profit' => $this->formatMoney($phaseProfit),
            'raw_balance' => $this->formatMoney((float) $challengeMetrics['raw_balance']),
            'raw_equity' => $this->formatMoney((float) $challengeMetrics['raw_equity']),
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
            'sync_source' => $account->sync_source ? $this->sourceLabel((string) $account->sync_source) : __('Not available'),
            'failure_reason' => $account->failure_reason ? $this->humanizeStatus((string) $account->failure_reason) : null,
            'trading_blocked' => (bool) $account->trading_blocked,
            'final_state_locked' => (bool) $account->final_state_locked,
            'state_notice' => $this->stateNotice($account),
            'certificate_url' => $this->certificateUrl($account),
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
        $reference = $account->account_reference ?? __('N/A');
        $platformAccountId = $account->platform_account_id ?: __('Link pending');
        $challengeMetrics = $this->challengeMetrics($account);
        $phaseProfit = (float) $challengeMetrics['realized_profit'];

        return [
            'title' => $this->planLabel((string) $account->challenge_type, (int) $account->account_size),
            'subtitle' => implode(' • ', array_filter([
                $reference,
                $platformAccountId,
                $account->platform,
            ])),
            'reference' => $reference,
            'platform' => $account->platform ?: __('Not linked'),
            'platform_account_id' => $platformAccountId,
            'start_date' => $this->formatDate($account->phase_started_at ?? $account->activated_at ?? $account->created_at),
            'challenge_phase' => $this->phaseLabel($account),
            'challenge_status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status ?: 'active')),
            'sync_status' => $this->humanizeStatus((string) ($account->sync_status ?: 'pending')),
            'sync_freshness' => $this->syncFreshness($account->last_synced_at),
            'badges' => $this->dashboardBadges($account),
            'state_notice' => $this->stateNotice($account),
            'metrics' => [
                [
                    'label' => __('Starting balance'),
                    'value' => $this->formatMoney((float) $challengeMetrics['challenge_starting_balance']),
                    'hint' => __('Challenge plan size'),
                    'tone' => 'slate',
                ],
                [
                    'label' => __('Current balance'),
                    'value' => $this->formatMoney((float) $challengeMetrics['challenge_balance']),
                    'hint' => __('Initial balance plus realized profit'),
                    'tone' => 'amber',
                ],
                [
                    'label' => __('Equity'),
                    'value' => $this->formatMoney((float) $challengeMetrics['challenge_equity']),
                    'hint' => __('Challenge balance plus open P&L'),
                    'tone' => 'sky',
                ],
                [
                    'label' => __('Floating P&L'),
                    'value' => $this->formatMoney((float) $account->profit_loss),
                    'hint' => __('Open positions'),
                    'tone' => $this->metricTone((float) $account->profit_loss),
                ],
                [
                    'label' => __('Recognized profit'),
                    'value' => $this->formatMoney($phaseProfit),
                    'hint' => __('Closed performance'),
                    'tone' => $this->metricTone($phaseProfit),
                ],
                [
                    'label' => __('Profit target'),
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
            ? 'funded'
            : match ((string) $account->challenge_status) {
                'passed' => 'passed',
                'failed' => 'failed',
                default => 'evaluation',
            };

        $badges = [
            ['label' => $this->challengeTypeLabel((string) $account->challenge_type), 'tone' => 'amber'],
            ['label' => $this->humanizeStatus($state), 'tone' => $this->statusTone($state)],
            ['label' => $this->humanizeStatus((string) ($account->account_status ?: $account->status ?: 'active')), 'tone' => $this->statusTone((string) ($account->account_status ?: $account->status ?: 'active'))],
            ['label' => $this->phaseLabel($account), 'tone' => 'slate'],
            ['label' => strtoupper((string) ($account->platform ?: 'N/A')), 'tone' => 'sky'],
            ['label' => $this->humanizeStatus((string) ($account->platform_environment ?: 'pending')), 'tone' => 'slate'],
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
        $challengeMetrics = $this->challengeMetrics($account);
        $phaseProfit = (float) $challengeMetrics['realized_profit'];
        $targetProgress = $targetAmount > 0
            ? round(max(min(($phaseProfit / $targetAmount) * 100, 100), 0), 2)
            : (float) $account->profit_target_progress_percent;
        $dailyLossLimit = (float) $account->daily_drawdown_limit_amount;
        $maxDrawdownLimit = (float) $account->max_drawdown_limit_amount;
        $minimumTradingDays = max((int) $account->minimum_trading_days, 1);
        $tradingDaysCompleted = (int) $account->trading_days_completed;

        return [
            [
                'label' => __('Profit target progress'),
                'value' => max(min($targetProgress, 100), 0),
                'value_label' => number_format($targetProgress, 1).'%',
                'current' => $this->formatMoney($phaseProfit),
                'target' => $this->formatMoney($targetAmount),
                'target_label' => __('Target'),
                'meta' => __('Remaining :amount', ['amount' => $this->formatMoney(max($targetAmount - $phaseProfit, 0))]),
                'tone' => 'amber',
            ],
            [
                'label' => __('Daily loss usage'),
                'value' => $this->safePercentage((float) $account->daily_loss_used, $dailyLossLimit),
                'value_label' => number_format($this->safePercentage((float) $account->daily_loss_used, $dailyLossLimit), 1).'%',
                'current' => $this->formatMoney((float) $account->daily_loss_used),
                'target' => $this->formatMoney($dailyLossLimit),
                'target_label' => __('Limit'),
                'meta' => __('Remaining :amount', ['amount' => $this->formatMoney(max($dailyLossLimit - (float) $account->daily_loss_used, 0))]),
                'tone' => ((float) $account->daily_loss_used) >= ($dailyLossLimit * 0.8) && $dailyLossLimit > 0 ? 'rose' : 'sky',
            ],
            [
                'label' => __('Max drawdown usage'),
                'value' => $this->safePercentage((float) $account->max_drawdown_used, $maxDrawdownLimit),
                'value_label' => number_format($this->safePercentage((float) $account->max_drawdown_used, $maxDrawdownLimit), 1).'%',
                'current' => $this->formatMoney((float) $account->max_drawdown_used),
                'target' => $this->formatMoney($maxDrawdownLimit),
                'target_label' => __('Limit'),
                'meta' => __('Remaining :amount', ['amount' => $this->formatMoney(max($maxDrawdownLimit - (float) $account->max_drawdown_used, 0))]),
                'tone' => ((float) $account->max_drawdown_used) >= ($maxDrawdownLimit * 0.8) && $maxDrawdownLimit > 0 ? 'rose' : 'slate',
            ],
            [
                'label' => __('Trading days completed'),
                'value' => $this->safePercentage((float) $tradingDaysCompleted, (float) $minimumTradingDays),
                'value_label' => sprintf('%d / %d', $tradingDaysCompleted, (int) $account->minimum_trading_days),
                'current' => (string) $tradingDaysCompleted,
                'target' => (string) $account->minimum_trading_days,
                'target_label' => __('Required'),
                'meta' => $tradingDaysCompleted >= (int) $account->minimum_trading_days
                    ? __('Minimum requirement met')
                    : __('Keep trading to unlock progression'),
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
            'empty_message' => __('The equity curve will appear after the first synced balance snapshot.'),
        ];

        if (! $account instanceof TradingAccount) {
            return $emptyState;
        }

        $points = $this->chartPoints($account);

        if ($points->isEmpty()) {
            return $emptyState;
        }

        $rangeDefinitions = [
            'all' => ['label' => __('All'), 'days' => null],
            'weekly' => ['label' => __('Weekly'), 'days' => 7],
            'monthly' => ['label' => __('Monthly'), 'days' => 30],
            'yearly' => ['label' => __('Yearly'), 'days' => 365],
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
                            'range_hint' => __('No synced data yet'),
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
                        'range_hint' => __(':from to :to', ['from' => $firstPoint['label'], 'to' => $lastPoint['label']]),
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
        $challengeMetrics = $this->challengeMetrics($account);
        $phaseProfit = (float) $challengeMetrics['realized_profit'];
        $cards = collect([
            [
                'label' => __('Daily profit'),
                'value' => $this->formatMoney((float) $account->today_profit),
                'hint' => __('Latest server-day result'),
                'tone' => $this->metricTone((float) $account->today_profit),
            ],
            [
                'label' => __('Unrealized profit'),
                'value' => $this->formatMoney((float) $account->profit_loss),
                'hint' => __('Open-position exposure'),
                'tone' => $this->metricTone((float) $account->profit_loss),
            ],
            [
                'label' => __('Weekly profit'),
                'value' => $weeklyProfit === null ? null : $this->formatMoney($weeklyProfit),
                'hint' => __('7-day change in synced total profit'),
                'tone' => $weeklyProfit === null ? 'slate' : $this->metricTone($weeklyProfit),
            ],
            [
                'label' => __('Net profit'),
                'value' => $this->formatMoney($phaseProfit),
                'hint' => __('Closed account performance'),
                'tone' => $this->metricTone($phaseProfit),
            ],
            [
                'label' => __('Phase profit'),
                'value' => $this->formatMoney($phaseProfit),
                'hint' => __('Current phase performance'),
                'tone' => $this->metricTone($phaseProfit),
            ],
            [
                'label' => __('Trading days completed'),
                'value' => sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days),
                'hint' => __('Progress toward the minimum rule'),
                'tone' => (int) $account->trading_days_completed >= (int) $account->minimum_trading_days ? 'emerald' : 'amber',
            ],
            [
                'label' => __('Daily loss used'),
                'value' => $this->formatMoney((float) $account->daily_loss_used),
                'hint' => __('Consumed daily loss room'),
                'tone' => 'slate',
            ],
            [
                'label' => __('Max drawdown used'),
                'value' => $this->formatMoney((float) $account->max_drawdown_used),
                'hint' => __('Consumed max loss room'),
                'tone' => 'slate',
            ],
        ])->filter(fn (array $card): bool => filled($card['value']));

        $tradeAnalytics = $this->tradeAnalytics($account);

        if ($tradeAnalytics !== null) {
            $cards = $cards->concat(array_filter([
                [
                    'label' => __('Gross profit'),
                    'value' => $this->formatMoney((float) $tradeAnalytics['gross_profit']),
                    'hint' => __('Closed winning trades'),
                    'tone' => 'emerald',
                ],
                [
                    'label' => __('Gross loss'),
                    'value' => $this->formatMoney(-(float) $tradeAnalytics['gross_loss']),
                    'hint' => __('Closed losing trades'),
                    'tone' => 'rose',
                ],
                [
                    'label' => __('Profit factor'),
                    'value' => $tradeAnalytics['profit_factor'] !== null ? number_format((float) $tradeAnalytics['profit_factor'], 2) : null,
                    'hint' => __('Gross profit divided by gross loss'),
                    'tone' => 'sky',
                ],
                [
                    'label' => __('Best profit'),
                    'value' => $this->formatMoney((float) $tradeAnalytics['best_profit']),
                    'hint' => __('Best closed trade'),
                    'tone' => 'emerald',
                ],
                [
                    'label' => __('Biggest loss'),
                    'value' => $this->formatMoney((float) $tradeAnalytics['biggest_loss']),
                    'hint' => __('Largest closed-trade loss'),
                    'tone' => 'rose',
                ],
                [
                    'label' => __('Expectancy'),
                    'value' => $tradeAnalytics['expectancy'] !== null ? $this->formatMoney((float) $tradeAnalytics['expectancy']) : null,
                    'hint' => __('Average P&L per closed trade'),
                    'tone' => $tradeAnalytics['expectancy'] !== null ? $this->metricTone((float) $tradeAnalytics['expectancy']) : 'slate',
                ],
                [
                    'label' => __('Average trade size'),
                    'value' => $tradeAnalytics['average_trade_size'] !== null ? $this->formatNumeric((float) $tradeAnalytics['average_trade_size']) : null,
                    'hint' => __('Mean closed-trade volume'),
                    'tone' => 'slate',
                ],
                [
                    'label' => __('Win rate'),
                    'value' => number_format((float) $tradeAnalytics['win_rate'], 1).'%',
                    'hint' => __('Winning closed trades'),
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
                'title' => __('History & analytics'),
                'message' => __('Per-trade analytics will appear once detailed trade rows are available inside the synced account snapshot.'),
                'cards' => [],
            ];
        }

        return [
            'is_available' => true,
            'title' => __('History & analytics'),
            'message' => __('The summary below is calculated from the latest synced trade-history rows attached to this account.'),
            'cards' => [
                [
                    'label' => __('Total trades'),
                    'value' => (string) $tradeAnalytics['closed_trades'],
                ],
                [
                    'label' => __('Fees'),
                    'value' => $this->formatMoney(-(float) $tradeAnalytics['fees']),
                ],
                [
                    'label' => __('Win rate'),
                    'value' => number_format((float) $tradeAnalytics['win_rate'], 1).'%',
                ],
                [
                    'label' => __('Total P&L'),
                    'value' => $this->formatMoney((float) $tradeAnalytics['total_pnl']),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mt5AccessPanel(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount || $account->platform_slug !== 'mt5') {
            return [
                'is_available' => false,
                'title' => __('MT5 credentials'),
                'message' => __('MT5 access details appear here once an MT5 challenge account is provisioned.'),
                'fields' => [],
                'privacy_note' => __('Only trader-facing account details are shown in the client dashboard.'),
            ];
        }

        $serverName = $this->accountMetadataValue($account, [
            'mt5_server',
            'server_name',
            'server',
            'platform_server',
            'broker_server',
            'credentials.server',
            'credentials.mt5_server',
        ]);

        $accountLogin = filled($account->platform_login)
            ? (string) $account->platform_login
            : (filled($account->platform_account_id) ? (string) $account->platform_account_id : null);
        $tradingPassword = $this->accountMetadataValue($account, [
            'trading_password',
            'password',
            'mt5_password',
            'platform_password',
            'credentials.password',
            'credentials.trading_password',
            'credentials.mt5_password',
        ]);
        $investorPassword = $this->accountMetadataValue($account, [
            'investor_password',
            'readonly_password',
            'read_only_password',
            'mt5_investor_password',
            'credentials.investor_password',
            'credentials.readonly_password',
            'credentials.read_only_password',
            'credentials.mt5_investor_password',
        ]);
        $platformStatus = $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link'));
        $disableStatusKey = $this->mt5DeactivationStatusKey($account);
        $mt5DisableStatus = $this->mt5DeactivationStatusLabel($account);

        if ($this->mt5AccessIsLocked($account)) {
            return [
                'is_available' => true,
                'title' => match ($disableStatusKey) {
                    'disabled' => __('MT5 disabled'),
                    'disable_requested' => __('MT5 disable requested'),
                    'disable_pending_ack' => __('MT5 disable pending acknowledgement'),
                    'disable_failed' => __('MT5 disable failed'),
                    default => __('MT5 final-state lock'),
                },
                'message' => match ($disableStatusKey) {
                    'disabled' => __('This account is in a final locked state and MT5 disablement has been confirmed.'),
                    'disable_requested' => __('This account is in a final locked state. MT5 disablement has been requested and confirmation is still pending.'),
                    'disable_pending_ack' => __('This account is in a final locked state. MT5 disablement is waiting for EA acknowledgement.'),
                    'disable_failed' => __('This account is in a final locked state, but the latest MT5 disable attempt failed and needs attention.'),
                    default => __('This account is in a final locked state. Review the MT5 disable status before assuming trading access is fully closed.'),
                },
                'fields' => [
                    [
                        'label' => __('Final state'),
                        'value' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status ?: 'locked')),
                        'hint' => __('Business outcome is locked locally and will not reopen from later MT5 snapshots.'),
                    ],
                    [
                        'label' => __('MT5 disable status'),
                        'value' => $mt5DisableStatus ?: $platformStatus,
                        'hint' => __('Only the disabled state confirms MT5 trading access has been fully invalidated.'),
                    ],
                    [
                        'label' => __('MT5 account login'),
                        'value' => $accountLogin ?: __('Link pending'),
                        'hint' => __('Login associated with the locked MT5 account.'),
                    ],
                    [
                        'label' => __('Account reference'),
                        'value' => $account->account_reference ?: __('N/A'),
                        'hint' => __('Internal Wolforix challenge reference.'),
                    ],
                ],
                'privacy_note' => __('Trading credentials stay hidden while this MT5 account is locked or pending disable confirmation.'),
            ];
        }

        return [
            'is_available' => true,
            'title' => __('MT5 credentials'),
            'message' => __('Use this secure access panel for the trader-facing MT5 identifiers linked to this challenge account.'),
            'fields' => [
                [
                    'label' => __('MT5 account login'),
                    'value' => $accountLogin ?: __('Link pending'),
                    'hint' => __('Trader login used by MT5 or the connected bridge.'),
                ],
                [
                    'label' => __('Server name'),
                    'value' => $serverName ?: ($account->platform_environment ? $this->humanizeStatus((string) $account->platform_environment) : __('Not provided yet')),
                    'hint' => $serverName ? __('Broker server supplied for this account.') : __('Server value is not stored on this account yet.'),
                ],
                [
                    'label' => __('Trading password'),
                    'value' => $tradingPassword ?: __('Password delivery pending'),
                    'hint' => $tradingPassword
                        ? __('Trading password stored for this MT5 account.')
                        : __('Trading password is not stored on this account yet.'),
                    'is_secret' => true,
                    'copyable' => filled($tradingPassword),
                ],
                [
                    'label' => __('Investor password'),
                    'value' => $investorPassword ?: __('Investor password pending'),
                    'hint' => $investorPassword
                        ? __('Investor password stored for read-only MT5 access.')
                        : __('Investor password is not stored on this account yet.'),
                    'is_secret' => true,
                    'copyable' => filled($investorPassword),
                ],
                [
                    'label' => __('Account reference'),
                    'value' => $account->account_reference ?: __('N/A'),
                    'hint' => __('Internal Wolforix challenge reference.'),
                ],
            ],
            'privacy_note' => __('Stored MT5 credentials are visible only inside this authenticated dashboard. Keep passwords private and do not share them outside Wolforix support channels.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardInsights(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                'is_available' => false,
                'balance' => $this->formatMoney(0),
                'equity' => $this->formatMoney(0),
                'balance_hint' => __('Challenge-relative current balance'),
                'equity_hint' => __('Challenge-relative equity'),
                'trading_days' => __('0 / 0'),
                'time_since_first_trade' => null,
                'time_since_first_trade_segments' => null,
                'first_trade_at' => null,
                'win_rate_available' => false,
                'win_rate' => __('No closed trades'),
                'win_rate_value' => 0.0,
                'win_rate_hint' => __('Win ratio appears after closed trades are synced.'),
                'closed_trades' => 0,
                'open_trades' => 0,
                'top_instruments' => [],
                'instrument_ring_style' => 'background: conic-gradient(rgba(148, 163, 184, 0.18) 0% 100%);',
                'instrument_message' => __('Instrument distribution activates once detailed MT5 trade rows are synced.'),
                'certificate_url' => null,
            ];
        }

        $challengeMetrics = $this->challengeMetrics($account);
        $tradeAnalytics = $this->tradeAnalytics($account);
        $payload = $this->latestSnapshotTradePayload($account);
        $timeline = $this->tradeTimeline($payload);
        $instrumentSummary = $this->topInstrumentSummary($payload);
        $closedTrades = (int) ($tradeAnalytics['closed_trades'] ?? 0);
        $winRate = $closedTrades > 0 ? (float) ($tradeAnalytics['win_rate'] ?? 0) : 0.0;

        return [
            'is_available' => true,
            'balance' => $this->formatMoney((float) $challengeMetrics['challenge_balance']),
            'equity' => $this->formatMoney((float) $challengeMetrics['challenge_equity']),
            'balance_hint' => __('Initial balance plus realized profit'),
            'equity_hint' => __('Challenge balance plus open P&L'),
            'trading_days' => sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days),
            'time_since_first_trade' => $timeline['time_since_first_trade'],
            'time_since_first_trade_segments' => $timeline['time_since_first_trade_segments'],
            'first_trade_at' => $timeline['first_trade_at'],
            'win_rate_available' => $closedTrades > 0,
            'win_rate' => $closedTrades > 0 ? number_format($winRate, 1).'%' : __('No closed trades'),
            'win_rate_value' => max(min($winRate, 100), 0),
            'win_rate_hint' => $closedTrades > 0
                ? __('Calculated from :count closed trades.', ['count' => $closedTrades])
                : __('Win ratio appears after closed trades are synced.'),
            'closed_trades' => $closedTrades,
            'open_trades' => (int) ($tradeAnalytics['open_trades'] ?? count($payload['open_positions'] ?? [])),
            'top_instruments' => $instrumentSummary['items'],
            'instrument_ring_style' => $instrumentSummary['ring_style'],
            'instrument_message' => $instrumentSummary['items'] === []
                ? __('Instrument distribution activates once detailed MT5 trade rows are synced.')
                : __('Top symbols by synced trade-row count.'),
            'certificate_url' => $this->certificateUrl($account),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statisticsGrid(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                'is_available' => false,
                'cards' => [],
                'message' => __('Statistics appear after the first synced account and trade-history payload.'),
                'notes' => [],
            ];
        }

        $tradeAnalytics = $this->tradeAnalytics($account);
        $payload = $this->latestSnapshotTradePayload($account);
        $timeline = $this->tradeTimeline($payload);
        $challengeMetrics = $this->challengeMetrics($account);
        $highWaterMark = $this->highWaterMarkBalance($account);
        $phaseProfit = (float) $challengeMetrics['realized_profit'];

        $cards = collect([
            [
                'label' => __('Win ratio'),
                'value' => $tradeAnalytics ? number_format((float) $tradeAnalytics['win_rate'], 1).'%' : null,
                'hint' => $tradeAnalytics ? __('Closed trades only') : null,
                'tone' => $tradeAnalytics && (float) $tradeAnalytics['win_rate'] >= 50 ? 'emerald' : 'amber',
            ],
            [
                'label' => __('HWM balance'),
                'value' => $this->formatMoney($highWaterMark),
                'hint' => __('Highest challenge-balance snapshot'),
                'tone' => 'amber',
            ],
            [
                'label' => __('Net profit'),
                'value' => $this->formatMoney($phaseProfit),
                'hint' => __('Current challenge phase'),
                'tone' => $this->metricTone($phaseProfit),
            ],
            [
                'label' => __('Average win'),
                'value' => $tradeAnalytics && $tradeAnalytics['average_win'] !== null ? $this->formatMoney((float) $tradeAnalytics['average_win']) : null,
                'hint' => __('Mean winning trade'),
                'tone' => 'emerald',
            ],
            [
                'label' => __('Average loss'),
                'value' => $tradeAnalytics && $tradeAnalytics['average_loss'] !== null ? $this->formatMoney((float) $tradeAnalytics['average_loss']) : null,
                'hint' => __('Mean losing trade'),
                'tone' => 'rose',
            ],
            [
                'label' => __('Profit factor'),
                'value' => $tradeAnalytics && $tradeAnalytics['profit_factor'] !== null ? number_format((float) $tradeAnalytics['profit_factor'], 2) : null,
                'hint' => __('Gross profit / gross loss'),
                'tone' => 'sky',
            ],
            [
                'label' => __('Best trade'),
                'value' => $tradeAnalytics ? $this->formatMoney((float) $tradeAnalytics['best_profit']) : null,
                'hint' => __('Highest closed-trade P&L'),
                'tone' => 'emerald',
            ],
            [
                'label' => __('Worst trade'),
                'value' => $tradeAnalytics ? $this->formatMoney((float) $tradeAnalytics['biggest_loss']) : null,
                'hint' => __('Lowest closed-trade P&L'),
                'tone' => 'rose',
            ],
            [
                'label' => __('Risk reward'),
                'value' => $tradeAnalytics && $tradeAnalytics['risk_reward'] !== null ? number_format((float) $tradeAnalytics['risk_reward'], 2).' R' : null,
                'hint' => __('Average win / average loss'),
                'tone' => 'slate',
            ],
            [
                'label' => __('Gross profit'),
                'value' => $tradeAnalytics ? $this->formatMoney((float) $tradeAnalytics['gross_profit']) : null,
                'hint' => __('Winning closed trades'),
                'tone' => 'emerald',
            ],
            [
                'label' => __('Gross loss'),
                'value' => $tradeAnalytics ? $this->formatMoney(-(float) $tradeAnalytics['gross_loss']) : null,
                'hint' => __('Losing closed trades'),
                'tone' => 'rose',
            ],
            [
                'label' => __('Expectancy'),
                'value' => $tradeAnalytics && $tradeAnalytics['expectancy'] !== null ? $this->formatMoney((float) $tradeAnalytics['expectancy']) : null,
                'hint' => __('Average P&L per trade'),
                'tone' => $tradeAnalytics && $tradeAnalytics['expectancy'] !== null ? $this->metricTone((float) $tradeAnalytics['expectancy']) : 'slate',
            ],
            [
                'label' => __('Average trade size'),
                'value' => $tradeAnalytics && $tradeAnalytics['average_trade_size'] !== null ? $this->formatNumeric((float) $tradeAnalytics['average_trade_size']) : null,
                'hint' => __('Mean closed-trade volume'),
                'tone' => 'slate',
            ],
            [
                'label' => __('Average trade duration'),
                'value' => $tradeAnalytics['average_trade_duration'] ?? null,
                'hint' => __('Requires open and close timestamps'),
                'tone' => 'slate',
            ],
            [
                'label' => __('Time since first trade'),
                'value' => $timeline['time_since_first_trade'],
                'hint' => $timeline['first_trade_at'] ?: __('Earliest synced trade row'),
                'tone' => 'amber',
            ],
        ])
            ->filter(fn (array $card): bool => filled($card['value']))
            ->values()
            ->all();

        $notes = [];

        if (! $tradeAnalytics) {
            $notes[] = __('Closed-trade metrics are hidden until trade history is included in the synced payload.');
        } elseif (($tradeAnalytics['average_trade_duration'] ?? null) === null) {
            $notes[] = __('Average trade duration is omitted when trade rows do not include both open and close timestamps.');
        }

        return [
            'is_available' => $cards !== [],
            'cards' => $cards,
            'message' => __('Only reliable values from snapshots or closed trade rows are shown.'),
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dailySummary(?TradingAccount $account): array
    {
        $emptyState = [
            'is_available' => false,
            'rows' => [],
            'message' => __('Daily summaries appear after trading-day activity is synced for this account.'),
        ];

        if (! $account instanceof TradingAccount) {
            return $emptyState;
        }

        $rows = $account->tradingDays()
            ->where('phase_index', (int) $account->phase_index)
            ->latest('trading_date')
            ->take(5)
            ->get()
            ->map(fn ($day): array => [
                'date' => $this->formatDate($day->trading_date),
                'activity' => __(':count trades', ['count' => (int) $day->activity_count]),
                'volume' => $this->formatNumeric((float) $day->volume),
                'first_activity_at' => $this->formatDateTime($day->first_activity_at),
                'last_activity_at' => $this->formatDateTime($day->last_activity_at),
                'source' => $day->source ? $this->sourceLabel((string) $day->source) : __('Snapshot payload'),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return $emptyState;
        }

        return [
            'is_available' => true,
            'rows' => $rows,
            'message' => __('Recent trading-day activity derived from synced challenge records.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tradesPanel(?TradingAccount $account): array
    {
        return $this->tradeHistoryPanelBuilder->build($account, [
            'empty_message' => __('Trade visualization activates once detailed MT5 trade rows are synced for this account. Daily activity counts may already appear in the summary above.'),
            'available_message' => __('The latest synced snapshot powers this table. Open and closed rows are shown only when the platform payload includes them.'),
        ]);
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
                    'hint' => __('No challenge account linked yet.'),
                ],
                [
                    'label' => __('Equity'),
                    'value' => $this->formatMoney(0),
                    'hint' => __('Equity appears after the first MT5 snapshot.'),
                ],
                [
                    'label' => __('Floating P&L'),
                    'value' => $this->formatMoney(0),
                    'hint' => __('Open-position profit appears after the first live update.'),
                ],
                [
                    'label' => __('Sync freshness'),
                    'value' => __('Awaiting sync'),
                    'hint' => __('The first successful MT5 update will mark this account as live.'),
                ],
            ];
        }

        $syncFreshness = $this->syncFreshness($account->last_synced_at);
        $challengeMetrics = $this->challengeMetrics($account);

        return [
            [
                'label' => __('site.dashboard.cards.balance'),
                'value' => $this->formatMoney((float) $challengeMetrics['challenge_balance']),
                'hint' => __('Challenge balance based on plan size plus realized profit.'),
            ],
            [
                'label' => __('Equity'),
                'value' => $this->formatMoney((float) $challengeMetrics['challenge_equity']),
                'hint' => __('Challenge-relative equity including open trade exposure.'),
            ],
            [
                'label' => __('Floating P&L'),
                'value' => $this->formatMoney((float) $account->profit_loss),
                'hint' => __('Open-position floating profit or loss from the latest sync.'),
            ],
            [
                'label' => __('Sync freshness'),
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
                'title' => __('Account provisioning'),
                'message' => $purchaseCount > 0
                    ? __('Your paid challenge records are saved. Account metrics appear here once the platform account is linked and synced.')
                    : __('Purchase a challenge to create your first tracked trading account.'),
                'meta' => [
                    __('Paid challenges: :count', ['count' => $purchaseCount]),
                    __('Platform: :value', ['value' => 'MT5']),
                    __('Sync status: waiting for account link'),
                ],
            ];
        }

        $consistency = $this->consistencyState($account);

        if (in_array($account->challenge_status, ['failed', 'passed'], true)) {
            if ($account->challenge_status === 'failed') {
                return [
                    'title' => __('Challenge failed - trading blocked'),
                    'message' => __('This account has breached a challenge rule. Local trading is locked while MT5 disable status is tracked.'),
                    'meta' => [
                        __('Reason: :value', ['value' => $account->failure_reason ? $this->humanizeStatus((string) $account->failure_reason) : __('Rule breach')]),
                        __('Trading blocked: :value', ['value' => (bool) $account->trading_blocked ? __('Yes') : __('No')]),
                        __('MT5 disable status: :value', ['value' => $this->mt5DeactivationStatusLabel($account) ?: $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link'))]),
                        __('Last evaluation: :value', ['value' => $this->formatDateTime($account->last_evaluated_at)]),
                    ],
                ];
            }

            return [
                'title' => __('Challenge passed'),
                'message' => __('This challenge has reached the required target. Local trading is locked while MT5 disable status is tracked for the next step.'),
                'meta' => [
                    __('Passed at: :value', ['value' => $this->formatDateTime($account->passed_at)]),
                    __('Profit target: :value', ['value' => $this->formatMoney($this->profitTargetAmount($account))]),
                    __('Trading blocked: :value', ['value' => (bool) $account->trading_blocked ? __('Yes') : __('No')]),
                    __('MT5 disable status: :value', ['value' => $this->mt5DeactivationStatusLabel($account) ?: $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link'))]),
                ],
            ];
        }

        if ((bool) ($consistency['warning_visible'] ?? false)) {
            return [
                'title' => __('Consistency rule warning'),
                'message' => $consistency['status'] === 'breach'
                    ? __('Your profit concentration has reached the consistency rule threshold. Review your trading-day distribution.')
                    : __('You are approaching the consistency rule limit. Profits should be spread across multiple trading days.'),
                'meta' => [
                    __('Current month profit').': '.$this->formatMoney((float) ($consistency['current_month_profit'] ?? 0)),
                    __('Highest single-day profit').': '.$this->formatMoney((float) ($consistency['highest_single_day_profit'] ?? 0)),
                    __('Ratio').': '.number_format((float) ($consistency['ratio_percent'] ?? 0), 2).'%',
                ],
            ];
        }

        if ($account->platform_slug === 'mt5') {
            $syncFreshness = $this->syncFreshness($account->last_synced_at);

            return [
                'title' => __('MT5 live sync'),
                'message' => __('Challenge balance, equity, floating P&L, and rule usage refresh from MT5 trade events with timer fallback so open and closed trades appear quickly in the dashboard.'),
                'meta' => [
                    __('Sync freshness: :value', ['value' => $syncFreshness['label']]),
                    __('Last sync: :value', ['value' => $this->formatDateTime($account->last_synced_at)]),
                    __('Data source: :value', ['value' => $this->sourceLabel((string) ($account->sync_source ?: 'mt5_ea'))]),
                ],
            ];
        }

        return [
            'title' => __('Sync health'),
            'message' => __('This dashboard now reads from the latest local account snapshot and rule evaluation state instead of preview-only demo data.'),
            'meta' => [
                __('Last sync: :value', ['value' => $this->formatDateTime($account->last_synced_at)]),
                __('Platform status: :value', ['value' => $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link'))]),
                __('Trading days: :value', ['value' => sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days)]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountCardPayload(TradingAccount $account): array
    {
        $syncFreshness = $this->syncFreshness($account->last_synced_at);
        $challengeMetrics = $this->challengeMetrics($account);
        $consistency = $this->consistencyState($account);

        return [
            'id' => $account->id,
            'reference' => $account->account_reference ?? __('N/A'),
            'plan' => $this->planLabel((string) $account->challenge_type, (int) $account->account_size),
            'challenge_type' => $this->challengeTypeLabel((string) $account->challenge_type),
            'challenge_phase' => $this->phaseLabel($account),
            'platform_slug' => $account->platform_slug,
            'account_size' => $this->formatMoney((float) $account->account_size),
            'status' => $this->humanizeStatus((string) ($account->account_status ?: $account->status ?: 'active')),
            'challenge_status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status ?: 'active')),
            'status_tone' => $this->statusTone((string) ($account->challenge_status ?: $account->account_status ?: 'active')),
            'stage' => $account->stage,
            'balance' => $this->formatMoney((float) $challengeMetrics['challenge_balance']),
            'equity' => $this->formatMoney((float) $challengeMetrics['challenge_equity']),
            'starting_balance' => $this->formatMoney((float) $challengeMetrics['challenge_starting_balance']),
            'total_profit' => $this->formatMoney((float) $challengeMetrics['realized_profit']),
            'raw_balance' => $this->formatMoney((float) $challengeMetrics['raw_balance']),
            'raw_equity' => $this->formatMoney((float) $challengeMetrics['raw_equity']),
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
            'platform_environment' => $this->humanizeStatus((string) ($account->platform_environment ?: 'pending')),
            'platform_account_id' => $account->platform_account_id ?: __('Link pending'),
            'platform_status' => $this->humanizeStatus((string) ($account->platform_status ?: 'pending_link')),
            'mt5_deactivation_status' => $this->mt5DeactivationStatusLabel($account),
            'sync_source' => $account->sync_source ? $this->sourceLabel((string) $account->sync_source) : __('Not available'),
            'failure_reason' => $account->failure_reason ? $this->humanizeStatus((string) $account->failure_reason) : null,
            'trading_blocked' => (bool) $account->trading_blocked,
            'final_state_locked' => (bool) $account->final_state_locked,
            'state_notice' => $this->stateNotice($account),
            'certificate_url' => $this->certificateUrl($account),
            'consistency' => $consistency,
            'dashboard_url' => route('dashboard', ['account' => $account->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consistencyState(TradingAccount $account): array
    {
        $consistency = (array) data_get($account->rule_state, 'consistency', []);
        $status = (string) ($consistency['status'] ?? $account->consistency_status ?? 'clear');

        return [
            'status' => $status,
            'warning_visible' => (bool) ($consistency['warning_visible'] ?? in_array($status, ['approaching', 'breach'], true)),
            'current_month_profit' => (float) ($consistency['current_month_profit'] ?? 0),
            'highest_single_day_profit' => (float) ($consistency['highest_single_day_profit'] ?? 0),
            'highest_single_day_date' => $consistency['highest_single_day_date'] ?? null,
            'ratio_percent' => (float) ($consistency['ratio_percent'] ?? 0),
            'approach_threshold_percent' => (float) ($consistency['approach_threshold_percent'] ?? max((float) $account->consistency_limit_percent - 5, 0)),
            'breach_threshold_percent' => (float) ($consistency['breach_threshold_percent'] ?? $account->consistency_limit_percent),
            'active_threshold_percent' => $consistency['active_threshold_percent'] ?? null,
            'last_triggered_threshold_percent' => $consistency['last_triggered_threshold_percent'] ?? $account->consistency_last_trigger_threshold,
            'triggered_at' => $consistency['triggered_at'] ?? optional($account->consistency_triggered_at)->toIso8601String(),
        ];
    }

    private function mt5AccessIsLocked(TradingAccount $account): bool
    {
        $platformStatus = (string) $account->platform_status;

        return (bool) $account->trading_blocked
            || in_array((string) $account->challenge_status, ['passed', 'failed'], true)
            || in_array($platformStatus, ['disable_requested', 'disable_pending_ack', 'disabled', 'disable_failed', 'disabled_pending_ack'], true)
            || $this->mt5DeactivationStatusKey($account) !== null;
    }

    private function mt5DeactivationStatusKey(TradingAccount $account): ?string
    {
        if ($account->platform_slug !== 'mt5') {
            return null;
        }

        $current = data_get($account->meta, 'mt5_deactivation.current');

        if (is_array($current) && filled($current['status'] ?? null)) {
            return (string) $current['status'];
        }

        $events = (array) data_get($account->meta, 'mt5_deactivation.events', []);

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            if (filled($event['status'] ?? null)) {
                return (string) $event['status'];
            }
        }

        if (in_array((string) $account->platform_status, ['disable_requested', 'disable_pending_ack', 'disabled', 'disable_failed', 'disabled_pending_ack'], true)) {
            return (string) $account->platform_status;
        }

        return null;
    }

    private function mt5DeactivationStatusLabel(TradingAccount $account): ?string
    {
        $status = $this->mt5DeactivationStatusKey($account);

        if ($status === null) {
            return null;
        }

        return match ($status) {
            'disable_requested', 'requested' => __('Disable requested'),
            'disable_pending_ack', 'pending_ea_ack', 'disabled_pending_ack' => __('Pending MT5 acknowledgement'),
            'disabled' => __('Disabled'),
            'disable_failed', 'failed' => __('Disable request failed'),
            'pending' => __('Sending disable request'),
            default => $this->humanizeStatus($status),
        };
    }

    /**
     * @return array<string, string>
     */
    private function mt5DeactivationStateCopy(TradingAccount $account): array
    {
        $status = $this->mt5DeactivationStatusLabel($account) ?: __('Pending status');

        if ($account->challenge_status === 'failed') {
            return [
                'title' => __('Failed / final lock'),
                'message' => __('Challenge failed. Local trading is locked. MT5 disable status: :status.', [
                    'status' => $status,
                ]),
            ];
        }

        if ($account->challenge_status === 'passed') {
            return [
                'title' => __('Passed / final lock'),
                'message' => __('Challenge target reached. Local trading is locked. MT5 disable status: :status.', [
                    'status' => $status,
                ]),
            ];
        }

        return [
            'title' => __('Trading blocked'),
            'message' => __('Local trading is locked. MT5 disable status: :status.', [
                'status' => $status,
            ]),
        ];
    }

    private function mt5DeactivationStatusDetail(TradingAccount $account): ?array
    {
        $current = data_get($account->meta, 'mt5_deactivation.current');

        if (! is_array($current)) {
            return null;
        }

        return [
            'event' => (string) ($current['event'] ?? ''),
            'reason' => (string) ($current['reason'] ?? ''),
            'status' => (string) ($current['status'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function payoutSummary(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                'next_window' => __('Not available yet'),
                'eligible_profit' => $this->formatMoney(0),
                'cycle_note' => __('Payout windows appear after an account reaches funded status.'),
                'status' => __('No funded accounts'),
            ];
        }

        if (! $account->is_funded) {
            return [
                'next_window' => __('Available after funding'),
                'eligible_profit' => $this->formatMoney(0),
                'cycle_note' => __('The current account is still in the challenge lifecycle and is not payout-eligible yet.'),
                'status' => $this->humanizeStatus((string) $account->account_status),
            ];
        }

        $eligibleProfit = max((float) $account->total_profit, 0) * (((float) $account->profit_split) / 100);
        $fundedTiming = $this->fundedTiming($account, $this->planDefinitionForAccount($account));

        return [
            'next_window' => $this->formatDateTime($fundedTiming['payout_eligible_at']),
            'eligible_profit' => $this->formatMoney($eligibleProfit),
            'cycle_note' => __('First payout eligibility: :value', ['value' => $this->formatDateTime($fundedTiming['first_payout_eligible_at'])]),
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

        $user->loadMissing(['challengePurchases.order.invoice', 'challengePurchases.tradingAccounts']);

        return $user->challengePurchases
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($purchase): array {
                $order = $purchase->order;
                $linkedAccount = $purchase->tradingAccounts->sortByDesc('created_at')->first();

                return [
                    'reference' => $order?->order_number ?? __('N/A'),
                    'plan' => $this->planLabel((string) $purchase->challenge_type, (int) $purchase->account_size),
                    'amount' => $this->formatMoney((float) ($order?->final_price ?? 0), $purchase->currency),
                    'payment_provider' => $order?->payment_provider ? ucfirst($order->payment_provider) : __('N/A'),
                    'payment_status' => $order?->payment_status ? $this->humanizeStatus((string) $order->payment_status) : __('N/A'),
                    'account_status' => $purchase->account_status ? $this->humanizeStatus((string) $purchase->account_status) : __('Not available'),
                    'account_reference' => $linkedAccount?->account_reference ?? __('Pending link'),
                    'sync_status' => $linkedAccount?->sync_status ? $this->humanizeStatus((string) $linkedAccount->sync_status) : __('Not synced'),
                    'created_at' => $purchase->created_at ? $this->formatDate($purchase->created_at) : __('N/A'),
                    'invoice_number' => $order?->invoice?->invoice_number,
                    'invoice_download_url' => $order?->invoice ? route('dashboard.invoices.download', $order->invoice) : null,
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

    /**
     * @return array<string, float|string|null>
     */
    private function challengeMetrics(TradingAccount $account, array $snapshot = []): array
    {
        return app(ChallengeAccountMetrics::class)->resolve($account, $snapshot);
    }

    /**
     * @return array{tone:string,title:string,message:string}|null
     */
    private function stateNotice(TradingAccount $account): ?array
    {
        if ($account->challenge_status === 'failed') {
            $copy = $this->mt5DeactivationStateCopy($account);

            return [
                'tone' => 'rose',
                'title' => $copy['title'],
                'message' => $copy['message'],
            ];
        }

        if ($account->challenge_status === 'passed') {
            $copy = $this->mt5DeactivationStateCopy($account);

            return [
                'tone' => 'emerald',
                'title' => $copy['title'],
                'message' => $copy['message'],
            ];
        }

        if ((bool) $account->trading_blocked) {
            $copy = $this->mt5DeactivationStateCopy($account);

            return [
                'tone' => 'amber',
                'title' => $copy['title'],
                'message' => $copy['message'],
            ];
        }

        return null;
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
            ->get(['snapshot_at', 'balance', 'equity', 'profit_loss', 'total_profit']);

        if ($snapshots->isEmpty()) {
            $fallbackDate = $account->last_synced_at ?? $account->created_at ?? now();
            $challengeMetrics = $this->challengeMetrics($account);

            return collect([
                [
                    'label' => Carbon::parse($fallbackDate)->locale(app()->getLocale())->translatedFormat('M d'),
                    'date_iso' => Carbon::parse($fallbackDate)->toDateString(),
                    'balance' => round((float) $challengeMetrics['challenge_balance'], 2),
                    'equity' => round((float) $challengeMetrics['challenge_equity'], 2),
                    'total_profit' => round((float) $challengeMetrics['realized_profit'], 2),
                ],
            ]);
        }

        return $snapshots->map(function ($snapshot) use ($account): array {
            $timestamp = Carbon::parse($snapshot->snapshot_at);
            $challengeMetrics = $this->challengeMetrics($account, [
                'balance' => $snapshot->balance,
                'equity' => $snapshot->equity,
                'profit_loss' => $snapshot->profit_loss,
                'total_profit' => $snapshot->total_profit,
            ]);

            return [
                'label' => $timestamp->locale(app()->getLocale())->translatedFormat('M d'),
                'date_iso' => $timestamp->toDateString(),
                'balance' => round((float) $challengeMetrics['challenge_balance'], 2),
                'equity' => round((float) $challengeMetrics['challenge_equity'], 2),
                'total_profit' => round((float) $challengeMetrics['realized_profit'], 2),
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
            ->map(fn (array $row): float => $this->tradeProfitValue($row))
            ->values();

        $grossProfit = round($profits->filter(fn (float $value): bool => $value > 0)->sum(), 2);
        $grossLoss = round(abs($profits->filter(fn (float $value): bool => $value < 0)->sum()), 2);
        $closedTrades = $profits->count();
        $winningProfits = $profits->filter(fn (float $value): bool => $value > 0)->values();
        $losingProfits = $profits->filter(fn (float $value): bool => $value < 0)->values();
        $wins = $winningProfits->count();
        $fees = round($tradeHistory->sum(fn (array $row): float => abs($this->tradeFeeValue($row))), 2);
        $averageTradeSize = $tradeHistory->avg(fn (array $row): float => $this->tradeVolumeValue($row));
        $averageWin = $winningProfits->isNotEmpty() ? round((float) $winningProfits->avg(), 2) : null;
        $averageLoss = $losingProfits->isNotEmpty() ? round((float) $losingProfits->avg(), 2) : null;
        $averageLossAbs = $averageLoss !== null ? abs($averageLoss) : null;
        $riskReward = $averageWin !== null && $averageLossAbs !== null && $averageLossAbs > 0
            ? round($averageWin / $averageLossAbs, 2)
            : null;

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
            'average_win' => $averageWin,
            'average_loss' => $averageLoss,
            'risk_reward' => $riskReward,
            'average_trade_duration' => $this->averageTradeDuration($tradeHistory),
            'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 1) : 0.0,
            'fees' => $fees,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{items:list<array<string, mixed>>,ring_style:string}
     */
    private function topInstrumentSummary(array $payload): array
    {
        $rows = collect($payload['trade_history'] ?? [])
            ->merge($payload['open_positions'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->values();

        if ($rows->isEmpty()) {
            return [
                'items' => [],
                'ring_style' => 'background: conic-gradient(rgba(148, 163, 184, 0.18) 0% 100%);',
            ];
        }

        $groups = [];

        foreach ($rows as $row) {
            $symbol = $this->tradeSymbolLabel($row);

            if ($symbol === __('—')) {
                continue;
            }

            $groups[$symbol] ??= [
                'symbol' => $symbol,
                'count' => 0,
                'volume' => 0.0,
                'pnl' => 0.0,
            ];

            $groups[$symbol]['count'] += $this->tradeCountValue($row);
            $groups[$symbol]['volume'] += $this->tradeVolumeValue($row);
            $groups[$symbol]['pnl'] += $this->tradeProfitValue($row);
        }

        $totalCount = collect($groups)->sum('count');

        if ($totalCount <= 0) {
            return [
                'items' => [],
                'ring_style' => 'background: conic-gradient(rgba(148, 163, 184, 0.18) 0% 100%);',
            ];
        }

        $colors = ['#f4b74a', '#38bdf8', '#34d399', '#fb7185', '#a78bfa'];
        $cursor = 0.0;
        $segments = [];

        $items = collect($groups)
            ->sort(fn (array $left, array $right): int => [$right['count'], $right['volume']] <=> [$left['count'], $left['volume']])
            ->take(5)
            ->values()
            ->map(function (array $group, int $index) use ($totalCount, $colors, &$cursor, &$segments): array {
                $share = round(((int) $group['count'] / $totalCount) * 100, 1);
                $color = $colors[$index % count($colors)];
                $segmentEnd = min($cursor + $share, 100);

                if ($segmentEnd > $cursor) {
                    $segments[] = sprintf('%s %.1f%% %.1f%%', $color, $cursor, $segmentEnd);
                    $cursor = $segmentEnd;
                }

                return [
                    'symbol' => $group['symbol'],
                    'count' => (int) $group['count'],
                    'count_label' => __(':count trades', ['count' => (int) $group['count']]),
                    'volume' => $this->formatNumeric((float) $group['volume']),
                    'pnl' => $this->formatMoney((float) $group['pnl']),
                    'pnl_tone' => $this->metricTone((float) $group['pnl']),
                    'share' => $share,
                    'share_label' => number_format($share, 1).'%',
                    'color' => $color,
                ];
            })
            ->all();

        if ($cursor < 100) {
            $segments[] = sprintf('rgba(148, 163, 184, 0.16) %.1f%% 100%%', $cursor);
        }

        return [
            'items' => $items,
            'ring_style' => 'background: conic-gradient('.implode(', ', $segments).');',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{time_since_first_trade:?string,time_since_first_trade_segments:?array<string, int>,first_trade_at:?string}
     */
    private function tradeTimeline(array $payload): array
    {
        $timestamps = collect($payload['trade_history'] ?? [])
            ->merge($payload['open_positions'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->flatMap(function (array $row): array {
                return [
                    $this->tradeOpenTimestamp($row),
                    $this->tradeCloseTimestamp($row),
                ];
            })
            ->filter(fn ($timestamp): bool => $timestamp instanceof Carbon)
            ->sortBy(fn (Carbon $timestamp): int => $timestamp->getTimestamp())
            ->values();

        /** @var Carbon|null $firstTradeAt */
        $firstTradeAt = $timestamps->first();

        if (! $firstTradeAt instanceof Carbon) {
            return [
                'time_since_first_trade' => null,
                'time_since_first_trade_segments' => null,
                'first_trade_at' => null,
            ];
        }

        $seconds = $firstTradeAt->diffInSeconds(now());

        return [
            'time_since_first_trade' => $this->formatCompactDuration($seconds),
            'time_since_first_trade_segments' => $this->durationSegments($seconds),
            'first_trade_at' => $this->formatTradeDate($firstTradeAt),
        ];
    }

    private function highWaterMarkBalance(TradingAccount $account): float
    {
        $points = $this->chartPoints($account);

        if ($points->isNotEmpty()) {
            return round((float) $points->max(fn (array $point): float => (float) $point['balance']), 2);
        }

        return round((float) $this->challengeMetrics($account)['challenge_balance'], 2);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tradeHistory
     */
    private function averageTradeDuration(Collection $tradeHistory): ?string
    {
        $durations = $tradeHistory
            ->map(function (array $row): ?int {
                $openedAt = $this->tradeOpenTimestamp($row);
                $closedAt = $this->tradeCloseTimestamp($row);

                if (! $openedAt instanceof Carbon || ! $closedAt instanceof Carbon || $closedAt->lt($openedAt)) {
                    return null;
                }

                return $openedAt->diffInSeconds($closedAt);
            })
            ->filter(fn ($seconds): bool => is_int($seconds) && $seconds >= 0)
            ->values();

        if ($durations->isEmpty()) {
            return null;
        }

        return $this->formatCompactDuration((int) round((float) $durations->avg()));
    }

    private function tradeOpenTimestamp(array $row): ?Carbon
    {
        foreach ([
            'open_timestamp',
            'open_time',
            'openTime',
            'openTimeMsc',
            'time',
            'Time',
            'time_open',
            'TimeOpen',
            'opened_at',
            'raw.openTimestamp',
            'raw.tradeData.openTimestamp',
            'raw.open_time',
            'raw.openTime',
            'raw.time',
        ] as $key) {
            $timestamp = $this->parseTradeTimestamp(Arr::get($row, $key));

            if ($timestamp instanceof Carbon) {
                return $timestamp;
            }
        }

        return null;
    }

    private function tradeCloseTimestamp(array $row): ?Carbon
    {
        foreach ([
            'close_timestamp',
            'closed_at',
            'close_time',
            'closeTime',
            'closeTimeMsc',
            'execution_timestamp',
            'execution_time',
            'executionTime',
            'time_close',
            'TimeClose',
            'raw.closeTimestamp',
            'raw.executionTimestamp',
            'raw.closeTime',
            'raw.executionTime',
        ] as $key) {
            $timestamp = $this->parseTradeTimestamp(Arr::get($row, $key));

            if ($timestamp instanceof Carbon) {
                return $timestamp;
            }
        }

        return null;
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
            'open_positions' => $this->tradeRowsFromPayload($payload, [
                'open_positions',
                'openPositions',
                'open.positions',
                'positions.open',
                'positions',
                'current_positions',
                'currentPositions',
                'raw.open_positions',
                'raw.openPositions',
                'raw.open.positions',
                'raw.positions.open',
                'raw.positions',
                'raw.current_positions',
                'mt5.open_positions',
                'mt5.positions',
            ]),
            'trade_history' => $this->tradeRowsFromPayload($payload, [
                'trade_history',
                'tradeHistory',
                'closed_trades',
                'closedTrades',
                'closed_positions',
                'closedPositions',
                'positions.closed',
                'history',
                'deal_history',
                'dealHistory',
                'deals',
                'orders',
                'raw.trade_history',
                'raw.tradeHistory',
                'raw.closed_trades',
                'raw.closedTrades',
                'raw.closed_positions',
                'raw.positions.closed',
                'raw.history',
                'raw.deal_history',
                'raw.deals',
                'raw.orders',
                'mt5.trade_history',
                'mt5.history',
                'mt5.deals',
            ], true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $paths
     * @return list<array<string, mixed>>
     */
    private function tradeRowsFromPayload(array $payload, array $paths, bool $allowAggregateFallback = false): array
    {
        $rows = collect($paths)
            ->flatMap(fn (string $path): array => $this->coerceTradeRows(Arr::get($payload, $path)))
            ->filter(fn ($row): bool => is_array($row))
            ->values();

        if ($rows->isEmpty() && $allowAggregateFallback) {
            $singleSymbol = $this->firstFilledValue($payload, [
                'symbol',
                'Symbol',
                'trade_symbol',
                'tradeSymbol',
                'last_symbol',
                'lastSymbol',
                'instrument',
                'Instrument',
                'raw.symbol',
                'raw.Symbol',
                'raw.trade_symbol',
                'raw.last_symbol',
            ]);

            $singleCount = $this->tradeCountValue($payload);

            if ($singleSymbol !== null && $singleCount > 0) {
                $rows = collect([[
                    'symbol' => $singleSymbol,
                    'trade_count' => $singleCount,
                    'volume' => $this->tradeVolumeValue($payload),
                    'net_profit' => $this->tradeProfitValue($payload),
                ]]);
            }
        }

        $seenIdentities = [];

        return $rows
            ->filter(function (array $row) use (&$seenIdentities): bool {
                $identity = $this->tradeIdentityKey($row);

                if ($identity === null) {
                    return true;
                }

                if (isset($seenIdentities[$identity])) {
                    return false;
                }

                $seenIdentities[$identity] = true;

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function coerceTradeRows(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return collect($value)
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all();
        }

        foreach (['data', 'items', 'rows', 'records', 'positions', 'deals', 'orders', 'history'] as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                return $this->coerceTradeRows($value[$key]);
            }
        }

        $keyedRows = collect($value)
            ->filter(fn ($row): bool => is_array($row) && $this->tradeSymbolLabel($row) !== __('—'))
            ->values();

        if ($keyedRows->isNotEmpty()) {
            return $keyedRows->all();
        }

        return $this->tradeSymbolLabel($value) !== __('—') ? [$value] : [];
    }

    private function tradeSymbolLabel(array $row): string
    {
        $symbol = $this->firstFilledValue($row, [
            'symbol',
            'Symbol',
            'SYMBOL',
            'symbol_name',
            'symbolName',
            'SymbolName',
            'instrument',
            'Instrument',
            'market',
            'Market',
            'ticker',
            'Ticker',
            'raw.symbolName',
            'raw.SymbolName',
            'raw.symbol',
            'raw.Symbol',
            'raw.tradeData.symbolName',
            'raw.tradeData.symbol',
            'raw.tradeData.Symbol',
            'symbol_id',
            'symbolId',
        ]);

        if (blank($symbol)) {
            return __('—');
        }

        if (is_numeric($symbol)) {
            return __('Symbol #:value', ['value' => $symbol]);
        }

        return (string) $symbol;
    }

    private function tradeIdentityKey(array $row): ?string
    {
        $id = $this->firstFilledValue($row, [
            'deal_id',
            'dealId',
            'position_id',
            'positionId',
            'ticket',
            'Ticket',
            'order',
            'Order',
            'id',
            'raw.deal_id',
            'raw.dealId',
            'raw.position_id',
            'raw.positionId',
            'raw.ticket',
            'raw.Ticket',
        ]);

        if ($id !== null) {
            return 'id|'.(string) $id;
        }

        $timestamp = $this->firstFilledValue($row, [
            'open_timestamp',
            'open_time',
            'openTime',
            'time',
            'Time',
            'close_timestamp',
            'close_time',
            'closeTime',
            'execution_timestamp',
            'execution_time',
            'executionTime',
            'TimeClose',
        ]);

        if ($timestamp === null) {
            return null;
        }

        return implode('|', [
            'time',
            $this->tradeSymbolLabel($row),
            (string) $timestamp,
            (string) $this->tradeVolumeValue($row),
            (string) $this->tradeProfitValue($row),
        ]);
    }

    private function tradeCountValue(array $row): int
    {
        $count = $this->firstFilledValue($row, [
            'trade_count',
            'tradeCount',
            'activity_count',
            'activityCount',
            'count',
            'Count',
            'deals_count',
            'dealsCount',
            'positions_count',
            'positionsCount',
            'raw.trade_count',
            'raw.count',
        ]);

        return max((int) ($count ?? 1), 1);
    }

    private function tradeVolumeValue(array $row): float
    {
        return round((float) ($this->firstFilledValue($row, [
            'total_volume',
            'totalVolume',
            'volume',
            'Volume',
            'lots',
            'Lots',
            'lot',
            'Lot',
            'lot_size',
            'lotSize',
            'raw.total_volume',
            'raw.volume',
            'raw.Volume',
            'raw.tradeData.volume',
        ]) ?? 0), 2);
    }

    private function tradeProfitValue(array $row): float
    {
        return round((float) ($this->firstFilledValue($row, [
            'net_profit',
            'netProfit',
            'net_unrealized_pnl',
            'netUnrealizedPnl',
            'profit',
            'Profit',
            'pnl',
            'Pnl',
            'PNL',
            'floating_profit',
            'floatingProfit',
            'unrealized_profit',
            'unrealizedProfit',
            'realized_profit',
            'realizedProfit',
            'total_profit',
            'totalProfit',
            'today_profit',
            'todayProfit',
            'raw.net_profit',
            'raw.netProfit',
            'raw.profit',
            'raw.Profit',
            'raw.pnl',
            'raw.tradeData.profit',
        ]) ?? 0), 2);
    }

    private function tradeFeeValue(array $row): float
    {
        return round((float) ($this->firstFilledValue($row, [
            'commission',
            'Commission',
            'fee',
            'Fee',
            'fees',
            'Fees',
            'swap',
            'Swap',
            'raw.commission',
            'raw.Commission',
            'raw.fee',
            'raw.swap',
        ]) ?? 0), 2);
    }

    private function tradeSideValue(array $row): mixed
    {
        return $this->firstFilledValue($row, [
            'trade_side',
            'tradeSide',
            'side',
            'Side',
            'type',
            'Type',
            'position_type',
            'positionType',
            'order_type',
            'orderType',
            'raw.trade_side',
            'raw.side',
            'raw.type',
            'raw.Type',
            'raw.tradeData.side',
            'raw.tradeData.type',
        ]);
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstFilledValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($row, $key);

            if (! blank($value)) {
                return $value;
            }
        }

        return null;
    }

    private function tradeSideLabel(mixed $value): string
    {
        $normalized = strtolower((string) $value);

        return match (true) {
            $normalized === '1',
            str_contains($normalized, 'buy'),
            str_contains($normalized, 'long') => __('Buy'),
            $normalized === '2',
            str_contains($normalized, 'sell'),
            str_contains($normalized, 'short') => __('Sell'),
            $normalized !== '' => str($normalized)->replace('_', ' ')->title()->toString(),
            default => __('—'),
        };
    }

    private function formatTradeDate(mixed $value): string
    {
        $parsed = $this->parseTradeTimestamp($value);

        return $parsed?->locale(app()->getLocale())->translatedFormat('M d, Y H:i') ?? __('—');
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
            return Carbon::instance($value)->locale(app()->getLocale())->translatedFormat('M d, Y');
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->locale(app()->getLocale())->translatedFormat('M d, Y');
        }

        return __('Not available');
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
        $translationKey = 'site.home.challenge_selector.types.'.$challengeType.'.label';
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return (string) config('wolforix.challenge_catalog.'.$challengeType.'.label', $challengeType);
    }

    private function planLabel(string $challengeType, int $accountSize): string
    {
        return $this->challengeTypeLabel($challengeType).' / '.((int) ($accountSize / 1000)).'K';
    }

    private function phaseLabel(TradingAccount $account): string
    {
        return match (true) {
            $account->challenge_type === 'one_step' => __('site.home.challenge_selector.phase_titles.single_phase'),
            (int) $account->phase_index > 1 => __('site.home.challenge_selector.phase_titles.phase_2'),
            default => __('site.home.challenge_selector.phase_titles.phase_1'),
        };
    }

    private function humanizeStatus(string $status): string
    {
        $normalized = str($status)->replace('_', ' ')->lower()->toString();
        $translated = __($normalized);

        if ($translated !== $normalized) {
            return $translated;
        }

        return str($normalized)->title()->toString();
    }

    /**
     * @return array{label:string,hint:string,tone:string}
     */
    private function syncFreshness(mixed $value): array
    {
        if (! $value instanceof \DateTimeInterface) {
            return [
                'label' => __('Awaiting first sync'),
                'hint' => __('No MT5 snapshot has been received yet.'),
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
                'label' => __('Live now'),
                'hint' => __('Updated :value.', ['value' => $relative]),
                'tone' => 'emerald',
            ];
        }

        if ($seconds <= $recentSeconds) {
            return [
                'label' => __('Synced recently'),
                'hint' => __('Updated :value.', ['value' => $relative]),
                'tone' => 'amber',
            ];
        }

        return [
            'label' => __('Sync delayed'),
            'hint' => __('Updated :value.', ['value' => $relative]),
            'tone' => 'rose',
        ];
    }

    private function formatRelativeAge(\DateTimeInterface $value): string
    {
        $seconds = max(now()->diffInSeconds($value), 0);

        return match (true) {
            $seconds < 60 => __(':value s ago', ['value' => $seconds]),
            $seconds < 3600 => __(':value m ago', ['value' => floor($seconds / 60)]),
            $seconds < 86400 => __(':value h ago', ['value' => floor($seconds / 3600)]),
            default => __(':value d ago', ['value' => floor($seconds / 86400)]),
        };
    }

    private function formatCompactDuration(int|float $seconds): string
    {
        $seconds = max((int) round($seconds), 0);

        return match (true) {
            $seconds >= 86400 => __(':value days', ['value' => (int) floor($seconds / 86400)]),
            $seconds >= 3600 => __(':value hr', ['value' => (int) floor($seconds / 3600)]),
            $seconds >= 60 => __(':value min', ['value' => (int) floor($seconds / 60)]),
            default => __(':value sec', ['value' => $seconds]),
        };
    }

    /**
     * @return array{days:int,hours:int,minutes:int,seconds:int}
     */
    private function durationSegments(int|float $seconds): array
    {
        $remaining = max((int) round($seconds), 0);
        $days = (int) floor($remaining / 86400);
        $remaining -= $days * 86400;
        $hours = (int) floor($remaining / 3600);
        $remaining -= $hours * 3600;
        $minutes = (int) floor($remaining / 60);
        $remaining -= $minutes * 60;

        return [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $remaining,
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private function accountMetadataValue(TradingAccount $account, array $keys): ?string
    {
        foreach ([$account->meta ?? [], $account->rule_state ?? []] as $source) {
            if (! is_array($source)) {
                continue;
            }

            foreach ($keys as $key) {
                $value = Arr::get($source, $key);

                if (is_scalar($value) && filled((string) $value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    private function certificateUrl(TradingAccount $account): ?string
    {
        if (! filled($account->certificate_path)) {
            return null;
        }

        $path = (string) $account->certificate_path;
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return route('dashboard.certificates.download', $account);
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
            'ctrader_api' => __('Legacy platform API'),
            'platform_sync' => __('Platform Sync'),
            default => $this->humanizeStatus($source),
        };
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->locale(app()->getLocale())->translatedFormat('M d, Y H:i');
        }

        return __('Not synced yet');
    }

}
