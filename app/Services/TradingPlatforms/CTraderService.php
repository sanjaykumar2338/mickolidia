<?php

namespace App\Services\TradingPlatforms;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CTraderService implements TradingPlatformClientInterface
{
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
        return filled(config('services.ctrader.base_url'))
            && filled(config('services.ctrader.access_token'));
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

        $identifier = $account->platform_account_id ?: $account->platform_login;

        if (! is_string($identifier) || trim($identifier) === '') {
            throw new RuntimeException('The trading account is not linked to a cTrader account ID or login.');
        }

        $baseUrl = rtrim((string) config('services.ctrader.base_url'), '/');
        $endpoint = str_replace('{account}', urlencode($identifier), (string) config('services.ctrader.account_endpoint', '/accounts/{account}'));

        $response = Http::timeout((int) config('services.ctrader.timeout', 10))
            ->acceptJson()
            ->withToken((string) config('services.ctrader.access_token'))
            ->get($baseUrl.$endpoint);

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('cTrader returned an unexpected response payload.');
        }

        return $this->normalizePayload($payload, $account);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, TradingAccount $account): array
    {
        $balance = (float) data_get($payload, 'balance', data_get($payload, 'account.balance', $account->balance));
        $equity = (float) data_get($payload, 'equity', data_get($payload, 'account.equity', $account->equity ?: $balance));
        $profitLoss = (float) data_get($payload, 'profit_loss', data_get($payload, 'profitLoss', data_get($payload, 'account.netProfit', $balance - (float) $account->starting_balance)));
        $todayProfit = (float) data_get($payload, 'today_profit', data_get($payload, 'todayProfit', $account->today_profit));
        $maxDrawdown = (float) data_get($payload, 'max_drawdown', data_get($payload, 'maxDrawdown', max((float) $account->starting_balance - min($balance, $equity), 0)));
        $dailyDrawdown = (float) data_get($payload, 'daily_drawdown', data_get($payload, 'dailyDrawdown', $account->daily_drawdown));
        $tradingDays = (int) data_get($payload, 'trading_days_completed', data_get($payload, 'tradingDaysCompleted', $account->trading_days_completed));
        $phaseIndex = (int) data_get($payload, 'phase_index', data_get($payload, 'phaseIndex', $account->phase_index ?: 1));
        $platformAccountId = data_get($payload, 'platform_account_id', data_get($payload, 'accountId', $account->platform_account_id));
        $platformLogin = data_get($payload, 'platform_login', data_get($payload, 'login', $account->platform_login));

        return [
            'platform_account_id' => is_scalar($platformAccountId) && (string) $platformAccountId !== '' ? (string) $platformAccountId : null,
            'platform_login' => is_scalar($platformLogin) && (string) $platformLogin !== '' ? (string) $platformLogin : null,
            'platform_environment' => (string) data_get($payload, 'platform_environment', config('services.ctrader.environment', 'demo')),
            'platform_status' => (string) data_get($payload, 'platform_status', data_get($payload, 'status', 'connected')),
            'balance' => $balance,
            'equity' => $equity,
            'profit_loss' => $profitLoss,
            'total_profit' => (float) data_get($payload, 'total_profit', data_get($payload, 'totalProfit', $profitLoss)),
            'today_profit' => $todayProfit,
            'daily_drawdown' => $dailyDrawdown,
            'max_drawdown' => $maxDrawdown,
            'drawdown_percent' => (float) data_get($payload, 'drawdown_percent'),
            'trading_days_completed' => $tradingDays,
            'account_phase' => (string) data_get($payload, 'account_phase', $account->account_phase ?: 'challenge'),
            'phase_index' => $phaseIndex,
            'account_status' => (string) data_get($payload, 'account_status', 'active'),
            'is_funded' => (bool) data_get($payload, 'is_funded', $account->is_funded),
            'stage' => (string) data_get($payload, 'stage', $account->stage),
            'activated_at' => data_get($payload, 'activated_at'),
            'synced_at' => data_get($payload, 'synced_at', now()->toIso8601String()),
            'raw' => $payload,
        ];
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
            'platform_status' => 'connected',
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
