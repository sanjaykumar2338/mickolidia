<?php

namespace App\Services\MetaApi;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\Mt5\Mt5AccountAllocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MetaApiPoolAssignmentService
{
    public function __construct(
        private readonly Mt5AccountAllocator $allocator,
        private readonly MetaApiOnboardingService $onboardingService,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function assign(string $identifier, array $options = []): array
    {
        $identifier = trim($identifier);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $warnings = [];
        $errors = [];

        $account = $this->resolveTradingAccount($identifier, (string) ($options['account'] ?? ''));
        $poolEntry = $this->resolvePoolEntry($identifier, $account, $options);

        if (! $account instanceof TradingAccount) {
            $errors[] = 'trading_account_missing';
        }

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $errors[] = 'pool_exhausted';
        }

        if ($account instanceof TradingAccount && $poolEntry instanceof Mt5AccountPoolEntry) {
            $existingAccountPool = $this->poolEntryForAccount($account);

            if ($existingAccountPool instanceof Mt5AccountPoolEntry && (int) $existingAccountPool->id !== (int) $poolEntry->id) {
                $errors[] = 'trading_account_already_has_pool_assignment';
            }

            if ($poolEntry->allocated_trading_account_id !== null && (int) $poolEntry->allocated_trading_account_id !== (int) $account->id) {
                $errors[] = 'pool_entry_allocated_elsewhere';
            }

            if ((int) $poolEntry->account_size !== (int) $account->account_size && ! $force) {
                $errors[] = 'account_size_mismatch';
            }

            if ($this->canonicalServer($poolEntry->server) !== (string) $poolEntry->server) {
                $warnings[] = 'canonical_server_normalization_available';
            }
        }

        $status = $errors === []
            ? ($dryRun ? 'ready' : 'ready_to_assign')
            : 'blocked';

        $result = [
            'status' => $status,
            'identifier' => $identifier,
            'dry_run' => $dryRun,
            'force' => $force,
            'trading_account' => $this->accountSummary($account),
            'pool_entry' => $this->poolSummary($poolEntry),
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'recommendations' => $this->recommendations($identifier, $errors, $account),
        ];

        if ($errors !== [] || $dryRun) {
            return $result;
        }

        if (! $account instanceof TradingAccount || ! $poolEntry instanceof Mt5AccountPoolEntry) {
            throw new RuntimeException('assignment_resolution_failed');
        }

        $assigned = DB::transaction(function () use ($account, $poolEntry, $force): Mt5AccountPoolEntry {
            /** @var TradingAccount $lockedAccount */
            $lockedAccount = TradingAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            /** @var Mt5AccountPoolEntry $lockedPoolEntry */
            $lockedPoolEntry = Mt5AccountPoolEntry::query()
                ->lockForUpdate()
                ->findOrFail($poolEntry->id);

            $this->normalizePoolServerSafely($lockedPoolEntry);

            $assignedEntry = $this->allocator->allocateSpecific($lockedAccount, $lockedPoolEntry, [
                'force_account_size' => $force,
            ]);

            $this->onboardingService->markAssigned($lockedAccount->fresh() ?? $lockedAccount, $assignedEntry, [
                'source' => 'phase_2_assignment_command',
            ]);

            return $assignedEntry->fresh() ?? $assignedEntry;
        });

        $account = $account->fresh() ?? $account;

        return array_merge($result, [
            'status' => 'assigned',
            'trading_account' => $this->accountSummary($account),
            'pool_entry' => $this->poolSummary($assigned),
            'changes' => [
                'pool_entry_assigned',
                'trading_account_hydrated',
                'onboarding_marked_assigned',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function diagnose(?string $identifier = null, array $options = []): array
    {
        $identifier = trim((string) $identifier);
        $available = $this->availablePoolQuery($options)->count();
        $allocated = Mt5AccountPoolEntry::query()
            ->whereNotNull('allocated_trading_account_id')
            ->count();
        $metaApiUnassigned = Mt5AccountPoolEntry::query()
            ->whereNotNull('meta->metaapi_account_id')
            ->whereNull('allocated_trading_account_id')
            ->count();

        $diagnostic = [
            'pool' => [
                'available' => $available,
                'allocated' => $allocated,
                'unassigned_metaapi_rows' => $metaApiUnassigned,
                'exhausted' => $available === 0,
            ],
            'recommendations' => $available === 0
                ? ['Import more MT5 pool accounts or release a verified unused pool row before auto assignment.']
                : ['Pool assignment is available. Use wolforix:assign-pool-account {login_or_reference}.'],
        ];

        if ($identifier !== '') {
            $assignment = $this->assign($identifier, array_merge($options, [
                'dry_run' => true,
            ]));

            $diagnostic['assignment'] = $assignment;
        }

        return $diagnostic;
    }

    private function resolveTradingAccount(string $identifier, string $accountOverride = ''): ?TradingAccount
    {
        $search = trim($accountOverride) !== '' ? trim($accountOverride) : $identifier;

        if ($search === '') {
            return null;
        }

        if (ctype_digit($search)) {
            $byId = TradingAccount::query()->find((int) $search);

            if ($byId instanceof TradingAccount) {
                return $byId;
            }
        }

        $query = TradingAccount::query()
            ->where(function (Builder $query) use ($search): void {
                $query->where('platform_login', $search)
                    ->orWhere('platform_account_id', $search)
                    ->orWhere('account_reference', $search)
                    ->orWhere('account_reference', 'like', '%'.$search.'%')
                    ->orWhere('meta->mt5_sync->identifier', $search)
                    ->orWhere('meta->mt5_sync->platform_login', $search)
                    ->orWhere('meta->mt5_sync->platform_account_id', $search)
                    ->orWhere('meta->mt5_pool_entry->login', $search);
            })
            ->where(function (Builder $query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5')
                    ->orWhere('platform', 'MT5 Demo');
            })
            ->orderByRaw("CASE WHEN challenge_status = 'pending_activation' THEN 0 WHEN account_status = 'pending_activation' THEN 1 WHEN challenge_status = 'active' THEN 2 ELSE 3 END")
            ->latest('id');

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolvePoolEntry(string $identifier, ?TradingAccount $account, array $options): ?Mt5AccountPoolEntry
    {
        $poolLogin = trim((string) ($options['pool_login'] ?? ''));

        if ($poolLogin !== '') {
            return Mt5AccountPoolEntry::query()
                ->where('login', $poolLogin)
                ->latest('allocated_at')
                ->latest('id')
                ->first();
        }

        if ($account instanceof TradingAccount) {
            $existing = $this->poolEntryForAccount($account);

            if ($existing instanceof Mt5AccountPoolEntry) {
                return $existing;
            }
        }

        $login = $account instanceof TradingAccount
            ? (string) ($account->platform_login ?: $account->platform_account_id)
            : $identifier;

        if ($login !== '') {
            $byLogin = Mt5AccountPoolEntry::query()
                ->where('login', $login)
                ->where(function (Builder $query) use ($account): void {
                    $query->whereNull('allocated_trading_account_id');

                    if ($account instanceof TradingAccount) {
                        $query->orWhere('allocated_trading_account_id', $account->id);
                    }
                })
                ->latest('allocated_at')
                ->latest('id')
                ->first();

            if ($byLogin instanceof Mt5AccountPoolEntry) {
                return $byLogin;
            }
        }

        return $this->availablePoolQuery($options, $account)
            ->orderBy('source_created_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function availablePoolQuery(array $options = [], ?TradingAccount $account = null): Builder
    {
        $query = Mt5AccountPoolEntry::query()
            ->where('is_available', true)
            ->where('is_promo', false)
            ->whereNull('allocated_at')
            ->whereNull('allocated_trading_account_id');

        $sourcePool = trim((string) ($options['source_pool'] ?? ''));
        $sourceFile = trim((string) ($options['source_file'] ?? ''));
        $server = trim((string) ($options['server'] ?? ''));

        if ($sourcePool !== '') {
            $query->where('source_pool', $sourcePool);
        }

        if ($sourceFile !== '') {
            $query->where('source_file', basename($sourceFile) ?: $sourceFile);
        }

        if ($server !== '') {
            $query->whereIn('server', array_values(array_unique([$server, $this->canonicalServer($server)])));
        }

        if ($account instanceof TradingAccount) {
            $query->where('account_size', (int) $account->account_size);
        }

        return $query;
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
            ->where('allocated_trading_account_id', $account->id)
            ->latest('allocated_at')
            ->latest('id')
            ->first();
    }

    private function normalizePoolServerSafely(Mt5AccountPoolEntry $entry): void
    {
        $canonical = $this->canonicalServer($entry->server);

        if ($canonical === (string) $entry->server) {
            return;
        }

        $duplicate = Mt5AccountPoolEntry::query()
            ->where('login', $entry->login)
            ->where('server', $canonical)
            ->whereKeyNot($entry->id)
            ->first();

        if ($duplicate instanceof Mt5AccountPoolEntry) {
            return;
        }

        $entry->forceFill(['server' => $canonical])->save();
    }

    private function canonicalServer(?string $server): string
    {
        $server = trim((string) $server);

        if ($server === '') {
            return (string) config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo');
        }

        if (str_contains(strtolower($server), 'fusionmarkets') || str_contains(strtolower($server), 'fusion markets')) {
            return (string) config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo');
        }

        return $server;
    }

    private function accountSummary(?TradingAccount $account): ?array
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
            'account_size' => (int) $account->account_size,
            'account_status' => $account->account_status,
            'challenge_status' => $account->challenge_status,
            'onboarding_state' => data_get($account->meta, 'metaapi_onboarding.state'),
        ];
    }

    private function poolSummary(?Mt5AccountPoolEntry $poolEntry): ?array
    {
        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            return null;
        }

        return [
            'id' => $poolEntry->id,
            'login' => $poolEntry->login,
            'server' => $poolEntry->server,
            'account_size' => (int) $poolEntry->account_size,
            'allocated_trading_account_id' => $poolEntry->allocated_trading_account_id,
            'allocated_user_id' => $poolEntry->allocated_user_id,
            'is_available' => (bool) $poolEntry->is_available,
            'source_status' => $poolEntry->source_status,
            'metaapi_account_id' => data_get($poolEntry->meta, 'metaapi_account_id'),
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function recommendations(string $identifier, array $errors, ?TradingAccount $account): array
    {
        $recommendations = [];

        if (in_array('trading_account_missing', $errors, true)) {
            $recommendations[] = "Create or locate the TradingAccount for {$identifier} before assigning a pool row.";
        }

        if (in_array('pool_exhausted', $errors, true)) {
            $recommendations[] = 'Import more MT5 pool accounts or pass --pool-login for a verified unallocated row.';
        }

        if (in_array('trading_account_already_has_pool_assignment', $errors, true) && $account instanceof TradingAccount) {
            $recommendations[] = "Trading account #{$account->id} already has a pool row; diagnose before changing it.";
        }

        if (in_array('pool_entry_allocated_elsewhere', $errors, true)) {
            $recommendations[] = 'The selected pool row belongs to another account. Choose a different pool row or verify the data manually.';
        }

        if (in_array('account_size_mismatch', $errors, true)) {
            $recommendations[] = 'Pool account size differs from the challenge account. Use --force only after admin review.';
        }

        return $recommendations;
    }
}
