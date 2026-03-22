<?php

namespace App\Http\Controllers;

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
        $primaryPlan = config('wolforix.challenge_catalog.two_step.plans.50000');
        $secondaryPlan = config('wolforix.challenge_catalog.one_step.plans.25000');

        $primaryAccount = [
            'reference' => 'WFX-CT-50021',
            'plan' => $primaryPlan['name'] ?? '2-Step 50K',
            'platform' => 'cTrader',
            'stage' => __('site.dashboard.account.stage'),
            'status' => __('site.dashboard.account.status'),
            'balance' => 54320.00,
            'starting_balance' => 50000.00,
            'total_profit' => 4320.00,
            'today_profit' => 1625.00,
            'drawdown_percent' => 2.4,
            'consistency_limit_percent' => 40,
            'trading_days_completed' => 2,
            'minimum_trading_days' => 3,
            'next_sync' => __('site.dashboard.account.next_sync'),
        ];

        $consistencyLimitAmount = $primaryAccount['total_profit'] * ($primaryAccount['consistency_limit_percent'] / 100);
        $consistencyUsage = $consistencyLimitAmount > 0
            ? round(($primaryAccount['today_profit'] / $consistencyLimitAmount) * 100, 1)
            : 0.0;

        return [
            'primaryAccount' => $primaryAccount,
            'primaryPlan' => $primaryPlan,
            'secondaryPlan' => $secondaryPlan,
            'summaryCards' => [
                [
                    'label' => __('site.dashboard.cards.balance'),
                    'value' => $this->formatMoney($primaryAccount['balance']),
                    'hint' => __('site.dashboard.card_hints.balance'),
                ],
                [
                    'label' => __('site.dashboard.cards.total_profit'),
                    'value' => $this->formatMoney($primaryAccount['total_profit']),
                    'hint' => __('site.dashboard.card_hints.total_profit'),
                ],
                [
                    'label' => __('site.dashboard.cards.today_profit'),
                    'value' => $this->formatMoney($primaryAccount['today_profit']),
                    'hint' => __('site.dashboard.card_hints.today_profit'),
                ],
                [
                    'label' => __('site.dashboard.cards.drawdown'),
                    'value' => number_format($primaryAccount['drawdown_percent'], 1).'%',
                    'hint' => __('site.dashboard.card_hints.drawdown'),
                ],
            ],
            'consistencyBanner' => [
                'title' => __('site.dashboard.consistency.title'),
                'message' => __('site.dashboard.consistency.message'),
                'meta' => [
                    __('site.dashboard.consistency.meta.today_profit').': '.$this->formatMoney($primaryAccount['today_profit']),
                    __('site.dashboard.consistency.meta.limit').': '.$this->formatMoney($consistencyLimitAmount),
                    __('site.dashboard.consistency.meta.usage').': '.number_format($consistencyUsage, 1).'%',
                ],
            ],
            'accounts' => [
                [
                    'reference' => $primaryAccount['reference'],
                    'plan' => $primaryPlan['name'] ?? $primaryAccount['plan'],
                    'status' => $primaryAccount['status'],
                    'stage' => $primaryAccount['stage'],
                    'balance' => $this->formatMoney($primaryAccount['balance']),
                    'progress' => '54%',
                    'challenge_plan' => $primaryPlan,
                ],
                [
                    'reference' => 'WFX-CT-25014',
                    'plan' => $secondaryPlan['name'] ?? '1-Step 25K',
                    'status' => __('site.dashboard.account.review_status'),
                    'stage' => __('site.dashboard.account.review_stage'),
                    'balance' => $this->formatMoney(26880.00),
                    'progress' => '81%',
                    'challenge_plan' => $secondaryPlan,
                ],
            ],
            'payoutSummary' => [
                'next_window' => __('site.dashboard.payouts.next_window_value'),
                'eligible_profit' => $this->formatMoney(1980.00),
                'cycle_note' => __('site.dashboard.payouts.cycle_note'),
                'status' => __('site.dashboard.payouts.placeholder_status'),
            ],
            'profile' => [
                'name' => 'Wolforix Demo Trader',
                'email' => 'demo@wolforix.test',
                'language' => __('site.languages.'.app()->getLocale()),
                'timezone' => 'Europe/Berlin',
            ],
            'purchasedChallenges' => $this->purchasedChallenges(),
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

        $user->loadMissing(['challengePurchases.order']);

        return $user->challengePurchases
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($purchase): array {
                $order = $purchase->order;

                return [
                    'reference' => $order?->order_number ?? 'N/A',
                    'plan' => ($purchase->challenge_type === 'one_step' ? '1-Step Challenge' : '2-Step Challenge').' / '.((int) ($purchase->account_size / 1000)).'K',
                    'amount' => $this->formatMoney((float) ($order?->final_price ?? 0), $purchase->currency),
                    'payment_provider' => $order?->payment_provider ? ucfirst($order->payment_provider) : 'N/A',
                    'payment_status' => $order?->payment_status ? ucfirst($order->payment_status) : 'N/A',
                    'account_status' => str($purchase->account_status)->replace('_', ' ')->title()->toString(),
                    'created_at' => $purchase->created_at?->format('M d, Y') ?? 'N/A',
                ];
            });
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€'.number_format($amount, 2),
            'GBP' => '£'.number_format($amount, 2),
            default => '$'.number_format($amount, 2),
        };
    }
}
