<?php

namespace App\Services\Challenge;

use App\Models\TradingAccount;
use App\Support\ChallengeAccountMetrics;
use Carbon\CarbonInterface;

class ChallengeProgressEngine
{
    public function __construct(
        private readonly ChallengeAccountMetrics $challengeAccountMetrics,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function evaluate(TradingAccount $account, array $context = []): array
    {
        $rules = $this->rulesForAccount($account);
        $evaluatedAt = $this->resolveDateTime($context['evaluated_at'] ?? null);
        $serverDay = (string) ($context['server_day'] ?? optional($evaluatedAt)->toDateString() ?? now()->toDateString());
        $tradingDaysCompleted = (int) ($context['trading_days_completed'] ?? $account->trading_days_completed ?? 0);
        $challengeMetrics = $this->challengeAccountMetrics->resolve($account, (array) ($context['snapshot'] ?? []));
        $phaseStartingBalance = (float) $challengeMetrics['challenge_starting_balance'];
        $phaseReferenceBalance = $phaseStartingBalance;
        $currentBalance = (float) $challengeMetrics['challenge_balance'];
        $currentEquity = (float) $challengeMetrics['challenge_equity'];
        $rawCurrentBalance = (float) $challengeMetrics['raw_balance'];
        $previousServerDay = optional($account->server_day)->toDateString();
        $storedHighestEquityToday = $this->storedHighestChallengeEquity($account, $currentEquity, $phaseStartingBalance);

        $highestEquityToday = $previousServerDay !== $serverDay
            ? $currentEquity
            : max($storedHighestEquityToday, $currentEquity);

        $dailyLossUsed = round(max($highestEquityToday - $currentEquity, 0), 2);
        $currentDrawdownUsage = round(max($phaseStartingBalance - min($currentBalance, $currentEquity), 0), 2);
        $maxDrawdownUsed = round(max((float) ($account->max_drawdown_used ?? 0), $currentDrawdownUsage), 2);
        $phaseProfit = round($currentBalance - $phaseStartingBalance, 2);
        $profitTargetAmount = round($phaseStartingBalance * ($rules['profit_target_percent'] / 100), 2);
        $profitTargetProgressPercent = $profitTargetAmount > 0
            ? round(max(min(($phaseProfit / $profitTargetAmount) * 100, 100), 0), 2)
            : 0.0;
        $dailyLossRemaining = max(round($rules['daily_drawdown_limit_amount'] - $dailyLossUsed, 2), 0);
        $maxDrawdownRemaining = max(round($rules['max_drawdown_limit_amount'] - $maxDrawdownUsed, 2), 0);
        $profitTargetRemaining = max(round($profitTargetAmount - $phaseProfit, 2), 0);
        $phaseSteps = max((int) ($rules['phase_steps'] ?? 1), 1);

        $storedStatus = (string) ($account->challenge_status ?: $account->account_status ?: 'active');
        $challengeStatus = match (true) {
            $account->is_funded => 'funded',
            $storedStatus === 'failed' => 'failed',
            $storedStatus === 'passed' && (int) $account->phase_index >= $phaseSteps => 'passed',
            $account->activated_at === null && ! $account->is_trial => 'pending_activation',
            default => 'active',
        };
        $failureReason = $challengeStatus === 'failed' ? $account->failure_reason : null;

        $dailyBreach = $challengeStatus === 'active'
            && $rules['daily_drawdown_limit_amount'] > 0
            && $dailyLossUsed >= $rules['daily_drawdown_limit_amount'];
        $maxBreach = $challengeStatus === 'active'
            && $rules['max_drawdown_limit_amount'] > 0
            && $maxDrawdownUsed >= $rules['max_drawdown_limit_amount'];

        if ($dailyBreach) {
            $challengeStatus = 'failed';
            $failureReason = 'daily_loss_breached';
        } elseif ($maxBreach) {
            $challengeStatus = 'failed';
            $failureReason = 'max_drawdown_breached';
        }

        $profitTargetMet = $challengeStatus === 'active' && $profitTargetAmount > 0 && $phaseProfit >= $profitTargetAmount;
        $minimumDaysMet = $challengeStatus === 'active' && $tradingDaysCompleted >= $rules['minimum_trading_days'];
        $phasePassed = $profitTargetMet && $minimumDaysMet;

        $ruleState = array_merge((array) ($account->rule_state ?? []), [
            'phase_steps' => $phaseSteps,
            'current_phase_key' => $rules['account_phase'],
            'raw_balance' => $challengeMetrics['raw_balance'],
            'raw_equity' => $challengeMetrics['raw_equity'],
            'broker_phase_reference_balance' => $challengeMetrics['broker_phase_reference_balance'],
            'broker_reference_source' => $challengeMetrics['broker_reference_source'],
            'challenge_starting_balance' => $phaseStartingBalance,
            'challenge_balance' => $currentBalance,
            'challenge_equity' => $currentEquity,
            'phase_profit' => $phaseProfit,
            'phase_profit_target_amount' => $profitTargetAmount,
            'phase_profit_target_remaining' => $profitTargetRemaining,
            'profit_target_met' => $profitTargetMet,
            'minimum_trading_days_met' => $minimumDaysMet,
            'daily_drawdown_breached' => $dailyBreach,
            'max_drawdown_breached' => $maxBreach,
            'daily_loss_used' => $dailyLossUsed,
            'daily_loss_remaining' => $dailyLossRemaining,
            'max_drawdown_used' => $maxDrawdownUsed,
            'max_drawdown_remaining' => $maxDrawdownRemaining,
            'highest_equity_today' => $highestEquityToday,
            'highest_challenge_equity_today' => $highestEquityToday,
            'trading_days_completed' => $tradingDaysCompleted,
            'server_day' => $serverDay,
            'evaluated_at' => optional($evaluatedAt)->toIso8601String(),
            'failure_reason' => $failureReason,
            'rules' => [
                'profit_target_percent' => $rules['profit_target_percent'],
                'daily_drawdown_limit_percent' => $rules['daily_drawdown_limit_percent'],
                'daily_drawdown_limit_amount' => $rules['daily_drawdown_limit_amount'],
                'max_drawdown_limit_percent' => $rules['max_drawdown_limit_percent'],
                'max_drawdown_limit_amount' => $rules['max_drawdown_limit_amount'],
                'minimum_trading_days' => $rules['minimum_trading_days'],
            ],
        ]);

        if ($challengeStatus === 'failed') {
            return array_merge($this->baseState(
                account: $account,
                rules: $rules,
                challengeStatus: 'failed',
                phaseStartingBalance: $phaseStartingBalance,
                phaseReferenceBalance: $phaseReferenceBalance,
                highestEquityToday: $highestEquityToday,
                dailyLossUsed: $dailyLossUsed,
                maxDrawdownUsed: $maxDrawdownUsed,
                tradingDaysCompleted: $tradingDaysCompleted,
                profitTargetAmount: $profitTargetAmount,
                profitTargetProgressPercent: $profitTargetProgressPercent,
                phaseProfit: $phaseProfit,
                serverDay: $serverDay,
                evaluatedAt: $evaluatedAt,
            ), [
                'status' => 'Failed',
                'failure_reason' => $failureReason,
                'failure_context' => [
                    'server_day' => $serverDay,
                    'highest_equity_today' => $highestEquityToday,
                    'daily_loss_used' => $dailyLossUsed,
                    'daily_loss_threshold' => $rules['daily_drawdown_limit_amount'],
                    'max_drawdown_used' => $maxDrawdownUsed,
                    'max_drawdown_threshold' => $rules['max_drawdown_limit_amount'],
                    'phase_profit' => $phaseProfit,
                    'rule_breached' => $failureReason,
                    'threshold' => $failureReason === 'daily_loss_breached'
                        ? $rules['daily_drawdown_limit_amount']
                        : $rules['max_drawdown_limit_amount'],
                    'recorded_value' => $failureReason === 'daily_loss_breached'
                        ? $dailyLossUsed
                        : $maxDrawdownUsed,
                ],
                'failed_at' => $account->failed_at ?? $evaluatedAt ?? now(),
                'trading_blocked' => true,
                'final_state_locked' => true,
                'rule_state' => $ruleState,
            ]);
        }

        if ($challengeStatus === 'funded') {
            return array_merge($this->baseState(
                account: $account,
                rules: $rules,
                challengeStatus: 'funded',
                phaseStartingBalance: $phaseStartingBalance,
                phaseReferenceBalance: $phaseReferenceBalance,
                highestEquityToday: $highestEquityToday,
                dailyLossUsed: $dailyLossUsed,
                maxDrawdownUsed: $maxDrawdownUsed,
                tradingDaysCompleted: $tradingDaysCompleted,
                profitTargetAmount: $profitTargetAmount,
                profitTargetProgressPercent: $profitTargetProgressPercent,
                phaseProfit: $phaseProfit,
                serverDay: $serverDay,
                evaluatedAt: $evaluatedAt,
            ), [
                'status' => 'Funded',
                'rule_state' => $ruleState,
            ]);
        }

        if ($challengeStatus === 'passed') {
            return array_merge($this->baseState(
                account: $account,
                rules: $rules,
                challengeStatus: 'passed',
                phaseStartingBalance: $phaseStartingBalance,
                phaseReferenceBalance: $phaseReferenceBalance,
                highestEquityToday: $highestEquityToday,
                dailyLossUsed: $dailyLossUsed,
                maxDrawdownUsed: $maxDrawdownUsed,
                tradingDaysCompleted: $tradingDaysCompleted,
                profitTargetAmount: $profitTargetAmount,
                profitTargetProgressPercent: 100,
                phaseProfit: $phaseProfit,
                serverDay: $serverDay,
                evaluatedAt: $evaluatedAt,
            ), [
                'status' => 'Passed',
                'passed_at' => $account->passed_at ?? $evaluatedAt ?? now(),
                'failure_reason' => null,
                'failure_context' => null,
                'trading_blocked' => true,
                'final_state_locked' => true,
                'rule_state' => $ruleState,
            ]);
        }

        if ($phasePassed && (int) $account->phase_index < $phaseSteps) {
            $nextPhaseIndex = (int) $account->phase_index + 1;
            $nextRules = $this->rulesForAccount($account, $nextPhaseIndex);
            $phaseHistory = (array) ($ruleState['phase_history'] ?? []);
            $phaseHistory[] = [
                'phase_index' => (int) $account->phase_index,
                'account_phase' => $rules['account_phase'],
                'completed_at' => optional($evaluatedAt)->toIso8601String(),
                'trading_days_completed' => $tradingDaysCompleted,
                'phase_profit' => $phaseProfit,
                'phase_profit_target_amount' => $profitTargetAmount,
            ];

            $nextPhaseStartingBalance = round((float) ($account->starting_balance ?: $account->account_size ?: $phaseStartingBalance), 2);
            $nextProfitTargetAmount = round($nextPhaseStartingBalance * ($nextRules['profit_target_percent'] / 100), 2);

            return [
                'account_phase' => $nextRules['account_phase'],
                'stage' => $nextRules['stage'],
                'phase_index' => $nextPhaseIndex,
                'challenge_status' => 'active',
                'account_status' => 'active',
                'status' => 'Active',
                'phase_starting_balance' => $nextPhaseStartingBalance,
                'phase_reference_balance' => $nextPhaseStartingBalance,
                'phase_started_at' => $evaluatedAt ?? now(),
                'highest_equity_today' => $nextPhaseStartingBalance,
                'daily_drawdown' => 0,
                'daily_loss_used' => 0,
                'max_drawdown' => 0,
                'max_drawdown_used' => 0,
                'total_profit' => 0,
                'profit_target_percent' => $nextRules['profit_target_percent'],
                'profit_target_amount' => $nextProfitTargetAmount,
                'profit_target_progress_percent' => 0,
                'daily_drawdown_limit_percent' => $nextRules['daily_drawdown_limit_percent'],
                'daily_drawdown_limit_amount' => $nextRules['daily_drawdown_limit_amount'],
                'max_drawdown_limit_percent' => $nextRules['max_drawdown_limit_percent'],
                'max_drawdown_limit_amount' => $nextRules['max_drawdown_limit_amount'],
                'minimum_trading_days' => $nextRules['minimum_trading_days'],
                'trading_days_completed' => 0,
                'failure_reason' => null,
                'failure_context' => null,
                'trading_blocked' => false,
                'final_state_locked' => false,
                'server_day' => $serverDay,
                'last_evaluated_at' => $evaluatedAt ?? now(),
                'rule_state' => array_merge($ruleState, [
                    'phase_history' => $phaseHistory,
                    'transitioned_to_phase_index' => $nextPhaseIndex,
                    'transitioned_at' => optional($evaluatedAt)->toIso8601String(),
                    'raw_balance' => $challengeMetrics['raw_balance'],
                    'raw_equity' => $challengeMetrics['raw_equity'],
                    'broker_phase_reference_balance' => $rawCurrentBalance,
                    'broker_reference_source' => 'phase_transition',
                    'challenge_starting_balance' => $nextPhaseStartingBalance,
                    'challenge_balance' => $nextPhaseStartingBalance,
                    'challenge_equity' => $nextPhaseStartingBalance,
                    'phase_profit' => 0,
                    'phase_profit_target_amount' => $nextProfitTargetAmount,
                    'phase_profit_target_remaining' => $nextProfitTargetAmount,
                    'profit_target_met' => false,
                    'minimum_trading_days_met' => false,
                    'daily_drawdown_breached' => false,
                    'max_drawdown_breached' => false,
                    'daily_loss_used' => 0,
                    'daily_loss_remaining' => $nextRules['daily_drawdown_limit_amount'],
                    'max_drawdown_used' => 0,
                    'max_drawdown_remaining' => $nextRules['max_drawdown_limit_amount'],
                    'highest_equity_today' => $nextPhaseStartingBalance,
                    'highest_challenge_equity_today' => $nextPhaseStartingBalance,
                    'current_phase_key' => $nextRules['account_phase'],
                    'failure_reason' => null,
                    'rules' => [
                        'profit_target_percent' => $nextRules['profit_target_percent'],
                        'daily_drawdown_limit_percent' => $nextRules['daily_drawdown_limit_percent'],
                        'daily_drawdown_limit_amount' => $nextRules['daily_drawdown_limit_amount'],
                        'max_drawdown_limit_percent' => $nextRules['max_drawdown_limit_percent'],
                        'max_drawdown_limit_amount' => $nextRules['max_drawdown_limit_amount'],
                        'minimum_trading_days' => $nextRules['minimum_trading_days'],
                    ],
                ]),
            ];
        }

        if ($phasePassed) {
            return array_merge($this->baseState(
                account: $account,
                rules: $rules,
                challengeStatus: 'passed',
                phaseStartingBalance: $phaseStartingBalance,
                phaseReferenceBalance: $phaseReferenceBalance,
                highestEquityToday: $highestEquityToday,
                dailyLossUsed: $dailyLossUsed,
                maxDrawdownUsed: $maxDrawdownUsed,
                tradingDaysCompleted: $tradingDaysCompleted,
                profitTargetAmount: $profitTargetAmount,
                profitTargetProgressPercent: 100,
                phaseProfit: $phaseProfit,
                serverDay: $serverDay,
                evaluatedAt: $evaluatedAt,
            ), [
                'status' => 'Passed',
                'passed_at' => $account->passed_at ?? $evaluatedAt ?? now(),
                'failure_reason' => null,
                'failure_context' => null,
                'trading_blocked' => true,
                'final_state_locked' => true,
                'rule_state' => array_merge($ruleState, [
                    'phase_history' => array_merge((array) ($ruleState['phase_history'] ?? []), [[
                        'phase_index' => (int) $account->phase_index,
                        'account_phase' => $rules['account_phase'],
                        'completed_at' => optional($evaluatedAt)->toIso8601String(),
                        'trading_days_completed' => $tradingDaysCompleted,
                        'phase_profit' => $phaseProfit,
                        'phase_profit_target_amount' => $profitTargetAmount,
                    ]]),
                ]),
            ]);
        }

        return array_merge($this->baseState(
            account: $account,
            rules: $rules,
            challengeStatus: $challengeStatus,
            phaseStartingBalance: $phaseStartingBalance,
            phaseReferenceBalance: $phaseReferenceBalance,
            highestEquityToday: $highestEquityToday,
            dailyLossUsed: $dailyLossUsed,
            maxDrawdownUsed: $maxDrawdownUsed,
            tradingDaysCompleted: $tradingDaysCompleted,
            profitTargetAmount: $profitTargetAmount,
            profitTargetProgressPercent: $profitTargetProgressPercent,
            phaseProfit: $phaseProfit,
            serverDay: $serverDay,
            evaluatedAt: $evaluatedAt,
        ), [
            'status' => $challengeStatus === 'pending_activation' ? 'Pending Activation' : 'Active',
            'failure_reason' => null,
            'failure_context' => null,
            'trading_blocked' => (bool) $account->trading_blocked,
            'final_state_locked' => (bool) $account->final_state_locked,
            'rule_state' => $ruleState,
        ]);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function baseState(
        TradingAccount $account,
        array $rules,
        string $challengeStatus,
        float $phaseStartingBalance,
        float $phaseReferenceBalance,
        float $highestEquityToday,
        float $dailyLossUsed,
        float $maxDrawdownUsed,
        int $tradingDaysCompleted,
        float $profitTargetAmount,
        float $profitTargetProgressPercent,
        float $phaseProfit,
        string $serverDay,
        ?CarbonInterface $evaluatedAt,
    ): array {
        return [
            'account_phase' => $rules['account_phase'],
            'stage' => $rules['stage'],
            'challenge_status' => $challengeStatus,
            'account_status' => $challengeStatus,
            'phase_starting_balance' => $phaseStartingBalance,
            'phase_reference_balance' => $phaseReferenceBalance,
            'phase_started_at' => $account->phase_started_at ?? $account->activated_at ?? $evaluatedAt ?? now(),
            'highest_equity_today' => $highestEquityToday,
            'daily_drawdown' => $dailyLossUsed,
            'daily_loss_used' => $dailyLossUsed,
            'max_drawdown' => $maxDrawdownUsed,
            'max_drawdown_used' => $maxDrawdownUsed,
            'total_profit' => $phaseProfit,
            'profit_target_percent' => $rules['profit_target_percent'],
            'profit_target_amount' => $profitTargetAmount,
            'profit_target_progress_percent' => $profitTargetProgressPercent,
            'daily_drawdown_limit_percent' => $rules['daily_drawdown_limit_percent'],
            'daily_drawdown_limit_amount' => $rules['daily_drawdown_limit_amount'],
            'max_drawdown_limit_percent' => $rules['max_drawdown_limit_percent'],
            'max_drawdown_limit_amount' => $rules['max_drawdown_limit_amount'],
            'minimum_trading_days' => $rules['minimum_trading_days'],
            'trading_days_completed' => $tradingDaysCompleted,
            'trading_blocked' => in_array($challengeStatus, ['failed', 'passed'], true) || (bool) $account->trading_blocked,
            'final_state_locked' => in_array($challengeStatus, ['failed', 'passed'], true) || (bool) $account->final_state_locked,
            'server_day' => $serverDay,
            'last_evaluated_at' => $evaluatedAt ?? now(),
        ];
    }

    private function phaseStartingBalance(TradingAccount $account): float
    {
        return round((float) ($account->phase_starting_balance ?: $account->starting_balance ?: $account->account_size ?: $account->balance), 2);
    }

    private function phaseReferenceBalance(TradingAccount $account): float
    {
        return round((float) ($account->phase_reference_balance ?: $account->phase_starting_balance ?: $account->starting_balance ?: $account->account_size ?: $account->balance), 2);
    }

    private function storedHighestChallengeEquity(TradingAccount $account, float $currentEquity, float $phaseStartingBalance): float
    {
        $stored = (float) (data_get($account->rule_state, 'highest_challenge_equity_today') ?: $account->highest_equity_today ?: $currentEquity);

        if ($stored <= 0) {
            return $currentEquity;
        }

        if ($phaseStartingBalance > 0 && $stored > ($phaseStartingBalance * 1.8) && $currentEquity < ($stored * 0.75)) {
            return $currentEquity;
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForAccount(TradingAccount $account, ?int $phaseIndex = null): array
    {
        $challengeType = (string) ($account->challenge_type ?: 'two_step');
        $accountSize = (int) ($account->account_size ?: $account->starting_balance ?: 0);
        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}")
            ?? config("wolforix.challenge_catalog.{$challengeType}.plans")[array_key_first((array) config("wolforix.challenge_catalog.{$challengeType}.plans", []))] ?? null;

        $phases = array_values((array) ($definition['phases'] ?? []));
        $currentPhaseIndex = max($phaseIndex ?? (int) $account->phase_index, 1);
        $phase = $phases[$currentPhaseIndex - 1] ?? ($phases[0] ?? []);
        $phaseStartingBalance = round((float) ($account->phase_starting_balance ?: $account->starting_balance ?: $account->account_size ?: 0), 2);

        return [
            'phase_steps' => max(count($phases), 1),
            'account_phase' => (string) ($phase['key'] ?? ($challengeType === 'one_step' ? 'single_phase' : ($currentPhaseIndex > 1 ? 'phase_2' : 'phase_1'))),
            'stage' => (string) match (true) {
                $account->is_funded => 'Funded',
                $challengeType === 'one_step' => 'Single Phase',
                $currentPhaseIndex > 1 => 'Challenge Step 2',
                default => 'Challenge Step 1',
            },
            'profit_target_percent' => (float) ($phase['profit_target'] ?? $account->profit_target_percent ?? 0),
            'daily_drawdown_limit_percent' => (float) ($phase['daily_loss_limit'] ?? $account->daily_drawdown_limit_percent ?? 0),
            'daily_drawdown_limit_amount' => round($phaseStartingBalance * ((float) ($phase['daily_loss_limit'] ?? $account->daily_drawdown_limit_percent ?? 0) / 100), 2),
            'max_drawdown_limit_percent' => (float) ($phase['max_loss_limit'] ?? $account->max_drawdown_limit_percent ?? 0),
            'max_drawdown_limit_amount' => round($phaseStartingBalance * ((float) ($phase['max_loss_limit'] ?? $account->max_drawdown_limit_percent ?? 0) / 100), 2),
            'minimum_trading_days' => (int) ($phase['minimum_trading_days'] ?? $account->minimum_trading_days ?? 0),
        ];
    }

    private function resolveDateTime(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return now()->setTimestamp($value->getTimestamp());
        }

        if (is_string($value) && $value !== '') {
            return now()->parse($value);
        }

        return null;
    }
}
