<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairMt5CredentialMapping extends Command
{
    private const LOGIN = '335405';

    private const WRONG_ACCOUNT_REFERENCE = 'WFX-MT5-00057-8HN7';

    private const CORRECT_ACCOUNT_REFERENCE = 'WFX-MT5-00062-NSTY';

    protected $signature = 'wolforix:repair-mt5-credential-mapping
        {--confirm : Apply the repair. Without this option the command only prints a dry-run plan.}';

    protected $description = 'Dry-run first repair for the locked MT5 login 335405 credential/account mapping issue.';

    public function handle(): int
    {
        $confirmed = (bool) $this->option('confirm');

        $this->info(($confirmed ? 'CONFIRMED' : 'DRY RUN').' MT5 credential/account mapping repair');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line('Locked login: '.self::LOGIN);
        $this->line('Wrong account reference: '.self::WRONG_ACCOUNT_REFERENCE);
        $this->line('Correct account reference: '.self::CORRECT_ACCOUNT_REFERENCE);
        $this->newLine();

        if (! $confirmed) {
            $this->warn('Dry run only. Re-run with --confirm to apply these exact repair actions.');
        } else {
            $this->warn('Applying repair. This command does not log or print passwords.');
        }

        if ($confirmed) {
            return DB::transaction(fn (): int => $this->runRepair(confirmed: true));
        }

        return $this->runRepair(confirmed: false);
    }

    private function runRepair(bool $confirmed): int
    {
        $context = $this->loadContext(lockForUpdate: $confirmed);

        if (! $context['wrong_account'] instanceof TradingAccount) {
            $this->error('Refusing to continue: wrong account reference was not found.');

            return self::FAILURE;
        }

        if (! $context['correct_account'] instanceof TradingAccount) {
            $this->error('Refusing to continue: correct account reference was not found.');

            return self::FAILURE;
        }

        if ($context['pool_entries']->isEmpty()) {
            $this->error('Refusing to continue: no MT5 pool entries were found for login '.self::LOGIN.'.');

            return self::FAILURE;
        }

        $beforeAccounts = $this->accountRows($context['accounts']);
        $beforePoolEntries = $this->poolRows($context['pool_entries']);
        $plan = $this->buildPlan($context);
        $afterAccounts = $this->plannedAccountRows($context['accounts'], $plan['accounts']);
        $afterPoolEntries = $this->plannedPoolRows($context['pool_entries'], $plan['pool_entries']);

        $this->printBeforeAfter('Trading account mapping before', 'Trading account mapping after', $beforeAccounts, $afterAccounts);
        $this->printBeforeAfter('Pool entry mapping before', 'Pool entry mapping after', $beforePoolEntries, $afterPoolEntries);

        $this->newLine();
        $this->info('Planned actions');
        $this->table(['Target', 'Action'], $plan['actions']);

        if (! $confirmed) {
            return self::SUCCESS;
        }

        foreach ($plan['accounts'] as $accountId => $attributes) {
            /** @var TradingAccount $account */
            $account = TradingAccount::query()->findOrFail($accountId);
            $account->forceFill($attributes)->save();
        }

        foreach ($plan['pool_entries'] as $poolEntryId => $attributes) {
            /** @var Mt5AccountPoolEntry $poolEntry */
            $poolEntry = Mt5AccountPoolEntry::query()->findOrFail($poolEntryId);
            $poolEntry->forceFill($attributes)->save();
        }

        $this->newLine();
        $this->info('Repair applied. No account pass/fail state, trades, snapshots, or passwords were logged.');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     wrong_account: TradingAccount|null,
     *     correct_account: TradingAccount|null,
     *     accounts: Collection<int, TradingAccount>,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>
     * }
     */
    private function loadContext(bool $lockForUpdate): array
    {
        $accountQuery = TradingAccount::query()->with('user');
        $poolQuery = Mt5AccountPoolEntry::query();

        if ($lockForUpdate) {
            $accountQuery->lockForUpdate();
            $poolQuery->lockForUpdate();
        }

        $wrongAccount = (clone $accountQuery)
            ->where('account_reference', self::WRONG_ACCOUNT_REFERENCE)
            ->first();
        $correctAccount = (clone $accountQuery)
            ->where('account_reference', self::CORRECT_ACCOUNT_REFERENCE)
            ->first();

        $accounts = (clone $accountQuery)
            ->where(function ($query): void {
                $query->whereIn('account_reference', [self::WRONG_ACCOUNT_REFERENCE, self::CORRECT_ACCOUNT_REFERENCE])
                    ->orWhere('platform_login', self::LOGIN)
                    ->orWhere('platform_account_id', self::LOGIN);
            })
            ->orderBy('id')
            ->get();

        $poolEntries = (clone $poolQuery)
            ->where('login', self::LOGIN)
            ->orderBy('id')
            ->get();

        return [
            'wrong_account' => $wrongAccount,
            'correct_account' => $correctAccount,
            'accounts' => $accounts,
            'pool_entries' => $poolEntries,
        ];
    }

    /**
     * @param  array{
     *     wrong_account: TradingAccount,
     *     correct_account: TradingAccount,
     *     accounts: Collection<int, TradingAccount>,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>
     * }  $context
     * @return array{accounts: array<int, array<string, mixed>>, pool_entries: array<int, array<string, mixed>>, actions: array<int, array{string, string}>}
     */
    private function buildPlan(array $context): array
    {
        $wrongAccount = $context['wrong_account'];
        $correctAccount = $context['correct_account'];
        $primaryPoolEntry = $context['pool_entries']->first();
        $poolCredentialState = $primaryPoolEntry instanceof Mt5AccountPoolEntry
            ? $this->credentialState($primaryPoolEntry)
            : ['password' => 'missing', 'investor_password' => 'missing'];
        $poolCredentialsAreUsable = $poolCredentialState['password'] === 'real_value'
            && $poolCredentialState['investor_password'] === 'real_value';

        $accountUpdates = [];
        $poolUpdates = [];
        $actions = [];

        foreach ($context['accounts'] as $account) {
            if ((string) $account->account_reference === self::CORRECT_ACCOUNT_REFERENCE) {
                $meta = $this->metaForCorrectAccount($account, $primaryPoolEntry, $poolCredentialsAreUsable);
                $accountUpdates[$account->id] = array_filter([
                    'platform' => 'MT5',
                    'platform_slug' => 'mt5',
                    'platform_login' => self::LOGIN,
                    'platform_account_id' => self::LOGIN,
                    'platform_environment' => $primaryPoolEntry?->server ?: $account->platform_environment,
                    'platform_status' => $poolCredentialsAreUsable
                        ? ($account->last_synced_at ? 'connected' : 'waiting_for_first_sync')
                        : 'pending_credential_repair',
                    'sync_status' => $poolCredentialsAreUsable ? ($account->sync_status ?: 'pending') : 'pending',
                    'meta' => $meta,
                ], static fn (mixed $value): bool => $value !== null);
                $actions[] = [
                    'trading_accounts#'.$account->id,
                    'Assign login '.self::LOGIN.' to correct account '.self::CORRECT_ACCOUNT_REFERENCE.'; credential state: '.$this->credentialStateLabel($poolCredentialState),
                ];

                continue;
            }

            if ($this->accountUsesLogin($account) || (string) $account->account_reference === self::WRONG_ACCOUNT_REFERENCE) {
                $accountUpdates[$account->id] = [
                    'platform_login' => $account->platform_login === self::LOGIN ? null : $account->platform_login,
                    'platform_account_id' => $account->platform_account_id === self::LOGIN ? null : $account->platform_account_id,
                    'platform_status' => 'pending_credential_repair',
                    'sync_status' => 'pending',
                    'meta' => $this->metaForPendingRepair($account),
                ];
                $actions[] = [
                    'trading_accounts#'.$account->id,
                    'Remove login '.self::LOGIN.' and placeholder MT5 credentials; mark pending credential repair.',
                ];
            }
        }

        foreach ($context['pool_entries'] as $entry) {
            $poolUpdates[$entry->id] = [
                'allocated_trading_account_id' => $correctAccount->id,
                'allocated_user_id' => $correctAccount->user_id,
                'allocated_at' => $entry->allocated_at ?: now(),
                'is_available' => false,
            ];
            $actions[] = [
                'mt5_account_pool_entries#'.$entry->id,
                'Allocate login '.self::LOGIN.' pool entry to correct account '.self::CORRECT_ACCOUNT_REFERENCE.'.',
            ];
        }

        return [
            'accounts' => $accountUpdates,
            'pool_entries' => $poolUpdates,
            'actions' => $actions,
        ];
    }

    private function metaForCorrectAccount(TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry, bool $poolCredentialsAreUsable): array
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $server = $poolEntry?->server ?: (string) ($account->platform_environment ?: data_get($meta, 'mt5_sync.server', ''));
        $broker = $poolEntry instanceof Mt5AccountPoolEntry
            ? (string) data_get($poolEntry->meta, 'broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS)
            : (string) data_get($meta, 'broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS);

        if (! $poolCredentialsAreUsable) {
            $meta = $this->removePlaceholderCredentials($meta);
            $meta['mt5_credential_repair'] = [
                'status' => 'pending',
                'reason' => 'pool_entry_credentials_are_placeholder_or_invalid',
                'login' => self::LOGIN,
                'checked_at' => now()->toIso8601String(),
            ];
        } else {
            $credentials = $this->credentialValues($poolEntry);
            $existingCredentials = is_array(data_get($meta, 'credentials')) ? (array) data_get($meta, 'credentials') : [];
            $meta['credentials'] = array_filter(array_merge($existingCredentials, [
                'server' => $server,
                'mt5_server' => $server,
                'password' => $credentials['password'],
                'trading_password' => $credentials['password'],
                'investor_password' => $credentials['investor_password'],
                'readonly_password' => $credentials['investor_password'],
                'last_updated_at' => now()->toIso8601String(),
            ]), static fn (mixed $value): bool => $value !== null && $value !== '');
            unset($meta['mt5_credential_repair']);
        }

        $meta['mt5_sync'] = array_filter([
            'identifier' => self::LOGIN,
            'account_reference' => self::CORRECT_ACCOUNT_REFERENCE,
            'server' => $server,
            'broker' => $broker,
            'status' => $poolCredentialsAreUsable
                ? ($account->last_synced_at ? 'connected' : 'waiting_for_first_sync')
                : 'pending_credential_repair',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($poolEntry instanceof Mt5AccountPoolEntry) {
            $meta['mt5_pool_entry'] = array_filter([
                'id' => $poolEntry->id,
                'source_pool' => $poolEntry->source_pool,
                'source_file' => $poolEntry->source_file,
                'source_batch' => $poolEntry->source_batch,
                'source_status' => $poolEntry->source_status,
                'broker' => data_get($poolEntry->meta, 'broker'),
                'platform' => data_get($poolEntry->meta, 'platform'),
                'source_created_at' => optional($poolEntry->source_created_at)->toDateString(),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        $meta['mt5_server'] = $server;
        $meta['broker'] = $broker;
        $meta['provider'] = $broker;
        $meta['platform'] = Mt5AccountPoolEntry::PLATFORM_MT5;

        return $meta;
    }

    private function metaForPendingRepair(TradingAccount $account): array
    {
        $meta = $this->removePlaceholderCredentials(is_array($account->meta) ? $account->meta : []);
        $mt5Sync = is_array(data_get($meta, 'mt5_sync')) ? (array) data_get($meta, 'mt5_sync') : [];

        if (($mt5Sync['identifier'] ?? null) === self::LOGIN) {
            unset($mt5Sync['identifier']);
        }

        $mt5Sync['status'] = 'pending_credential_repair';
        $mt5Sync['account_reference'] = self::WRONG_ACCOUNT_REFERENCE;
        $mt5Sync['last_repair_reason'] = 'login_335405_belongs_to_'.self::CORRECT_ACCOUNT_REFERENCE;
        $meta['mt5_sync'] = $mt5Sync;
        $meta['mt5_credential_repair'] = [
            'status' => 'pending',
            'reason' => 'login_335405_moved_to_correct_account',
            'removed_login' => self::LOGIN,
            'correct_account_reference' => self::CORRECT_ACCOUNT_REFERENCE,
            'checked_at' => now()->toIso8601String(),
        ];

        if ((string) data_get($meta, 'mt5_pool_entry.id', '') !== '') {
            unset($meta['mt5_pool_entry']);
        }

        return $meta;
    }

    private function removePlaceholderCredentials(array $meta): array
    {
        $credentials = is_array(data_get($meta, 'credentials')) ? (array) data_get($meta, 'credentials') : [];

        foreach ([
            'password',
            'trading_password',
            'mt5_password',
            'platform_password',
            'investor_password',
            'readonly_password',
            'read_only_password',
            'mt5_investor_password',
        ] as $key) {
            if (array_key_exists($key, $credentials) && $this->looksLikePlaceholder((string) $credentials[$key])) {
                unset($credentials[$key]);
            }
        }

        if ($credentials === []) {
            unset($meta['credentials']);
        } else {
            $meta['credentials'] = $credentials;
        }

        foreach (['password', 'trading_password', 'investor_password', 'readonly_password'] as $key) {
            if (array_key_exists($key, $meta) && $this->looksLikePlaceholder((string) $meta[$key])) {
                unset($meta[$key]);
            }
        }

        return $meta;
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
            $value = (string) $entry->{$field};

            if ($value === '') {
                return 'missing';
            }

            return $this->looksLikePlaceholder($value) ? 'placeholder' : 'real_value';
        } catch (DecryptException) {
            return 'decrypt_failed';
        }
    }

    /**
     * @return array{password: string|null, investor_password: string|null}
     */
    private function credentialValues(?Mt5AccountPoolEntry $entry): array
    {
        if (! $entry instanceof Mt5AccountPoolEntry) {
            return [
                'password' => null,
                'investor_password' => null,
            ];
        }

        return [
            'password' => filled($entry->password) ? (string) $entry->password : null,
            'investor_password' => filled($entry->investor_password) ? (string) $entry->investor_password : null,
        ];
    }

    private function credentialStateLabel(array $state): string
    {
        return 'password='.$state['password'].', investor_password='.$state['investor_password'];
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

    private function accountUsesLogin(TradingAccount $account): bool
    {
        return (string) $account->platform_login === self::LOGIN
            || (string) $account->platform_account_id === self::LOGIN;
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @return array<int, array<int, string>>
     */
    private function accountRows(Collection $accounts): array
    {
        return $accounts->map(fn (TradingAccount $account): array => [
            (string) $account->id,
            (string) ($account->account_reference ?: '-'),
            (string) ($account->user?->email ?? '-'),
            (string) ($account->platform_login ?: '-'),
            (string) ($account->platform_account_id ?: '-'),
            (string) ($account->platform_environment ?: '-'),
            (string) ($account->platform_status ?: '-'),
            (string) ($account->sync_status ?: '-'),
            $this->credentialSummary($account),
            (string) ($account->account_status ?: $account->status ?: '-'),
            (string) ($account->challenge_status ?: '-'),
        ])->all();
    }

    private function plannedAccountRows(Collection $accounts, array $updates): array
    {
        return $accounts->map(function (TradingAccount $account) use ($updates): array {
            $attributes = $updates[$account->id] ?? [];
            $clone = $account->replicate();
            $clone->forceFill($attributes);
            $clone->id = $account->id;
            $clone->setRelation('user', $account->user);

            return [
                (string) $clone->id,
                (string) ($clone->account_reference ?: '-'),
                (string) ($clone->user?->email ?? '-'),
                (string) ($clone->platform_login ?: '-'),
                (string) ($clone->platform_account_id ?: '-'),
                (string) ($clone->platform_environment ?: '-'),
                (string) ($clone->platform_status ?: '-'),
                (string) ($clone->sync_status ?: '-'),
                $this->credentialSummary($clone),
                (string) ($clone->account_status ?: $clone->status ?: '-'),
                (string) ($clone->challenge_status ?: '-'),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Mt5AccountPoolEntry>  $entries
     * @return array<int, array<int, string>>
     */
    private function poolRows(Collection $entries): array
    {
        return $entries->map(fn (Mt5AccountPoolEntry $entry): array => [
            (string) $entry->id,
            (string) $entry->login,
            (string) $entry->server,
            (string) ($entry->allocated_trading_account_id ?: '-'),
            (string) ($entry->allocated_user_id ?: '-'),
            $this->formatValue($entry->allocated_at),
            $entry->is_available ? 'yes' : 'no',
            $this->credentialStateLabel($this->credentialState($entry)),
        ])->all();
    }

    private function plannedPoolRows(Collection $entries, array $updates): array
    {
        return $entries->map(function (Mt5AccountPoolEntry $entry) use ($updates): array {
            $attributes = $updates[$entry->id] ?? [];
            $clone = $entry->replicate();
            $clone->forceFill($attributes);
            $clone->id = $entry->id;

            return [
                (string) $clone->id,
                (string) $clone->login,
                (string) $clone->server,
                (string) ($clone->allocated_trading_account_id ?: '-'),
                (string) ($clone->allocated_user_id ?: '-'),
                $this->formatValue($clone->allocated_at),
                $clone->is_available ? 'yes' : 'no',
                $this->credentialStateLabel($this->credentialState($entry)),
            ];
        })->all();
    }

    /**
     * @param  array<int, array<int, string>>  $before
     * @param  array<int, array<int, string>>  $after
     */
    private function printBeforeAfter(string $beforeTitle, string $afterTitle, array $before, array $after): void
    {
        $this->newLine();
        $this->info($beforeTitle);
        $headers = str_contains($beforeTitle, 'Pool')
            ? ['id', 'login', 'server', 'allocated_trading_account_id', 'allocated_user_id', 'allocated_at', 'is_available', 'credential_state']
            : ['id', 'account_reference', 'user_email', 'platform_login', 'platform_account_id', 'server', 'platform_status', 'sync_status', 'credential_state', 'account_status', 'challenge_status'];
        $this->table($headers, $before);

        $this->info($afterTitle);
        $this->table($headers, $after);
    }

    private function credentialSummary(TradingAccount $account): string
    {
        $credentials = is_array(data_get($account->meta, 'credentials')) ? (array) data_get($account->meta, 'credentials') : [];

        $passwordState = array_key_exists('password', $credentials)
            ? ($this->looksLikePlaceholder((string) $credentials['password']) ? 'placeholder' : 'present')
            : 'missing';
        $investorState = array_key_exists('investor_password', $credentials)
            ? ($this->looksLikePlaceholder((string) $credentials['investor_password']) ? 'placeholder' : 'present')
            : 'missing';

        return 'password='.$passwordState.', investor_password='.$investorState;
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
