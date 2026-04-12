<?php

namespace App\Services\Challenge;

use App\Mail\ChallengeFailedMail;
use App\Mail\ChallengePassedMail;
use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ChallengeFinalStateMailer
{
    public function __construct(
        private readonly ChallengeCertificateGenerator $certificateGenerator,
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

            if ($freshAccount->challenge_status === 'passed' && ! $fundedPassAlreadySent) {
                $certificate = $this->certificateGenerator->ensureForAccount($freshAccount);
                $sentAt = now();
                $freshAccount->forceFill([
                    'passed_email_sent_at' => $sentAt,
                    'funded_pass_email_sent_at' => $sentAt,
                ])->save();

                return [
                    'type' => 'passed',
                    'user' => $freshAccount->user,
                    'account' => $freshAccount->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $freshAccount,
                    'details' => $this->passedDetails($freshAccount),
                    'certificate' => $certificate,
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

            return;
        }

        Mail::to($mailPayload['user']->email)->send(new ChallengePassedMail(
            user: $mailPayload['user'],
            tradingAccount: $mailPayload['account'],
            details: $mailPayload['details'],
            certificate: $mailPayload['certificate'] ?? null,
        ));
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

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }
}
