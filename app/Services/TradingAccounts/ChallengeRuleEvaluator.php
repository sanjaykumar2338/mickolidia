<?php

namespace App\Services\TradingAccounts;

use App\Models\TradingAccount;

class ChallengeRuleEvaluator
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(TradingAccount $account): array
    {
        $plan = $account->challengePlan;
        $phaseSteps = max((int) ($plan?->steps ?? 1), 1);
        $minimumTradingDays = max((int) $account->minimum_trading_days, 0);
        $profitTargetAmount = (float) $account->profit_target_amount;
        $dailyLossLimit = (float) $account->daily_drawdown_limit_amount;
        $maxLossLimit = (float) $account->max_drawdown_limit_amount;

        $profitTargetMet = $profitTargetAmount > 0
            ? (float) $account->total_profit >= $profitTargetAmount
            : false;
        $minimumDaysMet = $minimumTradingDays === 0
            ? true
            : (int) $account->trading_days_completed >= $minimumTradingDays;
        $dailyBreach = $dailyLossLimit > 0 && (float) $account->daily_drawdown >= $dailyLossLimit;
        $maxBreach = $maxLossLimit > 0 && (float) $account->max_drawdown >= $maxLossLimit;
        $failed = $dailyBreach || $maxBreach;
        $pendingActivation = $account->activated_at === null && ! $account->is_trial;
        $passedThisPhase = ! $failed && ! $account->is_funded && $profitTargetMet && $minimumDaysMet;

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
            $machineStatus = 'phase_passed';
            $displayStatus = 'Awaiting Phase '.(((int) $account->phase_index) + 1);
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
            $firstPayoutEligibleAt = $activatedAt->copy()->addDays((int) ($plan?->first_payout_days ?? 21));
            $cycleStartedAt = $account->payout_cycle_started_at ?? $activatedAt;
            $payoutEligibleAt = $cycleStartedAt->copy()->addDays((int) ($plan?->payout_cycle_days ?? 14));
        }

        return [
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
            ],
        ];
    }
}
