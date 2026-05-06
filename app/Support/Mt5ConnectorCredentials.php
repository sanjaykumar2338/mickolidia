<?php

namespace App\Support;

use App\Models\TradingAccount;
use Illuminate\Support\Str;

class Mt5ConnectorCredentials
{
    /**
     * @return array{
     *     base_url:string,
     *     endpoint_url:string,
     *     account_reference:string,
     *     secret_token:string,
     *     masked_secret_token:string,
     *     download_url:string,
     *     download_file_name:string,
     *     preconfigured_download_url:string,
     *     preconfigured_download_file_name:string,
     *     status:string,
     *     status_label:string,
     *     status_badge:string,
     *     last_connected_at:?string
     * }
     */
    public function forAccount(TradingAccount $account): array
    {
        $account = $this->ensureToken($account);
        $accountReference = (string) $account->account_reference;
        $downloadPath = $this->downloadPath();

        return [
            'base_url' => url('/api/mt5'),
            'endpoint_url' => route('api.mt5.metrics', ['accountIdentifier' => $accountReference]),
            'account_reference' => $accountReference,
            'secret_token' => (string) data_get($account->meta, 'mt5_connector.secret_token'),
            'masked_secret_token' => $this->mask((string) data_get($account->meta, 'mt5_connector.secret_token')),
            'download_url' => asset($downloadPath),
            'download_file_name' => basename($downloadPath),
            'preconfigured_download_url' => route('dashboard.accounts.mt5-connector.download', ['account' => $account]),
            'preconfigured_download_file_name' => 'Wolforix-MT5-Connector-'.$this->safeReference($accountReference).'.zip',
            'status' => $this->connectionStatus($account),
            'status_label' => $this->connectionStatusLabel($account),
            'status_badge' => $this->connectionStatusBadge($account),
            'last_connected_at' => $account->last_synced_at?->toDayDateTimeString(),
        ];
    }

    public function ensureToken(TradingAccount $account): TradingAccount
    {
        if (filled(data_get($account->meta, 'mt5_connector.secret_token'))) {
            return $account;
        }

        $meta = is_array($account->meta) ? $account->meta : [];
        $meta['mt5_connector'] = array_merge((array) data_get($meta, 'mt5_connector', []), [
            'secret_token' => Str::random(48),
            'created_at' => now()->toIso8601String(),
        ]);

        $account->forceFill(['meta' => $meta])->save();

        return $account->refresh();
    }

    public function tokenMatches(TradingAccount $account, string $providedToken): bool
    {
        $accountToken = (string) data_get($account->meta, 'mt5_connector.secret_token', '');

        return $accountToken !== '' && hash_equals($accountToken, $providedToken);
    }

    public function connectionStatus(TradingAccount $account): string
    {
        if ($account->last_synced_at !== null && $account->sync_source === 'mt5_ea') {
            return 'connected';
        }

        if ($account->sync_status === 'syncing' || ($account->last_sync_started_at !== null && $account->last_sync_completed_at === null)) {
            return 'connecting';
        }

        return 'not_connected';
    }

    public function connectionStatusLabel(TradingAccount $account): string
    {
        return match ($this->connectionStatus($account)) {
            'connected' => __('site.trial.connector.statuses.connected'),
            'connecting' => __('site.trial.connector.statuses.connecting'),
            default => __('site.trial.connector.statuses.not_connected'),
        };
    }

    public function connectionStatusBadge(TradingAccount $account): string
    {
        return match ($this->connectionStatus($account)) {
            'connected' => 'border-emerald-400/20 bg-emerald-500/12 text-emerald-100',
            'connecting' => 'border-amber-400/20 bg-amber-500/12 text-amber-100',
            default => 'border-rose-400/20 bg-rose-500/12 text-rose-100',
        };
    }

    private function mask(string $token): string
    {
        if ($token === '') {
            return '********';
        }

        return str_repeat('*', max(8, strlen($token) - 4)).substr($token, -4);
    }

    private function downloadPath(): string
    {
        $zipPath = 'mt5software/wolforix-mt5-connector.zip';

        if (file_exists(public_path($zipPath))) {
            return $zipPath;
        }

        return 'mt5software/WolforixRuleEngineEA.mq5';
    }

    private function safeReference(string $reference): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference) ?: 'account';

        return trim($safe, '-') ?: 'account';
    }
}
