<?php

namespace App\Services\TradingAccounts;

use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Services\TradingPlatforms\TradingPlatformManager;
use App\Support\CTrader\CTraderAuthorizationRequiredException;
use Illuminate\Support\Facades\DB;
use Throwable;

class TradingAccountSyncService
{
    public function __construct(
        private readonly TradingPlatformManager $platformManager,
        private readonly TradingAccountSnapshotApplyService $snapshotApplyService,
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

            $this->snapshotApplyService->apply($account, $snapshot, [
                'source' => $platform->slug(),
                'started_at' => $log->started_at,
                'snapshot_at' => $completedAt,
            ]);

            $log->forceFill([
                'status' => 'success',
                'message' => 'Trading account sync completed.',
                'completed_at' => $completedAt,
                'payload' => $snapshot['raw'] ?? null,
            ])->save();

            return [
                'status' => 'success',
                'account_id' => $account->id,
            ];
        } catch (CTraderAuthorizationRequiredException $exception) {
            report($exception);

            return $this->markAuthorizationRequired($account, $log, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->markError($account, $log, $exception->getMessage());
        }
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
    private function markAuthorizationRequired(TradingAccount $account, TradingAccountSyncLog $log, string $message): array
    {
        $completedAt = now();

        DB::transaction(function () use ($account, $log, $message, $completedAt): void {
            TradingAccount::query()->whereKey($account->id)->update([
                'sync_status' => 'skipped',
                'platform_status' => 'authorization_required',
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
            'status' => 'authorization_required',
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
