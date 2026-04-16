<?php

namespace App\Services\TradingAccounts;

use App\Models\TradingAccount;
use Illuminate\Support\Carbon;

class TradingAccountConsistencyService
{
    public function __construct(
        private readonly TradeHistoryPanelBuilder $tradeHistoryPanelBuilder,
    ) {
    }

    public function evaluateAndPersist(
        TradingAccount $account,
        string|Carbon|null $serverDay = null,
        ?Carbon $evaluatedAt = null,
    ): void {
        $evaluatedAt = $evaluatedAt?->copy() ?? now();
        $serverDayDate = $this->resolveServerDay($account, $serverDay, $evaluatedAt);
        $monthStart = $serverDayDate->copy()->startOfMonth()->startOfDay();
        $monthEnd = $serverDayDate->copy()->endOfDay();
        $thresholds = $this->thresholdsForAccount($account);
        $state = $this->existingStateForMonth($account, $monthStart);

        $dailyProfits = [];
        $closedTradeCount = 0;

        foreach ($this->tradeHistoryPanelBuilder->closedTradeRowsFromSnapshots($account, $monthStart->copy()->subDay()) as $row) {
            $closedAt = $this->tradeHistoryPanelBuilder->tradeCloseTimestampForRow($row);

            if (! $closedAt instanceof Carbon || $closedAt->lt($monthStart) || $closedAt->gt($monthEnd)) {
                continue;
            }

            $realizedProfit = $this->tradeHistoryPanelBuilder->tradeRealizedResultForRow($row);

            if ($realizedProfit === null) {
                continue;
            }

            $tradingDate = $closedAt->toDateString();
            $dailyProfits[$tradingDate] = round(($dailyProfits[$tradingDate] ?? 0) + $realizedProfit, 2);
            $closedTradeCount++;
        }

        ksort($dailyProfits);

        $monthlyProfit = round(array_sum($dailyProfits), 2);
        $highestSingleDayProfit = $dailyProfits === [] ? 0.0 : round(max($dailyProfits), 2);
        $highestSingleDayDate = $dailyProfits === []
            ? null
            : collect($dailyProfits)
                ->sortDesc()
                ->keys()
                ->first();
        $ratioPercent = $monthlyProfit > 0 && $highestSingleDayProfit > 0
            ? round(($highestSingleDayProfit / $monthlyProfit) * 100, 2)
            : 0.0;

        $status = 'clear';
        $activeThreshold = null;

        if ($monthlyProfit > 0 && $highestSingleDayProfit > 0 && $thresholds['breach'] > 0) {
            if ($ratioPercent >= $thresholds['breach']) {
                $status = 'breach';
                $activeThreshold = $thresholds['breach'];
            } elseif ($thresholds['approach'] > 0 && $ratioPercent >= $thresholds['approach']) {
                $status = 'approaching';
                $activeThreshold = $thresholds['approach'];
            }
        }

        if (
            $activeThreshold !== null
            && ($state['last_triggered_threshold'] === null || $activeThreshold > $state['last_triggered_threshold'])
        ) {
            $state['last_triggered_threshold'] = $activeThreshold;
            $state['triggered_at'] = $evaluatedAt;
        }

        $consistencyState = [
            'status' => $status,
            'warning_visible' => in_array($status, ['approaching', 'breach'], true),
            'month' => $monthStart->format('Y-m'),
            'month_start' => $monthStart->toDateString(),
            'evaluated_at' => $evaluatedAt->toIso8601String(),
            'has_trade_history_data' => $closedTradeCount > 0,
            'closed_trade_count' => $closedTradeCount,
            'current_month_profit' => $monthlyProfit,
            'highest_single_day_profit' => $highestSingleDayProfit,
            'highest_single_day_date' => $highestSingleDayDate,
            'ratio_percent' => $ratioPercent,
            'approach_threshold_percent' => $thresholds['approach'],
            'breach_threshold_percent' => $thresholds['breach'],
            'active_threshold_percent' => $activeThreshold,
            'daily_realized_profit_map' => collect($dailyProfits)
                ->map(fn (float $profit, string $date): array => [
                    'trading_date' => $date,
                    'realized_profit' => $profit,
                ])
                ->values()
                ->all(),
            'last_triggered_threshold_percent' => $state['last_triggered_threshold'],
            'triggered_at' => $state['triggered_at']?->toIso8601String(),
            'approach_email_sent_at' => $state['approach_email_sent_at']?->toIso8601String(),
            'breach_email_sent_at' => $state['breach_email_sent_at']?->toIso8601String(),
        ];

        $account->forceFill([
            'consistency_status' => $status,
            'consistency_last_trigger_threshold' => $state['last_triggered_threshold'],
            'consistency_triggered_at' => $state['triggered_at'],
            'consistency_approach_email_sent_at' => $state['approach_email_sent_at'],
            'consistency_breach_email_sent_at' => $state['breach_email_sent_at'],
            'rule_state' => array_merge((array) ($account->rule_state ?? []), [
                'consistency' => $consistencyState,
            ]),
        ])->save();
    }

    /**
     * @return array{approach:float,breach:float}
     */
    private function thresholdsForAccount(TradingAccount $account): array
    {
        $breach = round((float) ($account->consistency_limit_percent ?: 40), 2);
        $approach = round(max($breach - 5, 0), 2);

        return [
            'approach' => $approach,
            'breach' => max($breach, 0),
        ];
    }

    /**
     * @return array{
     *     last_triggered_threshold:?float,
     *     triggered_at:?Carbon,
     *     approach_email_sent_at:?Carbon,
     *     breach_email_sent_at:?Carbon
     * }
     */
    private function existingStateForMonth(TradingAccount $account, Carbon $monthStart): array
    {
        $triggeredAt = $account->consistency_triggered_at;

        if (! $triggeredAt instanceof Carbon || $triggeredAt->format('Y-m') !== $monthStart->format('Y-m')) {
            return [
                'last_triggered_threshold' => null,
                'triggered_at' => null,
                'approach_email_sent_at' => null,
                'breach_email_sent_at' => null,
            ];
        }

        return [
            'last_triggered_threshold' => $account->consistency_last_trigger_threshold !== null
                ? round((float) $account->consistency_last_trigger_threshold, 2)
                : null,
            'triggered_at' => $triggeredAt,
            'approach_email_sent_at' => $account->consistency_approach_email_sent_at,
            'breach_email_sent_at' => $account->consistency_breach_email_sent_at,
        ];
    }

    private function resolveServerDay(
        TradingAccount $account,
        string|Carbon|null $serverDay,
        Carbon $evaluatedAt,
    ): Carbon {
        if ($serverDay instanceof Carbon) {
            return $serverDay->copy()->startOfDay();
        }

        if (is_string($serverDay) && $serverDay !== '') {
            return Carbon::parse($serverDay)->startOfDay();
        }

        if ($account->server_day instanceof Carbon) {
            return $account->server_day->copy()->startOfDay();
        }

        return $evaluatedAt->copy()->startOfDay();
    }
}
