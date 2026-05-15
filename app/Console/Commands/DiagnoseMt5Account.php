<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Support\Mt5ConnectorCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseMt5Account extends Command
{
    protected $signature = 'wolforix:diagnose-mt5-account
        {account_reference : Wolforix trading account reference}
        {--show-secret : Print the raw EA secret token. Use only in a secure terminal}';

    protected $description = 'Read-only diagnosis for an MT5 account reference and EA metrics API authentication.';

    public function handle(Mt5ConnectorCredentials $connectorCredentials): int
    {
        $accountReference = trim((string) $this->argument('account_reference'));
        $showSecret = (bool) $this->option('show-secret');

        $this->info('Read-only MT5 account diagnosis');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->newLine();

        $account = TradingAccount::query()
            ->with(['user', 'order', 'challengePurchase'])
            ->where('account_reference', $accountReference)
            ->first();

        if (! $account instanceof TradingAccount) {
            $this->error('Trading account was not found for account_reference '.$accountReference.'.');

            return self::FAILURE;
        }

        $secretToken = (string) data_get($account->meta, 'mt5_connector.secret_token', '');
        $latestLog = TradingAccountSyncLog::query()
            ->where('trading_account_id', $account->id)
            ->latest('id')
            ->first();

        $latestPayload = is_array($latestLog?->payload) ? $latestLog->payload : [];
        $incomingPlatformLogin = (string) data_get($latestPayload, 'platform_login', '');
        $incomingPlatformAccountId = (string) data_get($latestPayload, 'platform_account_id', '');
        $storedPlatformLogin = (string) ($account->platform_login ?: '');
        $storedPlatformAccountId = (string) ($account->platform_account_id ?: '');

        $this->table(['Field', 'Value'], [
            ['user_email', (string) ($account->user?->email ?? '-')],
            ['trading_account_id', (string) $account->id],
            ['account_reference', (string) $account->account_reference],
            ['account_status', (string) ($account->account_status ?: $account->status ?: '-')],
            ['status', (string) ($account->status ?: '-')],
            ['challenge_status', (string) ($account->challenge_status ?: '-')],
            ['challenge_type', (string) ($account->challenge_type ?: '-')],
            ['account_size', (string) ($account->account_size ?: '-')],
            ['platform', (string) ($account->platform ?: '-')],
            ['platform_slug', (string) ($account->platform_slug ?: '-')],
            ['platform_login', $storedPlatformLogin !== '' ? $storedPlatformLogin : '-'],
            ['platform_account_id', $storedPlatformAccountId !== '' ? $storedPlatformAccountId : '-'],
            ['platform_environment', (string) ($account->platform_environment ?: '-')],
            ['platform_status', (string) ($account->platform_status ?: '-')],
            ['sync_status', (string) ($account->sync_status ?: '-')],
            ['sync_source', (string) ($account->sync_source ?: '-')],
            ['last_synced_at', $this->formatValue($account->last_synced_at)],
            ['last_sync_started_at', $this->formatValue($account->last_sync_started_at)],
            ['last_sync_completed_at', $this->formatValue($account->last_sync_completed_at)],
            ['sync_error', (string) ($account->sync_error ?: '-')],
            ['sync_error_at', $this->formatValue($account->sync_error_at)],
        ]);

        $this->newLine();
        $this->info('EA/API authentication');
        $this->table(['Field', 'Value'], [
            ['endpoint_primary', route('api.mt5.metrics', ['accountIdentifier' => $accountReference])],
            ['endpoint_legacy', route('api.integrations.mt5.metrics', ['accountIdentifier' => $accountReference])],
            ['auth_header', 'Authorization: Bearer <secret_token>'],
            ['body_fallback', 'secret_token=<secret_token>'],
            ['token_storage', 'trading_accounts.meta.mt5_connector.secret_token'],
            ['token_format', 'plain JSON string; compared with hash_equals; not encrypted/hashed'],
            ['token_present', $secretToken !== '' ? 'yes' : 'no'],
            ['token_length', $secretToken !== '' ? (string) strlen($secretToken) : '-'],
            ['token_masked', $secretToken !== '' ? $this->mask($secretToken) : '-'],
            ['token_raw', $showSecret ? ($secretToken !== '' ? $secretToken : '-') : '(hidden; rerun with --show-secret)'],
            ['connector_status', $connectorCredentials->connectionStatus($account)],
            ['connector_status_label', $connectorCredentials->connectionStatusLabel($account)],
        ]);

        $this->newLine();
        $this->info('MT5 sync state');
        $this->table(['Field', 'Value'], [
            ['meta.mt5_sync.status', (string) data_get($account->meta, 'mt5_sync.status', '-')],
            ['meta.mt5_sync.identifier', (string) data_get($account->meta, 'mt5_sync.identifier', '-')],
            ['meta.mt5_sync.server', (string) data_get($account->meta, 'mt5_sync.server', '-')],
            ['last_rejected_at', (string) data_get($account->meta, 'mt5_sync.last_rejected_at', '-')],
            ['last_rejected_reason', (string) data_get($account->meta, 'mt5_sync.last_rejected_reason', '-')],
            ['last_ignored_reason', (string) data_get($account->meta, 'mt5_sync.last_ignored_reason', '-')],
            ['last_error', (string) data_get($account->meta, 'mt5_sync.last_error', '-')],
            ['incoming_platform_login_latest_log', $incomingPlatformLogin !== '' ? $incomingPlatformLogin : '-'],
            ['incoming_platform_account_id_latest_log', $incomingPlatformAccountId !== '' ? $incomingPlatformAccountId : '-'],
            ['incoming_login_matches_stored', $this->identityMatches($incomingPlatformLogin, $storedPlatformLogin)],
            ['incoming_account_id_matches_stored', $this->identityMatches($incomingPlatformAccountId, $storedPlatformAccountId)],
        ]);

        $this->printLatestSyncLog($latestLog);
        $this->printApiTrace();

        return self::SUCCESS;
    }

    private function printLatestSyncLog(?TradingAccountSyncLog $log): void
    {
        $this->newLine();
        $this->info('Latest sync log');

        if (! $log instanceof TradingAccountSyncLog) {
            $this->line('No sync log rows found for this trading account.');

            return;
        }

        $payload = is_array($log->payload) ? $log->payload : [];

        $this->table(['Field', 'Value'], [
            ['id', (string) $log->id],
            ['platform', (string) $log->platform],
            ['status', (string) $log->status],
            ['message', (string) ($log->message ?: '-')],
            ['error_message', (string) ($log->error_message ?: '-')],
            ['started_at', $this->formatValue($log->started_at)],
            ['completed_at', $this->formatValue($log->completed_at)],
            ['payload.sync_trigger', (string) data_get($payload, 'sync_trigger', '-')],
            ['payload.platform_login', (string) data_get($payload, 'platform_login', '-')],
            ['payload.platform_account_id', (string) data_get($payload, 'platform_account_id', '-')],
            ['payload.balance', $this->formatValue(data_get($payload, 'balance'))],
            ['payload.equity', $this->formatValue(data_get($payload, 'equity'))],
            ['payload.server_time', (string) (data_get($payload, 'server_time') ?? data_get($payload, 'timestamp') ?? '-')],
            ['payload.keys', implode(', ', array_keys($payload))],
        ]);
    }

    private function printApiTrace(): void
    {
        $this->newLine();
        $this->info('Code trace');
        $this->table(['Concern', 'Location'], [
            ['account_reference lookup', 'TradingAccountMetricsController::resolveAccount() -> trading_accounts.account_reference'],
            ['auth token extraction', 'bearerToken() first, then request input secret_token'],
            ['auth token validation', 'Mt5ConnectorCredentials::tokenMatches() -> hash_equals(meta.mt5_connector.secret_token, provided token)'],
            ['token generation/storage', 'TradingAccount::creating() and Mt5ConnectorCredentials::ensureToken() store Str::random(48) in meta.mt5_connector.secret_token'],
            ['metrics endpoint routes', 'POST /api/mt5/accounts/{accountIdentifier}/metrics and /api/integrations/mt5/accounts/{accountIdentifier}/metrics'],
        ]);
    }

    private function identityMatches(string $incoming, string $stored): string
    {
        if ($incoming === '') {
            return 'unknown; latest log has no incoming value';
        }

        if ($stored === '') {
            return 'unknown; stored value is empty';
        }

        return $incoming === $stored ? 'yes' : 'no';
    }

    private function mask(string $token): string
    {
        if ($token === '') {
            return '-';
        }

        return str_repeat('*', max(8, strlen($token) - 4)).substr($token, -4);
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[array]';
        }

        return (string) $value;
    }
}
