<?php

namespace App\Services\Challenge;

use App\Mail\ChallengeFailedMail;
use App\Mail\ChallengePhasePassSupportNotificationMail;
use App\Mail\ChallengePassedMail;
use App\Models\TradingAccount;
use App\Services\Reviews\TrustpilotReviewRequestMailer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ChallengeFinalStateMailer
{
    public function __construct(
        private readonly ChallengeCertificateGenerator $certificateGenerator,
        private readonly TrustpilotReviewRequestMailer $reviewRequestMailer,
    ) {
    }

    public function sendIfNeeded(TradingAccount $account): void
    {
        if ($account->is_trial) {
            return;
        }

        $mailPayload = DB::transaction(function () use ($account): ?array {
            /** @var TradingAccount|null $freshAccount */
            $freshAccount = TradingAccount::query()
                ->with('user')
                ->lockForUpdate()
                ->find($account->id);

            if (! $freshAccount instanceof TradingAccount || ! $freshAccount->user || blank($freshAccount->user->email)) {
                return null;
            }

            $meta = is_array($freshAccount->meta) ? $freshAccount->meta : [];
            $eventKey = $this->finalStateEventKey($freshAccount);

            if ($freshAccount->challenge_status === 'failed') {
                $shouldSendClient = $freshAccount->failed_email_sent_at === null;
                $shouldNotifySupport = $this->shouldNotifySupportForFailure($freshAccount)
                    && $this->supportNotificationMissing($meta, $eventKey);

                if (! $shouldSendClient && ! $shouldNotifySupport) {
                    return null;
                }

                $sentAt = now();

                if ($shouldNotifySupport) {
                    $this->markSupportNotification($meta, $eventKey, $sentAt, $freshAccount);
                }

                $freshAccount->forceFill(array_filter([
                    'failed_email_sent_at' => $shouldSendClient ? $sentAt : null,
                    'meta' => ($shouldNotifySupport || $meta !== ($freshAccount->meta ?? [])) ? $meta : null,
                ], static fn (mixed $value): bool => $value !== null))->save();

                return [
                    'type' => 'failed',
                    'user' => $freshAccount->user,
                    'account' => $freshAccount->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $freshAccount,
                    'details' => $this->failedDetails($freshAccount),
                    'send_client' => $shouldSendClient,
                    'send_support' => $shouldNotifySupport,
                    'support_details' => $this->supportDetails($freshAccount, $sentAt),
                ];
            }

            $shouldNotifySupport = $freshAccount->challenge_status === 'passed'
                && $this->supportNotificationMissing($meta, $eventKey);
            $fundedPassAlreadySent = $freshAccount->funded_pass_email_sent_at !== null
                || $freshAccount->passed_email_sent_at !== null;

            if ($freshAccount->challenge_status === 'passed' && ! $this->normalizedFinalPassIsConfirmed($freshAccount)) {
                Log::warning('Blocked challenge pass email because normalized rule state does not confirm pass.', $this->passAuditContext(
                    account: $freshAccount,
                    source: static::class.'::sendIfNeeded',
                    mailType: 'challenge_passed',
                ));

                return null;
            }

            if (
                $freshAccount->challenge_status === 'passed'
                && $freshAccount->funded_pass_email_sent_at === null
                && $freshAccount->passed_email_sent_at !== null
            ) {
                $freshAccount->forceFill([
                    'funded_pass_email_sent_at' => $freshAccount->passed_email_sent_at,
                ])->save();
            }

            if ($freshAccount->challenge_status === 'passed' && (! $fundedPassAlreadySent || $shouldNotifySupport)) {
                $certificate = ! $fundedPassAlreadySent
                    ? $this->certificateGenerator->ensureForAccount($freshAccount)
                    : null;
                $sentAt = now();

                if ($shouldNotifySupport) {
                    $this->markSupportNotification($meta, $eventKey, $sentAt, $freshAccount);
                }

                $freshAccount->forceFill(array_filter([
                    'passed_email_sent_at' => ! $fundedPassAlreadySent ? $sentAt : null,
                    'funded_pass_email_sent_at' => ! $fundedPassAlreadySent ? $sentAt : null,
                    'meta' => $shouldNotifySupport ? $meta : null,
                ], static fn (mixed $value): bool => $value !== null))->save();

                return [
                    'type' => 'passed',
                    'user' => $freshAccount->user,
                    'account' => $freshAccount->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $freshAccount,
                    'details' => $this->passedDetails($freshAccount),
                    'certificate' => $certificate,
                    'send_client' => ! $fundedPassAlreadySent,
                    'send_support' => $shouldNotifySupport,
                    'support_details' => $this->supportDetails($freshAccount, $sentAt),
                ];
            }

            return null;
        });

        if ($mailPayload === null) {
            return;
        }

        if ($mailPayload['type'] === 'failed') {
            if ($mailPayload['send_client'] ?? true) {
                Log::info('Sending challenge failed email.', $this->passAuditContext(
                    account: $mailPayload['account'],
                    source: static::class.'::sendIfNeeded',
                    mailType: ChallengeFailedMail::class,
                ));

                Mail::to($mailPayload['user']->email)->send(new ChallengeFailedMail(
                    user: $mailPayload['user'],
                    tradingAccount: $mailPayload['account'],
                    details: $mailPayload['details'],
                ));
                $this->reviewRequestMailer->sendInitialIfNeeded($mailPayload['account']);
            }

            if ($mailPayload['send_support'] ?? false) {
                Log::info('Sending challenge failure support notification.', $this->passAuditContext(
                    account: $mailPayload['account'],
                    source: static::class.'::sendIfNeeded',
                    mailType: ChallengePhasePassSupportNotificationMail::class,
                ));

                Mail::to((string) config('wolforix.support.email'))->send(new ChallengePhasePassSupportNotificationMail(
                    user: $mailPayload['user'],
                    tradingAccount: $mailPayload['account'],
                    details: $mailPayload['support_details'],
                ));
            }

            return;
        }

        if ($mailPayload['send_client'] ?? true) {
            Log::info('Sending challenge pass email.', $this->passAuditContext(
                account: $mailPayload['account'],
                source: static::class.'::sendIfNeeded',
                mailType: ChallengePassedMail::class,
            ));

            Mail::to($mailPayload['user']->email)->send(new ChallengePassedMail(
                user: $mailPayload['user'],
                tradingAccount: $mailPayload['account'],
                details: $mailPayload['details'],
                certificate: $mailPayload['certificate'] ?? null,
            ));
            $this->reviewRequestMailer->sendInitialIfNeeded($mailPayload['account']);
        }

        if ($mailPayload['send_support'] ?? false) {
            Log::info('Sending challenge final-state support notification.', $this->passAuditContext(
                account: $mailPayload['account'],
                source: static::class.'::sendIfNeeded',
                mailType: ChallengePhasePassSupportNotificationMail::class,
            ));

            Mail::to((string) config('wolforix.support.email'))->send(new ChallengePhasePassSupportNotificationMail(
                user: $mailPayload['user'],
                tradingAccount: $mailPayload['account'],
                details: $mailPayload['support_details'],
            ));
        }
    }

    /**
     * @return array<string, string>
     */
    private function failedDetails(TradingAccount $account): array
    {
        $context = (array) ($account->failure_context ?? []);
        $reason = (string) ($account->failure_reason ?: 'rule_breached');
        $threshold = (float) ($context['threshold'] ?? match ($reason) {
            'daily_loss_breached' => $account->daily_drawdown_limit_amount,
            'max_drawdown_breached' => $account->max_drawdown_limit_amount,
            default => 0,
        });
        $recorded = (float) ($context['recorded_value'] ?? match ($reason) {
            'daily_loss_breached' => $account->daily_loss_used,
            'max_drawdown_breached' => $account->max_drawdown_used,
            default => 0,
        });

        return [
            'client_name' => (string) ($account->user?->name ?: 'Trader'),
            'client_email' => (string) ($account->user?->email ?: 'Not available'),
            'account_reference' => (string) ($account->account_reference ?: 'N/A'),
            'mt5_login' => (string) ($account->platform_login ?: $account->platform_account_id ?: 'Not available'),
            'plan' => $this->planLabel($account),
            'rule' => $this->reasonLabel($reason),
            'reason' => $this->reasonLabel($reason),
            'threshold' => $this->money($threshold),
            'recorded_value' => $this->money($recorded),
            'violation_timestamp' => optional($account->failed_at)->toDateTimeString() ?: now()->toDateTimeString(),
            'final_account_status' => $this->reasonLabel((string) ($account->challenge_status ?: $account->account_status ?: 'failed')),
            'support_email' => (string) config('wolforix.support.email'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function passedDetails(TradingAccount $account): array
    {
        return [
            'plan' => $this->planLabel($account),
            'profit_target' => $this->money((float) $account->profit_target_amount),
            'profit_target_percent' => number_format((float) $account->profit_target_percent, 1).'%',
            'phase' => (string) ($account->stage ?: 'Challenge'),
            'support_email' => (string) config('wolforix.support.email'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function supportDetails(TradingAccount $account, \DateTimeInterface $sentAt): array
    {
        $user = $account->user;
        $timestamp = $account->challenge_status === 'failed'
            ? (optional($account->failed_at)->toDateTimeString() ?: $sentAt->format('Y-m-d H:i:s'))
            : (optional($account->passed_at)->toDateTimeString() ?: $sentAt->format('Y-m-d H:i:s'));

        return [
            'client_name' => (string) ($user?->name ?: 'Trader'),
            'client_email' => (string) ($user?->email ?: 'Not available'),
            'account_reference' => (string) ($account->account_reference ?: 'N/A'),
            'account_id' => (string) $account->id,
            'challenge_type' => $this->challengeTypeLabel($account),
            'phase' => $this->completedPhaseLabel($account),
            'final_status' => $this->reasonLabel((string) ($account->challenge_status ?: $account->account_status ?: 'locked')),
            'reason' => $account->challenge_status === 'failed'
                ? $this->reasonLabel((string) ($account->failure_reason ?: 'rule_violation'))
                : __('Passed'),
            'finalized_at' => $timestamp,
            'mt5_login' => (string) ($account->platform_login ?: $account->platform_account_id ?: 'Not available'),
            'mt5_deactivation_status' => $this->mt5DisableStatus($account),
        ];
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'failed' => 'Breached',
            'daily_loss_breached' => 'Daily loss limit',
            'max_drawdown_breached' => 'Maximum drawdown limit',
            default => str($reason)->replace('_', ' ')->title()->toString(),
        };
    }

    private function planLabel(TradingAccount $account): string
    {
        $challengeType = (string) ($account->challenge_type ?: 'challenge');
        $typeLabel = (string) (config("wolforix.challenge_catalog.{$challengeType}.label") ?: str($challengeType)->replace('_', ' ')->title());
        $size = (float) ($account->account_size ?: $account->starting_balance ?: 0);

        return trim($typeLabel.' '.$this->money($size));
    }

    private function challengeTypeLabel(TradingAccount $account): string
    {
        $challengeType = (string) ($account->challenge_type ?: 'challenge');

        return (string) (config("wolforix.challenge_catalog.{$challengeType}.label") ?: str($challengeType)->replace('_', ' ')->title());
    }

    private function completedPhaseLabel(TradingAccount $account): string
    {
        if ($account->challenge_type === 'one_step') {
            return 'Single Phase';
        }

        return (int) $account->phase_index > 1 ? 'Phase 2' : 'Phase 1';
    }

    private function finalStateEventKey(TradingAccount $account): string
    {
        $currentEventKey = (string) data_get($account->meta, 'mt5_deactivation.current_event_key', '');

        if ($currentEventKey !== '') {
            return $currentEventKey;
        }

        if ($account->challenge_status === 'failed') {
            $reason = (string) ($account->failure_reason ?: 'rule_violation');

            return 'fail_'.str($reason)->slug('_');
        }

        return 'pass_finalized';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function supportNotificationMissing(array $meta, string $eventKey): bool
    {
        return blank(Arr::get($meta, "support_notifications.events.{$eventKey}.notified_at"));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function markSupportNotification(array &$meta, string $eventKey, \DateTimeInterface $sentAt, TradingAccount $account): void
    {
        Arr::set($meta, "support_notifications.events.{$eventKey}.notified_at", $sentAt->format(\DateTimeInterface::ATOM));
        Arr::set($meta, "support_notifications.events.{$eventKey}.final_status", (string) ($account->challenge_status ?: $account->account_status ?: 'locked'));
        Arr::set($meta, "support_notifications.events.{$eventKey}.reason", (string) ($account->failure_reason ?: ($account->challenge_status === 'passed' ? 'passed' : 'final_state')));
        Arr::set($meta, "support_notifications.events.{$eventKey}.phase", $this->completedPhaseLabel($account));
    }

    private function shouldNotifySupportForFailure(TradingAccount $account): bool
    {
        return (bool) config('wolforix.support.notify_failures', false)
            && $account->challenge_status === 'failed';
    }

    private function normalizedFinalPassIsConfirmed(TradingAccount $account): bool
    {
        return filter_var(data_get($account->rule_state, 'profit_target_met'), FILTER_VALIDATE_BOOL)
            && filter_var(data_get($account->rule_state, 'minimum_trading_days_met'), FILTER_VALIDATE_BOOL)
            && (float) data_get($account->rule_state, 'phase_profit', $account->total_profit) >= (float) data_get($account->rule_state, 'phase_profit_target_amount', $account->profit_target_amount);
    }

    /**
     * @return array<string, mixed>
     */
    private function passAuditContext(TradingAccount $account, string $source, string $mailType): array
    {
        return [
            'source' => $source,
            'mail_type' => $mailType,
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'user_email' => $account->user?->email,
            'challenge_status' => $account->challenge_status,
            'account_status' => $account->account_status,
            'phase_index' => (int) $account->phase_index,
            'passed_at' => optional($account->passed_at)->toIso8601String(),
            'passed_email_sent_at' => optional($account->passed_email_sent_at)->toIso8601String(),
            'funded_pass_email_sent_at' => optional($account->funded_pass_email_sent_at)->toIso8601String(),
            'normalized_challenge_balance' => data_get($account->rule_state, 'challenge_balance'),
            'normalized_challenge_equity' => data_get($account->rule_state, 'challenge_equity'),
            'realized_profit' => data_get($account->rule_state, 'phase_profit', $account->total_profit),
            'profit_target_amount' => data_get($account->rule_state, 'phase_profit_target_amount', $account->profit_target_amount),
            'profit_target_met' => data_get($account->rule_state, 'profit_target_met'),
            'minimum_trading_days_met' => data_get($account->rule_state, 'minimum_trading_days_met'),
            'trading_days_completed' => data_get($account->rule_state, 'trading_days_completed', $account->trading_days_completed),
            'daily_loss_used' => data_get($account->rule_state, 'daily_loss_used', $account->daily_loss_used),
            'max_drawdown_used' => data_get($account->rule_state, 'max_drawdown_used', $account->max_drawdown_used),
        ];
    }

    private function mt5DisableStatus(TradingAccount $account): string
    {
        $status = (string) data_get($account->meta, 'mt5_deactivation.current.status', '');

        return $status !== ''
            ? str($status)->replace('_', ' ')->title()->toString()
            : str((string) ($account->platform_status ?: 'pending'))->replace('_', ' ')->title()->toString();
    }

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }
}
