<?php

namespace App\Services\Challenge;

use App\Mail\ChallengeFailedMail;
use App\Mail\ChallengePhasePassSupportNotificationMail;
use App\Mail\ChallengePassedMail;
use App\Models\TradingAccount;
use App\Services\Reviews\TrustpilotReviewRequestMailer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

            if ($freshAccount->challenge_status === 'failed' && $freshAccount->failed_email_sent_at === null) {
                $freshAccount->forceFill(['failed_email_sent_at' => now()])->save();

                return [
                    'type' => 'failed',
                    'user' => $freshAccount->user,
                    'account' => $freshAccount->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $freshAccount,
                    'details' => $this->failedDetails($freshAccount),
                ];
            }

            $meta = is_array($freshAccount->meta) ? $freshAccount->meta : [];
            $supportNotifiedAt = Arr::get($meta, 'support_notifications.challenge_pass.notified_at');
            $shouldNotifySupport = $freshAccount->challenge_status === 'passed' && blank($supportNotifiedAt);
            $fundedPassAlreadySent = $freshAccount->funded_pass_email_sent_at !== null
                || $freshAccount->passed_email_sent_at !== null;

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
                    Arr::set($meta, 'support_notifications.challenge_pass.notified_at', $sentAt->toIso8601String());
                    Arr::set($meta, 'support_notifications.challenge_pass.completed_phase', $this->completedPhaseLabel($freshAccount));
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
                    'support_details' => $this->supportDetails($freshAccount, $sentAt, $this->completedPhaseLabel($freshAccount)),
                ];
            }

            return null;
        });

        if ($mailPayload === null) {
            return;
        }

        if ($mailPayload['type'] === 'failed') {
            Mail::to($mailPayload['user']->email)->send(new ChallengeFailedMail(
                user: $mailPayload['user'],
                tradingAccount: $mailPayload['account'],
                details: $mailPayload['details'],
            ));
            $this->reviewRequestMailer->sendInitialIfNeeded($mailPayload['account']);

            return;
        }

        if ($mailPayload['send_client'] ?? true) {
            Mail::to($mailPayload['user']->email)->send(new ChallengePassedMail(
                user: $mailPayload['user'],
                tradingAccount: $mailPayload['account'],
                details: $mailPayload['details'],
                certificate: $mailPayload['certificate'] ?? null,
            ));
            $this->reviewRequestMailer->sendInitialIfNeeded($mailPayload['account']);
        }

        if ($mailPayload['send_support'] ?? false) {
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
            'plan' => $this->planLabel($account),
            'rule' => $this->reasonLabel($reason),
            'threshold' => $this->money($threshold),
            'recorded_value' => $this->money($recorded),
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
    private function supportDetails(TradingAccount $account, \DateTimeInterface $sentAt, string $completedPhase): array
    {
        $user = $account->user;

        return [
            'client_name' => (string) ($user?->name ?: 'Trader'),
            'client_email' => (string) ($user?->email ?: 'Not available'),
            'account_reference' => (string) ($account->account_reference ?: 'N/A'),
            'account_id' => (string) $account->id,
            'challenge_type' => $this->challengeTypeLabel($account),
            'completed_phase' => $completedPhase,
            'current_status' => $this->reasonLabel((string) ($account->challenge_status ?: $account->account_status ?: 'passed')),
            'pass_timestamp' => optional($account->passed_at)->toDateTimeString() ?: $sentAt->format('Y-m-d H:i:s'),
            'mt5_login' => (string) ($account->platform_login ?: $account->platform_account_id ?: 'Not available'),
            'mt5_deactivation_status' => (string) (data_get($account->meta, 'mt5_deactivation.events.challenge_pass.status') ?: 'requested'),
        ];
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
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

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }
}
