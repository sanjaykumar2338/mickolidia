<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Services\MetaApi\MetaApiAccountMappingRepairService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RepairMetaQuotesPoolMappings extends Command
{
    protected $signature = 'wolforix:repair-metaquotes-pool-mappings
        {--server=FusionMarkets-Demo : Restrict repairs to a server}
        {--source-pool= : Restrict repairs to a source pool}
        {--source-file= : Restrict repairs to a source file}
        {--broker= : Restrict repairs to a broker}
        {--platform= : Restrict repairs to a platform}
        {--confirm : Apply repairs. Without this flag the command is a dry run}
        {--no-api-lookup : Do not query MetaApi for missing UUIDs}
        {--json : Print JSON repair results}';

    protected $description = 'Repair missing MetaApi UUID mappings for allocated MetaQuotes/MT5 pool rows.';

    public function handle(MetaApiAccountMappingRepairService $repairService): int
    {
        $confirm = (bool) $this->option('confirm');
        $allowApiLookup = ! (bool) $this->option('no-api-lookup');
        $rows = $this->missingRows();
        $results = [];

        $this->info('MetaQuotes pool MetaApi mapping repair');
        $this->line('Secrets are never printed by this command.');
        $this->line($confirm ? 'Mode: APPLY' : 'Mode: DRY RUN');
        $this->newLine();

        if ($rows->isEmpty()) {
            $this->info('No allocated pool rows are missing MetaApi mapping for this scope.');
        }

        foreach ($rows as $entry) {
            $result = $repairService->repairByLogin((string) $entry->login, [
                'dry_run' => ! $confirm,
                'assign' => false,
                'allow_api_lookup' => $allowApiLookup,
            ]);

            $results[] = [
                'pool_entry_id' => $entry->id,
                'login' => $entry->login,
                'account_reference' => data_get($result, 'trading_account.account_reference'),
                'allocated_trading_account_id' => $entry->allocated_trading_account_id,
                'metaapi_account_id' => data_get($result, 'metaapi_account_id'),
                'status' => data_get($result, 'status'),
                'changes' => data_get($result, 'changes', []),
                'warnings' => data_get($result, 'warnings', []),
                'errors' => data_get($result, 'errors', []),
            ];
        }

        if ($results !== []) {
            $this->table(
                ['Pool row', 'Login', 'Account reference', 'Trading account', 'MetaApi UUID state', 'Status', 'Errors'],
                collect($results)->map(fn (array $result): array => [
                    (string) $result['pool_entry_id'],
                    (string) $result['login'],
                    (string) ($result['account_reference'] ?: '-'),
                    (string) ($result['allocated_trading_account_id'] ?: '-'),
                    filled((string) $result['metaapi_account_id']) ? 'present' : 'missing',
                    (string) ($result['status'] ?: '-'),
                    implode(', ', (array) $result['errors']) ?: '-',
                ])->all(),
            );
        }

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'confirm' => $confirm,
                'server' => $this->serverOption(),
                'repair_count' => count($results),
                'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[repair result unavailable]');
        }

        return collect($results)->contains(fn (array $result): bool => ! in_array((string) $result['status'], ['ok', 'repaired', 'repair_available'], true))
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Mt5AccountPoolEntry>
     */
    private function missingRows()
    {
        return $this->scopedPoolQuery()
            ->whereNotNull('allocated_trading_account_id')
            ->where(function (Builder $query): void {
                $query->whereNull('meta->metaapi_account_id')
                    ->orWhere('meta->metaapi_account_id', '');
            })
            ->orderBy('allocated_at')
            ->orderBy('id')
            ->get();
    }

    private function scopedPoolQuery(): Builder
    {
        $query = Mt5AccountPoolEntry::query();
        $server = $this->serverOption();
        $sourcePool = trim((string) ($this->option('source-pool') ?: config('wolforix.mt5_account_pool.default_pool', Mt5AccountPoolEntry::SOURCE_POOL_CLIENT)));
        $sourceFile = trim((string) ($this->option('source-file') ?: config('wolforix.mt5_account_pool.active_source_file')));
        $broker = trim((string) ($this->option('broker') ?: config('wolforix.mt5_account_pool.active_broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS)));
        $platform = trim((string) ($this->option('platform') ?: config('wolforix.mt5_account_pool.active_platform', Mt5AccountPoolEntry::PLATFORM_MT5)));

        if ($server !== '') {
            $query->whereIn('server', $this->serverAliases($server));
        }

        if ($sourcePool !== '') {
            $query->where('source_pool', $sourcePool);
        }

        if ($sourceFile !== '') {
            $query->where('source_file', basename($sourceFile) ?: $sourceFile);
        }

        if ($broker !== '') {
            $query->where(function (Builder $query) use ($broker): void {
                $query->where('meta->broker', $broker)
                    ->orWhere('meta->provider', $broker);
            });
        }

        if ($platform !== '') {
            $query->where('meta->platform', $platform);
        }

        return $query;
    }

    private function serverOption(): string
    {
        return trim((string) $this->option('server'));
    }

    /**
     * @return list<string>
     */
    private function serverAliases(string $server): array
    {
        $aliases = [$server];
        $lower = strtolower($server);

        if (str_contains($lower, 'fusionmarkets') || str_contains($lower, 'fusion markets')) {
            $aliases[] = (string) config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo');
            $aliases[] = 'Fusion Markets Pty - FusionMarkets Demo';
        }

        return array_values(array_unique(array_filter($aliases)));
    }
}
