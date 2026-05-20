<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountStatusHistory;
use App\Models\TradingAccountSyncLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DiagnoseMt5AccountMapping extends Command
{
    protected $signature = 'wolforix:diagnose-mt5-account-mapping
        {login : MT5 login to investigate}';

    protected $description = 'Read-only diagnosis for MT5 login/account mapping mismatches.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $dbConfig = DB::connection()->getConfig();

        $this->info('READ-ONLY MT5 account mapping diagnosis');
        $this->warn('No database writes, deactivation, invalidation, or status changes are performed by this command.');
        $this->table(['Environment field', 'Value'], [
            ['app_env', (string) config('app.env')],
            ['db_connection', (string) config('database.default')],
            ['db_host', $this->formatValue($dbConfig['host'] ?? '-')],
            ['db_port', $this->formatValue($dbConfig['port'] ?? '-')],
            ['db_database_config', $this->formatValue($dbConfig['database'] ?? '-')],
        ]);

        try {
            DB::selectOne('select 1 as connected');
        } catch (Throwable $exception) {
            $this->error('NOT SAFE / NEED MANUAL REVIEW — DB unavailable');
            $this->line('DB error: '.$exception->getMessage());
            $this->line('Recommended production action: Do not invalidate or deactivate any account until DB connectivity is restored and this diagnostic can complete.');

            return self::FAILURE;
        }

        $this->info('DB connection works.');
        $this->line('Connected database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line('Investigating login: '.$login);

        $directAccounts = $this->directAccounts($login);
        $poolEntries = $this->poolEntries($login);
        $allAccounts = $this->allAccounts($directAccounts, $poolEntries);
        $statusHistories = $this->statusHistories($allAccounts);
        $syncLogs = $this->syncLogs($allAccounts);

        $this->printRelationshipGraph($login, $directAccounts, $poolEntries);
        $this->printTradingAccounts($login, $directAccounts, $allAccounts);
        $this->printPoolEntries($poolEntries);
        $this->printUsers($allAccounts, $poolEntries);
        $this->printOrdersAndChallenges($allAccounts);
        $this->printTimeline($login, $allAccounts, $poolEntries, $statusHistories, $syncLogs);
        $this->printReassignmentHistory($allAccounts, $poolEntries);
        $this->printDetermination($login, $directAccounts, $poolEntries);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, TradingAccount>
     */
    private function directAccounts(string $login): Collection
    {
        return TradingAccount::query()
            ->with(['user', 'order', 'challengePurchase', 'challengePlan'])
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Mt5AccountPoolEntry>
     */
    private function poolEntries(string $login): Collection
    {
        return Mt5AccountPoolEntry::query()
            ->with([
                'allocatedUser',
                'allocatedTradingAccount.user',
                'allocatedTradingAccount.order',
                'allocatedTradingAccount.challengePurchase',
                'allocatedTradingAccount.challengePlan',
            ])
            ->where('login', $login)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, TradingAccount>  $directAccounts
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     * @return Collection<int, TradingAccount>
     */
    private function allAccounts(Collection $directAccounts, Collection $poolEntries): Collection
    {
        return $directAccounts
            ->concat($poolEntries->map(fn (Mt5AccountPoolEntry $entry) => $entry->allocatedTradingAccount)->filter())
            ->unique(fn (TradingAccount $account): int => (int) $account->id)
            ->sortBy('id')
            ->values();
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @return Collection<int, TradingAccountStatusHistory>
     */
    private function statusHistories(Collection $accounts): Collection
    {
        $accountIds = $accounts->pluck('id')->filter()->values();

        if ($accountIds->isEmpty()) {
            return collect();
        }

        return TradingAccountStatusHistory::query()
            ->whereIn('trading_account_id', $accountIds)
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @return Collection<int, TradingAccountSyncLog>
     */
    private function syncLogs(Collection $accounts): Collection
    {
        $accountIds = $accounts->pluck('id')->filter()->values();

        if ($accountIds->isEmpty()) {
            return collect();
        }

        return TradingAccountSyncLog::query()
            ->whereIn('trading_account_id', $accountIds)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->sortBy(fn (TradingAccountSyncLog $log): string => $this->formatTimelineDate($log->completed_at ?: $log->started_at ?: $log->created_at))
            ->values();
    }

    /**
     * @param  Collection<int, TradingAccount>  $directAccounts
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     */
    private function printRelationshipGraph(string $login, Collection $directAccounts, Collection $poolEntries): void
    {
        $this->newLine();
        $this->info('Relationship Graph');

        $rows = [];

        foreach ($directAccounts as $account) {
            $rows[] = [
                'input login '.$login,
                'matches trading_accounts.platform_login/platform_account_id',
                'trading_accounts#'.$account->id.' '.$this->reference($account),
                $this->accountUserLabel($account),
            ];
        }

        foreach ($poolEntries as $entry) {
            $allocatedAccount = $entry->allocatedTradingAccount;
            $rows[] = [
                'input login '.$login,
                'matches mt5_account_pool_entries.login',
                'mt5_account_pool_entries#'.$entry->id.' '.$entry->login.' / '.$this->formatValue($entry->server),
                'allocated_at='.$this->formatValue($entry->allocated_at),
            ];
            $rows[] = [
                'mt5_account_pool_entries#'.$entry->id,
                'allocated_trading_account_id',
                $allocatedAccount instanceof TradingAccount
                    ? 'trading_accounts#'.$allocatedAccount->id.' '.$this->reference($allocatedAccount)
                    : '-',
                $allocatedAccount instanceof TradingAccount ? $this->accountUserLabel($allocatedAccount) : 'no allocated account',
            ];
        }

        if ($rows === []) {
            $this->line('No trading account or MT5 pool rows were found for this login.');

            return;
        }

        $this->table(['From', 'Relationship', 'To', 'Evidence'], $rows);
    }

    /**
     * @param  Collection<int, TradingAccount>  $directAccounts
     * @param  Collection<int, TradingAccount>  $allAccounts
     */
    private function printTradingAccounts(string $login, Collection $directAccounts, Collection $allAccounts): void
    {
        $this->newLine();
        $this->info('Trading Accounts');

        if ($allAccounts->isEmpty()) {
            $this->line('No trading_accounts rows are directly or indirectly linked to login '.$login.'.');

            return;
        }

        $directIds = $directAccounts->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->table([
            'role',
            'id',
            'account_reference',
            'user_email',
            'platform_login',
            'platform_account_id',
            'account_status',
            'challenge_status',
            'platform_status',
            'is_trial',
            'trial_status',
            'account_type',
            'created_at',
            'activated_at',
            'last_synced_at',
        ], $allAccounts->map(fn (TradingAccount $account): array => [
            in_array((int) $account->id, $directIds, true) ? 'direct login match' : 'pool allocated owner',
            (string) $account->id,
            $this->reference($account),
            (string) ($account->user?->email ?? '-'),
            (string) ($account->platform_login ?: '-'),
            (string) ($account->platform_account_id ?: '-'),
            (string) ($account->account_status ?: $account->status ?: '-'),
            (string) ($account->challenge_status ?: '-'),
            (string) ($account->platform_status ?: '-'),
            $this->boolString((bool) $account->is_trial),
            (string) ($account->trial_status ?: '-'),
            (string) ($account->account_type ?: '-'),
            $this->formatValue($account->created_at),
            $this->formatValue($account->activated_at),
            $this->formatValue($account->last_synced_at),
        ])->all());
    }

    /**
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     */
    private function printPoolEntries(Collection $poolEntries): void
    {
        $this->newLine();
        $this->info('MT5 Account Pool Entries');

        if ($poolEntries->isEmpty()) {
            $this->line('No mt5_account_pool_entries rows were found for this login.');

            return;
        }

        $this->table([
            'id',
            'login',
            'server',
            'source_pool',
            'source_status',
            'source_created_at',
            'is_available',
            'allocated_at',
            'allocated_user_email',
            'allocated_trading_account_id',
            'allocated_account_reference',
            'meta_summary',
        ], $poolEntries->map(fn (Mt5AccountPoolEntry $entry): array => [
            (string) $entry->id,
            (string) $entry->login,
            (string) ($entry->server ?: '-'),
            (string) ($entry->source_pool ?: '-'),
            (string) ($entry->source_status ?: '-'),
            $this->formatValue($entry->source_created_at),
            $this->boolString((bool) $entry->is_available),
            $this->formatValue($entry->allocated_at),
            (string) ($entry->allocatedUser?->email ?? '-'),
            $this->formatValue($entry->allocated_trading_account_id),
            $entry->allocatedTradingAccount instanceof TradingAccount ? $this->reference($entry->allocatedTradingAccount) : '-',
            $this->metaSummary($entry->meta),
        ])->all());
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     */
    private function printUsers(Collection $accounts, Collection $poolEntries): void
    {
        $this->newLine();
        $this->info('Users');

        $users = $accounts
            ->map(fn (TradingAccount $account) => $account->user)
            ->concat($poolEntries->map(fn (Mt5AccountPoolEntry $entry) => $entry->allocatedUser))
            ->filter()
            ->unique(fn (User $user): int => (int) $user->id)
            ->sortBy('id')
            ->values();

        if ($users->isEmpty()) {
            $this->line('No related users were found.');

            return;
        }

        $this->table(['id', 'email', 'name', 'status'], $users->map(fn (User $user): array => [
            (string) $user->id,
            (string) ($user->email ?: '-'),
            (string) ($user->name ?: '-'),
            (string) ($user->status ?: '-'),
        ])->all());
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     */
    private function printOrdersAndChallenges(Collection $accounts): void
    {
        $this->newLine();
        $this->info('Orders And Challenges');

        if ($accounts->isEmpty()) {
            $this->line('No related orders or challenge rows were found.');

            return;
        }

        $this->table([
            'account_id',
            'account_reference',
            'order',
            'order_email',
            'payment_status',
            'order_status',
            'challenge_purchase',
            'purchase_status',
            'funded_status',
            'plan',
            'challenge_type',
            'account_size',
        ], $accounts->map(fn (TradingAccount $account): array => [
            (string) $account->id,
            $this->reference($account),
            $account->order ? '#'.$account->order->id.' '.$account->order->order_number : '-',
            (string) ($account->order?->email ?? '-'),
            (string) ($account->order?->payment_status ?? '-'),
            (string) ($account->order?->order_status ?? '-'),
            $account->challengePurchase ? '#'.$account->challengePurchase->id : '-',
            (string) ($account->challengePurchase?->account_status ?? '-'),
            (string) ($account->challengePurchase?->funded_status ?? '-'),
            $account->challengePlan ? '#'.$account->challengePlan->id.' '.$account->challengePlan->slug : '-',
            (string) ($account->challenge_type ?: $account->order?->challenge_type ?: '-'),
            $this->formatValue($account->account_size ?: $account->order?->account_size),
        ])->all());
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     * @param  Collection<int, TradingAccountStatusHistory>  $statusHistories
     * @param  Collection<int, TradingAccountSyncLog>  $syncLogs
     */
    private function printTimeline(
        string $login,
        Collection $accounts,
        Collection $poolEntries,
        Collection $statusHistories,
        Collection $syncLogs
    ): void {
        $this->newLine();
        $this->info('Timeline And History');

        $rows = [];

        foreach ($poolEntries as $entry) {
            $rows[] = [$this->formatTimelineDate($entry->source_created_at), 'pool#'.$entry->id, 'source_created_at', 'login '.$entry->login.' imported from '.$this->formatValue($entry->source_file)];
            $rows[] = [$this->formatTimelineDate($entry->created_at), 'pool#'.$entry->id, 'pool_row_created', 'server '.$this->formatValue($entry->server)];
            $rows[] = [$this->formatTimelineDate($entry->allocated_at), 'pool#'.$entry->id, 'login_assigned', 'allocated to trading_account_id '.$this->formatValue($entry->allocated_trading_account_id).' / user_id '.$this->formatValue($entry->allocated_user_id)];
            $rows[] = [$this->formatTimelineDate($entry->updated_at), 'pool#'.$entry->id, 'pool_row_updated', 'is_available='.$this->boolString((bool) $entry->is_available)];
        }

        foreach ($accounts as $account) {
            $rows[] = [$this->formatTimelineDate($account->created_at), 'account#'.$account->id, 'account_created', $this->reference($account).' / '.$this->accountUserLabel($account)];
            $rows[] = [$this->formatTimelineDate($account->activated_at), 'account#'.$account->id, 'activated_at', $this->reference($account)];
            $rows[] = [$this->formatTimelineDate($account->trial_started_at), 'account#'.$account->id, 'trial_started_at', $this->reference($account)];
            $rows[] = [$this->formatTimelineDate($account->last_synced_at), 'account#'.$account->id, 'last_synced_at', 'platform_login='.$this->formatValue($account->platform_login).' / platform_account_id='.$this->formatValue($account->platform_account_id)];
        }

        foreach ($statusHistories as $history) {
            $rows[] = [
                $this->formatTimelineDate($history->changed_at ?: $history->created_at),
                'status_history#'.$history->id,
                'status_change',
                'account#'.$history->trading_account_id.' '.$this->formatValue($history->previous_status).' -> '.$this->formatValue($history->new_status).' / source='.$this->formatValue($history->source),
            ];
        }

        foreach ($syncLogs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];
            $payloadLogin = (string) (data_get($payload, 'platform_login') ?: data_get($payload, 'platform_account_id') ?: '-');
            $rows[] = [
                $this->formatTimelineDate($log->completed_at ?: $log->started_at ?: $log->created_at),
                'sync_log#'.$log->id,
                'sync_log',
                'account#'.$this->formatValue($log->trading_account_id).' status='.$this->formatValue($log->status).' payload_login='.$payloadLogin.' target_login_match='.$this->boolString($payloadLogin === $login),
            ];
        }

        $rows = collect($rows)
            ->filter(fn (array $row): bool => $row[0] !== '-')
            ->sortBy(fn (array $row): string => $row[0])
            ->values()
            ->all();

        if ($rows === []) {
            $this->line('No timeline rows were found for the linked accounts or pool entries.');

            return;
        }

        $this->table(['time', 'subject', 'event', 'details'], $rows);
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     */
    private function printReassignmentHistory(Collection $accounts, Collection $poolEntries): void
    {
        $this->newLine();
        $this->info('Reassignment History From Metadata');

        $rows = [];

        foreach ($accounts as $account) {
            foreach ($this->historyLikeMetadata($account->meta) as $path => $value) {
                $rows[] = ['trading_accounts#'.$account->id, $this->reference($account), $path, $this->jsonValue($value)];
            }
        }

        foreach ($poolEntries as $entry) {
            foreach ($this->historyLikeMetadata($entry->meta) as $path => $value) {
                $rows[] = ['mt5_account_pool_entries#'.$entry->id, (string) $entry->login, $path, $this->jsonValue($value)];
            }
        }

        if ($rows === []) {
            $this->line('No reassignment/allocation history metadata was found. Use allocated_at and status histories above as the available timeline.');

            return;
        }

        $this->table(['source', 'reference', 'meta_path', 'sanitized_value'], $rows);
    }

    /**
     * @param  Collection<int, TradingAccount>  $directAccounts
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     */
    private function printDetermination(string $login, Collection $directAccounts, Collection $poolEntries): void
    {
        $this->newLine();
        $this->info('Determination');

        $poolOwners = $poolEntries
            ->map(fn (Mt5AccountPoolEntry $entry) => $entry->allocatedTradingAccount)
            ->filter()
            ->unique(fn (TradingAccount $account): int => (int) $account->id)
            ->values();
        $poolOwner = $poolOwners->count() === 1 ? $poolOwners->first() : null;
        $directOwner = $directAccounts->count() === 1 ? $directAccounts->first() : null;
        $safeAccount = null;
        $manualReviewReasons = [];

        if ($directAccounts->isEmpty()) {
            $manualReviewReasons[] = 'no trading account currently stores login '.$login;
        }

        if ($directAccounts->count() > 1) {
            $manualReviewReasons[] = 'multiple trading_accounts rows currently store login '.$login;
        }

        if ($poolEntries->isEmpty()) {
            $manualReviewReasons[] = 'no mt5_account_pool_entries row exists for login '.$login;
        }

        if ($poolEntries->count() > 1) {
            $manualReviewReasons[] = 'multiple mt5_account_pool_entries rows exist for login '.$login;
        }

        if ($poolOwners->isEmpty()) {
            $manualReviewReasons[] = 'MT5 pool entry has no allocated trading account';
        }

        if ($poolOwners->count() > 1) {
            $manualReviewReasons[] = 'MT5 pool entries point to multiple allocated trading accounts';
        }

        if ($poolOwner instanceof TradingAccount && ! $this->isIntendedProductionAccount($poolOwner)) {
            $manualReviewReasons[] = 'pool allocated account does not look like an intended production MT5 account';
        }

        if ($directOwner instanceof TradingAccount && $poolOwner instanceof TradingAccount) {
            if ((int) $directOwner->id === (int) $poolOwner->id) {
                $manualReviewReasons[] = 'direct trading account mapping and pool allocation already point to the same account';
            } elseif ($this->isTemporaryTestAccount($directOwner) && $this->isIntendedProductionAccount($poolOwner)) {
                $safeAccount = $directOwner;
            } else {
                $manualReviewReasons[] = 'direct login owner is not clearly a temporary test account';
            }
        }

        $status = $safeAccount instanceof TradingAccount && $manualReviewReasons === []
            ? 'SAFE ACCOUNT TO INVALIDATE'
            : 'NOT SAFE / NEED MANUAL REVIEW';
        $recommendedAction = $safeAccount instanceof TradingAccount && $poolOwner instanceof TradingAccount && $manualReviewReasons === []
            ? 'Invalidate/remove login '.$login.' from '.$this->reference($safeAccount).' (TradingAccount #'.$safeAccount->id.') only; keep login '.$login.' assigned to '.$this->reference($poolOwner).' (TradingAccount #'.$poolOwner->id.'); do not deactivate MT5 login '.$login.'.'
            : 'Do not invalidate or deactivate any account for login '.$login.'; manually review the trading_accounts platform mapping, mt5_account_pool_entries allocation, order ownership, and status history before taking production action.';

        $ownerAnswer = $poolOwner instanceof TradingAccount ? $this->reference($poolOwner).' / TradingAccount #'.$poolOwner->id.' (inferred from mt5_account_pool_entries.allocated_trading_account_id)' : 'unknown';
        $directAnswer = $directOwner instanceof TradingAccount ? $this->reference($directOwner).' / TradingAccount #'.$directOwner->id : 'unknown or duplicated';
        $temporaryAnswer = $directOwner instanceof TradingAccount ? $this->temporaryTestAnswer($directOwner) : 'unknown';
        $productionAnswer = $poolOwner instanceof TradingAccount ? $this->intendedProductionAnswer($poolOwner) : 'unknown';

        $this->table(['Question', 'Answer'], [
            ['which_account_should_own_login', $ownerAnswer],
            ['direct_login_account', $directAnswer],
            ['direct_login_account_temporary_test', $temporaryAnswer],
            ['pool_allocated_account_intended_production', $productionAnswer],
            ['decision', $status],
            ['manual_review_reasons', $manualReviewReasons === [] ? '-' : implode('; ', $manualReviewReasons)],
        ]);

        $this->line('which_account_should_own_login: '.$ownerAnswer);
        $this->line('direct_login_account: '.$directAnswer);
        $this->line('direct_login_account_temporary_test: '.$temporaryAnswer);
        $this->line('pool_allocated_account_intended_production: '.$productionAnswer);
        $this->line($status.($safeAccount instanceof TradingAccount ? ': '.$this->reference($safeAccount).' / TradingAccount #'.$safeAccount->id : ''));
        foreach ($manualReviewReasons as $reason) {
            $this->line('Manual review reason: '.$reason);
        }
        $this->line('Recommended production action: '.$recommendedAction);
    }

    private function isTemporaryTestAccount(TradingAccount $account): bool
    {
        return (bool) $account->is_trial
            || Str::startsWith((string) $account->account_reference, 'WFX-TRIAL-')
            || Str::contains(Str::lower((string) $account->account_type), 'trial')
            || filled($account->trial_status)
            || filled($account->trial_started_at);
    }

    private function isIntendedProductionAccount(TradingAccount $account): bool
    {
        return ! $this->isTemporaryTestAccount($account)
            && Str::startsWith((string) $account->account_reference, 'WFX-MT5-')
            && in_array((string) $account->platform_slug, ['mt5', ''], true)
            && in_array((string) $account->account_type, ['challenge', 'funded', ''], true);
    }

    private function temporaryTestAnswer(TradingAccount $account): string
    {
        return $this->isTemporaryTestAccount($account)
            ? 'yes — evidence: '.$this->temporaryEvidence($account)
            : 'no — no trial/reference evidence found';
    }

    private function intendedProductionAnswer(TradingAccount $account): string
    {
        return $this->isIntendedProductionAccount($account)
            ? 'yes — evidence: pool allocation plus non-trial WFX-MT5 reference'
            : 'no/unclear — evidence: '.$this->productionEvidence($account);
    }

    private function temporaryEvidence(TradingAccount $account): string
    {
        $evidence = [];

        if ((bool) $account->is_trial) {
            $evidence[] = 'is_trial=true';
        }

        if (Str::startsWith((string) $account->account_reference, 'WFX-TRIAL-')) {
            $evidence[] = 'account_reference starts WFX-TRIAL';
        }

        if (Str::contains(Str::lower((string) $account->account_type), 'trial')) {
            $evidence[] = 'account_type='.$account->account_type;
        }

        if (filled($account->trial_status)) {
            $evidence[] = 'trial_status='.$account->trial_status;
        }

        if (filled($account->trial_started_at)) {
            $evidence[] = 'trial_started_at='.$this->formatValue($account->trial_started_at);
        }

        return $evidence === [] ? '-' : implode(', ', $evidence);
    }

    private function productionEvidence(TradingAccount $account): string
    {
        return implode(', ', [
            'account_reference='.$this->reference($account),
            'is_trial='.$this->boolString((bool) $account->is_trial),
            'account_type='.$this->formatValue($account->account_type),
            'platform_slug='.$this->formatValue($account->platform_slug),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function historyLikeMetadata(mixed $meta, string $path = ''): array
    {
        if (! is_array($meta)) {
            return [];
        }

        $matches = [];

        foreach ($meta as $key => $value) {
            $childPath = $path === '' ? (string) $key : $path.'.'.$key;
            $lowerPath = Str::lower($childPath);

            if (Str::contains($lowerPath, ['assign', 'allocat', 'reassign', 'repair', 'history', 'event'])) {
                $matches[$childPath] = $this->isSensitivePath($childPath) ? '[redacted]' : $this->redactSecrets($value);
            }

            if (is_array($value)) {
                $matches = array_merge($matches, $this->historyLikeMetadata($value, $childPath));
            }
        }

        return $matches;
    }

    private function redactSecrets(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $key => $child) {
            $normalizedKey = Str::lower((string) $key);

            if (Str::contains($normalizedKey, ['password', 'secret', 'token', 'key', 'auth'])) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = $this->redactSecrets($child);
        }

        return $redacted;
    }

    private function isSensitivePath(string $path): bool
    {
        return Str::contains(Str::lower($path), ['password', 'secret', 'token', 'key', 'auth']);
    }

    private function metaSummary(mixed $meta): string
    {
        if (! is_array($meta) || $meta === []) {
            return '-';
        }

        $safeMeta = $this->redactSecrets($meta);
        $summary = array_intersect_key($safeMeta, array_flip(['broker', 'platform', 'row_number', 'import_notes', 'assignment_history', 'allocation_history', 'reassignment_history']));

        if ($summary === []) {
            return 'keys: '.implode(', ', array_keys($safeMeta));
        }

        return $this->jsonValue($summary);
    }

    private function accountUserLabel(TradingAccount $account): string
    {
        return 'user#'.$this->formatValue($account->user_id).' '.$this->formatValue($account->user?->email);
    }

    private function reference(TradingAccount $account): string
    {
        return (string) ($account->account_reference ?: '-');
    }

    private function boolString(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function formatTimelineDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $this->formatValue($value);
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $this->boolString($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return $this->jsonValue($value);
        }

        return (string) $value;
    }

    private function jsonValue(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '-';
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[json encode failed]';
    }
}
