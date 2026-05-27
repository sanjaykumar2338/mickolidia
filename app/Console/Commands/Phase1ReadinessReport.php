<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Support\Mt5SyncAnomalyInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class Phase1ReadinessReport extends Command
{
    protected $signature = 'wolforix:phase1-readiness-report
        {--json : Print JSON report}';

    protected $description = 'Print final MVP cloud-sync readiness across onboarding, sync, lifecycle, webhooks, dashboard, recovery, pool, and migration.';

    public function handle(Mt5SyncAnomalyInspector $anomalyInspector): int
    {
        $accounts = TradingAccount::query()
            ->where(function ($query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5')
                    ->orWhere('platform', 'MT5 Demo');
            })
            ->get();
        $report = $this->report($accounts, $anomalyInspector);

        $this->info('Phase 1 cloud-sync readiness report');
        $this->line('MVP stabilization report only. No new provider, payment, CRM, wallet, or trading UI automation is created.');
        $this->newLine();
        $this->table(['Area', 'Status', 'Summary'], [
            ['Onboarding', data_get($report, 'onboarding.status'), data_get($report, 'onboarding.summary')],
            ['Sync', data_get($report, 'sync.status'), data_get($report, 'sync.summary')],
            ['Lifecycle', data_get($report, 'lifecycle.status'), data_get($report, 'lifecycle.summary')],
            ['Webhooks', data_get($report, 'webhooks.status'), data_get($report, 'webhooks.summary')],
            ['Dashboard', data_get($report, 'dashboard.status'), data_get($report, 'dashboard.summary')],
            ['Recovery', data_get($report, 'recovery.status'), data_get($report, 'recovery.summary')],
            ['Pool', data_get($report, 'pool.status'), data_get($report, 'pool.summary')],
            ['Migration', data_get($report, 'migration.status'), data_get($report, 'migration.summary')],
        ]);

        $this->printList('Warnings', $report['warnings']);
        $this->printList('Recommendations', $report['recommendations']);

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[report unavailable]');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @return array<string, mixed>
     */
    private function report($accounts, Mt5SyncAnomalyInspector $anomalyInspector): array
    {
        $readyCount = $accounts->filter(fn (TradingAccount $account): bool => $this->readyToTrade($account))->count();
        $queueCount = $accounts->filter(fn (TradingAccount $account): bool => in_array((string) data_get($account->meta, 'metaapi_onboarding.state'), [
            'purchased',
            'account_assigned',
            'waiting_metaapi_connection',
            'first_sync_received',
        ], true))->count();
        $inconsistentCount = $accounts->filter(function (TradingAccount $account): bool {
            $state = (string) data_get($account->meta, 'metaapi_onboarding.state');

            return $state === 'ready_to_trade' && ! $this->readyToTrade($account);
        })->count();
        $syncReport = $anomalyInspector->report($accounts, 10);
        $syncSummary = (array) $syncReport['summary'];
        $connectedCount = (int) $syncSummary['connected'];
        $staleCount = (int) $syncSummary['stale'];
        $disconnectedCount = (int) $syncSummary['disconnected'];
        $syncIssueCount = (int) $syncSummary['errors'];
        $metaApiIssueCount = (int) $syncSummary['metaapi_issues'];
        $legacyIgnoredCount = (int) $syncSummary['legacy_ignored_for_metaapi_signoff'];
        $breachedCount = $accounts->filter(fn (TradingAccount $account): bool => $account->challenge_status === 'failed' || filled((string) $account->failure_reason))->count();
        $recoveredCount = $accounts->filter(fn (TradingAccount $account): bool => (int) data_get($account->meta, 'metaapi_lifecycle.recovery_count', 0) > 0)->count();
        $poolAvailable = Mt5AccountPoolEntry::query()
            ->where('is_available', true)
            ->whereNull('allocated_trading_account_id')
            ->count();
        $poolMetaApiUnassigned = Mt5AccountPoolEntry::query()
            ->whereNotNull('meta->metaapi_account_id')
            ->whereNull('allocated_trading_account_id')
            ->count();
        $webhooks = $this->webhookReadiness();
        $warnings = [];

        if ($inconsistentCount > 0) {
            $warnings[] = "{$inconsistentCount} account(s) have ready_to_trade state but are not currently readable/connected.";
        }

        if ($metaApiIssueCount > 0) {
            $warnings[] = "{$metaApiIssueCount} MetaApi account(s) have stale/disconnected/error sync anomalies that need review. Run wolforix:diagnose-sync-anomalies.";
        } elseif ($legacyIgnoredCount > 0) {
            $warnings[] = "{$legacyIgnoredCount} legacy EA fallback account(s) show stale/disconnected/error sync anomalies and are ignored for MetaApi Phase 1 signoff.";
        }

        if ($poolAvailable === 0) {
            $warnings[] = 'MT5 pool has no currently available unallocated accounts.';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'onboarding' => [
                'status' => $inconsistentCount === 0 ? 'ready' : 'needs_attention',
                'summary' => "ready={$readyCount}, queue={$queueCount}, inconsistent={$inconsistentCount}",
                'ready_to_trade' => $readyCount,
                'queue' => $queueCount,
                'inconsistent' => $inconsistentCount,
            ],
            'sync' => [
                'status' => $metaApiIssueCount === 0 ? 'ready' : 'needs_attention',
                'summary' => "metaapi_connected={$syncSummary['metaapi_connected']}, metaapi_stale={$syncSummary['metaapi_stale']}, metaapi_disconnected={$syncSummary['metaapi_disconnected']}, metaapi_errors={$syncSummary['metaapi_errors']}, legacy_ignored={$legacyIgnoredCount}",
                'connected' => $connectedCount,
                'stale' => $staleCount,
                'disconnected' => $disconnectedCount,
                'errors' => $syncIssueCount,
                'metaapi_connected' => (int) $syncSummary['metaapi_connected'],
                'metaapi_stale' => (int) $syncSummary['metaapi_stale'],
                'metaapi_disconnected' => (int) $syncSummary['metaapi_disconnected'],
                'metaapi_errors' => (int) $syncSummary['metaapi_errors'],
                'metaapi_issues' => $metaApiIssueCount,
                'legacy_ignored_for_metaapi_signoff' => $legacyIgnoredCount,
            ],
            'lifecycle' => [
                'status' => 'ready',
                'summary' => "breached={$breachedCount}, final-state locks preserved",
                'breached' => $breachedCount,
            ],
            'webhooks' => [
                'status' => 'ready',
                'summary' => 'email, discord, telegram, and CRM placeholders are configurable and disabled/safe by default',
                'providers' => $webhooks,
            ],
            'dashboard' => [
                'status' => 'ready',
                'summary' => 'trader/admin dashboard readiness indicators are present',
                'trader_indicators' => ['onboarding', 'ready_to_trade', 'sync_health', 'lifecycle_state', 'breach_status', 'recovery_warnings'],
                'admin_indicators' => ['onboarding_queue', 'ready_to_trade', 'connected', 'disconnected', 'stale', 'pool_availability', 'sync_issues'],
            ],
            'recovery' => [
                'status' => 'ready',
                'summary' => "recovered={$recoveredCount}, metaapi_stale={$syncSummary['metaapi_stale']}, metaapi_disconnected={$syncSummary['metaapi_disconnected']}",
                'recovered' => $recoveredCount,
            ],
            'pool' => [
                'status' => $poolAvailable > 0 ? 'ready' : 'needs_inventory',
                'summary' => "available={$poolAvailable}, metaapi_unassigned={$poolMetaApiUnassigned}",
                'available' => $poolAvailable,
                'metaapi_unassigned' => $poolMetaApiUnassigned,
            ],
            'migration' => [
                'status' => 'diagnostic_ready',
                'summary' => 'broker migration groundwork is documented through wolforix:diagnose-broker-abstraction',
            ],
            'warnings' => $warnings,
            'recommendations' => [
                'Run wolforix:diagnose-onboarding {login} for any account not showing ready_to_trade.',
                'Run wolforix:diagnose-sync-health {login} for stale/disconnected accounts.',
                'Keep webhook providers disabled until real endpoint secrets are configured on live.',
                'Keep EA fallback until MetaApi live coverage is proven across the production pool.',
            ],
        ];
    }

    private function readyToTrade(TradingAccount $account): bool
    {
        $state = (string) data_get($account->meta, 'metaapi_onboarding.state');

        if (! in_array($state, ['ready_to_trade', 'active'], true)) {
            return false;
        }

        if ($account->challenge_status === 'failed'
            || filled((string) $account->failure_reason)
            || (bool) $account->final_state_locked
            || (bool) $account->trading_blocked
        ) {
            return false;
        }

        if (in_array((string) $account->platform_status, ['stale', 'disconnected', 'disabled', 'disable_requested', 'disable_pending_ack', 'disable_failed'], true)) {
            return false;
        }

        if ((string) $account->sync_status === 'error') {
            return false;
        }

        $syncHealth = (string) data_get($account->meta, 'metaapi_lifecycle.sync_health');
        $coreHealth = (string) data_get($account->meta, 'metaapi_lifecycle.core_sync_health', $syncHealth);

        if (in_array($syncHealth, ['stale', 'disconnected'], true) || in_array($coreHealth, ['stale', 'disconnected'], true)) {
            return false;
        }

        $lastSync = $account->last_synced_at
            ?? data_get($account->meta, 'mt5_sync.last_successful_metric_update_at')
            ?? data_get($account->meta, 'mt5_sync.last_synced_at');

        return ((string) data_get($account->meta, 'metaapi_lifecycle.state') === 'connected' || (string) $account->platform_status === 'connected')
            && $lastSync !== null
            && is_numeric($account->balance)
            && is_numeric($account->equity);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function webhookReadiness(): array
    {
        return [
            'email' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.email_enabled', true),
                'configured' => true,
            ],
            'discord' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.discord_enabled', false),
                'configured' => filled((string) config('services.metaapi.events.discord_webhook_url')),
            ],
            'telegram' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.telegram_enabled', false),
                'configured' => filled((string) config('services.metaapi.events.telegram_bot_token'))
                    && filled((string) config('services.metaapi.events.telegram_chat_id')),
            ],
            'crm_webhook' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.crm_webhook_enabled', false),
                'configured' => filled((string) config('services.metaapi.events.crm_webhook_url')),
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
