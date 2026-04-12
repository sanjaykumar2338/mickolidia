<?php

namespace App\Support;

use App\Models\TradingAccount;

class ChallengeAccountMetrics
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, float|string|null>
     */
    public function resolve(TradingAccount $account, array $snapshot = []): array
    {
        $rawBalance = $this->number($snapshot['balance'] ?? null)
            ?? $this->number($account->balance)
            ?? 0.0;
        $rawEquity = $this->number($snapshot['equity'] ?? null)
            ?? $this->number($account->equity)
            ?? $rawBalance;
        $floatingPnl = $this->number($snapshot['profit_loss'] ?? null)
            ?? $this->number($snapshot['open_profit'] ?? null)
            ?? $this->number($account->profit_loss)
            ?? round($rawEquity - $rawBalance, 2);
        $phaseStartingBalance = $this->phaseStartingBalance($account, $snapshot);
        [$brokerReference, $brokerReferenceSource] = $this->brokerPhaseReferenceBalance($account, $snapshot, $rawBalance, $phaseStartingBalance);

        $realizedProfit = round($rawBalance - $brokerReference, 2);
        $challengeBalance = round($phaseStartingBalance + $realizedProfit, 2);
        $challengeEquity = round($phaseStartingBalance + ($rawEquity - $brokerReference), 2);
        $challengeProfitContext = round($challengeEquity - $phaseStartingBalance, 2);

        return [
            'raw_balance' => round($rawBalance, 2),
            'raw_equity' => round($rawEquity, 2),
            'floating_profit_loss' => round($floatingPnl, 2),
            'broker_phase_reference_balance' => round($brokerReference, 2),
            'broker_reference_source' => $brokerReferenceSource,
            'challenge_starting_balance' => round($phaseStartingBalance, 2),
            'challenge_balance' => $challengeBalance,
            'challenge_equity' => $challengeEquity,
            'realized_profit' => $realizedProfit,
            'challenge_profit_context' => $challengeProfitContext,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function phaseStartingBalance(TradingAccount $account, array $snapshot): float
    {
        return $this->positiveNumber($account->phase_starting_balance)
            ?? $this->positiveNumber($account->starting_balance)
            ?? $this->positiveNumber($account->account_size)
            ?? $this->positiveNumber($snapshot['starting_balance'] ?? null)
            ?? 0.0;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{0: float, 1: string}
     */
    private function brokerPhaseReferenceBalance(TradingAccount $account, array $snapshot, float $rawBalance, float $phaseStartingBalance): array
    {
        $ruleStateReference = $this->positiveNumber(data_get($account->rule_state, 'broker_phase_reference_balance'));

        if ($ruleStateReference !== null) {
            return [$ruleStateReference, 'rule_state'];
        }

        $metaReference = $this->positiveNumber(data_get($account->meta, 'broker_phase_reference_balance'));

        if ($metaReference !== null) {
            return [$metaReference, 'meta'];
        }

        $snapshotReference = $this->positiveNumber($snapshot['broker_phase_reference_balance'] ?? null)
            ?? $this->positiveNumber($snapshot['broker_starting_balance'] ?? null);

        if ($snapshotReference !== null) {
            return [$snapshotReference, 'snapshot_reference'];
        }

        if (array_key_exists('total_profit', $snapshot) && $this->number($snapshot['total_profit']) !== null) {
            return [round($rawBalance - (float) $snapshot['total_profit'], 2), 'snapshot_total_profit'];
        }

        if ($this->number($account->total_profit) !== null && (float) $account->total_profit !== 0.0) {
            return [round($rawBalance - (float) $account->total_profit, 2), 'account_total_profit'];
        }

        $rawLooksPlanRelative = $phaseStartingBalance > 0
            && abs($rawBalance - $phaseStartingBalance) <= max($phaseStartingBalance * 0.2, 1000);

        if ($rawLooksPlanRelative) {
            return [$phaseStartingBalance, 'challenge_plan_reference'];
        }

        return [$rawBalance, 'current_raw_balance'];
    }

    private function positiveNumber(mixed $value): ?float
    {
        $number = $this->number($value);

        return $number !== null && $number > 0 ? $number : null;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        return null;
    }
}
