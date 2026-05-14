<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\Mt5PromoCode;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepairMt5PoolCredentials extends Command
{
    private const ALLOWED_LOGIN = '335405';

    private const EXPECTED_PROMO_CODE = 'WFXGIVE-335405';

    protected $signature = 'wolforix:repair-mt5-pool-credentials
        {login : MT5 login to repair. This command only allows 335405}
        {--password= : MT5 trading/master password to re-encrypt}
        {--investor-password= : MT5 investor/read-only password to re-encrypt}
        {--dry-run : Inspect and log what would change without writing}';

    protected $description = 'Safely re-encrypt credentials for the WFXGIVE-335405 MT5 pool entry.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $password = (string) $this->option('password');
        $investorPassword = (string) $this->option('investor-password');
        $dryRun = (bool) $this->option('dry-run');

        if ($login !== self::ALLOWED_LOGIN) {
            $this->error('Refusing to run: this repair command only targets MT5 login '.self::ALLOWED_LOGIN.'.');

            return self::FAILURE;
        }

        if ($password === '' || $investorPassword === '') {
            $this->error('Both --password and --investor-password are required.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run MT5 pool credential repair' : 'Live MT5 pool credential repair');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->newLine();

        try {
            return DB::transaction(function () use ($login, $password, $investorPassword, $dryRun): int {
                $entries = Mt5AccountPoolEntry::query()
                    ->where('login', $login)
                    ->lockForUpdate()
                    ->get();

                if ($entries->count() !== 1) {
                    $this->error('Expected exactly one MT5 pool entry for login '.$login.', found '.$entries->count().'. No changes made.');

                    return self::FAILURE;
                }

                /** @var Mt5AccountPoolEntry $entry */
                $entry = $entries->first();
                $promoCode = Mt5PromoCode::query()
                    ->where('mt5_account_pool_entry_id', $entry->id)
                    ->first();

                if (! $this->guardTargetRow($entry, $promoCode)) {
                    return self::FAILURE;
                }

                $beforeState = $this->credentialState($entry);
                $proposedAfterState = [
                    'password' => 'decryptable_present',
                    'investor_password' => 'decryptable_present',
                ];

                $this->printTargetSummary($entry, $promoCode, $beforeState, $dryRun ? $proposedAfterState : null);

                Log::info('MT5 pool credential repair inspected target row.', [
                    'dry_run' => $dryRun,
                    'mt5_account_pool_entry_id' => $entry->id,
                    'login' => $entry->login,
                    'promo_code_id' => $promoCode?->id,
                    'promo_code' => $promoCode?->code,
                    'before_state' => $beforeState,
                    'proposed_after_state' => $proposedAfterState,
                    'will_change_only' => ['password', 'investor_password'],
                ]);

                if ($dryRun) {
                    $this->warn('Dry run only. No credentials were written.');

                    return self::SUCCESS;
                }

                $entry->forceFill([
                    'password' => $password,
                    'investor_password' => $investorPassword,
                ])->save();

                /** @var Mt5AccountPoolEntry $reloadedEntry */
                $reloadedEntry = Mt5AccountPoolEntry::query()->findOrFail($entry->id);
                $afterState = $this->credentialState($reloadedEntry);

                $this->table(['Credential field', 'Before', 'After'], [
                    ['password', $beforeState['password'], $afterState['password']],
                    ['investor_password', $beforeState['investor_password'], $afterState['investor_password']],
                ]);

                Log::info('MT5 pool credentials re-encrypted for giveaway promo.', [
                    'mt5_account_pool_entry_id' => $reloadedEntry->id,
                    'login' => $reloadedEntry->login,
                    'promo_code_id' => $promoCode?->id,
                    'promo_code' => $promoCode?->code,
                    'before_state' => $beforeState,
                    'after_state' => $afterState,
                    'changed_only' => ['password', 'investor_password'],
                ]);

                if ($afterState['password'] !== 'decryptable_present' || $afterState['investor_password'] !== 'decryptable_present') {
                    $this->error('Repair wrote the credentials, but one or both fields still are not decryptable.');

                    return self::FAILURE;
                }

                $this->info('Credentials repaired. Run diagnose-promo again; both credential states should be decryptable_present.');

                return self::SUCCESS;
            });
        } catch (QueryException $exception) {
            $databaseMessage = $exception->getPrevious()?->getMessage() ?: $exception->getMessage();

            $this->error('Database repair failed: '.$databaseMessage);
            $this->warn('No intentional promo status/allocation changes were made by this command.');

            return self::FAILURE;
        }
    }

    private function guardTargetRow(Mt5AccountPoolEntry $entry, ?Mt5PromoCode $promoCode): bool
    {
        if ($entry->login !== self::ALLOWED_LOGIN) {
            $this->error('Refusing to run: locked row login did not match '.self::ALLOWED_LOGIN.'.');

            return false;
        }

        if (! $entry->is_promo) {
            $this->error('Refusing to run: linked pool entry is not marked as promo.');

            return false;
        }

        if ((int) $entry->account_size !== 10000) {
            $this->error('Refusing to run: linked pool entry is not a $10K account.');

            return false;
        }

        if ($entry->allocated_at !== null || $entry->allocated_trading_account_id !== null || $entry->allocated_user_id !== null) {
            $this->error('Refusing to run: linked pool entry is already allocated.');

            return false;
        }

        if (! $promoCode instanceof Mt5PromoCode) {
            $this->error('Refusing to run: no promo code is linked to this pool entry.');

            return false;
        }

        if ($promoCode->code !== self::EXPECTED_PROMO_CODE) {
            $this->error('Refusing to run: linked promo code is '.$promoCode->code.', expected '.self::EXPECTED_PROMO_CODE.'.');

            return false;
        }

        if ($promoCode->used_at !== null || $promoCode->used_order_id !== null || $promoCode->used_trading_account_id !== null) {
            $this->error('Refusing to run: promo code has already been used.');

            return false;
        }

        return true;
    }

    /**
     * @param  array{password: string, investor_password: string}  $beforeState
     * @param  array{password: string, investor_password: string}|null  $afterState
     */
    private function printTargetSummary(Mt5AccountPoolEntry $entry, ?Mt5PromoCode $promoCode, array $beforeState, ?array $afterState): void
    {
        $this->table(['Field', 'Value'], [
            ['pool_entry_id', (string) $entry->id],
            ['login', $entry->login],
            ['server', $entry->server],
            ['account_size', (string) $entry->account_size],
            ['is_promo', $entry->is_promo ? 'yes' : 'no'],
            ['allocated_at', $this->formatValue($entry->allocated_at)],
            ['allocated_user_id', $this->formatValue($entry->allocated_user_id)],
            ['allocated_trading_account_id', $this->formatValue($entry->allocated_trading_account_id)],
            ['promo_code_id', $this->formatValue($promoCode?->id)],
            ['promo_code', $this->formatValue($promoCode?->code)],
            ['promo_used_at', $this->formatValue($promoCode?->used_at)],
            ['password_state_before', $beforeState['password']],
            ['investor_password_state_before', $beforeState['investor_password']],
            ['password_state_after', $afterState['password'] ?? '(pending write)'],
            ['investor_password_state_after', $afterState['investor_password'] ?? '(pending write)'],
        ]);
    }

    /**
     * @return array{password: string, investor_password: string}
     */
    private function credentialState(Mt5AccountPoolEntry $entry): array
    {
        return [
            'password' => $this->credentialFieldState($entry, 'password'),
            'investor_password' => $this->credentialFieldState($entry, 'investor_password'),
        ];
    }

    private function credentialFieldState(Mt5AccountPoolEntry $entry, string $field): string
    {
        try {
            $value = $entry->{$field};

            return filled($value) ? 'decryptable_present' : 'empty';
        } catch (DecryptException) {
            $rawValue = (string) $entry->getRawOriginal($field);

            if (! filled($rawValue)) {
                return 'empty';
            }

            if ($entry->is_promo && ! $this->looksLikeEncryptedCastPayload($rawValue)) {
                return 'legacy_plaintext_present';
            }

            return 'decrypt_failed';
        }
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

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
