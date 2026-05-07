<?php

namespace App\Support;

use App\Models\TradingAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TradingMetricsCalculator
{
    public function __construct(
        private readonly ChallengeAccountMetrics $challengeAccountMetrics,
    ) {
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function calculate(TradingAccount $account, array $snapshot): array
    {
        $challengeMetrics = $this->challengeAccountMetrics->resolve($account, $snapshot);
        $startingBalance = (float) $challengeMetrics['challenge_starting_balance'];
        $balance = (float) ($snapshot['balance'] ?? $account->balance ?? $startingBalance);
        $equity = (float) ($snapshot['equity'] ?? $account->equity ?? $balance);
        $profitLoss = array_key_exists('profit_loss', $snapshot)
            ? (float) $snapshot['profit_loss']
            : (array_key_exists('open_profit', $snapshot)
                ? (float) $snapshot['open_profit']
                : round($equity - $balance, 2));
        $totalProfit = (float) $challengeMetrics['realized_profit'];
        $todayProfit = (float) (
            $snapshot['today_profit']
            ?? $this->todayProfitFromTradeHistory($snapshot)
            ?? $account->today_profit
            ?? 0
        );
        $maxDrawdown = array_key_exists('max_drawdown', $snapshot)
            ? (float) $snapshot['max_drawdown']
            : round(max($startingBalance - min((float) $challengeMetrics['challenge_balance'], (float) $challengeMetrics['challenge_equity']), 0), 2);
        $dailyDrawdown = array_key_exists('daily_drawdown', $snapshot)
            ? (float) $snapshot['daily_drawdown']
            : round(max((float) $challengeMetrics['challenge_balance'] - (float) $challengeMetrics['challenge_equity'], 0), 2);
        $drawdownPercent = array_key_exists('drawdown_percent', $snapshot)
            ? (float) $snapshot['drawdown_percent']
            : ($startingBalance > 0 ? round(($maxDrawdown / $startingBalance) * 100, 2) : 0.0);
        $profitTargetAmount = (float) ($account->profit_target_amount ?: round($startingBalance * ((float) $account->profit_target_percent / 100), 2));
        $progressPercent = $profitTargetAmount > 0
            ? round(max(min(($totalProfit / $profitTargetAmount) * 100, 100), 0), 2)
            : 0.0;

        $balanceChanged = (float) $account->balance !== $balance;

        Log::info('Trading account MT5 metrics calculated.', [
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'is_trial' => (bool) $account->is_trial,
            'raw_balance' => $challengeMetrics['raw_balance'],
            'raw_equity' => $challengeMetrics['raw_equity'],
            'challenge_balance' => $challengeMetrics['challenge_balance'],
            'challenge_equity' => $challengeMetrics['challenge_equity'],
            'broker_phase_reference_balance' => $challengeMetrics['broker_phase_reference_balance'],
            'broker_reference_source' => $challengeMetrics['broker_reference_source'],
            'floating_profit_loss' => $profitLoss,
            'realized_profit' => $totalProfit,
            'today_profit' => $todayProfit,
            'server_day' => $snapshot['server_day'] ?? null,
            'trading_days_completed' => $snapshot['trading_days_completed'] ?? $account->trading_days_completed ?? 0,
        ]);

        return [
            'starting_balance' => $startingBalance,
            'balance' => $balance,
            'equity' => $equity,
            'profit_loss' => $profitLoss,
            'total_profit' => $totalProfit,
            'today_profit' => $todayProfit,
            'daily_drawdown' => $dailyDrawdown,
            'max_drawdown' => $maxDrawdown,
            'drawdown_percent' => $drawdownPercent,
            'profit_target_amount' => $profitTargetAmount,
            'profit_target_progress_percent' => $progressPercent,
            'trading_days_completed' => (int) ($snapshot['trading_days_completed'] ?? $account->trading_days_completed ?? 0),
            'last_balance_change_at' => $balanceChanged ? now() : $account->last_balance_change_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function todayProfitFromTradeHistory(array $snapshot): ?float
    {
        $serverDay = $this->serverDay($snapshot);
        $rows = $snapshot['trade_history']
            ?? data_get($snapshot, 'raw.trade_history')
            ?? data_get($snapshot, 'raw.closed_trades')
            ?? data_get($snapshot, 'raw.closed_positions')
            ?? null;

        if (! $serverDay instanceof Carbon || ! is_array($rows)) {
            return null;
        }

        $total = 0.0;
        $matchedRows = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $closedAt = $this->tradeCloseTime($row);

            if (! $closedAt instanceof Carbon || ! $closedAt->isSameDay($serverDay)) {
                continue;
            }

            $profit = $this->numericValue($row['net_profit'] ?? $row['net_result'] ?? null);

            if ($profit === null) {
                $profit = ($this->numericValue($row['profit'] ?? null) ?? 0.0)
                    + ($this->numericValue($row['commission'] ?? null) ?? 0.0)
                    + ($this->numericValue($row['swap'] ?? null) ?? 0.0);
            }

            $total += $profit;
            $matchedRows++;
        }

        return $matchedRows > 0 ? round($total, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function serverDay(array $snapshot): ?Carbon
    {
        $value = $snapshot['server_day'] ?? $snapshot['timestamp'] ?? data_get($snapshot, 'raw.server_day') ?? data_get($snapshot, 'raw.server_time');

        return $this->carbonValue($value);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function tradeCloseTime(array $row): ?Carbon
    {
        return $this->carbonValue(
            $row['execution_timestamp']
                ?? $row['execution_time']
                ?? $row['close_timestamp']
                ?? $row['close_time']
                ?? $row['closed_at']
                ?? null
        );
    }

    private function carbonValue(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            return $timestamp > 0 ? Carbon::createFromTimestamp($timestamp) : null;
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse(trim($value));
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function numericValue(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
