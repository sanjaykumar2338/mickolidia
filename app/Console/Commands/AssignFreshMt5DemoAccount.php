<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignFreshMt5DemoAccount extends Command
{
    private const ACCOUNT_REFERENCE = 'WFX-MT5-00057-8HN7';

    private const SERVER = 'FusionMarkets-Demo';

    protected $signature = 'wolforix:assign-fresh-mt5-demo-account
        {--confirm : Apply the assignment. Without this option the command only prints a dry-run plan.}
        {--show-secret : Print decrypted passwords instead of masked values}';

    protected $description = 'Dry-run first assignment of a fresh valid FusionMarkets demo MT5 pool account to WFX-MT5-00057-8HN7.';

    public function handle(): int
    {
        $confirmed = (bool) $this->option('confirm');
        $showSecret = (bool) $this->option('show-secret');

        $this->info(($confirmed ? 'CONFIRMED' : 'DRY RUN').' fresh FusionMarkets MT5 demo account assignment');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line('Target account reference: '.self::ACCOUNT_REFERENCE);
        $this->line('Required server: '.self::SERVER);
        $this->newLine();

        if (! $confirmed) {
            $this->warn('Dry run only. Re-run with --confirm to apply this exact assignment.');
        } else {
            $this->warn('Applying assignment. This command does not log passwords.');
        }

        if ($showSecret) {
            $this->warn('Sensitive credentials may be printed to this terminal because --show-secret was passed.');
        }

        if ($confirmed) {
            return DB::transaction(fn (): int => $this->runAssignment(confirmed: true, showSecret: $showSecret));
        }

        return $this->runAssignment(confirmed: false, showSecret: $showSecret);
    }

    private function runAssignment(bool $confirmed, bool $showSecret): int
    {
        $account = $this->targetAccount(lockForUpdate: $confirmed);

        if (! $account instanceof TradingAccount) {
            $this->error('Refusing to continue: target account reference was not found.');

            return self::FAILURE;
        }

        $poolEntry = $this->freshPoolEntry(lockForUpdate: $confirmed);

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $this->error('Refusing to continue: no unused FusionMarkets-Demo pool entry with real non-placeholder credentials was found.');
            $this->printRejectedPoolSummary();

            return self::FAILURE;
        }

        $credentials = $this->credentialValues($poolEntry);
        $accountUpdates = $this->accountUpdates($account, $poolEntry, $credentials);
        $poolUpdates = [
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $account->user_id,
            'allocated_at' => now(),
            'is_available' => false,
        ];

        $this->printBeforeAfter($account, $poolEntry, $accountUpdates, $poolUpdates, $credentials, $showSecret);

        if (! $confirmed) {
            $this->printVerificationSummary($account, $poolEntry, $credentials, applied: false, showSecret: $showSecret);

            return self::SUCCESS;
        }

        $account->forceFill($accountUpdates)->save();
        $poolEntry->forceFill($poolUpdates)->save();

        $account->refresh();
        $poolEntry->refresh();

        $this->newLine();
        $this->info('Assignment applied. Trades and snapshots were not touched. Passwords were not logged.');
        $this->printVerificationSummary($account, $poolEntry, $credentials, applied: true, showSecret: $showSecret);

        return self::SUCCESS;
    }

    private function targetAccount(bool $lockForUpdate): ?TradingAccount
    {
        $query = TradingAccount::query()
            ->with('user')
            ->where('account_reference', self::ACCOUNT_REFERENCE);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function freshPoolEntry(bool $lockForUpdate): ?Mt5AccountPoolEntry
    {
        $query = Mt5AccountPoolEntry::query()
            ->where('server', self::SERVER)
            ->whereNull('allocated_trading_account_id')
            ->whereNull('allocated_user_id')
            ->whereNull('allocated_at')
            ->where('is_available', true)
            ->orderBy('source_created_at')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Collection<int, Mt5AccountPoolEntry> $entries */
        $entries = $query->get();

        return $entries->first(function (Mt5AccountPoolEntry $entry): bool {
            $state = $this->credentialState($entry);

            return $state['password'] === 'real_value'
                && $state['investor_password'] === 'real_value';
        });
    }

    /**
     * @param  array{password: string, investor_password: string}  $credentials
     * @return array<string, mixed>
     */
    private function accountUpdates(TradingAccount $account, Mt5AccountPoolEntry $poolEntry, array $credentials): array
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        unset($meta['mt5_credential_repair']);

        $broker = (string) data_get($poolEntry->meta, 'broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS);
        $meta['credentials'] = array_filter(array_merge(
            is_array(data_get($meta, 'credentials')) ? (array) data_get($meta, 'credentials') : [],
            [
                'server' => $poolEntry->server,
                'mt5_server' => $poolEntry->server,
                'password' => $credentials['password'],
                'trading_password' => $credentials['password'],
                'investor_password' => $credentials['investor_password'],
                'readonly_password' => $credentials['investor_password'],
                'last_updated_at' => now()->toIso8601String(),
            ],
        ), static fn (mixed $value): bool => $value !== null && $value !== '');

        $meta['mt5_sync'] = [
            'identifier' => $poolEntry->login,
            'account_reference' => self::ACCOUNT_REFERENCE,
            'server' => $poolEntry->server,
            'broker' => $broker,
            'status' => $account->last_synced_at ? 'connected' : 'waiting_for_first_sync',
        ];
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
        $meta['mt5_server'] = $poolEntry->server;
        $meta['broker'] = $broker;
        $meta['provider'] = $broker;
        $meta['platform'] = Mt5AccountPoolEntry::PLATFORM_MT5;

        return [
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $poolEntry->login,
            'platform_account_id' => $poolEntry->login,
            'platform_environment' => $poolEntry->server,
            'platform_status' => $account->last_synced_at ? 'connected' : 'waiting_for_first_sync',
            'sync_status' => 'pending',
            'sync_error' => null,
            'sync_error_at' => null,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array{password: string, investor_password: string}  $credentials
     * @param  array<string, mixed>  $accountUpdates
     * @param  array<string, mixed>  $poolUpdates
     */
    private function printBeforeAfter(
        TradingAccount $account,
        Mt5AccountPoolEntry $poolEntry,
        array $accountUpdates,
        array $poolUpdates,
        array $credentials,
        bool $showSecret,
    ): void {
        $plannedAccount = $account->replicate();
        $plannedAccount->forceFill($accountUpdates);
        $plannedAccount->id = $account->id;
        $plannedAccount->setRelation('user', $account->user);

        $plannedPoolEntry = $poolEntry->replicate();
        $plannedPoolEntry->forceFill($poolUpdates);
        $plannedPoolEntry->id = $poolEntry->id;

        $this->newLine();
        $this->info('Trading account mapping before');
        $this->table($this->accountHeaders(), [$this->accountRow($account, null, $showSecret)]);
        $this->info('Trading account mapping after');
        $this->table($this->accountHeaders(), [$this->accountRow($plannedAccount, $credentials, $showSecret)]);

        $this->newLine();
        $this->info('Selected pool entry before');
        $this->table($this->poolHeaders(), [$this->poolRow($poolEntry)]);
        $this->info('Selected pool entry after');
        $this->table($this->poolHeaders(), [$this->poolRow($plannedPoolEntry)]);
    }

    /**
     * @param  array{password: string, investor_password: string}|null  $credentials
     * @return array<int, string>
     */
    private function accountRow(TradingAccount $account, ?array $credentials, bool $showSecret): array
    {
        $storedCredentials = is_array(data_get($account->meta, 'credentials')) ? (array) data_get($account->meta, 'credentials') : [];
        $password = $credentials['password'] ?? (string) ($storedCredentials['password'] ?? '');
        $investorPassword = $credentials['investor_password'] ?? (string) ($storedCredentials['investor_password'] ?? '');

        return [
            (string) $account->id,
            (string) ($account->user?->email ?? '-'),
            (string) ($account->account_reference ?: '-'),
            (string) ($account->platform_login ?: '-'),
            (string) ($account->platform_account_id ?: '-'),
            (string) ($account->platform_environment ?: '-'),
            (string) ($account->platform_status ?: '-'),
            (string) ($account->sync_status ?: '-'),
            data_get($account->meta, 'mt5_credential_repair.status') ? 'present' : 'absent',
            $this->secretDisplay($password, $showSecret),
            $this->secretDisplay($investorPassword, $showSecret),
        ];
    }

    private function accountHeaders(): array
    {
        return ['id', 'user_email', 'account_reference', 'platform_login', 'platform_account_id', 'server', 'platform_status', 'sync_status', 'pending_repair', 'password', 'investor_password'];
    }

    /**
     * @return array<int, string>
     */
    private function poolRow(Mt5AccountPoolEntry $entry): array
    {
        return [
            (string) $entry->id,
            (string) $entry->login,
            (string) $entry->server,
            (string) ($entry->allocated_trading_account_id ?: '-'),
            (string) ($entry->allocated_user_id ?: '-'),
            $this->formatValue($entry->allocated_at),
            $entry->is_available ? 'yes' : 'no',
            $this->credentialStateLabel($this->credentialState($entry)),
        ];
    }

    private function poolHeaders(): array
    {
        return ['id', 'login', 'server', 'allocated_trading_account_id', 'allocated_user_id', 'allocated_at', 'is_available', 'credential_state'];
    }

    /**
     * @param  array{password: string, investor_password: string}  $credentials
     */
    private function printVerificationSummary(TradingAccount $account, Mt5AccountPoolEntry $poolEntry, array $credentials, bool $applied, bool $showSecret): void
    {
        $this->newLine();
        $this->info('Final verification summary');
        $this->table(['Check', 'Value'], [
            ['mode', $applied ? 'applied' : 'dry_run'],
            ['target_account_reference', self::ACCOUNT_REFERENCE],
            ['selected_pool_entry_id', (string) $poolEntry->id],
            ['selected_login', (string) $poolEntry->login],
            ['selected_server', (string) $poolEntry->server],
            ['password_state', $this->looksLikePlaceholder($credentials['password']) ? 'placeholder' : 'real_value'],
            ['investor_password_state', $this->looksLikePlaceholder($credentials['investor_password']) ? 'placeholder' : 'real_value'],
            ['password', $this->secretDisplay($credentials['password'], $showSecret)],
            ['investor_password', $this->secretDisplay($credentials['investor_password'], $showSecret)],
            ['pending_credential_repair_removed', $applied ? (data_get($account->meta, 'mt5_credential_repair') === null ? 'yes' : 'no') : 'planned'],
            ['trades_snapshots_touched', 'no'],
        ]);
    }

    private function printRejectedPoolSummary(): void
    {
        $entries = Mt5AccountPoolEntry::query()
            ->where('server', self::SERVER)
            ->whereNull('allocated_trading_account_id')
            ->whereNull('allocated_user_id')
            ->whereNull('allocated_at')
            ->orderBy('source_created_at')
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($entries->isEmpty()) {
            $this->line('No unused FusionMarkets-Demo pool rows were found.');

            return;
        }

        $this->table($this->poolHeaders(), $entries->map(fn (Mt5AccountPoolEntry $entry): array => $this->poolRow($entry))->all());
    }

    /**
     * @return array{password: string, investor_password: string}
     */
    private function credentialValues(Mt5AccountPoolEntry $entry): array
    {
        return [
            'password' => (string) $entry->password,
            'investor_password' => (string) $entry->investor_password,
        ];
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
