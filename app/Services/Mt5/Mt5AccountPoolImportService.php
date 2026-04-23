<?php

namespace App\Services\Mt5;

use App\Models\Mt5AccountPoolEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class Mt5AccountPoolImportService
{
    public function __construct(
        private readonly Mt5AccountPoolSpreadsheetParser $parser,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $path): array
    {
        return $this->parser->inspect($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function import(
        string $path,
        string $pool = Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
        ?string $batch = null,
        bool $dryRun = false,
    ): array {
        $pool = $this->normalizePool($pool);
        $inspection = $this->parser->inspect($path);
        $batch ??= $this->batchIdentifier((string) $inspection['file']);

        $report = [
            'path' => $inspection['path'],
            'file' => $inspection['file'],
            'sheet_name' => $inspection['sheet_name'],
            'header_row_number' => $inspection['header_row_number'],
            'column_map' => $inspection['column_map'],
            'pool' => $pool,
            'batch' => $batch,
            'dry_run' => $dryRun,
            'rows_seen' => count($inspection['rows']),
            'imported' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'skipped_reasons' => [],
            'skipped_rows' => [],
        ];

        $seenRows = [];

        foreach ($inspection['rows'] as $row) {
            $values = $row['values'];
            $validationErrors = $this->validationErrors($values);

            if ($validationErrors !== []) {
                $this->recordSkip($report, $row['row_number'], implode(', ', $validationErrors));

                continue;
            }

            $login = (string) $values['login'];
            $server = (string) $values['server'];
            $duplicateKey = Str::lower($login.'|'.$server);

            if (isset($seenRows[$duplicateKey])) {
                $this->recordSkip($report, $row['row_number'], 'duplicate_in_file');
                $report['duplicates']++;

                continue;
            }

            $seenRows[$duplicateKey] = true;

            /** @var Mt5AccountPoolEntry|null $existing */
            $existing = Mt5AccountPoolEntry::query()
                ->where('login', $login)
                ->where('server', $server)
                ->first();

            if ($existing instanceof Mt5AccountPoolEntry) {
                $reason = $existing->source_pool === $pool
                    ? 'duplicate_existing'
                    : 'duplicate_existing_other_pool';

                $this->recordSkip($report, $row['row_number'], $reason);
                $report['duplicates']++;

                continue;
            }

            if ($dryRun) {
                $report['imported']++;

                continue;
            }

            Mt5AccountPoolEntry::query()->create([
                'login' => $login,
                'password' => (string) $values['password'],
                'investor_password' => filled($values['investor_password'] ?? null) ? (string) $values['investor_password'] : null,
                'server' => $server,
                'account_size' => $this->parseAccountSize((string) $values['account_size']),
                'currency_code' => $this->currencyCode($values['currency_symbol'] ?? null),
                'source_status' => $this->normalizeStatus($values['source_status'] ?? 'available'),
                'source_file' => (string) $inspection['file'],
                'source_batch' => $batch,
                'source_pool' => $pool,
                'source_created_at' => $this->sourceCreatedAt(
                    rowValue: $values['source_created_at'] ?? null,
                    metadataValue: $inspection['metadata']['created_at'] ?? null,
                ),
                'is_available' => $this->isAvailable($values['source_status'] ?? 'available'),
                'meta' => array_filter([
                    'worksheet' => $inspection['sheet_name'],
                    'row_number' => $row['row_number'],
                    'header_row_number' => $inspection['header_row_number'],
                    'currency_symbol' => $values['currency_symbol'] ?? null,
                    'source_created_at_raw' => $values['source_created_at'] ?? ($inspection['metadata']['created_at'] ?? null),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ]);

            $report['imported']++;
        }

        return $report;
    }

    private function normalizePool(string $pool): string
    {
        $normalized = Str::lower(trim($pool));

        if (! in_array($normalized, [
            Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            Mt5AccountPoolEntry::SOURCE_POOL_INTERNAL,
        ], true)) {
            throw new RuntimeException(sprintf('Unsupported MT5 pool type: %s', $pool));
        }

        return $normalized;
    }

    private function batchIdentifier(string $file): string
    {
        $name = Str::slug(pathinfo($file, PATHINFO_FILENAME));

        return trim($name.'-'.now()->format('YmdHis'), '-');
    }

    /**
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private function validationErrors(array $values): array
    {
        $errors = [];

        if (! filled($values['login'] ?? null)) {
            $errors[] = 'missing_login';
        }

        if (! filled($values['password'] ?? null)) {
            $errors[] = 'missing_password';
        }

        if (! filled($values['server'] ?? null)) {
            $errors[] = 'missing_server';
        }

        $accountSize = $values['account_size'] ?? null;

        if (! filled($accountSize)) {
            $errors[] = 'missing_account_size';
        } elseif ($this->parseAccountSize((string) $accountSize) <= 0) {
            $errors[] = 'invalid_account_size';
        }

        return $errors;
    }

    private function parseAccountSize(string $value): int
    {
        $normalized = preg_replace('/[^0-9]/', '', $value);

        return max((int) $normalized, 0);
    }

    private function currencyCode(?string $value): ?string
    {
        $normalized = Str::upper(trim((string) $value));

        return match ($normalized) {
            '$', 'USD' => 'USD',
            '€', 'EUR' => 'EUR',
            '£', 'GBP' => 'GBP',
            '' => null,
            default => strlen($normalized) === 3 ? $normalized : null,
        };
    }

    private function normalizeStatus(?string $value): string
    {
        $normalized = Str::lower(trim((string) $value));

        return $normalized !== '' ? $normalized : 'available';
    }

    private function isAvailable(?string $value): bool
    {
        $normalized = $this->normalizeStatus($value);

        return in_array($normalized, ['available', 'ready'], true);
    }

    private function sourceCreatedAt(?string $rowValue, ?string $metadataValue): ?Carbon
    {
        foreach (array_filter([$rowValue, $metadataValue]) as $value) {
            foreach (['d.m.y', 'd.m.Y', 'Y-m-d'] as $format) {
                try {
                    return Carbon::createFromFormat($format, trim((string) $value))->startOfDay();
                } catch (\Throwable) {
                    continue;
                }
            }

            try {
                return Carbon::parse((string) $value)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function recordSkip(array &$report, int $rowNumber, string $reason): void
    {
        $report['skipped']++;
        $report['skipped_reasons'][$reason] = ($report['skipped_reasons'][$reason] ?? 0) + 1;
        $report['skipped_rows'][] = [
            'row_number' => $rowNumber,
            'reason' => $reason,
        ];
    }
}
