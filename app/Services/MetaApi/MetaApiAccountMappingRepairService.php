<?php

namespace App\Services\MetaApi;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaApiAccountMappingRepairService
{
    public function __construct(
        private readonly MetaApiClient $metaApi,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function repairByLogin(string $login, array $options = []): array
    {
        $login = trim($login);
        $dryRun = (bool) ($options['dry_run'] ?? false);

        if ($login === '') {
            return $this->blocked($login, ['login_missing'], ['Provide a non-empty MT5 login or account reference.']);
        }

        $poolEntry = $this->poolEntryForLogin($login);
        $resolution = $this->resolveTradingAccountForLogin($login, $poolEntry, (bool) ($options['assign'] ?? false));

        return $this->repair($login, $poolEntry, $resolution['account'], $options + [
            'dry_run' => $dryRun,
            'trading_account_resolution' => $resolution,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function repairAccount(TradingAccount $account, array $options = []): array
    {
        $login = (string) ($account->platform_login ?: $account->platform_account_id ?: $account->account_reference);
        $poolEntry = $this->poolEntryForAccount($account) ?? $this->poolEntryForLogin($login);

        return $this->repair($login, $poolEntry, $account, $options);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unassignedMetaApiPoolEntries(int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));

        return Mt5AccountPoolEntry::query()
            ->whereNull('allocated_trading_account_id')
            ->orderBy('id')
            ->limit($limit * 10)
            ->get()
            ->filter(fn (Mt5AccountPoolEntry $entry): bool => $this->looksLikeMetaApiAccountId((string) data_get($entry->meta, 'metaapi_account_id')))
            ->take($limit)
            ->map(function (Mt5AccountPoolEntry $entry): array {
                $resolution = $this->resolveTradingAccountForLogin((string) $entry->login, $entry, assign: true);

                return [
                    'pool_entry' => $this->poolEntrySummary($entry),
                    'trading_account_resolution' => [
                        'status' => $resolution['status'],
                        'source' => $resolution['source'],
                        'candidates' => $resolution['candidates'],
                    ],
                    'recommendation' => $resolution['account'] instanceof TradingAccount
                        ? "Run: php artisan wolforix:repair-metaapi-account {$entry->login} --assign"
                        : $this->adminAssignmentAction((string) $entry->login),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function repair(string $login, ?Mt5AccountPoolEntry $poolEntry, ?TradingAccount $tradingAccount, array $options): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $assign = (bool) ($options['assign'] ?? false);
        $allowApiLookup = (bool) ($options['allow_api_lookup'] ?? true);
        $manualMetaApiAccountId = trim((string) ($options['metaapi_account_id'] ?? ''));
        $tradingAccountResolution = (array) ($options['trading_account_resolution'] ?? []);
        $tradingAccountCandidates = (array) ($tradingAccountResolution['candidates'] ?? []);
        $resolutionSource = (string) ($tradingAccountResolution['source'] ?? 'unknown');
        $warnings = [];
        $errors = [];
        $recommendations = [];
        $changes = [];
        $apiLookup = null;

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $errors[] = 'pool_entry_missing';
            $recommendations[] = 'Import or create a MetaApi-enabled MT5 pool row for this login first.';
        }

        if (! $tradingAccount instanceof TradingAccount) {
            $errors[] = 'trading_account_missing';
            $recommendations[] = $this->adminAssignmentAction($login);
        }

        if (($tradingAccountResolution['status'] ?? null) === 'multiple_matches') {
            $errors[] = 'multiple_trading_account_candidates';
            $recommendations[] = 'Multiple trading accounts match this login. Set exactly one trading account to this MT5 login or clear the stale duplicate mapping before re-running with --assign.';
        }

        if (
            $poolEntry instanceof Mt5AccountPoolEntry
            && $poolEntry->allocated_trading_account_id === null
            && $tradingAccount instanceof TradingAccount
            && $resolutionSource !== 'direct'
            && $resolutionSource !== 'pool_allocation'
            && ! $assign
        ) {
            $errors[] = 'assignment_confirmation_required';
            $recommendations[] = "Re-run: php artisan wolforix:repair-metaapi-account {$login} --assign";
        }

        $poolAllocatedId = $poolEntry?->allocated_trading_account_id;
        $poolAssignedElsewhere = $poolEntry instanceof Mt5AccountPoolEntry
            && $tradingAccount instanceof TradingAccount
            && $poolAllocatedId !== null
            && (int) $poolAllocatedId !== (int) $tradingAccount->id;

        if ($poolAssignedElsewhere) {
            $errors[] = 'mapping_mismatch';
            $recommendations[] = 'Pool row is allocated to a different trading account. Inspect both accounts before changing ownership.';
        }

        $identityConflicts = $this->tradingAccountIdentityConflicts($tradingAccount, $login);

        if ($identityConflicts !== []) {
            $errors[] = 'trading_account_login_mismatch';
            $recommendations[] = 'Trading account already contains a different MT5 login/account id. Clear or correct that stale mapping before assigning this pool row.';
        }

        $localMetaApiAccountIds = $this->validMetaApiAccountIds([
            'manual' => $manualMetaApiAccountId,
            'pool.metaapi_account_id' => data_get($poolEntry?->meta, 'metaapi_account_id'),
            'trading_account.metaapi_account_id' => data_get($tradingAccount?->meta, 'metaapi_account_id'),
            'trading_account.mt5_sync.metaapi_account_id' => data_get($tradingAccount?->meta, 'mt5_sync.metaapi_account_id'),
            'trading_account.mt5_pool_entry.metaapi_account_id' => data_get($tradingAccount?->meta, 'mt5_pool_entry.metaapi_account_id'),
        ]);
        $uniqueLocalMetaApiAccountIds = array_values(array_unique(array_values($localMetaApiAccountIds)));
        $metaApiAccountId = $uniqueLocalMetaApiAccountIds[0] ?? null;
        $metaApiAccountIdMismatch = count($uniqueLocalMetaApiAccountIds) > 1;

        if ($metaApiAccountIdMismatch) {
            $errors[] = 'metaapi_account_id_mismatch';
            $recommendations[] = 'Pool and trading account contain different valid MetaApi UUIDs. Inspect the mappings before repairing.';
        }

        if ($metaApiAccountId === null && $allowApiLookup && $this->metaApi->isConfigured()) {
            $apiLookup = $this->lookupMetaApiAccount($login);
            $metaApiAccountId = $apiLookup['metaapi_account_id'];

            if (($apiLookup['status'] ?? null) !== 'found') {
                $warnings[] = 'metaapi_lookup_'.$apiLookup['status'];
            }
        }

        if ($metaApiAccountId === null) {
            $errors[] = 'metaapi_account_id_missing';
            $recommendations[] = 'Add --metaapi-account-id=<MetaApi UUID> or confirm the account exists in MetaApi for this login.';
        }

        $canonicalServer = $this->canonicalServer(
            data_get($apiLookup, 'server')
                ?: $poolEntry?->server
                ?: $tradingAccount?->platform_environment
        );

        if ($poolEntry instanceof Mt5AccountPoolEntry && $tradingAccount instanceof TradingAccount && $poolAllocatedId === null) {
            $changes[] = 'assign_pool_to_trading_account';
        }

        if ($poolEntry instanceof Mt5AccountPoolEntry && $metaApiAccountId !== null && ! $this->looksLikeMetaApiAccountId((string) data_get($poolEntry->meta, 'metaapi_account_id'))) {
            $changes[] = 'persist_pool_metaapi_account_id';
        }

        if ($tradingAccount instanceof TradingAccount && $metaApiAccountId !== null && ! $this->looksLikeMetaApiAccountId((string) data_get($tradingAccount->meta, 'metaapi_account_id'))) {
            $changes[] = 'persist_trading_account_metaapi_account_id';
        }

        if ($tradingAccount instanceof TradingAccount && $this->missingTradingAccountLogin($tradingAccount, $login)) {
            $changes[] = 'persist_trading_account_login';
        }

        if ($poolEntry instanceof Mt5AccountPoolEntry && $canonicalServer !== null && $poolEntry->server !== $canonicalServer) {
            $changes[] = 'normalize_pool_server';
        }

        if ($tradingAccount instanceof TradingAccount && $canonicalServer !== null && $tradingAccount->platform_environment !== $canonicalServer) {
            $changes[] = 'normalize_trading_account_server';
        }

        $blocked = $errors !== [];

        $result = [
            'status' => $blocked ? 'blocked' : ($changes === [] ? 'ok' : ($dryRun ? 'repair_available' : 'repaired')),
            'login' => $login,
            'dry_run' => $dryRun,
            'assign' => $assign,
            'pool_entry' => $this->poolEntrySummary($poolEntry),
            'trading_account' => $this->tradingAccountSummary($tradingAccount),
            'trading_account_resolution' => [
                'status' => (string) ($tradingAccountResolution['status'] ?? 'unknown'),
                'source' => $resolutionSource,
                'candidates' => $tradingAccountCandidates,
            ],
            'metaapi_account_id' => $metaApiAccountId,
            'canonical_server' => $canonicalServer,
            'mapping' => [
                'missing_assignment' => $poolEntry instanceof Mt5AccountPoolEntry && $poolEntry->allocated_trading_account_id === null,
                'missing_metaapi_id' => $metaApiAccountId === null,
                'mapping_mismatch' => $poolAssignedElsewhere || $metaApiAccountIdMismatch,
            ],
            'changes' => array_values(array_unique($changes)),
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'recommendations' => array_values(array_unique($recommendations)),
            'metaapi_lookup' => $apiLookup,
        ];

        if ($blocked || $dryRun || $changes === []) {
            return $result;
        }

        $written = DB::transaction(function () use ($login, $poolEntry, $tradingAccount, $metaApiAccountId, $canonicalServer, $result): array {
            /** @var Mt5AccountPoolEntry|null $lockedPoolEntry */
            $lockedPoolEntry = $poolEntry instanceof Mt5AccountPoolEntry
                ? Mt5AccountPoolEntry::query()->lockForUpdate()->find($poolEntry->id)
                : null;
            /** @var TradingAccount|null $lockedTradingAccount */
            $lockedTradingAccount = $tradingAccount instanceof TradingAccount
                ? TradingAccount::query()->lockForUpdate()->find($tradingAccount->id)
                : null;

            if ($lockedPoolEntry instanceof Mt5AccountPoolEntry && $lockedTradingAccount instanceof TradingAccount) {
                $poolMeta = is_array($lockedPoolEntry->meta) ? $lockedPoolEntry->meta : [];

                if ($lockedPoolEntry->allocated_trading_account_id === null) {
                    $lockedPoolEntry->allocated_trading_account_id = $lockedTradingAccount->id;
                    $lockedPoolEntry->allocated_user_id = $lockedTradingAccount->user_id;
                    $lockedPoolEntry->allocated_at = $lockedPoolEntry->allocated_at ?? now();
                    $lockedPoolEntry->is_available = false;
                    $lockedPoolEntry->source_status = 'assigned';
                }

                if ($metaApiAccountId !== null && ! $this->looksLikeMetaApiAccountId((string) data_get($poolMeta, 'metaapi_account_id'))) {
                    $poolMeta['metaapi_account_id'] = $metaApiAccountId;
                    $poolMeta['metaapi_registered_at'] = $poolMeta['metaapi_registered_at'] ?? now()->toIso8601String();
                }

                if ($canonicalServer !== null) {
                    $lockedPoolEntry->server = $canonicalServer;
                }

                $poolMeta['metaapi_mapping_repaired_at'] = now()->toIso8601String();
                $poolMeta['metaapi_mapping_repair_reason'] = 'wolforix_repair_metaapi_account';
                $lockedPoolEntry->meta = $poolMeta;
                $lockedPoolEntry->save();
            }

            if ($lockedTradingAccount instanceof TradingAccount) {
                $accountMeta = is_array($lockedTradingAccount->meta) ? $lockedTradingAccount->meta : [];

                if ($metaApiAccountId !== null) {
                    foreach (['metaapi_account_id', 'mt5_sync.metaapi_account_id', 'mt5_pool_entry.metaapi_account_id'] as $path) {
                        if (! $this->looksLikeMetaApiAccountId((string) data_get($accountMeta, $path))) {
                            data_set($accountMeta, $path, $metaApiAccountId);
                        }
                    }
                }

                if ($lockedPoolEntry instanceof Mt5AccountPoolEntry) {
                    data_set($accountMeta, 'mt5_pool_entry.id', data_get($accountMeta, 'mt5_pool_entry.id') ?: $lockedPoolEntry->id);
                }

                data_set($accountMeta, 'mt5_sync.mapping_repaired_at', now()->toIso8601String());
                data_set($accountMeta, 'mt5_sync.mapping_repair_reason', 'wolforix_repair_metaapi_account');

                $fill = [
                    'platform' => $lockedTradingAccount->platform ?: 'MT5',
                    'platform_slug' => $lockedTradingAccount->platform_slug ?: 'mt5',
                    'meta' => $accountMeta,
                ];

                if (blank($lockedTradingAccount->platform_login)) {
                    $fill['platform_login'] = $login;
                }

                if (blank($lockedTradingAccount->platform_account_id)) {
                    $fill['platform_account_id'] = $login;
                }

                data_set($accountMeta, 'mt5_sync.identifier', data_get($accountMeta, 'mt5_sync.identifier') ?: $login);
                data_set($accountMeta, 'mt5_sync.account_reference', data_get($accountMeta, 'mt5_sync.account_reference') ?: $lockedTradingAccount->account_reference);
                data_set($accountMeta, 'mt5_pool_entry.login', data_get($accountMeta, 'mt5_pool_entry.login') ?: $login);

                if ($canonicalServer !== null) {
                    $fill['platform_environment'] = $canonicalServer;
                    data_set($accountMeta, 'mt5_sync.server', data_get($accountMeta, 'mt5_sync.server') ?: $canonicalServer);
                }

                $fill['meta'] = $accountMeta;
                $lockedTradingAccount->forceFill($fill)->save();
            }

            Log::info('MetaApi account mapping repaired.', [
                'login' => $login,
                'pool_entry_id' => $lockedPoolEntry?->id,
                'trading_account_id' => $lockedTradingAccount?->id,
                'changes' => $result['changes'],
            ]);

            return $result;
        });

        $written['pool_entry'] = $this->poolEntrySummary($poolEntry instanceof Mt5AccountPoolEntry ? $poolEntry->fresh() : null);
        $written['trading_account'] = $this->tradingAccountSummary($tradingAccount instanceof TradingAccount ? $tradingAccount->fresh() : null);

        return $written;
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $recommendations
     * @return array<string, mixed>
     */
    private function blocked(string $login, array $errors, array $recommendations): array
    {
        return [
            'status' => 'blocked',
            'login' => $login,
            'pool_entry' => null,
            'trading_account' => null,
            'trading_account_resolution' => [
                'status' => 'blocked',
                'source' => 'none',
                'candidates' => [],
            ],
            'metaapi_account_id' => null,
            'canonical_server' => null,
            'mapping' => [
                'missing_assignment' => true,
                'missing_metaapi_id' => true,
                'mapping_mismatch' => false,
            ],
            'changes' => [],
            'warnings' => [],
            'errors' => $errors,
            'recommendations' => $recommendations,
            'metaapi_lookup' => null,
        ];
    }

    private function poolEntryForLogin(string $login): ?Mt5AccountPoolEntry
    {
        return Mt5AccountPoolEntry::query()
            ->where('login', trim($login))
            ->orderByRaw('allocated_trading_account_id is null')
            ->latest('allocated_at')
            ->latest('id')
            ->first();
    }

    private function poolEntryForAccount(TradingAccount $account): ?Mt5AccountPoolEntry
    {
        $poolEntryId = data_get($account->meta, 'mt5_pool_entry.id');

        if (is_numeric($poolEntryId)) {
            $poolEntry = Mt5AccountPoolEntry::query()->find((int) $poolEntryId);

            if ($poolEntry instanceof Mt5AccountPoolEntry) {
                return $poolEntry;
            }
        }

        return Mt5AccountPoolEntry::query()
            ->where(function ($query) use ($account): void {
                $query->where('allocated_trading_account_id', $account->id)
                    ->orWhere('login', (string) ($account->platform_login ?: $account->platform_account_id));
            })
            ->latest('allocated_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{status: string, source: string, account: TradingAccount|null, candidates: list<array<string, mixed>>}
     */
    private function resolveTradingAccountForLogin(string $login, ?Mt5AccountPoolEntry $poolEntry, bool $assign): array
    {
        if ($poolEntry?->allocated_trading_account_id !== null) {
            $account = $poolEntry->allocatedTradingAccount;

            if ($account instanceof TradingAccount) {
                return [
                    'status' => 'matched',
                    'source' => 'pool_allocation',
                    'account' => $account,
                    'candidates' => [$this->tradingAccountSummary($account)],
                ];
            }
        }

        $directMatches = $this->tradingAccountCandidatesForLogin($login, $poolEntry, includeLoginMetadata: false);

        if ($directMatches->count() === 1) {
            /** @var TradingAccount $account */
            $account = $directMatches->first();

            return [
                'status' => 'matched',
                'source' => 'direct',
                'account' => $account,
                'candidates' => [$this->tradingAccountSummary($account)],
            ];
        }

        if ($directMatches->count() > 1) {
            return [
                'status' => 'multiple_matches',
                'source' => 'direct',
                'account' => null,
                'candidates' => $directMatches->map(fn (TradingAccount $account): array => $this->tradingAccountSummary($account))->values()->all(),
            ];
        }

        if (! $assign) {
            return [
                'status' => 'not_found',
                'source' => 'none',
                'account' => null,
                'candidates' => [],
            ];
        }

        $loginMatches = $this->tradingAccountCandidatesForLogin($login, $poolEntry, includeLoginMetadata: true);

        if ($loginMatches->count() === 1) {
            /** @var TradingAccount $account */
            $account = $loginMatches->first();

            return [
                'status' => 'matched',
                'source' => 'login_metadata',
                'account' => $account,
                'candidates' => [$this->tradingAccountSummary($account)],
            ];
        }

        return [
            'status' => $loginMatches->isEmpty() ? 'not_found' : 'multiple_matches',
            'source' => 'login_metadata',
            'account' => null,
            'candidates' => $loginMatches->map(fn (TradingAccount $account): array => $this->tradingAccountSummary($account))->values()->all(),
        ];
    }

    /**
     * @return Collection<int, TradingAccount>
     */
    private function tradingAccountCandidatesForLogin(string $login, ?Mt5AccountPoolEntry $poolEntry, bool $includeLoginMetadata): Collection
    {
        return TradingAccount::query()
            ->where(function (Builder $query) use ($login, $poolEntry, $includeLoginMetadata): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login);

                if ($includeLoginMetadata) {
                    $query->orWhere('meta->mt5_sync->identifier', $login)
                        ->orWhere('meta->mt5_sync->platform_login', $login)
                        ->orWhere('meta->mt5_sync->platform_account_id', $login)
                        ->orWhere('meta->mt5_sync->login', $login)
                        ->orWhere('meta->mt5_pool_entry->login', $login);

                    if ($poolEntry instanceof Mt5AccountPoolEntry) {
                        $query->orWhere('meta->mt5_pool_entry->id', $poolEntry->id)
                            ->orWhere('meta->mt5_pool_entry->id', (string) $poolEntry->id);
                    }
                }
            })
            ->where(function (Builder $query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5')
                    ->orWhere('account_reference', 'like', 'WFX-MT5-%');
            })
            ->orderByRaw("CASE WHEN challenge_status = 'active' THEN 0 WHEN account_status = 'active' THEN 1 ELSE 2 END")
            ->latest('id')
            ->get();
    }

    /**
     * @return array{status: string, metaapi_account_id: ?string, server: ?string, error: ?string}
     */
    private function lookupMetaApiAccount(string $login): array
    {
        $result = $this->metaApi->readAccounts($login);

        if (! (bool) ($result['ok'] ?? false)) {
            return [
                'status' => 'failed',
                'metaapi_account_id' => null,
                'server' => null,
                'error' => $this->safeError($result),
            ];
        }

        $matches = collect($this->rowsFromPayload($result['payload'] ?? []))
            ->filter(fn (array $row): bool => (string) data_get($row, 'login') === $login)
            ->values();

        if ($matches->isEmpty()) {
            return [
                'status' => 'not_found',
                'metaapi_account_id' => null,
                'server' => null,
                'error' => null,
            ];
        }

        if ($matches->count() > 1) {
            return [
                'status' => 'multiple_matches',
                'metaapi_account_id' => null,
                'server' => null,
                'error' => null,
            ];
        }

        $match = $matches->first();
        $accountId = (string) (data_get($match, '_id') ?: data_get($match, 'id'));

        return [
            'status' => $this->looksLikeMetaApiAccountId($accountId) ? 'found' : 'invalid_id',
            'metaapi_account_id' => $this->looksLikeMetaApiAccountId($accountId) ? $accountId : null,
            'server' => data_get($match, 'server'),
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

    /**
     * @param  array<string, mixed>  $candidates
     * @return array<string, string>
     */
    private function validMetaApiAccountIds(array $candidates): array
    {
        $valid = [];

        foreach ($candidates as $source => $candidate) {
            $candidate = trim((string) $candidate);

            if ($this->looksLikeMetaApiAccountId($candidate)) {
                $valid[(string) $source] = $candidate;
            }
        }

        return $valid;
    }

    private function canonicalServer(mixed $server): ?string
    {
        $server = trim((string) $server);

        if ($server === '') {
            return null;
        }

        if (str_contains(strtolower($server), 'fusionmarkets')) {
            return (string) config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo');
        }

        if (str_contains(strtolower($server), 'fusion markets')) {
            return (string) config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo');
        }

        return $server;
    }

    /**
     * @return list<string>
     */
    private function tradingAccountIdentityConflicts(?TradingAccount $account, string $login): array
    {
        if (! $account instanceof TradingAccount) {
            return [];
        }

        $conflicts = [];

        foreach (['platform_login', 'platform_account_id'] as $field) {
            $value = trim((string) $account->{$field});

            if ($value !== '' && $value !== $login) {
                $conflicts[] = $field;
            }
        }

        return $conflicts;
    }

    private function missingTradingAccountLogin(TradingAccount $account, string $login): bool
    {
        return blank($account->platform_login)
            || blank($account->platform_account_id)
            || (string) data_get($account->meta, 'mt5_sync.identifier') !== $login
            || (string) data_get($account->meta, 'mt5_pool_entry.login') !== $login;
    }

    private function adminAssignmentAction(string $login): string
    {
        return "Admin action required: create or update exactly one MT5 trading account so platform_login, platform_account_id, account_reference, meta.mt5_sync.identifier, meta.mt5_sync.platform_login, meta.mt5_sync.platform_account_id, meta.mt5_sync.login, or meta.mt5_pool_entry.login equals {$login}; then run php artisan wolforix:repair-metaapi-account {$login} --assign.";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function poolEntrySummary(?Mt5AccountPoolEntry $entry): ?array
    {
        if (! $entry instanceof Mt5AccountPoolEntry) {
            return null;
        }

        return [
            'id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
            'allocated_trading_account_id' => $entry->allocated_trading_account_id,
            'allocated_user_id' => $entry->allocated_user_id,
            'is_available' => (bool) $entry->is_available,
            'source_status' => $entry->source_status,
            'metaapi_account_id' => data_get($entry->meta, 'metaapi_account_id'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tradingAccountSummary(?TradingAccount $account): ?array
    {
        if (! $account instanceof TradingAccount) {
            return null;
        }

        return [
            'id' => $account->id,
            'account_reference' => $account->account_reference,
            'platform_login' => $account->platform_login,
            'platform_account_id' => $account->platform_account_id,
            'platform_environment' => $account->platform_environment,
            'account_status' => $account->account_status,
            'challenge_status' => $account->challenge_status,
            'metaapi_account_id' => data_get($account->meta, 'metaapi_account_id'),
            'mt5_sync_metaapi_account_id' => data_get($account->meta, 'mt5_sync.metaapi_account_id'),
            'mt5_pool_entry_id' => data_get($account->meta, 'mt5_pool_entry.id'),
        ];
    }

    private function safeError(array $response): ?string
    {
        $error = $response['error'] ?? data_get($response, 'payload.message') ?? data_get($response, 'payload.error');

        return filled($error) ? (string) $error : null;
    }

    private function looksLikeMetaApiAccountId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id));
    }
}
