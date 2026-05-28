<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiLiveSyncService;
use Illuminate\Console\Command;
use Throwable;

class RefreshMetaApiMetrics extends Command
{
    protected $signature = 'wolforix:refresh-metaapi-metrics
        {login : MT5 login/account reference to refresh from MetaApi}
        {--history-days=1 : Number of recent days to request from MetaApi history endpoints}
        {--history-limit=50 : Max history rows per MetaApi history endpoint}
        {--debug : Print sanitized JSON result}';

    protected $description = 'Force a MetaApi metrics refresh and print safe balance, equity, position, and today closed P/L diagnostics.';

    public function handle(MetaApiLiveSyncService $syncService): int
    {
        $login = trim((string) $this->argument('login'));

        if ($login === '') {
            $this->error('A non-empty MT5 login is required.');

            return self::FAILURE;
        }

        $before = $this->accountForLogin($login);

        try {
            $result = $syncService->syncByLogin($login, [
                'history_days' => max(1, (int) $this->option('history-days')),
                'history_limit' => max(1, (int) $this->option('history-limit')),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('MetaApi metrics refresh failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $after = $this->accountForLogin($login);
        $snapshot = $after?->balanceSnapshots()->latest('snapshot_at')->latest('id')->first();
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $breakdown = (array) data_get($payload, 'raw.today_profit_breakdown', data_get($payload, 'today_profit_breakdown', []));

        $this->info('MetaApi metrics refresh result');
        $this->table(['Field', 'Value'], [
            ['Login', (string) ($after?->platform_login ?? $login)],
            ['Old balance', $this->value($before?->balance)],
            ['Old equity', $this->value($before?->equity)],
            ['New balance', $this->value($after?->balance)],
            ['New equity', $this->value($after?->equity)],
            ['Open positions count', (string) ($result['positions_count'] ?? data_get($payload, 'positions_count', 0))],
            ['Closed trades today count', (string) ($breakdown['closed_trades_today_count'] ?? 0)],
            ['Gross today profit', $this->value($breakdown['gross_today_profit'] ?? 0)],
            ['Commission', $this->value($breakdown['today_commission'] ?? 0)],
            ['Swap', $this->value($breakdown['today_swap'] ?? 0)],
            ['Net today profit', $this->value($breakdown['net_today_profit'] ?? $after?->today_profit ?? 0)],
            ['Total realized P/L', $this->value($after?->total_profit)],
            ['Latest sync timestamp', optional($after?->last_synced_at)->toDateTimeString() ?: '-'],
        ]);

        if (($result['history_errors'] ?? []) !== []) {
            $this->warn('History degraded: '.implode(' | ', (array) $result['history_errors']));
        }

        if ((bool) $this->option('debug')) {
            $this->newLine();
            $this->line(json_encode([
                'result' => $result,
                'today_profit_breakdown' => $breakdown,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[debug unavailable]');
        }

        return ($result['status'] ?? null) === 'error' ? self::FAILURE : self::SUCCESS;
    }

    private function accountForLogin(string $login): ?TradingAccount
    {
        return TradingAccount::query()
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login)
                    ->orWhere('account_reference', 'like', '%'.$login.'%')
                    ->orWhere('meta->mt5_pool_entry->login', $login);
            })
            ->where(function ($query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5');
            })
            ->latest('id')
            ->first();
    }

    private function value(mixed $value): string
    {
        return is_numeric($value) ? (string) round((float) $value, 2) : '-';
    }
}
