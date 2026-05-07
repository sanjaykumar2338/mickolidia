<?php

namespace App\Http\Controllers;

use App\Jobs\SendTrialEncouragementEmail;
use App\Mail\TrialAccountInstructionsMail;
use App\Mail\TrialBreachedMail;
use App\Mail\TrialPassedMail;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Mt5ConnectorCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrialController extends Controller
{
    public function __construct(
        private readonly Mt5ConnectorCredentials $connectorCredentials,
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

        return view('trial.setup', [
            'trialAccount' => $trialAccount,
            'demoRegistrationUrl' => $this->demoRegistrationUrl(),
            'connector' => $this->connectorCredentials->forAccount($trialAccount),
        ]);
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

    private function createTrialAccount(User $user): TradingAccount
    {
        $startingBalance = (float) config('wolforix.trial.starting_balance', 10000);
        $displayRules = config('wolforix.trial.display_rules', []);
        $profitTargetPercent = (float) ($displayRules['profit_target'] ?? 8);
        $dailyDrawdownLimitPercent = (float) ($displayRules['daily_drawdown_limit'] ?? 5);
        $maxDrawdownLimitPercent = (float) ($displayRules['max_drawdown_limit'] ?? 10);

        $trialAccount = TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => null,
            'account_reference' => 'WFX-TRIAL-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(5)),
            'platform' => 'MT5 Demo',
            'platform_slug' => 'mt5',
            'platform_environment' => 'IC Markets Demo',
            'platform_status' => 'pending_connection',
            'stage' => config('wolforix.trial.account_type', 'Trial (Demo)'),
            'status' => 'Active',
            'account_status' => 'active',
            'account_type' => 'trial',
            'is_trial' => true,
            'starting_balance' => $startingBalance,
            'balance' => $startingBalance,
            'equity' => $startingBalance,
            'daily_drawdown' => 0,
            'max_drawdown' => 0,
            'profit_loss' => 0,
            'total_profit' => 0,
            'today_profit' => 0,
            'drawdown_percent' => 0,
            'profit_target_percent' => $profitTargetPercent,
            'profit_target_amount' => round($startingBalance * ($profitTargetPercent / 100), 2),
            'profit_target_progress_percent' => 0,
            'daily_drawdown_limit_percent' => $dailyDrawdownLimitPercent,
            'daily_drawdown_limit_amount' => round($startingBalance * ($dailyDrawdownLimitPercent / 100), 2),
            'max_drawdown_limit_percent' => $maxDrawdownLimitPercent,
            'max_drawdown_limit_amount' => round($startingBalance * ($maxDrawdownLimitPercent / 100), 2),
            'consistency_limit_percent' => 40,
            'minimum_trading_days' => (int) ($displayRules['minimum_trading_days'] ?? 3),
            'trading_days_completed' => 0,
            'allowed_symbols' => config('wolforix.trial.allowed_symbols', []),
            'trial_status' => 'active',
            'trial_started_at' => now(),
            'last_activity_at' => now(),
            'synced_at' => now(),
            'meta' => [
                'source' => 'free-trial-registration',
                'execution_profile' => 'challenge-matched-demo',
                'demo_broker' => 'IC Markets',
                'demo_registration_url' => $this->demoRegistrationUrl(),
                'trial_onboarding_step' => 'connector_pending',
                'mt5_connector' => [
                    'secret_token' => Str::random(48),
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $this->sendTrialInstructionsImmediately($user, $trialAccount);

        return $trialAccount;
    }

    private function sendTrialInstructionsImmediately(User $user, TradingAccount $trialAccount): void
    {
        $startedAt = microtime(true);

        try {
            Mail::to($user->email)->send(new TrialAccountInstructionsMail($user));

            $meta = is_array($trialAccount->meta) ? $trialAccount->meta : [];
            $meta['trial_instructions_email_sent_at'] = now()->toIso8601String();

            $trialAccount->forceFill(['meta' => $meta])->save();

            Log::info('trial.instructions_email_sent_immediately', [
                'user_id' => $user->id,
                'trading_account_id' => $trialAccount->id,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            Log::warning('trial.instructions_email_immediate_send_failed', [
                'user_id' => $user->id,
                'trading_account_id' => $trialAccount->id,
                'message' => $exception->getMessage(),
            ]);
        }
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
