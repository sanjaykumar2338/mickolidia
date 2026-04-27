<?php

namespace App\Services\Challenge;

use App\Mail\ChallengeAccountDetailsMail;
use App\Mail\ChallengePhasePassSupportNotificationMail;
use App\Mail\ChallengePurchaseConfirmationMail;
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
    public function sendPurchaseConfirmationIfNeeded(Order $order): bool
    {
        $payload = DB::transaction(function () use ($order): ?array {
            /** @var Order|null $freshOrder */
            $freshOrder = Order::query()
                ->with(['challengePurchase', 'user'])
                ->lockForUpdate()
                ->find($order->id);

            if (! $freshOrder instanceof Order || ! $freshOrder->challengePurchase) {
                return null;
            }

            $recipient = $this->recipientForOrder($freshOrder);

            if ($recipient === null) {
                return null;
            }

            $metadata = $freshOrder->metadata ?? [];

            if (Arr::has($metadata, 'emails.purchase_confirmation_sent_at')) {
                return null;
            }

            Arr::set($metadata, 'emails.purchase_confirmation_sent_at', now()->toIso8601String());

            $freshOrder->forceFill([
                'metadata' => $metadata,
            ])->save();

            return [
                'email' => $recipient['email'],
                'order' => $freshOrder->fresh(['challengePurchase', 'user']) ?? $freshOrder,
            ];
        });

        if ($payload === null) {
            return false;
        }

        Mail::to($payload['email'])->send(new ChallengePurchaseConfirmationMail($payload['order']));

        return true;
    }

    public function sendPurchaseCredentialsIfNeeded(TradingAccount $account): bool
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

            $details = $this->readyCredentialDetails($freshAccount);

            if ($details === null) {
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
                'details' => $details,
            ];
        });

        if ($payload === null) {
            return false;
        }

        Mail::to($payload['email'])->send(new ChallengeAccountDetailsMail(
            traderName: $payload['trader_name'],
            tradingAccount: $payload['account'],
            order: $payload['order'],
            details: $payload['details'],
        ));

        return true;
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

            $eventKey = 'phase_1_pass_finalized';
            $shouldSendPhaseOne = $freshAccount->phase_one_pass_email_sent_at === null;
            $shouldSendPhaseTwoCredentials = $freshAccount->phase_two_credentials_email_sent_at === null;
            $meta = is_array($freshAccount->meta) ? $freshAccount->meta : [];
            $shouldNotifySupport = blank(Arr::get($meta, "support_notifications.events.{$eventKey}.notified_at"));

            if (! $shouldSendPhaseOne && ! $shouldSendPhaseTwoCredentials && ! $shouldNotifySupport) {
                return null;
            }

            $sentAt = now();

            if ($shouldNotifySupport) {
                Arr::set($meta, "support_notifications.events.{$eventKey}.notified_at", $sentAt->toIso8601String());
                Arr::set($meta, "support_notifications.events.{$eventKey}.final_status", 'phase_passed');
                Arr::set($meta, "support_notifications.events.{$eventKey}.reason", 'phase_passed');
                Arr::set($meta, "support_notifications.events.{$eventKey}.phase", 'Phase 1');
            }

            $freshAccount->forceFill(array_filter([
                'phase_one_pass_email_sent_at' => $shouldSendPhaseOne ? $sentAt : null,
                'phase_two_credentials_email_sent_at' => $shouldSendPhaseTwoCredentials ? $sentAt : null,
                'meta' => $shouldNotifySupport ? $meta : null,
            ], static fn ($value) => $value !== null))->save();

            return [
                'email' => $recipient['email'],
                'trader_name' => $recipient['name'],
                'account' => $freshAccount->fresh(['user', 'order', 'challengePlan', 'challengePurchase']) ?? $freshAccount,
                'details' => $this->phaseDetails($freshAccount),
                'credentials' => $this->credentialDetails($freshAccount),
                'send_phase_one' => $shouldSendPhaseOne,
                'send_phase_two_credentials' => $shouldSendPhaseTwoCredentials,
                'send_support' => $shouldNotifySupport,
                'support_details' => $this->supportDetails($freshAccount, $sentAt, 'Phase 1'),
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

        if ($payload['send_support'] && $payload['account']->user instanceof User) {
            Mail::to((string) config('wolforix.support.email'))->send(new ChallengePhasePassSupportNotificationMail(
                user: $payload['account']->user,
                tradingAccount: $payload['account'],
                details: $payload['support_details'],
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
     * @return array{email:string}|null
     */
    private function recipientForOrder(Order $order): ?array
    {
        $user = $order->user;
        $email = filled($order->email)
            ? (string) $order->email
            : ($user instanceof User && filled($user->email) ? (string) $user->email : null);

        if ($email === null) {
            return null;
        }

        return [
            'email' => $email,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function credentialDetails(TradingAccount $account): array
    {
        $deliverableDetails = $this->readyCredentialDetails($account);

        if ($deliverableDetails !== null) {
            return $deliverableDetails;
        }

        $login = $this->accountLogin($account) ?? 'Pending provisioning';
        $server = $this->accountServer($account) ?: ($account->platform_environment ? $this->humanize((string) $account->platform_environment) : 'Pending provisioning');
        $password = $this->accountPassword($account) ?: 'Provided separately by Wolforix support';

        return [
            'platform' => (string) ($account->platform ?: 'MT5'),
            'broker' => $this->accountBroker($account) ?: 'FusionMarkets',
            'login_id' => $login,
            'password' => $password,
            'investor_password' => $this->accountInvestorPassword($account) ?: 'Investor password pending',
            'server' => $server,
            'account_type' => $this->planLabel($account),
            'account_size' => $this->money((float) ($account->account_size ?: $account->starting_balance ?: 0)),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function readyCredentialDetails(TradingAccount $account): ?array
    {
        $login = $this->accountLogin($account);
        $server = $this->accountServer($account);
        $password = $this->accountPassword($account);

        if (! filled($login) || ! filled($server) || ! filled($password)) {
            return null;
        }

        return [
            'platform' => (string) ($account->platform ?: 'MT5'),
            'broker' => $this->accountBroker($account) ?: 'FusionMarkets',
            'login_id' => $login,
            'password' => $password,
            'investor_password' => $this->accountInvestorPassword($account) ?: 'Investor password pending',
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
            'phase' => $completedPhase,
            'final_status' => 'Phase Passed',
            'reason' => 'Phase Passed',
            'finalized_at' => $sentAt->format('Y-m-d H:i:s'),
            'mt5_login' => (string) ($account->platform_login ?: $account->platform_account_id ?: 'Not available'),
            'mt5_deactivation_status' => (string) str((string) (data_get($account->meta, 'mt5_deactivation.current.status') ?: $account->platform_status ?: 'pending'))->replace('_', ' ')->title(),
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

    private function accountLogin(TradingAccount $account): ?string
    {
        if (filled($account->platform_login)) {
            return (string) $account->platform_login;
        }

        if (filled($account->platform_account_id)) {
            return (string) $account->platform_account_id;
        }

        return null;
    }

    private function accountServer(TradingAccount $account): ?string
    {
        return $this->metadataValue($account, [
            'mt5_server',
            'server_name',
            'server',
            'platform_server',
            'broker_server',
            'credentials.server',
            'credentials.mt5_server',
        ]);
    }

    private function accountBroker(TradingAccount $account): ?string
    {
        return $this->metadataValue($account, [
            'broker',
            'provider',
            'mt5_pool_entry.broker',
            'credentials.broker',
            'credentials.provider',
        ]);
    }

    private function accountPassword(TradingAccount $account): ?string
    {
        return $this->metadataValue($account, [
            'trading_password',
            'password',
            'mt5_password',
            'platform_password',
            'credentials.password',
            'credentials.trading_password',
            'credentials.mt5_password',
        ]);
    }

    private function accountInvestorPassword(TradingAccount $account): ?string
    {
        return $this->metadataValue($account, [
            'investor_password',
            'readonly_password',
            'read_only_password',
            'mt5_investor_password',
            'credentials.investor_password',
            'credentials.readonly_password',
            'credentials.read_only_password',
            'credentials.mt5_investor_password',
        ]);
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

    private function challengeTypeLabel(TradingAccount $account): string
    {
        $challengeType = (string) ($account->challenge_type ?: 'challenge');

        return (string) (config("wolforix.challenge_catalog.{$challengeType}.label") ?: str($challengeType)->replace('_', ' ')->title());
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
