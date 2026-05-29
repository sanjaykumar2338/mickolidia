<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiAccountLifecycleService;
use Illuminate\Console\Command;

class DiagnoseSyncHealth extends Command
{
    protected $signature = 'wolforix:diagnose-sync-health
        {login : MT5 login/account reference to inspect}
        {--json : Print JSON diagnostics}';

    protected $description = 'Inspect MetaApi sync health, stale state, retries, and recovery status.';

    public function handle(MetaApiAccountLifecycleService $lifecycleService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');

            return self::FAILURE;
        }

        $diagnostic = $lifecycleService->diagnose($account);
        $latestLog = $account->syncLogs()->latest('id')->first();
        $deactivation = $this->deactivationSummary($account);

        $this->info('MetaApi sync health diagnosis');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Login', (string) ($diagnostic['login'] ?? $login)],
            ['Trading account', (string) ($diagnostic['trading_account_id'] ?? '-')],
            ['Account status', (string) ($account->account_status ?: '-')],
            ['Challenge status', (string) ($account->challenge_status ?: '-')],
            ['Trading blocked', (bool) $account->trading_blocked ? 'yes' : 'no'],
            ['Sync health', (string) ($diagnostic['sync_health'] ?? '-')],
            ['Lifecycle state', (string) ($diagnostic['lifecycle_state'] ?? '-')],
            ['Connector status', (string) ($diagnostic['connector_status'] ?? '-')],
            ['Ready to trade', ! empty($diagnostic['ready_to_trade']) ? 'yes' : 'no'],
            ['Last sync', (string) ($diagnostic['last_sync_at'] ?? '-')],
            ['Sync age seconds', (string) ($diagnostic['sync_age_seconds'] ?? '-')],
            ['Stale threshold minutes', (string) ($diagnostic['stale_threshold_minutes'] ?? '-')],
            ['Is stale', ! empty($diagnostic['is_stale']) ? 'yes' : 'no'],
            ['MT5 deactivation event', (string) ($deactivation['event'] ?: '-')],
            ['MT5 deactivation status', (string) ($deactivation['status'] ?: '-')],
            ['MT5 deactivation source', (string) ($deactivation['source'] ?: '-')],
            ['MT5 deactivation error', (string) ($deactivation['error'] ?: '-')],
            ['Retry attempts', (string) data_get($diagnostic, 'retry_state.attempts', 0)],
            ['Next retry', (string) (data_get($diagnostic, 'retry_state.next_retry_at') ?: '-')],
            ['Last retry error', (string) (data_get($diagnostic, 'retry_state.last_error') ?: '-')],
            ['Latest sync log', $latestLog ? '#'.$latestLog->id : '-'],
            ['Latest sync status', (string) ($latestLog?->status ?: '-')],
            ['Latest sync error', (string) ($latestLog?->error_message ?: '-')],
            ['Recovery count', (string) data_get($diagnostic, 'recovery.recovery_count', 0)],
            ['Last recovered', (string) (data_get($diagnostic, 'recovery.last_recovered_at') ?: '-')],
        ]);

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode(array_merge($diagnostic, [
                'challenge_status' => $account->challenge_status,
                'account_status' => $account->account_status,
                'trading_blocked' => (bool) $account->trading_blocked,
                'mt5_deactivation' => $deactivation,
                'latest_sync_log' => $latestLog ? [
                    'id' => $latestLog->id,
                    'status' => $latestLog->status,
                    'message' => $latestLog->message,
                    'error_message' => $latestLog->error_message,
                    'completed_at' => optional($latestLog->completed_at)->toIso8601String(),
                ] : null,
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{event: string|null, status: string|null, source: string|null, error: string|null}
     */
    private function deactivationSummary(TradingAccount $account): array
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
            'error' => (string) ($event['error'] ?? $current['error'] ?? '') ?: null,
        ];
    }

    private function accountForLogin(string $login): ?TradingAccount
    {
        $account = TradingAccount::query()
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login)
                    ->orWhere('account_reference', 'like', '%'.$login.'%');
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
