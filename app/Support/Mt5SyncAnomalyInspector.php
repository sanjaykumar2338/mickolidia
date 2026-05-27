<?php

namespace App\Support;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Mt5SyncAnomalyInspector
{
    private const PHASE1_VALIDATED_LOGINS = [
        '340134',
        '335400',
    ];

    private const PHASE1_EXCLUDED_LOGINS = [
        '335436',
        '52841770',
        '52841775',
    ];

    public function __construct(
        private readonly Mt5ConnectorStatus $connectorStatus,
    ) {}

    /**
     * @return array<int, string>
     */
    public function phase1ValidatedLogins(): array
    {
        return self::PHASE1_VALIDATED_LOGINS;
    }

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
            ->filter(fn (array $row): bool => (bool) $row['active_metaapi_validation_account'])
            ->count();
        $legacyIssues = $anomalies
            ->filter(fn (array $row): bool => ! (bool) $row['is_metaapi'] && ! (bool) $row['excluded_by_phase1_scope'])
            ->count();
        $historicalNotOnboarded = $rows
            ->filter(fn (array $row): bool => (bool) $row['historical_metaapi_not_onboarded'])
            ->count();
        $scopeExcluded = $rows
            ->filter(fn (array $row): bool => (bool) $row['excluded_by_phase1_scope'])
            ->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_accounts' => $rows->count(),
                'validated_accounts' => self::PHASE1_VALIDATED_LOGINS,
                'connected' => $rows->where('connector_status', Mt5ConnectorStatus::CONNECTED)->count(),
                'stale' => $rows->where('connector_status', Mt5ConnectorStatus::STALE)->count(),
                'disconnected' => $rows->where('connector_status', Mt5ConnectorStatus::DISCONNECTED)->count(),
                'errors' => $rows->where('has_error', true)->count(),
                'metaapi_accounts' => $rows->where('is_metaapi', true)->count(),
                'active_metaapi_validation_accounts' => $rows->where('active_metaapi_validation_account', true)->count(),
                'historical_metaapi_not_onboarded' => $historicalNotOnboarded,
                'excluded_by_phase1_scope' => $scopeExcluded,
                'metaapi_connected' => $rows->where('active_metaapi_validation_account', true)->where('connector_status', Mt5ConnectorStatus::CONNECTED)->count(),
                'metaapi_stale' => $rows->where('active_metaapi_validation_account', true)->where('connector_status', Mt5ConnectorStatus::STALE)->count(),
                'metaapi_disconnected' => $rows->where('active_metaapi_validation_account', true)->where('connector_status', Mt5ConnectorStatus::DISCONNECTED)->count(),
                'metaapi_errors' => $rows->where('active_metaapi_validation_account', true)->where('has_error', true)->count(),
                'legacy_accounts' => $rows->where('is_metaapi', false)->count(),
                'legacy_stale' => $rows->where('is_metaapi', false)->where('connector_status', Mt5ConnectorStatus::STALE)->count(),
                'legacy_disconnected' => $rows->where('is_metaapi', false)->where('connector_status', Mt5ConnectorStatus::DISCONNECTED)->count(),
                'legacy_errors' => $rows->where('is_metaapi', false)->where('has_error', true)->count(),
                'metaapi_issues' => $metaApiIssues,
                'historical_metaapi_warnings' => $historicalNotOnboarded,
                'phase1_scope_exclusions' => $scopeExcluded,
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
        $hasMetaApiAccountId = $this->hasMetaApiAccountId($account);
        $missingMetaApiId = $this->hasMissingMetaApiIdError($account, $latestLog);
        $isMetaApi = $this->connectorStatus->isMetaApiAccount($account) || $hasMetaApiAccountId || $missingMetaApiId;
        $hasError = $account->sync_status === 'error'
            || filled((string) $account->sync_error)
            || $latestLog?->status === 'error';
        $phase1ScopeReason = $this->phase1ScopeReason($account, $latestLog, $isMetaApi, $hasMetaApiAccountId);
        $excludedByPhase1Scope = $phase1ScopeReason !== null;
        $historicalNotOnboarded = $phase1ScopeReason === 'historical_not_onboarded_metaapi_account';
        $activeMetaApiValidationAccount = $this->isPhase1ValidatedLogin($account);
        $isAnomaly = $hasError || in_array($status, [Mt5ConnectorStatus::STALE, Mt5ConnectorStatus::DISCONNECTED], true);
        $lastSyncAt = $connector['last_activity_at'] ?? $connector['last_sync_at'] ?? $account->last_synced_at;

