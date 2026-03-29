<?php

namespace App\Http\Controllers;

use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index', $this->dashboardViewData());
    }

    public function accounts(): View
    {
        return view('dashboard.accounts', $this->dashboardViewData());
    }

    public function payouts(): View
    {
        return view('dashboard.payouts', $this->dashboardViewData());
    }

    public function settings(): View
    {
        return view('dashboard.settings', $this->dashboardViewData());
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardViewData(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return $this->emptyDashboardState();
        }

        $user->loadMissing([
            'profile',
            'challengeTradingAccounts.challengePlan',
            'challengePurchases.order',
        ]);

        $accounts = $user->challengeTradingAccounts
            ->sortByDesc('created_at')
            ->values();

        /** @var TradingAccount|null $primaryAccount */
        $primaryAccount = $accounts->first();
        $primaryPlanDefinition = $primaryAccount instanceof TradingAccount
            ? $this->planDefinitionForAccount($primaryAccount)
            : null;

        return [
            'primaryAccount' => $primaryAccount ? $this->overviewAccountPayload($primaryAccount) : null,
            'primaryPlanDefinition' => $primaryPlanDefinition,
            'summaryCards' => $this->summaryCards($primaryAccount),
            'consistencyBanner' => $this->consistencyBanner($primaryAccount, $user->challengePurchases->count()),
            'accounts' => $accounts->map(fn (TradingAccount $account): array => $this->accountCardPayload($account))->all(),
            'payoutSummary' => $this->payoutSummary($primaryAccount),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'language' => __('site.languages.'.($user->profile?->preferred_language ?? app()->getLocale())),
                'timezone' => $user->profile?->timezone ?? config('app.timezone', 'UTC'),
            ],
            'purchasedChallenges' => $this->purchasedChallenges(),
            'hasTradingAccounts' => $accounts->isNotEmpty(),
            'emptyState' => [
                'title' => 'No challenge accounts linked yet',
                'message' => 'Paid challenges stay visible below. A trading account card appears here once the purchase is provisioned and linked for sync.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardState(): array
    {
        return [
            'primaryAccount' => null,
            'primaryPlanDefinition' => null,
            'summaryCards' => $this->summaryCards(null),
            'consistencyBanner' => $this->consistencyBanner(null, 0),
            'accounts' => [],
            'payoutSummary' => $this->payoutSummary(null),
            'profile' => [
                'name' => '',
                'email' => '',
                'language' => __('site.languages.'.app()->getLocale()),
                'timezone' => config('app.timezone', 'UTC'),
            ],
            'purchasedChallenges' => collect(),
            'hasTradingAccounts' => false,
            'emptyState' => [
                'title' => 'No challenge accounts linked yet',
                'message' => 'Your dashboard will populate here after a paid challenge is provisioned.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewAccountPayload(TradingAccount $account): array
    {
        $plan = $this->planDefinitionForAccount($account);

        return [
            'reference' => $account->account_reference ?? 'N/A',
            'plan' => $account->challengePlan?->name ?? $this->challengeTypeLabel((string) $account->challenge_type).' / '.((int) ($account->account_size / 1000)).'K',
            'platform' => $account->platform,
            'stage' => $account->stage,
            'status' => $account->status,
            'account_status' => $this->humanizeStatus((string) $account->account_status),
            'platform_account_id' => $account->platform_account_id ?: 'Link pending',
            'platform_login' => $account->platform_login ?: 'Link pending',
            'sync_status' => $this->humanizeStatus((string) $account->sync_status),
            'last_synced_at' => $this->formatDateTime($account->last_synced_at),
            'balance' => $this->formatMoney((float) $account->balance),
            'starting_balance' => $this->formatMoney((float) $account->starting_balance),
            'equity' => $this->formatMoney((float) $account->equity),
            'total_profit' => $this->formatMoney((float) $account->total_profit),
            'today_profit' => $this->formatMoney((float) $account->today_profit),
            'drawdown_percent' => number_format((float) $account->drawdown_percent, 1).'%',
            'profit_target_percent' => number_format((float) $account->profit_target_percent, 1).'%',
            'daily_drawdown_limit_percent' => number_format((float) $account->daily_drawdown_limit_percent, 1).'%',
            'max_drawdown_limit_percent' => number_format((float) $account->max_drawdown_limit_percent, 1).'%',
            'minimum_trading_days' => (int) $account->minimum_trading_days,
            'trading_days_completed' => (int) $account->trading_days_completed,
            'progress_value' => max(min((float) $account->profit_target_progress_percent, 100), 0),
            'progress_label' => number_format((float) $account->profit_target_progress_percent, 0).'%',
            'profit_split' => number_format((float) $account->profit_split, 0).'%',
            'payout_eligible_at' => $this->formatDateTime($account->payout_eligible_at),
            'first_payout_eligible_at' => $this->formatDateTime($account->first_payout_eligible_at),
            'sync_error' => $account->sync_error,
            'payout_cycle_days' => (int) ($plan['funded']['payout_cycle_days'] ?? $account->challengePlan?->payout_cycle_days ?? 14),
            'first_payout_days' => (int) ($plan['funded']['first_withdrawal_days'] ?? $account->challengePlan?->first_payout_days ?? 21),
            'leverage' => $plan['phases'][0]['leverage'] ?? null,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function summaryCards(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                [
                    'label' => __('site.dashboard.cards.balance'),
                    'value' => $this->formatMoney(0),
                    'hint' => 'No challenge account linked yet.',
                ],
                [
                    'label' => __('site.dashboard.cards.total_profit'),
                    'value' => $this->formatMoney(0),
                    'hint' => 'Profit metrics will appear after the first sync.',
                ],
                [
                    'label' => __('site.dashboard.cards.today_profit'),
                    'value' => $this->formatMoney(0),
                    'hint' => 'Today\'s result is unavailable until the account starts syncing.',
                ],
                [
                    'label' => __('site.dashboard.cards.drawdown'),
                    'value' => '0.0%',
                    'hint' => 'Risk tracking activates after account provisioning.',
                ],
            ];
        }

        return [
            [
                'label' => __('site.dashboard.cards.balance'),
                'value' => $this->formatMoney((float) $account->balance),
                'hint' => 'Latest synced account balance.',
            ],
            [
                'label' => __('site.dashboard.cards.total_profit'),
                'value' => $this->formatMoney((float) $account->total_profit),
                'hint' => 'Total realized account performance versus the starting balance.',
            ],
            [
                'label' => __('site.dashboard.cards.today_profit'),
                'value' => $this->formatMoney((float) $account->today_profit),
                'hint' => 'Today\'s tracked result from the most recent sync.',
            ],
            [
                'label' => __('site.dashboard.cards.drawdown'),
                'value' => number_format((float) $account->drawdown_percent, 1).'%',
                'hint' => 'Maximum tracked drawdown against the account start balance.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consistencyBanner(?TradingAccount $account, int $purchaseCount): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                'title' => 'Account provisioning',
                'message' => $purchaseCount > 0
                    ? 'Your paid challenge records are saved. Account metrics appear here once the platform account is linked and synced.'
                    : 'Purchase a challenge to create your first tracked trading account.',
                'meta' => [
                    'Paid challenges: '.$purchaseCount,
                    'Platform: cTrader',
                    'Sync status: waiting for account link',
                ],
            ];
        }

        $consistencyLimitAmount = (float) $account->total_profit * (((float) $account->consistency_limit_percent) / 100);
        $consistencyUsage = $consistencyLimitAmount > 0
            ? round((((float) $account->today_profit) / $consistencyLimitAmount) * 100, 1)
            : 0.0;

        if ($consistencyUsage >= 80) {
            return [
                'title' => __('site.dashboard.consistency.title'),
                'message' => __('site.dashboard.consistency.message'),
                'meta' => [
                    __('site.dashboard.consistency.meta.today_profit').': '.$this->formatMoney((float) $account->today_profit),
                    __('site.dashboard.consistency.meta.limit').': '.$this->formatMoney($consistencyLimitAmount),
                    __('site.dashboard.consistency.meta.usage').': '.number_format($consistencyUsage, 1).'%',
                ],
            ];
        }

        return [
            'title' => 'Sync health',
            'message' => 'This dashboard now reads from the latest local account snapshot and rule evaluation state instead of preview-only demo data.',
            'meta' => [
                'Last sync: '.$this->formatDateTime($account->last_synced_at),
                'Platform status: '.$this->humanizeStatus((string) ($account->platform_status ?: 'pending_link')),
                'Trading days: '.sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountCardPayload(TradingAccount $account): array
    {
        return [
            'reference' => $account->account_reference ?? 'N/A',
            'plan' => $account->challengePlan?->name ?? $this->challengeTypeLabel((string) $account->challenge_type).' / '.((int) ($account->account_size / 1000)).'K',
            'status' => $account->status,
            'stage' => $account->stage,
            'balance' => $this->formatMoney((float) $account->balance),
            'equity' => $this->formatMoney((float) $account->equity),
            'progress' => number_format((float) $account->profit_target_progress_percent, 0).'%',
            'progress_value' => max(min((float) $account->profit_target_progress_percent, 100), 0),
            'sync_status' => $this->humanizeStatus((string) $account->sync_status),
            'last_synced_at' => $this->formatDateTime($account->last_synced_at),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function payoutSummary(?TradingAccount $account): array
    {
        if (! $account instanceof TradingAccount) {
            return [
                'next_window' => 'Not available yet',
                'eligible_profit' => $this->formatMoney(0),
                'cycle_note' => 'Payout windows appear after an account reaches funded status.',
                'status' => 'No funded accounts',
            ];
        }

        if (! $account->is_funded) {
            return [
                'next_window' => 'Available after funding',
                'eligible_profit' => $this->formatMoney(0),
                'cycle_note' => 'The current account is still in the challenge lifecycle and is not payout-eligible yet.',
                'status' => $this->humanizeStatus((string) $account->account_status),
            ];
        }

        $eligibleProfit = max((float) $account->total_profit, 0) * (((float) $account->profit_split) / 100);

        return [
            'next_window' => $this->formatDateTime($account->payout_eligible_at),
            'eligible_profit' => $this->formatMoney($eligibleProfit),
            'cycle_note' => 'First payout eligibility: '.$this->formatDateTime($account->first_payout_eligible_at),
            'status' => $this->humanizeStatus((string) $account->account_status),
        ];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function purchasedChallenges(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $user->loadMissing(['challengePurchases.order', 'challengePurchases.tradingAccounts']);

        return $user->challengePurchases
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($purchase): array {
                $order = $purchase->order;
                $linkedAccount = $purchase->tradingAccounts->sortByDesc('created_at')->first();

                return [
                    'reference' => $order?->order_number ?? 'N/A',
                    'plan' => $this->challengeTypeLabel($purchase->challenge_type).' / '.((int) ($purchase->account_size / 1000)).'K',
                    'amount' => $this->formatMoney((float) ($order?->final_price ?? 0), $purchase->currency),
                    'payment_provider' => $order?->payment_provider ? ucfirst($order->payment_provider) : 'N/A',
                    'payment_status' => $order?->payment_status ? ucfirst($order->payment_status) : 'N/A',
                    'account_status' => str($purchase->account_status)->replace('_', ' ')->title()->toString(),
                    'account_reference' => $linkedAccount?->account_reference ?? 'Pending link',
                    'sync_status' => $linkedAccount?->sync_status ? $this->humanizeStatus((string) $linkedAccount->sync_status) : 'Not synced',
                    'created_at' => $purchase->created_at?->format('M d, Y') ?? 'N/A',
                ];
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planDefinitionForAccount(TradingAccount $account): ?array
    {
        $challengeType = (string) $account->challenge_type;
        $accountSize = (int) $account->account_size;

        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}");

        return is_array($definition) ? $definition : null;
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€'.number_format($amount, 2),
            'GBP' => '£'.number_format($amount, 2),
            default => '$'.number_format($amount, 2),
        };
    }

    private function challengeTypeLabel(string $challengeType): string
    {
        return (string) config(
            'wolforix.challenge_catalog.'.$challengeType.'.label',
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );
    }

    private function humanizeStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('M d, Y H:i');
        }

        return 'Not synced yet';
    }
}
