<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RepairTrialLoginMapping extends Command
{
    private const EXPECTED_LOGIN = '335374';

    private const TRIAL_ACCOUNT_ID = 85;

    private const TRIAL_ACCOUNT_REFERENCE = 'WFX-TRIAL-0009-1FZA9';

    private const PRODUCTION_ACCOUNT_ID = 62;

    private const PRODUCTION_ACCOUNT_REFERENCE = 'WFX-MT5-00057-8HN7';

    protected $signature = 'wolforix:repair-trial-login-mapping
        {login : MT5 login to remove from the verified trial account}
        {--confirm : Apply the repair. Without this option the command only prints a dry-run plan.}';

    protected $description = 'Dry-run first repair for the verified trial MT5 login mapping mismatch.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $confirmed = (bool) $this->option('confirm');

        $this->info(($confirmed ? 'CONFIRMED' : 'DRY RUN').' trial MT5 login mapping repair');
        $this->warn('This command does not deactivate the broker MT5 login and does not touch the production trading account.');
        $this->table(['Field', 'Value'], [
            ['app_env', (string) config('app.env')],
            ['db_connection', (string) config('database.default')],
            ['database', (string) (DB::connection()->getDatabaseName() ?: '(unknown)')],
            ['requested_login', $login],
            ['expected_login', self::EXPECTED_LOGIN],
            ['trial_account', '#'.self::TRIAL_ACCOUNT_ID.' '.self::TRIAL_ACCOUNT_REFERENCE],
            ['production_owner', '#'.self::PRODUCTION_ACCOUNT_ID.' '.self::PRODUCTION_ACCOUNT_REFERENCE],
            ['mode', $confirmed ? 'confirm' : 'dry-run'],
        ]);

        if ($login !== self::EXPECTED_LOGIN) {
            $this->error('Refusing to continue: this repair is locked to login '.self::EXPECTED_LOGIN.'.');

            return self::FAILURE;
        }

        try {
            DB::selectOne('select 1 as connected');
        } catch (Throwable $exception) {
            $this->error('NOT SAFE — DB unavailable');
            $this->line('DB error: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($confirmed) {
            return DB::transaction(fn (): int => $this->runRepair($login, confirmed: true));
        }

        return $this->runRepair($login, confirmed: false);
    }

    private function runRepair(string $login, bool $confirmed): int
    {
        $context = $this->loadContext($login, lockForUpdate: $confirmed);
        $blockers = $this->safetyBlockers($login, $context);

        $this->printCurrentState($context);

        if ($blockers !== []) {
            $this->newLine();
            $this->error('NOT SAFE — repair blocked');
            foreach ($blockers as $blocker) {
                $this->line('Safety blocker: '.$blocker);
            }

            return self::FAILURE;
        }

        /** @var TradingAccount $trialAccount */
        $trialAccount = $context['trial_account'];
        $plannedAttributes = $this->plannedTrialAttributes($trialAccount, $login);
        $beforeRows = $this->accountRows(collect([$trialAccount, $context['production_account']]));
        $afterRows = $this->plannedAccountRows(collect([$trialAccount, $context['production_account']]), [
            $trialAccount->id => $plannedAttributes,
        ]);

        $this->newLine();
        $this->info('Trading account before/after');
        $this->table($this->accountHeaders(), $beforeRows);
        $this->table($this->accountHeaders(), $afterRows);

        $this->newLine();
        $this->info('MT5 pool allocation before/after');
        $poolRows = $this->poolRows($context['pool_entries']);
        $this->table($this->poolHeaders(), $poolRows);
        $this->table($this->poolHeaders(), $poolRows);

        $this->newLine();
        $this->info('Planned action');
        $this->table(['Target', 'Action'], [
            [
                'trading_accounts#'.$trialAccount->id,
                'Clear platform_login/platform_account_id for login '.$login.'; set platform_status=pending_connection and sync_status=pending; remove MT5 sync linkage metadata from the trial row only.',
            ],
            [
                'trading_accounts#'.self::PRODUCTION_ACCOUNT_ID,
                'No change.',
            ],
            [
                'mt5_account_pool_entries.login='.$login,
                'No change; allocation remains on '.self::PRODUCTION_ACCOUNT_REFERENCE.'.',
            ],
        ]);

        if (! $confirmed) {
            $this->printVerification($login, $context, $plannedAttributes, applied: false);
            $this->warn('DRY RUN ONLY — no database changes were made. Re-run with --confirm to apply.');

            return self::SUCCESS;
        }

        $trialAccount->forceFill($plannedAttributes)->save();

        $verifiedContext = $this->loadContext($login, lockForUpdate: false);
        $this->printVerification($login, $verifiedContext, null, applied: true);
        $this->info('SAFE REPAIR COMPLETE');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     trial_account: TradingAccount|null,
     *     production_account: TradingAccount|null,
     *     direct_accounts: Collection<int, TradingAccount>,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>
     * }
     */
    private function loadContext(string $login, bool $lockForUpdate): array
    {
        $accountQuery = TradingAccount::query()->with('user');
        $poolQuery = Mt5AccountPoolEntry::query()->with(['allocatedTradingAccount.user', 'allocatedUser']);

        if ($lockForUpdate) {
            $accountQuery->lockForUpdate();
            $poolQuery->lockForUpdate();
        }

        $trialAccount = (clone $accountQuery)
            ->whereKey(self::TRIAL_ACCOUNT_ID)
            ->where('account_reference', self::TRIAL_ACCOUNT_REFERENCE)
            ->first();
        $productionAccount = (clone $accountQuery)
            ->whereKey(self::PRODUCTION_ACCOUNT_ID)
            ->where('account_reference', self::PRODUCTION_ACCOUNT_REFERENCE)
            ->first();
        $directAccounts = (clone $accountQuery)
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login);
            })
            ->orderBy('id')
            ->get();
        $poolEntries = (clone $poolQuery)
            ->where('login', $login)
            ->orderBy('id')
            ->get();

        return [
            'trial_account' => $trialAccount,
            'production_account' => $productionAccount,
            'direct_accounts' => $directAccounts,
            'pool_entries' => $poolEntries,
        ];
    }

    /**
     * @param  array{
     *     trial_account: TradingAccount|null,
     *     production_account: TradingAccount|null,
     *     direct_accounts: Collection<int, TradingAccount>,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>
     * }  $context
     * @return list<string>
     */
    private function safetyBlockers(string $login, array $context): array
    {
        $blockers = [];
        $trialAccount = $context['trial_account'];
        $productionAccount = $context['production_account'];
        $poolEntries = $context['pool_entries'];
        $directAccounts = $context['direct_accounts'];

        if (! $trialAccount instanceof TradingAccount) {
            $blockers[] = 'trial account #'.self::TRIAL_ACCOUNT_ID.' / '.self::TRIAL_ACCOUNT_REFERENCE.' was not found';
        }

        if (! $productionAccount instanceof TradingAccount) {
            $blockers[] = 'production account #'.self::PRODUCTION_ACCOUNT_ID.' / '.self::PRODUCTION_ACCOUNT_REFERENCE.' was not found';
        }

        if ($trialAccount instanceof TradingAccount && ! $this->isTrialAccount($trialAccount)) {
            $blockers[] = 'trial account reference/id exists but does not look like a trial account';
        }

        if ($productionAccount instanceof TradingAccount && $this->isTrialAccount($productionAccount)) {
            $blockers[] = 'production owner looks like a trial account';
        }

        $unexpectedDirectAccounts = $directAccounts
            ->reject(fn (TradingAccount $account): bool => in_array((int) $account->id, [self::TRIAL_ACCOUNT_ID, self::PRODUCTION_ACCOUNT_ID], true))
            ->values();

        if ($unexpectedDirectAccounts->isNotEmpty()) {
            $blockers[] = 'unexpected trading account(s) also map to login '.$login.': '.$unexpectedDirectAccounts
                ->map(fn (TradingAccount $account): string => '#'.$account->id.' '.($account->account_reference ?: '-'))
                ->implode(', ');
        }

        if ($trialAccount instanceof TradingAccount
            && (string) $trialAccount->platform_login !== $login
            && (string) $trialAccount->platform_account_id !== $login
        ) {
            $this->warn('Trial account already has no direct mapping to login '.$login.'. This run will be treated as an idempotent no-op for login fields.');
        }

        if ($poolEntries->count() !== 1) {
            $blockers[] = 'expected exactly one MT5 pool entry for login '.$login.', found '.$poolEntries->count();
        }

        $poolEntry = $poolEntries->first();

        if ($poolEntry instanceof Mt5AccountPoolEntry) {
            if ((int) $poolEntry->allocated_trading_account_id !== self::PRODUCTION_ACCOUNT_ID) {
                $blockers[] = 'MT5 pool entry is not allocated to production account #'.self::PRODUCTION_ACCOUNT_ID;
            }

            if ((bool) $poolEntry->is_available) {
                $blockers[] = 'MT5 pool entry is still marked available';
            }
        }

        return $blockers;
    }

    /**
     * @return array<string, mixed>
     */
    private function plannedTrialAttributes(TradingAccount $trialAccount, string $login): array
    {
        return [
            'platform_login' => null,
            'platform_account_id' => null,
            'platform_status' => 'pending_connection',
            'sync_status' => 'pending',
            'meta' => $this->clearedTrialMeta($trialAccount, $login),
        ];
    }

    private function clearedTrialMeta(TradingAccount $trialAccount, string $login): array
    {
        $meta = is_array($trialAccount->meta) ? $trialAccount->meta : [];

        unset($meta['mt5_pool_entry']);

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
            'server',
            'mt5_server',
        ] as $key) {
            unset($credentials[$key]);
        }

        if ($credentials === []) {
            unset($meta['credentials']);
        } else {
            $meta['credentials'] = $credentials;
        }

        $mt5Sync = is_array(data_get($meta, 'mt5_sync')) ? (array) data_get($meta, 'mt5_sync') : [];
        foreach (['identifier', 'account_reference', 'server', 'broker'] as $key) {
            unset($mt5Sync[$key]);
        }
        $mt5Sync['status'] = 'login_mapping_cleared';
        $mt5Sync['removed_login'] = $login;
        $mt5Sync['production_owner_account_id'] = self::PRODUCTION_ACCOUNT_ID;
        $mt5Sync['production_owner_account_reference'] = self::PRODUCTION_ACCOUNT_REFERENCE;
        $mt5Sync['last_repair_reason'] = 'trial_login_mapping_removed';
        $mt5Sync['cleared_at'] = now()->toIso8601String();
        $meta['mt5_sync'] = $mt5Sync;

        $meta['mt5_trial_login_repair'] = [
            'status' => 'cleared',
            'removed_login' => $login,
            'trial_account_id' => self::TRIAL_ACCOUNT_ID,
            'trial_account_reference' => self::TRIAL_ACCOUNT_REFERENCE,
            'production_owner_account_id' => self::PRODUCTION_ACCOUNT_ID,
            'production_owner_account_reference' => self::PRODUCTION_ACCOUNT_REFERENCE,
            'cleared_at' => now()->toIso8601String(),
            'note' => 'Broker MT5 login and production pool allocation were not changed.',
        ];

        return $meta;
    }

    /**
     * @param  array{
     *     trial_account: TradingAccount|null,
     *     production_account: TradingAccount|null,
     *     direct_accounts: Collection<int, TradingAccount>,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>
     * }  $context
     */
    private function printCurrentState(array $context): void
    {
        $this->newLine();
        $this->info('Verified context');
        $this->table(['Check', 'Result'], [
            ['trial_account', $context['trial_account'] instanceof TradingAccount ? '#'.$context['trial_account']->id.' '.$context['trial_account']->account_reference : 'missing'],
            ['production_owner', $context['production_account'] instanceof TradingAccount ? '#'.$context['production_account']->id.' '.$context['production_account']->account_reference : 'missing'],
            ['direct_login_accounts', $context['direct_accounts']->map(fn (TradingAccount $account): string => '#'.$account->id.' '.($account->account_reference ?: '-'))->implode(', ') ?: '-'],
            ['pool_entries', $context['pool_entries']->map(fn (Mt5AccountPoolEntry $entry): string => '#'.$entry->id.' allocated_trading_account_id='.$this->formatValue($entry->allocated_trading_account_id))->implode(', ') ?: '-'],
        ]);
    }

    /**
     * @param  array{
     *     trial_account: TradingAccount|null,
     *     production_account: TradingAccount|null,
     *     direct_accounts: Collection<int, TradingAccount>,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>
     * }  $context
     * @param  array<string, mixed>|null  $plannedAttributes
     */
    private function printVerification(string $login, array $context, ?array $plannedAttributes, bool $applied): void
    {
        $trialAccount = $context['trial_account'];
        $productionAccount = $context['production_account'];
        $poolEntry = $context['pool_entries']->first();

        if (! $applied && $trialAccount instanceof TradingAccount && $plannedAttributes !== null) {
            $trialAccount = $trialAccount->replicate()->forceFill($plannedAttributes);
            $trialAccount->id = self::TRIAL_ACCOUNT_ID;
            $trialAccount->exists = true;
        }

        $trialCleared = $trialAccount instanceof TradingAccount
            && (string) $trialAccount->platform_login === ''
            && (string) $trialAccount->platform_account_id === '';
        $poolStillOwned = $poolEntry instanceof Mt5AccountPoolEntry
            && (int) $poolEntry->allocated_trading_account_id === self::PRODUCTION_ACCOUNT_ID;
        $productionUntouched = $productionAccount instanceof TradingAccount
            && (int) $productionAccount->id === self::PRODUCTION_ACCOUNT_ID
            && (string) $productionAccount->account_reference === self::PRODUCTION_ACCOUNT_REFERENCE;

        $this->newLine();
        $this->info($applied ? 'Final verification' : 'Planned final verification');
        $this->table(['Check', 'Result'], [
            ['login', $login],
            ['trial_login_fields_cleared', $this->boolString($trialCleared)],
            ['trial_platform_status', $trialAccount instanceof TradingAccount ? (string) ($trialAccount->platform_status ?: '-') : '-'],
            ['trial_sync_status', $trialAccount instanceof TradingAccount ? (string) ($trialAccount->sync_status ?: '-') : '-'],
            ['production_account_touched_by_plan', 'no'],
            ['pool_allocation_unchanged_on_production_owner', $this->boolString($poolStillOwned)],
            ['broker_mt5_login_deactivated', 'no'],
            ['orders_trades_snapshots_challenge_history_rule_state_changed', 'no'],
            ['mapping_conflict_after_repair', $trialCleared && $poolStillOwned && $productionUntouched ? 'no' : 'yes'],
        ]);
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @return array<int, array<int, string>>
     */
    private function accountRows(Collection $accounts): array
    {
        return $accounts->filter()->map(fn (TradingAccount $account): array => [
            (string) $account->id,
            (string) ($account->account_reference ?: '-'),
            (string) ($account->platform_login ?: '-'),
            (string) ($account->platform_account_id ?: '-'),
            (string) ($account->platform_status ?: '-'),
            (string) ($account->sync_status ?: '-'),
            (string) ($account->account_status ?: $account->status ?: '-'),
            (string) ($account->challenge_status ?: '-'),
            $this->boolString((bool) $account->is_trial),
            $this->metaLinkageSummary($account),
        ])->all();
    }

    private function plannedAccountRows(Collection $accounts, array $updates): array
    {
        return $accounts->filter()->map(function (TradingAccount $account) use ($updates): array {
            $clone = $account->replicate();
            $clone->forceFill($updates[$account->id] ?? []);
            $clone->id = $account->id;

            return [
                (string) $clone->id,
                (string) ($clone->account_reference ?: '-'),
                (string) ($clone->platform_login ?: '-'),
                (string) ($clone->platform_account_id ?: '-'),
                (string) ($clone->platform_status ?: '-'),
                (string) ($clone->sync_status ?: '-'),
                (string) ($clone->account_status ?: $clone->status ?: '-'),
                (string) ($clone->challenge_status ?: '-'),
                $this->boolString((bool) $clone->is_trial),
                $this->metaLinkageSummary($clone),
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
            (string) ($entry->server ?: '-'),
            $this->formatValue($entry->allocated_trading_account_id),
            (string) ($entry->allocatedTradingAccount?->account_reference ?? '-'),
            $this->formatValue($entry->allocated_user_id),
            $this->formatValue($entry->allocated_at),
            $this->boolString((bool) $entry->is_available),
        ])->all();
    }

    /**
     * @return list<string>
     */
    private function accountHeaders(): array
    {
        return ['id', 'account_reference', 'platform_login', 'platform_account_id', 'platform_status', 'sync_status', 'account_status', 'challenge_status', 'is_trial', 'mt5_meta_linkage'];
    }

    /**
     * @return list<string>
     */
    private function poolHeaders(): array
    {
        return ['id', 'login', 'server', 'allocated_trading_account_id', 'allocated_account_reference', 'allocated_user_id', 'allocated_at', 'is_available'];
    }

    private function isTrialAccount(TradingAccount $account): bool
    {
        return (bool) $account->is_trial
            || str_starts_with((string) $account->account_reference, 'WFX-TRIAL-')
            || str_contains(strtolower((string) $account->account_type), 'trial');
    }

    private function metaLinkageSummary(TradingAccount $account): string
    {
        return implode(', ', [
            'mt5_sync.identifier='.$this->formatValue(data_get($account->meta, 'mt5_sync.identifier')),
            'mt5_sync.status='.$this->formatValue(data_get($account->meta, 'mt5_sync.status')),
            'mt5_pool_entry.id='.$this->formatValue(data_get($account->meta, 'mt5_pool_entry.id')),
            'credentials='.$this->credentialsState($account),
        ]);
    }

    private function credentialsState(TradingAccount $account): string
    {
        $credentials = is_array(data_get($account->meta, 'credentials')) ? (array) data_get($account->meta, 'credentials') : [];

        return $credentials === [] ? 'none' : 'present';
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

        return (string) $value;
    }
}
