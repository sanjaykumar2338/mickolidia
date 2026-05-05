<?php

namespace App\Services\Mt5;

use App\Models\Mt5AccountPoolEntry;
use App\Models\Mt5PromoCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
        array $options = [],
    ): array {
        $pool = $this->normalizePool($pool);
        $inspection = $this->parser->inspect($path);
        $batch ??= $this->batchIdentifier((string) $inspection['file']);
        $broker = trim((string) ($options['broker'] ?? ''));
        $platform = trim((string) ($options['platform'] ?? ''));
        $updateExisting = (bool) ($options['update_existing'] ?? false);
        $requireInvestorPassword = (bool) ($options['require_investor_password'] ?? false);
        $deactivateOtherClientEntries = (bool) ($options['deactivate_other_client_entries'] ?? false);

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
            'created' => 0,
            'updated' => 0,
            'invalid' => 0,
            'deactivated_old_entries' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'promo_accounts' => 0,
            'promo_codes_created' => 0,
            'promo_codes_existing' => 0,
            'promo_codes' => [],
            'skipped_reasons' => [],
            'skipped_rows' => [],
        ];

        $seenRows = [];
        $currentKeys = [];

        foreach ($inspection['rows'] as $row) {
            $values = $row['values'];
            $validationErrors = $this->validationErrors($values, $requireInvestorPassword);

            if ($validationErrors !== []) {
                $this->recordSkip($report, $row['row_number'], implode(', ', $validationErrors));
                $report['invalid']++;

                continue;
            }

            $login = (string) $values['login'];
            $server = (string) $values['server'];
            $isPromo = $this->isPromoRow($values);
            $duplicateKey = Str::lower($login.'|'.$server);
            $currentKeys[$duplicateKey] = true;

            if ($isPromo) {
                $report['promo_accounts']++;
            }

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
                if (
                    $updateExisting
                    && $existing->source_pool === $pool
                    && $existing->allocated_at === null
                    && $existing->allocated_trading_account_id === null
                ) {
                    if (! $dryRun) {
                        $this->updateExistingEntry($existing, $this->entryAttributes(
                            values: $values,
                            inspection: $inspection,
                            rowNumber: $row['row_number'],
                            pool: $pool,
                            batch: $batch,
                            broker: $broker,
                            platform: $platform,
                        ));

                        $existing = $existing->fresh() ?? $existing;

                        $this->ensurePromoCode($existing, $report, $dryRun);
                    }

                    $report['updated']++;
                    $report['imported']++;

                    continue;
                }

                $reason = match (true) {
                    $existing->source_pool !== $pool => 'duplicate_existing_other_pool',
                    $existing->allocated_at !== null || $existing->allocated_trading_account_id !== null => 'assigned_existing_not_overwritten',
                    default => 'duplicate_existing',
                };

                $this->recordSkip($report, $row['row_number'], $reason);
                $report['duplicates']++;

                continue;
            }

            if ($dryRun) {
                $report['promo_codes'][] = $isPromo
                    ? [
                        'code' => 'WFXGIVE-'.$login,
                        'login' => $login,
                        'status' => 'dry_run',
                    ]
                    : null;
                $report['promo_codes'] = array_values(array_filter($report['promo_codes']));
                $report['created']++;
                $report['imported']++;

                continue;
            }

            $entry = Mt5AccountPoolEntry::query()->create($this->entryAttributes(
                values: $values,
                inspection: $inspection,
                rowNumber: $row['row_number'],
                pool: $pool,
                batch: $batch,
                broker: $broker,
                platform: $platform,
            ));
            $this->ensurePromoCode($entry, $report, $dryRun);

            $report['created']++;
            $report['imported']++;
        }

        if ($deactivateOtherClientEntries) {
            $report['deactivated_old_entries'] = $this->deactivateOldClientEntries(
                currentKeys: $currentKeys,
                currentFile: (string) $inspection['file'],
                batch: $batch,
                broker: $broker,
                dryRun: $dryRun,
            );
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
    private function validationErrors(array $values, bool $requireInvestorPassword = false): array
    {
        $errors = [];

        if (! filled($values['login'] ?? null)) {
            $errors[] = 'missing_login';
        }

        if (! filled($values['password'] ?? null)) {
            $errors[] = 'missing_password';
        }

        if ($requireInvestorPassword && ! filled($values['investor_password'] ?? null)) {
            $errors[] = 'missing_investor_password';
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

    /**
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $inspection
     * @return array<string, mixed>
     */
    private function entryAttributes(
        array $values,
        array $inspection,
        int $rowNumber,
        string $pool,
        string $batch,
        string $broker = '',
        string $platform = '',
    ): array {
        return [
            'login' => (string) $values['login'],
            'password' => (string) $values['password'],
            'investor_password' => filled($values['investor_password'] ?? null) ? (string) $values['investor_password'] : null,
            'server' => (string) $values['server'],
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
            'is_promo' => $this->isPromoRow($values),
            'is_available' => ! $this->isPromoRow($values) && $this->isAvailable($values['source_status'] ?? 'available'),
            'meta' => array_filter([
                'broker' => $broker !== '' ? $broker : null,
                'provider' => $broker !== '' ? $broker : null,
                'platform' => $platform !== '' ? $platform : null,
                'worksheet' => $inspection['sheet_name'],
                'row_number' => $rowNumber,
                'header_row_number' => $inspection['header_row_number'],
                'currency_symbol' => $values['currency_symbol'] ?? null,
                'source_created_at_raw' => $values['source_created_at'] ?? ($inspection['metadata']['created_at'] ?? null),
                'promo_marker' => $values['promo_marker'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateExistingEntry(Mt5AccountPoolEntry $entry, array $attributes): void
    {
        $updates = $attributes;
        $updates['password'] = Crypt::encryptString((string) $attributes['password']);
        $updates['investor_password'] = filled($attributes['investor_password'] ?? null)
            ? Crypt::encryptString((string) $attributes['investor_password'])
            : null;
        $updates['meta'] = json_encode($attributes['meta'] ?? [], JSON_THROW_ON_ERROR);
        $updates['updated_at'] = now();

        DB::table($entry->getTable())
            ->where('id', $entry->id)
            ->update($updates);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function isPromoRow(array $values): bool
    {
        return Str::lower(trim((string) ($values['promo_marker'] ?? ''))) === 'promo';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function ensurePromoCode(Mt5AccountPoolEntry $entry, array &$report, bool $dryRun): void
    {
        if (! $entry->is_promo) {
            return;
        }

        $code = 'WFXGIVE-'.$entry->login;

        if ($dryRun) {
            $report['promo_codes'][] = [
                'code' => $code,
                'login' => $entry->login,
                'status' => 'dry_run',
            ];

            return;
        }

        $promoCode = Mt5PromoCode::query()->firstOrCreate(
            ['mt5_account_pool_entry_id' => $entry->id],
            [
                'code' => $code,
                'mt5_login' => $entry->login,
                'meta' => [
                    'source_file' => $entry->source_file,
                    'source_batch' => $entry->source_batch,
                ],
            ],
        );

        if ($promoCode->wasRecentlyCreated) {
            $report['promo_codes_created']++;
        } else {
            $report['promo_codes_existing']++;
        }

        $report['promo_codes'][] = [
            'code' => $promoCode->code,
            'login' => $entry->login,
            'status' => $promoCode->used_at === null ? 'unused' : 'used',
        ];
    }

    /**
     * @param  array<string, bool>  $currentKeys
     */
    private function deactivateOldClientEntries(array $currentKeys, string $currentFile, string $batch, string $broker, bool $dryRun): int
    {
        $count = 0;

        Mt5AccountPoolEntry::query()
            ->where('source_pool', Mt5AccountPoolEntry::SOURCE_POOL_CLIENT)
            ->where('is_available', true)
            ->whereNull('allocated_at')
            ->whereNull('allocated_trading_account_id')
            ->orderBy('id')
            ->get()
            ->each(function (Mt5AccountPoolEntry $entry) use (&$count, $currentKeys, $currentFile, $batch, $broker, $dryRun): void {
                $key = Str::lower($entry->login.'|'.$entry->server);
                $isCurrentFile = $entry->source_file === $currentFile;
                $isCurrentBroker = $broker === '' || (string) data_get($entry->meta, 'broker') === $broker;

                if ($isCurrentFile && $isCurrentBroker && isset($currentKeys[$key])) {
                    return;
                }

                $count++;

                if ($dryRun) {
                    return;
                }

                $meta = is_array($entry->meta) ? $entry->meta : [];
                $meta['deprecated_by'] = $broker !== '' ? $broker : $currentFile;
                $meta['deprecated_by_batch'] = $batch;
                $meta['deprecated_at'] = now()->toIso8601String();

                $entry->forceFill([
                    'source_status' => 'deprecated',
                    'is_available' => false,
                    'meta' => $meta,
                ])->save();
            });

        return $count;
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
