<?php

namespace App\Services\MetaApi;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Services\TradingAccounts\TradingAccountSnapshotApplyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MetaApiLiveSyncService
{
    public function __construct(
        private readonly MetaApiClient $metaApi,
        private readonly TradingAccountSnapshotApplyService $snapshotApplyService,
        private readonly MetaApiAccountMappingRepairService $mappingRepairService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function syncByLogin(string $login, array $options = []): array
    {
        $repair = $this->mappingRepairService->repairByLogin($login, [
            'metaapi_account_id' => $this->manualMetaApiAccountId($options),
            'allow_api_lookup' => true,
        ]);
        $account = $this->findTradingAccountByLogin($login);

        if (! $account instanceof TradingAccount) {
            $recommendation = implode(' ', (array) ($repair['recommendations'] ?? []));

            throw new RuntimeException(trim("No assigned MT5 trading account was found for login {$login}. {$recommendation}"));
        }

        return $this->syncAccount($account, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function syncAccount(TradingAccount $account, array $options = []): array
    {
        $startedAt = now();
        $account = $account->fresh(['challengePlan', 'challengePurchase', 'user']) ?? $account;
        $login = (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference);
        $repair = $this->mappingRepairService->repairAccount($account, [
            'metaapi_account_id' => $this->manualMetaApiAccountId($options),
            'allow_api_lookup' => true,
        ]);
        $account = $account->fresh(['challengePlan', 'challengePurchase', 'user']) ?? $account;
        $metaApiAccountId = $this->manualMetaApiAccountId($options)
            ?? ($this->looksLikeMetaApiAccountId((string) ($repair['metaapi_account_id'] ?? null)) ? (string) $repair['metaapi_account_id'] : null)
            ?? $this->metaApiAccountIdFor($account);

        $log = $this->createSyncLog($account, $startedAt, [
            'login' => $login,
            'metaapi_account_id' => $metaApiAccountId,
            'mapping_repair' => $repair,
        ]);

        $this->markSyncStarted($account, $startedAt);

        if (! $this->looksLikeMetaApiAccountId((string) $metaApiAccountId)) {
            return $this->markFailed(
                account: $account,
                log: $log,
                startedAt: $startedAt,
                status: 'error',
                message: 'MetaApi account id is missing for this assigned MT5 account.',
                error: 'metaapi_account_id_missing',
                metaApi: [
                    'login' => $login,
                    'metaapi_account_id' => $metaApiAccountId,
                    'mapping_repair' => $repair,
                ],
            );
        }

        $accountRead = $this->metaApi->readAccount((string) $metaApiAccountId);
        $accountPayload = (array) ($accountRead['payload'] ?? []);
        $connectionStatus = strtoupper((string) (data_get($accountPayload, 'connectionStatus') ?: 'UNKNOWN'));
        $state = strtoupper((string) (data_get($accountPayload, 'state') ?: 'UNKNOWN'));

        if (! (bool) ($accountRead['ok'] ?? false)) {
            return $this->markFailed(
                account: $account,
                log: $log,
                startedAt: $startedAt,
                status: 'error',
                message: 'MetaApi provisioning account could not be read.',
                error: $this->safeError($accountRead),
                metaApi: $this->metaApiSummary($metaApiAccountId, $accountRead),
            );
        }

        $accountInformation = $this->metaApi->readAccountInformation((string) $metaApiAccountId, true);
        $positions = $this->metaApi->readPositions((string) $metaApiAccountId, true);

        $accountInfoPayload = (array) ($accountInformation['payload'] ?? []);
        $positionRows = $this->rowsFromResult($positions);
        $accountInfoReadable = (bool) ($accountInformation['ok'] ?? false)
            && $this->numericValue($this->firstValue($accountInfoPayload, ['balance', 'Balance'])) !== null
            && $this->numericValue($this->firstValue($accountInfoPayload, ['equity', 'Equity'])) !== null;
        $positionsReadable = (bool) ($positions['ok'] ?? false) && is_array($positions['payload'] ?? null);

        if (! $accountInfoReadable || ! $positionsReadable) {
            return $this->markFailed(
                account: $account,
                log: $log,
                startedAt: $startedAt,
                status: 'error',
                message: 'MetaApi core account sync did not return readable balance/equity and positions.',
                error: $this->coreReadError($accountInformation, $positions),
                metaApi: array_merge($this->metaApiSummary($metaApiAccountId, $accountRead), [
                    'account_information' => $this->responseSummary($accountInformation),
                    'positions' => $this->responseSummary($positions),
                ]),
            );
        }

        $history = $this->history(
            accountId: (string) $metaApiAccountId,
            days: max(1, (int) ($options['history_days'] ?? config('services.metaapi.history.days', 7))),
            limit: max(1, (int) ($options['history_limit'] ?? config('services.metaapi.history.limit', 50))),
        );

        $snapshotAt = now();
        $snapshot = $this->snapshot(
            account: $account,
            accountId: (string) $metaApiAccountId,
            accountRead: $accountRead,
            accountInformation: $accountInformation,
            positionRows: $positionRows,
            history: $history,
            snapshotAt: $snapshotAt,
        );

        $updatedAccount = $this->snapshotApplyService->apply($account, $snapshot, [
            'source' => 'metaapi',
            'started_at' => $startedAt,
            'snapshot_at' => $snapshotAt,
        ]);

        $resultStatus = $history['ok'] ? 'success' : 'partial';
        $validationState = $history['ok'] ? 'CONNECTED' : 'PARTIAL_CONNECTED';
        $this->completeSyncLog($log, $resultStatus, $history['ok']
            ? 'MetaApi live sync completed.'
            : 'MetaApi live sync completed with degraded history.', [
                'metaapi' => $this->metaApiSummary($metaApiAccountId, $accountRead),
                'account_information' => $this->responseSummary($accountInformation),
                'positions' => $this->responseSummary($positions, ['rows' => count($positionRows)]),
                'history' => $history['summary'],
            ]);

        Log::info('MetaApi live account sync completed.', [
            'trading_account_id' => $updatedAccount->id,
            'account_reference' => $updatedAccount->account_reference,
            'login' => $updatedAccount->platform_login,
            'metaapi_account_id' => $metaApiAccountId,
            'status' => $resultStatus,
            'validation_state' => $validationState,
            'connection_status' => $connectionStatus,
            'state' => $state,
            'history_ok' => $history['ok'],
        ]);

        return [
            'status' => $resultStatus,
            'validation_state' => $validationState,
            'trading_account_id' => $updatedAccount->id,
            'login' => $updatedAccount->platform_login,
            'metaapi_account_id' => $metaApiAccountId,
            'deploy_status' => $state,
            'connection_status' => $connectionStatus,
            'account_information_readable' => true,
            'balance' => (float) $updatedAccount->balance,
            'equity' => (float) $updatedAccount->equity,
            'positions_readable' => true,
            'positions_count' => count($positionRows),
            'history_readable' => $history['ok'],
            'history_errors' => $history['errors'],
            'sync_log_id' => $log->id,
            'phase_1b_ready' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function syncAccounts(array $options = []): array
    {
        $limit = max(1, (int) ($options['limit'] ?? config('services.metaapi.sync.limit', 10)));
        $accounts = TradingAccount::query()
            ->where(function ($query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5');
            })
            ->where(function ($query): void {
                $query->whereNull('final_state_locked')
                    ->orWhere('final_state_locked', false);
            })
            ->whereNotIn('account_status', ['failed', 'passed', 'funded'])
            ->orderBy('id')
            ->limit($limit * 5)
            ->get()
            ->filter(fn (TradingAccount $account): bool => $this->looksLikeMetaApiAccountId((string) $this->metaApiAccountIdFor($account))
                || $this->poolEntryForAccount($account) instanceof Mt5AccountPoolEntry)
            ->take($limit)
            ->values();

        $results = [];

        foreach ($accounts as $account) {
            try {
                $results[] = $this->syncAccount($account, $options);
            } catch (Throwable $exception) {
                report($exception);
                $results[] = [
                    'status' => 'error',
                    'trading_account_id' => $account->id,
                    'login' => $account->platform_login,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'status' => collect($results)->contains(fn (array $result): bool => ($result['status'] ?? null) === 'error')
                ? 'partial'
                : 'success',
            'requested_limit' => $limit,
            'synced' => count($results),
            'results' => $results,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnoseByLogin(string $login): array
    {
        $mappingDiagnostic = $this->mappingRepairService->repairByLogin($login, [
            'assign' => true,
            'dry_run' => true,
            'allow_api_lookup' => true,
        ]);
        $account = $this->findTradingAccountByLogin($login);
        $poolEntry = $this->poolEntryForLogin($login, $account);
        $syncLog = $account?->syncLogs()->latest('id')->first();
        $snapshot = $account?->balanceSnapshots()->latest('snapshot_at')->latest('id')->first();

        return [
            'login' => $login,
            'trading_account' => $account instanceof TradingAccount ? [
                'id' => $account->id,
                'account_reference' => $account->account_reference,
                'account_status' => $account->account_status,
                'challenge_status' => $account->challenge_status,
                'platform_status' => $account->platform_status,
                'sync_status' => $account->sync_status,
                'sync_source' => $account->sync_source,
                'last_synced_at' => optional($account->last_synced_at)->toIso8601String(),
                'balance' => $account->balance,
                'equity' => $account->equity,
                'floating_pnl' => $account->profit_loss,
                'final_state_locked' => (bool) $account->final_state_locked,
                'metaapi_account_id' => $this->metaApiAccountIdFor($account),
                'mt5_sync_status' => data_get($account->meta, 'mt5_sync.status'),
                'mt5_sync_error' => data_get($account->meta, 'mt5_sync.last_error'),
            ] : null,
            'pool_entry' => $poolEntry instanceof Mt5AccountPoolEntry ? [
                'id' => $poolEntry->id,
                'login' => $poolEntry->login,
                'server' => $poolEntry->server,
                'allocated_trading_account_id' => $poolEntry->allocated_trading_account_id,
                'source_status' => $poolEntry->source_status,
                'is_available' => (bool) $poolEntry->is_available,
                'metaapi_account_id' => data_get($poolEntry->meta, 'metaapi_account_id'),
            ] : null,
            'latest_sync_log' => $syncLog instanceof TradingAccountSyncLog ? [
                'id' => $syncLog->id,
                'status' => $syncLog->status,
                'message' => $syncLog->message,
                'error_message' => $syncLog->error_message,
                'started_at' => optional($syncLog->started_at)->toIso8601String(),
                'completed_at' => optional($syncLog->completed_at)->toIso8601String(),
            ] : null,
            'latest_snapshot' => $snapshot ? [
                'id' => $snapshot->id,
                'snapshot_at' => optional($snapshot->snapshot_at)->toIso8601String(),
                'balance' => $snapshot->balance,
                'equity' => $snapshot->equity,
                'profit_loss' => $snapshot->profit_loss,
            ] : null,
            'mapping_diagnostics' => $mappingDiagnostic,
        ];
    }

    private function findTradingAccountByLogin(string $login): ?TradingAccount
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        $account = TradingAccount::query()
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login);
            })
            ->where(function ($query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5');
            })
            ->orderByRaw("CASE WHEN challenge_status = 'active' THEN 0 WHEN account_status = 'active' THEN 1 ELSE 2 END")
            ->latest('id')
            ->first();

        if ($account instanceof TradingAccount) {
            return $account;
        }

        $poolEntry = $this->poolEntryForLogin($login);

        return $poolEntry?->allocatedTradingAccount;
    }

    private function metaApiAccountIdFor(TradingAccount $account): ?string
    {
        $candidates = [
            data_get($account->meta, 'metaapi_account_id'),
            data_get($account->meta, 'mt5_sync.metaapi_account_id'),
            data_get($account->meta, 'mt5_sync.metaapi.account_id'),
            data_get($account->meta, 'mt5_pool_entry.metaapi_account_id'),
        ];

        $poolEntry = $this->poolEntryForAccount($account);

        if ($poolEntry instanceof Mt5AccountPoolEntry) {
            $candidates[] = data_get($poolEntry->meta, 'metaapi_account_id');
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($this->looksLikeMetaApiAccountId($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function poolEntryForLogin(string $login, ?TradingAccount $account = null): ?Mt5AccountPoolEntry
    {
        if ($account instanceof TradingAccount) {
            return $this->poolEntryForAccount($account);
        }

        return Mt5AccountPoolEntry::query()
            ->where('login', trim($login))
            ->orderByDesc('allocated_at')
            ->latest('id')
            ->first();
    }

    private function poolEntryForAccount(TradingAccount $account): ?Mt5AccountPoolEntry
    {
        $poolEntryId = data_get($account->meta, 'mt5_pool_entry.id');

        if (is_numeric($poolEntryId)) {
            $poolEntry = Mt5AccountPoolEntry::query()->find((int) $poolEntryId);

            if ($poolEntry instanceof Mt5AccountPoolEntry) {
                return $poolEntry;
            }
        }

        $poolEntry = Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->latest('allocated_at')
            ->latest('id')
            ->first();

        if ($poolEntry instanceof Mt5AccountPoolEntry) {
            return $poolEntry;
        }

        $login = (string) ($account->platform_login ?: $account->platform_account_id);

        if ($login === '') {
            return null;
        }

        return Mt5AccountPoolEntry::query()
            ->where('login', $login)
            ->latest('allocated_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(
        TradingAccount $account,
        string $accountId,
        array $accountRead,
        array $accountInformation,
        array $positionRows,
        array $history,
        Carbon $snapshotAt,
    ): array {
        $accountPayload = (array) ($accountRead['payload'] ?? []);
        $info = (array) ($accountInformation['payload'] ?? []);
        $balance = $this->numericValue($this->firstValue($info, ['balance', 'Balance'])) ?? 0.0;
        $equity = $this->numericValue($this->firstValue($info, ['equity', 'Equity'])) ?? $balance;
        $floatingPnl = $this->floatingPnl($positionRows);
        $closedRows = $history['deals'] !== [] ? $history['deals'] : $history['orders'];
        $server = (string) (data_get($accountPayload, 'server') ?: data_get($info, 'server') ?: $account->platform_environment ?: 'MetaApi');
        $connectionStatus = strtoupper((string) (data_get($accountPayload, 'connectionStatus') ?: 'UNKNOWN'));

        return [
            'balance' => $balance,
            'equity' => $equity,
            'margin' => $this->numericValue($this->firstValue($info, ['margin', 'Margin'])),
            'free_margin' => $this->numericValue($this->firstValue($info, ['freeMargin', 'free_margin', 'FreeMargin'])),
            'leverage' => $this->numericValue($this->firstValue($info, ['leverage', 'Leverage'])),
            'profit_loss' => $floatingPnl ?? round($equity - $balance, 2),
            'open_profit' => $floatingPnl ?? round($equity - $balance, 2),
            'positions_count' => count($positionRows),
            'closed_positions_count' => count($closedRows),
            'trade_count' => count($positionRows) + count($closedRows),
            'activity_count' => count($positionRows) + count($closedRows),
            'has_activity' => count($positionRows) > 0 || count($closedRows) > 0,
            'platform_status' => $connectionStatus === 'CONNECTED' ? 'connected' : 'disconnected',
            'platform_login' => (string) (data_get($info, 'login') ?: data_get($accountPayload, 'login') ?: $account->platform_login),
            'platform_account_id' => (string) (data_get($info, 'login') ?: data_get($accountPayload, 'login') ?: $account->platform_account_id),
            'platform_environment' => $server,
            'server_day' => $snapshotAt->toDateString(),
            'timestamp' => $snapshotAt->toDateTimeString(),
            'sync_trigger' => 'metaapi_live_sync',
            'open_positions' => $positionRows,
            'trade_history' => $closedRows,
            'raw' => [
                'source' => 'metaapi',
                'metaapi_account_id' => $accountId,
                'state' => data_get($accountPayload, 'state'),
                'connection_status' => $connectionStatus,
                'server' => $server,
                'region' => data_get($accountPayload, 'region'),
                'account_information' => $this->sanitizePayload($info),
                'open_positions' => $this->sanitizePayload($positionRows),
                'trade_history' => $this->sanitizePayload($closedRows),
                'history_orders' => $this->sanitizePayload($history['orders']),
                'history_deals' => $this->sanitizePayload($history['deals']),
                'history_status' => $history['ok'] ? 'ok' : 'degraded',
                'history_errors' => $history['errors'],
                'balance' => $balance,
                'equity' => $equity,
                'margin' => $this->numericValue($this->firstValue($info, ['margin', 'Margin'])),
                'free_margin' => $this->numericValue($this->firstValue($info, ['freeMargin', 'free_margin', 'FreeMargin'])),
                'leverage' => $this->numericValue($this->firstValue($info, ['leverage', 'Leverage'])),
                'open_positions_count' => count($positionRows),
                'trade_history_rows' => count($closedRows),
            ],
        ];
    }

    /**
     * @return array{ok: bool, orders: array<int, array<string, mixed>>, deals: array<int, array<string, mixed>>, errors: array<int, string>, summary: array<string, mixed>}
     */
    private function history(string $accountId, int $days, int $limit): array
    {
        $orders = $this->historyEndpoint($accountId, $days, $limit, 'orders');
        $deals = $this->historyEndpoint($accountId, $days, $limit, 'deals');
        $errors = array_values(array_filter(array_merge($orders['errors'], $deals['errors'])));

        return [
            'ok' => $orders['ok'] && $deals['ok'],
            'orders' => $orders['rows'],
            'deals' => $deals['rows'],
            'errors' => $errors,
            'summary' => [
                'orders_ok' => $orders['ok'],
                'deals_ok' => $deals['ok'],
                'orders_rows' => count($orders['rows']),
                'deals_rows' => count($deals['rows']),
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @return array{ok: bool, rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    private function historyEndpoint(string $accountId, int $days, int $limit, string $type): array
    {
        $ranges = array_values(array_unique(array_filter([
            $days,
            min($days, 1),
        ], static fn (int $value): bool => $value > 0)));
        $errors = [];

        foreach ($ranges as $rangeDays) {
            $end = now();
            $start = $end->copy()->subDays($rangeDays);
            $result = $type === 'orders'
                ? $this->metaApi->readHistoryOrdersByTimeRange($accountId, $start, $end, $limit)
                : $this->metaApi->readDealsByTimeRange($accountId, $start, $end, $limit);

            if ((bool) ($result['ok'] ?? false)) {
                return [
                    'ok' => true,
                    'rows' => $this->rowsFromResult($result),
                    'errors' => [],
                ];
            }

            $errors[] = "{$type}_{$rangeDays}d: ".$this->safeError($result);
        }

        return [
            'ok' => false,
            'rows' => [],
            'errors' => $errors,
        ];
    }

    private function markSyncStarted(TradingAccount $account, Carbon $startedAt): void
    {
        $account->forceFill([
            'sync_status' => 'syncing',
            'sync_source' => 'metaapi',
            'last_sync_started_at' => $startedAt,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createSyncLog(TradingAccount $account, Carbon $startedAt, array $payload): TradingAccountSyncLog
    {
        return $account->syncLogs()->create([
            'platform' => 'metaapi',
            'status' => 'syncing',
            'message' => 'MetaApi live sync started.',
            'started_at' => $startedAt,
            'payload' => $this->sanitizePayload($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function completeSyncLog(TradingAccountSyncLog $log, string $status, string $message, array $payload = [], ?string $error = null): void
    {
        $log->forceFill([
            'status' => $status,
            'message' => $message,
            'error_message' => $error,
            'completed_at' => now(),
            'payload' => $this->sanitizePayload(array_merge(is_array($log->payload) ? $log->payload : [], $payload)),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $metaApi
     * @return array<string, mixed>
     */
    private function markFailed(
        TradingAccount $account,
        TradingAccountSyncLog $log,
        Carbon $startedAt,
        string $status,
        string $message,
        string $error,
        array $metaApi = [],
    ): array {
        $completedAt = now();
        $meta = is_array($account->meta) ? $account->meta : [];
        $syncMeta = is_array(data_get($meta, 'mt5_sync')) ? (array) data_get($meta, 'mt5_sync') : [];
        $syncMeta['status'] = $status === 'stale' ? 'stale' : 'disconnected';
        $syncMeta['last_error'] = $error;
        $syncMeta['last_error_at'] = $completedAt->toIso8601String();
        $syncMeta['last_sync_trigger'] = 'metaapi_live_sync';
        $syncMeta['metaapi'] = $metaApi;

        if (filled($metaApi['metaapi_account_id'] ?? null)) {
            $syncMeta['metaapi_account_id'] = (string) $metaApi['metaapi_account_id'];
        }

        $meta['mt5_sync'] = $syncMeta;

        $platformStatus = in_array((string) $account->platform_status, ['disable_requested', 'disable_pending_ack', 'disabled', 'disable_failed', 'disabled_pending_ack'], true)
            ? $account->platform_status
            : 'disconnected';

        $account->forceFill([
            'platform_status' => $platformStatus,
            'sync_status' => 'error',
            'sync_source' => 'metaapi',
            'sync_error' => $error,
            'sync_error_at' => $completedAt,
            'last_sync_started_at' => $startedAt,
            'last_sync_completed_at' => $completedAt,
            'meta' => $meta,
        ])->save();

        $this->completeSyncLog($log, 'error', $message, [
            'metaapi' => $metaApi,
        ], $error);

        Log::warning('MetaApi live account sync failed.', [
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'login' => $account->platform_login,
            'error' => $error,
            'metaapi' => $metaApi,
        ]);

        return [
            'status' => 'error',
            'validation_state' => strtoupper($syncMeta['status']),
            'trading_account_id' => $account->id,
            'login' => $account->platform_login,
            'metaapi_account_id' => $metaApi['metaapi_account_id'] ?? null,
            'deploy_status' => $metaApi['state'] ?? null,
            'connection_status' => $metaApi['connection_status'] ?? null,
            'account_information_readable' => false,
            'positions_readable' => false,
            'history_readable' => false,
            'error' => $error,
            'sync_log_id' => $log->id,
            'phase_1b_ready' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metaApiSummary(?string $accountId, array $accountRead): array
    {
        $payload = (array) ($accountRead['payload'] ?? []);

        return [
            'metaapi_account_id' => $accountId,
            'status' => $accountRead['status'] ?? null,
            'ok' => (bool) ($accountRead['ok'] ?? false),
            'state' => data_get($payload, 'state'),
            'connection_status' => data_get($payload, 'connectionStatus'),
            'server' => data_get($payload, 'server'),
            'region' => data_get($payload, 'region'),
            'login' => data_get($payload, 'login'),
            'error' => $this->safeError($accountRead),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function responseSummary(array $response, array $extra = []): array
    {
        return array_merge([
            'ok' => (bool) ($response['ok'] ?? false),
            'status' => $response['status'] ?? null,
            'action' => $response['action'] ?? null,
            'error' => $this->safeError($response),
        ], $extra);
    }

    private function coreReadError(array $accountInformation, array $positions): string
    {
        $errors = [];

        if (! (bool) ($accountInformation['ok'] ?? false)) {
            $errors[] = 'account_information: '.$this->safeError($accountInformation);
        }

        if (! (bool) ($positions['ok'] ?? false)) {
            $errors[] = 'positions: '.$this->safeError($positions);
        }

        return $errors !== [] ? implode('; ', $errors) : 'core_metaapi_payload_incomplete';
    }

    private function safeError(array $response): ?string
    {
        $error = $response['error'] ?? data_get($response, 'payload.message') ?? data_get($response, 'payload.error');

        if (! filled($error)) {
            return null;
        }

        return $this->sanitizeText((string) $error);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromResult(array $result): array
    {
        return $this->rowsFromPayload($result['payload'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromPayload(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return collect($payload)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): array => $this->sanitizePayload($row))
                ->values()
                ->all();
        }

        foreach (['data', 'items', 'rows', 'records', 'positions', 'orders', 'deals', 'history', 'payload', 'result'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $this->rowsFromPayload($payload[$key]);
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     */
    private function floatingPnl(array $positions): ?float
    {
        if ($positions === []) {
            return 0.0;
        }

        $found = false;
        $total = 0.0;

        foreach ($positions as $position) {
            $profit = $this->numericValue($this->firstValue($position, [
                'profit',
                'Profit',
                'unrealizedProfit',
                'unrealized_profit',
                'floatingProfit',
                'floating_profit',
                'currentProfit',
            ]));

            if ($profit === null) {
                continue;
            }

            $found = true;
            $total += $profit;
            $total += $this->numericValue($this->firstValue($position, ['commission', 'Commission'])) ?? 0.0;
            $total += $this->numericValue($this->firstValue($position, ['swap', 'Swap'])) ?? 0.0;
        }

        return $found ? round($total, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $paths
     */
    private function firstValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizePayload(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return is_string($payload) ? $this->sanitizeText($payload) : $payload;
        }

        $sanitized = [];

        foreach ($payload as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;

            if (preg_match('/(password|token|secret|auth|authorization)/i', $keyString)) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizePayload($value)
                : (is_string($value) ? $this->sanitizeText($value) : $value);
        }

        return $sanitized;
    }

    private function sanitizeText(string $value): string
    {
        $token = (string) config('services.metaapi.token', '');

        if ($token !== '') {
            $value = str_replace($token, '[redacted]', $value);
        }

        return preg_replace('/(password|token|secret|auth-token)=([^&\s]+)/i', '$1=[redacted]', $value) ?: $value;
    }

    private function manualMetaApiAccountId(array $options): ?string
    {
        $accountId = trim((string) ($options['metaapi_account_id'] ?? ''));

        return $accountId !== '' ? $accountId : null;
    }

    private function looksLikeMetaApiAccountId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id));
    }
}
