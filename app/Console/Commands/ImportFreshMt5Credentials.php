<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportFreshMt5Credentials extends Command
{
    /** @var list<string> */
    private const REQUIRED_COLUMNS = ['login', 'server', 'password', 'investor_password', 'account_size', 'broker'];

    protected $signature = 'wolforix:import-fresh-mt5-credentials
        {--file= : CSV file path. Example: storage/app/mt5_fresh_accounts.csv}
        {--confirm : Apply the import. Without this option the command only prints a dry-run plan.}
        {--show-secret : Print imported passwords instead of masked values}';

    protected $description = 'Dry-run first CSV import for fresh MT5 demo credentials encrypted with the current APP_KEY.';

    public function handle(): int
    {
        $file = trim((string) $this->option('file'));
        $confirmed = (bool) $this->option('confirm');
        $showSecret = (bool) $this->option('show-secret');

        if ($file === '') {
            $this->error('The --file option is required.');

            return self::FAILURE;
        }

        $path = $this->resolvePath($file);

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('CSV file was not found or is not readable: '.$file);

            return self::FAILURE;
        }

        $this->info(($confirmed ? 'CONFIRMED' : 'DRY RUN').' fresh MT5 credential import');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line('File: '.$path);
        $this->line('No trades or snapshots are touched by this command.');
        $this->newLine();

        if (! $confirmed) {
            $this->warn('Dry run only. Re-run with --confirm to encrypt and save these credentials.');
        } else {
            $this->warn('Applying import with the current APP_KEY. Passwords are not logged.');
        }

        if ($showSecret) {
            $this->warn('Sensitive credentials may be printed to this terminal because --show-secret was passed.');
        }

        try {
            $rows = $this->readCsv($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $plans = $this->buildPlans($rows, basename($path), $showSecret);
        $this->printVerificationTable($plans, $showSecret, applied: false);

        if (! $confirmed) {
            return $this->hasImportableRows($plans) ? self::SUCCESS : self::FAILURE;
        }

        $appliedPlans = DB::transaction(fn (): array => $this->applyPlans($plans));
        $this->printVerificationTable($appliedPlans, $showSecret, applied: true);

        return $this->hasImportableRows($appliedPlans) ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR)) {
            return $file;
        }

        return base_path($file);
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        try {
            $headers = fgetcsv($handle);

            if (! is_array($headers)) {
                throw new RuntimeException('CSV file is empty.');
            }

            $headers = array_map(fn (string $header): string => Str::lower(trim($header)), $headers);
            $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));

            if ($missing !== []) {
                throw new RuntimeException('CSV is missing required columns: '.implode(', ', $missing));
            }

            $rows = [];
            $rowNumber = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($data === [null] || $data === false) {
                    continue;
                }

                $row = [];

                foreach ($headers as $index => $header) {
                    $row[$header] = trim((string) ($data[$index] ?? ''));
                }

                if (implode('', $row) === '') {
                    continue;
                }

                $row['_row_number'] = (string) $rowNumber;
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function buildPlans(array $rows, string $sourceFile, bool $showSecret): array
    {
        $plans = [];
        $seen = [];
        $batch = 'fresh-mt5-csv-'.now()->format('YmdHis');

        foreach ($rows as $row) {
            $login = $row['login'] ?? '';
            $server = $row['server'] ?? '';
            $key = Str::lower($login.'|'.$server);
            $errors = $this->validationErrors($row);

            if (isset($seen[$key])) {
                $errors[] = 'duplicate_in_file';
            }

            $seen[$key] = true;

            $existing = $login !== '' && $server !== ''
                ? Mt5AccountPoolEntry::query()->where('login', $login)->where('server', $server)->first()
                : null;

            if ($existing instanceof Mt5AccountPoolEntry && $this->isAllocated($existing)) {
                $errors[] = 'existing_entry_allocated';
            }

            $action = $existing instanceof Mt5AccountPoolEntry ? 'update_unallocated' : 'create';

            $plans[] = [
                'row_number' => (int) ($row['_row_number'] ?? 0),
                'action' => $errors === [] ? $action : 'skip',
                'status' => $errors === [] ? 'planned' : 'rejected',
                'errors' => $errors,
                'existing_entry_id' => $existing?->id,
                'attributes' => $errors === [] ? $this->attributesFor($row, $sourceFile, $batch) : [],
                'password' => (string) ($row['password'] ?? ''),
                'investor_password' => (string) ($row['investor_password'] ?? ''),
            ];
        }

        return $plans;
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function validationErrors(array $row): array
    {
        $errors = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! filled($row[$column] ?? null)) {
                $errors[] = 'missing_'.$column;
            }
        }

        if (filled($row['account_size'] ?? null) && $this->parseAccountSize((string) $row['account_size']) <= 0) {
            $errors[] = 'invalid_account_size';
        }

        if (filled($row['password'] ?? null) && $this->looksLikePlaceholder((string) $row['password'])) {
            $errors[] = 'placeholder_password';
        }

        if (filled($row['investor_password'] ?? null) && $this->looksLikePlaceholder((string) $row['investor_password'])) {
            $errors[] = 'inv_placeholder';
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function attributesFor(array $row, string $sourceFile, string $batch): array
    {
        return [
            'login' => (string) $row['login'],
            'server' => (string) $row['server'],
            'password' => (string) $row['password'],
            'investor_password' => (string) $row['investor_password'],
            'account_size' => $this->parseAccountSize((string) $row['account_size']),
            'currency_code' => 'USD',
            'source_status' => 'available',
            'source_file' => $sourceFile,
            'source_batch' => $batch,
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'source_created_at' => now()->toDateString(),
            'is_promo' => false,
            'is_available' => true,
            'meta' => [
                'broker' => (string) $row['broker'],
                'provider' => (string) $row['broker'],
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'imported_by' => 'wolforix:import-fresh-mt5-credentials',
                'imported_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @return list<array<string, mixed>>
     */
    private function applyPlans(array $plans): array
    {
        foreach ($plans as $index => $plan) {
            if ($plan['status'] !== 'planned') {
                continue;
            }

            $attributes = $plan['attributes'];
            $entry = $plan['existing_entry_id'] !== null
                ? Mt5AccountPoolEntry::query()->lockForUpdate()->findOrFail($plan['existing_entry_id'])
                : new Mt5AccountPoolEntry();

            if ($this->isAllocated($entry)) {
                $plans[$index]['status'] = 'rejected';
                $plans[$index]['errors'] = ['existing_entry_allocated_during_import'];

                continue;
            }

            $entry->forceFill($attributes)->save();
            $entry->refresh();

            $verification = $this->verifySavedEntry($entry, (string) $plan['password'], (string) $plan['investor_password']);
            $plans[$index]['status'] = $verification['ok'] ? 'saved' : 'verification_failed';
            $plans[$index]['saved_entry_id'] = $entry->id;
            $plans[$index]['verification'] = $verification;
        }

        return $plans;
    }

    /**
     * @return array{ok: bool, password_readable: bool, investor_password_readable: bool, raw_password_present: bool, raw_investor_password_present: bool}
     */
    private function verifySavedEntry(Mt5AccountPoolEntry $entry, string $password, string $investorPassword): array
    {
        $rawPassword = $entry->getRawOriginal('password');
        $rawInvestorPassword = $entry->getRawOriginal('investor_password');

        try {
            $passwordReadable = (string) $entry->password === $password
                && Crypt::decryptString((string) $rawPassword) === $password;
        } catch (DecryptException) {
            $passwordReadable = false;
        }

        try {
            $investorPasswordReadable = (string) $entry->investor_password === $investorPassword
                && Crypt::decryptString((string) $rawInvestorPassword) === $investorPassword;
        } catch (DecryptException) {
            $investorPasswordReadable = false;
        }

        return [
            'ok' => $passwordReadable && $investorPasswordReadable,
            'password_readable' => $passwordReadable,
            'investor_password_readable' => $investorPasswordReadable,
            'raw_password_present' => filled($rawPassword),
            'raw_investor_password_present' => filled($rawInvestorPassword),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function printVerificationTable(array $plans, bool $showSecret, bool $applied): void
    {
        $this->newLine();
        $this->info($applied ? 'Import verification table' : 'Import dry-run verification table');

        foreach ($plans as $plan) {
            $errors = (array) ($plan['errors'] ?? []);

            if ($errors !== []) {
                $this->line(sprintf('row %s errors: %s', (string) $plan['row_number'], implode(', ', $errors)));
            }

            if ($showSecret && in_array($plan['status'], ['planned', 'saved'], true)) {
                $this->line(sprintf(
                    'row %s secrets: password=%s investor_password=%s',
                    (string) $plan['row_number'],
                    (string) ($plan['password'] ?? ''),
                    (string) ($plan['investor_password'] ?? ''),
                ));
            }
        }

        $this->table(
            ['row', 'entry_id', 'login', 'server', 'broker', 'account_size', 'action', 'status', 'password', 'investor_password', 'raw_password', 'raw_investor', 'decrypt_ok', 'errors'],
            array_map(function (array $plan) use ($showSecret): array {
                $attributes = $plan['attributes'] ?? [];
                $verification = $plan['verification'] ?? [];

                return [
                    (string) $plan['row_number'],
                    (string) ($plan['saved_entry_id'] ?? $plan['existing_entry_id'] ?? '-'),
                    (string) ($attributes['login'] ?? '-'),
                    (string) ($attributes['server'] ?? '-'),
                    (string) data_get($attributes, 'meta.broker', '-'),
                    (string) ($attributes['account_size'] ?? '-'),
                    (string) $plan['action'],
                    (string) $plan['status'],
                    $this->secretDisplay((string) ($plan['password'] ?? ''), $showSecret),
                    $this->secretDisplay((string) ($plan['investor_password'] ?? ''), $showSecret),
                    array_key_exists('raw_password_present', $verification) ? $this->boolString((bool) $verification['raw_password_present']) : 'pending',
                    array_key_exists('raw_investor_password_present', $verification) ? $this->boolString((bool) $verification['raw_investor_password_present']) : 'pending',
                    array_key_exists('ok', $verification) ? $this->boolString((bool) $verification['ok']) : 'pending',
                    implode(', ', (array) ($plan['errors'] ?? [])) ?: '-',
                ];
            }, $plans),
        );
    }

    private function parseAccountSize(string $value): int
    {
        $normalized = preg_replace('/[^0-9]/', '', $value);

        return max((int) $normalized, 0);
    }

    private function isAllocated(Mt5AccountPoolEntry $entry): bool
    {
        return $entry->allocated_at !== null
            || $entry->allocated_trading_account_id !== null
            || $entry->allocated_user_id !== null;
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function hasImportableRows(array $plans): bool
    {
        return collect($plans)->contains(fn (array $plan): bool => in_array($plan['status'], ['planned', 'saved'], true));
    }

    private function looksLikePlaceholder(string $value): bool
    {
        $normalized = Str::lower(trim($value));

        if ($normalized === '') {
            return true;
        }

        if (in_array($normalized, [
            'password',
            'password123',
            'real_password',
            'real_investor_password',
            'test',
            'test123',
            'secret',
            'secret-pass',
            'investor password pending',
            'provided separately by wolforix support',
        ], true)) {
            return true;
        }

        return Str::contains($normalized, ['placeholder', 'dummy', 'sample', 'example', 'fake']);
    }

    private function secretDisplay(string $secret, bool $showSecret): string
    {
        if ($secret === '') {
            return '-';
        }

        if ($showSecret) {
            return $secret;
        }

        $length = strlen($secret);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max($length - 4, 0)).substr($secret, -4);
    }

    private function boolString(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
