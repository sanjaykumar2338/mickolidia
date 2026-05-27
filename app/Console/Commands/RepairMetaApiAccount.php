<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaApiAccountMappingRepairService;
use Illuminate\Console\Command;

class RepairMetaApiAccount extends Command
{
    protected $signature = 'wolforix:repair-metaapi-account
        {login : MT5 login/account reference to repair}
        {--metaapi-account-id= : Optional MetaApi UUID to persist if local records do not have one}
        {--dry-run : Show repair plan without writing}
        {--no-api-lookup : Do not query MetaApi for a missing account UUID}';

    protected $description = 'Repair MetaApi MT5 pool/trading-account mapping without printing secrets.';

    public function handle(MetaApiAccountMappingRepairService $repairService): int
    {
        $login = trim((string) $this->argument('login'));

        if ($login === '') {
            $this->error('A non-empty MT5 login is required.');

            return self::FAILURE;
        }

        $result = $repairService->repairByLogin($login, [
            'metaapi_account_id' => $this->option('metaapi-account-id'),
            'dry_run' => (bool) $this->option('dry-run'),
            'allow_api_lookup' => ! (bool) $this->option('no-api-lookup'),
        ]);

        $this->info('MetaApi account mapping repair');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();

        $poolEntry = (array) ($result['pool_entry'] ?? []);
        $tradingAccount = (array) ($result['trading_account'] ?? []);
        $mapping = (array) ($result['mapping'] ?? []);

        $this->table(['Field', 'Value'], [
            ['Login', (string) ($result['login'] ?? $login)],
            ['Status', (string) ($result['status'] ?? '-')],
            ['Pool entry', (string) ($poolEntry['id'] ?? '-')],
            ['Pool allocated account', (string) ($poolEntry['allocated_trading_account_id'] ?? '-')],
            ['Trading account', (string) ($tradingAccount['id'] ?? '-')],
            ['Account reference', (string) ($tradingAccount['account_reference'] ?? '-')],
            ['MetaApi account', (string) ($result['metaapi_account_id'] ?? '-')],
            ['Canonical server', (string) ($result['canonical_server'] ?? '-')],
            ['Mapping mismatch', ! empty($mapping['mapping_mismatch']) ? 'yes' : 'no'],
            ['Missing assignment', ! empty($mapping['missing_assignment']) ? 'yes' : 'no'],
            ['Missing MetaApi id', ! empty($mapping['missing_metaapi_id']) ? 'yes' : 'no'],
            ['Dry run', ! empty($result['dry_run']) ? 'yes' : 'no'],
        ]);

        $this->printList('Changes', (array) ($result['changes'] ?? []));
        $this->printList('Warnings', (array) ($result['warnings'] ?? []));
        $this->printList('Errors', (array) ($result['errors'] ?? []));
        $this->printList('Repair recommendation', (array) ($result['recommendations'] ?? []));

        return in_array((string) ($result['status'] ?? ''), ['ok', 'repaired', 'repair_available'], true)
            ? self::SUCCESS
            : self::FAILURE;
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
