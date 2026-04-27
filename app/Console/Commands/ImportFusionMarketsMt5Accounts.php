<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Services\Mt5\Mt5AccountPoolImportService;
use Illuminate\Console\Command;

class ImportFusionMarketsMt5Accounts extends Command
{
    protected $signature = 'mt5:import-fusionmarkets
        {path? : Relative or absolute path to the FusionMarkets ODS file}
        {--batch= : Optional source batch identifier}
        {--dry-run : Parse and validate without writing to the database}';

    protected $description = 'Import the active FusionMarkets MT5 account pool from an ODS spreadsheet.';

    public function handle(Mt5AccountPoolImportService $importService): int
    {
        $path = (string) ($this->argument('path') ?: config('wolforix.mt5_account_pool.fusionmarkets.source'));
        $batch = $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        $report = $importService->import(
            path: $path,
            pool: Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            batch: is_string($batch) ? $batch : null,
            dryRun: $dryRun,
            options: [
                'broker' => (string) config('wolforix.mt5_account_pool.fusionmarkets.broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS),
                'platform' => (string) config('wolforix.mt5_account_pool.fusionmarkets.platform', Mt5AccountPoolEntry::PLATFORM_MT5),
                'update_existing' => true,
                'require_investor_password' => true,
                'deactivate_other_client_entries' => true,
            ],
        );

        $this->info(sprintf(
            '%s FusionMarkets MT5 import processed on worksheet [%s].',
            $dryRun ? 'Dry-run' : 'Live',
            $report['sheet_name'],
        ));

        $this->table(
            ['Metric', 'Value'],
            [
                ['File', $report['file']],
                ['Header row', (string) $report['header_row_number']],
                ['Rows seen', (string) $report['rows_seen']],
                ['Created', (string) $report['created']],
                ['Updated', (string) $report['updated']],
                ['Invalid rows', (string) $report['invalid']],
                ['Duplicates/skipped existing', (string) $report['duplicates']],
                ['Skipped', (string) $report['skipped']],
                ['Old unassigned entries disabled', (string) $report['deactivated_old_entries']],
                ['Batch', $report['batch']],
            ],
        );

        $this->table(
            ['Column', 'Header', 'Mapped field'],
            array_map(static fn (array $column): array => [
                $column['letter'],
                $column['label'] !== '' ? $column['label'] : '(blank)',
                $column['field'] ?? '(ignored)',
            ], $report['column_map']),
        );

        if ($report['skipped_reasons'] !== []) {
            $this->table(
                ['Reason', 'Count'],
                collect($report['skipped_reasons'])
                    ->map(fn (int $count, string $reason): array => [$reason, (string) $count])
                    ->values()
                    ->all(),
            );
        }

        return self::SUCCESS;
    }
}
