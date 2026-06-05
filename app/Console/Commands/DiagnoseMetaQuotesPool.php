<?php

namespace App\Console\Commands;

use App\Services\MetaQuotes\MetaQuotesPoolProvisioningService;
use Illuminate\Console\Command;

class DiagnoseMetaQuotesPool extends Command
{
    protected $signature = 'wolforix:diagnose-metaquotes-pool
        {--server=FusionMarkets-Demo : Restrict diagnostics to a server}
        {--source-pool= : Restrict diagnostics to a source pool}
        {--source-file= : Restrict diagnostics to a source file}
        {--broker= : Restrict diagnostics to a broker}
        {--platform= : Restrict diagnostics to a platform}
        {--json : Print JSON diagnostics}';

    protected $description = 'Diagnose MetaQuotes/MT5 pool readiness for automated onboarding.';

    public function handle(MetaQuotesPoolProvisioningService $provisioningService): int
    {
        $diagnostic = $provisioningService->diagnose([
            'server' => $this->option('server'),
            'source_pool' => $this->option('source-pool'),
            'source_file' => $this->option('source-file'),
            'broker' => $this->option('broker'),
            'platform' => $this->option('platform'),
        ]);

        $this->info('MetaQuotes pool diagnostics');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Server', (string) data_get($diagnostic, 'server', '-')],
            ['Available accounts', (string) data_get($diagnostic, 'available_accounts', 0)],
            ['Allocated accounts', (string) data_get($diagnostic, 'allocated_accounts', 0)],
            ['Stale allocations', (string) data_get($diagnostic, 'stale_allocations', 0)],
            ['Missing MetaApi mapping', (string) data_get($diagnostic, 'missing_metaapi_mapping', 0)],
            ['Assigned pending MetaApi', (string) data_get($diagnostic, 'assigned_pending_metaapi', 0)],
            ['Duplicate login risk', (string) data_get($diagnostic, 'duplicate_login_risk', 0)],
        ]);

        $this->printList('Onboarding blockers', (array) data_get($diagnostic, 'onboarding_blockers', []));

        $missingMappings = (array) data_get($diagnostic, 'missing_metaapi_mappings', []);

        if ($missingMappings !== []) {
            $this->newLine();
            $this->info('Allocated accounts missing MetaApi mapping');
            $this->table(
                ['Pool row', 'Login', 'Account reference', 'Trading account', 'Pool UUID', 'Account UUID', 'Expected UUID state'],
                collect($missingMappings)->map(fn (array $row): array => [
                    (string) data_get($row, 'pool_entry_id', '-'),
                    (string) data_get($row, 'login', '-'),
                    (string) (data_get($row, 'account_reference') ?: '-'),
                    (string) (data_get($row, 'allocated_trading_account_id') ?: '-'),
                    (string) data_get($row, 'pool_metaapi_uuid_state', '-'),
                    (string) data_get($row, 'account_metaapi_uuid_state', '-'),
                    (string) data_get($row, 'expected_metaapi_uuid_state', '-'),
                ])->all(),
            );
        }

        $this->printList('Recommendations', (array) data_get($diagnostic, 'recommendations', []));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
        }

        return self::SUCCESS;
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
