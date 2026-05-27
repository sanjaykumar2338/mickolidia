<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaApiLiveSyncService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class SyncMetaApiAccount extends Command
{
    protected $signature = 'wolforix:sync-metaapi-account
        {login : Assigned MT5 login/account reference to sync}
        {--metaapi-account-id= : Override the stored MetaApi account UUID for this run}
        {--history-days= : Number of days to request from MetaApi history endpoints}
        {--history-limit= : Max history orders/deals per request}
        {--debug : Print sanitized JSON result}';

    protected $description = 'Sync one assigned MT5 account from MetaApi into the Wolforix dashboard/rule pipeline.';

    public function handle(MetaApiLiveSyncService $syncService): int
    {
        $login = trim((string) $this->argument('login'));

        if ($login === '') {
            $this->error('A non-empty MT5 login is required.');

            return self::FAILURE;
        }

        try {
            $result = $syncService->syncByLogin($login, [
                'metaapi_account_id' => $this->option('metaapi-account-id'),
                'history_days' => $this->option('history-days'),
                'history_limit' => $this->option('history-limit'),
            ]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('MetaApi account sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('MetaApi account sync result');
        $this->table(['Field', 'Value'], [
            ['Login', (string) ($result['login'] ?? $login)],
            ['MetaApi account', (string) ($result['metaapi_account_id'] ?? '-')],
            ['Status', (string) ($result['status'] ?? '-')],
            ['Validation state', (string) ($result['validation_state'] ?? '-')],
            ['Deploy state', (string) ($result['deploy_status'] ?? '-')],
            ['Connection status', (string) ($result['connection_status'] ?? '-')],
            ['Account info readable', ! empty($result['account_information_readable']) ? 'yes' : 'no'],
            ['Positions readable', ! empty($result['positions_readable']) ? 'yes' : 'no'],
            ['History readable', ! empty($result['history_readable']) ? 'yes' : 'no'],
            ['Balance', array_key_exists('balance', $result) ? (string) $result['balance'] : '-'],
            ['Equity', array_key_exists('equity', $result) ? (string) $result['equity'] : '-'],
            ['Sync log', (string) ($result['sync_log_id'] ?? '-')],
            ['Lifecycle state', (string) ($result['lifecycle_state'] ?? '-')],
            ['Sync health', (string) ($result['sync_health'] ?? '-')],
            ['Phase 1B ready', ! empty($result['phase_1b_ready']) ? 'yes' : 'no'],
        ]);

        if (($result['history_errors'] ?? []) !== []) {
            $this->warn('History degraded: '.implode(' | ', (array) $result['history_errors']));
        }

        if ((bool) $this->option('debug')) {
            $this->newLine();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[result unavailable]');
        }

        return ($result['status'] ?? null) === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
