<?php

namespace App\Support;

use App\Models\TradingAccount;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class Mt5ConnectorStatus
{
    public const CONNECTED = 'connected';

    public const CONNECTING = 'connecting';

    public const STALE = 'stale';

    public const NOT_CONNECTED = 'not_connected';

    /**
     * @return array{
     *     status:string,
     *     label:string,
     *     tone:string,
     *     badge:string,
     *     message:?string,
     *     is_connected:bool,
     *     is_stale:bool,
     *     last_sync_at:?Carbon,
     *     last_metric_update_at:?Carbon,
     *     last_heartbeat_at:?Carbon,
     *     last_activity_at:?Carbon,
     *     timeout_seconds:int,
     *     heartbeat_timeout_seconds:int
     * }
     */
    public function forAccount(?TradingAccount $account): array
    {
        $lastMetricUpdateAt = $account instanceof TradingAccount
            ? $this->lastMetricUpdateAt($account)
            : null;
        $lastHeartbeatAt = $account instanceof TradingAccount
            ? $this->lastHeartbeatAt($account)
            : null;
        $lastActivityAt = $account instanceof TradingAccount
            ? $this->latestDate([$lastMetricUpdateAt, $lastHeartbeatAt])
            : null;

        if (! $account instanceof TradingAccount) {
            $status = self::NOT_CONNECTED;
        } elseif ($lastHeartbeatAt instanceof Carbon) {
            $status = $this->isRecentHeartbeat($lastHeartbeatAt)
                ? self::CONNECTED
                : self::STALE;
        } elseif ($lastMetricUpdateAt instanceof Carbon) {
            $status = $this->isRecent($lastMetricUpdateAt)
                ? self::CONNECTED
                : self::STALE;
        } elseif ($this->isConnecting($account)) {
            $status = self::CONNECTING;
        } else {
            $status = self::NOT_CONNECTED;
        }

        return [
            'status' => $status,
            'label' => $this->label($status),
            'tone' => $this->tone($status),
            'badge' => $this->badge($status),
            'message' => $this->message($status),
            'is_connected' => $status === self::CONNECTED,
            'is_stale' => $status === self::STALE,
            'last_sync_at' => $lastMetricUpdateAt,
            'last_metric_update_at' => $lastMetricUpdateAt,
            'last_heartbeat_at' => $lastHeartbeatAt,
            'last_activity_at' => $lastActivityAt,
            'timeout_seconds' => $this->timeoutSeconds(),
            'heartbeat_timeout_seconds' => $this->heartbeatTimeoutSeconds(),
        ];
    }

    /**
     * @return array{label:string,hint:string,tone:string}
     */
    public function freshnessForAccount(?TradingAccount $account): array
    {
        $status = $this->forAccount($account);
        $timestamp = $status['last_activity_at'];

        if (! $timestamp instanceof Carbon) {
            return [
                'label' => $status['status'] === self::CONNECTING ? $status['label'] : __('Awaiting first sync'),
                'hint' => __('No MT5 snapshot has been received yet.'),
                'tone' => $status['tone'],
            ];
        }

        if ($status['status'] === self::STALE) {
            return [
                'label' => $status['label'],
                'hint' => $status['message'] ?? $this->formatUpdatedHint($timestamp),
                'tone' => $status['tone'],
            ];
        }

        $seconds = $this->ageSeconds($timestamp);
        $liveSeconds = max((int) config('trading.platforms.mt5.freshness.live_seconds', 15), 1);
        $recentSeconds = max((int) config('trading.platforms.mt5.freshness.recent_seconds', 60), $liveSeconds);

        if ($seconds <= $liveSeconds) {
            return [
                'label' => __('Live now'),
                'hint' => $this->formatUpdatedHint($timestamp),
                'tone' => 'emerald',
            ];
        }

        if ($seconds <= $recentSeconds) {
            return [
                'label' => __('Synced recently'),
                'hint' => $this->formatUpdatedHint($timestamp),
                'tone' => 'amber',
            ];
        }

        return [
            'label' => $status['label'],
            'hint' => $this->formatUpdatedHint($timestamp),
            'tone' => $status['tone'],
        ];
    }

    public function status(TradingAccount $account): string
    {
        return $this->forAccount($account)['status'];
    }

    public function label(string $status): string
    {
        return match ($status) {
            self::CONNECTED => __('site.trial.connector.statuses.connected'),
            self::CONNECTING => __('site.trial.connector.statuses.connecting'),
            self::STALE => __('site.trial.connector.statuses.stale'),
            default => __('site.trial.connector.statuses.not_connected'),
        };
    }

    public function badge(string $status): string
    {
        return match ($status) {
            self::CONNECTED => 'border-emerald-400/20 bg-emerald-500/12 text-emerald-100',
            self::CONNECTING => 'border-amber-400/20 bg-amber-500/12 text-amber-100',
            self::STALE => 'border-rose-400/20 bg-rose-500/12 text-rose-100',
            default => 'border-rose-400/20 bg-rose-500/12 text-rose-100',
        };
    }

    public function tone(string $status): string
    {
        return match ($status) {
            self::CONNECTED => 'emerald',
            self::CONNECTING => 'amber',
            self::STALE => 'rose',
            default => 'slate',
        };
    }

    public function message(string $status): ?string
    {
        return $status === self::STALE
            ? __('site.trial.connector.offline_message')
            : null;
    }

    public function timeoutSeconds(): int
    {
        return max((int) config('trading.platforms.mt5.freshness.stale_seconds', 300), 1);
    }

    public function heartbeatTimeoutSeconds(): int
    {
        return max((int) config('trading.platforms.mt5.freshness.heartbeat_seconds', 90), 1);
    }

    public function lastMetricUpdateAt(TradingAccount $account): ?Carbon
    {
        return $this->latestDate([
            $account->last_synced_at,
            data_get($account->meta, 'mt5_sync.last_successful_metric_update_at'),
            data_get($account->meta, 'mt5_sync.last_metric_update_at'),
            data_get($account->meta, 'mt5_sync.last_metric_update'),
            data_get($account->meta, 'mt5_sync.last_synced_at'),
            data_get($account->meta, 'mt5_sync.last_sync_at'),
            data_get($account->meta, 'last_metric_update_at'),
            data_get($account->meta, 'last_metric_update'),
            data_get($account->meta, 'last_sync_at'),
        ]);
    }

    public function lastHeartbeatAt(TradingAccount $account): ?Carbon
    {
        return $this->latestDate([
            data_get($account->meta, 'mt5_sync.last_ea_ping_at'),
            data_get($account->meta, 'mt5_sync.last_heartbeat_at'),
            data_get($account->meta, 'last_ea_ping_at'),
            data_get($account->meta, 'last_heartbeat_at'),
        ]);
    }

    private function isConnecting(TradingAccount $account): bool
    {
        return $account->sync_status === 'syncing'
            || ($account->last_sync_started_at !== null && $account->last_sync_completed_at === null);
    }

    private function isRecent(Carbon $timestamp): bool
    {
        $ageSeconds = $this->ageSeconds($timestamp);

        return $ageSeconds >= 0 && $ageSeconds <= $this->timeoutSeconds();
    }

    private function isRecentHeartbeat(Carbon $timestamp): bool
    {
        $ageSeconds = $this->ageSeconds($timestamp);

        return $ageSeconds >= 0 && $ageSeconds <= $this->heartbeatTimeoutSeconds();
    }

    private function ageSeconds(Carbon $timestamp): int
    {
        return (int) $timestamp->diffInSeconds(now(), false);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function latestDate(array $values): ?Carbon
    {
        $latest = null;

        foreach ($values as $value) {
            $date = $this->parseDate($value);

            if (! $date instanceof Carbon) {
                continue;
            }

            if (! $latest instanceof Carbon || $date->greaterThan($latest)) {
                $latest = $date;
            }
        }

        return $latest;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function formatUpdatedHint(Carbon $timestamp): string
    {
        return __('Updated :value.', ['value' => $this->formatRelativeAge($timestamp)]);
    }

    private function formatRelativeAge(Carbon $timestamp): string
    {
        $seconds = max($this->ageSeconds($timestamp), 0);

        return match (true) {
            $seconds < 60 => __(':value s ago', ['value' => $seconds]),
            $seconds < 3600 => __(':value m ago', ['value' => floor($seconds / 60)]),
            $seconds < 86400 => __(':value h ago', ['value' => floor($seconds / 3600)]),
            default => __(':value d ago', ['value' => floor($seconds / 86400)]),
        };
    }
}
