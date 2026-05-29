<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\Mt5\Mt5AccountDeactivationService;
use Illuminate\Console\Command;

class RetryMt5Deactivation extends Command
{
    protected $signature = 'wolforix:retry-mt5-deactivation
        {login : MT5 login/account reference to disable}
        {--force : Retry even if the last bridge failure is inside the cooldown window}
        {--json : Print JSON diagnostics}';

    protected $description = 'Retry the existing MT5 final-state deactivation bridge for a breached/passed account.';

    public function handle(Mt5AccountDeactivationService $deactivationService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');

            return self::FAILURE;
        }

        $endpoint = trim((string) config('services.mt5_deactivation.endpoint', ''));
        $before = $this->summary($account);
        $config = $this->bridgeConfig($endpoint);

        $this->info('MT5 deactivation bridge retry');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Login', (string) ($account->platform_login ?: $login)],
            ['Trading account', '#'.$account->id],
            ['Account reference', (string) ($account->account_reference ?: '-')],
            ['Endpoint configured', $config['endpoint_configured'] ? 'yes' : 'no'],
            ['Endpoint host', (string) ($config['endpoint_host'] ?: '-')],
            ['Timeout', (string) $config['timeout']],
            ['Connect timeout', (string) $config['connect_timeout']],
            ['HTTP retries', (string) $config['http_retries']],
            ['Retry sleep ms', (string) $config['retry_sleep_ms']],
            ['Current event', (string) ($before['event'] ?: '-')],
            ['Current status', (string) ($before['status'] ?: '-')],
            ['Current source', (string) ($before['source'] ?: '-')],
        ]);

        if ($endpoint === '') {
            $this->error('MT5_DEACTIVATION_ENDPOINT is not configured. Broker bridge cannot execute.');
            $this->warn('Set MT5_DEACTIVATION_ENDPOINT and MT5_DEACTIVATION_TOKEN if required, then run php artisan config:clear && php artisan config:cache before retrying.');
            $this->printJson($login, $before, $before, $config);

            return self::FAILURE;
        }

        $eventKey = (string) ($before['event'] ?: $this->eventKeyForAccount($account));
        $updated = $deactivationService->retryFinalStateBridge($account, $eventKey, $this->deactivationContext($account), (bool) $this->option('force'));
        $after = $this->summary($updated->fresh() ?? $updated);

        $this->newLine();
        $this->info('After retry');
        $this->table(['Field', 'Value'], [
            ['Event', (string) ($after['event'] ?: '-')],
            ['Status', (string) ($after['status'] ?: '-')],
            ['Source', (string) ($after['source'] ?: '-')],
            ['Bridge status', (string) ($after['bridge_status'] ?: '-')],
            ['Executed at', (string) ($after['executed_at'] ?: '-')],
            ['Acknowledged at', (string) ($after['acknowledged_at'] ?: '-')],
            ['Error', (string) ($after['error'] ?: '-')],
        ]);

        $this->printJson($login, $before, $after, $config);

        return $after['status'] === 'disabled' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function bridgeConfig(string $endpoint): array
    {
        return [
            'endpoint_configured' => $endpoint !== '',
            'endpoint_host' => $endpoint !== '' ? (parse_url($endpoint, PHP_URL_HOST) ?: 'configured-endpoint') : null,
            'timeout' => max((int) config('services.mt5_deactivation.timeout', 10), 1),
            'connect_timeout' => max((int) config('services.mt5_deactivation.connect_timeout', 5), 1),
            'http_retries' => max((int) config('services.mt5_deactivation.http_retries', 2), 0),
            'retry_sleep_ms' => max((int) config('services.mt5_deactivation.retry_sleep_ms', 500), 0),
            'retry_after_seconds' => max((int) config('services.mt5_deactivation.retry_after_seconds', 300), 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(TradingAccount $account): array
    {
        $eventKey = (string) data_get($account->meta, 'mt5_deactivation.current_event_key', '');
        $current = (array) data_get($account->meta, 'mt5_deactivation.current', []);
        $event = $eventKey !== ''
            ? (array) data_get($account->meta, "mt5_deactivation.events.{$eventKey}", [])
            : [];

        return [
            'event' => $eventKey !== '' ? $eventKey : null,
            'status' => (string) ($event['status'] ?? $current['status'] ?? $account->platform_status ?: '') ?: null,
            'source' => (string) ($event['source'] ?? $current['source'] ?? '') ?: null,
            'bridge_status' => $event['bridge_status'] ?? $current['bridge_status'] ?? null,
            'executed_at' => $event['executed_at'] ?? $current['executed_at'] ?? null,
            'acknowledged_at' => $event['acknowledged_at'] ?? $current['acknowledged_at'] ?? null,
            'error' => $event['error'] ?? $current['last_error'] ?? $event['bridge_configuration_error'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deactivationContext(TradingAccount $account): array
    {
        return [
            'reason' => (string) ($account->failure_reason ?: 'final_state_locked'),
            'completed_phase' => $account->stage ?: 'Challenge',
            'final_status' => (string) ($account->challenge_status ?: $account->account_status ?: 'locked'),
            'failure_reason' => $account->failure_reason,
            'source' => 'manual_mt5_deactivation_retry',
        ];
    }

    private function eventKeyForAccount(TradingAccount $account): string
    {
        if ($account->challenge_status === 'failed') {
            return 'fail_'.str((string) ($account->failure_reason ?: 'rule_violation'))->slug('_');
        }

        if ($account->challenge_status === 'passed') {
            return 'pass_finalized';
        }

        return 'final_state_locked';
    }

    private function printJson(string $login, array $before, array $after, array $config): void
    {
        if (! (bool) $this->option('json')) {
            return;
        }

        $this->newLine();
        $this->line(json_encode([
            'login' => $login,
            'bridge_config' => $config,
            'before' => $before,
            'after' => $after,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
    }

    private function accountForLogin(string $login): ?TradingAccount
    {
        $account = TradingAccount::query()
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

        if ($account instanceof TradingAccount) {
            return $account;
        }

        return Mt5AccountPoolEntry::query()
            ->where('login', $login)
            ->latest('allocated_at')
            ->latest('id')
            ->first()
            ?->allocatedTradingAccount;
    }
}
