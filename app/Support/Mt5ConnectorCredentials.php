<?php

namespace App\Support;

use App\Models\TradingAccount;
use Illuminate\Support\Str;

class Mt5ConnectorCredentials
{
    public function __construct(
        private readonly Mt5ConnectorStatus $connectorStatus,
    ) {}

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
     *     last_connected_at:?string,
     *     status_message:?string
     * }
     */
    public function forAccount(TradingAccount $account): array
    {
        $account = $this->ensureToken($account);
        $accountReference = (string) $account->account_reference;
        $downloadPath = $this->downloadPath();
        $status = $this->connectorStatus->forAccount($account);

        return [
            'base_url' => $this->baseUrl(),
            'endpoint_url' => route('api.mt5.metrics', ['accountIdentifier' => $accountReference]),
            'account_reference' => $accountReference,
            'secret_token' => (string) data_get($account->meta, 'mt5_connector.secret_token'),
            'masked_secret_token' => $this->mask((string) data_get($account->meta, 'mt5_connector.secret_token')),
            'download_url' => asset($downloadPath),
            'download_file_name' => basename($downloadPath),
            'preconfigured_download_url' => route('dashboard.accounts.mt5-connector.download', ['account' => $account]),
            'preconfigured_download_file_name' => 'Wolforix-MT5-Connector-'.$this->safeReference($accountReference).'.zip',
            'status' => $status['status'],
            'status_label' => $status['label'],
            'status_badge' => $status['badge'],
            'status_message' => $status['message'],
            'last_connected_at' => $status['last_sync_at']?->toDayDateTimeString(),
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
        return $this->connectorStatus->status($account);
    }

    public function connectionStatusLabel(TradingAccount $account): string
    {
        return $this->connectorStatus->forAccount($account)['label'];
    }

    public function connectionStatusBadge(TradingAccount $account): string
    {
        return $this->connectorStatus->forAccount($account)['badge'];
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

    private function baseUrl(): string
    {
        $configuredBaseUrl = config('wolforix.mt5_connector.base_url');

        if (is_string($configuredBaseUrl) && trim($configuredBaseUrl) !== '') {
            return rtrim(trim($configuredBaseUrl), '/');
        }

        return rtrim(url('/'), '/');
    }

    private function safeReference(string $reference): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference) ?: 'account';

        return trim($safe, '-') ?: 'account';
    }
}
