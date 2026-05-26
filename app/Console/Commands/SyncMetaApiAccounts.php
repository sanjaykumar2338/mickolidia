<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaApiLiveSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncMetaApiAccounts extends Command
{
    protected $signature = 'wolforix:sync-metaapi-accounts
        {--limit=10 : Maximum assigned active MT5 accounts to sync}
        {--history-days= : Number of days to request from MetaApi history endpoints}
        {--history-limit= : Max history orders/deals per request}
        {--debug : Print sanitized JSON result}';

    protected $description = 'Sync assigned active MT5 accounts from MetaApi into the Wolforix dashboard/rule pipeline.';

    public function handle(MetaApiLiveSyncService $syncService): int
    {
        try {
            $result = $syncService->syncAccounts([
                'limit' => $this->option('limit'),
                'history_days' => $this->option('history-days'),
                'history_limit' => $this->option('history-limit'),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('MetaApi account batch sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('MetaApi account batch sync result');
        $this->table(['Field', 'Value'], [
            ['Requested limit', (string) ($result['requested_limit'] ?? '-')],
            ['Synced', (string) ($result['synced'] ?? 0)],
            ['Status', (string) ($result['status'] ?? '-')],
        ]);

        $rows = collect((array) ($result['results'] ?? []))
            ->map(fn (array $row): array => [
                (string) ($row['login'] ?? '-'),
                (string) ($row['metaapi_account_id'] ?? '-'),
                (string) ($row['status'] ?? '-'),
                (string) ($row['validation_state'] ?? '-'),
                (string) ($row['connection_status'] ?? '-'),
                ! empty($row['history_readable']) ? 'yes' : 'no',
            ])
            ->all();

        if ($rows !== []) {
            $this->newLine();
            $this->table(['Login', 'MetaApi account', 'Status', 'State', 'Connection', 'History'], $rows);
        }

        if ((bool) $this->option('debug')) {
            $this->newLine();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[result unavailable]');
        }

        return ($result['status'] ?? null) === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
