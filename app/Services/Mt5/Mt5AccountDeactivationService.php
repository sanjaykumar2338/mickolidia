<?php

namespace App\Services\Mt5;

use App\Models\TradingAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class Mt5AccountDeactivationService
{
    private const STATUS_DISABLE_REQUESTED = 'disable_requested';

    private const STATUS_DISABLE_PENDING_ACK = 'disable_pending_ack';

    private const STATUS_DISABLED = 'disabled';

    private const STATUS_DISABLE_FAILED = 'disable_failed';

    /**
     * @param  array<string, mixed>  $context
     */
    public function requestForFinalState(TradingAccount $account, string $eventKey, array $context = []): TradingAccount
    {
        if ($account->is_trial || $account->platform_slug !== 'mt5') {
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
     * @param  array<string, mixed>  $snapshot
     */
    public function acknowledgeIfNeeded(TradingAccount $account, array $snapshot): TradingAccount
    {
        if (! (bool) ($snapshot['trading_blocked_ack'] ?? false)) {
            return $account;
        }

        /** @var TradingAccount|null $freshAccount */
        $freshAccount = TradingAccount::query()->find($account->id);

        if (! $freshAccount instanceof TradingAccount) {
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
            $event['acknowledged_by'] = 'mt5_metrics';
            $event['source'] = 'mt5_metrics_ack';
            $events[$key] = $event;
            $updated = true;
        }

        if (! $updated) {
            return $freshAccount;
        }

        Arr::set($meta, 'mt5_deactivation.events', $events);
        Arr::set($meta, 'mt5_deactivation.last_confirmed_at', $acknowledgedAt);
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
            $response->throw();

            $body = $response->json();
            $disabled = is_array($body) && (bool) ($body['disabled'] ?? $body['deactivated'] ?? false);
            $status = $disabled ? self::STATUS_DISABLED : self::STATUS_DISABLE_REQUESTED;
            $acknowledgedAt = $disabled ? now()->toIso8601String() : null;

            $meta = $this->meta($account);
            $eventPath = "mt5_deactivation.events.{$eventKey}";
            $event = (array) Arr::get($meta, $eventPath, []);

            Arr::set($meta, $eventPath, array_filter([
                ...$event,
                'status' => $status,
                'bridge_status' => $response->status(),
                'bridge_response' => is_array($body) ? $body : null,
                'acknowledged_at' => $event['acknowledged_at'] ?? $acknowledgedAt,
                'source' => 'bridge_request',
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
        } catch (Throwable $exception) {
            report($exception);

            $meta = $this->meta($account);
            $eventPath = "mt5_deactivation.events.{$eventKey}";
            $event = (array) Arr::get($meta, $eventPath, []);

            Arr::set($meta, $eventPath, [
                ...$event,
                'status' => self::STATUS_DISABLE_FAILED,
                'source' => 'bridge_request',
                'error' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);
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
                'reason' => $exception->getMessage(),
            ]);
        }

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
            'source' => $event['source'] ?? null,
            'bridge_response' => $event['bridge_response'] ?? null,
            'last_error' => $event['error'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
