<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;
use App\Models\TradingAccountSyncLog;
use App\Services\Mt5\Mt5AccountDeactivationService;
use App\Support\ChallengeCalculationBreakdown;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseBreachInvalidation extends Command
{
    protected $signature = 'wolforix:diagnose-breach-invalidation
        {account : Account reference, MT5 login, platform account id, or trading_accounts.id}
        {--fix : Show the repair action that would mark a breached active account failed}
        {--confirm : Apply the repair action. Requires --fix.}
        {--evidence-balance= : Manual screenshot/evidence balance at the breach moment}
        {--evidence-equity= : Manual screenshot/evidence equity at the breach moment}
        {--evidence-floating-pnl= : Manual screenshot/evidence floating PnL at the breach moment}
        {--evidence-at= : Manual screenshot/evidence timestamp. Defaults to now}
        {--evidence-server= : Manual screenshot/evidence broker server}';

    protected $description = 'Diagnose whether a challenge account breached daily or max loss and remained recoverable.';

    public function handle(
        ChallengeCalculationBreakdown $calculationBreakdown,
        Mt5AccountDeactivationService $deactivationService,
    ): int {
        $identifier = trim((string) $this->argument('account'));
        $fix = (bool) $this->option('fix');
        $confirm = (bool) $this->option('confirm');

        $this->info('Breach invalidation diagnosis');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line($fix && $confirm ? 'Mode: APPLY guarded repair' : ($fix ? 'Mode: dry-run repair plan' : 'Mode: read-only diagnosis'));
        $this->newLine();

        $account = $this->resolveAccount($identifier);

        if (! $account instanceof TradingAccount) {
            $this->error('Trading account was not found for '.$identifier.'.');

            return self::FAILURE;
        }

        try {
            $manualEvidenceSnapshot = $this->manualEvidenceSnapshot($account);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $latestSnapshot = $account->balanceSnapshots()
            ->orderByDesc('snapshot_at')
            ->orderByDesc('id')
            ->first();
        $latestCalculation = $calculationBreakdown->forAccount($account, $latestSnapshot);
        $firstBreach = $this->firstBreach($account, $calculationBreakdown);
        $manualEvidenceCalculation = $manualEvidenceSnapshot instanceof TradingAccountBalanceSnapshot
            ? $calculationBreakdown->forAccount($account, $manualEvidenceSnapshot)
            : null;
        $diagnosticCalculation = $firstBreach['calculation'] ?? $manualEvidenceCalculation ?? $latestCalculation;
        $calculationSource = $firstBreach['calculation'] !== null
            ? 'stored_breach_snapshot'
            : ($manualEvidenceCalculation !== null ? 'manual_evidence' : 'latest_stored_state');
        $breachTimestamp = $firstBreach['snapshot'] instanceof TradingAccountBalanceSnapshot
            ? $firstBreach['snapshot']->snapshot_at
            : ($manualEvidenceSnapshot?->snapshot_at ?? $latestSnapshot?->snapshot_at ?? $account->last_evaluated_at ?? $account->last_synced_at);

        $this->printAccountState($account, $latestSnapshot);
        $this->printSyncIngestionDiagnosis($account, $latestSnapshot);
        $this->printBreachCalculation($diagnosticCalculation, $breachTimestamp, $calculationSource);
        $this->printStatePersistence($account);
        $this->printMt5DisableState($account);
        $this->printDecision($account, $diagnosticCalculation, $latestSnapshot, $manualEvidenceSnapshot);

        if (! $fix) {
            return self::SUCCESS;
        }

        if (! (bool) ($diagnosticCalculation['breach'] ?? false)) {
            $this->warn('Repair skipped: no daily/max loss breach is visible from stored snapshots or manual evidence.');

            return self::FAILURE;
        }

        if ($account->challenge_status === 'failed' && (bool) $account->final_state_locked) {
            $this->info('Repair not needed: account is already failed and final_state_locked.');

            return self::SUCCESS;
        }

        $reason = (string) ($diagnosticCalculation['breach_reason'] ?: 'rule_violation');
        $eventKey = 'fail_'.str($reason)->slug('_');

        $this->newLine();
        $this->info('Repair action');
        $this->table(['Field', 'Value'], [
            ['will_set_challenge_status', 'failed'],
            ['will_set_failure_reason', $reason],
            ['will_set_failed_at', $this->formatDate($breachTimestamp ?? now())],
            ['will_set_trading_blocked', 'yes'],
            ['will_set_final_state_locked', 'yes'],
            ['will_request_mt5_disable_event', $eventKey],
            ['will_store_manual_evidence_snapshot', $manualEvidenceSnapshot instanceof TradingAccountBalanceSnapshot ? 'yes' : 'no'],
        ]);

        if (! $confirm) {
            $this->warn('Dry-run only. Re-run with --fix --confirm to apply this guarded repair.');

            return self::SUCCESS;
        }

        $updatedAccount = DB::transaction(function () use ($account, $diagnosticCalculation, $reason, $breachTimestamp, $manualEvidenceSnapshot): TradingAccount {
            /** @var TradingAccount $lockedAccount */
            $lockedAccount = TradingAccount::query()
                ->with('challengePurchase')
                ->lockForUpdate()
                ->findOrFail($account->id);

            $previousStatus = $lockedAccount->account_status;
            $previousPhaseIndex = (int) $lockedAccount->phase_index;
            $failedAt = $this->carbonValue($breachTimestamp) ?? now();
            $manualEvidenceFill = $manualEvidenceSnapshot instanceof TradingAccountBalanceSnapshot
                ? [
                    'balance' => (float) $manualEvidenceSnapshot->balance,
                    'equity' => (float) $manualEvidenceSnapshot->equity,
                    'profit_loss' => (float) $manualEvidenceSnapshot->profit_loss,
                    'synced_at' => $failedAt,
                    'last_evaluated_at' => $failedAt,
                    'server_day' => $failedAt->toDateString(),
                ]
                : [];

            $lockedAccount->forceFill(array_merge($manualEvidenceFill, [
                'status' => 'Failed',
                'account_status' => 'failed',
                'challenge_status' => 'failed',
                'failure_reason' => $reason,
                'failure_context' => $this->failureContext($diagnosticCalculation, $failedAt),
                'failed_at' => $lockedAccount->failed_at ?? $failedAt,
                'trading_blocked' => true,
                'final_state_locked' => true,
                'daily_drawdown' => (float) ($diagnosticCalculation['daily_loss_used'] ?? $lockedAccount->daily_drawdown),
                'daily_loss_used' => (float) ($diagnosticCalculation['daily_loss_used'] ?? $lockedAccount->daily_loss_used),
                'max_drawdown' => (float) ($diagnosticCalculation['max_drawdown_used'] ?? $lockedAccount->max_drawdown),
                'max_drawdown_used' => (float) ($diagnosticCalculation['max_drawdown_used'] ?? $lockedAccount->max_drawdown_used),
                'rule_state' => $this->ruleState($lockedAccount, $diagnosticCalculation, $reason, $failedAt),
                'meta' => $this->repairMeta($lockedAccount, $diagnosticCalculation, $manualEvidenceSnapshot, $failedAt),
            ]))->save();

            if ($manualEvidenceSnapshot instanceof TradingAccountBalanceSnapshot) {
                $this->storeManualEvidenceSnapshot($lockedAccount, $manualEvidenceSnapshot, $diagnosticCalculation);
            }

            if ($previousStatus !== 'failed') {
                $lockedAccount->statusHistories()->create([
                    'previous_status' => $previousStatus,
                    'new_status' => 'failed',
                    'previous_phase_index' => $previousPhaseIndex,
                    'new_phase_index' => (int) $lockedAccount->phase_index,
                    'source' => 'breach_invalidation_repair',
                    'context' => $lockedAccount->rule_state,
                    'changed_at' => $failedAt,
                ]);
            }

            if ($lockedAccount->challengePurchase !== null) {
                $lockedAccount->challengePurchase->forceFill([
                    'account_status' => 'failed',
                    'meta' => array_merge($lockedAccount->challengePurchase->meta ?? [], [
                        'failure_reason' => $reason,
                        'failed_at' => $failedAt->toIso8601String(),
                        'breach_repair_applied_at' => now()->toIso8601String(),
                    ]),
                ])->save();
            }

            return $lockedAccount->fresh(['challengePurchase']) ?? $lockedAccount;
        });

        $updatedAccount = $updatedAccount->is_trial
            ? $deactivationService->requestForTrialFailure($updatedAccount, 'trial_'.$eventKey, [
                'reason' => $reason,
                'final_status' => 'failed',
                'failure_reason' => $reason,
                'source' => 'breach_invalidation_repair',
            ])
            : $deactivationService->requestForFinalState($updatedAccount, $eventKey, [
                'reason' => $reason,
                'final_status' => 'failed',
                'failure_reason' => $reason,
                'source' => 'breach_invalidation_repair',
            ]);

        $this->newLine();
        $this->info('Repair applied.');
        $this->table(['Field', 'Value'], [
            ['challenge_status', (string) $updatedAccount->challenge_status],
            ['failure_reason', (string) $updatedAccount->failure_reason],
            ['trading_blocked', $this->yesNo((bool) $updatedAccount->trading_blocked)],
            ['final_state_locked', $this->yesNo((bool) $updatedAccount->final_state_locked)],
            ['mt5_deactivation_event', (string) data_get($updatedAccount->meta, 'mt5_deactivation.current.event', '-')],
            ['mt5_deactivation_status', (string) data_get($updatedAccount->meta, 'mt5_deactivation.current.status', '-')],
        ]);

        return self::SUCCESS;
    }

    private function resolveAccount(string $identifier): ?TradingAccount
    {
        return TradingAccount::query()
            ->where('account_reference', $identifier)
            ->orWhere('platform_login', $identifier)
            ->orWhere('platform_account_id', $identifier)
            ->when(ctype_digit($identifier), fn ($query) => $query->orWhere('id', (int) $identifier))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{snapshot:?TradingAccountBalanceSnapshot, calculation:?array<string, mixed>}
     */
    private function firstBreach(TradingAccount $account, ChallengeCalculationBreakdown $calculationBreakdown): array
    {
        $snapshots = $account->balanceSnapshots()
            ->orderBy('snapshot_at')
            ->orderBy('id')
            ->get();

        foreach ($snapshots as $snapshot) {
            $calculation = $calculationBreakdown->forAccount($account, $snapshot);

            if ((bool) ($calculation['breach'] ?? false)) {
                return [
                    'snapshot' => $snapshot,
                    'calculation' => $calculation,
                ];
            }
        }

        return [
            'snapshot' => null,
            'calculation' => null,
        ];
    }

    /**
     * @return Collection<int, Mt5AccountPoolEntry>
     */
    private function poolEntriesForAccount(TradingAccount $account): Collection
    {
        if (! Schema::hasTable('mt5_account_pool_entries')) {
            return collect();
        }

        $loginValues = collect([
            $account->platform_login,
            $account->platform_account_id,
            data_get($account->meta, 'mt5_sync.identifier'),
        ])
            ->filter(fn (mixed $value): bool => filled((string) $value))
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->values();

        return Mt5AccountPoolEntry::query()
            ->where(function ($query) use ($account, $loginValues): void {
                if ($loginValues->isNotEmpty()) {
                    $query->whereIn('login', $loginValues->all());
                }

                $query->orWhere('allocated_trading_account_id', $account->id);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TradingAccount>
     */
    private function accountsUsingLogin(TradingAccount $account): Collection
    {
        $loginValues = collect([
            $account->platform_login,
            $account->platform_account_id,
            data_get($account->meta, 'mt5_sync.identifier'),
        ])
            ->filter(fn (mixed $value): bool => filled((string) $value))
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->values();

        if ($loginValues->isEmpty()) {
            return collect();
        }

        return TradingAccount::query()
            ->where(function ($query) use ($loginValues): void {
                $query->whereIn('platform_login', $loginValues->all())
                    ->orWhereIn('platform_account_id', $loginValues->all());
            })
            ->orderBy('id')
            ->get();
    }

    private function latestAccountSyncLog(TradingAccount $account): ?TradingAccountSyncLog
    {
        if (! Schema::hasTable('trading_account_sync_logs')) {
            return null;
        }

        return TradingAccountSyncLog::query()
            ->where('trading_account_id', $account->id)
            ->orderByDesc('id')
            ->first();
    }

    private function latestPayloadSyncLog(TradingAccount $account): ?TradingAccountSyncLog
    {
        if (! Schema::hasTable('trading_account_sync_logs')) {
            return null;
        }

        $needles = collect([
            $account->account_reference,
            $account->platform_login,
            $account->platform_account_id,
            data_get($account->meta, 'mt5_sync.identifier'),
        ])
            ->filter(fn (mixed $value): bool => filled((string) $value))
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->values();

        if ($needles->isEmpty()) {
            return null;
        }

        return TradingAccountSyncLog::query()
            ->where(function ($query) use ($account, $needles): void {
                $query->where('trading_account_id', $account->id);

                foreach ($needles as $needle) {
                    $query->orWhere('payload', 'like', '%'.$needle.'%');
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     * @param  Collection<int, TradingAccount>  $accountsUsingLogin
     */
    private function missingSyncReason(
        TradingAccount $account,
        ?TradingAccountBalanceSnapshot $latestSnapshot,
        ?TradingAccountSyncLog $latestAccountLog,
        ?TradingAccountSyncLog $latestPayloadLog,
        Collection $poolEntries,
        Collection $accountsUsingLogin,
    ): string {
        if ($latestSnapshot instanceof TradingAccountBalanceSnapshot) {
            return 'snapshot_present';
        }

        $credentialRepairStatus = (string) data_get($account->meta, 'mt5_credential_repair.status', '');

        if ($credentialRepairStatus !== '' && ! in_array($credentialRepairStatus, ['complete', 'completed', 'resolved'], true)) {
            return 'NO_STORED_SNAPSHOT: credential repair is '.$credentialRepairStatus.'; reinstall/regenerate the MT5 connector for this account after mapping is correct.';
        }

        $otherAccountsUsingLogin = $accountsUsingLogin
            ->reject(fn (TradingAccount $candidate): bool => (int) $candidate->id === (int) $account->id);

        if ($otherAccountsUsingLogin->isNotEmpty()) {
            return 'NO_STORED_SNAPSHOT: platform login is shared by another trading_account; endpoint fallback would be ambiguous.';
        }

        $poolMismatch = $poolEntries->first(fn (Mt5AccountPoolEntry $entry): bool => $entry->allocated_trading_account_id !== null
            && (int) $entry->allocated_trading_account_id !== (int) $account->id);

        if ($poolMismatch instanceof Mt5AccountPoolEntry) {
            return 'NO_STORED_SNAPSHOT: MT5 pool entry is allocated to trading_account #'.$poolMismatch->allocated_trading_account_id.'.';
        }

        $relevantLog = $latestAccountLog ?? $latestPayloadLog;

        if ($relevantLog instanceof TradingAccountSyncLog) {
            if ($relevantLog->status === 'rejected') {
                return 'NO_STORED_SNAPSHOT: latest MT5 payload was rejected: '.($relevantLog->error_message ?: $relevantLog->message ?: 'unknown rejection').'.';
            }

            if ($relevantLog->status === 'error') {
                return 'NO_STORED_SNAPSHOT: latest MT5 sync errored: '.($relevantLog->error_message ?: $relevantLog->message ?: 'unknown error').'.';
            }

            if ($relevantLog->status === 'ignored') {
                return 'NO_STORED_SNAPSHOT: latest MT5 payload was ignored, likely stale timestamp.';
            }
        }

        if ((string) $account->sync_error !== '') {
            return 'NO_STORED_SNAPSHOT: account sync_error is '.$account->sync_error.'.';
        }

        return 'NO_STORED_SNAPSHOT: no accepted MT5 metrics payload is stored for this account/login; verify EA is installed, endpoint identifier, and secret token.';
    }

    private function syncLogSummary(?TradingAccountSyncLog $log): string
    {
        if (! $log instanceof TradingAccountSyncLog) {
            return '-';
        }

        return sprintf(
            '#%d %s %s %s',
            $log->id,
            $log->status ?: '-',
            $this->formatDate($log->completed_at ?? $log->started_at ?? $log->created_at),
            $log->error_message ?: $log->message ?: '-',
        );
    }

    private function printAccountState(TradingAccount $account, ?TradingAccountBalanceSnapshot $latestSnapshot): void
    {
        $this->info('Account state');
        $this->table(['Field', 'Value'], [
            ['account_id', (string) $account->id],
            ['account_reference', (string) $account->account_reference],
            ['account_login', (string) ($account->platform_login ?: $account->platform_account_id ?: '-')],
            ['server', (string) ($account->platform_environment ?: '-')],
            ['challenge_type', (string) $account->challenge_type],
            ['account_size', (string) $account->account_size],
            ['current_balance', $this->money($account->balance)],
            ['current_equity', $this->money($account->equity)],
            ['current_floating_pnl', $this->money($account->profit_loss)],
            ['latest_snapshot_at', $this->formatDate($latestSnapshot?->snapshot_at)],
        ]);
    }

    private function printSyncIngestionDiagnosis(TradingAccount $account, ?TradingAccountBalanceSnapshot $latestSnapshot): void
    {
        $login = (string) ($account->platform_login ?: $account->platform_account_id ?: '');
        $poolEntries = $this->poolEntriesForAccount($account);
        $accountsUsingLogin = $this->accountsUsingLogin($account);
        $latestAccountLog = $this->latestAccountSyncLog($account);
        $latestPayloadLog = $this->latestPayloadSyncLog($account);

        $this->newLine();
        $this->info('MT5 sync ingestion diagnosis');
        $this->table(['Field', 'Value'], [
            ['expected_endpoint_primary', '/api/integrations/mt5/accounts/'.$account->account_reference.'/metrics'],
            ['accepted_identifier_fallback', 'unique platform_login/platform_account_id with account token'],
            ['connector_secret_present', $this->yesNo(filled((string) data_get($account->meta, 'mt5_connector.secret_token')))],
            ['platform_login', $login !== '' ? $login : '-'],
            ['platform_account_id', (string) ($account->platform_account_id ?: '-')],
            ['pool_entry_ids', $poolEntries->isEmpty() ? '-' : $poolEntries->pluck('id')->implode(', ')],
            ['pool_allocated_account_ids', $poolEntries->isEmpty() ? '-' : $poolEntries->pluck('allocated_trading_account_id')->filter()->implode(', ')],
            ['accounts_using_login', $accountsUsingLogin->isEmpty() ? '-' : $accountsUsingLogin->map(fn (TradingAccount $item): string => '#'.$item->id.' '.$item->account_reference)->implode(', ')],
            ['credential_repair.status', (string) data_get($account->meta, 'mt5_credential_repair.status', '-')],
            ['mt5_sync.status', (string) data_get($account->meta, 'mt5_sync.status', '-')],
            ['mt5_sync.last_rejected_reason', (string) data_get($account->meta, 'mt5_sync.last_rejected_reason', '-')],
            ['mt5_sync.last_ignored_reason', (string) data_get($account->meta, 'mt5_sync.last_ignored_reason', '-')],
            ['sync_status', (string) ($account->sync_status ?: '-')],
            ['sync_error', (string) ($account->sync_error ?: '-')],
            ['last_synced_at', $this->formatDate($account->last_synced_at)],
            ['snapshots_count', (string) $account->balanceSnapshots()->count()],
            ['latest_snapshot_at', $this->formatDate($latestSnapshot?->snapshot_at)],
            ['latest_account_sync_log', $this->syncLogSummary($latestAccountLog)],
            ['latest_payload_log_for_login_or_reference', $this->syncLogSummary($latestPayloadLog)],
            ['missing_sync_reason', $this->missingSyncReason($account, $latestSnapshot, $latestAccountLog, $latestPayloadLog, $poolEntries, $accountsUsingLogin)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function printBreachCalculation(array $calculation, mixed $breachTimestamp, string $calculationSource): void
    {
        $startingBalance = max((float) ($calculation['challenge_starting_balance'] ?? 0), 0.01);

        $this->newLine();
        $this->info('Breach calculation');
        $this->table(['Field', 'Value'], [
            ['calculation_source', $calculationSource],
            ['challenge_balance', $this->money($calculation['challenge_balance'] ?? null)],
            ['challenge_equity', $this->money($calculation['challenge_equity'] ?? null)],
            ['floating_pnl', $this->money($calculation['floating_pnl'] ?? null)],
            ['daily_loss_used', $this->money($calculation['daily_loss_used'] ?? null)],
            ['daily_loss_percent', $this->percent(((float) ($calculation['daily_loss_used'] ?? 0) / $startingBalance) * 100)],
            ['daily_loss_limit', $this->money($calculation['daily_loss_limit'] ?? null)],
            ['daily_breach', $this->yesNo((bool) ($calculation['daily_breach'] ?? false))],
            ['max_total_loss_used', $this->money($calculation['max_drawdown_used'] ?? null)],
            ['max_total_loss_percent', $this->percent(((float) ($calculation['max_drawdown_used'] ?? 0) / $startingBalance) * 100)],
            ['max_total_loss_limit', $this->money($calculation['max_drawdown_limit'] ?? null)],
            ['max_total_breach', $this->yesNo((bool) ($calculation['max_breach'] ?? false))],
            ['breach_timestamp', $this->formatDate($breachTimestamp)],
            ['breach_rule', (string) ($calculation['breach_reason'] ?? '-')],
        ]);
    }

    private function printStatePersistence(TradingAccount $account): void
    {
        $this->newLine();
        $this->info('Failure persistence');
        $this->table(['Field', 'Value'], [
            ['failure_reason', (string) ($account->failure_reason ?: '-')],
            ['failed_at', $this->formatDate($account->failed_at)],
            ['trading_blocked', $this->yesNo((bool) $account->trading_blocked)],
            ['final_state_locked', $this->yesNo((bool) $account->final_state_locked)],
            ['rule_state.failure_reason', (string) data_get($account->rule_state, 'failure_reason', '-')],
            ['rule_state.daily_drawdown_breached', $this->formatValue(data_get($account->rule_state, 'daily_drawdown_breached'))],
            ['rule_state.max_drawdown_breached', $this->formatValue(data_get($account->rule_state, 'max_drawdown_breached'))],
        ]);
    }

    private function printMt5DisableState(TradingAccount $account): void
    {
        $this->newLine();
        $this->info('MT5 disable queue status');
        $this->table(['Field', 'Value'], [
            ['platform_status', (string) ($account->platform_status ?: '-')],
            ['current_event', (string) data_get($account->meta, 'mt5_deactivation.current.event', '-')],
            ['current_status', (string) data_get($account->meta, 'mt5_deactivation.current.status', '-')],
            ['current_action', (string) data_get($account->meta, 'mt5_deactivation.current.action', '-')],
            ['current_requested_at', (string) data_get($account->meta, 'mt5_deactivation.current.requested_at', '-')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function printDecision(
        TradingAccount $account,
        array $calculation,
        ?TradingAccountBalanceSnapshot $latestSnapshot,
        ?TradingAccountBalanceSnapshot $manualEvidenceSnapshot,
    ): void
    {
        $this->newLine();
        $this->info('Diagnostic decision');

        if (! (bool) ($calculation['breach'] ?? false)) {
            if (! $latestSnapshot instanceof TradingAccountBalanceSnapshot && ! $manualEvidenceSnapshot instanceof TradingAccountBalanceSnapshot) {
                $this->warn('UNKNOWN: no stored MT5 snapshot/trade evidence exists. Do not treat stale DB balance/equity as proof that the account is valid.');

                return;
            }

            $this->warn('No daily/max total loss breach is visible from current stored evidence.');

            return;
        }

        if ($account->challenge_status === 'failed' && (bool) $account->final_state_locked) {
            $this->info('PASS: breach is permanently failed and locked.');

            return;
        }

        $this->error('FAIL: breach evidence exists but the account is not permanently failed/locked.');
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, mixed>
     */
    private function failureContext(array $calculation, Carbon $failedAt): array
    {
        return [
            'server_day' => optional($failedAt)->toDateString(),
            'breach_timestamp' => $failedAt->toIso8601String(),
            'balance_at_breach' => $calculation['challenge_balance'] ?? null,
            'equity_at_breach' => $calculation['challenge_equity'] ?? null,
            'raw_balance_at_breach' => $calculation['raw_balance'] ?? null,
            'raw_equity_at_breach' => $calculation['raw_equity'] ?? null,
            'floating_pnl' => $calculation['floating_pnl'] ?? null,
            'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
            'daily_loss_threshold' => $calculation['daily_loss_limit'] ?? null,
            'max_drawdown_used' => $calculation['max_drawdown_used'] ?? null,
            'max_drawdown_threshold' => $calculation['max_drawdown_limit'] ?? null,
            'rule_breached' => $calculation['breach_reason'] ?? null,
            'source' => 'breach_invalidation_repair',
        ];
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, mixed>
     */
    private function ruleState(TradingAccount $account, array $calculation, string $reason, Carbon $failedAt): array
    {
        return array_merge($account->rule_state ?? [], [
            'challenge_starting_balance' => $calculation['challenge_starting_balance'] ?? null,
            'challenge_balance' => $calculation['challenge_balance'] ?? null,
            'challenge_equity' => $calculation['challenge_equity'] ?? null,
            'floating_pnl' => $calculation['floating_pnl'] ?? null,
            'daily_drawdown_breached' => (bool) ($calculation['daily_breach'] ?? false),
            'max_drawdown_breached' => (bool) ($calculation['max_breach'] ?? false),
            'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
            'daily_loss_remaining' => $calculation['daily_loss_remaining'] ?? null,
            'max_drawdown_used' => $calculation['max_drawdown_used'] ?? null,
            'max_drawdown_remaining' => $calculation['max_drawdown_remaining'] ?? null,
            'failure_reason' => $reason,
            'evaluated_at' => $failedAt->toIso8601String(),
            'breach_repair_applied_at' => now()->toIso8601String(),
            'rules' => array_merge((array) data_get($account->rule_state, 'rules', []), [
                'daily_drawdown_limit_amount' => $calculation['daily_loss_limit'] ?? null,
                'max_drawdown_limit_amount' => $calculation['max_drawdown_limit'] ?? null,
            ]),
        ]);
    }

    private function manualEvidenceSnapshot(TradingAccount $account): ?TradingAccountBalanceSnapshot
    {
        $provided = collect([
            'evidence-balance' => $this->option('evidence-balance'),
            'evidence-equity' => $this->option('evidence-equity'),
            'evidence-floating-pnl' => $this->option('evidence-floating-pnl'),
            'evidence-at' => $this->option('evidence-at'),
            'evidence-server' => $this->option('evidence-server'),
        ])->filter(fn (mixed $value): bool => $value !== null && $value !== '');

        if ($provided->isEmpty()) {
            return null;
        }

        $balance = $this->requiredNumericOption('evidence-balance');
        $equity = $this->requiredNumericOption('evidence-equity');
        $floatingPnl = $this->numericOption('evidence-floating-pnl') ?? round($equity - $balance, 2);
        $snapshotAt = $this->carbonValue($this->option('evidence-at')) ?? now();
        $server = (string) ($this->option('evidence-server') ?: $account->platform_environment ?: data_get($account->meta, 'mt5_sync.server', ''));

        return new TradingAccountBalanceSnapshot([
            'trading_account_id' => $account->id,
            'snapshot_at' => $snapshotAt,
            'balance' => $balance,
            'equity' => $equity,
            'profit_loss' => $floatingPnl,
            'total_profit' => round($balance - (float) ($account->phase_reference_balance ?: $account->starting_balance ?: $account->account_size ?: $balance), 2),
            'today_profit' => 0,
            'daily_drawdown' => 0,
            'max_drawdown' => 0,
            'drawdown_percent' => 0,
            'payload' => [
                'source' => 'manual_screenshot_evidence',
                'manual_evidence' => true,
                'balance' => $balance,
                'equity' => $equity,
                'open_profit' => $floatingPnl,
                'profit_loss' => $floatingPnl,
                'platform_login' => $account->platform_login ?: $account->platform_account_id,
                'platform_account_id' => $account->platform_account_id ?: $account->platform_login,
                'platform_environment' => $server !== '' ? $server : null,
                'timestamp' => $snapshotAt->toDateTimeString(),
                'server_day' => $snapshotAt->toDateString(),
                'note' => 'Manual breach evidence supplied to wolforix:diagnose-breach-invalidation.',
            ],
        ]);
    }

    private function requiredNumericOption(string $name): float
    {
        $value = $this->option($name);

        if ($value === null || $value === '' || ! is_numeric($value)) {
            throw new \InvalidArgumentException('--'.$name.' is required and must be numeric when any manual evidence option is used.');
        }

        return round((float) $value, 2);
    }

    private function numericOption(string $name): ?float
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('--'.$name.' must be numeric.');
        }

        return round((float) $value, 2);
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, mixed>
     */
    private function repairMeta(
        TradingAccount $account,
        array $calculation,
        ?TradingAccountBalanceSnapshot $manualEvidenceSnapshot,
        Carbon $failedAt,
    ): array {
        $meta = is_array($account->meta) ? $account->meta : [];
        $meta['breach_invalidation_repair'] = array_filter([
            'applied_at' => now()->toIso8601String(),
            'failed_at' => $failedAt->toIso8601String(),
            'source' => $manualEvidenceSnapshot instanceof TradingAccountBalanceSnapshot ? 'manual_screenshot_evidence' : 'stored_snapshot',
            'breach_reason' => $calculation['breach_reason'] ?? null,
            'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
            'max_drawdown_used' => $calculation['max_drawdown_used'] ?? null,
            'manual_evidence_snapshot_at' => $manualEvidenceSnapshot?->snapshot_at?->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($manualEvidenceSnapshot instanceof TradingAccountBalanceSnapshot) {
            $syncMeta = is_array(data_get($meta, 'mt5_sync')) ? (array) data_get($meta, 'mt5_sync') : [];
            $syncMeta['status'] = 'manual_evidence_applied';
            $syncMeta['last_manual_evidence_at'] = now()->toIso8601String();
            $syncMeta['last_manual_evidence_snapshot_at'] = $manualEvidenceSnapshot->snapshot_at?->toIso8601String();
            $syncMeta['last_payload_summary'] = [
                'balance' => (float) $manualEvidenceSnapshot->balance,
                'equity' => (float) $manualEvidenceSnapshot->equity,
                'open_profit' => (float) $manualEvidenceSnapshot->profit_loss,
                'timestamp' => $manualEvidenceSnapshot->snapshot_at?->toDateTimeString(),
                'source' => 'manual_screenshot_evidence',
            ];
            $meta['mt5_sync'] = $syncMeta;
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function storeManualEvidenceSnapshot(
        TradingAccount $account,
        TradingAccountBalanceSnapshot $manualEvidenceSnapshot,
        array $calculation,
    ): void {
        $payload = is_array($manualEvidenceSnapshot->payload) ? $manualEvidenceSnapshot->payload : [];
        $payload['calculation'] = [
            'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
            'daily_loss_limit' => $calculation['daily_loss_limit'] ?? null,
            'max_drawdown_used' => $calculation['max_drawdown_used'] ?? null,
            'max_drawdown_limit' => $calculation['max_drawdown_limit'] ?? null,
            'breach_reason' => $calculation['breach_reason'] ?? null,
        ];

        $account->balanceSnapshots()->create([
            'snapshot_at' => $manualEvidenceSnapshot->snapshot_at ?? now(),
            'balance' => $manualEvidenceSnapshot->balance,
            'equity' => $manualEvidenceSnapshot->equity,
            'profit_loss' => $manualEvidenceSnapshot->profit_loss,
            'total_profit' => $manualEvidenceSnapshot->total_profit,
            'today_profit' => $manualEvidenceSnapshot->today_profit,
            'daily_drawdown' => $calculation['daily_loss_used'] ?? 0,
            'max_drawdown' => $calculation['max_drawdown_used'] ?? 0,
            'drawdown_percent' => $account->starting_balance > 0
                ? round(((float) ($calculation['max_drawdown_used'] ?? 0) / (float) $account->starting_balance) * 100, 2)
                : 0,
            'payload' => $payload,
        ]);
    }

    private function carbonValue(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function formatDate(mixed $value): string
    {
        return $this->carbonValue($value)?->toDateTimeString() ?? '-';
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '-';
    }

    private function percent(float $value): string
    {
        return number_format($value, 2, '.', '').'%';
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $this->yesNo($value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[array]';
        }

        return (string) $value;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
