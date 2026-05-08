<?php

namespace App\Http\Controllers;

use App\Jobs\SendTrialEncouragementEmail;
use App\Mail\TrialBreachedMail;
use App\Mail\TrialPassedMail;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Trials\TrialAccountCreator;
use App\Support\Mt5ConnectorCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrialController extends Controller
{
    public function __construct(
        private readonly Mt5ConnectorCredentials $connectorCredentials,
        private readonly TrialAccountCreator $trialAccountCreator,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user() && $request->session()->has('trial_user_id')) {
            Auth::loginUsingId((int) $request->session()->get('trial_user_id'));
        }

        if ($redirect = $this->redirectAuthenticatedUserToTrial($request)) {
            return $redirect;
        }

        $request->session()->put('url.intended', route('trial.register'));

        return view('trial.register', [
            'displayRules' => config('wolforix.trial.display_rules', []),
            'demoRegistrationUrl' => $this->demoRegistrationUrl(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectAuthenticatedUserToTrial($request)) {
            return $redirect;
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $existingUser = User::query()->where('email', $validated['email'])->first();

        if ($existingUser instanceof User) {
            if (! Auth::attempt([
                'email' => $validated['email'],
                'password' => $validated['password'],
            ])) {
                return back()
                    ->withInput($request->except('password'))
                    ->withErrors([
                        'email' => __('site.trial.register.existing_account_error'),
                    ]);
            }

            $request->session()->regenerate();

            return $this->redirectAuthenticatedUserToTrial($request)
                ?? redirect()->route('trial.dashboard');
        }

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => Str::of($validated['email'])->before('@')->replace(['.', '-', '_'], ' ')->title()->toString(),
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => 'active',
            ]);

            UserProfile::query()->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'preferred_language' => app()->getLocale(),
            ]);

            $this->createTrialAccount($user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('trial_user_id', $user->id);

        return redirect()
            ->route('trial.setup')
            ->with('trial_success', __('site.trial.register.success'));
    }

    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $trialAccount = $this->latestTrialAccount($user);

        if (! $trialAccount instanceof TradingAccount) {
            return redirect()->route('trial.register');
        }

        if ($this->resolveTrialOutcome($trialAccount) !== 'active') {
            return redirect()->route('trial.dashboard');
        }

        return $this->renderSetup($trialAccount, 'trial');
    }

    public function mt5Setup(Request $request): View|RedirectResponse
    {
        $account = $this->latestMt5ConnectorAccount($request->user());

        if (! $account instanceof TradingAccount) {
            return redirect()->route('dashboard');
        }

        return $this->renderSetup($account, 'mt5');
    }

    public function confirmDemo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $trialAccount = $this->latestTrialAccount($user);

        if (! $trialAccount instanceof TradingAccount) {
            return redirect()->route('trial.register');
        }

        $meta = is_array($trialAccount->meta) ? $trialAccount->meta : [];

        $trialAccount->forceFill([
            'platform' => 'MT5 Demo',
            'platform_slug' => 'mt5',
            'platform_environment' => 'IC Markets Demo',
            'platform_status' => 'pending_connection',
            'meta' => array_merge($meta, [
                'demo_broker' => 'IC Markets',
                'demo_registration_url' => $this->demoRegistrationUrl(),
                'trial_connector_acknowledged_at' => now()->toIso8601String(),
                'trial_onboarding_step' => 'connector_pending',
            ]),
        ])->save();

        return redirect()
            ->route('trial.dashboard')
            ->with('trial_success', __('site.trial.setup.continue_success'));
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $trialAccount = $this->latestTrialAccount($user);

        if (! $trialAccount instanceof TradingAccount) {
            return redirect()->route('trial.register');
        }

        $this->markLastActivity($trialAccount);
        $trialAccount->refresh();
        $trialStatus = $this->ensureTrialState($trialAccount, $user);
        $trialPassed = $trialStatus === 'passed';
        $trialEnded = $trialStatus === 'ended';
        $milestoneMessage = ($trialPassed || $trialEnded) ? null : $this->resolveMilestoneMessage($trialAccount);

        if (! $trialPassed && ! $trialEnded) {
            $this->triggerEncouragementIfDue($trialAccount, $user);
        }

        $trialAccount->refresh();

        return view('trial.dashboard', [
            'trialAccount' => $trialAccount,
            'trialPassed' => $trialPassed,
            'trialEnded' => $trialEnded,
            'trialStatus' => $trialStatus,
            'milestoneMessage' => $milestoneMessage,
            'displayRules' => config('wolforix.trial.display_rules', []),
            'connector' => $this->connectorCredentials->forAccount($trialAccount),
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->trialAccounts()
                ->whereNull('ended_at')
                ->update([
                    'trial_status' => 'ended',
                    'status' => 'Ended',
                    'ended_at' => now(),
                ]);

            $this->createTrialAccount($user);
        });

        return redirect()
            ->route('trial.setup')
            ->with('trial_success', __('site.trial.retry.success'));
    }

    private function redirectAuthenticatedUserToTrial(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $request->session()->put('trial_user_id', $user->id);
        $trialAccount = $this->latestTrialAccount($user);

        if ($trialAccount instanceof TradingAccount) {
            if ($this->resolveTrialOutcome($trialAccount) !== 'active') {
                return redirect()->route('trial.dashboard');
            }

            return redirect()->route(
                $this->connectorCredentials->connectionStatus($trialAccount) === 'connected' ? 'trial.dashboard' : 'trial.setup'
            );
        }

        DB::transaction(function () use ($user): void {
            UserProfile::query()->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'preferred_language' => app()->getLocale(),
            ]);

            $this->createTrialAccount($user);
        });

        return redirect()
            ->route('trial.setup')
            ->with('trial_success', __('site.trial.register.success'));
    }

    private function latestTrialAccount(?User $user): ?TradingAccount
    {
        if (! $user instanceof User) {
            return null;
        }

        return $user->latestTrialAccount()->first();
    }

    private function latestMt5ConnectorAccount(?User $user): ?TradingAccount
    {
        if (! $user instanceof User) {
            return null;
        }

        $challengeAccount = $user->tradingAccounts()
            ->where('platform_slug', 'mt5')
            ->where('is_trial', false)
            ->latest('id')
            ->first();

        if ($challengeAccount instanceof TradingAccount) {
            return $challengeAccount;
        }

        return $user->tradingAccounts()
            ->where('platform_slug', 'mt5')
            ->latest('id')
            ->first();
    }

    private function renderSetup(TradingAccount $account, string $mode): View
    {
        return view('trial.setup', [
            'trialAccount' => $account,
            'demoRegistrationUrl' => $this->demoRegistrationUrl(),
            'connector' => $this->connectorCredentials->forAccount($account),
            'setupCopy' => $this->setupCopy($mode),
            'connectorCopy' => $this->connectorCopy($mode),
        ]);
    }

    private function setupCopy(string $mode): array
    {
        if ($mode === 'mt5') {
            return [
                'mode' => 'mt5',
                'eyebrow' => __('MT5 Setup'),
                'title' => __('Connect Your MT5 Account to Wolforix'),
                'description' => __('Install the Wolforix MT5 Connector EA, attach it to an active MetaTrader 5 chart, and let Wolforix synchronize dashboard metrics, P/L, trading days, and account statistics.'),
                'process_label' => __('MT5 connection process'),
                'steps' => [
                    [
                        'title' => __('Step 1: Open Your Wolforix Dashboard'),
                        'body' => __('Confirm the MT5 account reference you want to connect.'),
                    ],
                    [
                        'title' => __('Step 2: Watch the MT5 Setup Video'),
                        'body' => __('Review the connector walkthrough before copying files into MetaTrader 5.'),
                    ],
                    [
                        'title' => __('Step 3: Download the Preconfigured Connector'),
                        'body' => __('Download the package from this page so the connector is prepared for your Wolforix account.'),
                    ],
                    [
                        'title' => __('Step 4: Install the EA in MetaTrader 5'),
                        'body' => __('Copy WolforixRuleEngineEA.mq5 into MQL5/Experts and copy the Include files into MQL5/Include.'),
                    ],
                    [
                        'title' => __('Step 5: Allow Wolforix WebRequest URLs'),
                        'body' => __('Enable Algo Trading and allow WebRequest for https://www.wolforix.com and https://wolforix.com.'),
                    ],
                    [
                        'title' => __('Step 6: Attach the EA and Sync'),
                        'body' => __('Attach the connector to an active chart and wait for the dashboard status to show Connected.'),
                    ],
                ],
                'show_demo_section' => false,
                'pre_connector_label' => __('MT5 Account'),
                'pre_connector_title' => __('Use this page for an active MT5 account'),
                'pre_connector_copy' => __('Your Wolforix account can only update once the connector is installed, attached to a chart, and successfully synced from MetaTrader 5.'),
                'important_items' => [
                    __('Keep MetaTrader 5 open while the EA is syncing.'),
                    __('Use the preconfigured package when possible.'),
                    __('Contact support if the status does not change to Connected after setup.'),
                ],
                'pre_connector_button_url' => route('dashboard'),
                'pre_connector_button_label' => __('Open Wolforix Dashboard'),
                'status_label' => __('Connection Status'),
                'continue_button' => __('Open Dashboard'),
                'continue_url' => route('dashboard'),
                'help_title' => __('Need help?'),
                'help_copy' => __('If you have any issues connecting your MT5 account, contact our support team at'),
            ];
        }

        return [
            'mode' => 'trial',
            'eyebrow' => __('site.trial.eyebrow'),
            'title' => __('site.trial.setup.title'),
            'description' => __('site.trial.setup.description'),
            'process_label' => __('site.trial.setup.process_label'),
            'steps' => trans('site.trial.setup.steps'),
            'show_demo_section' => true,
            'pre_connector_label' => __('site.trial.setup.step_two_label'),
            'pre_connector_title' => __('site.trial.setup.open_demo_title'),
            'pre_connector_copy' => __('site.trial.setup.open_demo_copy'),
            'important_items' => trans('site.trial.setup.important_items'),
            'pre_connector_button_url' => $this->demoRegistrationUrl(),
            'pre_connector_button_label' => __('site.trial.setup.open_demo_button'),
            'status_label' => __('site.trial.setup.step_three_label'),
            'continue_button' => __('site.trial.setup.continue_button'),
            'continue_url' => null,
            'help_title' => __('site.trial.setup.help_title'),
            'help_copy' => __('site.trial.setup.help_copy'),
        ];
    }

    private function connectorCopy(string $mode): array
    {
        if ($mode === 'mt5') {
            return [
                'title' => __('Connect Your MT5 Account to Wolforix'),
                'description' => __('Connection happens inside MetaTrader 5 using the Wolforix connector Expert Advisor. Install the connector, allow the Wolforix WebRequest URLs, paste these details into the EA inputs, then attach it to an active chart.'),
                'steps' => [
                    __('Download and extract the Wolforix MT5 connector zip package.'),
                    __('In MetaTrader 5, click File > Open Data Folder. When File Explorer opens, open MQL5 > Experts and paste the WolforixRuleEngineEA.mq5 file or extracted connector folder there. Copy the Include files into MQL5 > Include.'),
                    __('Open MetaTrader 5 and enable Algo Trading.'),
                    __('Open Tools > Options > Expert Advisors, tick Allow WebRequest for listed URL, then add https://www.wolforix.com and https://wolforix.com.'),
                    __('Copy the Base URL, Account Reference, and Secret Token shown here.'),
                    __('Attach the WolforixRuleEngineEA Expert Advisor to an active chart.'),
                    __('Paste the three values into the EA settings popup inside MetaTrader 5.'),
                    __('Click OK. Your Wolforix dashboard will show Connected after the EA sends its first update.'),
                ],
                'notes' => [
                    __('Until the EA connector is installed, attached to a chart, and successfully synced, dashboard metrics such as real-time P/L, trading days, account statistics, and profit updates may not appear.'),
                    __('Required MT5 setting: Tools > Options > Expert Advisors > tick Allow WebRequest for listed URL, then add https://www.wolforix.com and https://wolforix.com.'),
                    __('Keep your Secret Token private. Support will never ask for your password.'),
                ],
            ];
        }

        return [
            'title' => __('site.trial.connector.title'),
            'description' => __('site.trial.connector.description'),
            'steps' => trans('site.trial.connector.steps'),
            'notes' => trans('site.trial.connector.notes'),
        ];
    }

    private function createTrialAccount(User $user): TradingAccount
    {
        return $this->trialAccountCreator->create($user);
    }

    private function demoRegistrationUrl(): string
    {
        return (string) config('wolforix.trial.demo_registration_url', 'https://www.icmarkets.eu/de/open-trading-account/demo');
    }

    private function markLastActivity(TradingAccount $trialAccount): void
    {
        $trialAccount->forceFill([
            'last_activity_at' => now(),
        ])->save();
    }

    private function ensureTrialState(TradingAccount $trialAccount, User $user): string
    {
        $outcome = $this->resolveTrialOutcome($trialAccount);

        if ($outcome === 'passed') {
            $stateChanged = $trialAccount->trial_status !== 'passed'
                || $trialAccount->passed_at === null
                || $trialAccount->ended_at === null
                || $trialAccount->status !== 'Passed'
                || $trialAccount->account_status !== 'passed';

            if ($stateChanged) {
                $trialAccount->forceFill([
                    'trial_status' => 'passed',
                    'status' => 'Passed',
                    'account_status' => 'passed',
                    'passed_at' => $trialAccount->passed_at ?? now(),
                    'ended_at' => $trialAccount->ended_at ?? now(),
                ])->save();

                $trialAccount->refresh();
                $this->sendTrialPassedEmail($trialAccount, $user);
            }

            return 'passed';
        }

        if ($outcome === 'ended') {
            $stateChanged = $trialAccount->trial_status !== 'ended'
                || $trialAccount->failed_at === null
                || $trialAccount->ended_at === null
                || $trialAccount->status !== 'Ended'
                || $trialAccount->account_status !== 'failed';

            if ($stateChanged) {
                $trialAccount->forceFill([
                    'trial_status' => 'ended',
                    'status' => 'Ended',
                    'account_status' => 'failed',
                    'failed_at' => $trialAccount->failed_at ?? now(),
                    'ended_at' => $trialAccount->ended_at ?? now(),
                ])->save();

                $trialAccount->refresh();
                $this->sendTrialBreachedEmail($trialAccount, $user, $this->resolveTrialBreachReason($trialAccount));
            }

            return 'ended';
        }

        return 'active';
    }

    private function isTrialEnded(TradingAccount $trialAccount): bool
    {
        return $this->resolveTrialOutcome($trialAccount) === 'ended';
    }

    private function resolveMilestoneMessage(TradingAccount $trialAccount): ?string
    {
        $startingBalance = (float) $trialAccount->starting_balance;
        $profitPercent = $startingBalance > 0
            ? (((float) $trialAccount->profit_loss / $startingBalance) * 100)
            : 0;

        if ($profitPercent >= 5 && ! $trialAccount->milestone_popup_5_shown) {
            $trialAccount->forceFill([
                'milestone_popup_5_shown' => true,
            ])->save();

            return __('site.trial.milestones.five');
        }

        if ($profitPercent >= 3 && ! $trialAccount->milestone_popup_3_shown) {
            $trialAccount->forceFill([
                'milestone_popup_3_shown' => true,
            ])->save();

            return __('site.trial.milestones.three');
        }

        return null;
    }

    private function triggerEncouragementIfDue(TradingAccount $trialAccount, User $user): void
    {
        if ($trialAccount->encouragement_email_sent_at !== null || $trialAccount->trial_started_at === null) {
            return;
        }

        $daysActive = $trialAccount->trial_started_at->diffInDays(now());
        $threshold = (int) config('wolforix.trial.encouragement_after_days', 3);

        if ($daysActive < $threshold) {
            return;
        }

        $trialAccount->forceFill([
            'encouragement_email_sent_at' => now(),
        ])->save();

        SendTrialEncouragementEmail::dispatchSync($trialAccount->id, $user->email);
    }

    private function resolveTrialOutcome(TradingAccount $trialAccount): string
    {
        if ($trialAccount->trial_status === 'passed' || $trialAccount->passed_at !== null || $trialAccount->status === 'Passed') {
            return 'passed';
        }

        if ($trialAccount->trial_status === 'ended' || ($trialAccount->ended_at !== null && $trialAccount->status === 'Ended')) {
            return 'ended';
        }

        if ($this->trialRulesBreached($trialAccount)) {
            return 'ended';
        }

        if ($this->trialProfitTargetMet($trialAccount) && $this->trialMinimumDaysMet($trialAccount)) {
            return 'passed';
        }

        return 'active';
    }

    private function trialRulesBreached(TradingAccount $trialAccount): bool
    {
        return (float) $trialAccount->balance <= 0
            || (float) $trialAccount->equity <= 0
            || (float) $trialAccount->daily_drawdown >= $this->trialDailyLossAmount($trialAccount)
            || (float) $trialAccount->max_drawdown >= $this->trialMaxLossAmount($trialAccount);
    }

    private function trialProfitTargetMet(TradingAccount $trialAccount): bool
    {
        return $this->trialCurrentProfit($trialAccount) >= $this->trialProfitTargetAmount($trialAccount);
    }

    private function trialMinimumDaysMet(TradingAccount $trialAccount): bool
    {
        return (int) $trialAccount->trading_days_completed >= (int) config('wolforix.trial.display_rules.minimum_trading_days', 3);
    }

    private function trialCurrentProfit(TradingAccount $trialAccount): float
    {
        $totalProfit = (float) $trialAccount->total_profit;

        return $totalProfit !== 0.0 ? $totalProfit : (float) $trialAccount->profit_loss;
    }

    private function trialProfitTargetAmount(TradingAccount $trialAccount): float
    {
        return round(((float) $trialAccount->starting_balance * $this->trialProfitTargetPercent()) / 100, 2);
    }

    private function trialProfitTargetPercent(): float
    {
        return (float) config('wolforix.trial.display_rules.profit_target', 8);
    }

    private function trialDailyLossAmount(TradingAccount $trialAccount): float
    {
        return round(((float) $trialAccount->starting_balance * (float) config('wolforix.trial.display_rules.daily_drawdown_limit', 5)) / 100, 2);
    }

    private function trialMaxLossAmount(TradingAccount $trialAccount): float
    {
        return round(((float) $trialAccount->starting_balance * (float) config('wolforix.trial.display_rules.max_drawdown_limit', 10)) / 100, 2);
    }

    private function resolveTrialBreachReason(TradingAccount $trialAccount): string
    {
        if ((float) $trialAccount->daily_drawdown >= $this->trialDailyLossAmount($trialAccount)) {
            return 'Daily drawdown limit breached.';
        }

        if ((float) $trialAccount->max_drawdown >= $this->trialMaxLossAmount($trialAccount)) {
            return 'Maximum drawdown limit breached.';
        }

        if ((float) $trialAccount->equity <= 0 || (float) $trialAccount->balance <= 0) {
            return 'Account equity fell below the allowed threshold.';
        }

        return 'Trial rules were breached.';
    }

    private function sendTrialPassedEmail(TradingAccount $trialAccount, User $user): void
    {
        $meta = is_array($trialAccount->meta) ? $trialAccount->meta : [];

        if (! empty($meta['trial_completion_email_sent_at'])) {
            return;
        }

        try {
            Mail::to($user->email)->send(new TrialPassedMail($user, $trialAccount));

            $trialAccount->forceFill([
                'meta' => array_merge($meta, [
                    'trial_completion_email_sent_at' => now()->toIso8601String(),
                ]),
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function sendTrialBreachedEmail(TradingAccount $trialAccount, User $user, string $reason): void
    {
        $meta = is_array($trialAccount->meta) ? $trialAccount->meta : [];

        if (! empty($meta['trial_breach_email_sent_at'])) {
            return;
        }

        try {
            Mail::to($user->email)->send(new TrialBreachedMail($user, $trialAccount, $reason));

            $trialAccount->forceFill([
                'meta' => array_merge($meta, [
                    'trial_breach_email_sent_at' => now()->toIso8601String(),
                    'trial_breach_reason' => $reason,
                ]),
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
