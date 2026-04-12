<?php

namespace App\Services\Challenge;

use App\Mail\ChallengeAccountDetailsMail;
use App\Mail\PhaseOnePassedMail;
use App\Mail\PhaseTwoAccountDetailsMail;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ChallengeLifecycleMailer
{
    public function sendPurchaseCredentialsIfNeeded(TradingAccount $account): void
    {
        $payload = DB::transaction(function () use ($account): ?array {
            /** @var TradingAccount|null $freshAccount */
            $freshAccount = TradingAccount::query()
                ->with(['user', 'order', 'challengePlan', 'challengePurchase'])
                ->lockForUpdate()
                ->find($account->id);

            if (! $freshAccount instanceof TradingAccount || $freshAccount->is_trial || $freshAccount->challenge_purchase_email_sent_at !== null) {
                return null;
            }

            $recipient = $this->recipientForAccount($freshAccount);

            if ($recipient === null) {
                return null;
            }

            $freshAccount->forceFill([
                'challenge_purchase_email_sent_at' => now(),
            ])->save();

            return [
                'email' => $recipient['email'],
                'trader_name' => $recipient['name'],
                'account' => $freshAccount->fresh(['user', 'order', 'challengePlan', 'challengePurchase']) ?? $freshAccount,
                'order' => $freshAccount->order,
                'details' => $this->credentialDetails($freshAccount),
            ];
        });

        if ($payload === null) {
            return;
        }

        Mail::to($payload['email'])->send(new ChallengeAccountDetailsMail(
            traderName: $payload['trader_name'],
            tradingAccount: $payload['account'],
            order: $payload['order'],
            details: $payload['details'],
        ));
    }

    public function sendPhaseProgressIfNeeded(TradingAccount $account): void
    {
        $payload = DB::transaction(function () use ($account): ?array {
            /** @var TradingAccount|null $freshAccount */
            $freshAccount = TradingAccount::query()
                ->with(['user', 'order', 'challengePlan', 'challengePurchase'])
                ->lockForUpdate()
                ->find($account->id);

            if (
                ! $freshAccount instanceof TradingAccount
                || $freshAccount->is_trial
                || $freshAccount->challenge_type !== 'two_step'
                || (int) $freshAccount->phase_index < 2
                || $freshAccount->challenge_status === 'failed'
            ) {
                return null;
            }

            $recipient = $this->recipientForAccount($freshAccount);

            if ($recipient === null) {
                return null;
            }

            $shouldSendPhaseOne = $freshAccount->phase_one_pass_email_sent_at === null;
            $shouldSendPhaseTwoCredentials = $freshAccount->phase_two_credentials_email_sent_at === null;

            if (! $shouldSendPhaseOne && ! $shouldSendPhaseTwoCredentials) {
                return null;
            }

            $freshAccount->forceFill(array_filter([
                'phase_one_pass_email_sent_at' => $shouldSendPhaseOne ? now() : null,
                'phase_two_credentials_email_sent_at' => $shouldSendPhaseTwoCredentials ? now() : null,
            ], static fn ($value) => $value !== null))->save();

            return [
                'email' => $recipient['email'],
                'trader_name' => $recipient['name'],
                'account' => $freshAccount->fresh(['user', 'order', 'challengePlan', 'challengePurchase']) ?? $freshAccount,
                'details' => $this->phaseDetails($freshAccount),
                'credentials' => $this->credentialDetails($freshAccount),
                'send_phase_one' => $shouldSendPhaseOne,
                'send_phase_two_credentials' => $shouldSendPhaseTwoCredentials,
            ];
        });

        if ($payload === null) {
            return;
        }

        if ($payload['send_phase_one']) {
            Mail::to($payload['email'])->send(new PhaseOnePassedMail(
                traderName: $payload['trader_name'],
                tradingAccount: $payload['account'],
                details: $payload['details'],
            ));
        }

        if ($payload['send_phase_two_credentials']) {
            Mail::to($payload['email'])->send(new PhaseTwoAccountDetailsMail(
                traderName: $payload['trader_name'],
                tradingAccount: $payload['account'],
                details: $payload['credentials'],
            ));
        }
    }

    /**
     * @return array{email:string,name:string}|null
     */
    private function recipientForAccount(TradingAccount $account): ?array
    {
        $order = $account->order;
        $user = $account->user;
        $email = $order instanceof Order && filled($order->email)
            ? (string) $order->email
            : ($user instanceof User && filled($user->email) ? (string) $user->email : null);

        if ($email === null) {
            return null;
        }

        return [
            'email' => $email,
            'name' => $order instanceof Order && filled($order->full_name)
                ? (string) $order->full_name
                : (string) ($user?->name ?: 'Trader'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function credentialDetails(TradingAccount $account): array
    {
        $login = filled($account->platform_login)
            ? (string) $account->platform_login
            : (filled($account->platform_account_id) ? (string) $account->platform_account_id : 'Pending provisioning');
        $server = $this->metadataValue($account, [
            'mt5_server',
            'server_name',
            'server',
            'platform_server',
            'broker_server',
            'credentials.server',
            'credentials.mt5_server',
        ]) ?: ($account->platform_environment ? $this->humanize((string) $account->platform_environment) : 'Pending provisioning');
        $password = $this->metadataValue($account, [
            'trading_password',
            'password',
            'mt5_password',
            'platform_password',
            'credentials.password',
            'credentials.trading_password',
            'credentials.mt5_password',
        ]) ?: 'Provided separately by Wolforix support';

        return [
            'platform' => (string) ($account->platform ?: 'MT5'),
            'login_id' => $login,
            'password' => $password,
            'server' => $server,
            'account_type' => $this->planLabel($account),
            'account_size' => $this->money((float) ($account->account_size ?: $account->starting_balance ?: 0)),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function phaseDetails(TradingAccount $account): array
    {
        return [
            'plan' => $this->planLabel($account),
            'completed_phase' => 'Phase 1',
            'next_phase' => 'Phase 2',
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private function metadataValue(TradingAccount $account, array $keys): ?string
    {
        foreach ([$account->meta ?? [], $account->rule_state ?? []] as $source) {
            if (! is_array($source)) {
                continue;
            }

            foreach ($keys as $key) {
                $value = Arr::get($source, $key);

                if (is_scalar($value) && filled((string) $value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    private function planLabel(TradingAccount $account): string
    {
        $challengeType = (string) ($account->challenge_type ?: 'challenge');
        $typeLabel = (string) config(
            "wolforix.challenge_catalog.{$challengeType}.label",
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );

        return trim('Wolforix Challenge - '.$typeLabel.' '.$this->money((float) ($account->account_size ?: $account->starting_balance ?: 0)));
    }

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }

    private function humanize(string $value): string
    {
        return str($value)->replace(['_', '-'], ' ')->headline()->toString();
    }
}
