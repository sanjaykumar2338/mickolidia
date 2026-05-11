<?php

namespace App\Services\Mt5;

use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Mt5AccountDeactivationService
{
    private const STATUS_DISABLE_REQUESTED = 'disable_requested';

    private const STATUS_DISABLE_PENDING_ACK = 'disable_pending_ack';

    private const STATUS_DISABLED = 'disabled';

    private const STATUS_DISABLE_FAILED = 'disable_failed';

    private const ACTIVE_DISABLE_STATUSES = [
        self::STATUS_DISABLE_REQUESTED,
        self::STATUS_DISABLE_PENDING_ACK,
        self::STATUS_DISABLE_FAILED,
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function requestForFinalState(TradingAccount $account, string $eventKey, array $context = [], bool $allowTrial = false): TradingAccount
    {
        if (($account->is_trial && ! $allowTrial) || $account->platform_slug !== 'mt5') {
            return $account;
        }

        /** @var TradingAccount|null $freshAccount */
        $freshAccount = TradingAccount::query()->find($account->id);

        if (! $freshAccount instanceof TradingAccount) {
            return $account;
        }

        $meta = $this->meta($freshAccount);
        $eventPath = "mt5_deactivation.events.{$eventKey}";
        $event = (array) Arr::get($meta, $eventPath, []);
        $status = (string) ($event['status'] ?? '');

        if (
            $status === self::STATUS_DISABLE_FAILED
            && ! $this->failedRetryWindowElapsed($event)
        ) {
            Log::warning('MT5 deactivation retry skipped while failure cooldown is active.', [
                'trading_account_id' => $freshAccount->id,
                'account_reference' => $freshAccount->account_reference,
                'event' => $eventKey,
                'last_attempt_at' => $event['last_attempt_at'] ?? null,
                'retry_after_seconds' => $this->retryAfterSeconds(),
                'reason' => $event['error'] ?? null,
            ]);

            return $freshAccount->fresh() ?? $freshAccount;
        }

        if (in_array($status, [
            self::STATUS_DISABLE_REQUESTED,
            self::STATUS_DISABLE_PENDING_ACK,
            self::STATUS_DISABLED,
        ], true)) {
            $expectedPlatformStatus = $status;

            if ((string) $freshAccount->platform_status !== $expectedPlatformStatus) {
                $freshAccount->forceFill([
                    'platform_status' => $expectedPlatformStatus,
                ])->save();
            }

            return $freshAccount->fresh() ?? $freshAccount;
        }

        $requestedAt = now();
        $payload = $this->payload($freshAccount, $eventKey, $context, $requestedAt->toIso8601String());
        $endpoint = trim((string) config('services.mt5_deactivation.endpoint', ''));
        $initialStatus = $endpoint === ''
            ? self::STATUS_DISABLE_PENDING_ACK
            : self::STATUS_DISABLE_REQUESTED;

        Arr::set($meta, $eventPath, array_filter([
            ...$event,
            'event' => $eventKey,
            'status' => $initialStatus,
            'requested_at' => $event['requested_at'] ?? $requestedAt->toIso8601String(),
            'last_attempt_at' => $requestedAt->toIso8601String(),
            'attempts' => max((int) ($event['attempts'] ?? 0), 0) + 1,
            'action' => $payload['action'],
            'platform_login' => $payload['platform_login'],
            'platform_account_id' => $payload['platform_account_id'],
            'reason' => $payload['reason'],
            'completed_phase' => $payload['completed_phase'],
            'final_status' => $payload['final_status'],
            'failure_reason' => $payload['failure_reason'] ?? null,
            'source' => $endpoint === '' ? 'ea_action' : 'bridge_request',
            'bridge_response' => null,
            'acknowledged_at' => $event['acknowledged_at'] ?? null,
            'executed_at' => $event['executed_at'] ?? null,
            'trading_permission_state' => $event['trading_permission_state'] ?? $this->permissionStateLabel('unknown'),
            'error' => null,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $eventState = (array) Arr::get($meta, $eventPath, []);
        Arr::set($meta, 'mt5_deactivation.current_event_key', $eventKey);
        Arr::set($meta, 'mt5_deactivation.current', $this->currentStatePayload($eventKey, $eventState));

        $freshAccount->forceFill([
            'platform_status' => $initialStatus,
            'meta' => $meta,
        ])->save();

        if ($endpoint === '') {
            Log::info('MT5 deactivation queued for EA acknowledgement.', [
                'trading_account_id' => $freshAccount->id,
                'account_reference' => $freshAccount->account_reference,
                'event' => $eventKey,
                'platform_login' => $payload['platform_login'],
            ]);
            $this->recordDeactivationLog($freshAccount, 'pending_ack', 'MT5 deactivation queued for EA acknowledgement.', null, $payload);

            return $freshAccount->fresh() ?? $freshAccount;
        }

        return $this->sendBridgeRequest($freshAccount, $eventKey, $payload, $endpoint);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function requestForPass(TradingAccount $account, string $eventKey, array $context = []): TradingAccount
    {
        return $this->requestForFinalState($account, $eventKey, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function requestForTrialFailure(TradingAccount $account, string $eventKey, array $context = []): TradingAccount
    {
        return $this->requestForFinalState($account, $eventKey, $context, allowTrial: true);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function acknowledgeIfNeeded(TradingAccount $account, array $snapshot): TradingAccount
    {
        $permissionState = $this->tradingPermissionState($snapshot);
        $acknowledged = (bool) ($snapshot['trading_blocked_ack'] ?? false)
            || $this->permissionConfirmsDisabled($permissionState);

        /** @var TradingAccount|null $freshAccount */
        $freshAccount = TradingAccount::query()->find($account->id);

        if (! $freshAccount instanceof TradingAccount) {
            return $account;
        }

        if (! $acknowledged) {
            if ($permissionState['state'] !== 'unknown') {
                return $this->recordPermissionState($freshAccount, $snapshot, $permissionState);
            }

            return $account;
        }

        $meta = $this->meta($freshAccount);
        $events = (array) Arr::get($meta, 'mt5_deactivation.events', []);
        $acknowledgedAt = now()->toIso8601String();
        $updated = false;

        foreach ($events as $key => $event) {
            if (! is_array($event)) {
                continue;
            }

            if (($event['status'] ?? null) === self::STATUS_DISABLED) {
                continue;
            }

            $event['status'] = self::STATUS_DISABLED;
            $event['acknowledged_at'] = $event['acknowledged_at'] ?? $acknowledgedAt;
            $event['executed_at'] = $event['executed_at'] ?? $acknowledgedAt;
            $event['acknowledged_by'] = 'mt5_metrics';
            $event['source'] = $this->permissionConfirmsDisabled($permissionState)
                ? 'mt5_permission_state'
                : 'mt5_metrics_ack';
            $event['trading_permission_state'] = $permissionState['label'];
            $event['trading_permission_payload'] = $permissionState['payload'];
            $events[$key] = $event;
            $updated = true;
        }

        if (! $updated) {
            return $freshAccount;
        }

        Arr::set($meta, 'mt5_deactivation.events', $events);
        Arr::set($meta, 'mt5_deactivation.last_confirmed_at', $acknowledgedAt);
        Arr::set($meta, 'mt5_deactivation.last_permission_state', $permissionState);
        $currentEventKey = (string) Arr::get($meta, 'mt5_deactivation.current_event_key', '');

        if ($currentEventKey !== '' && isset($events[$currentEventKey]) && is_array($events[$currentEventKey])) {
            Arr::set($meta, 'mt5_deactivation.current', $this->currentStatePayload($currentEventKey, $events[$currentEventKey]));
        }

        $freshAccount->forceFill([
            'platform_status' => self::STATUS_DISABLED,
            'meta' => $meta,
        ])->save();

        Log::info('MT5 deactivation acknowledged by metrics payload.', [
            'trading_account_id' => $freshAccount->id,
            'account_reference' => $freshAccount->account_reference,
            'events' => array_keys($events),
            'permission_state' => $permissionState['state'],
        ]);
        $this->recordDeactivationLog($freshAccount, 'success', 'MT5 deactivation acknowledged by metrics payload.', null, [
            'events' => array_keys($events),
            'permission_state' => $permissionState,
        ]);

        return $freshAccount->fresh() ?? $freshAccount;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendBridgeRequest(TradingAccount $account, string $eventKey, array $payload, string $endpoint): TradingAccount
    {
        try {
            $request = Http::timeout((int) config('services.mt5_deactivation.timeout', 10))
                ->acceptJson()
                ->asJson();

            $token = trim((string) config('services.mt5_deactivation.token', ''));

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($endpoint, $payload);
            $body = $this->responsePayload($response);

            if (! $response->successful()) {
                return $this->markBridgeFailure(
                    account: $account,
                    eventKey: $eventKey,
                    reason: 'Bridge returned HTTP '.$response->status().'.',
                    bridgeStatus: $response->status(),
                    bridgeResponse: $body,
                    requestPayload: $payload,
                );
            }

            $disabled = is_array($body) && (bool) ($body['disabled'] ?? $body['deactivated'] ?? false);
            $status = $disabled ? self::STATUS_DISABLED : self::STATUS_DISABLE_REQUESTED;
            $executedAt = now()->toIso8601String();
            $acknowledgedAt = $disabled ? $executedAt : null;

            $meta = $this->meta($account);
            $eventPath = "mt5_deactivation.events.{$eventKey}";
            $event = (array) Arr::get($meta, $eventPath, []);
            $permissionState = $this->tradingPermissionState(is_array($body) ? $body : []);

            Arr::set($meta, $eventPath, array_filter([
                ...$event,
                'status' => $status,
                'bridge_status' => $response->status(),
                'bridge_response' => is_array($body) ? $body : null,
                'executed_at' => $event['executed_at'] ?? $executedAt,
                'acknowledged_at' => $event['acknowledged_at'] ?? $acknowledgedAt,
                'source' => 'bridge_request',
                'trading_permission_state' => $permissionState['label'],
                'trading_permission_payload' => $permissionState['payload'],
                'error' => null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            Arr::set($meta, 'mt5_deactivation.last_requested_at', now()->toIso8601String());
            Arr::set($meta, 'mt5_deactivation.current_event_key', $eventKey);
            Arr::set($meta, 'mt5_deactivation.current', $this->currentStatePayload($eventKey, (array) Arr::get($meta, $eventPath, [])));

            $account->forceFill([
                'platform_status' => $status,
                'meta' => $meta,
            ])->save();

            Log::info('MT5 deactivation bridge request succeeded.', [
                'trading_account_id' => $account->id,
                'account_reference' => $account->account_reference,
                'event' => $eventKey,
                'status' => $status,
            ]);
            $this->recordDeactivationLog($account, $disabled ? 'success' : 'requested', 'MT5 deactivation bridge request succeeded.', null, [
                'event' => $eventKey,
                'bridge_status' => $response->status(),
                'bridge_response' => is_array($body) ? $body : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->markBridgeFailure(
                account: $account,
                eventKey: $eventKey,
                reason: $exception->getMessage(),
                bridgeStatus: null,
                bridgeResponse: null,
                requestPayload: $payload,
            );
        }

        return $account->fresh() ?? $account;
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>|string|null  $bridgeResponse
     */
    private function markBridgeFailure(
        TradingAccount $account,
        string $eventKey,
        string $reason,
        ?int $bridgeStatus,
        array|string|null $bridgeResponse,
        array $requestPayload,
    ): TradingAccount {
        $meta = $this->meta($account);
        $eventPath = "mt5_deactivation.events.{$eventKey}";
        $event = (array) Arr::get($meta, $eventPath, []);

        Arr::set($meta, $eventPath, array_filter([
            ...$event,
            'status' => self::STATUS_DISABLE_FAILED,
            'source' => 'bridge_request',
            'bridge_status' => $bridgeStatus,
            'bridge_response' => $bridgeResponse,
            'error' => $reason,
            'failed_at' => now()->toIso8601String(),
            'trading_permission_state' => $event['trading_permission_state'] ?? $this->permissionStateLabel('unknown'),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        Arr::set($meta, 'mt5_deactivation.current_event_key', $eventKey);
        Arr::set($meta, 'mt5_deactivation.current', $this->currentStatePayload($eventKey, (array) Arr::get($meta, $eventPath, [])));

        $account->forceFill([
            'platform_status' => self::STATUS_DISABLE_FAILED,
            'meta' => $meta,
        ])->save();

        Log::error('MT5 deactivation bridge request failed.', [
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'event' => $eventKey,
            'bridge_status' => $bridgeStatus,
            'reason' => $reason,
        ]);
        $this->recordDeactivationLog($account, 'error', 'MT5 deactivation bridge request failed.', $reason, [
            'event' => $eventKey,
            'bridge_status' => $bridgeStatus,
            'bridge_response' => $bridgeResponse,
            'request' => $requestPayload,
        ]);

        return $account->fresh() ?? $account;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function payload(TradingAccount $account, string $eventKey, array $context, string $requestedAt): array
    {
        return [
            'action' => 'close_all_positions_and_disable_account',
            'event' => $eventKey,
            'reason' => (string) ($context['reason'] ?? 'final_state_locked'),
            'completed_phase' => (string) ($context['completed_phase'] ?? ($account->stage ?: 'Challenge')),
            'requested_at' => $requestedAt,
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'platform_login' => $account->platform_login ?: $account->platform_account_id,
            'platform_account_id' => $account->platform_account_id,
            'challenge_type' => $account->challenge_type,
            'challenge_status' => $account->challenge_status,
            'final_status' => (string) ($context['final_status'] ?? ($account->challenge_status ?: $account->account_status ?: 'locked')),
            'failure_reason' => $context['failure_reason'] ?? $account->failure_reason,
            'account_status' => $account->account_status,
            'phase_index' => (int) $account->phase_index,
            'passed_at' => optional($account->passed_at)->toIso8601String(),
            'failed_at' => optional($account->failed_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(TradingAccount $account): array
    {
        return is_array($account->meta) ? $account->meta : [];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function currentStatePayload(string $eventKey, array $event): array
    {
        return array_filter([
            'event' => $eventKey,
            'status' => $event['status'] ?? null,
            'reason' => $event['reason'] ?? null,
            'final_status' => $event['final_status'] ?? null,
            'failure_reason' => $event['failure_reason'] ?? null,
            'completed_phase' => $event['completed_phase'] ?? null,
            'requested_at' => $event['requested_at'] ?? null,
            'acknowledged_at' => $event['acknowledged_at'] ?? null,
            'executed_at' => $event['executed_at'] ?? null,
            'last_attempt_at' => $event['last_attempt_at'] ?? null,
            'attempts' => $event['attempts'] ?? null,
            'source' => $event['source'] ?? null,
            'bridge_status' => $event['bridge_status'] ?? null,
            'bridge_response' => $event['bridge_response'] ?? null,
            'trading_permission_state' => $event['trading_permission_state'] ?? null,
            'trading_permission_payload' => $event['trading_permission_payload'] ?? null,
            'failed_at' => $event['failed_at'] ?? null,
            'last_error' => $event['error'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{state:string,label:string,payload:array<string, mixed>}
     */
    private function tradingPermissionState(array $snapshot): array
    {
        $tradingAllowed = $this->firstBoolean($snapshot, [
            'trading_allowed',
            'tradingAllowed',
            'trade_allowed',
            'tradeAllowed',
            'trading_enabled',
            'tradingEnabled',
            'account_trade_allowed',
            'accountTradeAllowed',
            'terminal_trade_allowed',
            'terminalTradeAllowed',
            'expert_trade_allowed',
            'expertTradeAllowed',
            'can_trade',
            'canTrade',
            'permissions.trading_allowed',
            'permissions.trade_allowed',
            'permissions.can_trade',
            'raw.trading_allowed',
            'raw.tradingAllowed',
            'raw.trade_allowed',
            'raw.tradeAllowed',
            'raw.trading_enabled',
            'raw.tradingEnabled',
            'raw.account_trade_allowed',
            'raw.accountTradeAllowed',
            'raw.terminal_trade_allowed',
            'raw.terminalTradeAllowed',
            'raw.expert_trade_allowed',
            'raw.expertTradeAllowed',
            'raw.can_trade',
            'raw.canTrade',
            'raw.permissions.trading_allowed',
            'raw.permissions.trade_allowed',
            'raw.permissions.can_trade',
        ]);
        $readOnly = $this->firstBoolean($snapshot, [
            'read_only',
            'readOnly',
            'readonly',
            'readonly_mode',
            'readonlyMode',
            'read_only_mode',
            'readOnlyMode',
            'investor_mode',
            'investorMode',
            'investor_password_mode',
            'investorPasswordMode',
            'raw.read_only',
            'raw.readOnly',
            'raw.readonly',
            'raw.readonly_mode',
            'raw.readonlyMode',
            'raw.read_only_mode',
            'raw.readOnlyMode',
            'raw.investor_mode',
            'raw.investorMode',
            'raw.investor_password_mode',
            'raw.investorPasswordMode',
        ]);
        $tradingDisabled = $this->firstBoolean($snapshot, [
            'trading_disabled',
            'tradingDisabled',
            'trade_disabled',
            'tradeDisabled',
            'account_trade_disabled',
            'accountTradeDisabled',
            'raw.trading_disabled',
            'raw.tradingDisabled',
            'raw.trade_disabled',
            'raw.tradeDisabled',
            'raw.account_trade_disabled',
            'raw.accountTradeDisabled',
        ]);

        $state = match (true) {
            $tradingDisabled === true,
            $tradingAllowed === false => 'trading_disabled',
            $readOnly === true => 'read_only',
            $tradingAllowed === true,
            $tradingDisabled === false => 'trading_enabled',
            default => 'unknown',
        };

        return [
            'state' => $state,
            'label' => $this->permissionStateLabel($state),
            'payload' => array_filter([
                'trading_allowed' => $tradingAllowed,
                'trading_disabled' => $tradingDisabled,
                'read_only_or_investor_mode' => $readOnly,
                'checked_at' => now()->toIso8601String(),
            ], static fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $permissionState
     */
    private function permissionConfirmsDisabled(array $permissionState): bool
    {
        return in_array($permissionState['state'] ?? null, ['trading_disabled', 'read_only'], true);
    }

    private function permissionStateLabel(string $state): string
    {
        return match ($state) {
            'trading_disabled' => 'Trading disabled by MT5',
            'read_only' => 'Read-only / investor mode confirmed',
            'trading_enabled' => 'Trading still enabled in MT5',
            default => 'Unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{state:string,label:string,payload:array<string, mixed>}  $permissionState
     */
    private function recordPermissionState(TradingAccount $account, array $snapshot, array $permissionState): TradingAccount
    {
        $meta = $this->meta($account);
        $events = (array) Arr::get($meta, 'mt5_deactivation.events', []);
        $currentEventKey = (string) Arr::get($meta, 'mt5_deactivation.current_event_key', '');
        $updated = false;

        foreach ($events as $key => $event) {
            if (! is_array($event)) {
                continue;
            }

            if (! in_array((string) ($event['status'] ?? ''), self::ACTIVE_DISABLE_STATUSES, true)) {
                continue;
            }

            $event['trading_permission_state'] = $permissionState['label'];
            $event['trading_permission_payload'] = $permissionState['payload'];
            $event['last_permission_check_at'] = now()->toIso8601String();

            if (($permissionState['state'] ?? null) === 'trading_enabled') {
                $event['trading_still_enabled_at'] = now()->toIso8601String();
            }

            if ($this->snapshotHasTradingActivity($snapshot)) {
                $event['post_disable_activity_detected_at'] = now()->toIso8601String();
            }

            $events[$key] = $event;
            $updated = true;
        }

        if (! $updated) {
            return $account;
        }

        Arr::set($meta, 'mt5_deactivation.events', $events);
        Arr::set($meta, 'mt5_deactivation.last_permission_state', $permissionState);

        if ($currentEventKey !== '' && isset($events[$currentEventKey]) && is_array($events[$currentEventKey])) {
            Arr::set($meta, 'mt5_deactivation.current', $this->currentStatePayload($currentEventKey, $events[$currentEventKey]));
        }

        $account->forceFill(['meta' => $meta])->save();

        if (($permissionState['state'] ?? null) === 'trading_enabled') {
            Log::warning('MT5 reports trading still enabled after deactivation request.', [
                'trading_account_id' => $account->id,
                'account_reference' => $account->account_reference,
                'permission_state' => $permissionState,
            ]);
        }

        return $account->fresh() ?? $account;
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, mixed>  $payload
     */
    private function firstBoolean(array $payload, array $paths): ?bool
    {
        foreach ($paths as $path) {
            if (! Arr::has($payload, $path)) {
                continue;
            }

            $value = Arr::get($payload, $path);

            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value !== 0;
            }

            if (is_string($value)) {
                $normalized = Str::of($value)->trim()->lower()->toString();

                if (in_array($normalized, ['true', 'yes', 'y', 'on', 'enabled', 'allow', 'allowed', '1'], true)) {
                    return true;
                }

                if (in_array($normalized, ['false', 'no', 'n', 'off', 'disabled', 'deny', 'denied', '0'], true)) {
                    return false;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotHasTradingActivity(array $snapshot): bool
    {
        return (bool) ($snapshot['has_activity'] ?? false)
            || (int) ($snapshot['trade_count'] ?? $snapshot['activity_count'] ?? 0) > 0
            || (int) ($snapshot['positions_count'] ?? 0) > 0
            || (int) ($snapshot['closed_positions_count'] ?? 0) > 0
            || (float) ($snapshot['volume'] ?? 0) > 0;
    }

    private function retryAfterSeconds(): int
    {
        return max((int) config('services.mt5_deactivation.retry_after_seconds', 300), 1);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function failedRetryWindowElapsed(array $event): bool
    {
        $lastAttemptAt = $event['last_attempt_at'] ?? $event['failed_at'] ?? null;

        if (! is_string($lastAttemptAt) || $lastAttemptAt === '') {
            return true;
        }

        try {
            return now()->diffInSeconds(\Illuminate\Support\Carbon::parse($lastAttemptAt), true) >= $this->retryAfterSeconds();
        } catch (Throwable) {
            return true;
        }
    }

    private function responsePayload(\Illuminate\Http\Client\Response $response): array|string|null
    {
        try {
            $json = $response->json();

            if (is_array($json)) {
                return $json;
            }
        } catch (Throwable) {
            // Fall through to body capture.
        }

        $body = trim($response->body());

        return $body !== '' ? $body : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordDeactivationLog(TradingAccount $account, string $status, string $message, ?string $error = null, array $payload = []): void
    {
        TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => $account->platform_slug ?: 'mt5',
            'status' => $status,
            'message' => $message,
            'error_message' => $error,
            'started_at' => now(),
            'completed_at' => now(),
            'payload' => $payload,
        ]);
    }
}