        return [
            'trading_account_id' => $account->id,
            'login' => (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference),
            'account_reference' => $account->account_reference,
            'source' => $this->source($account, $isMetaApi),
            'is_metaapi' => $isMetaApi,
            'active_metaapi_validation_account' => $activeMetaApiValidationAccount,
            'historical_metaapi_not_onboarded' => $historicalNotOnboarded,
            'excluded_by_phase1_scope' => $excludedByPhase1Scope,
            'phase1_scope' => $activeMetaApiValidationAccount ? 'validated' : ($excludedByPhase1Scope ? 'excluded' : 'unscoped'),
            'phase1_scope_reason' => $phase1ScopeReason,
            'validated_accounts' => self::PHASE1_VALIDATED_LOGINS,
            'has_metaapi_account_id' => $hasMetaApiAccountId,
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
            'ignored_for_metaapi_signoff' => $isAnomaly && ($excludedByPhase1Scope || ! $isMetaApi),
            'reason' => $this->reason($account, $connector, $latestLog, $isMetaApi, $hasError, $excludedByPhase1Scope),
            'recommended_fix' => $this->recommendedFix($account, $status, $isMetaApi, $hasError, $excludedByPhase1Scope, $phase1ScopeReason),
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
    private function reason(TradingAccount $account, array $connector, ?TradingAccountSyncLog $latestLog, bool $isMetaApi, bool $hasError, bool $excludedByPhase1Scope): string
    {
        if ($excludedByPhase1Scope) {
            return 'excluded_by_phase1_scope';
        }

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

    private function recommendedFix(TradingAccount $account, string $status, bool $isMetaApi, bool $hasError, bool $excludedByPhase1Scope, ?string $phase1ScopeReason): string
    {
        $login = (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference);

        if ($excludedByPhase1Scope) {
            return match ($phase1ScopeReason) {
                'historical_not_onboarded_metaapi_account' => 'Historical MetaApi-shaped record with no MetaApi UUID. Informational only for Phase 1.',
                'icmarkets_demo_account' => 'ICMarkets demo account is outside Phase 1 MetaApi validation scope.',
                'explicitly_excluded_login' => 'This login was explicitly excluded from Phase 1 MetaApi validation scope.',
                default => 'Account is outside Phase 1 MetaApi validation scope.',
            };
        }

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

    private function phase1ScopeReason(
        TradingAccount $account,
        ?TradingAccountSyncLog $latestLog,
        bool $isMetaApi,
        bool $hasMetaApiAccountId,
    ): ?string {
        if ($this->isPhase1ValidatedLogin($account)) {
            return null;
        }

        if ($this->loginIn($account, self::PHASE1_EXCLUDED_LOGINS)) {
            return 'explicitly_excluded_login';
        }

        if ($this->isIcMarketsAccount($account)) {
            return 'icmarkets_demo_account';
        }

        if ($isMetaApi && ! $hasMetaApiAccountId && ! $this->hasMetaApiOnboardingIntent($account) && $this->hasMissingMetaApiIdError($account, $latestLog)) {
            return 'historical_not_onboarded_metaapi_account';
        }

        if ($isMetaApi) {
            return 'not_in_phase1_validated_accounts';
        }

        return null;
    }

    private function hasMissingMetaApiIdError(TradingAccount $account, ?TradingAccountSyncLog $latestLog): bool
    {
        return str_contains((string) $account->sync_error, 'metaapi_account_id_missing')
            || str_contains((string) data_get($account->meta, 'mt5_sync.last_error'), 'metaapi_account_id_missing')
            || str_contains((string) ($latestLog?->error_message ?: ''), 'metaapi_account_id_missing');
    }

    private function hasMetaApiOnboardingIntent(TradingAccount $account): bool
    {
        return filled(data_get($account->meta, 'metaapi_onboarding.state'))
            || filled(data_get($account->meta, 'metaapi_lifecycle.state'))
            || filled(data_get($account->meta, 'mt5_pool_entry.id'));
    }

    private function hasMetaApiAccountId(TradingAccount $account): bool
    {
        $candidates = [
            data_get($account->meta, 'metaapi_account_id'),
            data_get($account->meta, 'mt5_sync.metaapi_account_id'),
            data_get($account->meta, 'mt5_pool_entry.metaapi_account_id'),
        ];

        $poolEntryId = data_get($account->meta, 'mt5_pool_entry.id');

        if (is_numeric($poolEntryId)) {
            $candidates[] = data_get(Mt5AccountPoolEntry::query()->find((int) $poolEntryId)?->meta, 'metaapi_account_id');
        }

        $allocatedPoolId = Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->latest('allocated_at')
            ->latest('id')
            ->value('meta->metaapi_account_id');

        $candidates[] = $allocatedPoolId;

        foreach ($candidates as $candidate) {
            if ($this->looksLikeMetaApiAccountId((string) $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeMetaApiAccountId(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim($id)) === 1;
    }

    private function isPhase1ValidatedLogin(TradingAccount $account): bool
    {
        return $this->loginIn($account, self::PHASE1_VALIDATED_LOGINS);
    }

    /**
     * @param  array<int, string>  $logins
     */
    private function loginIn(TradingAccount $account, array $logins): bool
    {
        $candidates = array_filter([
            (string) $account->platform_login,
            (string) $account->platform_account_id,
            (string) $account->account_reference,
            (string) data_get($account->meta, 'mt5_sync.identifier'),
            (string) data_get($account->meta, 'mt5_pool_entry.login'),
        ]);

        return collect($candidates)
            ->contains(fn (string $candidate): bool => in_array($candidate, $logins, true));
    }

    private function isIcMarketsAccount(TradingAccount $account): bool
    {
        $values = [
            $account->platform_environment,
            data_get($account->meta, 'mt5_pool_entry.server'),
            data_get($account->meta, 'mt5_sync.server'),
            data_get($account->meta, 'mt5_sync.platform_environment'),
        ];

        return collect($values)
            ->contains(fn (mixed $value): bool => str_contains(strtolower((string) $value), 'icmarkets'));
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
