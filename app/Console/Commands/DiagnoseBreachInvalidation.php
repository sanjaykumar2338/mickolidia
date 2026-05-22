<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;
use App\Services\Mt5\Mt5AccountDeactivationService;
use App\Support\ChallengeCalculationBreakdown;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DiagnoseBreachInvalidation extends Command
{
    protected $signature = 'wolforix:diagnose-breach-invalidation
        {account : Account reference, MT5 login, platform account id, or trading_accounts.id}
        {--fix : Show the repair action that would mark a breached active account failed}
        {--confirm : Apply the repair action. Requires --fix.}';

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

        $latestSnapshot = $account->balanceSnapshots()
            ->orderByDesc('snapshot_at')
            ->orderByDesc('id')
            ->first();
        $latestCalculation = $calculationBreakdown->forAccount($account, $latestSnapshot);
        $firstBreach = $this->firstBreach($account, $calculationBreakdown);
        $diagnosticCalculation = $firstBreach['calculation'] ?? $latestCalculation;
        $breachTimestamp = $firstBreach['snapshot'] instanceof TradingAccountBalanceSnapshot
            ? $firstBreach['snapshot']->snapshot_at
            : ($latestSnapshot?->snapshot_at ?? $account->last_evaluated_at ?? $account->last_synced_at);

        $this->printAccountState($account, $latestSnapshot);
        $this->printBreachCalculation($diagnosticCalculation, $breachTimestamp);
        $this->printStatePersistence($account);
        $this->printMt5DisableState($account);
        $this->printDecision($account, $diagnosticCalculation);

        if (! $fix) {
            return self::SUCCESS;
        }

        if (! (bool) ($diagnosticCalculation['breach'] ?? false)) {
            $this->warn('Repair skipped: no daily/max loss breach is visible in the stored calculation evidence.');

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
        ]);

        if (! $confirm) {
            $this->warn('Dry-run only. Re-run with --fix --confirm to apply this guarded repair.');

            return self::SUCCESS;
        }

        $updatedAccount = DB::transaction(function () use ($account, $diagnosticCalculation, $reason, $breachTimestamp): TradingAccount {
            /** @var TradingAccount $lockedAccount */
            $lockedAccount = TradingAccount::query()
                ->with('challengePurchase')
                ->lockForUpdate()
                ->findOrFail($account->id);

            $previousStatus = $lockedAccount->account_status;
            $previousPhaseIndex = (int) $lockedAccount->phase_index;
            $failedAt = $this->carbonValue($breachTimestamp) ?? now();

            $lockedAccount->forceFill([
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
            ])->save();

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

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function printBreachCalculation(array $calculation, mixed $breachTimestamp): void
    {
        $startingBalance = max((float) ($calculation['challenge_starting_balance'] ?? 0), 0.01);

        $this->newLine();
        $this->info('Breach calculation');
        $this->table(['Field', 'Value'], [
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
    private function printDecision(TradingAccount $account, array $calculation): void
    {
        $this->newLine();
        $this->info('Diagnostic decision');

        if (! (bool) ($calculation['breach'] ?? false)) {
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
