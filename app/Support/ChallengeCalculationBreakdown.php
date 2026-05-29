<?php

namespace App\Support;

use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;

class ChallengeCalculationBreakdown
{
    public function __construct(
        private readonly ChallengeAccountMetrics $challengeAccountMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAccount(TradingAccount $account, ?TradingAccountBalanceSnapshot $snapshot = null): array
    {
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $snapshotInput = array_filter([
            'balance' => $snapshot?->balance,
            'equity' => $snapshot?->equity,
            'profit_loss' => $snapshot?->profit_loss,
            'total_profit' => $snapshot?->total_profit,
            'today_profit' => $snapshot?->today_profit,
            'starting_balance' => $payload['starting_balance'] ?? null,
            'broker_starting_balance' => $payload['broker_starting_balance'] ?? null,
            'broker_phase_reference_balance' => $payload['broker_phase_reference_balance'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $metrics = $this->challengeAccountMetrics->resolve($account, $snapshotInput);
        $challengeStartingBalance = (float) $metrics['challenge_starting_balance'];
        $challengeBalance = (float) $metrics['challenge_balance'];
        $challengeEquity = (float) $metrics['challenge_equity'];
        $realizedProfit = (float) $metrics['realized_profit'];
        $floatingPnl = round($challengeEquity - $challengeBalance, 2);
        $equityProfit = round($challengeEquity - $challengeStartingBalance, 2);
        $profitTargetPercent = (float) ($account->profit_target_percent ?: data_get($account->rule_state, 'rules.profit_target_percent', 0));
        $profitTargetAmount = (float) ($account->profit_target_amount ?: round($challengeStartingBalance * ($profitTargetPercent / 100), 2));
        $profitTargetProgress = $profitTargetAmount > 0
            ? round(max(min(($realizedProfit / $profitTargetAmount) * 100, 100), 0), 2)
            : 0.0;
        $highestEquityToday = max(
            $this->number(data_get($account->rule_state, 'highest_challenge_equity_today')) ?? 0,
            $this->number($account->highest_equity_today) ?? 0,
            $challengeStartingBalance,
            $challengeEquity,
        );
        $dailyLossUsed = round(max($highestEquityToday - $challengeEquity, 0), 2);
        $currentDrawdownUsage = round(max($challengeStartingBalance - min($challengeBalance, $challengeEquity), 0), 2);
        $maxDrawdownUsed = round(max((float) ($account->max_drawdown_used ?? 0), $currentDrawdownUsage), 2);
        $dailyLossLimit = $this->number(data_get($account->rule_state, 'rules.daily_drawdown_limit_amount'))
            ?? $this->number($account->daily_drawdown_limit_amount)
            ?? 0.0;
        $maxDrawdownLimit = $this->number(data_get($account->rule_state, 'rules.max_drawdown_limit_amount'))
            ?? $this->number($account->max_drawdown_limit_amount)
            ?? 0.0;
        $dailyBreach = $dailyLossLimit > 0 && $dailyLossUsed >= $dailyLossLimit;
        $maxBreach = $maxDrawdownLimit > 0 && $maxDrawdownUsed >= $maxDrawdownLimit;

        return [
            'raw_balance' => (float) $metrics['raw_balance'],
            'raw_equity' => (float) $metrics['raw_equity'],
            'broker_phase_reference_balance' => (float) $metrics['broker_phase_reference_balance'],
            'broker_reference_source' => (string) $metrics['broker_reference_source'],
            'challenge_starting_balance' => $challengeStartingBalance,
            'challenge_starting_balance_source' => $this->challengeStartingBalanceSource($account, $payload),
            'challenge_balance' => $challengeBalance,
            'challenge_equity' => $challengeEquity,
            'realized_profit' => $realizedProfit,
            'floating_pnl' => $floatingPnl,
            'equity_profit' => $equityProfit,
            'today_profit' => $this->number($snapshot?->today_profit) ?? $this->number($account->today_profit) ?? 0.0,
            'highest_challenge_equity_today' => $highestEquityToday,
            'profit_target_percent' => $profitTargetPercent,
            'profit_target_amount' => $profitTargetAmount,
            'profit_target_progress_percent' => $profitTargetProgress,
            'profit_target_met' => $profitTargetAmount > 0 && $realizedProfit >= $profitTargetAmount,
            'daily_loss_used' => $dailyLossUsed,
            'daily_loss_limit' => $dailyLossLimit,
            'daily_loss_remaining' => max(round($dailyLossLimit - $dailyLossUsed, 2), 0),
            'daily_breach' => $dailyBreach,
            'max_drawdown_used' => $maxDrawdownUsed,
            'max_drawdown_limit' => $maxDrawdownLimit,
            'max_drawdown_remaining' => max(round($maxDrawdownLimit - $maxDrawdownUsed, 2), 0),
            'max_breach' => $maxBreach,
            'breach' => $dailyBreach || $maxBreach,
            'breach_reason' => $dailyBreach ? 'daily_loss_breached' : ($maxBreach ? 'max_drawdown_breached' : null),
            'formula' => [
                'challenge_balance' => 'challenge_starting_balance + (raw_mt5_balance - broker_phase_reference_balance)',
                'challenge_equity' => 'challenge_starting_balance + (raw_mt5_equity - broker_phase_reference_balance)',
                'realized_profit' => 'raw_mt5_balance - broker_phase_reference_balance',
                'profit_target_progress' => 'realized_profit / profit_target_amount * 100',
                'daily_loss_used' => 'max(today_highest_challenge_equity - current_challenge_equity, 0)',
                'max_drawdown_used' => 'max(previous_max_drawdown_used, challenge_starting_balance - min(challenge_balance, challenge_equity), 0)',
            ],
            'snapshot_id' => $snapshot?->id,
            'snapshot_at' => $snapshot?->snapshot_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function challengeStartingBalanceSource(TradingAccount $account, array $payload): string
    {
        return match (true) {
            $this->number($account->phase_starting_balance) !== null && (float) $account->phase_starting_balance > 0 => 'trading_accounts.phase_starting_balance',
            $this->number($account->starting_balance) !== null && (float) $account->starting_balance > 0 => 'trading_accounts.starting_balance',
            $this->number($account->account_size) !== null && (float) $account->account_size > 0 => 'trading_accounts.account_size',
            $this->number($payload['starting_balance'] ?? null) !== null && (float) $payload['starting_balance'] > 0 => 'latest_payload.starting_balance',
            default => 'not_found',
        };
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
