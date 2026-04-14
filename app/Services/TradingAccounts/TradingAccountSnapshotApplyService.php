<?php

namespace App\Services\TradingAccounts;

use App\Models\TradingAccount;
use App\Models\TradingAccountDay;
use App\Services\Challenge\ChallengeFinalStateMailer;
use App\Services\Challenge\ChallengeLifecycleMailer;
use App\Services\Challenge\ChallengeProgressEngine;
use App\Support\TradingMetricsCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TradingAccountSnapshotApplyService
{
    public function __construct(
        private readonly TradingMetricsCalculator $metricsCalculator,
        private readonly ChallengeProgressEngine $progressEngine,
        private readonly ChallengeFinalStateMailer $finalStateMailer,
        private readonly ChallengeLifecycleMailer $lifecycleMailer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $options
     */
    public function apply(TradingAccount $account, array $snapshot, array $options = []): TradingAccount
    {
        $snapshotAt = $this->resolveDateTime($options['snapshot_at'] ?? ($snapshot['timestamp'] ?? null)) ?? now();
        $startedAt = $this->resolveDateTime($options['started_at'] ?? null) ?? $snapshotAt;
        $source = (string) ($options['source'] ?? 'platform_sync');

        /** @var TradingAccount $updatedAccount */
        $updatedAccount = DB::transaction(function () use ($account, $snapshot, $snapshotAt, $startedAt, $source): TradingAccount {
            /** @var TradingAccount $freshAccount */
            $freshAccount = TradingAccount::query()
                ->with(['challengePlan', 'challengePurchase'])
                ->lockForUpdate()
                ->findOrFail($account->id);

            $previousStatus = $freshAccount->account_status;
            $previousPhaseIndex = (int) $freshAccount->phase_index;

            $metrics = $this->metricsCalculator->calculate($freshAccount, $snapshot);
            $workingCopy = $freshAccount->replicate();
            $workingCopy->exists = true;
            $workingCopy->forceFill(array_merge(
                $this->snapshotAccountFill($snapshot),
                $metrics,
            ));

            if ($workingCopy->activated_at === null && ! $workingCopy->is_trial) {
                $workingCopy->activated_at = $snapshotAt;
            }

            $serverDay = $this->resolveServerDay($snapshot, $snapshotAt);
            $tradingDaysCompleted = $this->upsertTradingDay(
                account: $freshAccount,
                snapshot: $snapshot,
                occurredAt: $snapshotAt,
                source: $source,
            );

            if ($tradingDaysCompleted === 0) {
                $tradingDaysCompleted = max(
                    (int) $freshAccount->trading_days_completed,
                    (int) ($snapshot['trading_days_completed'] ?? 0),
                );
            }

            $workingCopy->trading_days_completed = $tradingDaysCompleted;
            $workingCopy->server_day = $serverDay;

            $evaluation = $this->progressEngine->evaluate($workingCopy, [
                'evaluated_at' => $snapshotAt,
                'server_day' => $serverDay,
                'trading_days_completed' => $tradingDaysCompleted,
                'snapshot' => $snapshot,
            ]);

            $freshAccount->forceFill(array_merge(
                $this->snapshotAccountFill($snapshot),
                $metrics,
                $evaluation,
                [
                    'activated_at' => $workingCopy->activated_at,
                    'sync_status' => 'success',
                    'sync_source' => $source,
                    'synced_at' => $snapshotAt,
                    'last_synced_at' => $snapshotAt,
                    'last_sync_started_at' => $startedAt,
                    'last_sync_completed_at' => $snapshotAt,
                    'sync_error' => null,
                    'sync_error_at' => null,
                ],
            ))->save();

            $freshAccount->balanceSnapshots()->create([
                'snapshot_at' => $snapshotAt,
                'balance' => $freshAccount->balance,
                'equity' => $freshAccount->equity,
                'profit_loss' => $freshAccount->profit_loss,
                'total_profit' => $freshAccount->total_profit,
                'today_profit' => $freshAccount->today_profit,
                'daily_drawdown' => $freshAccount->daily_drawdown,
                'max_drawdown' => $freshAccount->max_drawdown,
                'drawdown_percent' => $freshAccount->drawdown_percent,
                'payload' => $snapshot['raw'] ?? $snapshot,
            ]);

            if ($freshAccount->challengePurchase !== null) {
                $freshAccount->challengePurchase->forceFill([
                    'account_status' => $freshAccount->challenge_status ?: $freshAccount->account_status,
                    'started_at' => $freshAccount->activated_at ?? $freshAccount->challengePurchase->started_at,
                    'funded_status' => $freshAccount->is_funded ? 'funded' : $freshAccount->challengePurchase->funded_status,
                    'meta' => array_merge($freshAccount->challengePurchase->meta ?? [], [
                        'trading_account_id' => $freshAccount->id,
                        'last_synced_at' => optional($freshAccount->last_synced_at)->toIso8601String(),
                        'phase_index' => (int) $freshAccount->phase_index,
                        'failure_reason' => $freshAccount->failure_reason,
                        'sync_source' => $source,
                    ]),
                ])->save();
            }

            if (
                $previousStatus !== $freshAccount->account_status
                || $previousPhaseIndex !== (int) $freshAccount->phase_index
            ) {
                $freshAccount->statusHistories()->create([
                    'previous_status' => $previousStatus,
                    'new_status' => $freshAccount->account_status,
                    'previous_phase_index' => $previousPhaseIndex,
                    'new_phase_index' => (int) $freshAccount->phase_index,
                    'source' => $source,
                    'context' => $freshAccount->rule_state,
                    'changed_at' => $snapshotAt,
                ]);
            }

            return $freshAccount->fresh([
                'challengePlan',
                'challengePurchase',
                'user',
            ]) ?? $freshAccount;
        });

        $this->finalStateMailer->sendIfNeeded($updatedAccount);
        $this->lifecycleMailer->sendPhaseProgressIfNeeded($updatedAccount);
        $this->lifecycleMailer->sendPurchaseCredentialsIfNeeded($updatedAccount);

        return $updatedAccount;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function snapshotAccountFill(array $snapshot): array
    {
        $profitLoss = $snapshot['profit_loss'] ?? $snapshot['open_profit'] ?? null;

        return array_filter([
            'platform_account_id' => $snapshot['platform_account_id'] ?? null,
            'platform_login' => $snapshot['platform_login'] ?? null,
            'platform_environment' => $snapshot['platform_environment'] ?? null,
            'platform_status' => $snapshot['platform_status'] ?? null,
            'account_phase' => $snapshot['account_phase'] ?? null,
            'phase_index' => $snapshot['phase_index'] ?? null,
            'is_funded' => $snapshot['is_funded'] ?? null,
            'highest_equity_today' => $snapshot['highest_equity_today'] ?? null,
            'daily_drawdown' => $snapshot['daily_drawdown'] ?? null,
            'daily_loss_used' => $snapshot['daily_loss_used'] ?? null,
            'max_drawdown' => $snapshot['max_drawdown'] ?? null,
            'max_drawdown_used' => $snapshot['max_drawdown_used'] ?? null,
            'profit_loss' => $profitLoss,
            'total_profit' => $snapshot['total_profit'] ?? null,
            'today_profit' => $snapshot['today_profit'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolveServerDay(array $snapshot, Carbon $snapshotAt): string
    {
        $serverDay = $snapshot['server_day'] ?? null;

        if (is_string($serverDay) && $serverDay !== '') {
            return Carbon::parse($serverDay)->toDateString();
        }

        return $snapshotAt->toDateString();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolveDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function upsertTradingDay(
        TradingAccount $account,
        array $snapshot,
        Carbon $occurredAt,
        string $source,
    ): int {
        $activityCount = max((int) ($snapshot['trade_count'] ?? $snapshot['activity_count'] ?? 0), 0);
        $volume = round((float) ($snapshot['volume'] ?? 0), 2);
        $hasActivity = (bool) ($snapshot['has_activity'] ?? false) || $activityCount > 0 || $volume > 0;

        if (! $hasActivity) {
            return (int) $account->tradingDays()
                ->where('phase_index', (int) $account->phase_index)
                ->count();
        }

        $tradingDate = $this->resolveServerDay($snapshot, $occurredAt);

        /** @var TradingAccountDay|null $day */
        $day = TradingAccountDay::query()
            ->where('trading_account_id', $account->id)
            ->where('phase_index', (int) $account->phase_index)
            ->whereDate('trading_date', $tradingDate)
            ->first();

        $day ??= new TradingAccountDay([
            'trading_account_id' => $account->id,
            'phase_index' => (int) $account->phase_index,
            'trading_date' => $tradingDate,
        ]);

        $day->fill([
            'activity_count' => max((int) ($day->activity_count ?? 0), max($activityCount, 1)),
            'volume' => round(max((float) ($day->volume ?? 0), $volume), 2),
            'first_activity_at' => $day->first_activity_at ?? $occurredAt,
            'last_activity_at' => $occurredAt,
            'source' => $source,
        ]);
        $day->save();

        return (int) $account->tradingDays()
            ->where('phase_index', (int) $account->phase_index)
            ->count();
    }
}
