<?php

namespace App\Services\MetaQuotes;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\MetaApi\MetaQuotesAssignmentMetaApiService;
use App\Services\Mt5\Mt5AccountAllocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaQuotesPoolProvisioningService
{
    public function __construct(
        private readonly Mt5AccountAllocator $allocator,
        private readonly MetaQuotesAssignmentMetaApiService $assignmentMetaApi,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function provisionForAccount(TradingAccount $account, array $options = []): array
    {
        $account = $account->fresh() ?? $account;
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $warnings = [];
        $errors = [];

        $existingAssignment = $this->poolEntryForAccount($account);

        if ($existingAssignment instanceof Mt5AccountPoolEntry) {
            $warnings = array_merge($warnings, $this->mappingWarnings($account, $existingAssignment));
            $metaApiAssignment = null;

            if (! $dryRun) {
                $this->syncMetaApiMapping($account, $existingAssignment);
                $this->stampProvisioning($account->fresh() ?? $account, $existingAssignment, 'already_assigned', [
                    'source' => (string) ($options['source'] ?? 'metaquotes_pool_provisioning'),
                ]);
                $metaApiAssignment = $this->assignmentMetaApi->ensureReadyForAssignedAccount($account->fresh() ?? $account, $existingAssignment, [
                    'force' => (bool) ($options['force_metaapi'] ?? false),
                ]);
                $account = $account->fresh() ?? $account;
                $existingAssignment = $existingAssignment->fresh() ?? $existingAssignment;
            }

            return [
                'status' => 'already_assigned',
                'dry_run' => $dryRun,
                'trading_account' => $this->accountSummary($account->fresh() ?? $account),
                'pool_entry' => $this->poolSummary($existingAssignment->fresh() ?? $existingAssignment),
                'metaapi_assignment' => $metaApiAssignment,
                'warnings' => array_values(array_unique($warnings)),
                'errors' => [],
                'recommendations' => $this->recommendations([], $warnings),
                'changes' => $dryRun ? [] : ['existing_assignment_confirmed', 'provisioning_metadata_refreshed'],
            ];
        }

        $candidate = $this->candidatePoolEntry($account, $options);

        if (! $candidate instanceof Mt5AccountPoolEntry) {
            $errors[] = 'pool_exhausted';

            if (! $dryRun) {
                $this->stampProvisioning($account, null, 'pool_exhausted', [
                    'source' => (string) ($options['source'] ?? 'metaquotes_pool_provisioning'),
                    'server' => $this->serverOption($options),
                ]);
            }
        } elseif ((int) $candidate->account_size !== (int) $account->account_size && ! $force) {
            $errors[] = 'account_size_mismatch';
        }

        $result = [
            'status' => $errors === [] ? ($dryRun ? 'ready' : 'ready_to_assign') : 'blocked',
            'dry_run' => $dryRun,
            'trading_account' => $this->accountSummary($account),
            'pool_entry' => $this->poolSummary($candidate),
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'recommendations' => $this->recommendations($errors, $warnings),
        ];

        if ($dryRun || $errors !== []) {
            return $result;
        }

        $assigned = DB::transaction(function () use ($account, $candidate, $options, $force): Mt5AccountPoolEntry {
            /** @var TradingAccount $lockedAccount */
            $lockedAccount = TradingAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            $existing = $this->poolEntryForAccount($lockedAccount);

            if ($existing instanceof Mt5AccountPoolEntry) {
                return $existing;
            }

            /** @var Mt5AccountPoolEntry $lockedEntry */
            $lockedEntry = Mt5AccountPoolEntry::query()
                ->lockForUpdate()
                ->findOrFail($candidate->id);

            if ($lockedEntry->allocated_trading_account_id !== null
                && (int) $lockedEntry->allocated_trading_account_id !== (int) $lockedAccount->id
            ) {
                throw new RuntimeException('pool_entry_allocated_elsewhere');
            }

            if ((int) $lockedEntry->account_size !== (int) $lockedAccount->account_size && ! $force) {
                throw new RuntimeException('account_size_mismatch');
            }

            $assignedEntry = $this->allocator->allocateSpecific($lockedAccount, $lockedEntry, [
                'force_account_size' => $force,
            ]);

            $this->stampProvisioning($lockedAccount->fresh() ?? $lockedAccount, $assignedEntry, 'assigned', [
                'source' => (string) ($options['source'] ?? 'metaquotes_pool_provisioning'),
            ]);

            return $assignedEntry->fresh() ?? $assignedEntry;
        });

        $account = $account->fresh() ?? $account;
        $metaApiAssignment = $this->assignmentMetaApi->ensureReadyForAssignedAccount($account, $assigned, [
            'force' => (bool) ($options['force_metaapi'] ?? false),
        ]);
        $account = $account->fresh() ?? $account;
        $assigned = $assigned->fresh() ?? $assigned;

        return array_merge($result, [
            'status' => 'assigned',
            'trading_account' => $this->accountSummary($account),
            'pool_entry' => $this->poolSummary($assigned),
            'metaapi_assignment' => $metaApiAssignment,
            'warnings' => array_values(array_unique(array_merge($warnings, $this->mappingWarnings($account, $assigned)))),
            'changes' => [
                'pool_entry_allocated',
                'trading_account_bound',
                'credentials_hydrated',
                'provisioning_metadata_persisted',
                'assignment_time_metaapi_checked',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function diagnose(array $options = []): array
    {
        $server = $this->serverOption($options);
        $availableQuery = $this->availablePoolQuery($options);
        $allScoped = $this->scopedPoolQuery($options);
        $allocatedRows = (clone $allScoped)
            ->whereNotNull('allocated_trading_account_id');
        $staleAllocations = (clone $allocatedRows)
            ->whereDoesntHave('allocatedTradingAccount')
            ->count();
        $missingMetaApiMappingRows = (clone $allocatedRows)
            ->where(function (Builder $query): void {
                $query->whereNull('meta->metaapi_account_id')
                    ->orWhere('meta->metaapi_account_id', '');
            })
            ->get();
        $assignedPendingMetaApi = $missingMetaApiMappingRows
            ->filter(fn (Mt5AccountPoolEntry $entry): bool => $this->assignmentMetaApi->isMetaQuotesPoolEntry($entry))
            ->count();
        $missingMetaApiMapping = $missingMetaApiMappingRows->count() - $assignedPendingMetaApi;
        $duplicateLoginRows = $this->duplicateLoginRows($options);
        $blockers = [];

        if ((clone $availableQuery)->count() === 0) {
            $blockers[] = 'pool_exhausted';
        }

        if ($staleAllocations > 0) {
            $blockers[] = 'stale_allocations';
        }

        if ($missingMetaApiMapping > 0) {
            $blockers[] = 'missing_metaapi_mapping';
        }

        if ($duplicateLoginRows->isNotEmpty()) {
            $blockers[] = 'duplicate_login_risk';
        }

        return [
            'server' => $server,
            'available_accounts' => (clone $availableQuery)->count(),
            'allocated_accounts' => (clone $allocatedRows)->count(),
            'stale_allocations' => $staleAllocations,
            'missing_metaapi_mapping' => $missingMetaApiMapping,
            'assigned_pending_metaapi' => $assignedPendingMetaApi,
            'missing_metaapi_mappings' => $this->missingMetaApiMappingRows($options)->values()->all(),
            'duplicate_login_risk' => $duplicateLoginRows->count(),
            'duplicate_logins' => $duplicateLoginRows->take(10)->values()->all(),
            'onboarding_blockers' => array_values(array_unique($blockers)),
            'recommendations' => $this->diagnosticRecommendations($blockers),
        ];
    }

    public function resolveAccountForSubject(string $subject, ?string $accountOverride = null): ?TradingAccount
    {
        $accountSearch = trim((string) $accountOverride);

        if ($accountSearch !== '') {
            return $this->resolveTradingAccount($accountSearch);
        }

        $subject = trim($subject);

        if ($subject === '') {
            return null;
        }

        $user = $this->resolveUser($subject);

        if ($user instanceof User) {
            return $user->challengeTradingAccounts()
                ->orderByRaw("CASE WHEN challenge_status = 'pending_activation' THEN 0 WHEN account_status = 'pending_activation' THEN 1 WHEN challenge_status = 'active' THEN 2 ELSE 3 END")
                ->latest('id')
                ->first()
                ?? $user->tradingAccounts()->latest('id')->first();
        }

        return $this->resolveTradingAccount($subject);
    }

    private function resolveUser(string $subject): ?User
    {
        if (ctype_digit($subject)) {
            $user = User::query()->find((int) $subject);

            if ($user instanceof User) {
                return $user;
            }
        }

        return User::query()
            ->where('email', $subject)
            ->orWhere('name', $subject)
            ->first();
    }

    private function resolveTradingAccount(string $subject): ?TradingAccount
    {
        if (ctype_digit($subject)) {
            $account = TradingAccount::query()->find((int) $subject);

            if ($account instanceof TradingAccount) {
                return $account;
            }
        }

        return TradingAccount::query()
            ->where(function (Builder $query) use ($subject): void {
                $query->where('platform_login', $subject)
                    ->orWhere('platform_account_id', $subject)
                    ->orWhere('account_reference', $subject)
                    ->orWhere('account_reference', 'like', '%'.$subject.'%')
                    ->orWhere('meta->mt5_sync->identifier', $subject)
                    ->orWhere('meta->mt5_pool_entry->login', $subject);
            })
            ->where(function (Builder $query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5')
                    ->orWhere('platform', 'MT5 Demo');
            })
            ->orderByRaw("CASE WHEN challenge_status = 'pending_activation' THEN 0 WHEN account_status = 'pending_activation' THEN 1 WHEN challenge_status = 'active' THEN 2 ELSE 3 END")
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function candidatePoolEntry(TradingAccount $account, array $options): ?Mt5AccountPoolEntry
    {
        $poolLogin = trim((string) ($options['pool_login'] ?? ''));

        if ($poolLogin !== '') {
            return Mt5AccountPoolEntry::query()
                ->where('login', $poolLogin)
                ->latest('allocated_at')
                ->latest('id')
                ->first();
        }

        return $this->availablePoolQuery($options)
            ->where('account_size', (int) $account->account_size)
            ->orderBy('source_created_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function availablePoolQuery(array $options): Builder
    {
        return $this->scopedPoolQuery($options)
            ->where('is_available', true)
            ->where('is_promo', false)
            ->whereNull('allocated_at')
            ->whereNull('allocated_trading_account_id');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function scopedPoolQuery(array $options): Builder
    {
        $query = Mt5AccountPoolEntry::query();
        $server = $this->serverOption($options);
        $sourcePool = trim((string) ($options['source_pool'] ?? config('wolforix.mt5_account_pool.default_pool', Mt5AccountPoolEntry::SOURCE_POOL_CLIENT)));
        $sourceFile = trim((string) ($options['source_file'] ?? config('wolforix.mt5_account_pool.active_source_file')));
        $broker = trim((string) ($options['broker'] ?? config('wolforix.mt5_account_pool.active_broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS)));
        $platform = trim((string) ($options['platform'] ?? config('wolforix.mt5_account_pool.active_platform', Mt5AccountPoolEntry::PLATFORM_MT5)));

        if ($server !== '') {
            $query->whereIn('server', $this->serverAliases($server));
        }

        if ($sourcePool !== '') {
            $query->where('source_pool', $sourcePool);
        }

        if ($sourceFile !== '') {
            $query->where('source_file', basename($sourceFile) ?: $sourceFile);
        }

        if ($broker !== '') {
            $query->where(function (Builder $query) use ($broker): void {
                $query->where('meta->broker', $broker)
                    ->orWhere('meta->provider', $broker);
            });
        }

        if ($platform !== '') {
            $query->where('meta->platform', $platform);
        }

        return $query;
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

    private function syncMetaApiMapping(TradingAccount $account, Mt5AccountPoolEntry $entry): void
    {
        $metaApiAccountId = $this->metaApiAccountId($account, $entry);

        if ($metaApiAccountId === null) {
            return;
        }

        $meta = is_array($account->meta) ? $account->meta : [];
        $changed = false;

        foreach (['metaapi_account_id', 'mt5_sync.metaapi_account_id', 'mt5_pool_entry.metaapi_account_id'] as $path) {
            if (! filled((string) data_get($meta, $path))) {
                data_set($meta, $path, $metaApiAccountId);
                $changed = true;
            }
        }

        if ($changed) {
            $account->forceFill([
                'sync_source' => $account->sync_source ?: 'metaapi',
                'meta' => $meta,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function stampProvisioning(TradingAccount $account, ?Mt5AccountPoolEntry $entry, string $status, array $context = []): void
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $metaApiAccountId = $entry instanceof Mt5AccountPoolEntry ? $this->metaApiAccountId($account, $entry) : null;

        $meta['metaquotes_pool_provisioning'] = array_filter([
            'status' => $status,
            'last_checked_at' => now()->toIso8601String(),
            'assigned_at' => $entry instanceof Mt5AccountPoolEntry
                ? (data_get($meta, 'metaquotes_pool_provisioning.assigned_at') ?: now()->toIso8601String())
                : data_get($meta, 'metaquotes_pool_provisioning.assigned_at'),
            'pool_entry_id' => $entry?->id,
            'login' => $entry?->login ?: $account->platform_login ?: $account->platform_account_id,
            'server' => $entry?->server ?: $account->platform_environment ?: ($context['server'] ?? null),
            'source_pool' => $entry?->source_pool,
            'source_file' => $entry?->source_file,
            'metaapi_account_id_present' => $metaApiAccountId !== null,
            'source' => $context['source'] ?? 'metaquotes_pool_provisioning',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $account->forceFill([
            'sync_source' => $metaApiAccountId !== null ? 'metaapi' : ($account->sync_source ?: 'mt5_pool'),
            'meta' => $meta,
        ])->save();

        Log::info('MetaQuotes pool provisioning state updated.', [
            'trading_account_id' => $account->id,
            'pool_entry_id' => $entry?->id,
            'status' => $status,
            'source' => $context['source'] ?? 'metaquotes_pool_provisioning',
        ]);
    }

    private function metaApiAccountId(TradingAccount $account, Mt5AccountPoolEntry $entry): ?string
    {
        $candidates = [
            data_get($account->meta, 'metaapi_account_id'),
            data_get($account->meta, 'mt5_sync.metaapi_account_id'),
            data_get($account->meta, 'mt5_pool_entry.metaapi_account_id'),
            data_get($entry->meta, 'metaapi_account_id'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mappingWarnings(TradingAccount $account, Mt5AccountPoolEntry $entry): array
    {
        $warnings = [];

        if ((string) $account->platform_login !== '' && (string) $account->platform_login !== (string) $entry->login) {
            $warnings[] = 'trading_account_login_mismatch';
        }

        if ((int) $entry->allocated_trading_account_id !== 0 && (int) $entry->allocated_trading_account_id !== (int) $account->id) {
            $warnings[] = 'pool_entry_allocated_elsewhere';
        }

        if ($this->metaApiAccountId($account, $entry) === null) {
            $warnings[] = $this->assignmentMetaApi->isMetaQuotesPoolEntry($entry)
                ? MetaQuotesAssignmentMetaApiService::STATUS_ASSIGNED_PENDING_METAAPI
                : 'missing_metaapi_mapping';
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function recommendations(array $errors, array $warnings): array
    {
        $recommendations = [];

        if (in_array('pool_exhausted', $errors, true)) {
            $recommendations[] = 'Import additional pre-created MT5 pool accounts for this server/account size before retrying provisioning.';
        }

        if (in_array('account_size_mismatch', $errors, true)) {
            $recommendations[] = 'Use a matching account-size pool row, or retry with --force only after admin review.';
        }

        if (in_array('missing_metaapi_mapping', $warnings, true)) {
            $recommendations[] = 'Account can be assigned, but MetaApi sync needs a UUID before cloud synchronization can start.';
        }

        if (in_array(MetaQuotesAssignmentMetaApiService::STATUS_ASSIGNED_PENDING_METAAPI, $warnings, true)) {
            $recommendations[] = 'MetaQuotes account is assigned; run assignment-time MetaApi registration/deployment before expecting dashboard sync.';
        }

        if (in_array('trading_account_login_mismatch', $warnings, true)) {
            $recommendations[] = 'Review the existing trading-account login before changing any allocation.';
        }

        return array_values(array_unique($recommendations));
    }

    /**
     * @param  list<string>  $blockers
     * @return list<string>
     */
    private function diagnosticRecommendations(array $blockers): array
    {
        $recommendations = [];

        if (in_array('pool_exhausted', $blockers, true)) {
            $recommendations[] = 'Import more available pre-created MT5 accounts for the scoped server/source.';
        }

        if (in_array('stale_allocations', $blockers, true)) {
            $recommendations[] = 'Review allocated pool rows whose trading accounts no longer exist before reusing them.';
        }

        if (in_array('missing_metaapi_mapping', $blockers, true)) {
            $recommendations[] = 'Provision/import MetaApi UUIDs for allocated rows that should cloud sync.';
        }

        if (in_array('duplicate_login_risk', $blockers, true)) {
            $recommendations[] = 'Review duplicate logins before bulk assignment to avoid accidental reuse.';
        }

        return $recommendations;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, array{login:string,count:int}>
     */
    private function duplicateLoginRows(array $options): Collection
    {
        return (clone $this->scopedPoolQuery($options))
            ->selectRaw('login, count(*) as aggregate_count')
            ->groupBy('login')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('aggregate_count')
            ->get()
            ->map(fn (Mt5AccountPoolEntry $row): array => [
                'login' => (string) $row->login,
                'count' => (int) $row->aggregate_count,
            ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, array<string, mixed>>
     */
    private function missingMetaApiMappingRows(array $options): Collection
    {
        return (clone $this->scopedPoolQuery($options))
            ->with('allocatedTradingAccount')
            ->whereNotNull('allocated_trading_account_id')
            ->where(function (Builder $query): void {
                $query->whereNull('meta->metaapi_account_id')
                    ->orWhere('meta->metaapi_account_id', '');
            })
            ->orderBy('allocated_at')
            ->orderBy('id')
            ->get()
            ->map(function (Mt5AccountPoolEntry $entry): array {
                $account = $entry->allocatedTradingAccount;
                $accountMetaApiId = $account instanceof TradingAccount
                    ? $this->firstMetaApiAccountId([
                        data_get($account->meta, 'metaapi_account_id'),
                        data_get($account->meta, 'mt5_sync.metaapi_account_id'),
                        data_get($account->meta, 'mt5_pool_entry.metaapi_account_id'),
                    ])
                    : null;

                return [
                    'pool_entry_id' => $entry->id,
                    'login' => $entry->login,
                    'server' => $entry->server,
                    'source_pool' => $entry->source_pool,
                    'source_file' => $entry->source_file,
                    'allocated_trading_account_id' => $entry->allocated_trading_account_id,
                    'account_reference' => $account?->account_reference,
                    'account_status' => $account?->account_status,
                    'challenge_status' => $account?->challenge_status,
                    'pool_metaapi_uuid_state' => 'missing',
                    'account_metaapi_uuid_state' => $accountMetaApiId !== null ? 'present' : 'missing',
                    'expected_metaapi_uuid_state' => $accountMetaApiId !== null ? 'copy_from_trading_account' : 'lookup_required',
                ];
            });
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstMetaApiAccountId(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function serverOption(array $options): string
    {
        return trim((string) ($options['server'] ?? config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo')));
    }

    /**
     * @return list<string>
     */
    private function serverAliases(string $server): array
    {
        $server = trim($server);

        if ($server === '') {
            return [];
        }

        $aliases = [$server];
        $lower = strtolower($server);

        if (str_contains($lower, 'fusionmarkets') || str_contains($lower, 'fusion markets')) {
            $aliases[] = (string) config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo');
            $aliases[] = 'Fusion Markets Pty - FusionMarkets Demo';
        }

        return array_values(array_unique($aliases));
    }

    private function accountSummary(?TradingAccount $account): ?array
    {
        if (! $account instanceof TradingAccount) {
            return null;
        }

        return [
            'id' => $account->id,
            'user_id' => $account->user_id,
            'account_reference' => $account->account_reference,
            'platform_login' => $account->platform_login,
            'platform_account_id' => $account->platform_account_id,
            'platform_environment' => $account->platform_environment,
            'account_size' => (int) $account->account_size,
            'sync_source' => $account->sync_source,
            'onboarding_state' => data_get($account->meta, 'metaapi_onboarding.state'),
            'provisioning_status' => data_get($account->meta, 'metaquotes_pool_provisioning.status'),
            'metaapi_workflow_status' => data_get($account->meta, 'metaapi_workflow.status'),
            'metaapi_account_id' => data_get($account->meta, 'metaapi_account_id'),
        ];
    }

    private function poolSummary(?Mt5AccountPoolEntry $entry): ?array
    {
        if (! $entry instanceof Mt5AccountPoolEntry) {
            return null;
        }

        return [
            'id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
            'account_size' => (int) $entry->account_size,
            'source_pool' => $entry->source_pool,
            'source_file' => $entry->source_file,
            'allocated_trading_account_id' => $entry->allocated_trading_account_id,
            'allocated_user_id' => $entry->allocated_user_id,
            'is_available' => (bool) $entry->is_available,
            'metaapi_account_id_present' => filled((string) data_get($entry->meta, 'metaapi_account_id')),
            'metaapi_workflow_status' => data_get($entry->meta, 'metaapi_workflow.status'),
        ];
    }
}
