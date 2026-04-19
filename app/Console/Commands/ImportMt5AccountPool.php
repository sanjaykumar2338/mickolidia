<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Services\Mt5\Mt5AccountPoolImportService;
use Illuminate\Console\Command;

class ImportMt5AccountPool extends Command
{
    protected $signature = 'mt5:import-account-pool
        {path? : Relative or absolute path to the ODS file}
        {--pool= : Pool tag to apply (client_pool or internal_only)}
        {--batch= : Optional source batch identifier}
        {--dry-run : Parse and validate without writing to the database}';

    protected $description = 'Import manual MT5 account pool entries from an ODS spreadsheet.';

    public function handle(Mt5AccountPoolImportService $importService): int
    {
        $path = (string) ($this->argument('path') ?: config('wolforix.mt5_account_pool.default_client_source'));
        $pool = (string) ($this->option('pool') ?: config('wolforix.mt5_account_pool.default_pool', Mt5AccountPoolEntry::SOURCE_POOL_CLIENT));
        $batch = $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        $report = $importService->import($path, $pool, is_string($batch) ? $batch : null, $dryRun);

        $this->info(sprintf(
            '%s MT5 account pool import processed for [%s] on worksheet [%s].',
            $dryRun ? 'Dry-run' : 'Live',
            $report['pool'],
            $report['sheet_name'],
        ));

        $this->table(
            ['Metric', 'Value'],
            [
                ['File', $report['file']],
                ['Header row', (string) $report['header_row_number']],
                ['Rows seen', (string) $report['rows_seen']],
                ['Imported', (string) $report['imported']],
                ['Duplicates', (string) $report['duplicates']],
                ['Skipped', (string) $report['skipped']],
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
