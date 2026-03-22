<?php

namespace App\Http\Controllers;

use App\Jobs\SendTrialEncouragementEmail;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrialController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user() && $request->session()->has('trial_user_id')) {
            Auth::loginUsingId((int) $request->session()->get('trial_user_id'));
        }

        $activeTrial = $this->activeTrialAccount($request->user());

        if ($activeTrial instanceof TradingAccount) {
            return redirect()->route('trial.dashboard');
        }

        return view('trial.register', [
            'startingBalance' => (float) config('wolforix.trial.starting_balance', 10000),
            'allowedSymbols' => config('wolforix.trial.allowed_symbols', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = DB::transaction(function () use ($validated, $request): User {
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
        $request->session()->put('trial_user_id', $user->id);

        return redirect()
            ->route('trial.dashboard')
            ->with('trial_success', __('site.trial.register.success'));
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
        $trialEnded = $this->ensureTrialState($trialAccount);
        $milestoneMessage = $trialEnded ? null : $this->resolveMilestoneMessage($trialAccount);

        if (! $trialEnded) {
            $this->triggerEncouragementIfDue($trialAccount, $user);
        }

        $trialAccount->refresh();

        return view('trial.dashboard', [
            'trialAccount' => $trialAccount,
            'trialEnded' => $this->isTrialEnded($trialAccount),
            'milestoneMessage' => $milestoneMessage,
            'allowedSymbols' => $trialAccount->allowed_symbols ?? config('wolforix.trial.allowed_symbols', []),
            'displayRules' => config('wolforix.trial.display_rules', []),
            'startingBalance' => (float) $trialAccount->starting_balance,
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
            ->route('trial.dashboard')
            ->with('trial_success', __('site.trial.retry.success'));
    }

    private function activeTrialAccount(?User $user): ?TradingAccount
    {
        if (! $user instanceof User) {
            return null;
        }

        $trialAccount = $user->latestTrialAccount()->first();

        if (! $trialAccount instanceof TradingAccount) {
            return null;
        }

        return $this->isTrialEnded($trialAccount) ? null : $trialAccount;
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

        return TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => null,
            'account_reference' => 'WFX-TRIAL-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(5)),
            'platform' => 'cTrader Demo',
            'stage' => config('wolforix.trial.account_type', 'Trial (Demo)'),
            'status' => 'Active',
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
            ],
        ]);
    }

    private function markLastActivity(TradingAccount $trialAccount): void
    {
        $trialAccount->forceFill([
            'last_activity_at' => now(),
        ])->save();
    }

    private function ensureTrialState(TradingAccount $trialAccount): bool
    {
        if ($this->isTrialEnded($trialAccount)) {
            if ($trialAccount->trial_status !== 'ended' || $trialAccount->ended_at === null || $trialAccount->status !== 'Ended') {
                $trialAccount->forceFill([
                    'trial_status' => 'ended',
                    'status' => 'Ended',
                    'ended_at' => $trialAccount->ended_at ?? now(),
                ])->save();
            }

            return true;
        }

        return false;
    }

    private function isTrialEnded(TradingAccount $trialAccount): bool
    {
        $maxLossLimit = (float) config('wolforix.trial.display_rules.max_drawdown_limit', 10);
        $maxLossAmount = ((float) $trialAccount->starting_balance * $maxLossLimit) / 100;

        return $trialAccount->trial_status === 'ended'
            || $trialAccount->ended_at !== null
            || (float) $trialAccount->balance <= 0
            || (float) $trialAccount->equity <= 0
            || (float) $trialAccount->max_drawdown >= $maxLossAmount;
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
}
