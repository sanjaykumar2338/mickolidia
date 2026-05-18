<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiagnoseMt5Credentials extends Command
{
    private const ALLOWED_LOGIN = '335405';

    private const ALLOWED_ACCOUNT_REFERENCE = 'WFX-MT5-00057-8HN7';

    private const REAL_PASSWORD_MARKER = 'REAL_PASSWORD';

    private const REAL_INVESTOR_PASSWORD_MARKER = 'REAL_INVESTOR_PASSWORD';

    protected $signature = 'wolforix:diagnose-mt5-credentials
        {login : MT5 login. This command only allows 335405}
        {--account-reference= : Wolforix account reference. This command only allows WFX-MT5-00057-8HN7}
        {--show-secret : Print decrypted passwords instead of masked values}';

    protected $description = 'Read-only credential integrity diagnosis for the locked WFX-MT5-00057-8HN7 / 335405 MT5 target.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $accountReference = trim((string) $this->option('account-reference'));
        $showSecret = (bool) $this->option('show-secret');

        if ($login !== self::ALLOWED_LOGIN) {
            $this->error('Refusing to run: this diagnostic only allows MT5 login '.self::ALLOWED_LOGIN.'.');

            return self::FAILURE;
        }

        if ($accountReference !== self::ALLOWED_ACCOUNT_REFERENCE) {
            $this->error('Refusing to run: this diagnostic only allows account reference '.self::ALLOWED_ACCOUNT_REFERENCE.'.');

            return self::FAILURE;
        }

        $this->info('Read-only MT5 credential integrity diagnosis');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line('No writes are performed by this command.');
        $this->newLine();

        if ($showSecret) {
            $this->warn('Sensitive credentials shown in terminal only. They are not written to Laravel logs by this command.');
        } else {
            $this->warn('Secrets are masked. Re-run with --show-secret only in a secure terminal.');
        }

        $account = TradingAccount::query()
            ->with('user')
            ->where('account_reference', self::ALLOWED_ACCOUNT_REFERENCE)
            ->first();

        $poolEntry = Mt5AccountPoolEntry::query()
            ->where('login', self::ALLOWED_LOGIN)
            ->orderBy('id')
            ->first();

        $accountsUsingLogin = $this->accountsUsingLogin(self::ALLOWED_LOGIN);
        $linkedAccount = $this->linkedAccount($poolEntry, $accountsUsingLogin);

        if (! $account instanceof TradingAccount) {
            $this->error('Trading account was not found for account reference '.self::ALLOWED_ACCOUNT_REFERENCE.'.');
        }

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $this->error('MT5 pool entry was not found for login '.self::ALLOWED_LOGIN.'.');
        }

        $passwordResult = $poolEntry instanceof Mt5AccountPoolEntry
            ? $this->decryptRawCredential($poolEntry, 'password')
            : $this->missingCredentialResult();
        $investorPasswordResult = $poolEntry instanceof Mt5AccountPoolEntry
            ? $this->decryptRawCredential($poolEntry, 'investor_password')
            : $this->missingCredentialResult();

        $sameLoginAllocatedElsewhere = $poolEntry instanceof Mt5AccountPoolEntry
            && $poolEntry->allocated_trading_account_id !== null
            && (! $account instanceof TradingAccount || (int) $poolEntry->allocated_trading_account_id !== (int) $account->id);
        $sameLoginUsedByOtherTradingAccount = $account instanceof TradingAccount
            && $accountsUsingLogin->contains(fn (TradingAccount $candidate): bool => (int) $candidate->id !== (int) $account->id);
        $wrongTradingAccount = $sameLoginAllocatedElsewhere || $sameLoginUsedByOtherTradingAccount || (
            $linkedAccount instanceof TradingAccount
            && (string) $linkedAccount->account_reference !== self::ALLOWED_ACCOUNT_REFERENCE
        );

        $serverMismatchPossible = $this->serverMismatchPossible($account, $poolEntry);
        $placeholderPassword = $passwordResult['ok'] && $this->looksLikePlaceholder($passwordResult['value']);
        $placeholderInvestorPassword = $investorPasswordResult['ok'] && $this->looksLikePlaceholder($investorPasswordResult['value']);
        $placeholderCredentials = $placeholderPassword || $placeholderInvestorPassword;
        $decryptFailed = ! $passwordResult['ok'] || ! $investorPasswordResult['ok'];

        $this->printSummary(
            $account,
            $poolEntry,
            $linkedAccount,
            $passwordResult,
            $investorPasswordResult,
            $showSecret,
            $sameLoginAllocatedElsewhere || $sameLoginUsedByOtherTradingAccount,
            $placeholderPassword,
            $placeholderInvestorPassword,
            $serverMismatchPossible,
        );

        $this->printAccountsUsingLogin($accountsUsingLogin);
        $this->printLatestSyncLogs($account, $accountsUsingLogin);

        $this->newLine();
        $this->info('Final decision: '.$this->finalDecision(
            $decryptFailed,
            $wrongTradingAccount,
            $placeholderCredentials,
            $serverMismatchPossible,
        ));

        return $decryptFailed || ! ($account instanceof TradingAccount) || ! ($poolEntry instanceof Mt5AccountPoolEntry)
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return Collection<int, TradingAccount>
     */
    private function accountsUsingLogin(string $login): Collection
    {
        return TradingAccount::query()
            ->with('user')
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, TradingAccount>  $accountsUsingLogin
     */
    private function linkedAccount(?Mt5AccountPoolEntry $poolEntry, Collection $accountsUsingLogin): ?TradingAccount
    {
        if ($poolEntry instanceof Mt5AccountPoolEntry && $poolEntry->allocated_trading_account_id !== null) {
            $allocated = TradingAccount::query()
                ->with('user')
                ->find($poolEntry->allocated_trading_account_id);

            if ($allocated instanceof TradingAccount) {
                return $allocated;
            }
        }

        return $accountsUsingLogin->first();
    }

    /**
     * @param  array{ok: bool, value: string, state: string}  $passwordResult
     * @param  array{ok: bool, value: string, state: string}  $investorPasswordResult
     */
    private function printSummary(
        ?TradingAccount $account,
        ?Mt5AccountPoolEntry $poolEntry,
        ?TradingAccount $linkedAccount,
        array $passwordResult,
        array $investorPasswordResult,
        bool $showSecret,
        bool $sameLoginAllocatedElsewhere,
        bool $placeholderPassword,
        bool $placeholderInvestorPassword,
        bool $serverMismatchPossible,
    ): void {
        $this->newLine();
        $this->info('Credential and mapping summary');
        $this->table(['Field', 'Value'], [
            ['trading_account_id', $account instanceof TradingAccount ? (string) $account->id : '-'],
            ['user email', $account instanceof TradingAccount ? (string) ($account->user?->email ?? '-') : '-'],
            ['account_reference', $account instanceof TradingAccount ? (string) $account->account_reference : '-'],
            ['platform_login', $account instanceof TradingAccount ? (string) ($account->platform_login ?: '-') : '-'],
            ['platform_account_id', $account instanceof TradingAccount ? (string) ($account->platform_account_id ?: '-') : '-'],
            ['server', $this->serverValue($account, $poolEntry)],
            ['broker', $this->brokerValue($account, $poolEntry)],
            ['source pool entry id', $poolEntry instanceof Mt5AccountPoolEntry ? (string) $poolEntry->id : '-'],
            ['decrypted password', $this->credentialDisplay($passwordResult, $showSecret)],
            ['decrypted investor password', $this->credentialDisplay($investorPasswordResult, $showSecret)],
            ['password equals REAL_PASSWORD', $this->boolString($passwordResult['ok'] && hash_equals(self::REAL_PASSWORD_MARKER, $passwordResult['value']))],
            ['investor password equals REAL_INVESTOR_PASSWORD', $this->boolString($investorPasswordResult['ok'] && hash_equals(self::REAL_INVESTOR_PASSWORD_MARKER, $investorPasswordResult['value']))],
            ['password looks like placeholder/test value', $this->boolString($placeholderPassword)],
            ['investor password looks like placeholder/test value', $this->boolString($placeholderInvestorPassword)],
            ['same login allocated to another trading account', $this->boolString($sameLoginAllocatedElsewhere)],
            ['server mismatch possible', $this->boolString($serverMismatchPossible)],
            ['actual account reference linked to login 335405', $linkedAccount instanceof TradingAccount ? (string) ($linkedAccount->account_reference ?: '-') : '-'],
        ]);
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     */
    private function printAccountsUsingLogin(Collection $accounts): void
    {
        $this->newLine();
        $this->info('All trading_accounts using platform_login/account_id '.self::ALLOWED_LOGIN);

        if ($accounts->isEmpty()) {
            $this->line('No trading account rows currently use login/account id '.self::ALLOWED_LOGIN.'.');

            return;
        }

        $this->table(
            ['id', 'user_email', 'account_reference', 'platform_login', 'platform_account_id', 'server', 'status', 'sync_status', 'last_synced_at'],
            $accounts->map(fn (TradingAccount $account): array => [
                (string) $account->id,
                (string) ($account->user?->email ?? '-'),
                (string) ($account->account_reference ?: '-'),
                (string) ($account->platform_login ?: '-'),
                (string) ($account->platform_account_id ?: '-'),
                (string) ($account->platform_environment ?: data_get($account->meta, 'mt5_sync.server', '-')),
                (string) ($account->account_status ?: $account->status ?: '-'),
                (string) ($account->sync_status ?: '-'),
                $this->formatValue($account->last_synced_at),
            ])->all(),
        );
    }

    /**
     * @param  Collection<int, TradingAccount>  $accountsUsingLogin
     */
    private function printLatestSyncLogs(?TradingAccount $account, Collection $accountsUsingLogin): void
    {
        $accountIds = $accountsUsingLogin->pluck('id');

        if ($account instanceof TradingAccount) {
            $accountIds->push($account->id);
        }

        $accountIds = $accountIds->filter()->unique()->values();

        $this->newLine();
        $this->info('Latest sync logs for this account showing auth success/failure');

        if ($accountIds->isEmpty()) {
            $this->line('No trading account ids available for sync log lookup.');

            return;
        }

        $logs = TradingAccountSyncLog::query()
            ->whereIn('trading_account_id', $accountIds->all())
            ->where(function ($query): void {
                $query->where('platform', 'mt5')
                    ->orWhere('platform', 'MT5')
                    ->orWhere('message', 'like', '%auth%')
                    ->orWhere('error_message', 'like', '%auth%');
            })
            ->latest('id')
            ->limit(8)
            ->get();

        if ($logs->isEmpty()) {
            $this->line('No MT5 sync log rows found for this account/login.');

            return;
        }

        $this->table(
            ['id', 'trading_account_id', 'status', 'auth_result', 'message', 'error_message', 'payload_login', 'payload_account_id', 'payload_server', 'started_at', 'completed_at'],
            $logs->map(function (TradingAccountSyncLog $log): array {
                $payload = is_array($log->payload) ? $log->payload : [];

                return [
                    (string) $log->id,
                    (string) ($log->trading_account_id ?: '-'),
                    (string) ($log->status ?: '-'),
                    $this->authResult($log, $payload),
                    Str::limit((string) ($log->message ?: '-'), 120),
                    Str::limit((string) ($log->error_message ?: '-'), 120),
                    (string) data_get($payload, 'platform_login', '-'),
                    (string) data_get($payload, 'platform_account_id', '-'),
                    (string) (data_get($payload, 'server') ?? data_get($payload, 'platform_environment') ?? '-'),
                    $this->formatValue($log->started_at),
                    $this->formatValue($log->completed_at),
                ];
            })->all(),
        );
    }

    /**
     * @return array{ok: bool, value: string, state: string}
     */
    private function decryptRawCredential(Mt5AccountPoolEntry $poolEntry, string $field): array
    {
        $rawValue = (string) $poolEntry->getRawOriginal($field);

        if ($rawValue === '') {
            return [
                'ok' => false,
                'value' => '',
                'state' => 'missing',
            ];
        }

        try {
            $decrypted = Crypt::decryptString($rawValue);

            return [
                'ok' => $decrypted !== '',
                'value' => $decrypted,
                'state' => $decrypted !== '' ? 'decryptable_present' : 'empty_after_decrypt',
            ];
        } catch (DecryptException $exception) {
            return [
                'ok' => false,
                'value' => '',
                'state' => 'decrypt_failed: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, value: string, state: string}
     */
    private function missingCredentialResult(): array
    {
        return [
            'ok' => false,
            'value' => '',
            'state' => 'pool_entry_missing',
        ];
    }

    /**
     * @param  array{ok: bool, value: string, state: string}  $result
     */
    private function credentialDisplay(array $result, bool $showSecret): string
    {
        if (! $result['ok']) {
            return $result['state'];
        }

        return $showSecret ? $result['value'] : $this->maskSecret($result['value']);
    }

    private function serverMismatchPossible(?TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry): bool
    {
        if (! $account instanceof TradingAccount || ! $poolEntry instanceof Mt5AccountPoolEntry) {
            return false;
        }

        $accountServers = array_values(array_filter(array_map('strval', [
            $account->platform_environment,
            data_get($account->meta, 'mt5_sync.server'),
            data_get($account->meta, 'credentials.server'),
            data_get($account->meta, 'credentials.mt5_server'),
            data_get($account->meta, 'mt5_server'),
        ])));

        if ($accountServers === []) {
            return false;
        }

        $poolServer = (string) $poolEntry->server;

        return $poolServer !== '' && ! in_array($poolServer, $accountServers, true);
    }

    private function serverValue(?TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry): string
    {
        $values = array_filter([
            'account' => $account instanceof TradingAccount ? (string) ($account->platform_environment ?: data_get($account->meta, 'mt5_sync.server', '')) : '',
            'pool' => $poolEntry instanceof Mt5AccountPoolEntry ? (string) $poolEntry->server : '',
        ], static fn (string $value): bool => $value !== '');

        if ($values === []) {
            return '-';
        }

        return collect($values)
            ->map(fn (string $value, string $key): string => $key.': '.$value)
            ->implode('; ');
    }

    private function brokerValue(?TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry): string
    {
        $broker = $poolEntry instanceof Mt5AccountPoolEntry
            ? (string) data_get($poolEntry->meta, 'broker', '')
            : '';

        if ($broker === '' && $account instanceof TradingAccount) {
            $broker = (string) (data_get($account->meta, 'broker') ?: data_get($account->meta, 'mt5_sync.broker'));
        }

        return $broker !== '' ? $broker : '-';
    }

    private function authResult(TradingAccountSyncLog $log, array $payload): string
    {
        $haystack = strtolower(implode(' ', array_filter([
            (string) $log->status,
            (string) $log->message,
            (string) $log->error_message,
            (string) data_get($payload, 'auth_status', ''),
            (string) data_get($payload, 'auth_result', ''),
            (string) data_get($payload, 'error', ''),
            (string) data_get($payload, 'reason', ''),
        ])));

        if (str_contains($haystack, 'unauth') || str_contains($haystack, 'forbidden') || str_contains($haystack, 'token') || str_contains($haystack, 'secret')) {
            return 'auth failure possible';
        }

        if (str_contains($haystack, 'success') || str_contains($haystack, 'completed') || str_contains($haystack, 'accepted')) {
            return 'auth success/accepted';
        }

        return 'unknown';
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
            'test',
            'test123',
            'secret',
            'secret-pass',
            'provided separately by wolforix support',
            'investor password pending',
        ], true)) {
            return true;
        }

        return Str::contains($normalized, [
            'real_password',
            'real_investor_password',
            'placeholder',
            'dummy',
            'sample',
            'example',
            'fake',
            'test-',
            '-test',
        ]);
    }

    private function finalDecision(bool $decryptFailed, bool $wrongTradingAccount, bool $placeholderCredentials, bool $serverMismatchPossible): string
    {
        if ($decryptFailed) {
            return 'decrypt failed';
        }

        if ($wrongTradingAccount) {
            return 'login mapped to wrong trading account';
        }

        if ($placeholderCredentials) {
            return 'credentials are placeholder';
        }

        if ($serverMismatchPossible) {
            return 'server mismatch possible';
        }

        return 'credentials look real';
    }

    private function maskSecret(string $secret): string
    {
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

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
