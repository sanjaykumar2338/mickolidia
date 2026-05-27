<?php

namespace App\Services\MetaApi;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MetaApiOnboardingService
{
    public const STATE_PURCHASED = 'purchased';

    public const STATE_ACCOUNT_ASSIGNED = 'account_assigned';

    public const STATE_WAITING_METAAPI_CONNECTION = 'waiting_metaapi_connection';

    public const STATE_FIRST_SYNC_RECEIVED = 'first_sync_received';

    public const STATE_READY_TO_TRADE = 'ready_to_trade';

    public const STATE_ACTIVE = 'active';

    public const STATE_BREACHED = 'breached';

    public const STATE_DISABLED = 'disabled';

    private const MAX_EVENTS = 60;

    /**
     * @param  array<string, mixed>  $context
     */
    public function initialize(TradingAccount $account, array $context = []): TradingAccount
    {
        return $this->transition($account, $this->inferState($account), array_merge([
            'source' => 'onboarding_initialize',
            'message' => 'Phase 2 onboarding lifecycle initialized.',
        ], $context), 'onboarding_initialized');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function markAssigned(TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry = null, array $context = []): TradingAccount
    {
        $state = $this->metaApiAccountId($account, $poolEntry) !== null
            ? self::STATE_WAITING_METAAPI_CONNECTION
            : self::STATE_ACCOUNT_ASSIGNED;

        return $this->transition($account, $state, array_merge([
            'source' => 'pool_assignment',
            'message' => 'MT5 account credentials were assigned and onboarding is waiting for MetaApi readiness.',
            'pool_entry_id' => $poolEntry?->id,
            'login' => $poolEntry?->login ?: $account->platform_login ?: $account->platform_account_id,
            'server' => $poolEntry?->server ?: $account->platform_environment,
        ], $context), 'account_assigned');
    }

    /**
     * @param  array<string, mixed>  $syncResult
     */
    public function recordSyncResult(TradingAccount $account, array $syncResult): TradingAccount
    {
        $account = $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;

        if ($this->isFinalStateLocked($account)) {
            return $this->transition($account, $this->finalState($account), [
                'source' => 'metaapi_sync',
                'message' => 'Onboarding is locked because the challenge reached a final state.',
                'validation_state' => $syncResult['validation_state'] ?? null,
            ], $this->finalState($account) === self::STATE_BREACHED ? 'challenge_breached' : 'account_disabled');
        }

        $coreReadable = (bool) ($syncResult['account_information_readable'] ?? false)
            && (bool) ($syncResult['positions_readable'] ?? false);
        $previousOnboardingState = (string) data_get($account->meta, 'metaapi_onboarding.state');
        $state = $coreReadable
            ? (in_array($previousOnboardingState, [self::STATE_READY_TO_TRADE, self::STATE_ACTIVE], true) ? self::STATE_ACTIVE : self::STATE_READY_TO_TRADE)
            : self::STATE_WAITING_METAAPI_CONNECTION;
        $eventType = $coreReadable
            ? ($state === self::STATE_ACTIVE ? 'account_active' : 'ready_to_trade')
            : 'waiting_metaapi_connection';
        $message = $coreReadable
            ? 'The first MetaApi sync is readable. Account is ready to trade.'
            : 'MetaApi sync ran, but onboarding is still waiting for readable account data.';

        $account = $this->transition($account, $state, [
            'source' => 'metaapi_sync',
            'message' => $message,
            'validation_state' => $syncResult['validation_state'] ?? null,
            'connection_status' => $syncResult['connection_status'] ?? null,
            'history_readable' => $syncResult['history_readable'] ?? null,
        ], $eventType);

        if ($coreReadable) {
            $account = $this->recordEvent($account, 'onboarding_completed', [
                'message' => 'The client onboarding path is complete and the dashboard can show live MetaApi metrics.',
                'login' => $account->platform_login ?: $account->platform_account_id,
            ], 'onboarding_completed');
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSyncFailure(TradingAccount $account, string $reason, array $context = []): TradingAccount
    {
        $account = $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;

        if ($this->isFinalStateLocked($account)) {
            return $this->transition($account, $this->finalState($account), array_merge([
                'source' => 'metaapi_sync_failure',
                'message' => 'Onboarding is locked in a final account state.',
                'reason' => $reason,
            ], $context), $this->finalState($account) === self::STATE_BREACHED ? 'challenge_breached' : 'account_disabled');
        }

        return $this->transition($account, self::STATE_WAITING_METAAPI_CONNECTION, array_merge([
            'source' => 'metaapi_sync_failure',
            'message' => 'MetaApi connection is not ready yet. The system will keep retrying safely.',
            'reason' => $reason,
        ], $context), 'onboarding_retry_scheduled');
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
        $onboarding = (array) data_get($account->meta, 'metaapi_onboarding', []);
        $poolEntry = $this->poolEntryForAccount($account);
        $lastSync = $this->dateValue($account->last_synced_at)
            ?? $this->dateValue(data_get($account->meta, 'mt5_sync.last_successful_metric_update_at'));
        $warnings = [];
        $recommendations = [];

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $warnings[] = 'pool_assignment_missing';
            $recommendations[] = 'Run php artisan wolforix:assign-pool-account '.($account->platform_login ?: $account->account_reference).' or assign a pool row from admin.';
        }

        if ($this->metaApiAccountId($account, $poolEntry) === null) {
            $warnings[] = 'metaapi_account_id_missing';
            $recommendations[] = 'Repair or import the MetaApi UUID before expecting cloud sync.';
        }

        if ($lastSync === null) {
            $warnings[] = 'first_sync_missing';
            $recommendations[] = 'Run php artisan wolforix:sync-metaapi-account '.($account->platform_login ?: $account->account_reference).' after MetaApi is deployed.';
        }

        if ($account->challenge_status === 'failed' || filled((string) $account->failure_reason)) {
            $warnings[] = 'challenge_breached';
        }

        $retry = (array) data_get($onboarding, 'retry', []);
        $state = (string) ($onboarding['state'] ?? $this->inferState($account, $poolEntry));
        $readyToTrade = $this->readyToTrade($account, $state, $lastSync);
        $storedReadyToTrade = (bool) data_get($onboarding, 'ready_to_trade', false);

        if ($state === self::STATE_READY_TO_TRADE && $readyToTrade !== $storedReadyToTrade) {
            $warnings[] = 'ready_to_trade_flag_inconsistent';
            $recommendations[] = 'Readiness is being derived from lifecycle state and readable sync data; the next successful sync will refresh the stored flag.';
        }

        return [
            'trading_account_id' => $account->id,
            'login' => (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference),
            'account_reference' => $account->account_reference,
            'onboarding_state' => $state,
            'onboarding_state_label' => str($state)->replace('_', ' ')->title()->toString(),
            'assignment_status' => [
                'assigned' => $poolEntry instanceof Mt5AccountPoolEntry,
                'pool_entry_id' => $poolEntry?->id,
                'pool_login' => $poolEntry?->login,
                'server' => $poolEntry?->server ?: $account->platform_environment,
            ],
            'sync_readiness' => [
                'metaapi_account_id' => $this->metaApiAccountId($account, $poolEntry),
                'last_sync_at' => optional($lastSync)->toIso8601String(),
                'ready_to_trade' => $readyToTrade,
                'stored_ready_to_trade' => $storedReadyToTrade,
                'first_sync_received_at' => data_get($onboarding, 'first_sync_received_at'),
            ],
            'lifecycle_readiness' => [
                'account_status' => $account->account_status,
                'challenge_status' => $account->challenge_status,
                'platform_status' => $account->platform_status,
                'final_state_locked' => (bool) $account->final_state_locked,
            ],
            'retry_state' => $retry,
            'recovery_state' => [
                'last_recovered_at' => data_get($account->meta, 'metaapi_lifecycle.last_recovered_at'),
                'recovery_count' => (int) data_get($account->meta, 'metaapi_lifecycle.recovery_count', 0),
            ],
            'notifications_fired' => collect($this->events($account))
                ->pluck('type')
                ->filter()
                ->values()
                ->all(),
            'warnings' => array_values(array_unique($warnings)),
            'recommendations' => array_values(array_unique($recommendations)),
            'recent_events' => array_slice(array_reverse($this->events($account)), 0, 12),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function transition(TradingAccount $account, string $state, array $context, string $eventType): TradingAccount
    {
        $account = $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;
        $previousState = (string) data_get($account->meta, 'metaapi_onboarding.state', $this->inferState($account));
        $now = now();
        $meta = $this->baseMeta($account);
        $firstSyncAt = data_get($meta, 'metaapi_onboarding.first_sync_received_at');

        if (in_array($state, [self::STATE_FIRST_SYNC_RECEIVED, self::STATE_READY_TO_TRADE, self::STATE_ACTIVE], true)) {
            $firstSyncAt = $firstSyncAt ?: $now->toIso8601String();
        }

        $retryAttempts = (int) data_get($meta, 'metaapi_onboarding.retry.attempts', 0);
        $retry = [
            'attempts' => $eventType === 'onboarding_retry_scheduled' ? $retryAttempts + 1 : ($state === self::STATE_READY_TO_TRADE ? 0 : $retryAttempts),
            'max_attempts' => (int) config('services.metaapi.onboarding.max_retries', 5),
            'next_retry_at' => $eventType === 'onboarding_retry_scheduled'
                ? $now->copy()->addMinutes(max((int) config('services.metaapi.onboarding.retry_delay_minutes', 2), 0))->toIso8601String()
                : null,
            'last_error' => $context['reason'] ?? null,
            'last_failure_at' => $eventType === 'onboarding_retry_scheduled' ? $now->toIso8601String() : data_get($meta, 'metaapi_onboarding.retry.last_failure_at'),
        ];

        $meta['metaapi_onboarding'] = array_merge((array) data_get($meta, 'metaapi_onboarding', []), [
            'state' => $state,
            'previous_state' => $previousState,
            'state_label' => str($state)->replace('_', ' ')->title()->toString(),
            'started_at' => data_get($meta, 'metaapi_onboarding.started_at') ?: $now->toIso8601String(),
            'last_transition_at' => $previousState !== $state ? $now->toIso8601String() : data_get($meta, 'metaapi_onboarding.last_transition_at'),
            'account_assigned_at' => $state === self::STATE_ACCOUNT_ASSIGNED || $state === self::STATE_WAITING_METAAPI_CONNECTION
                ? (data_get($meta, 'metaapi_onboarding.account_assigned_at') ?: $now->toIso8601String())
                : data_get($meta, 'metaapi_onboarding.account_assigned_at'),
            'first_sync_received_at' => $firstSyncAt,
            'ready_to_trade_at' => $state === self::STATE_READY_TO_TRADE
                ? (data_get($meta, 'metaapi_onboarding.ready_to_trade_at') ?: $now->toIso8601String())
                : data_get($meta, 'metaapi_onboarding.ready_to_trade_at'),
            'completed_at' => $state === self::STATE_READY_TO_TRADE || $state === self::STATE_ACTIVE
                ? (data_get($meta, 'metaapi_onboarding.completed_at') ?: $now->toIso8601String())
                : data_get($meta, 'metaapi_onboarding.completed_at'),
            'ready_to_trade' => in_array($state, [self::STATE_READY_TO_TRADE, self::STATE_ACTIVE], true),
            'retry' => $retry,
            'providers' => $this->providerReadiness(),
            'last_context' => $this->sanitizePayload($context),
        ]);

        $meta['metaapi_events'] = $this->appendEvent(
            $this->events($account),
            $eventType,
            array_merge($context, [
                'previous_onboarding_state' => $previousState,
                'onboarding_state' => $state,
            ]),
            $eventType.':'.$state,
        );

        $account->forceFill(['meta' => $meta])->save();

        return $account->fresh(['user', 'challengePlan', 'challengePurchase']) ?? $account;
    }

    private function inferState(TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry = null): string
    {
        if ($this->isFinalStateLocked($account)) {
            return $this->finalState($account);
        }

        if ($account->last_synced_at !== null || (string) $account->platform_status === 'connected') {
            return self::STATE_READY_TO_TRADE;
        }

        $poolEntry ??= $this->poolEntryForAccount($account);

        if ($this->metaApiAccountId($account, $poolEntry) !== null) {
            return self::STATE_WAITING_METAAPI_CONNECTION;
        }

        if ($poolEntry instanceof Mt5AccountPoolEntry || filled((string) $account->platform_login) || filled((string) $account->platform_account_id)) {
            return self::STATE_ACCOUNT_ASSIGNED;
        }

        return self::STATE_PURCHASED;
    }

    private function readyToTrade(TradingAccount $account, string $state, ?Carbon $lastSync = null): bool
    {
        if (! in_array($state, [self::STATE_READY_TO_TRADE, self::STATE_ACTIVE], true)) {
            return false;
        }

        if ($this->isFinalStateLocked($account)) {
            return false;
        }

        if (in_array((string) $account->platform_status, ['stale', 'disconnected', 'disabled', 'disable_requested', 'disable_pending_ack', 'disable_failed'], true)) {
            return false;
        }

        if ((string) $account->sync_status === 'error') {
            return false;
        }

        $lifecycleState = (string) data_get($account->meta, 'metaapi_lifecycle.state');
        $syncHealth = (string) data_get($account->meta, 'metaapi_lifecycle.sync_health');
        $coreHealth = (string) data_get($account->meta, 'metaapi_lifecycle.core_sync_health', $syncHealth);

        if (in_array($syncHealth, ['stale', 'disconnected'], true) || in_array($coreHealth, ['stale', 'disconnected'], true)) {
            return false;
        }

        $syncConnected = $lifecycleState === 'connected' || (string) $account->platform_status === 'connected';
        $healthReadable = in_array($syncHealth, ['connected', 'recovered', 'degraded'], true)
            || in_array($coreHealth, ['connected', 'degraded'], true)
            || $syncHealth === '';

        $lastSync ??= $this->dateValue($account->last_synced_at)
            ?? $this->dateValue(data_get($account->meta, 'mt5_sync.last_successful_metric_update_at'))
            ?? $this->dateValue(data_get($account->meta, 'mt5_sync.last_synced_at'));

        return $syncConnected
            && $healthReadable
            && $lastSync instanceof Carbon
            && is_numeric($account->balance)
            && is_numeric($account->equity);
    }

    private function finalState(TradingAccount $account): string
    {
        return $account->challenge_status === 'failed' || filled((string) $account->failure_reason)
            ? self::STATE_BREACHED
            : self::STATE_DISABLED;
    }

    private function isFinalStateLocked(TradingAccount $account): bool
    {
        return (bool) $account->final_state_locked
            || (bool) $account->trading_blocked
            || filled((string) $account->failure_reason)
            || in_array((string) $account->challenge_status, ['failed', 'passed', 'funded'], true)
            || in_array((string) $account->account_status, ['failed', 'passed', 'funded'], true);
    }

    private function poolEntryForAccount(TradingAccount $account): ?Mt5AccountPoolEntry
    {
        $poolEntryId = data_get($account->meta, 'mt5_pool_entry.id');

        if (is_numeric($poolEntryId)) {
            $entry = Mt5AccountPoolEntry::query()->find((int) $poolEntryId);

            if ($entry instanceof Mt5AccountPoolEntry) {
                return $entry;
            }
        }

        $entry = Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->latest('allocated_at')
            ->latest('id')
            ->first();

        if ($entry instanceof Mt5AccountPoolEntry) {
            return $entry;
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

    private function metaApiAccountId(TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry = null): ?string
    {
        $candidates = [
            data_get($account->meta, 'metaapi_account_id'),
            data_get($account->meta, 'mt5_sync.metaapi_account_id'),
            data_get($account->meta, 'mt5_pool_entry.metaapi_account_id'),
            data_get($poolEntry?->meta, 'metaapi_account_id'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($this->looksLikeMetaApiAccountId($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function looksLikeMetaApiAccountId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id));
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

        Log::info('MetaApi onboarding event recorded.', [
            'type' => $type,
            'dedupe_key' => $dedupeKey,
            'context' => $this->sanitizePayload($context),
        ]);

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMeta(TradingAccount $account): array
    {
        return is_array($account->meta) ? $account->meta : [];
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
                'configured' => filled((string) config('services.metaapi.events.discord_webhook_url')),
            ],
            'telegram' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.telegram_enabled', false),
                'configured' => filled((string) config('services.metaapi.events.telegram_bot_token'))
                    && filled((string) config('services.metaapi.events.telegram_chat_id')),
            ],
            'crm_webhook' => [
                'prepared' => true,
                'enabled' => (bool) config('services.metaapi.events.crm_webhook_enabled', false),
                'configured' => filled((string) config('services.metaapi.events.crm_webhook_url')),
            ],
        ];
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
