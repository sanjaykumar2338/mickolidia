<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiClient;
use App\Services\MetaApi\MetaApiLiveSyncService;
use App\Services\Mt5\Mt5AccountAllocator;
use App\Services\TradingAccounts\TradingAccountSnapshotApplyService;
use Illuminate\Console\Command;

class DiagnoseBrokerAbstraction extends Command
{
    protected $signature = 'wolforix:diagnose-broker-abstraction
        {--json : Print JSON diagnostics}';

    protected $description = 'Diagnose future broker migration readiness without implementing new broker infrastructure.';

    public function handle(): int
    {
        $diagnostic = $this->diagnostic();

        $this->info('Broker abstraction readiness');
        $this->line('Diagnostic only. No broker migration or new broker infrastructure is created.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Readiness', (string) $diagnostic['readiness']],
            ['MT5 accounts', (string) data_get($diagnostic, 'account_surface.mt5_accounts', 0)],
            ['MetaApi sync accounts', (string) data_get($diagnostic, 'account_surface.metaapi_accounts', 0)],
            ['EA fallback accounts', (string) data_get($diagnostic, 'account_surface.ea_fallback_accounts', 0)],
            ['Swappable components', (string) count($diagnostic['swappable_components'])],
            ['Migration blockers', (string) count($diagnostic['migration_blockers'])],
        ]);

        $this->printList('MetaApi dependency points', $diagnostic['metaapi_dependency_points']);
        $this->printList('Swappable components', $diagnostic['swappable_components']);
        $this->printList('Migration blockers', $diagnostic['migration_blockers']);
        $this->printList('Recommendations', $diagnostic['recommendations']);

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnostic(): array
    {
        $mt5Accounts = TradingAccount::query()
            ->where(function ($query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5')
                    ->orWhere('platform', 'MT5 Demo');
            })
            ->count();
        $metaApiAccounts = TradingAccount::query()
            ->where(function ($query): void {
                $query->where('sync_source', 'metaapi')
                    ->orWhereNotNull('meta->metaapi_account_id')
                    ->orWhereNotNull('meta->mt5_sync->metaapi_account_id')
                    ->orWhereNotNull('meta->mt5_pool_entry->metaapi_account_id');
            })
            ->count();
        $eaFallbackAccounts = TradingAccount::query()
            ->where('sync_source', 'mt5_ea')
            ->count();

        $swappableComponents = array_values(array_filter([
            class_exists(MetaApiClient::class) ? MetaApiClient::class : null,
            class_exists(MetaApiLiveSyncService::class) ? MetaApiLiveSyncService::class : null,
            class_exists(TradingAccountSnapshotApplyService::class) ? TradingAccountSnapshotApplyService::class : null,
            class_exists(Mt5AccountAllocator::class) ? Mt5AccountAllocator::class : null,
        ]));

        $blockers = [];

        if (! config('services.metaapi.enabled')) {
            $blockers[] = 'MetaApi is disabled in config; cloud-sync migration readiness cannot be validated live.';
        }

        if ($mt5Accounts > 0 && $metaApiAccounts === 0) {
            $blockers[] = 'MT5 accounts exist but no MetaApi-linked accounts were found.';
        }

        if ($eaFallbackAccounts > 0) {
            $blockers[] = 'EA fallback is still active by design; future migration must preserve or explicitly retire it.';
        }

        return [
            'readiness' => $blockers === [] ? 'ready_for_future_design' : 'partial',
            'account_surface' => [
                'mt5_accounts' => $mt5Accounts,
                'metaapi_accounts' => $metaApiAccounts,
                'ea_fallback_accounts' => $eaFallbackAccounts,
            ],
            'metaapi_dependency_points' => [
                'MetaApiClient handles provisioning/client REST calls.',
                'MetaApiLiveSyncService pulls cloud account info, positions, and history.',
                'TradingAccountSnapshotApplyService applies provider-neutral snapshots into rules/dashboard state.',
                'Mt5AccountAllocator owns pool credential assignment and can remain broker-aware.',
            ],
            'swappable_components' => $swappableComponents,
            'migration_blockers' => $blockers,
            'recommendations' => [
                'Keep MetaApi as the validated MVP sync provider until a broker-manager API is funded.',
                'Treat TradingAccountSnapshotApplyService as the provider-neutral boundary for future migration.',
                'Do not remove EA fallback until production MetaApi coverage is proven across the live account pool.',
            ],
        ];
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
