<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\Order;
use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class ChallengePurchaseConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    /**
     * @var array<string, string>|null
     */
    public ?array $accountAccessDetails = null;

    public ?string $accountReference = null;

    public bool $credentialsReady = false;

    public function __construct(public Order $order)
    {
        if ($this->order->exists) {
            $this->order->loadMissing([
                'challengePurchase.tradingAccounts',
                'tradingAccounts',
            ]);
        }

        $account = $this->primaryTradingAccount();

        if (! $account instanceof TradingAccount) {
            return;
        }

        $this->accountReference = filled($account->account_reference)
            ? (string) $account->account_reference
            : null;
        $this->accountAccessDetails = $this->buildAccountAccessDetails($account);
        $this->credentialsReady = filled($this->accountAccessDetails['login_id'] ?? null)
            && filled($this->accountAccessDetails['server'] ?? null)
            && filled($this->accountAccessDetails['password'] ?? null)
            && ! in_array($this->accountAccessDetails['login_id'], ['Pending provisioning', 'Link pending'], true)
            && $this->accountAccessDetails['password'] !== 'Provided separately by Wolforix support';
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Wolforix Challenge Purchase Confirmation');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-confirmation',
        );
    }

    private function primaryTradingAccount(): ?TradingAccount
    {
        $purchaseAccounts = $this->order->challengePurchase?->tradingAccounts;

        if ($purchaseAccounts !== null && $purchaseAccounts->isNotEmpty()) {
            /** @var TradingAccount|null $account */
            $account = $purchaseAccounts->sortBy('phase_index')->first();

            return $account;
        }

        if ($this->order->relationLoaded('tradingAccounts') && $this->order->tradingAccounts->isNotEmpty()) {
            /** @var TradingAccount|null $account */
            $account = $this->order->tradingAccounts->sortBy('phase_index')->first();

            return $account;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function buildAccountAccessDetails(TradingAccount $account): array
    {
        $login = $this->accountLogin($account) ?? 'Pending provisioning';
        $server = $this->accountServer($account)
            ?: ($account->platform_environment ? $this->humanize((string) $account->platform_environment) : 'Pending provisioning');
        $password = $this->accountPassword($account) ?: 'Provided separately by Wolforix support';

        return [
            'platform' => (string) ($account->platform ?: 'Trading Account'),
            'login_id' => $login,
            'password' => $password,
            'server' => $server,
            'account_type' => $this->planLabel($account),
            'account_size' => $this->money((float) ($account->account_size ?: $account->starting_balance ?: $this->order->account_size ?: 0)),
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

    private function planLabel(TradingAccount $account): string
    {
        $challengeType = (string) ($account->challenge_type ?: $this->order->challenge_type ?: 'challenge');
        $typeLabel = (string) config(
            "wolforix.challenge_catalog.{$challengeType}.label",
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );

        return trim('Wolforix Challenge - '.$typeLabel.' '.$this->money((float) ($account->account_size ?: $account->starting_balance ?: $this->order->account_size ?: 0)));
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
