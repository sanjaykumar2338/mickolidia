<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\View\View;

class AdminClientController extends Controller
{
    public function index(): View
    {
        $clients = User::query()
            ->with([
                'profile',
                'latestChallengeTradingAccount.challengePlan',
                'latestTradingAccount.challengePlan',
                'latestOrder.paymentAttempts',
                'latestChallengePurchase.order',
            ])
            ->latest()
            ->get()
            ->map(fn (User $user): array => $this->clientTableRow($user));

        return view('admin.clients.index', [
            'clients' => $clients,
        ]);
    }

    public function show(User $user): View
    {
        $user->loadMissing([
            'profile',
            'latestChallengeTradingAccount.challengePlan',
            'latestTradingAccount.challengePlan',
            'latestOrder.paymentAttempts',
            'latestChallengePurchase.order',
        ]);

        $latestAccount = $this->currentTradingAccount($user);
        $latestOrder = $user->latestOrder;

        return view('admin.clients.show', [
            'client' => [
                'id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
                'country' => $this->resolveCountry($user),
                'plan_selected' => $this->resolvePlanLabel($user),
                'payment_amount' => $this->resolvePaymentAmount($user),
                'payment_provider' => $this->resolvePaymentProvider($user),
                'payment_status' => $this->resolvePaymentStatus($user),
                'order_date' => $this->resolveOrderDate($user),
                'account_status' => $this->resolveAccountStatus($user),
            ],
            'metrics' => [
                [
                    'label' => __('site.admin.metrics.profit'),
                    'value' => $this->formatMoney((float) ($latestAccount?->total_profit ?? 0)),
                ],
                [
                    'label' => 'Balance',
                    'value' => $this->formatMoney((float) ($latestAccount?->balance ?? 0)),
                ],
                [
                    'label' => 'Equity',
                    'value' => $this->formatMoney((float) ($latestAccount?->equity ?? 0)),
                ],
                [
                    'label' => __('site.admin.metrics.max_drawdown'),
                    'value' => number_format((float) ($latestAccount?->drawdown_percent ?? 0), 1).'%',
                ],
                [
                    'label' => __('site.admin.metrics.trading_days'),
                    'value' => $latestAccount !== null
                        ? sprintf(
                            '%d / %d',
                            (int) $latestAccount->trading_days_completed,
                            (int) $latestAccount->minimum_trading_days
                        )
                        : '0 / 0',
                ],
                [
                    'label' => __('site.admin.metrics.current_status'),
                    'value' => $this->resolveAccountStatus($user),
                ],
                [
                    'label' => 'Sync Status',
                    'value' => $latestAccount?->sync_status
                        ? $this->humanizeStatus((string) $latestAccount->sync_status)
                        : 'Not synced',
                ],
            ],
            'latestAccount' => $latestAccount,
            'billing' => [
                'full_name' => $latestOrder?->full_name ?? $user->name,
                'street_address' => $latestOrder?->street_address ?? $user->profile?->street_address ?? 'N/A',
                'city' => $latestOrder?->city ?? $user->profile?->city ?? 'N/A',
                'postal_code' => $latestOrder?->postal_code ?? $user->profile?->postal_code ?? 'N/A',
                'country' => $latestOrder instanceof Order
                    ? $this->countryName($latestOrder->country)
                    : $this->resolveCountry($user),
            ],
            'providerReferences' => [
                'order_number' => $latestOrder?->order_number ?? 'N/A',
                'checkout_id' => $latestOrder?->external_checkout_id ?? 'N/A',
                'payment_id' => $latestOrder?->external_payment_id ?? 'N/A',
                'customer_id' => $latestOrder?->external_customer_id ?? 'N/A',
                'platform_account_id' => $latestAccount?->platform_account_id ?? 'Link pending',
                'platform_login' => $latestAccount?->platform_login ?? 'Link pending',
                'platform_environment' => $latestAccount?->platform_environment ?? 'N/A',
                'last_synced_at' => $this->formatDateTime($latestAccount?->last_synced_at),
                'sync_error' => $latestAccount?->sync_error ?? 'None',
            ],
        ]);
    }

