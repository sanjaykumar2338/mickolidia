<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Services\TradingAccounts\TradingAccountSnapshotApplyService;
use App\Support\Mt5ConnectorCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TradingAccountMetricsController extends Controller
{
    private const DECIMAL_FIELDS = [
        'balance',
        'equity',
        'starting_balance',
        'broker_starting_balance',
        'broker_phase_reference_balance',
        'open_profit',
        'profit_loss',
        'total_profit',
        'today_profit',
        'daily_drawdown',
        'max_drawdown',
        'highest_equity_today',
        'daily_loss_used',
        'daily_loss_limit',
        'max_drawdown_used',
        'max_drawdown_limit',
        'volume',
    ];

    private const INTEGER_FIELDS = [
        'trade_count',
        'activity_count',
        'trading_days',
        'trading_days_completed',
        'phase_index',
        'positions_count',
        'closed_positions_count',
    ];

    private const BOOLEAN_FIELDS = [
        'has_activity',
        'is_funded',
        'trading_blocked_ack',
    ];

    private const TRIMMED_STRING_FIELDS = [
        'platform_status',
        'platform_environment',
        'platform_account_id',
        'platform_login',
        'account_phase',
        'phase',
        'phase_label',
        'challenge_id',
        'challenge_status',
        'sync_trigger',
        'server_day',
    ];

    public function __invoke(
        Request $request,
        string $accountIdentifier,
        TradingAccountSnapshotApplyService $snapshotApplyService,
        Mt5ConnectorCredentials $connectorCredentials,
    ): JsonResponse {
        $account = $this->resolveAccount($accountIdentifier, $request);
        $startedAt = now();
        $sanitizedPayload = $this->sanitizePayload($request->all());
        $rawSyncTrigger = $this->normalizeString($request->input('sync_trigger')) ?: 'unknown';

        Log::info('MT5 metrics payload received.', [
            'account_identifier' => $accountIdentifier,
            'account_reference' => $account->account_reference,
            'trading_account_id' => $account->id,
            'platform_login' => $account->platform_login,
            'sync_trigger' => $rawSyncTrigger,
            'ip' => $request->ip(),
            'payload_summary' => $this->payloadSummary($sanitizedPayload),
            'payload' => $sanitizedPayload,
        ]);

        if ($authFailure = $this->authorizeIngestion($request, $account, $connectorCredentials, $accountIdentifier, $startedAt, $sanitizedPayload)) {
            return $authFailure;
        }

        $log = TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => $account->platform_slug ?: 'mt5',
            'status' => 'started',
            'message' => "MT5 metrics update received ({$rawSyncTrigger}).",
            'started_at' => $startedAt,
            'payload' => $sanitizedPayload,
        ]);

        try {
            $normalizedPayload = $this->normalizePayload($request->all(), $accountIdentifier);
        } catch (\InvalidArgumentException $exception) {
            $this->markRejectedSync(
                account: $account,
                log: $log,
                startedAt: $startedAt,
                reason: 'payload_normalization_failed',
                message: $exception->getMessage(),
                payload: $sanitizedPayload,
            );

            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        try {
            $validated = Validator::make($normalizedPayload, [
                'balance' => ['required', 'numeric'],
                'equity' => ['required', 'numeric'],
                'starting_balance' => ['nullable', 'numeric'],
                'broker_starting_balance' => ['nullable', 'numeric'],
                'broker_phase_reference_balance' => ['nullable', 'numeric'],
                'open_profit' => ['nullable', 'numeric'],
                'profit_loss' => ['nullable', 'numeric'],
                'total_profit' => ['nullable', 'numeric'],
                'today_profit' => ['nullable', 'numeric'],
                'daily_drawdown' => ['nullable', 'numeric'],
                'max_drawdown' => ['nullable', 'numeric'],
                'highest_equity_today' => ['nullable', 'numeric'],
                'daily_loss_used' => ['nullable', 'numeric'],
                'max_drawdown_used' => ['nullable', 'numeric'],
                'timestamp' => ['required', 'date'],
                'server_day' => ['nullable', 'date'],
                'platform_status' => ['nullable', 'string', 'max:255'],
                'platform_environment' => ['nullable', 'string', 'max:255'],
                'platform_account_id' => ['nullable', 'string', 'max:255'],
                'platform_login' => ['nullable', 'string', 'max:255'],
                'trade_count' => ['nullable', 'integer', 'min:0'],
                'activity_count' => ['nullable', 'integer', 'min:0'],
                'has_activity' => ['nullable', 'boolean'],
                'volume' => ['nullable', 'numeric', 'min:0'],
                'positions_count' => ['nullable', 'integer', 'min:0'],
                'closed_positions_count' => ['nullable', 'integer', 'min:0'],
                'trading_blocked_ack' => ['nullable', 'boolean'],
                'phase_index' => ['nullable', 'integer', 'min:1'],
                'account_phase' => ['nullable', 'string', 'max:255'],
                'sync_trigger' => ['nullable', 'string', 'max:255'],
                'trading_days_completed' => ['nullable', 'integer', 'min:0'],
                'is_funded' => ['nullable', 'boolean'],
                'raw' => ['nullable', 'array'],
            ])->validate();
        } catch (ValidationException $exception) {
            $this->markRejectedSync(
                account: $account,
                log: $log,
                startedAt: $startedAt,
                reason: 'payload_validation_failed',
                message: 'MT5 metrics payload validation failed.',
                payload: $sanitizedPayload,
                context: ['errors' => $exception->errors()],
            );

            return response()->json([
                'status' => 'error',
                'message' => 'MT5 metrics payload rejected.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $syncTrigger = (string) ($validated['sync_trigger'] ?? 'timer');
        $this->logPlatformIdentityMismatch($account, $validated, $accountIdentifier);

        Log::info('MT5 metrics update received.', [
            'account_identifier' => $accountIdentifier,
            'account_reference' => $account->account_reference,
            'trading_account_id' => $account->id,
            'sync_trigger' => $syncTrigger,
            'balance' => $validated['balance'],
            'equity' => $validated['equity'],
            'open_profit' => $validated['open_profit'] ?? null,
            'trade_count' => $validated['trade_count'] ?? null,
            'activity_count' => $validated['activity_count'] ?? null,
            'positions_count' => $validated['positions_count'] ?? null,
            'closed_positions_count' => $validated['closed_positions_count'] ?? null,
            'has_activity' => $validated['has_activity'] ?? null,
            'timestamp' => $validated['timestamp'],
            'server_day' => $validated['server_day'] ?? null,
            'stored_platform_login' => $account->platform_login,
            'incoming_platform_login' => $validated['platform_login'] ?? null,
        ]);

        try {
            $updatedAccount = $snapshotApplyService->apply($account, array_merge($validated, [
                'raw' => $sanitizedPayload,
            ]), [
                'source' => 'mt5_ea',
                'started_at' => $startedAt,
                'snapshot_at' => $validated['timestamp'],
            ]);

            $log->forceFill([
                'status' => data_get($updatedAccount->meta, 'mt5_sync.last_ignored_reason') === 'stale_timestamp' ? 'ignored' : 'success',
                'message' => data_get($updatedAccount->meta, 'mt5_sync.last_ignored_reason') === 'stale_timestamp'
                    ? "MT5 metrics ignored as stale ({$syncTrigger})."
                    : "MT5 metrics applied successfully ({$syncTrigger}).",
                'completed_at' => now(),
            ])->save();

            Log::info('MT5 metrics update applied.', [
                'account_identifier' => $accountIdentifier,
                'account_reference' => $updatedAccount->account_reference,
                'sync_trigger' => $syncTrigger,
                'challenge_status' => $updatedAccount->challenge_status,
                'phase_index' => (int) $updatedAccount->phase_index,
                'trading_days_completed' => (int) $updatedAccount->trading_days_completed,
                'balance' => (float) $updatedAccount->balance,
                'equity' => (float) $updatedAccount->equity,
                'profit_loss' => (float) $updatedAccount->profit_loss,
                'total_profit' => (float) $updatedAccount->total_profit,
                'today_profit' => (float) $updatedAccount->today_profit,
                'last_synced_at' => optional($updatedAccount->last_synced_at)->toIso8601String(),
                'ignored_reason' => data_get($updatedAccount->meta, 'mt5_sync.last_ignored_reason'),
            ]);

            return response()->json([
                'status' => 'ok',
                'account_id' => $updatedAccount->id,
                'account_reference' => $updatedAccount->account_reference,
                'challenge_status' => $updatedAccount->challenge_status,
                'phase_index' => (int) $updatedAccount->phase_index,
                'trading_days_completed' => (int) $updatedAccount->trading_days_completed,
                'failure_reason' => $updatedAccount->failure_reason,
                'trading_blocked' => (bool) $updatedAccount->trading_blocked,
                'final_state_locked' => (bool) $updatedAccount->final_state_locked,
                'close_positions_required' => $this->shouldRequestPositionClosure($updatedAccount),
                'mt5_deactivation_required' => $this->mt5DeactivationRequired($updatedAccount),
                'mt5_deactivation_event' => $this->mt5DeactivationEvent($updatedAccount, includeDisabled: true)['event'] ?? null,
                'mt5_deactivation_status' => $this->mt5DeactivationEvent($updatedAccount, includeDisabled: true)['status'] ?? null,
                'ea_action' => $this->eaAction($updatedAccount),
                'last_synced_at' => optional($updatedAccount->last_synced_at)->toIso8601String(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            $log->forceFill([
                'status' => 'error',
                'message' => "MT5 metrics update failed ({$syncTrigger}).",
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            TradingAccount::query()->whereKey($account->id)->update([
                'sync_status' => 'error',
                'sync_source' => 'mt5_ea',
                'sync_error' => $exception->getMessage(),
                'sync_error_at' => now(),
                'last_sync_started_at' => $startedAt,
                'last_sync_completed_at' => now(),
            ]);

            Log::error('MT5 metrics update failed.', [
                'account_identifier' => $accountIdentifier,
                'sync_trigger' => $syncTrigger,
                'reason' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'MT5 metrics update failed.',
            ], 500);
        }
    }

    private function authorizeIngestion(
        Request $request,
        TradingAccount $account,
        Mt5ConnectorCredentials $connectorCredentials,
        string $accountIdentifier,
        Carbon $startedAt,
        array $sanitizedPayload,
    ): ?JsonResponse
    {
        $providedToken = (string) ($request->bearerToken() ?: $request->input('secret_token', ''));

        if ($connectorCredentials->tokenMatches($account, $providedToken)) {
            return null;
        }

        Log::warning('MT5 metrics token rejected.', [
            'account_identifier' => $accountIdentifier,
            'account_reference' => $account->account_reference,
            'has_token' => $providedToken !== '',
            'ip' => $request->ip(),
        ]);

        TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => $account->platform_slug ?: 'mt5',
            'status' => 'rejected',
            'message' => 'MT5 metrics token rejected.',
            'error_message' => 'Invalid token',
            'started_at' => $startedAt,
            'completed_at' => now(),
            'payload' => $sanitizedPayload,
        ]);

        return response()->json([
            'error' => 'Invalid token',
        ], 401);
    }

    private function shouldRequestPositionClosure(TradingAccount $account): bool
    {
        return $this->mt5DeactivationRequired($account);
    }

    private function eaAction(TradingAccount $account): string
    {
        if ($this->mt5DeactivationRequired($account)) {
            return 'close_all_positions_and_disable_account';
        }

        if ((bool) $account->trading_blocked) {
            return 'block_trading';
        }

        return 'continue';
    }

    /**
     * @return array{event:string,status:string}|null
     */
    private function mt5DeactivationEvent(TradingAccount $account, bool $includeDisabled = false): ?array
    {
        if ($account->platform_slug !== 'mt5') {
            return null;
        }

        $current = data_get($account->meta, 'mt5_deactivation.current');

        if (is_array($current)) {
            $status = (string) ($current['status'] ?? '');

            if ($status !== '' && ($includeDisabled || $status !== 'disabled')) {
                return [
                    'event' => (string) ($current['event'] ?? ''),
                    'status' => $status,
                ];
            }
        }

        $events = (array) data_get($account->meta, 'mt5_deactivation.events', []);

        foreach ($events as $eventKey => $event) {
            if (! is_array($event)) {
                continue;
            }

            $status = (string) ($event['status'] ?? '');

            if ($status === '' || (! $includeDisabled && $status === 'disabled')) {
                continue;
            }

            return [
                'event' => (string) $eventKey,
                'status' => $status,
            ];
        }

        return null;
    }

    private function mt5DeactivationRequired(TradingAccount $account): bool
    {
        return $this->mt5DeactivationEvent($account) !== null;
    }

    private function resolveAccount(string $accountIdentifier, Request $request): TradingAccount
    {
        $account = TradingAccount::query()
            ->where('account_reference', $accountIdentifier)
            ->first();

        if (! $account instanceof TradingAccount) {
            Log::warning('MT5 metrics account reference was not found.', [
                'account_identifier' => $accountIdentifier,
                'ip' => $request->ip(),
                'payload_keys' => array_keys($request->all()),
            ]);

            throw ValidationException::withMessages([
                'account' => 'Trading account not found for the provided identifier.',
            ]);
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        return $this->sanitizePayloadValue($payload);
    }

    private function sanitizePayloadValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);

            if (str_contains($normalizedKey, 'token') || str_contains($normalizedKey, 'password') || str_contains($normalizedKey, 'secret')) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = $this->sanitizePayloadValue($item);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, string $accountIdentifier): array
    {
        $payload = $this->normalizeScalarFields($payload);
        $timestamp = $payload['timestamp'] ?? $payload['server_time'] ?? null;
        $accountPhase = $payload['account_phase'] ?? $payload['phase'] ?? null;
        $serverDay = $payload['server_day'] ?? null;

        if ($timestamp !== null) {
            $sourceField = array_key_exists('timestamp', $payload) ? 'timestamp' : 'server_time';
            $parsedTimestamp = $this->parseServerTime($timestamp, $sourceField, $accountIdentifier);

            if ($parsedTimestamp instanceof Carbon) {
                $payload['timestamp'] = $parsedTimestamp->toDateTimeString();

                if (($payload['server_day'] ?? null) === null) {
                    $payload['server_day'] = $parsedTimestamp->toDateString();
                }
            }
        }

        if ($serverDay !== null) {
            $parsedServerDay = $this->parseDateValue($serverDay, 'server_day', $accountIdentifier);

            if ($parsedServerDay instanceof Carbon) {
                $payload['server_day'] = $parsedServerDay->toDateString();
            }
        }

        if (($payload['trading_days_completed'] ?? null) === null && array_key_exists('trading_days', $payload)) {
            $payload['trading_days_completed'] = $payload['trading_days'];
        }

        if (($payload['account_phase'] ?? null) === null && is_string($accountPhase) && $accountPhase !== '') {
            $payload['account_phase'] = $accountPhase;
        }

        if (($payload['phase_index'] ?? null) === null) {
            $phaseIndex = $this->phaseIndexFromAlias($accountPhase);

            if ($phaseIndex !== null) {
                $payload['phase_index'] = $phaseIndex;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeScalarFields(array $payload): array
    {
        foreach (self::DECIMAL_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->normalizeDecimal($payload[$field]);
            }
        }

        foreach (self::INTEGER_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->normalizeInteger($payload[$field]);
            }
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->normalizeBoolean($payload[$field]);
            }
        }

        foreach (self::TRIMMED_STRING_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->normalizeString($payload[$field]);
            }
        }

        return $payload;
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return $value;
    }

    private function normalizeInteger(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return $value;
    }

    private function normalizeBoolean(mixed $value): mixed
    {
        if (is_string($value)) {
            $normalized = filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $value;
    }

    private function normalizeString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseServerTime(mixed $value, string $sourceField, string $accountIdentifier): ?Carbon
    {
        return $this->parseDateValue($value, $sourceField, $accountIdentifier, [
            'Y.m.d H:i:s',
            'Y-m-d H:i:s',
        ]);
    }

    /**
     * @param  list<string>  $formats
     */
    private function parseDateValue(
        mixed $value,
        string $sourceField,
        string $accountIdentifier,
        array $formats = ['Y.m.d', 'Y-m-d'],
    ): ?Carbon {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $rawValue = trim($value);

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $rawValue);

                if ($parsed instanceof Carbon) {
                    Log::info('MT5 metrics timestamp normalized.', [
                        'account_identifier' => $accountIdentifier,
                        'source_field' => $sourceField,
                        'raw_value' => $rawValue,
                        'matched_format' => $format,
                    ]);

                    return $parsed;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            $parsed = Carbon::parse($rawValue);

            Log::info('MT5 metrics timestamp normalized.', [
                'account_identifier' => $accountIdentifier,
                'source_field' => $sourceField,
                'raw_value' => $rawValue,
                'matched_format' => 'carbon_parse',
            ]);

            return $parsed;
        } catch (\Throwable $exception) {
            Log::warning('MT5 metrics timestamp normalization failed.', [
                'account_identifier' => $accountIdentifier,
                'source_field' => $sourceField,
                'raw_value' => $rawValue,
                'reason' => $exception->getMessage(),
            ]);

            throw new \InvalidArgumentException("Invalid {$sourceField} format");
        }
    }

    private function phaseIndexFromAlias(mixed $phase): ?int
    {
        if (is_numeric($phase)) {
            return max((int) $phase, 1);
        }

        if (! is_string($phase) || $phase === '') {
            return null;
        }

        return match (strtolower(trim($phase))) {
            'one_step', 'single_phase', 'phase_1', 'phase1', 'phase 1', 'challenge_step_1', 'challenge step 1', 'two_step_phase_1' => 1,
            'phase_2', 'phase2', 'phase 2', 'challenge_step_2', 'challenge step 2', 'two_step_phase_2' => 2,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    private function markRejectedSync(
        TradingAccount $account,
        TradingAccountSyncLog $log,
        Carbon $startedAt,
        string $reason,
        string $message,
        array $payload,
        array $context = [],
    ): void {
        $completedAt = now();

        $log->forceFill([
            'status' => 'rejected',
            'message' => $message,
            'error_message' => $reason,
            'completed_at' => $completedAt,
        ])->save();

        $meta = is_array($account->meta) ? $account->meta : [];
        $syncMeta = is_array(data_get($meta, 'mt5_sync')) ? (array) data_get($meta, 'mt5_sync') : [];
        $syncMeta = array_merge($syncMeta, [
            'status' => 'rejected',
            'last_rejected_at' => $completedAt->toIso8601String(),
            'last_rejected_reason' => $reason,
            'last_error' => $message,
            'last_payload_summary' => $this->payloadSummary($payload),
        ]);

        if ($context !== []) {
            $syncMeta['last_rejected_context'] = $context;
        }

        $meta['mt5_sync'] = $syncMeta;

        $account->forceFill([
            'sync_status' => 'error',
            'sync_source' => 'mt5_ea',
            'sync_error' => "{$reason}: {$message}",
            'sync_error_at' => $completedAt,
            'last_sync_started_at' => $startedAt,
            'last_sync_completed_at' => $completedAt,
            'meta' => $meta,
        ])->save();

        Log::warning('MT5 metrics payload rejected.', array_merge([
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'reason' => $reason,
            'message' => $message,
            'payload_summary' => $this->payloadSummary($payload),
        ], $context));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logPlatformIdentityMismatch(TradingAccount $account, array $payload, string $accountIdentifier): void
    {
        $incomingLogin = (string) ($payload['platform_login'] ?? '');
        $incomingAccountId = (string) ($payload['platform_account_id'] ?? '');
        $storedLogin = (string) ($account->platform_login ?: '');
        $storedAccountId = (string) ($account->platform_account_id ?: '');

        if (
            ($incomingLogin === '' || $storedLogin === '' || $incomingLogin === $storedLogin)
            && ($incomingAccountId === '' || $storedAccountId === '' || $incomingAccountId === $storedAccountId)
        ) {
            return;
        }

        Log::warning('MT5 metrics platform identity differs from stored account credentials.', [
            'account_identifier' => $accountIdentifier,
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'stored_platform_login' => $storedLogin ?: null,
            'incoming_platform_login' => $incomingLogin ?: null,
            'stored_platform_account_id' => $storedAccountId ?: null,
            'incoming_platform_account_id' => $incomingAccountId ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadSummary(array $payload): array
    {
        return [
            'keys' => array_keys($payload),
            'balance' => $payload['balance'] ?? null,
            'equity' => $payload['equity'] ?? null,
            'open_profit' => $payload['open_profit'] ?? $payload['profit_loss'] ?? null,
            'total_profit' => $payload['total_profit'] ?? null,
            'today_profit' => $payload['today_profit'] ?? null,
            'trade_count' => $payload['trade_count'] ?? null,
            'activity_count' => $payload['activity_count'] ?? null,
            'positions_count' => $payload['positions_count'] ?? null,
            'closed_positions_count' => $payload['closed_positions_count'] ?? null,
            'open_positions_rows' => is_array($payload['open_positions'] ?? null) ? count($payload['open_positions']) : null,
            'trade_history_rows' => is_array($payload['trade_history'] ?? null) ? count($payload['trade_history']) : null,
            'has_activity' => $payload['has_activity'] ?? null,
            'server_time' => $payload['server_time'] ?? $payload['timestamp'] ?? null,
            'server_day' => $payload['server_day'] ?? null,
            'sync_trigger' => $payload['sync_trigger'] ?? null,
            'platform_login' => $payload['platform_login'] ?? null,
            'platform_account_id' => $payload['platform_account_id'] ?? null,
        ];
    }
}
