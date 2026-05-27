<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Support\Mt5ConnectorStatus;
use App\Support\Mt5SyncAnomalyInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DashboardVisibilityCheck extends Command
{
    protected $signature = 'wolforix:dashboard-visibility-check
        {--json : Print JSON diagnostics}';

    protected $description = 'Verify dashboard/admin visibility for the validated MetaApi Phase 1 accounts.';

    public function handle(Mt5SyncAnomalyInspector $inspector, Mt5ConnectorStatus $connectorStatus): int
    {
        $logins = $inspector->phase1ValidatedLogins();
        $rows = collect($logins)
            ->map(fn (string $login): array => $this->visibilityRow($login, $connectorStatus))
            ->values();
        $traderReady = $rows->where('trader_dashboard_data_ready', true)->count();
        $adminReady = $rows->where('admin_dashboard_data_ready', true)->count();
        $missingUiFields = $rows
            ->flatMap(fn (array $row): array => $row['missing_ui_fields'])
            ->unique()
            ->values()
            ->all();
        $recommendations = $rows
            ->pluck('recommendation')
            ->filter(fn (?string $recommendation): bool => filled($recommendation))
            ->unique()
            ->values()
            ->all();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'validated_accounts_checked' => count($logins),
            'validated_accounts' => $logins,
            'trader_dashboard_data_readiness' => $traderReady === count($logins) ? 'ready' : 'needs_attention',
            'admin_dashboard_data_readiness' => $adminReady === count($logins) ? 'ready' : 'needs_attention',
            'latest_sync_status' => $rows
                ->mapWithKeys(fn (array $row): array => [$row['login'] => $row['latest_sync_status']])
                ->all(),
            'missing_ui_fields' => $missingUiFields,
            'recommendations' => $recommendations === [] ? ['No dashboard visibility gaps detected.'] : $recommendations,
            'accounts' => $rows->all(),
        ];

        $this->info('Dashboard visibility check');
        $this->line('Secrets, passwords, tokens, and MetaApi UUIDs are never printed by this command.');
        $this->line('validated accounts checked: '.count($logins));
        $this->line('trader dashboard data readiness: '.$report['trader_dashboard_data_readiness']);
        $this->line('admin dashboard data readiness: '.$report['admin_dashboard_data_readiness']);
        $this->newLine();
        $this->table(
            [
                'login',
                'account',
                'source',
                'connection',
                'lifecycle',
                'onboarding',
                'ready',
                'balance',
                'equity',
                'positions',
                'last_sync',
                'latest_sync',
                'trader_ui',
                'admin_ui',
                'latest_error',
            ],
            $rows->map(fn (array $row): array => [
                $row['login'],
                $row['account_reference'],
                $row['sync_source'],
                $row['connection_status'],
                $row['lifecycle_state'],
                $row['onboarding_state'],
                $row['ready_to_trade'] ? 'yes' : 'no',
                $row['balance'],
                $row['equity'],
                (string) $row['positions_count'],
                $row['last_sync_at'] ?? '-',
                $row['latest_sync_status'],
                $row['trader_dashboard_data_ready'] ? 'ready' : 'needs attention',
                $row['admin_dashboard_data_ready'] ? 'ready' : 'needs attention',
                $row['latest_error'],
            ])->all(),
        );

        if ($missingUiFields !== []) {
            $this->newLine();
            $this->warn('Missing UI/data fields: '.implode(', ', $missingUiFields));
        }

        $this->newLine();
        $this->line('recommendations: '.implode(' | ', $report['recommendations']));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[report unavailable]');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function visibilityRow(string $login, Mt5ConnectorStatus $connectorStatus): array
    {
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            return [
                'login' => $login,
                'account_reference' => 'missing',
                'sync_source' => 'MetaApi',
                'connection_status' => 'missing',
                'lifecycle_state' => 'missing',
                'onboarding_state' => 'missing',
                'ready_to_trade' => false,
                'phase_1_ready' => false,
                'phase_2_ready' => false,
                'balance' => 'N/A',
                'equity' => 'N/A',
                'positions_count' => 0,
                'last_sync_at' => null,
                'latest_sync_status' => 'N/A',
                'latest_error' => 'trading_account_missing',
                'trader_dashboard_data_ready' => false,
                'admin_dashboard_data_ready' => false,
                'missing_ui_fields' => ['trading_account'],
                'recommendation' => "Create or repair the trading account mapping for {$login}.",
            ];
        }

        $connector = $connectorStatus->forAccount($account);
        $latestLog = $account->syncLogs()->latest('id')->first();
        $lastSync = $connector['last_sync_at'] ?? $account->last_synced_at;
        $lifecycleState = (string) data_get($account->meta, 'metaapi_lifecycle.state', 'waiting_for_first_sync');
        $syncHealth = (string) data_get($account->meta, 'metaapi_lifecycle.sync_health', $connector['status']);
        $onboardingState = (string) data_get($account->meta, 'metaapi_onboarding.state', 'pending');
        $readyToTrade = $this->readyToTrade($account);
        $phaseOneReady = $this->phaseOneReady($account, $lifecycleState, $syncHealth);
        $positionsCount = max(0, (int) data_get($account->meta, 'mt5_sync.last_payload_summary.positions_count', 0));
        $latestError = $this->shortError((string) ($account->sync_error ?: $latestLog?->error_message ?: 'None'));
        $missing = $this->missingFields($account, $connector['status'], $lastSync, $lifecycleState, $syncHealth, $onboardingState, $phaseOneReady, $readyToTrade);
        $adminMissing = collect($missing)
            ->diff(['ready_to_trade'])
            ->values()
            ->all();

        return [
            'login' => $login,
            'account_reference' => (string) ($account->account_reference ?? 'N/A'),
            'sync_source' => $this->sourceLabel((string) $account->sync_source),
            'connection_status' => (string) $connector['status'],
            'lifecycle_state' => $lifecycleState,
            'sync_health' => $syncHealth,
            'onboarding_state' => $onboardingState,
            'ready_to_trade' => $readyToTrade,
            'phase_1_ready' => $phaseOneReady,
            'phase_2_ready' => $readyToTrade,
            'balance' => $this->formatMoney((float) $account->balance),
            'equity' => $this->formatMoney((float) $account->equity),
            'positions_count' => $positionsCount,
            'last_sync_at' => $this->formatDateTime($lastSync),
            'latest_sync_status' => (string) ($latestLog?->status ?: $account->sync_status ?: 'N/A'),
            'latest_error' => $latestError,
            'trader_dashboard_data_ready' => $missing === [],
            'admin_dashboard_data_ready' => $adminMissing === [],
            'missing_ui_fields' => $missing,
            'recommendation' => $missing === []
                ? 'No dashboard visibility gaps detected.'
                : "Run php artisan wolforix:sync-metaapi-account {$login} --debug, then refresh the dashboard.",
        ];
    }

    private function accountForLogin(string $login): ?TradingAccount
    {
        $account = TradingAccount::query()
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login)
                    ->orWhere('account_reference', 'like', '%'.$login.'%')
                    ->orWhere('meta->mt5_sync->identifier', $login)
                    ->orWhere('meta->mt5_pool_entry->login', $login);
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

    /**
     * @return list<string>
     */
    private function missingFields(
        TradingAccount $account,
        string $connectionStatus,
        ?Carbon $lastSync,
        string $lifecycleState,
        string $syncHealth,
        string $onboardingState,
        bool $phaseOneReady,
        bool $readyToTrade,
    ): array {
        $missing = [];

        if ((string) $account->sync_source !== 'metaapi') {
            $missing[] = 'sync_source_metaapi';
        }

        if ($connectionStatus !== Mt5ConnectorStatus::CONNECTED) {
            $missing[] = 'connection_status_connected';
        }

        if (! $lastSync instanceof Carbon) {
            $missing[] = 'last_sync_time';
        }

        if (! is_numeric($account->balance)) {
            $missing[] = 'balance';
        }

        if (! is_numeric($account->equity)) {
            $missing[] = 'equity';
        }

        if ($lifecycleState !== 'connected') {
            $missing[] = 'lifecycle_connected';
        }

        if (! in_array($syncHealth, ['connected', 'recovered', 'degraded'], true)) {
            $missing[] = 'sync_health_connected';
        }

        if (! in_array($onboardingState, ['ready_to_trade', 'active'], true)) {
            $missing[] = 'onboarding_ready_or_active';
        }

        if (! $phaseOneReady) {
            $missing[] = 'phase_1_ready';
        }

        if (! $readyToTrade) {
            $missing[] = 'ready_to_trade';
        }

        return array_values(array_unique($missing));
    }

    private function readyToTrade(TradingAccount $account): bool
    {
        $state = (string) data_get($account->meta, 'metaapi_onboarding.state');

        if (! in_array($state, ['ready_to_trade', 'active'], true)) {
            return false;
        }

        if ($account->challenge_status === 'failed'
            || filled((string) $account->failure_reason)
            || (bool) $account->final_state_locked
            || (bool) $account->trading_blocked
        ) {
            return false;
        }

        if (in_array((string) $account->platform_status, ['stale', 'disconnected', 'disabled', 'disable_requested', 'disable_pending_ack', 'disable_failed'], true)) {
            return false;
        }

        if ((string) $account->sync_status === 'error') {
            return false;
        }

        $syncHealth = (string) data_get($account->meta, 'metaapi_lifecycle.sync_health');
        $coreHealth = (string) data_get($account->meta, 'metaapi_lifecycle.core_sync_health', $syncHealth);

        if (in_array($syncHealth, ['stale', 'disconnected'], true) || in_array($coreHealth, ['stale', 'disconnected'], true)) {
            return false;
        }

        return $this->phaseOneReady($account, (string) data_get($account->meta, 'metaapi_lifecycle.state'), $syncHealth);
    }

    private function phaseOneReady(TradingAccount $account, string $lifecycleState, string $syncHealth): bool
    {
        $coreHealth = (string) data_get($account->meta, 'metaapi_lifecycle.core_sync_health', $syncHealth);

        return (string) $account->sync_source === 'metaapi'
            && $lifecycleState === 'connected'
            && in_array($syncHealth, ['connected', 'recovered', 'degraded'], true)
            && in_array($coreHealth, ['connected', 'recovered', 'degraded'], true)
            && $account->last_synced_at !== null
            && is_numeric($account->balance)
            && is_numeric($account->equity)
            && $account->challenge_status !== 'failed'
            && blank((string) $account->failure_reason)
            && ! (bool) $account->final_state_locked;
    }

    private function sourceLabel(string $source): string
    {
        return match (strtolower($source)) {
            'metaapi' => 'MetaApi',
            'mt5_ea' => 'MT5 EA',
            'admin_activation' => 'Admin activation',
            default => $source !== '' ? str($source)->replace('_', ' ')->title()->toString() : 'N/A',
        };
    }

    private function formatMoney(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (! $value instanceof Carbon) {
            return null;
        }

        return $value->toDateTimeString();
    }

    private function shortError(string $error): string
    {
        $error = trim($error);

        if ($error === '') {
            return 'None';
        }

        $error = preg_replace('/(password|token|secret|auth-token)=([^&\s]+)/i', '$1=[redacted]', $error) ?: $error;
        $error = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $error) ?: $error;

        return substr($error, 0, 180);
    }
}
