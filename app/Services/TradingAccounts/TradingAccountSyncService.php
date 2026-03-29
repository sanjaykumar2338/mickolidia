<?php

namespace App\Services\TradingAccounts;

use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Services\TradingPlatforms\TradingPlatformManager;
use App\Support\TradingMetricsCalculator;
use Illuminate\Support\Facades\DB;
use Throwable;

class TradingAccountSyncService
{
    public function __construct(
        private readonly TradingPlatformManager $platformManager,
        private readonly TradingMetricsCalculator $metricsCalculator,
        private readonly ChallengeRuleEvaluator $ruleEvaluator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(TradingAccount $account): array
    {
        $account->loadMissing(['challengePlan', 'challengePurchase']);

        $platform = $this->platformManager->forAccount($account);
        $startedAt = now();

        $log = TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => $platform->slug(),
            'status' => 'started',
            'message' => 'Trading account sync started.',
            'started_at' => $startedAt,
        ]);

        if (! $platform->isEnabled()) {
            return $this->markSkipped($account, $log, 'Trading sync is disabled by configuration.');
        }

        if (! $platform->isConfigured() && ! (bool) config('trading.platforms.ctrader.use_mock_data', false)) {
            return $this->markSkipped($account, $log, 'cTrader credentials are missing.');
        }

        try {
            $snapshot = $platform->fetchAccountSnapshot($account);
            $completedAt = now();

            DB::transaction(function () use ($account, $log, $snapshot, $completedAt): void {
                $freshAccount = TradingAccount::query()
                    ->with(['challengePlan', 'challengePurchase'])
                    ->lockForUpdate()
                    ->findOrFail($account->id);
                $previousStatus = $freshAccount->account_status;
                $previousPhaseIndex = (int) $freshAccount->phase_index;

                $metrics = $this->metricsCalculator->calculate($freshAccount, $snapshot);
                $workingCopy = $freshAccount->replicate();
                $workingCopy->exists = true;
                $workingCopy->forceFill(array_merge($snapshot, $metrics));

                if ($workingCopy->activated_at === null && (($snapshot['account_status'] ?? null) === 'active' || ($snapshot['platform_status'] ?? null) === 'connected')) {
                    $workingCopy->activated_at = $completedAt;
                }

                $ruleState = $this->ruleEvaluator->evaluate($workingCopy);
                $activatedAt = $snapshot['activated_at'] ?? $workingCopy->activated_at;

                $freshAccount->forceFill(array_merge(
                    $this->snapshotAccountFill($snapshot),
                    $metrics,
                    $ruleState,
                    [
                        'activated_at' => $activatedAt,
                        'sync_status' => 'success',
                        'synced_at' => $completedAt,
                        'last_synced_at' => $completedAt,
                        'last_sync_started_at' => $log->started_at,
                        'last_sync_completed_at' => $completedAt,
                        'sync_error' => null,
                        'sync_error_at' => null,
                    ],
                ))->save();

                $freshAccount->balanceSnapshots()->create([
                    'snapshot_at' => $completedAt,
                    'balance' => $freshAccount->balance,
                    'equity' => $freshAccount->equity,
                    'profit_loss' => $freshAccount->profit_loss,
                    'total_profit' => $freshAccount->total_profit,
                    'today_profit' => $freshAccount->today_profit,
                    'daily_drawdown' => $freshAccount->daily_drawdown,
                    'max_drawdown' => $freshAccount->max_drawdown,
                    'drawdown_percent' => $freshAccount->drawdown_percent,
                    'payload' => $snapshot['raw'] ?? null,
                ]);

                $log->forceFill([
                    'status' => 'success',
                    'message' => 'Trading account sync completed.',
                    'completed_at' => $completedAt,
                    'payload' => $snapshot['raw'] ?? null,
                ])->save();

                if ($freshAccount->challengePurchase !== null) {
                    $freshAccount->challengePurchase->forceFill([
                        'account_status' => $freshAccount->account_status,
                        'started_at' => $freshAccount->activated_at ?? $freshAccount->challengePurchase->started_at,
                        'funded_status' => $freshAccount->is_funded ? 'funded' : $freshAccount->challengePurchase->funded_status,
                        'meta' => array_merge($freshAccount->challengePurchase->meta ?? [], [
                            'trading_account_id' => $freshAccount->id,
                            'last_synced_at' => optional($freshAccount->last_synced_at)->toIso8601String(),
                        ]),
                    ])->save();
                }

                if ($previousStatus !== $freshAccount->account_status || $previousPhaseIndex !== (int) $freshAccount->phase_index) {
                    $freshAccount->statusHistories()->create([
                        'previous_status' => $previousStatus,
                        'new_status' => $freshAccount->account_status,
                        'previous_phase_index' => $previousPhaseIndex,
                        'new_phase_index' => (int) $freshAccount->phase_index,
                        'source' => 'sync',
                        'context' => $freshAccount->rule_state,
                        'changed_at' => $completedAt,
                    ]);
                }
            });

            return [
                'status' => 'success',
                'account_id' => $account->id,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->markError($account, $log, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function snapshotAccountFill(array $snapshot): array
    {
        return array_filter([
            'platform_account_id' => $snapshot['platform_account_id'] ?? null,
            'platform_login' => $snapshot['platform_login'] ?? null,
            'platform_environment' => $snapshot['platform_environment'] ?? null,
            'platform_status' => $snapshot['platform_status'] ?? null,
            'account_phase' => $snapshot['account_phase'] ?? null,
            'phase_index' => $snapshot['phase_index'] ?? null,
            'stage' => $snapshot['stage'] ?? null,
            'is_funded' => $snapshot['is_funded'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function markSkipped(TradingAccount $account, TradingAccountSyncLog $log, string $message): array
    {
        $completedAt = now();

        DB::transaction(function () use ($account, $log, $message, $completedAt): void {
            TradingAccount::query()->whereKey($account->id)->update([
                'sync_status' => 'skipped',
                'sync_error' => $message,
                'sync_error_at' => $completedAt,
                'last_sync_started_at' => $log->started_at,
                'last_sync_completed_at' => $completedAt,
            ]);

            $log->forceFill([
                'status' => 'skipped',
                'message' => $message,
                'completed_at' => $completedAt,
            ])->save();
        });

        return [
            'status' => 'skipped',
            'account_id' => $account->id,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function markError(TradingAccount $account, TradingAccountSyncLog $log, string $message): array
    {
        $completedAt = now();

        DB::transaction(function () use ($account, $log, $message, $completedAt): void {
            TradingAccount::query()->whereKey($account->id)->update([
                'sync_status' => 'error',
                'sync_error' => $message,
                'sync_error_at' => $completedAt,
                'last_sync_started_at' => $log->started_at,
                'last_sync_completed_at' => $completedAt,
            ]);

            $log->forceFill([
                'status' => 'error',
                'message' => 'Trading account sync failed.',
                'error_message' => $message,
                'completed_at' => $completedAt,
            ])->save();
        });

        return [
            'status' => 'error',
            'account_id' => $account->id,
            'message' => $message,
        ];
    }
}
