<?php

namespace App\Services\Challenge;

use App\Models\TradingAccount;
use App\Services\Mt5\Mt5AccountDeactivationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChallengeBreachFinalizer
{
    public function __construct(
        private readonly Mt5AccountDeactivationService $deactivationService,
        private readonly ChallengeFinalStateMailer $finalStateMailer,
    ) {}

    /**
     * @param  array<string, mixed>  $calculation
     */
    public function finalizeIfBreached(
        TradingAccount $account,
        array $calculation,
        mixed $breachAt = null,
        string $source = 'breach_state_reconciliation',
    ): TradingAccount {
        if (! (bool) ($calculation['breach'] ?? false)) {
            return $account;
        }

        $reason = (string) ($calculation['breach_reason'] ?: 'rule_violation');
        $failedAt = $this->carbonValue($breachAt)
            ?? $this->carbonValue($calculation['snapshot_at'] ?? null)
            ?? $account->last_evaluated_at
            ?? $account->last_synced_at
            ?? now();

        $updatedAccount = DB::transaction(function () use ($account, $calculation, $reason, $failedAt, $source): TradingAccount {
            /** @var TradingAccount $lockedAccount */
            $lockedAccount = TradingAccount::query()
                ->with('challengePurchase')
                ->lockForUpdate()
                ->findOrFail($account->id);

            if ($lockedAccount->challenge_status === 'failed' && (bool) $lockedAccount->final_state_locked) {
                if ($this->finalStateMetaIsConsistent($lockedAccount)) {
                    return $lockedAccount->fresh(['challengePurchase']) ?? $lockedAccount;
                }

                return $this->syncFinalStateMeta($lockedAccount, $calculation, $reason, $failedAt, $source);
            }

            $previousStatus = $lockedAccount->account_status;
            $previousPhaseIndex = (int) $lockedAccount->phase_index;

            $lockedAccount->forceFill([
                'status' => 'Breached',
                'account_status' => 'failed',
                'challenge_status' => 'failed',
                'failure_reason' => $reason,
                'failure_context' => $this->failureContext($calculation, $failedAt, $source),
                'failed_at' => $lockedAccount->failed_at ?? $failedAt,
                'trading_blocked' => true,
                'final_state_locked' => true,
                'daily_drawdown' => (float) ($calculation['daily_loss_used'] ?? $lockedAccount->daily_drawdown),
                'daily_loss_used' => (float) ($calculation['daily_loss_used'] ?? $lockedAccount->daily_loss_used),
                'max_drawdown' => (float) ($calculation['max_drawdown_used'] ?? $lockedAccount->max_drawdown),
                'max_drawdown_used' => (float) ($calculation['max_drawdown_used'] ?? $lockedAccount->max_drawdown_used),
                'rule_state' => $this->ruleState($lockedAccount, $calculation, $reason, $failedAt, $source),
                'meta' => $this->finalStateMeta($lockedAccount, $calculation, $reason, $failedAt, $source),
            ])->save();

            if ($previousStatus !== 'failed') {
                $lockedAccount->statusHistories()->create([
                    'previous_status' => $previousStatus,
                    'new_status' => 'failed',
                    'previous_phase_index' => $previousPhaseIndex,
                    'new_phase_index' => (int) $lockedAccount->phase_index,
                    'source' => $source,
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
                        'breach_state_reconciled_at' => now()->toIso8601String(),
                        'breach_state_source' => $source,
                    ]),
                ])->save();
            }

            Log::warning('Challenge breach final state reconciled from stored rule evidence.', [
                'trading_account_id' => $lockedAccount->id,
                'account_reference' => $lockedAccount->account_reference,
                'reason' => $reason,
                'source' => $source,
                'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
                'daily_loss_limit' => $calculation['daily_loss_limit'] ?? null,
                'max_drawdown_used' => $calculation['max_drawdown_used'] ?? null,
                'max_drawdown_limit' => $calculation['max_drawdown_limit'] ?? null,
            ]);

            return $lockedAccount->fresh(['challengePurchase']) ?? $lockedAccount;
        });

        $eventKey = 'fail_'.str($reason)->slug('_');

        $updatedAccount = $updatedAccount->is_trial
            ? $this->deactivationService->requestForTrialFailure($updatedAccount, 'trial_'.$eventKey, [
                'reason' => $reason,
                'final_status' => 'failed',
                'failure_reason' => $reason,
                'source' => $source,
            ])
            : $this->deactivationService->requestForFinalState($updatedAccount, $eventKey, [
                'reason' => $reason,
                'final_status' => 'failed',
                'failure_reason' => $reason,
                'source' => $source,
            ]);

        $this->finalStateMailer->sendIfNeeded($updatedAccount);

        return $updatedAccount->fresh(['challengePurchase']) ?? $updatedAccount;
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, mixed>
     */
    private function failureContext(array $calculation, Carbon $failedAt, string $source): array
    {
        $reason = (string) ($calculation['breach_reason'] ?? 'rule_violation');

        return [
            'server_day' => $failedAt->toDateString(),
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
            'phase_profit' => $calculation['realized_profit'] ?? null,
            'rule_breached' => $reason,
            'threshold' => $reason === 'daily_loss_breached'
                ? ($calculation['daily_loss_limit'] ?? null)
                : ($calculation['max_drawdown_limit'] ?? null),
            'recorded_value' => $reason === 'daily_loss_breached'
                ? ($calculation['daily_loss_used'] ?? null)
                : ($calculation['max_drawdown_used'] ?? null),
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, mixed>
     */
    private function ruleState(
        TradingAccount $account,
        array $calculation,
        string $reason,
        Carbon $failedAt,
        string $source,
    ): array {
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
            'breach_state_reconciled_at' => now()->toIso8601String(),
            'breach_state_source' => $source,
            'rules' => array_merge((array) data_get($account->rule_state, 'rules', []), [
                'daily_drawdown_limit_amount' => $calculation['daily_loss_limit'] ?? null,
                'max_drawdown_limit_amount' => $calculation['max_drawdown_limit'] ?? null,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, mixed>
     */
    private function finalStateMeta(
        TradingAccount $account,
        array $calculation,
        string $reason,
        Carbon $failedAt,
        string $source,
    ): array {
        $meta = is_array($account->meta) ? $account->meta : [];
        $now = now();
        $previousLifecycleState = (string) data_get($meta, 'metaapi_lifecycle.state', 'connected');
        $previousOnboardingState = (string) data_get($meta, 'metaapi_onboarding.state', 'active');

        $meta['breach_state_reconciliation'] = array_filter([
            'applied_at' => $now->toIso8601String(),
            'failed_at' => $failedAt->toIso8601String(),
            'source' => $source,
            'breach_reason' => $reason,
            'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
            'daily_loss_limit' => $calculation['daily_loss_limit'] ?? null,
            'max_drawdown_used' => $calculation['max_drawdown_used'] ?? null,
            'max_drawdown_limit' => $calculation['max_drawdown_limit'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $meta['metaapi_lifecycle'] = array_merge((array) data_get($meta, 'metaapi_lifecycle', []), [
            'state' => 'breached',
            'previous_state' => $previousLifecycleState,
            'last_state_change_at' => $now->toIso8601String(),
            'breach' => [
                'breached' => true,
                'reason' => $reason,
                'detected_at' => $failedAt->toIso8601String(),
                'final_state_locked' => true,
                'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
                'daily_loss_limit' => $calculation['daily_loss_limit'] ?? null,
                'balance_at_breach' => $calculation['challenge_balance'] ?? null,
                'equity_at_breach' => $calculation['challenge_equity'] ?? null,
            ],
        ]);

        $meta['metaapi_onboarding'] = array_merge((array) data_get($meta, 'metaapi_onboarding', []), [
            'state' => 'breached',
            'previous_state' => $previousOnboardingState,
            'state_label' => 'Breached',
            'ready_to_trade' => false,
            'phase_1_ready' => false,
            'phase_2_ready' => false,
            'last_transition_at' => $now->toIso8601String(),
            'breached_at' => $failedAt->toIso8601String(),
        ]);

        $meta['metaapi_events'] = $this->appendEvent((array) data_get($meta, 'metaapi_events', []), [
            'type' => 'challenge_breached',
            'dedupe_key' => 'challenge_breached:'.$reason.':'.$failedAt->toIso8601String(),
            'occurred_at' => $now->toIso8601String(),
            'context' => [
                'message' => 'A trading rule was breached and the account was locked in its final state.',
                'reason' => $reason,
                'source' => $source,
                'daily_loss_used' => $calculation['daily_loss_used'] ?? null,
                'daily_loss_limit' => $calculation['daily_loss_limit'] ?? null,
            ],
        ]);

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function syncFinalStateMeta(
        TradingAccount $account,
        array $calculation,
        string $reason,
        Carbon $failedAt,
        string $source,
    ): TradingAccount {
        $account->forceFill([
            'meta' => $this->finalStateMeta($account, $calculation, $reason, $failedAt, $source),
        ])->save();

        return $account->fresh(['challengePurchase']) ?? $account;
    }

    private function finalStateMetaIsConsistent(TradingAccount $account): bool
    {
        return data_get($account->meta, 'metaapi_lifecycle.state') === 'breached'
            && data_get($account->meta, 'metaapi_onboarding.state') === 'breached'
            && ! (bool) data_get($account->meta, 'metaapi_onboarding.ready_to_trade', false);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array<string, mixed>  $event
     * @return list<array<string, mixed>>
     */
    private function appendEvent(array $events, array $event): array
    {
        $dedupeKey = (string) ($event['dedupe_key'] ?? '');

        foreach ($events as $existing) {
            if (($existing['type'] ?? null) === ($event['type'] ?? null)
                && $dedupeKey !== ''
                && ($existing['dedupe_key'] ?? null) === $dedupeKey
            ) {
                return $events;
            }
        }

        $events[] = $event;

        if (count($events) > 60) {
            $events = array_slice($events, -60);
        }

        return array_values($events);
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
}
