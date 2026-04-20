<?php

namespace App\Services\Mt5;

use App\Models\TradingAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class Mt5AccountDeactivationService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function requestForPass(TradingAccount $account, string $eventKey, array $context = []): TradingAccount
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

        if (in_array($status, ['pending', 'pending_ea_ack', 'requested', 'disabled'], true)) {
            $expectedPlatformStatus = $status === 'disabled' ? 'disabled' : 'disabled_pending_ack';

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

        Arr::set($meta, $eventPath, array_filter([
            ...$event,
            'event' => $eventKey,
            'status' => $endpoint === '' ? 'pending_ea_ack' : 'pending',
            'requested_at' => $event['requested_at'] ?? $requestedAt->toIso8601String(),
            'last_attempt_at' => $requestedAt->toIso8601String(),
            'action' => $payload['action'],
            'platform_login' => $payload['platform_login'],
            'platform_account_id' => $payload['platform_account_id'],
            'reason' => $payload['reason'],
            'completed_phase' => $payload['completed_phase'],
            'error' => null,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $freshAccount->forceFill([
            'platform_status' => 'disabled_pending_ack',
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

            if (($event['status'] ?? null) === 'disabled') {
                continue;
            }

            $event['status'] = 'disabled';
            $event['confirmed_at'] = $event['confirmed_at'] ?? $acknowledgedAt;
            $event['acknowledged_by'] = 'mt5_metrics';
            $events[$key] = $event;
            $updated = true;
        }

        if (! $updated) {
            return $freshAccount;
        }

        Arr::set($meta, 'mt5_deactivation.events', $events);
        Arr::set($meta, 'mt5_deactivation.last_confirmed_at', $acknowledgedAt);

        $freshAccount->forceFill([
            'platform_status' => 'disabled',
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
            $status = $disabled ? 'disabled' : 'requested';
            $confirmedAt = $disabled ? now()->toIso8601String() : null;

            $meta = $this->meta($account);
            $eventPath = "mt5_deactivation.events.{$eventKey}";
            $event = (array) Arr::get($meta, $eventPath, []);

            Arr::set($meta, $eventPath, array_filter([
                ...$event,
                'status' => $status,
                'bridge_status' => $response->status(),
                'bridge_response' => is_array($body) ? $body : null,
                'confirmed_at' => $event['confirmed_at'] ?? $confirmedAt,
                'error' => null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            Arr::set($meta, 'mt5_deactivation.last_requested_at', now()->toIso8601String());

            $account->forceFill([
                'platform_status' => $disabled ? 'disabled' : 'disabled_pending_ack',
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
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);

            $account->forceFill([
                'platform_status' => 'disable_failed',
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
            'action' => 'disable_account_after_phase_pass',
            'event' => $eventKey,
            'reason' => (string) ($context['reason'] ?? 'challenge_phase_passed'),
            'completed_phase' => (string) ($context['completed_phase'] ?? ($account->stage ?: 'Challenge')),
            'requested_at' => $requestedAt,
            'trading_account_id' => $account->id,
            'account_reference' => $account->account_reference,
            'platform_login' => $account->platform_login ?: $account->platform_account_id,
            'platform_account_id' => $account->platform_account_id,
            'challenge_type' => $account->challenge_type,
            'challenge_status' => $account->challenge_status,
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
}
