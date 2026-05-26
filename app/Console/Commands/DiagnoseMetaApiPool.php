<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiagnoseMetaApiPool extends Command
{
    protected $signature = 'wolforix:diagnose-metaapi-pool
        {login : MT5 login to inspect}
        {--server= : Optional exact MT5 server to narrow the pool row}
        {--repair : Re-encrypt password and investor_password for one matching row}
        {--password= : MT5 trading/master password used only with --repair}
        {--investor-password= : MT5 investor/read-only password used only with --repair}
        {--dry-run : With --repair, show the target and proposed result without writing}';

    protected $description = 'Diagnose and optionally repair MetaApi MT5 pool encrypted credential payloads.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $server = trim((string) $this->option('server'));
        $repair = (bool) $this->option('repair');
        $dryRun = (bool) $this->option('dry-run');

        if ($login === '') {
            $this->error('A non-empty login is required.');

            return self::FAILURE;
        }

        $this->info('MetaApi MT5 pool credential diagnosis');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line('Secrets are never printed by this command.');
        $this->newLine();

        $entries = Mt5AccountPoolEntry::query()
            ->where('login', $login)
            ->when($server !== '', fn ($query) => $query->where('server', $server))
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            $this->warn('No mt5_account_pool_entries rows found for this login'.($server !== '' ? ' and server.' : '.'));

            return self::FAILURE;
        }

        $rows = $entries->map(function (Mt5AccountPoolEntry $entry): array {
            $integrity = $this->credentialIntegrity($entry);

            return [
                'id' => (string) $entry->id,
                'login' => $entry->login,
                'server' => $entry->server,
                'source_file' => $entry->source_file,
                'source_pool' => $entry->source_pool,
                'allocated' => $entry->allocated_at !== null || $entry->allocated_trading_account_id !== null ? 'yes' : 'no',
                'password' => (string) data_get($integrity, 'password.state'),
                'investor_password' => (string) data_get($integrity, 'investor_password.state'),
                'metaapi_account_id' => (string) (data_get($entry->meta, 'metaapi_account_id') ?: '-'),
            ];
        });

        $this->table([
            'id',
            'login',
            'server',
            'source_file',
            'source_pool',
            'allocated',
            'password',
            'investor_password',
            'metaapi_account_id',
        ], $rows->all());

        foreach ($entries as $entry) {
            $this->logIntegrity($entry, $this->credentialIntegrity($entry), 'diagnose_metaapi_pool_inspected');
        }

        if (! $repair) {
            return $rows->contains(fn (array $row): bool => $row['password'] === 'decrypt_failed' || $row['investor_password'] === 'decrypt_failed')
                ? self::FAILURE
                : self::SUCCESS;
        }

        if ($entries->count() !== 1) {
            $this->error('Repair requires exactly one matching row. Re-run with --server= to narrow the target.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        $investorPassword = (string) $this->option('investor-password');

        if ($password === '' || $investorPassword === '') {
            $this->error('Repair requires both --password and --investor-password. They are not logged or printed.');

            return self::FAILURE;
        }

        /** @var Mt5AccountPoolEntry $entry */
        $entry = $entries->first();
        $before = $this->credentialIntegrity($entry);

        if ($dryRun) {
            $this->warn('Dry run only. No credentials were written.');
            $this->table(['Column', 'Before', 'After'], [
                ['password', (string) data_get($before, 'password.state'), 'decryptable_present'],
                ['investor_password', (string) data_get($before, 'investor_password.state'), 'decryptable_present'],
            ]);

            return self::SUCCESS;
        }

        $meta = is_array($entry->meta) ? $entry->meta : [];
        $meta['metaapi_pool_repaired_at'] = now()->toIso8601String();
        $meta['metaapi_pool_repair_reason'] = 'wolforix_diagnose_metaapi_pool_manual_reencrypt';

        DB::table($entry->getTable())
            ->where('id', $entry->id)
            ->update([
                'password' => Crypt::encryptString($password),
                'investor_password' => Crypt::encryptString($investorPassword),
                'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

        /** @var Mt5AccountPoolEntry $reloaded */
        $reloaded = Mt5AccountPoolEntry::query()->findOrFail($entry->id);
        $after = $this->credentialIntegrity($reloaded);

        $this->table(['Column', 'Before', 'After'], [
            ['password', (string) data_get($before, 'password.state'), (string) data_get($after, 'password.state')],
            ['investor_password', (string) data_get($before, 'investor_password.state'), (string) data_get($after, 'investor_password.state')],
        ]);

        Log::info('MetaApi MT5 pool credentials re-encrypted.', [
            'mt5_account_pool_entry_id' => $reloaded->id,
            'login' => $reloaded->login,
            'server' => $reloaded->server,
            'before' => $this->sanitizedIntegrity($before),
            'after' => $this->sanitizedIntegrity($after),
        ]);

        return data_get($after, 'password.state') === 'decryptable_present'
            && data_get($after, 'investor_password.state') === 'decryptable_present'
                ? self::SUCCESS
                : self::FAILURE;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function credentialIntegrity(Mt5AccountPoolEntry $entry): array
    {
        return [
            'password' => $this->credentialColumnIntegrity($entry, 'password'),
            'investor_password' => $this->credentialColumnIntegrity($entry, 'investor_password'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function credentialColumnIntegrity(Mt5AccountPoolEntry $entry, string $column): array
    {
        $rawValue = $entry->getRawOriginal($column);
        $rawPresent = filled($rawValue);

        try {
            $value = $entry->{$column};

            return [
                'model' => Mt5AccountPoolEntry::class,
                'table' => $entry->getTable(),
                'id' => $entry->id,
                'column' => $column,
                'state' => filled($value) ? 'decryptable_present' : ($rawPresent ? 'decryptable_empty' : 'missing'),
                'raw_present' => $rawPresent,
                'encrypted_payload_shape' => $rawPresent ? $this->looksLikeEncryptedCastPayload((string) $rawValue) : false,
            ];
        } catch (DecryptException $exception) {
            return [
                'model' => Mt5AccountPoolEntry::class,
                'table' => $entry->getTable(),
                'id' => $entry->id,
                'column' => $column,
                'state' => $rawPresent ? 'decrypt_failed' : 'missing',
                'raw_present' => $rawPresent,
                'encrypted_payload_shape' => $rawPresent ? $this->looksLikeEncryptedCastPayload((string) $rawValue) : false,
                'exception' => $exception::class,
                'reason' => 'laravel_encrypted_cast_decrypt_failed',
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $integrity
     */
    private function logIntegrity(Mt5AccountPoolEntry $entry, array $integrity, string $action): void
    {
        Log::info('MetaApi MT5 pool credential integrity inspected.', [
            'action' => $action,
            'mt5_account_pool_entry_id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
            'integrity' => $this->sanitizedIntegrity($integrity),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $integrity
     * @return array<string, array<string, mixed>>
     */
    private function sanitizedIntegrity(array $integrity): array
    {
        return collect($integrity)
            ->map(fn (array $column): array => [
                'model' => $column['model'] ?? null,
                'table' => $column['table'] ?? null,
                'id' => $column['id'] ?? null,
                'column' => $column['column'] ?? null,
                'state' => $column['state'] ?? null,
                'raw_present' => $column['raw_present'] ?? null,
                'encrypted_payload_shape' => $column['encrypted_payload_shape'] ?? null,
                'exception' => $column['exception'] ?? null,
                'reason' => $column['reason'] ?? null,
            ])
            ->all();
    }

    private function looksLikeEncryptedCastPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && array_key_exists('iv', $payload)
            && array_key_exists('value', $payload)
            && array_key_exists('mac', $payload);
    }
}
