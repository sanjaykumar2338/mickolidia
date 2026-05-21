<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;
use App\Models\TradingAccountDay;
use App\Models\TradingAccountStatusHistory;
use App\Models\TradingAccountSyncLog;
use App\Services\Mt5\Mt5AccountDeactivationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class InvalidateMt5AccountReview extends Command
{
    private const TARGET_ACCOUNT_ID = 62;

    private const TARGET_ACCOUNT_REFERENCE = 'WFX-MT5-00057-8HN7';

    private const TARGET_EMAIL = 'josublen457@gmail.com';

    private const TARGET_LOGIN = '335374';

    private const FAILURE_REASON = 'scalping_rule_violation';

    private const DEACTIVATION_EVENT = 'fail_scalping_rule_violation';

    protected $signature = 'wolforix:invalidate-mt5-account-review
        {account_reference : Confirmed Wolforix account reference to invalidate}
        {--confirm : Apply the invalidation. Without this option the command only prints a dry-run plan.}';

    protected $description = 'Dry-run first invalidation for the confirmed MT5 manual review failure.';

    public function handle(Mt5AccountDeactivationService $deactivationService): int
    {
        $accountReference = trim((string) $this->argument('account_reference'));
        $confirmed = (bool) $this->option('confirm');

        $this->info(($confirmed ? 'CONFIRMED' : 'DRY RUN').' MT5 account manual-review invalidation');
        $this->warn('This command is scoped to the confirmed production MT5 account only. It does not create Phase 2 accounts, delete broker history, or issue replacements.');

        try {
            DB::selectOne('select 1 as connected');
        } catch (Throwable $exception) {
            $this->error('NOT SAFE — DB unavailable');
            $this->line('DB error: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['app_env', (string) config('app.env')],
            ['db_connection', (string) config('database.default')],
            ['db_host', $this->databaseHost()],
            ['database', (string) (DB::connection()->getDatabaseName() ?: '(unknown)')],
            ['requested_account_reference', $accountReference],
            ['confirmed_account', '#'.self::TARGET_ACCOUNT_ID.' '.self::TARGET_ACCOUNT_REFERENCE],
            ['confirmed_login', self::TARGET_LOGIN],
            ['confirmed_email', self::TARGET_EMAIL],
            ['failure_reason', self::FAILURE_REASON],
            ['mode', $confirmed ? 'confirm' : 'dry-run'],
        ]);

        if ($accountReference !== self::TARGET_ACCOUNT_REFERENCE) {
            $this->error('Refusing to continue: this command is locked to '.self::TARGET_ACCOUNT_REFERENCE.'.');

            return self::FAILURE;
        }

        $context = $this->loadContext(lockForUpdate: false);
        $blockers = $this->safetyBlockers($context);
        $this->printCurrentState($context);

        if ($blockers !== []) {
            $this->newLine();
            $this->error('NOT SAFE — invalidation blocked');
            foreach ($blockers as $blocker) {
                $this->line('Safety blocker: '.$blocker);
            }

            return self::FAILURE;
        }

        /** @var TradingAccount $account */
        $account = $context['account'];
        $integrityBefore = $this->integrityCounts($account);
        $plannedAttributes = $this->plannedAccountAttributes($account, $context);
        $plannedPurchaseAttributes = $this->plannedChallengePurchaseAttributes($account);

        $this->printBeforeAfter($account, $plannedAttributes, $plannedPurchaseAttributes);
        $this->printPlannedAction($context, $integrityBefore);

        if (! $confirmed) {
            $this->printVerification(
                $this->plannedContext($context, $plannedAttributes, $plannedPurchaseAttributes),
                $integrityBefore,
                $integrityBefore,
                applied: false,
            );
            $this->warn('DRY RUN ONLY — no database changes were made. Re-run with --confirm to apply.');

            return self::SUCCESS;
        }

        try {
            /** @var TradingAccount $updatedAccount */
            $updatedAccount = DB::transaction(function () use ($plannedAttributes, $plannedPurchaseAttributes): TradingAccount {
                $lockedContext = $this->loadContext(lockForUpdate: true);
                $lockedBlockers = $this->safetyBlockers($lockedContext);

                if ($lockedBlockers !== []) {
                    throw new RuntimeException('Safety state changed: '.implode('; ', $lockedBlockers));
                }

                /** @var TradingAccount $lockedAccount */
                $lockedAccount = $lockedContext['account'];
                $lockedAccount->forceFill($plannedAttributes)->save();

                if ($lockedAccount->challengePurchase !== null && $plannedPurchaseAttributes !== []) {
                    $lockedAccount->challengePurchase->forceFill($plannedPurchaseAttributes)->save();
                }

                return $lockedAccount->fresh(['user', 'challengePurchase']) ?? $lockedAccount;
            });
        } catch (Throwable $exception) {
            $this->error('NOT SAFE — invalidation was not applied');
            $this->line('Error: '.$exception->getMessage());

            return self::FAILURE;
        }

        $updatedAccount = $deactivationService->requestForFinalState(
            $updatedAccount,
            self::DEACTIVATION_EVENT,
            $this->deactivationContext($updatedAccount),
        );

        $verifiedContext = $this->loadContext(lockForUpdate: false);
        /** @var TradingAccount $verifiedAccount */
        $verifiedAccount = $verifiedContext['account'];
        $integrityAfter = $this->integrityCounts($verifiedAccount);

        $this->printVerification($verifiedContext, $integrityBefore, $integrityAfter, applied: true);
        $this->info('SAFE INVALIDATION COMPLETE');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }
     */
    private function loadContext(bool $lockForUpdate): array
    {
        $accountQuery = TradingAccount::query()->with(['user', 'challengePurchase', 'order', 'challengePlan']);
        $poolQuery = Mt5AccountPoolEntry::query()->with(['allocatedTradingAccount.user', 'allocatedUser']);
        $directAccountQuery = TradingAccount::query()->with('user');

        if ($lockForUpdate) {
            $accountQuery->lockForUpdate();
            $poolQuery->lockForUpdate();
            $directAccountQuery->lockForUpdate();
        }

        $account = (clone $accountQuery)
            ->whereKey(self::TARGET_ACCOUNT_ID)
            ->where('account_reference', self::TARGET_ACCOUNT_REFERENCE)
            ->first();

        $poolEntries = (clone $poolQuery)
            ->where('login', self::TARGET_LOGIN)
            ->orderBy('id')
            ->get();

        $directAccounts = (clone $directAccountQuery)
            ->where(function ($query): void {
                $query->where('platform_login', self::TARGET_LOGIN)
                    ->orWhere('platform_account_id', self::TARGET_LOGIN);
            })
            ->orderBy('id')
            ->get();

        return [
            'account' => $account,
            'pool_entries' => $poolEntries,
            'direct_accounts' => $directAccounts,
        ];
    }

    /**
     * @param  array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     * @return list<string>
     */
    private function safetyBlockers(array $context): array
    {
        $blockers = [];
        $account = $context['account'];
        $poolEntries = $context['pool_entries'];
        $directAccounts = $context['direct_accounts'];

        if (! $account instanceof TradingAccount) {
            return ['target trading account #'.self::TARGET_ACCOUNT_ID.' / '.self::TARGET_ACCOUNT_REFERENCE.' was not found'];
        }

        if ((int) $account->id !== self::TARGET_ACCOUNT_ID) {
            $blockers[] = 'target account id mismatch';
        }

        if ((string) $account->account_reference !== self::TARGET_ACCOUNT_REFERENCE) {
            $blockers[] = 'target account reference mismatch';
        }

        if ((string) $account->user?->email !== self::TARGET_EMAIL) {
            $blockers[] = 'target account user email is not '.self::TARGET_EMAIL;
        }

        if ((bool) $account->is_trial || str_starts_with((string) $account->account_reference, 'WFX-TRIAL-')) {
            $blockers[] = 'target account looks like a trial account';
        }

        if ((string) $account->platform_slug !== 'mt5') {
            $blockers[] = 'target account platform_slug is not mt5';
        }

        if ((bool) $account->final_state_locked && (string) $account->failure_reason !== self::FAILURE_REASON) {
            $blockers[] = 'target account is already final_state_locked for a different reason';
        }

        $directLoginMatch = $this->directLoginMatches($account);
        $poolOwnershipMatch = $this->poolOwnershipMatches($context);

        if (! $directLoginMatch && ! $poolOwnershipMatch) {
            $blockers[] = 'neither target direct login fields nor MT5 pool allocation prove ownership of login '.self::TARGET_LOGIN;
        }

        $unexpectedDirectAccounts = $directAccounts
            ->reject(fn (TradingAccount $directAccount): bool => (int) $directAccount->id === self::TARGET_ACCOUNT_ID)
            ->values();

        if ($unexpectedDirectAccounts->isNotEmpty()) {
            $blockers[] = 'unexpected trading account(s) also map to login '.self::TARGET_LOGIN.': '.$unexpectedDirectAccounts
                ->map(fn (TradingAccount $directAccount): string => '#'.$directAccount->id.' '.($directAccount->account_reference ?: '-'))
                ->implode(', ');
        }

        if ($poolEntries->count() > 1) {
            $blockers[] = 'expected at most one MT5 pool entry for login '.self::TARGET_LOGIN.', found '.$poolEntries->count();
        }

        $poolEntry = $poolEntries->first();

        if ($poolEntry instanceof Mt5AccountPoolEntry) {
            if ($poolEntry->allocated_trading_account_id === null) {
                $blockers[] = 'MT5 pool entry is not allocated to any trading account';
            } elseif ((int) $poolEntry->allocated_trading_account_id !== self::TARGET_ACCOUNT_ID) {
                $blockers[] = 'MT5 pool entry is not allocated to target trading account #'.self::TARGET_ACCOUNT_ID;
            }

            if ((int) $poolEntry->allocated_user_id !== (int) $account->user_id) {
                $blockers[] = 'MT5 pool entry allocated user does not match target account user';
            }

            if ((bool) $poolEntry->is_available) {
                $blockers[] = 'MT5 pool entry is still marked available';
            }
        }

        return $blockers;
    }

    /**
     * @param  array{
     *     account: TradingAccount,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     * @return array<string, mixed>
     */
    private function plannedAccountAttributes(TradingAccount $account, array $context): array
    {
        $poolEntry = $context['pool_entries']->first();
        $failedAt = $account->failed_at ?? now();
        $meta = is_array($account->meta) ? $account->meta : [];

        $meta['manual_review_invalidation'] = [
            'status' => 'confirmed_failed',
            'confirmed_by' => 'Miguel',
            'failure_reason' => self::FAILURE_REASON,
            'rule_description' => 'Manual review confirmed a trade-duration/scalping rule violation: trade closed under 60 seconds.',
            'mt5_login' => self::TARGET_LOGIN,
            'account_reference' => self::TARGET_ACCOUNT_REFERENCE,
            'broker_history_preserved' => true,
            'replacement_account_created' => false,
            'phase_2_account_created' => false,
            'recorded_at' => now()->toIso8601String(),
        ];

        return [
            'platform' => $account->platform ?: 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => self::TARGET_LOGIN,
            'platform_account_id' => self::TARGET_LOGIN,
            'platform_environment' => $account->platform_environment ?: ($poolEntry instanceof Mt5AccountPoolEntry ? $poolEntry->server : null),
            'status' => 'Failed',
            'account_status' => 'failed',
            'challenge_status' => 'failed',
            'failed_at' => $failedAt,
            'failure_reason' => self::FAILURE_REASON,
            'failure_context' => $this->failureContext($account),
            'trading_blocked' => true,
            'final_state_locked' => true,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function plannedChallengePurchaseAttributes(TradingAccount $account): array
    {
        if ($account->challengePurchase === null) {
            return [];
        }

        return [
            'account_status' => 'failed',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failureContext(TradingAccount $account): array
    {
        $existing = is_array($account->failure_context) ? $account->failure_context : [];

        return array_merge($existing, [
            'source' => 'manual_review',
            'confirmation' => 'manual_review_confirmed',
            'confirmed_by' => 'Miguel',
            'rule_breached' => self::FAILURE_REASON,
            'failure_reason' => self::FAILURE_REASON,
            'rule_description' => 'Manual review confirmed a trade-duration/scalping rule violation: trade closed under 60 seconds.',
            'under_60_second_trade' => true,
            'trade_duration_seconds_threshold' => 60,
            'account_reference' => self::TARGET_ACCOUNT_REFERENCE,
            'mt5_login' => self::TARGET_LOGIN,
            'customer_email' => self::TARGET_EMAIL,
            'broker_history_preserved' => true,
            'replacement_account_created' => false,
            'phase_2_account_created' => false,
            'reviewed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deactivationContext(TradingAccount $account): array
    {
        return [
            'reason' => self::FAILURE_REASON,
            'completed_phase' => $account->stage ?: $this->phaseLabel($account),
            'final_status' => 'failed',
            'failure_reason' => self::FAILURE_REASON,
            'source' => 'manual_review_confirmed_by_miguel',
            'manual_review_confirmed_by' => 'Miguel',
            'trade_duration_rule' => 'under_60_seconds',
        ];
    }

    /**
     * @param  array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     */
    private function printCurrentState(array $context): void
    {
        $account = $context['account'];

        $this->newLine();
        $this->info('Verified context');
        $this->table(['Check', 'Result'], [
            ['target_account', $account instanceof TradingAccount ? '#'.$account->id.' '.$account->account_reference : 'missing'],
            ['target_email', $account instanceof TradingAccount ? (string) ($account->user?->email ?: '-') : '-'],
            ['target_login_fields', $account instanceof TradingAccount ? 'platform_login='.$this->formatValue($account->platform_login).', platform_account_id='.$this->formatValue($account->platform_account_id) : '-'],
            ['direct_login_match', $this->boolString($account instanceof TradingAccount && $this->directLoginMatches($account))],
            ['pool_allocation_ownership_match', $this->boolString($this->poolOwnershipMatches($context))],
            ['validation_path_used', $this->validationPath($context)],
            ['direct_login_accounts', $context['direct_accounts']->map(fn (TradingAccount $directAccount): string => '#'.$directAccount->id.' '.($directAccount->account_reference ?: '-'))->implode(', ') ?: '-'],
            ['pool_entries', $context['pool_entries']->map(fn (Mt5AccountPoolEntry $entry): string => '#'.$entry->id.' allocated_trading_account_id='.$this->formatValue($entry->allocated_trading_account_id))->implode(', ') ?: '-'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $plannedAttributes
     * @param  array<string, mixed>  $plannedPurchaseAttributes
     */
    private function printBeforeAfter(TradingAccount $account, array $plannedAttributes, array $plannedPurchaseAttributes): void
    {
        $planned = $account->replicate();
        $planned->forceFill($plannedAttributes);
        $planned->id = $account->id;

        $this->newLine();
        $this->info('Trading account before/after');
        $this->table($this->accountHeaders(), [$this->accountRow($account)]);
        $this->table($this->accountHeaders(), [$this->accountRow($planned)]);

        if ($account->challengePurchase !== null) {
            $this->newLine();
            $this->info('Challenge/account purchase before/after');
            $this->table(['id', 'account_status'], [
                [(string) $account->challengePurchase->id, (string) ($account->challengePurchase->account_status ?: '-')],
            ]);
            $this->table(['id', 'account_status'], [
                [(string) $account->challengePurchase->id, (string) ($plannedPurchaseAttributes['account_status'] ?? $account->challengePurchase->account_status ?: '-')],
            ]);
        }
    }

    /**
     * @param  array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     * @param  array<string, int>  $integrityBefore
     */
    private function printPlannedAction(array $context, array $integrityBefore): void
    {
        $poolEntry = $context['pool_entries']->first();

        $this->newLine();
        $this->info('Planned action');
        $this->table(['Target', 'Action'], [
            [
                'trading_accounts#'.self::TARGET_ACCOUNT_ID,
                'Mark failed/invalid for manual review scalping_rule_violation; set trading_blocked=true and final_state_locked=true; preserve rule_state and trading data.',
            ],
            [
                'Mt5AccountDeactivationService',
                'Request close_all_positions_and_disable_account for login '.self::TARGET_LOGIN.' using event '.self::DEACTIVATION_EVENT.'.',
            ],
            [
                'mt5_account_pool_entries#'.($poolEntry instanceof Mt5AccountPoolEntry ? $poolEntry->id : '-'),
                'No allocation change; login remains owned by '.self::TARGET_ACCOUNT_REFERENCE.'.',
            ],
            [
                'orders/trades/snapshots/history',
                'No deletes and no direct edits. Current protected counts: '.$this->integritySummary($integrityBefore).'.',
            ],
            [
                'phase_2_or_replacement_account',
                'No account will be created.',
            ],
        ]);
    }

    /**
     * @param  array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     * @param  array<string, int>  $integrityBefore
     * @param  array<string, int>  $integrityAfter
     */
    private function printVerification(array $context, array $integrityBefore, array $integrityAfter, bool $applied): void
    {
        $account = $context['account'];
        $poolEntry = $context['pool_entries']->first();
        $deactivationStatus = $account instanceof TradingAccount
            ? (string) data_get($account->meta, 'mt5_deactivation.current.status', '-')
            : '-';

        $this->newLine();
        $this->info($applied ? 'Final verification' : 'Planned final verification');
        $this->table(['Check', 'Result'], [
            ['account_is_failed_invalid', $this->boolString($account instanceof TradingAccount && $account->status === 'Failed' && $account->account_status === 'failed' && $account->challenge_status === 'failed')],
            ['failure_reason', $account instanceof TradingAccount ? (string) ($account->failure_reason ?: '-') : '-'],
            ['trading_blocked', $this->boolString($account instanceof TradingAccount && (bool) $account->trading_blocked)],
            ['final_state_locked', $this->boolString($account instanceof TradingAccount && (bool) $account->final_state_locked)],
            ['mt5_login_on_target', $account instanceof TradingAccount ? (string) ($account->platform_login ?: $account->platform_account_id ?: '-') : '-'],
            ['pool_allocation_unchanged_on_target', $this->boolString($poolEntry instanceof Mt5AccountPoolEntry && (int) $poolEntry->allocated_trading_account_id === self::TARGET_ACCOUNT_ID)],
            ['mt5_deactivation_event', $account instanceof TradingAccount ? (string) data_get($account->meta, 'mt5_deactivation.current_event_key', ($applied ? '-' : self::DEACTIVATION_EVENT)) : '-'],
            ['mt5_deactivation_status', $applied ? $deactivationStatus : 'will be requested on --confirm'],
            ['no_phase_2_account_created', $this->boolString(($integrityAfter['related_accounts'] ?? 0) === ($integrityBefore['related_accounts'] ?? 0))],
            ['orders_preserved', $this->boolString(($integrityAfter['orders'] ?? 0) === ($integrityBefore['orders'] ?? 0))],
            ['snapshots_preserved', $this->boolString(($integrityAfter['snapshots'] ?? 0) === ($integrityBefore['snapshots'] ?? 0))],
            ['trading_days_preserved', $this->boolString(($integrityAfter['trading_days'] ?? 0) === ($integrityBefore['trading_days'] ?? 0))],
            ['status_history_preserved', $this->boolString(($integrityAfter['status_history'] ?? 0) === ($integrityBefore['status_history'] ?? 0))],
            ['broker_history_deleted', 'no'],
            ['replacement_account_created', 'no'],
        ]);

        if (! $applied) {
            $this->info('SAFE TO PROCEED');
        }
    }

    /**
     * @param  array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     * @param  array<string, mixed>  $plannedAttributes
     * @param  array<string, mixed>  $plannedPurchaseAttributes
     * @return array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }
     */
    private function plannedContext(array $context, array $plannedAttributes, array $plannedPurchaseAttributes): array
    {
        $account = $context['account'];

        if ($account instanceof TradingAccount) {
            $planned = $account->replicate();
            $planned->forceFill($plannedAttributes);
            $planned->id = $account->id;
            $planned->exists = true;
            $planned->setRelation('user', $account->user);

            if ($account->challengePurchase !== null) {
                $purchase = $account->challengePurchase->replicate();
                $purchase->forceFill($plannedPurchaseAttributes);
                $purchase->id = $account->challengePurchase->id;
                $purchase->exists = true;
                $planned->setRelation('challengePurchase', $purchase);
            }

            $context['account'] = $planned;
        }

        return $context;
    }

    /**
     * @return array<string, int>
     */
    private function integrityCounts(TradingAccount $account): array
    {
        return [
            'related_accounts' => TradingAccount::query()
                ->where(function ($query) use ($account): void {
                    $query->where('user_id', $account->user_id);

                    if ($account->order_id !== null) {
                        $query->orWhere('order_id', $account->order_id);
                    }

                    if ($account->challenge_purchase_id !== null) {
                        $query->orWhere('challenge_purchase_id', $account->challenge_purchase_id);
                    }
                })
                ->count(),
            'orders' => Order::query()
                ->where('user_id', $account->user_id)
                ->count(),
            'snapshots' => TradingAccountBalanceSnapshot::query()
                ->where('trading_account_id', $account->id)
                ->count(),
            'trading_days' => TradingAccountDay::query()
                ->where('trading_account_id', $account->id)
                ->count(),
            'status_history' => TradingAccountStatusHistory::query()
                ->where('trading_account_id', $account->id)
                ->count(),
            'sync_logs' => TradingAccountSyncLog::query()
                ->where('trading_account_id', $account->id)
                ->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function accountHeaders(): array
    {
        return [
            'id',
            'account_reference',
            'platform_login',
            'platform_account_id',
            'platform_status',
            'status',
            'account_status',
            'challenge_status',
            'trading_blocked',
            'final_state_locked',
            'failed_at',
            'failure_reason',
        ];
    }

    /**
     * @return list<string>
     */
    private function accountRow(TradingAccount $account): array
    {
        return [
            (string) $account->id,
            (string) ($account->account_reference ?: '-'),
            (string) ($account->platform_login ?: '-'),
            (string) ($account->platform_account_id ?: '-'),
            (string) ($account->platform_status ?: '-'),
            (string) ($account->status ?: '-'),
            (string) ($account->account_status ?: '-'),
            (string) ($account->challenge_status ?: '-'),
            $this->boolString((bool) $account->trading_blocked),
            $this->boolString((bool) $account->final_state_locked),
            $this->formatValue($account->failed_at),
            (string) ($account->failure_reason ?: '-'),
        ];
    }

    private function integritySummary(array $counts): string
    {
        return collect($counts)
            ->except('sync_logs')
            ->map(fn (int $count, string $key): string => $key.'='.$count)
            ->implode(', ');
    }

    private function directLoginMatches(TradingAccount $account): bool
    {
        return (string) $account->platform_login === self::TARGET_LOGIN
            || (string) $account->platform_account_id === self::TARGET_LOGIN;
    }

    /**
     * @param  array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     */
    private function poolOwnershipMatches(array $context): bool
    {
        $account = $context['account'];
        $poolEntries = $context['pool_entries'];

        if (! $account instanceof TradingAccount || $poolEntries->count() !== 1) {
            return false;
        }

        $poolEntry = $poolEntries->first();

        return $poolEntry instanceof Mt5AccountPoolEntry
            && (string) $poolEntry->login === self::TARGET_LOGIN
            && (int) $poolEntry->allocated_trading_account_id === self::TARGET_ACCOUNT_ID;
    }

    /**
     * @param  array{
     *     account: TradingAccount|null,
     *     pool_entries: Collection<int, Mt5AccountPoolEntry>,
     *     direct_accounts: Collection<int, TradingAccount>
     * }  $context
     */
    private function validationPath(array $context): string
    {
        $account = $context['account'];
        $paths = [];

        if ($account instanceof TradingAccount && $this->directLoginMatches($account)) {
            $paths[] = 'direct login match';
        }

        if ($this->poolOwnershipMatches($context)) {
            $paths[] = 'pool allocation ownership match';
        }

        return $paths === [] ? 'none' : implode(' + ', $paths);
    }

    private function phaseLabel(TradingAccount $account): string
    {
        return match ((int) $account->phase_index) {
            2 => 'Phase 2',
            default => 'Phase 1',
        };
    }

    private function databaseHost(): string
    {
        $connection = (string) config('database.default');
        $host = config("database.connections.{$connection}.host");

        return is_string($host) && $host !== '' ? $host : '(unknown)';
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
