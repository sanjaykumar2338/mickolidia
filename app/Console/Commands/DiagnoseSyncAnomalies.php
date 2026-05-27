<?php

namespace App\Console\Commands;

use App\Support\Mt5SyncAnomalyInspector;
use Illuminate\Console\Command;

class DiagnoseSyncAnomalies extends Command
{
    protected $signature = 'wolforix:diagnose-sync-anomalies
        {--limit=50 : Maximum anomaly rows to show}
        {--json : Print JSON diagnostics}';

    protected $description = 'List MT5 sync stale/disconnected/error anomalies with safe Phase 1 closeout recommendations.';

    public function handle(Mt5SyncAnomalyInspector $inspector): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $report = $inspector->report(limit: $limit);
        $summary = (array) $report['summary'];
        $anomalies = (array) $report['anomalies'];

        $this->info('MT5 sync anomaly diagnosis');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Status', (string) $report['status']],
            ['Total MT5 accounts', (string) $summary['total_accounts']],
            ['Connected', (string) $summary['connected']],
            ['Stale', (string) $summary['stale']],
            ['Disconnected', (string) $summary['disconnected']],
            ['Errors', (string) $summary['errors']],
            ['MetaApi issues', (string) $summary['metaapi_issues']],
            ['Legacy EA ignored for MetaApi signoff', (string) $summary['legacy_ignored_for_metaapi_signoff']],
        ]);

        if ($anomalies === []) {
            $this->info('No stale, disconnected, or error sync anomalies were found.');
        } else {
            $this->newLine();
            $this->table(
                ['id', 'login', 'source', 'status', 'last_sync', 'reason', 'recommended_fix'],
                collect($anomalies)->map(fn (array $row): array => [
                    (string) ($row['trading_account_id'] ?? '-'),
                    (string) ($row['login'] ?? '-'),
                    (string) ($row['source'] ?? '-'),
                    (string) ($row['connector_status'] ?? '-'),
                    (string) ($row['last_sync_at'] ?? '-'),
                    (string) ($row['reason'] ?? '-'),
                    (string) ($row['recommended_fix'] ?? '-'),
                ])->all(),
            );
        }

        if ((int) $summary['legacy_ignored_for_metaapi_signoff'] > 0 && (int) $summary['metaapi_issues'] === 0) {
            $this->newLine();
            $this->line('Legacy EA fallback anomalies are intentionally separated from MetaApi Phase 1 cloud-sync signoff.');
        }

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[report unavailable]');
        }

        return self::SUCCESS;
    }
}