    private function clientTableRow(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'country' => $this->resolveCountry($user),
            'plan_selected' => $this->resolvePlanLabel($user),
            'payment_amount' => $this->resolvePaymentAmount($user),
            'payment_provider' => $this->resolvePaymentProvider($user),
            'payment_status' => $this->resolvePaymentStatus($user),
            'order_date' => $this->resolveOrderDate($user),
            'account_status' => $this->resolveAccountStatus($user),
        ];
    }

    private function resolvePlanLabel(User $user): string
    {
        $purchase = $user->latestChallengePurchase;

        if ($purchase !== null) {
            return sprintf(
                '%s / %dK',
                $this->challengeTypeLabel($purchase->challenge_type),
                (int) ($purchase->account_size / 1000),
            );
        }

        $order = $user->latestOrder;

        if ($order !== null) {
            return sprintf(
                '%s / %dK',
                $this->challengeTypeLabel($order->challenge_type),
                (int) ($order->account_size / 1000),
            );
        }

        if ($user->plan_type !== null && $user->account_size !== null) {
            return sprintf('%s / %dK', $user->plan_type, (int) ($user->account_size / 1000));
        }

        $plan = $this->currentTradingAccount($user)?->challengePlan;

        return $plan?->name ?? 'Not assigned';
    }

    private function resolvePaymentAmount(User $user): string
    {
        if ($user->latestOrder instanceof Order) {
            return $this->formatMoney((float) $user->latestOrder->final_price, $user->latestOrder->currency);
        }

        $amount = $user->payment_amount;

        if ($amount === null) {
            $amount = $this->currentTradingAccount($user)?->challengePlan?->entry_fee;
        }

        return $amount !== null
            ? $this->formatMoney((float) $amount)
            : '$0.00';
    }

    private function resolvePaymentProvider(User $user): string
    {
        return $user->latestOrder?->payment_provider !== null
            ? ucfirst((string) $user->latestOrder->payment_provider)
            : 'N/A';
    }

    private function resolvePaymentStatus(User $user): string
    {
        return $user->latestOrder?->payment_status !== null
            ? ucfirst((string) $user->latestOrder->payment_status)
            : 'N/A';
    }

    private function resolveOrderDate(User $user): string
    {
        return $user->latestOrder?->created_at?->format('Y-m-d H:i') ?? 'N/A';
    }

    private function resolveAccountStatus(User $user): string
    {
        $account = $this->currentTradingAccount($user);
        $purchaseStatus = $user->latestChallengePurchase?->account_status;

        if ($account?->account_status !== null && ($account->account_status !== 'pending_activation' || $purchaseStatus === null)) {
            return $this->humanizeStatus((string) $account->account_status);
        }

        if ($purchaseStatus !== null) {
            return $this->humanizeStatus((string) $purchaseStatus);
        }

        $status = $user->status;

        if (($status === null || strtolower((string) $status) === 'active') && $account?->status !== null) {
            $status = $account->status;
        }

        $status ??= 'active';

        return ucfirst(strtolower((string) $status));
    }

    private function resolveCountry(User $user): string
    {
        if ($user->profile?->country) {
            return $user->profile->country;
        }

        if ($user->latestOrder?->country) {
            return $this->countryName($user->latestOrder->country);
        }

        return 'N/A';
    }

    private function currentTradingAccount(User $user): ?TradingAccount
    {
        return $user->latestChallengeTradingAccount ?? $user->latestTradingAccount;
    }

    private function challengeTypeLabel(string $challengeType): string
    {
        return (string) config(
            'wolforix.challenge_catalog.'.$challengeType.'.label',
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );
    }

    private function countryName(string $countryCode): string
    {
        return config("wolforix.checkout_countries.{$countryCode}", $countryCode);
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€'.number_format($amount, 2),
            'GBP' => '£'.number_format($amount, 2),
            default => '$'.number_format($amount, 2),
        };
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return 'Not synced yet';
    }

    private function humanizeStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }
}
