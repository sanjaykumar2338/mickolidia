<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Services\TradingAccounts\TradingAccountSnapshotApplyService;
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
    ): JsonResponse {
        $this->authorizeIngestion($request);

        try {
            $normalizedPayload = $this->normalizePayload($request->all(), $accountIdentifier);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

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

        $account = $this->resolveAccount($accountIdentifier);
        $startedAt = now();
        $syncTrigger = (string) ($validated['sync_trigger'] ?? 'timer');

        Log::info('MT5 metrics update received.', [
            'account_identifier' => $accountIdentifier,
            'sync_trigger' => $syncTrigger,
            'balance' => $validated['balance'],
            'equity' => $validated['equity'],
            'open_profit' => $validated['open_profit'] ?? null,
            'positions_count' => $validated['positions_count'] ?? null,
            'has_activity' => $validated['has_activity'] ?? null,
            'timestamp' => $validated['timestamp'],
        ]);

        $log = TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => $account->platform_slug ?: 'mt5',
            'status' => 'started',
            'message' => "MT5 metrics update received ({$syncTrigger}).",
            'started_at' => $startedAt,
            'payload' => $request->all(),
        ]);

        try {
            $updatedAccount = $snapshotApplyService->apply($account, array_merge($validated, [
                'raw' => $request->all(),
            ]), [
                'source' => 'mt5_ea',
                'started_at' => $startedAt,
                'snapshot_at' => $validated['timestamp'],
            ]);

            $log->forceFill([
                'status' => 'success',
                'message' => "MT5 metrics applied successfully ({$syncTrigger}).",
                'completed_at' => now(),
            ])->save();

            Log::info('MT5 metrics update applied.', [
                'account_identifier' => $accountIdentifier,
                'account_reference' => $updatedAccount->account_reference,
                'sync_trigger' => $syncTrigger,
                'challenge_status' => $updatedAccount->challenge_status,
                'phase_index' => (int) $updatedAccount->phase_index,
                'trading_days_completed' => (int) $updatedAccount->trading_days_completed,
                'last_synced_at' => optional($updatedAccount->last_synced_at)->toIso8601String(),
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

    private function authorizeIngestion(Request $request): void
    {
        $configuredToken = (string) config('services.mt5_ingestion.token');
        $providedToken = (string) ($request->bearerToken() ?: $request->header('X-Wolforix-Token', ''));

        abort_unless(
            $configuredToken !== '' && hash_equals($configuredToken, $providedToken),
            401,
            'Invalid integration token.',
        );
    }

    private function shouldRequestPositionClosure(TradingAccount $account): bool
    {
        return $account->challenge_status === 'failed';
    }

    private function eaAction(TradingAccount $account): string
    {
        if ($account->challenge_status === 'failed') {
            return 'close_all_positions_and_block_trading';
        }

        if ((bool) $account->trading_blocked) {
            return 'block_trading';
        }

        return 'continue';
    }

    private function resolveAccount(string $accountIdentifier): TradingAccount
    {
        $account = TradingAccount::query()
            ->where('platform_account_id', $accountIdentifier)
            ->orWhere('platform_login', $accountIdentifier)
            ->orWhere('account_reference', $accountIdentifier)
            ->when(is_numeric($accountIdentifier), function ($query) use ($accountIdentifier) {
                $query->orWhere('id', (int) $accountIdentifier);
            })
            ->first();

        if (! $account instanceof TradingAccount) {
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
            'single_phase', 'phase_1', 'phase1', 'phase 1', 'challenge_step_1', 'challenge step 1' => 1,
            'phase_2', 'phase2', 'phase 2', 'challenge_step_2', 'challenge step 2' => 2,
            default => null,
        };
    }
}
