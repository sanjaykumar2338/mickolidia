<?php

namespace App\Jobs;

use App\Models\TradingAccount;
use App\Services\TradingAccounts\TradingAccountSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTradingAccountJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $tradingAccountId,
    ) {
        $this->onQueue((string) config('trading.sync.queue', 'trading-sync'));
    }

    public function handle(TradingAccountSyncService $syncService): void
    {
        $account = TradingAccount::query()->find($this->tradingAccountId);

        if (! $account instanceof TradingAccount) {
            return;
        }

        $syncService->sync($account);
    }
}
