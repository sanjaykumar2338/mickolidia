<?php

namespace App\Support;

use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Mt5SyncAnomalyInspector
{
    public function __construct(
        private readonly Mt5ConnectorStatus $connectorStatus,
    ) {}

    /**
     * @return Collection<int, TradingAccount>
     */
    public function mt5Accounts(): Collection
    {
        return TradingAccount::query()
            ->where(function ($query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5')
                    ->orWhere('platform', 'MT5 Demo');
            })
            ->latest('id')
            ->get();
    }

    /**
     * @param  Collection<int, TradingAccount>|null  $accounts
     * @return array<string, mixed>
     */
    public function report(?Collection $accounts = null, int $limit = 50): array
    {
        $rows = ($accounts ?? $this->mt5Accounts())
            ->map(fn (TradingAccount $account): array => $this->classify($account))
            ->values();

        $anomalies = $rows
            ->filter(fn (array $row): bool => (bool) $row['is_anomaly'])
            ->values();

        $metaApiIssues = $anomalies
            ->filter(fn (array $row): bool => (bool) $row['is_metaapi'])
            ->count();
        $legacyIssues = $anomalies
            ->filter(fn (array $row): bool => ! (bool) $row['is_metaapi'])
            ->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_accounts' => $rows->count(),
                'connected' => $rows->where('connector_status', Mt5ConnectorStatus::CONNECTED)->count(),
                'stale' => $rows->where('connector_status', Mt5ConnectorStatus::STALE)->count(),
                'disconnected' => $rows->where('connector_status', Mt5ConnectorStatus::DISCONNECTED)->count(),
                'errors' => $rows->where('has_error', true)->count(),
                'metaapi_accounts' => $rows->where('is_metaapi', true)->count(),
                'metaapi_connected' => $rows->where('is_metaapi', true)->where('connector_status', Mt5ConnectorStatus::CONNECTED)->count(),
                'metaapi_stale' => $rows->where('is_metaapi', true)->where('connector_status', Mt5ConnectorStatus::STALE)->count(),
                'metaapi_disconnected' => $rows->where('is_metaapi', true)->where('connector_status', Mt5ConnectorStatus::DISCONNECTED)->count(),
                'metaapi_errors' => $rows->where('is_metaapi', true)->where('has_error', true)->count(),
                'legacy_accounts' => $rows->where('is_metaapi', false)->count(),
                'legacy_stale' => $rows->where('is_metaapi', false)->where('connector_status', Mt5ConnectorStatus::STALE)->count(),
                'legacy_disconnected' => $rows->where('is_metaapi', false)->where('connector_status', Mt5ConnectorStatus::DISCONNECTED)->count(),
                'legacy_errors' => $rows->where('is_metaapi', false)->where('has_error', true)->count(),
                'metaapi_issues' => $metaApiIssues,
                'legacy_ignored_for_metaapi_signoff' => $legacyIssues,
            ],
            'status' => $metaApiIssues === 0 ? 'ready' : 'needs_attention',
            'anomalies' => $anomalies
                ->take(max(1, $limit))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function classify(TradingAccount $account): array
    {
        $connector = $this->connectorStatus->forAccount($account);
        $status = (string) $connector['status'];
        $latestLog = $account->syncLogs()->latest('id')->first();
        $isMetaApi = $this->connectorStatus->isMetaApiAccount($account);
        $hasError = $account->sync_status === 'error'
            || filled((string) $account->sync_error)
            || $latestLog?->status === 'error';
        $isAnomaly = $hasError || in_array($status, [Mt5ConnectorStatus::STALE, Mt5ConnectorStatus::DISCONNECTED], true);
        $lastSyncAt = $connector['last_activity_at'] ?? $connector['last_sync_at'] ?? $account->last_synced_at;

        return [
            'trading_account_id' => $account->id,
            'login' => (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference),
            'account_reference' => $account->account_reference,
            'source' => $this->source($account, $isMetaApi),
            'is_metaapi' => $isMetaApi,
            'connector_status' => $status,
            'platform_status' => (string) $account->platform_status,
            'sync_status' => (string) $account->sync_status,
            'lifecycle_state' => (string) data_get($account->meta, 'metaapi_lifecycle.state', '-'),
            'sync_health' => (string) data_get($account->meta, 'metaapi_lifecycle.sync_health', '-'),
            'mt5_sync_status' => (string) data_get($account->meta, 'mt5_sync.status', '-'),
            'last_sync_at' => $lastSyncAt instanceof Carbon ? $lastSyncAt->toIso8601String() : null,
            'sync_age_seconds' => $lastSyncAt instanceof Carbon ? (int) $lastSyncAt->diffInSeconds(now(), false) : null,
            'timeout_seconds' => (int) $connector['timeout_seconds'],
            'has_error' => $hasError,
            'latest_sync_log_id' => $latestLog?->id,
            'latest_sync_log_status' => $latestLog?->status,
            'latest_sync_log_error' => $this->shortError((string) ($latestLog?->error_message ?: '')),
            'is_anomaly' => $isAnomaly,
            'ignored_for_metaapi_signoff' => $isAnomaly && ! $isMetaApi,
            'reason' => $this->reason($account, $connector, $latestLog, $isMetaApi, $hasError),
            'recommended_fix' => $this->recommendedFix($account, $status, $isMetaApi, $hasError),
        ];
    }

    private function source(TradingAccount $account, bool $isMetaApi): string
    {
        if ($isMetaApi) {
            return 'metaapi';
        }

        $source = trim((string) $account->sync_source);

        return $source !== '' ? $source : 'legacy_ea';
    }

    /**
     * @param  array<string, mixed>  $connector
     */
    private function reason(TradingAccount $account, array $connector, ?TradingAccountSyncLog $latestLog, bool $isMetaApi, bool $hasError): string
    {
        if ($hasError) {
            return 'sync_error: '.$this->shortError((string) ($account->sync_error ?: $latestLog?->error_message ?: 'latest sync log failed'));
        }

        $status = (string) $connector['status'];

        if (! $isMetaApi && $status === Mt5ConnectorStatus::STALE) {
            return 'legacy_ea_no_recent_heartbeat_or_metric_sync';
        }

        if (! $isMetaApi && $status === Mt5ConnectorStatus::DISCONNECTED) {
            return 'legacy_ea_explicitly_disconnected_or_waiting_for_first_sync';
        }

        if ($status === Mt5ConnectorStatus::STALE) {
            if (in_array((string) data_get($account->meta, 'metaapi_lifecycle.sync_health'), ['stale', 'disconnected'], true)) {
                return 'metaapi_lifecycle_marked_stale_or_disconnected';
            }

            return 'last_successful_metaapi_sync_older_than_threshold';
        }

        if ($status === Mt5ConnectorStatus::DISCONNECTED) {
            return 'platform_or_metaapi_status_disconnected';
        }

        return 'no_sync_anomaly_detected';
    }

    private function recommendedFix(TradingAccount $account, string $status, bool $isMetaApi, bool $hasError): string
    {
        $login = (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference);

        if (! $isMetaApi) {
            return 'Legacy EA fallback account. Verify the EA heartbeat if this account is still active, otherwise ignore it for MetaApi Phase 1 signoff.';
        }

        if (! filled(data_get($account->meta, 'metaapi_account_id')) && ! filled(data_get($account->meta, 'mt5_sync.metaapi_account_id'))) {
            return "Run php artisan wolforix:repair-metaapi-account {$login} --assign before syncing.";
        }

        if ($hasError || in_array($status, [Mt5ConnectorStatus::STALE, Mt5ConnectorStatus::DISCONNECTED], true)) {
            return "Run php artisan wolforix:sync-metaapi-account {$login} --debug, then php artisan wolforix:diagnose-sync-health {$login}.";
        }

        return 'No action required.';
    }

    private function shortError(string $error): string
    {
        $error = trim($error);

        if ($error === '') {
            return '-';
        }

        $error = preg_replace('/(password|token|secret|auth-token)=([^&\s]+)/i', '$1=[redacted]', $error) ?: $error;
        $error = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $error) ?: $error;

        return substr($error, 0, 180);
    }
}
