<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class AdminClientController extends Controller
{
    public function index(): View
    {
        $clients = User::query()
            ->with(['profile', 'latestTradingAccount.challengePlan'])
            ->latest()
            ->get()
            ->map(fn (User $user): array => $this->clientTableRow($user));

        return view('admin.clients.index', [
            'clients' => $clients,
        ]);
    }

    public function show(User $user): View
    {
        $user->loadMissing(['profile', 'latestTradingAccount.challengePlan']);

        $latestAccount = $user->latestTradingAccount;
        $currentStatus = $this->resolveStatus($user);

        return view('admin.clients.show', [
            'client' => [
                'id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
                'country' => $user->profile?->country ?? 'N/A',
                'plan_selected' => $this->resolvePlanLabel($user),
                'payment_amount' => $this->resolvePaymentAmount($user),
                'status' => $currentStatus,
            ],
            'metrics' => [
                [
                    'label' => __('site.admin.metrics.profit'),
                    'value' => $this->formatMoney((float) ($latestAccount?->total_profit ?? 0)),
                ],
                [
                    'label' => __('site.admin.metrics.daily_loss'),
                    'value' => $latestAccount?->challengePlan?->daily_loss_limit !== null
                        ? number_format((float) $latestAccount->challengePlan->daily_loss_limit, 0).'%'
                        : '0%',
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
                    'value' => $currentStatus,
                ],
            ],
            'latestAccount' => $latestAccount,
        ]);
    }

    private function clientTableRow(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'country' => $user->profile?->country ?? 'N/A',
            'plan_selected' => $this->resolvePlanLabel($user),
            'payment_amount' => $this->resolvePaymentAmount($user),
            'status' => $this->resolveStatus($user),
        ];
    }

    private function resolvePlanLabel(User $user): string
    {
        if ($user->plan_type !== null && $user->account_size !== null) {
            return sprintf('%s / %dK', $user->plan_type, (int) ($user->account_size / 1000));
        }

        $plan = $user->latestTradingAccount?->challengePlan;

        if ($plan !== null) {
            return $plan->name;
        }

        return 'Not assigned';
    }

    private function resolvePaymentAmount(User $user): string
    {
        $amount = $user->payment_amount;

        if ($amount === null) {
            $amount = $user->latestTradingAccount?->challengePlan?->entry_fee;
        }

        return $amount !== null
            ? $this->formatMoney((float) $amount)
            : '$0.00';
    }

    private function resolveStatus(User $user): string
    {
        $status = $user->status;

        if (($status === null || strtolower((string) $status) === 'active') && $user->latestTradingAccount?->status !== null) {
            $status = $user->latestTradingAccount->status;
        }

        $status ??= 'active';

        return ucfirst(strtolower((string) $status));
    }

    private function formatMoney(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
