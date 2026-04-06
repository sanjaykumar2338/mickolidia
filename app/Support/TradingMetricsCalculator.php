<?php

namespace App\Support;

use App\Models\TradingAccount;

class TradingMetricsCalculator
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function calculate(TradingAccount $account, array $snapshot): array
    {
        $startingBalance = (float) ($snapshot['starting_balance'] ?? $account->starting_balance ?: $account->account_size ?: 0);
        $balance = (float) ($snapshot['balance'] ?? $account->balance ?? $startingBalance);
        $equity = (float) ($snapshot['equity'] ?? $account->equity ?? $balance);
        $profitLoss = array_key_exists('profit_loss', $snapshot)
            ? (float) $snapshot['profit_loss']
            : (array_key_exists('open_profit', $snapshot)
                ? (float) $snapshot['open_profit']
                : round($equity - $balance, 2));
        $totalProfit = array_key_exists('total_profit', $snapshot)
            ? (float) $snapshot['total_profit']
            : $profitLoss;
        $todayProfit = (float) ($snapshot['today_profit'] ?? $account->today_profit ?? 0);
        $maxDrawdown = array_key_exists('max_drawdown', $snapshot)
            ? (float) $snapshot['max_drawdown']
            : round(max($startingBalance - min($balance, $equity), 0), 2);
        $dailyDrawdown = array_key_exists('daily_drawdown', $snapshot)
            ? (float) $snapshot['daily_drawdown']
            : round(max($balance - $equity, 0), 2);
        $drawdownPercent = array_key_exists('drawdown_percent', $snapshot)
            ? (float) $snapshot['drawdown_percent']
            : ($startingBalance > 0 ? round(($maxDrawdown / $startingBalance) * 100, 2) : 0.0);
        $profitTargetAmount = (float) ($account->profit_target_amount ?: round($startingBalance * ((float) $account->profit_target_percent / 100), 2));
        $progressPercent = $profitTargetAmount > 0
            ? round(max(min(($totalProfit / $profitTargetAmount) * 100, 100), 0), 2)
            : 0.0;

        $balanceChanged = (float) $account->balance !== $balance;

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
}
