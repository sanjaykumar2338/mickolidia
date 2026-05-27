<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaApiAccountMappingRepairService;
use Illuminate\Console\Command;

class ListUnassignedMetaApiAccounts extends Command
{
    protected $signature = 'wolforix:list-unassigned-metaapi-accounts
        {--limit=50 : Maximum unassigned MetaApi pool rows to show}';

    protected $description = 'List unassigned MT5 pool rows that have MetaApi UUIDs without printing secrets.';

    public function handle(MetaApiAccountMappingRepairService $repairService): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $rows = $repairService->unassignedMetaApiPoolEntries($limit);

        $this->info('Unassigned MetaApi MT5 accounts');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();

        if ($rows === []) {
            $this->info('No unassigned MetaApi pool rows were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['pool_id', 'login', 'server', 'metaapi_account_id', 'candidate', 'match', 'recommendation'],
            collect($rows)->map(function (array $row): array {
                $poolEntry = (array) ($row['pool_entry'] ?? []);
                $resolution = (array) ($row['trading_account_resolution'] ?? []);
                $candidates = (array) ($resolution['candidates'] ?? []);
                $candidate = (array) ($candidates[0] ?? []);

                return [
                    (string) ($poolEntry['id'] ?? '-'),
                    (string) ($poolEntry['login'] ?? '-'),
                    (string) ($poolEntry['server'] ?? '-'),
                    (string) ($poolEntry['metaapi_account_id'] ?? '-'),
                    $candidate !== []
                        ? '#'.(string) ($candidate['id'] ?? '-').' '.(string) ($candidate['account_reference'] ?? '-')
                        : '-',
                    (string) (($resolution['status'] ?? '-').' / '.($resolution['source'] ?? '-')),
                    (string) ($row['recommendation'] ?? '-'),
                ];
            })->all(),
        );

        return self::SUCCESS;
    }
}
