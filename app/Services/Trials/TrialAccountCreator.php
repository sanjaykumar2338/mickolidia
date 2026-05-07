<?php

namespace App\Services\Trials;

use App\Mail\TrialAccountInstructionsMail;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TrialAccountCreator
{
    public function create(User $user): TradingAccount
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

        try {
            Mail::to($user->email)->send(new TrialAccountInstructionsMail($user));

            Log::info('trial.instructions_email_sent', [
                'user_id' => $user->id,
                'trading_account_id' => $trialAccount->id,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            Log::warning('trial.instructions_email_failed', [
                'user_id' => $user->id,
                'trading_account_id' => $trialAccount->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return $trialAccount;
    }

    private function demoRegistrationUrl(): string
    {
        return (string) config('wolforix.trial.demo_registration_url', 'https://www.icmarkets.eu/de/open-trading-account/demo');
    }
}
