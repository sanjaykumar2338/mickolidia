<?php

namespace App\Services\MetaApi;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class MetaQuotesAssignmentMetaApiService
{
    public const STATUS_POOL_ONLY = 'pool_only';

    public const STATUS_ASSIGNED_PENDING_METAAPI = 'assigned_pending_metaapi';

    public const STATUS_METAAPI_REGISTERED = 'metaapi_registered';

    public const STATUS_DEPLOYED = 'deployed';

    public const STATUS_SYNC_ACTIVE = 'sync_active';

    public const STATUS_METAAPI_BILLING_BLOCKED = 'metaapi_billing_blocked';

    public function __construct(
        private readonly MetaApiClient $metaApi,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function markPoolOnly(Mt5AccountPoolEntry $entry, array $context = []): array
    {
        if (! $this->isMetaQuotesPoolEntry($entry)) {
            return [
                'status' => 'skipped_non_metaquotes',
                'pool_entry_id' => $entry->id,
            ];
        }

        $this->stampPoolEntry($entry, self::STATUS_POOL_ONLY, array_merge([
            'stage' => 'pool_inventory',
            'message' => 'MT5 demo credentials are stored as pool inventory. MetaApi registration/deployment is deferred until assignment.',
        ], $context));

        return [
            'status' => self::STATUS_POOL_ONLY,
            'pool_entry_id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureReadyForAssignedAccount(TradingAccount $account, Mt5AccountPoolEntry $entry, array $options = []): array
    {
        $account = $account->fresh() ?? $account;
        $entry = $entry->fresh() ?? $entry;

        if (! $this->isMetaQuotesPoolEntry($entry)) {
            return [
                'status' => 'skipped_non_metaquotes',
                'trading_account_id' => $account->id,
                'pool_entry_id' => $entry->id,
            ];
        }

        if (! (bool) config('services.metaapi.onboarding.assignment_registration_enabled', true)) {
            $this->stampAssignedPending($account, $entry, [
                'message' => 'MetaApi assignment registration is disabled by configuration.',
                'registration_enabled' => false,
            ]);

            return [
                'status' => self::STATUS_ASSIGNED_PENDING_METAAPI,
                'trading_account_id' => $account->id,
                'pool_entry_id' => $entry->id,
                'registration_enabled' => false,
            ];
        }

        $existingBlock = $this->activeBillingBlock($account, $entry);

        if ($existingBlock !== null && ! (bool) ($options['force'] ?? false)) {
            return [
                'status' => self::STATUS_METAAPI_BILLING_BLOCKED,
                'trading_account_id' => $account->id,
                'pool_entry_id' => $entry->id,
                'stage' => $existingBlock['stage'] ?? null,
                'blocked_until' => $existingBlock['blocked_until'] ?? null,
                'skipped' => true,
            ];
        }

        $this->stampAssignedPending($account, $entry, [
            'message' => 'MT5 pool account is assigned; MetaApi registration/deployment can now run.',
        ]);

        $metaApiAccountId = $this->metaApiAccountId($account, $entry);
        $registration = null;

        if ($metaApiAccountId === null) {
            $registration = $this->registerMetaApiAccount($account, $entry);

            if (($registration['status'] ?? null) === self::STATUS_METAAPI_BILLING_BLOCKED) {
                $this->stampBillingBlocked($account, $entry, 'register', $registration);

                return $registration + [
                    'trading_account_id' => $account->id,
                    'pool_entry_id' => $entry->id,
                ];
            }

            $metaApiAccountId = $registration['metaapi_account_id'] ?? null;

            if (! $this->looksLikeMetaApiAccountId((string) $metaApiAccountId)) {
                $this->stampAssignedPending($account, $entry, [
                    'message' => 'MetaApi registration did not return a confirmed UUID.',
                    'stage' => 'register',
                    'error' => $registration['error'] ?? 'metaapi_account_id_missing',
                ]);

                return [
                    'status' => self::STATUS_ASSIGNED_PENDING_METAAPI,
                    'trading_account_id' => $account->id,
                    'pool_entry_id' => $entry->id,
                    'stage' => 'register',
                    'error' => $registration['error'] ?? 'metaapi_account_id_missing',
                    'registration' => $registration,
                ];
            }

            $this->persistMetaApiAccountId($account, $entry, (string) $metaApiAccountId, [
                'source' => 'metaquotes_assignment_metaapi_registration',
            ]);
            $account = $account->fresh() ?? $account;
            $entry = $entry->fresh() ?? $entry;
        }

        $this->stampBoth($account, $entry, self::STATUS_METAAPI_REGISTERED, [
            'stage' => 'register',
            'message' => 'MetaApi UUID is stored and ready for deployment.',
            'metaapi_account_id' => $metaApiAccountId,
            'registration' => $registration,
        ]);

        if (! (bool) ($options['deploy'] ?? config('services.metaapi.onboarding.deploy_on_assignment', true))) {
            return [
                'status' => self::STATUS_METAAPI_REGISTERED,
                'trading_account_id' => $account->id,
                'pool_entry_id' => $entry->id,
                'metaapi_account_id' => $metaApiAccountId,
                'deploy_skipped' => true,
            ];
        }

        $deployment = $this->deployMetaApiAccount($account, $entry, (string) $metaApiAccountId);

        if (($deployment['status'] ?? null) === self::STATUS_METAAPI_BILLING_BLOCKED) {
            $this->stampBillingBlocked($account, $entry, 'deploy', $deployment);

            return $deployment + [
                'trading_account_id' => $account->id,
                'pool_entry_id' => $entry->id,
                'metaapi_account_id' => $metaApiAccountId,
            ];
        }

        if (($deployment['status'] ?? null) === self::STATUS_DEPLOYED) {
            $this->stampBoth($account, $entry, self::STATUS_DEPLOYED, [
                'stage' => 'deploy',
                'message' => 'MetaApi deployment was accepted or the account was already deployed.',
                'metaapi_account_id' => $metaApiAccountId,
                'deploy_state' => $deployment['deploy_state'] ?? null,
                'connection_status' => $deployment['connection_status'] ?? null,
            ]);
        }

        return $deployment + [
            'trading_account_id' => $account->id,
            'pool_entry_id' => $entry->id,
            'metaapi_account_id' => $metaApiAccountId,
        ];
    }

    public function markSyncActive(TradingAccount $account, array $context = []): void
    {
        $poolEntry = $this->poolEntryForAccount($account);

        if (! $poolEntry instanceof Mt5AccountPoolEntry || ! $this->isMetaQuotesPoolEntry($poolEntry)) {
            return;
        }

        $this->stampBoth($account, $poolEntry, self::STATUS_SYNC_ACTIVE, array_merge([
            'stage' => 'sync',
            'message' => 'MetaApi sync is active and dashboard metrics are readable.',
        ], $context));
    }

    public function isMetaQuotesPoolEntry(Mt5AccountPoolEntry $entry): bool
    {
        $broker = Str::lower((string) (data_get($entry->meta, 'broker') ?: data_get($entry->meta, 'provider')));
        $server = Str::lower((string) $entry->server);
        $sourcePool = (string) $entry->source_pool;

        if ($sourcePool === 'metaquotes_demo_pool') {
            return true;
        }

        if (Str::contains($broker, 'metaquotes')) {
            return true;
        }

        return Str::contains($server, 'metaquotes-demo');
    }

    /**
     * @return array<string, mixed>
     */
    private function registerMetaApiAccount(TradingAccount $account, Mt5AccountPoolEntry $entry): array
    {
        if (! $this->metaApi->isConfigured()) {
            return [
                'status' => self::STATUS_ASSIGNED_PENDING_METAAPI,
                'error' => 'metaapi_not_configured',
            ];
        }

        $password = $this->credential($entry, 'password');

        if ($password === null) {
            return [
                'status' => self::STATUS_ASSIGNED_PENDING_METAAPI,
                'error' => 'mt5_password_missing',
            ];
        }

        $payload = array_filter([
            'login' => (string) $entry->login,
            'password' => $password,
            'name' => 'Wolforix MetaQuotes '.$entry->login,
            'server' => (string) $entry->server,
            'platform' => 'mt5',
            'magic' => 20260525,
            'type' => config('services.metaapi.account_type'),
            'reliability' => config('services.metaapi.account_reliability', 'regular'),
            'provisioningProfileId' => $this->provisioningProfileIdForCreate(),
            'keywords' => ['MetaQuotes', 'MetaQuotes-Demo'],
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $transactionId = $this->metaApi->transactionId();
        $maxAttempts = max(1, 1 + (int) config('services.metaapi.demo.accepted_retries', 3));
        $lastResult = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->metaApi->createAccount($payload, $transactionId);
            $lastResult = $result;

            if ($this->billingBlocked($result)) {
                return $this->billingBlockResult('register', $result);
            }

            $metaApiAccountId = $this->createdAccountId($result);

            if ($metaApiAccountId !== null) {
                return [
                    'status' => self::STATUS_METAAPI_REGISTERED,
                    'metaapi_account_id' => $metaApiAccountId,
                    'response_status' => $result['status'] ?? null,
                ];
            }

            if ((int) ($result['status'] ?? 0) !== 202) {
                break;
            }

            $this->sleep((int) config('services.metaapi.demo.accepted_retry_delay_seconds', 30));
        }

        $existing = $this->lookupExistingMetaApiAccount((string) $entry->login, (string) $entry->server);

        if ($existing['metaapi_account_id'] !== null) {
            return [
                'status' => self::STATUS_METAAPI_REGISTERED,
                'metaapi_account_id' => $existing['metaapi_account_id'],
                'response_status' => $lastResult['status'] ?? null,
                'lookup' => $existing,
            ];
        }

        return [
            'status' => self::STATUS_ASSIGNED_PENDING_METAAPI,
            'response_status' => $lastResult['status'] ?? null,
            'payload_keys' => is_array($lastResult['payload'] ?? null) ? array_keys((array) $lastResult['payload']) : [],
            'payload_id' => data_get($lastResult, 'payload.id'),
            'error' => $this->safeError($lastResult ?? []),
            'lookup' => $existing,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deployMetaApiAccount(TradingAccount $account, Mt5AccountPoolEntry $entry, string $metaApiAccountId): array
    {
        $read = $this->metaApi->readAccount($metaApiAccountId);

        if ($this->billingBlocked($read)) {
            return $this->billingBlockResult('deploy', $read);
        }

        $state = Str::upper((string) data_get($read, 'payload.state'));
        $connectionStatus = Str::upper((string) data_get($read, 'payload.connectionStatus'));

        if ((bool) ($read['ok'] ?? false) && $state === 'DEPLOYED') {
            return [
                'status' => self::STATUS_DEPLOYED,
                'metaapi_account_id' => $metaApiAccountId,
                'deploy_state' => $state,
                'connection_status' => $connectionStatus,
                'already_deployed' => true,
            ];
        }

        $deploy = $this->metaApi->deployAccount($metaApiAccountId);

        if ($this->billingBlocked($deploy)) {
            return $this->billingBlockResult('deploy', $deploy);
        }

        if ((bool) ($deploy['ok'] ?? false) || (int) ($deploy['status'] ?? 0) === 204) {
            return [
                'status' => self::STATUS_DEPLOYED,
                'metaapi_account_id' => $metaApiAccountId,
                'deploy_state' => 'DEPLOY_REQUESTED',
                'connection_status' => $connectionStatus ?: null,
                'response_status' => $deploy['status'] ?? null,
            ];
        }

        $this->stampAssignedPending($account, $entry, [
            'stage' => 'deploy',
            'message' => 'MetaApi deployment did not complete.',
            'metaapi_account_id' => $metaApiAccountId,
            'error' => $this->safeError($deploy),
        ]);

        return [
            'status' => self::STATUS_METAAPI_REGISTERED,
            'metaapi_account_id' => $metaApiAccountId,
            'stage' => 'deploy',
            'response_status' => $deploy['status'] ?? null,
            'error' => $this->safeError($deploy),
        ];
    }

    /**
     * @return array{metaapi_account_id: ?string, status: string, server: ?string, error: ?string}
     */
    private function lookupExistingMetaApiAccount(string $login, string $server): array
    {
        if (! $this->metaApi->isConfigured()) {
            return [
                'status' => 'skipped_not_configured',
                'metaapi_account_id' => null,
                'server' => null,
                'error' => null,
            ];
        }

        $lookup = $this->metaApi->readAccounts($login);

        if (! (bool) ($lookup['ok'] ?? false)) {
            return [
                'status' => 'failed',
                'metaapi_account_id' => null,
                'server' => null,
                'error' => $this->safeError($lookup),
            ];
        }

        $matches = collect($this->rowsFromPayload($lookup['payload'] ?? []))
            ->filter(fn (array $row): bool => (string) data_get($row, 'login') === $login)
            ->values();
        $serverMatches = $matches
            ->filter(fn (array $row): bool => $this->serverMatches((string) data_get($row, 'server'), $server))
            ->values();
        $selected = $serverMatches->first() ?: ($matches->count() === 1 ? $matches->first() : null);
        $accountId = is_array($selected) ? (string) (data_get($selected, '_id') ?: data_get($selected, 'id')) : null;

        return [
            'status' => $this->looksLikeMetaApiAccountId((string) $accountId) ? 'found' : ($matches->isEmpty() ? 'not_found' : 'ambiguous'),
            'metaapi_account_id' => $this->looksLikeMetaApiAccountId((string) $accountId) ? (string) $accountId : null,
            'server' => is_array($selected) ? data_get($selected, 'server') : null,
            'error' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsFromPayload(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return collect($payload)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->values()
                ->all();
        }

        foreach (['data', 'items', 'rows', 'records', 'accounts', 'payload', 'result'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $this->rowsFromPayload($payload[$key]);
            }
        }

        return [];
    }

    private function persistMetaApiAccountId(TradingAccount $account, Mt5AccountPoolEntry $entry, string $metaApiAccountId, array $context = []): void
    {
        $poolMeta = is_array($entry->meta) ? $entry->meta : [];
        $poolMeta['metaapi_account_id'] = $metaApiAccountId;
        $poolMeta['metaapi_registered_at'] = $poolMeta['metaapi_registered_at'] ?? now()->toIso8601String();
        $poolMeta['metaapi_registration_source'] = $context['source'] ?? 'metaquotes_assignment';

        $entry->forceFill(['meta' => $poolMeta])->save();

        $accountMeta = is_array($account->meta) ? $account->meta : [];

        foreach (['metaapi_account_id', 'mt5_sync.metaapi_account_id', 'mt5_pool_entry.metaapi_account_id'] as $path) {
            data_set($accountMeta, $path, $metaApiAccountId);
        }

        $account->forceFill([
            'sync_source' => 'metaapi',
            'meta' => $accountMeta,
        ])->save();
    }

    private function stampAssignedPending(TradingAccount $account, Mt5AccountPoolEntry $entry, array $context = []): void
    {
        $this->stampBoth($account, $entry, self::STATUS_ASSIGNED_PENDING_METAAPI, array_merge([
            'stage' => 'assigned',
        ], $context));
    }

    private function stampBillingBlocked(TradingAccount $account, Mt5AccountPoolEntry $entry, string $stage, array $context): void
    {
        $this->stampBoth($account, $entry, self::STATUS_METAAPI_BILLING_BLOCKED, array_merge([
            'stage' => $stage,
            'message' => 'MetaApi returned a billing/top-up/rate-limit block. Further automatic registration/deploy attempts are paused.',
            'blocked_until' => $context['blocked_until'] ?? $this->blockedUntil([])->toIso8601String(),
        ], $context));
    }

    private function stampBoth(TradingAccount $account, Mt5AccountPoolEntry $entry, string $status, array $context = []): void
    {
        $this->stampPoolEntry($entry, $status, $context);
        $this->stampTradingAccount($account, $status, $context + [
            'pool_entry_id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
        ]);
    }

    private function stampPoolEntry(Mt5AccountPoolEntry $entry, string $status, array $context = []): void
    {
        $meta = is_array($entry->meta) ? $entry->meta : [];
        $meta['metaapi_workflow'] = $this->workflowPayload($status, $context);

        $entry->forceFill(['meta' => $meta])->save();
    }

    private function stampTradingAccount(TradingAccount $account, string $status, array $context = []): void
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $meta['metaapi_workflow'] = $this->workflowPayload($status, $context);

        if (in_array($status, [self::STATUS_DEPLOYED, self::STATUS_SYNC_ACTIVE], true)) {
            data_set($meta, 'mt5_sync.status', $status === self::STATUS_SYNC_ACTIVE ? 'connected' : 'waiting_for_first_sync');
        }

        $fill = [
            'meta' => $meta,
        ];

        if ($status === self::STATUS_ASSIGNED_PENDING_METAAPI && blank($account->sync_source)) {
            $fill['sync_source'] = 'mt5_pool';
        }

        if (in_array($status, [self::STATUS_METAAPI_REGISTERED, self::STATUS_DEPLOYED, self::STATUS_SYNC_ACTIVE], true)) {
            $fill['sync_source'] = 'metaapi';
        }

        if ($status === self::STATUS_METAAPI_BILLING_BLOCKED) {
            $fill['sync_status'] = 'blocked';
            $fill['sync_error'] = 'metaapi_billing_blocked';
            $fill['sync_error_at'] = now();
        }

        $account->forceFill($fill)->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowPayload(string $status, array $context): array
    {
        $blockedUntil = $context['blocked_until'] ?? null;

        return array_filter(array_merge($context, [
            'status' => $status,
            'last_checked_at' => now()->toIso8601String(),
            'blocked_until' => $blockedUntil instanceof Carbon ? $blockedUntil->toIso8601String() : $blockedUntil,
        ]), static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeBillingBlock(TradingAccount $account, Mt5AccountPoolEntry $entry): ?array
    {
        foreach ([data_get($account->meta, 'metaapi_workflow'), data_get($entry->meta, 'metaapi_workflow')] as $workflow) {
            if (! is_array($workflow) || ($workflow['status'] ?? null) !== self::STATUS_METAAPI_BILLING_BLOCKED) {
                continue;
            }

            $blockedUntil = $this->dateValue($workflow['blocked_until'] ?? null);

            if ($blockedUntil === null || $blockedUntil->isFuture()) {
                return $workflow;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function billingBlockResult(string $stage, array $response): array
    {
        return [
            'status' => self::STATUS_METAAPI_BILLING_BLOCKED,
            'stage' => $stage,
            'response_status' => $response['status'] ?? null,
            'error' => $this->safeError($response),
            'retry_after' => $response['retry_after'] ?? null,
            'blocked_until' => $this->blockedUntil($response)->toIso8601String(),
        ];
    }

    private function billingBlocked(array $response): bool
    {
        $status = (int) ($response['status'] ?? 0);
        $error = Str::lower((string) $this->safeError($response));

        if ($status === 429 || $status === 402) {
            return true;
        }

        return $status === 403 && Str::contains($error, ['top up', 'billing', 'quota', 'high reliability', 'deployment']);
    }

    private function blockedUntil(array $response): Carbon
    {
        $retryAfter = trim((string) ($response['retry_after'] ?? ''));

        if (ctype_digit($retryAfter)) {
            return now()->addSeconds((int) $retryAfter);
        }

        if ($retryAfter !== '') {
            try {
                return Carbon::parse($retryAfter);
            } catch (Throwable) {
                // Fall back to the configured cooldown below.
            }
        }

        return now()->addMinutes(max(1, (int) config('services.metaapi.onboarding.billing_block_retry_minutes', 60)));
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
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function metaApiAccountId(TradingAccount $account, Mt5AccountPoolEntry $entry): ?string
    {
        foreach ([
            data_get($account->meta, 'metaapi_account_id'),
            data_get($account->meta, 'mt5_sync.metaapi_account_id'),
            data_get($account->meta, 'mt5_pool_entry.metaapi_account_id'),
            data_get($entry->meta, 'metaapi_account_id'),
        ] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($this->looksLikeMetaApiAccountId($candidate)) {
                return $candidate;
            }
        }

        return null;
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

        return Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->latest('allocated_at')
            ->latest('id')
            ->first();
    }

    private function credential(Mt5AccountPoolEntry $entry, string $key): ?string
    {
        $value = $entry->{$key};

        return filled($value) ? (string) $value : null;
    }

    private function provisioningProfileIdForCreate(): ?string
    {
        $profileId = trim((string) config('services.metaapi.profile_id', ''));

        return $profileId !== '' && $profileId !== 'default' ? $profileId : null;
    }

    private function createdAccountId(array $response): ?string
    {
        if (! in_array((int) ($response['status'] ?? 0), [201, 202], true)) {
            return null;
        }

        $id = (string) (data_get($response, 'payload.id') ?: data_get($response, 'payload._id') ?: '');

        return $this->looksLikeMetaApiAccountId($id) ? $id : null;
    }

    private function serverMatches(string $left, string $right): bool
    {
        return $this->normalizedServer($left) === $this->normalizedServer($right);
    }

    private function normalizedServer(string $server): string
    {
        return Str::lower((string) preg_replace('/[^a-z0-9]+/i', '', $server));
    }

    private function safeError(array $response): ?string
    {
        $error = $response['error'] ?? data_get($response, 'payload.message') ?? data_get($response, 'payload.error');
        $error = filled($error) ? (string) $error : null;
        $token = (string) config('services.metaapi.token', '');

        if ($error !== null && $token !== '') {
            $error = str_replace($token, '[redacted]', $error);
        }

        return $error;
    }

    private function looksLikeMetaApiAccountId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id));
    }

    private function sleep(int $seconds): void
    {
        if ($seconds <= 0 || app()->runningUnitTests()) {
            return;
        }

        sleep($seconds);
    }
}
