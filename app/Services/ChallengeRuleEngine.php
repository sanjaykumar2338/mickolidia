<?php

namespace App\Services;

use App\Models\TradingAccount;

class ChallengeRuleEngine
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(TradingAccount $account): array
    {
        $rules = $this->rulesForAccount($account);
        $phaseSteps = max((int) ($account->challengePlan?->steps ?? ($account->challenge_type === 'two_step' ? 2 : 1)), 1);
        $profitTargetAmount = (float) ($account->starting_balance ?: $account->account_size ?: 0) * ($rules['profit_target_percent'] / 100);
        $profitTargetMet = $profitTargetAmount > 0
            ? (float) $account->total_profit >= round($profitTargetAmount, 2)
            : false;
        $minimumDaysMet = (int) $account->trading_days_completed >= $rules['minimum_trading_days'];
        $dailyBreach = $rules['daily_drawdown_limit_amount'] > 0
            && (float) $account->daily_drawdown >= $rules['daily_drawdown_limit_amount'];
        $maxBreach = $rules['max_drawdown_limit_amount'] > 0
            && (float) $account->max_drawdown >= $rules['max_drawdown_limit_amount'];
        $failed = $dailyBreach || $maxBreach;
        $passedThisPhase = ! $failed && ! $account->is_funded && $profitTargetMet && $minimumDaysMet;
        $pendingActivation = $account->activated_at === null && ! $account->is_trial;

        $machineStatus = 'active';
        $displayStatus = 'Active';

        if ($failed) {
            $machineStatus = 'failed';
            $displayStatus = 'Failed';
        } elseif ((bool) $account->is_funded) {
            $machineStatus = 'funded';
            $displayStatus = 'Funded';
        } elseif ($pendingActivation) {
            $machineStatus = 'pending_activation';
            $displayStatus = 'Pending Activation';
        } elseif ($passedThisPhase && (int) $account->phase_index < $phaseSteps) {
            $machineStatus = 'passed';
            $displayStatus = 'Passed - Awaiting Phase '.(((int) $account->phase_index) + 1);
        } elseif ($passedThisPhase) {
            $machineStatus = 'passed';
            $displayStatus = 'Passed';
        } elseif ($account->sync_status === 'error') {
            $machineStatus = 'sync_error';
            $displayStatus = 'Sync Error';
        }

        $activatedAt = $account->activated_at;
        $firstPayoutEligibleAt = null;
        $payoutEligibleAt = null;

        if ((bool) $account->is_funded && $activatedAt !== null) {
            $firstPayoutEligibleAt = $activatedAt->copy()->addDays($rules['first_payout_days']);
            $cycleStartedAt = $account->payout_cycle_started_at ?? $activatedAt;
            $payoutEligibleAt = $cycleStartedAt->copy()->addDays($rules['payout_cycle_days']);
        }

        return [
            'account_phase' => $rules['account_phase'],
            'stage' => $rules['stage'],
            'profit_target_percent' => $rules['profit_target_percent'],
            'profit_target_amount' => round($profitTargetAmount, 2),
            'daily_drawdown_limit_percent' => $rules['daily_drawdown_limit_percent'],
            'daily_drawdown_limit_amount' => round($rules['daily_drawdown_limit_amount'], 2),
            'max_drawdown_limit_percent' => $rules['max_drawdown_limit_percent'],
            'max_drawdown_limit_amount' => round($rules['max_drawdown_limit_amount'], 2),
            'minimum_trading_days' => $rules['minimum_trading_days'],
            'account_status' => $machineStatus,
            'status' => $displayStatus,
            'passed_at' => $passedThisPhase && $account->passed_at === null ? now() : $account->passed_at,
            'failed_at' => $failed && $account->failed_at === null ? now() : $account->failed_at,
            'first_payout_eligible_at' => $firstPayoutEligibleAt,
            'payout_cycle_started_at' => $account->payout_cycle_started_at ?? ($account->is_funded ? $activatedAt : null),
            'payout_eligible_at' => $payoutEligibleAt,
            'rule_state' => [
                'profit_target_met' => $profitTargetMet,
                'minimum_trading_days_met' => $minimumDaysMet,
                'daily_drawdown_breached' => $dailyBreach,
                'max_drawdown_breached' => $maxBreach,
                'phase_steps' => $phaseSteps,
                'next_phase_index' => $passedThisPhase && (int) $account->phase_index < $phaseSteps
                    ? ((int) $account->phase_index) + 1
                    : null,
                'rules' => [
                    'profit_target_percent' => $rules['profit_target_percent'],
                    'daily_drawdown_limit_percent' => $rules['daily_drawdown_limit_percent'],
                    'max_drawdown_limit_percent' => $rules['max_drawdown_limit_percent'],
                    'minimum_trading_days' => $rules['minimum_trading_days'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForAccount(TradingAccount $account): array
    {
        $challengeType = (string) ($account->challenge_type ?: 'two_step');
        $accountSize = (int) ($account->account_size ?: $account->starting_balance ?: 0);
        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}")
            ?? config("wolforix.challenge_catalog.{$challengeType}.plans")[array_key_first((array) config("wolforix.challenge_catalog.{$challengeType}.plans", []))] ?? null;

        $phases = (array) ($definition['phases'] ?? []);
        $phaseIndex = max((int) $account->phase_index, 1);
        $phase = $phases[$phaseIndex - 1] ?? ($phases[0] ?? []);
        $startingBalance = (float) ($account->starting_balance ?: $account->account_size ?: 0);

        return [
            'account_phase' => (string) ($phase['key'] ?? ($challengeType === 'one_step' ? 'single_phase' : ($phaseIndex > 1 ? 'phase_2' : 'phase_1'))),
            'stage' => (string) match (true) {
                $account->is_funded => 'Funded',
                $challengeType === 'one_step' => 'Single Phase',
                $phaseIndex > 1 => 'Challenge Step 2',
                default => 'Challenge Step 1',
            },
            'profit_target_percent' => (float) ($phase['profit_target'] ?? $account->profit_target_percent ?? 0),
            'daily_drawdown_limit_percent' => (float) ($phase['daily_loss_limit'] ?? $account->daily_drawdown_limit_percent ?? 0),
            'daily_drawdown_limit_amount' => round($startingBalance * ((float) ($phase['daily_loss_limit'] ?? $account->daily_drawdown_limit_percent ?? 0) / 100), 2),
            'max_drawdown_limit_percent' => (float) ($phase['max_loss_limit'] ?? $account->max_drawdown_limit_percent ?? 0),
            'max_drawdown_limit_amount' => round($startingBalance * ((float) ($phase['max_loss_limit'] ?? $account->max_drawdown_limit_percent ?? 0) / 100), 2),
            'minimum_trading_days' => (int) ($phase['minimum_trading_days'] ?? $account->minimum_trading_days ?? 0),
            'first_payout_days' => (int) ($definition['funded']['first_withdrawal_days'] ?? $account->challengePlan?->first_payout_days ?? config('wolforix.challenge_models.one_step.funded.first_withdrawal_days', 21)),
            'payout_cycle_days' => (int) ($definition['funded']['payout_cycle_days'] ?? $account->challengePlan?->payout_cycle_days ?? 14),
        ];
    }
}
