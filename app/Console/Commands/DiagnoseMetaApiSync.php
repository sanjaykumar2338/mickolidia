<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaApiLiveSyncService;
use Illuminate\Console\Command;

class DiagnoseMetaApiSync extends Command
{
    protected $signature = 'wolforix:diagnose-metaapi-sync
        {login : MT5 login/account reference to inspect}
        {--json : Print sanitized JSON diagnostics}';

    protected $description = 'Inspect local MetaApi sync state for an assigned MT5 account without printing secrets.';

    public function handle(MetaApiLiveSyncService $syncService): int
    {
        $login = trim((string) $this->argument('login'));

        if ($login === '') {
            $this->error('A non-empty MT5 login is required.');

            return self::FAILURE;
        }

        $diagnostic = $syncService->diagnoseByLogin($login);

        $this->info('MetaApi live sync diagnosis');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();

        $account = (array) ($diagnostic['trading_account'] ?? []);
        $poolEntry = (array) ($diagnostic['pool_entry'] ?? []);
        $syncLog = (array) ($diagnostic['latest_sync_log'] ?? []);
        $snapshot = (array) ($diagnostic['latest_snapshot'] ?? []);
        $mapping = (array) data_get($diagnostic, 'mapping_diagnostics.mapping', []);
        $lifecycle = (array) ($diagnostic['lifecycle'] ?? []);

        $this->table(['Field', 'Value'], [
            ['Login', $login],
            ['Trading account', (string) ($account['id'] ?? '-')],
            ['Account reference', (string) ($account['account_reference'] ?? '-')],
            ['MetaApi account', (string) ($account['metaapi_account_id'] ?? $poolEntry['metaapi_account_id'] ?? '-')],
            ['Account status', (string) ($account['account_status'] ?? '-')],
            ['Challenge status', (string) ($account['challenge_status'] ?? '-')],
            ['Platform status', (string) ($account['platform_status'] ?? '-')],
            ['Sync status', (string) ($account['sync_status'] ?? '-')],
            ['Sync source', (string) ($account['sync_source'] ?? '-')],
            ['Last synced at', (string) ($account['last_synced_at'] ?? '-')],
            ['MT5 sync status', (string) ($account['mt5_sync_status'] ?? '-')],
            ['MT5 sync error', (string) ($account['mt5_sync_error'] ?? '-')],
            ['Pool entry', (string) ($poolEntry['id'] ?? '-')],
            ['Pool server', (string) ($poolEntry['server'] ?? '-')],
            ['Latest log', (string) ($syncLog['id'] ?? '-')],
            ['Latest log status', (string) ($syncLog['status'] ?? '-')],
            ['Latest log error', (string) ($syncLog['error_message'] ?? '-')],
            ['Latest snapshot', (string) ($snapshot['id'] ?? '-')],
            ['Snapshot balance', (string) ($snapshot['balance'] ?? '-')],
            ['Snapshot equity', (string) ($snapshot['equity'] ?? '-')],
            ['Lifecycle state', (string) ($lifecycle['lifecycle_state'] ?? '-')],
            ['Sync health', (string) ($lifecycle['sync_health'] ?? '-')],
            ['Recovery count', (string) data_get($lifecycle, 'recovery.recovery_count', '-')],
            ['Mapping mismatch', ! empty($mapping['mapping_mismatch']) ? 'yes' : 'no'],
            ['Missing assignment', ! empty($mapping['missing_assignment']) ? 'yes' : 'no'],
            ['Missing MetaApi id', ! empty($mapping['missing_metaapi_id']) ? 'yes' : 'no'],
        ]);

        $this->printList('Repair recommendation', (array) data_get($diagnostic, 'mapping_diagnostics.recommendations', []));
        $this->printList('Mapping warnings', (array) data_get($diagnostic, 'mapping_diagnostics.warnings', []));
        $this->printList('Mapping errors', (array) data_get($diagnostic, 'mapping_diagnostics.errors', []));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
        }

        return $account !== [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function printList(string $label, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->info($label);

        foreach ($items as $item) {
            $this->line('- '.(string) $item);
        }
    }
}
