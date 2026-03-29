<?php

namespace App\Console\Commands;

use App\Jobs\SyncTradingAccountJob;
use App\Models\TradingAccount;
use App\Services\TradingAccounts\TradingAccountSyncService;
use Illuminate\Console\Command;

class SyncTradingAccounts extends Command
{
    protected $signature = 'trading:sync-accounts
        {--account= : Sync a single trading account ID}
        {--queued : Dispatch each account sync onto the queue instead of running inline}';

    protected $description = 'Sync active Wolforix challenge and funded trading accounts from cTrader.';

    public function handle(TradingAccountSyncService $syncService): int
    {
        $accountId = $this->option('account');
        $query = TradingAccount::query()
            ->where('is_trial', false)
            ->where('platform_slug', 'ctrader')
            ->when($accountId, fn ($builder) => $builder->whereKey((int) $accountId))
            ->orderBy('id');

        $accounts = $query->limit((int) config('trading.sync.chunk_size', 50))->get();

        if ($accounts->isEmpty()) {
            $this->info('No trading accounts matched the sync criteria.');

            return self::SUCCESS;
        }

        $queued = (bool) $this->option('queued') && (bool) config('trading.sync.use_queue', true);

        foreach ($accounts as $account) {
            if ($queued) {
                SyncTradingAccountJob::dispatch($account->id);
                $this->line("Queued account #{$account->id} for sync.");

                continue;
            }

            $result = $syncService->sync($account);
            $this->line(sprintf(
                'Account #%d sync result: %s%s',
                $account->id,
                $result['status'],
                isset($result['message']) ? ' ('.$result['message'].')' : '',
            ));
        }

        return self::SUCCESS;
    }
}
