<?php

namespace App\Services\TradingPlatforms;

use App\Models\TradingAccount;
use App\Services\CTraderService as LiveCTraderService;
use RuntimeException;

class CTraderService implements TradingPlatformClientInterface
{
    public function __construct(
        private readonly LiveCTraderService $ctraderService,
    ) {
    }

    public function slug(): string
    {
        return 'ctrader';
    }

    public function isEnabled(): bool
    {
        return (bool) config('trading.platforms.ctrader.enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->ctraderService->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAccountSnapshot(TradingAccount $account): array
    {
        if ((bool) config('trading.platforms.ctrader.use_mock_data', false)) {
            return $this->mockSnapshot($account);
        }

        if (! $this->isEnabled()) {
            throw new RuntimeException('cTrader sync is disabled.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('cTrader credentials are missing.');
        }

        return $this->ctraderService->syncAccountData($account);
    }

    /**
     * @return array<string, mixed>
     */
    private function mockSnapshot(TradingAccount $account): array
    {
        $startingBalance = (float) ($account->starting_balance ?: $account->account_size ?: 0);
        $existingProfit = (float) $account->total_profit;
        $profitDelta = round(max($startingBalance * 0.012, 120), 2);
        $totalProfit = $existingProfit !== 0.0 ? $existingProfit : $profitDelta;
        $balance = round($startingBalance + $totalProfit, 2);
        $equity = round($balance - max($profitDelta * 0.1, 35), 2);
        $dailyDrawdown = round(max($startingBalance - $equity, 0), 2);
        $maxDrawdown = round(max($startingBalance - min($balance, $equity), 0), 2);

        return [
            'platform_account_id' => $account->platform_account_id ?: 'mock-'.$account->id,
            'platform_login' => $account->platform_login ?: 'mock-login-'.$account->id,
            'platform_environment' => (string) config('services.ctrader.environment', 'demo'),
            'platform_status' => 'mock',
            'balance' => $balance,
            'equity' => $equity,
            'profit_loss' => $totalProfit,
            'total_profit' => $totalProfit,
            'today_profit' => round(max($profitDelta * 0.35, 40), 2),
            'daily_drawdown' => $dailyDrawdown,
            'max_drawdown' => $maxDrawdown,
            'trading_days_completed' => max((int) $account->trading_days_completed, 2),
            'account_phase' => $account->account_phase ?: 'challenge',
            'phase_index' => max((int) $account->phase_index, 1),
            'account_status' => $account->activated_at === null ? 'active' : ($account->account_status ?: 'active'),
            'is_funded' => (bool) $account->is_funded,
            'stage' => $account->stage,
            'activated_at' => optional($account->activated_at ?? now()->subDays(3))->toIso8601String(),
            'synced_at' => now()->toIso8601String(),
            'raw' => [
                'source' => 'mock',
                'balance' => $balance,
                'equity' => $equity,
            ],
        ];
    }
}
