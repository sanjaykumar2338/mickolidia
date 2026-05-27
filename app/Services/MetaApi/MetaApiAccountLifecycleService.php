<?php

namespace App\Services\MetaApi;

use App\Models\TradingAccount;
use App\Support\Mt5ConnectorStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MetaApiAccountLifecycleService
{
    public const STATE_PENDING_ACTIVATION = 'pending_activation';

    public const STATE_WAITING_FOR_FIRST_SYNC = 'waiting_for_first_sync';

    public const STATE_CONNECTED = 'connected';

    public const STATE_STALE = 'stale';

    public const STATE_DISCONNECTED = 'disconnected';

    public const STATE_BREACHED = 'breached';

    public const STATE_DISABLED = 'disabled';

    public const HEALTH_CONNECTED = 'connected';

    public const HEALTH_DEGRADED = 'degraded';

    public const HEALTH_STALE = 'stale';

    public const HEALTH_DISCONNECTED = 'disconnected';

    public const HEALTH_RECOVERED = 'recovered';

    private const MAX_EVENTS = 60;

    public function __construct(
        private readonly Mt5ConnectorStatus $connectorStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $syncResult
     */
    public function recordSyncResult(TradingAccount $account, array $syncResult): TradingAccount
    {
        $account = $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;

        if ($this->isFinalStateLocked($account)) {
            return $this->recordFinalState($account, $syncResult);
        }

        $previousState = $this->currentLifecycleState($account);
        $previousHealth = $this->currentSyncHealth($account);
        $health = $this->healthFromSyncResult($syncResult);
        $recovered = in_array($previousHealth, [self::HEALTH_STALE, self::HEALTH_DISCONNECTED], true)
            && in_array($health, [self::HEALTH_CONNECTED, self::HEALTH_DEGRADED], true);
        $reportedHealth = $recovered && $health === self::HEALTH_CONNECTED
            ? self::HEALTH_RECOVERED
            : $health;
        $state = match ($health) {
            self::HEALTH_DISCONNECTED => self::STATE_DISCONNECTED,
            self::HEALTH_STALE => self::STATE_STALE,
            default => self::STATE_CONNECTED,
        };

        $activated = $this->shouldActivate($account);
        $meta = $this->baseMeta($account);
        $events = $this->events($account);
        $now = now();

        if ($activated) {
            $events = $this->appendEvent($events, 'account_activated', [
                'message' => 'MetaApi first successful sync activated the account lifecycle.',
                'login' => $account->platform_login ?: $account->platform_account_id,
            ], 'account_activated');
        }

        if ($previousState !== self::STATE_CONNECTED || $activated) {
            $events = $this->appendEvent($events, 'account_connected', [
                'message' => 'MetaApi live sync is connected and account metrics are readable.',
                'connection_status' => $syncResult['connection_status'] ?? null,
                'validation_state' => $syncResult['validation_state'] ?? null,
            ]);
        }

        if ($recovered) {
            $events = $this->appendEvent($events, 'account_recovered', [
                'message' => 'MetaApi sync recovered after a stale or disconnected state.',
                'previous_sync_health' => $previousHealth,
                'connection_status' => $syncResult['connection_status'] ?? null,
            ]);
        }

        if ($health === self::HEALTH_DEGRADED) {
            $events = $this->appendEvent($events, 'sync_failure', [
                'message' => 'MetaApi history is degraded, but balance/equity and positions remain readable.',
                'degraded_component' => 'history',
                'errors' => $syncResult['history_errors'] ?? [],
            ]);
        }

        $lifecycle = array_merge((array) data_get($meta, 'metaapi_lifecycle', []), [
            'state' => $state,
            'sync_health' => $reportedHealth,
            'core_sync_health' => $health,
            'previous_state' => $previousState,
            'previous_sync_health' => $previousHealth,
            'last_state_change_at' => $previousState !== $state ? $now->toIso8601String() : data_get($meta, 'metaapi_lifecycle.last_state_change_at'),
            'last_health_change_at' => $previousHealth !== $reportedHealth ? $now->toIso8601String() : data_get($meta, 'metaapi_lifecycle.last_health_change_at'),
            'last_sync_at' => optional($account->last_synced_at)->toIso8601String() ?: $now->toIso8601String(),
            'last_successful_sync_at' => optional($account->last_synced_at)->toIso8601String() ?: $now->toIso8601String(),
            'last_connected_at' => $now->toIso8601String(),
            'first_connected_at' => data_get($meta, 'metaapi_lifecycle.first_connected_at') ?: $now->toIso8601String(),
            'activated_at' => data_get($meta, 'metaapi_lifecycle.activated_at') ?: optional($account->activated_at)->toIso8601String() ?: $now->toIso8601String(),
            'recovery_count' => (int) data_get($meta, 'metaapi_lifecycle.recovery_count', 0) + ($recovered ? 1 : 0),
            'last_recovered_at' => $recovered ? $now->toIso8601String() : data_get($meta, 'metaapi_lifecycle.last_recovered_at'),
            'retry' => [
                'attempts' => 0,
                'next_retry_at' => null,
                'last_error' => null,
                'last_failure_at' => null,
            ],
            'providers' => $this->providerReadiness(),
        ]);

        $meta['metaapi_lifecycle'] = $lifecycle;
        $meta['metaapi_events'] = $events;

        $fill = [
            'meta' => $meta,
        ];

        if ($activated) {
            $fill = array_merge($fill, [
                'account_status' => 'active',
                'challenge_status' => 'active',
                'status' => 'Active',
                'activated_at' => $account->activated_at ?? $now,
                'phase_started_at' => $account->phase_started_at ?? $now,
            ]);
        }

        if (! $this->isDisableStatus((string) $account->platform_status)) {
            $fill['platform_status'] = $state === self::STATE_DISCONNECTED ? 'disconnected' : 'connected';
        }

        $account->forceFill($fill)->save();

        return $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSyncFailure(TradingAccount $account, string $reason, array $context = []): TradingAccount
    {
        $account = $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;
        $now = now();
        $previousState = $this->currentLifecycleState($account);
        $previousHealth = $this->currentSyncHealth($account);
        $stale = $this->isStale($account);
        $state = $stale ? self::STATE_STALE : self::STATE_DISCONNECTED;
        $health = $stale ? self::HEALTH_STALE : self::HEALTH_DISCONNECTED;
        $meta = $this->baseMeta($account);
        $events = $this->events($account);

        $events = $this->appendEvent($events, 'sync_failure', [
            'message' => 'MetaApi sync could not read the required account data.',
            'reason' => $reason,
            'connection_status' => $context['connection_status'] ?? null,
            'deploy_status' => $context['deploy_status'] ?? null,
        ]);

        $events = $this->appendEvent($events, $stale ? 'account_stale' : 'account_disconnected', [
            'message' => $stale
                ? 'MetaApi account sync is stale and needs a successful refresh.'
                : 'MetaApi account sync is disconnected and needs recovery.',
            'reason' => $reason,
        ]);

        $attempts = (int) data_get($meta, 'metaapi_lifecycle.retry.attempts', 0) + 1;
        $retryDelay = max((int) config('services.metaapi.sync.retry_delay_ms', 500), 0);

        $meta['metaapi_lifecycle'] = array_merge((array) data_get($meta, 'metaapi_lifecycle', []), [
            'state' => $state,
            'sync_health' => $health,
            'core_sync_health' => $health,
            'previous_state' => $previousState,
            'previous_sync_health' => $previousHealth,
            'last_state_change_at' => $previousState !== $state ? $now->toIso8601String() : data_get($meta, 'metaapi_lifecycle.last_state_change_at'),
            'last_health_change_at' => $previousHealth !== $health ? $now->toIso8601String() : data_get($meta, 'metaapi_lifecycle.last_health_change_at'),
            'last_failure_at' => $now->toIso8601String(),
            'last_stale_at' => $stale ? $now->toIso8601String() : data_get($meta, 'metaapi_lifecycle.last_stale_at'),
            'last_disconnected_at' => $stale ? data_get($meta, 'metaapi_lifecycle.last_disconnected_at') : $now->toIso8601String(),
            'retry' => [
                'attempts' => $attempts,
                'next_retry_at' => $retryDelay > 0 ? $now->copy()->addMilliseconds($retryDelay)->toIso8601String() : null,
                'last_error' => $reason,
                'last_failure_at' => $now->toIso8601String(),
            ],
            'providers' => $this->providerReadiness(),
        ]);
        $meta['metaapi_events'] = $events;

        $account->forceFill([
            'meta' => $meta,
        ])->save();

        return $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordEvent(TradingAccount $account, string $type, array $context = [], ?string $dedupeKey = null): TradingAccount
    {
        $meta = $this->baseMeta($account);
        $meta['metaapi_events'] = $this->appendEvent($this->events($account), $type, $context, $dedupeKey);

        $account->forceFill(['meta' => $meta])->save();

        return $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnose(TradingAccount $account): array
    {
        $account = $account->fresh() ?? $account;
        $status = $this->connectorStatus->forAccount($account);
        $lifecycle = (array) data_get($account->meta, 'metaapi_lifecycle', []);
        $events = array_slice(array_reverse($this->events($account)), 0, 12);
        $lastSyncAt = $this->dateValue($status['last_sync_at'] ?? $account->last_synced_at);
        $ageSeconds = $lastSyncAt instanceof Carbon ? (int) $lastSyncAt->diffInSeconds(now(), false) : null;

        return [
            'trading_account_id' => $account->id,
            'login' => (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference),
            'account_reference' => $account->account_reference,
            'lifecycle_state' => (string) ($lifecycle['state'] ?? $this->inferLifecycleState($account)),
            'sync_health' => (string) ($lifecycle['sync_health'] ?? $this->inferSyncHealth($account)),
            'connector_status' => $status['status'],
            'connector_label' => $status['label'],
            'last_sync_at' => optional($lastSyncAt)->toIso8601String(),
            'last_successful_sync_at' => data_get($lifecycle, 'last_successful_sync_at'),
            'sync_age_seconds' => $ageSeconds,
            'stale_threshold_minutes' => (int) config('services.metaapi.sync.stale_minutes', 10),
            'is_stale' => (bool) ($status['is_stale'] ?? false) || $this->isStale($account),
            'retry_state' => (array) data_get($lifecycle, 'retry', []),
            'breach_state' => [
                'breached' => $account->challenge_status === 'failed' || filled((string) $account->failure_reason),
                'reason' => $account->failure_reason,
                'failed_at' => optional($account->failed_at)->toIso8601String(),
                'final_state_locked' => (bool) $account->final_state_locked,
            ],
            'notifications' => [
                'failed_email_sent_at' => optional($account->failed_email_sent_at)->toIso8601String(),
                'passed_email_sent_at' => optional($account->passed_email_sent_at)->toIso8601String(),
                'lifecycle_notifications' => (array) data_get($lifecycle, 'notifications', []),
            ],
            'recovery' => [
                'recovery_count' => (int) data_get($lifecycle, 'recovery_count', 0),
                'last_recovered_at' => data_get($lifecycle, 'last_recovered_at'),
            ],
            'providers' => (array) data_get($lifecycle, 'providers', $this->providerReadiness()),
            'recent_events' => $events,
        ];
    }

    /**
     * @param  array<string, mixed>  $syncResult
     */
    private function recordFinalState(TradingAccount $account, array $syncResult): TradingAccount
    {
        $meta = $this->baseMeta($account);
        $events = $this->events($account);
        $now = now();
        $state = $account->challenge_status === 'failed' || filled((string) $account->failure_reason)
            ? self::STATE_BREACHED
            : self::STATE_DISABLED;
        $eventType = $state === self::STATE_BREACHED ? 'challenge_breached' : 'challenge_passed';
        $dedupeKey = $eventType.':'.($account->failure_reason ?: $account->challenge_status ?: $account->account_status).':'.(optional($account->failed_at ?? $account->passed_at)->toIso8601String() ?: $account->id);
        $message = $state === self::STATE_BREACHED
            ? $this->supportiveBreachMessage($account)
            : 'Challenge status changed to passed and the final-state account lifecycle is locked.';

        $events = $this->appendEvent($events, $eventType, [
            'message' => $message,
            'reason' => $account->failure_reason,
            'challenge_status' => $account->challenge_status,
            'email_sent_at' => optional($account->failed_email_sent_at ?? $account->passed_email_sent_at)->toIso8601String(),
            'connection_status' => $syncResult['connection_status'] ?? null,
        ], $dedupeKey);

        $notifications = (array) data_get($meta, 'metaapi_lifecycle.notifications', []);

        if ($state === self::STATE_BREACHED) {
            $notifications['breach_email'] = [
                'status' => $account->failed_email_sent_at ? 'sent' : 'pending',
                'sent_at' => optional($account->failed_email_sent_at)->toIso8601String(),
                'message' => $message,
            ];
        }

        $meta['metaapi_lifecycle'] = array_merge((array) data_get($meta, 'metaapi_lifecycle', []), [
            'state' => $state,
            'sync_health' => $this->healthFromSyncResult($syncResult),
            'core_sync_health' => $this->healthFromSyncResult($syncResult),
            'last_state_change_at' => $now->toIso8601String(),
            'last_sync_at' => optional($account->last_synced_at)->toIso8601String(),
            'breach' => [
                'breached' => $state === self::STATE_BREACHED,
                'reason' => $account->failure_reason,
                'detected_at' => optional($account->failed_at)->toIso8601String(),
                'final_state_locked' => (bool) $account->final_state_locked,
                'message' => $state === self::STATE_BREACHED ? $message : null,
            ],
            'notifications' => $notifications,
            'providers' => $this->providerReadiness(),
        ]);
        $meta['metaapi_events'] = $events;

        $account->forceFill(['meta' => $meta])->save();

        return $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;
    }

    private function supportiveBreachMessage(TradingAccount $account): string
    {
        $rule = str((string) ($account->failure_reason ?: 'risk_rule'))->replace('_', ' ')->title()->toString();

        return "A trading rule was violated ({$rule}), so this challenge cannot continue in its current phase. This outcome is handled respectfully and the account is locked to preserve evaluation integrity. Keep reviewing the trade decisions that led here; disciplined improvement is exactly what future participation is built on.";
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function appendEvent(array $events, string $type, array $context = [], ?string $dedupeKey = null): array
    {
        if ($dedupeKey !== null) {
            foreach ($events as $event) {
                if (($event['type'] ?? null) === $type && ($event['dedupe_key'] ?? null) === $dedupeKey) {
                    return $events;
                }
            }
        }

        $events[] = [
            'type' => $type,
            'dedupe_key' => $dedupeKey,
            'occurred_at' => now()->toIso8601String(),
            'context' => $this->sanitizePayload($context),
            'providers' => $this->providerReadiness(),
        ];

        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }

        Log::info('MetaApi account lifecycle event recorded.', [
            'type' => $type,
            'dedupe_key' => $dedupeKey,
            'context' => $this->sanitizePayload($context),
        ]);

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function events(TradingAccount $account): array
    {
        return collect((array) data_get($account->meta, 'metaapi_events', []))
            ->filter(fn (mixed $event): bool => is_array($event))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMeta(TradingAccount $account): array
    {
        return is_array($account->meta) ? $account->meta : [];
    }

    /**
     * @param  array<string, mixed>  $syncResult
     */
    private function healthFromSyncResult(array $syncResult): string
    {
        if (($syncResult['status'] ?? null) === 'error') {
            return self::HEALTH_DISCONNECTED;
        }

        if (! (bool) ($syncResult['account_information_readable'] ?? false) || ! (bool) ($syncResult['positions_readable'] ?? false)) {
            return self::HEALTH_DISCONNECTED;
        }

        if (($syncResult['status'] ?? null) === 'partial' || ! (bool) ($syncResult['history_readable'] ?? true)) {
            return self::HEALTH_DEGRADED;
        }

        return self::HEALTH_CONNECTED;
    }

    private function currentLifecycleState(TradingAccount $account): string
    {
        return (string) data_get($account->meta, 'metaapi_lifecycle.state', $this->inferLifecycleState($account));
    }

    private function currentSyncHealth(TradingAccount $account): string
    {
        return (string) data_get($account->meta, 'metaapi_lifecycle.sync_health', $this->inferSyncHealth($account));
    }

    private function inferLifecycleState(TradingAccount $account): string
    {
        if ($account->challenge_status === 'failed' || filled((string) $account->failure_reason)) {
            return self::STATE_BREACHED;
        }

        if ($this->isDisableStatus((string) $account->platform_status) || in_array((string) $account->challenge_status, ['passed', 'funded'], true)) {
            return self::STATE_DISABLED;
        }

        if ($this->isStale($account)) {
            return self::STATE_STALE;
        }

        if ((string) $account->platform_status === 'disconnected' || (string) $account->sync_status === 'error') {
            return self::STATE_DISCONNECTED;
        }

        if ($account->last_synced_at !== null || (string) $account->platform_status === 'connected') {
            return self::STATE_CONNECTED;
        }

        return (string) $account->account_status === self::STATE_PENDING_ACTIVATION
            ? self::STATE_PENDING_ACTIVATION
            : self::STATE_WAITING_FOR_FIRST_SYNC;
    }

    private function inferSyncHealth(TradingAccount $account): string
    {
        $status = $this->connectorStatus->forAccount($account);

        return match ($status['status']) {
            Mt5ConnectorStatus::CONNECTED => self::HEALTH_CONNECTED,
            Mt5ConnectorStatus::STALE => self::HEALTH_STALE,
            Mt5ConnectorStatus::DISCONNECTED => self::HEALTH_DISCONNECTED,
            default => $account->sync_status === 'error' ? self::HEALTH_DISCONNECTED : self::HEALTH_DEGRADED,
        };
    }

    private function shouldActivate(TradingAccount $account): bool
    {
        if ($this->isFinalStateLocked($account)) {
            return false;
        }

        return data_get($account->meta, 'metaapi_lifecycle.activated_at') === null
            || $account->activated_at === null
            || in_array((string) $account->account_status, [self::STATE_PENDING_ACTIVATION, self::STATE_WAITING_FOR_FIRST_SYNC, 'pending'], true)
            || in_array((string) $account->challenge_status, [self::STATE_PENDING_ACTIVATION, self::STATE_WAITING_FOR_FIRST_SYNC, 'pending'], true);
    }

    private function isFinalStateLocked(TradingAccount $account): bool
    {
        return (bool) $account->final_state_locked
            || (bool) $account->trading_blocked
            || filled((string) $account->failure_reason)
            || in_array((string) $account->challenge_status, ['failed', 'passed', 'funded'], true)
            || in_array((string) $account->account_status, ['failed', 'passed', 'funded'], true);
    }

    private function isDisableStatus(string $status): bool
    {
        return in_array($status, ['disable_requested', 'disable_pending_ack', 'disabled', 'disable_failed', 'disabled_pending_ack'], true);
    }

    private function isStale(TradingAccount $account): bool
    {
        $lastSync = $this->dateValue($account->last_synced_at)
            ?? $this->dateValue(data_get($account->meta, 'mt5_sync.last_successful_metric_update_at'))
            ?? $this->dateValue(data_get($account->meta, 'mt5_sync.last_synced_at'));

        if (! $lastSync instanceof Carbon) {
            return false;
        }

        $thresholdSeconds = max((int) config('services.metaapi.sync.stale_minutes', 10), 1) * 60;

        return $lastSync->diffInSeconds(now(), false) > $thresholdSeconds;
    }

    private function dateValue(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function providerReadiness(): array
    {
        return [
            'email' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.email_enabled', true),
            ],
            'discord' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.discord_enabled', false),
            ],
            'telegram' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.telegram_enabled', false),
            ],
            'crm_webhook' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.crm_webhook_enabled', false),
            ],
        ];
    }

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
}
