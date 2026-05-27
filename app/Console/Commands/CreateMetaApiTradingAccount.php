<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\MetaApi\MetaApiClient;
use App\Services\MetaApi\MetaApiOnboardingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMetaApiTradingAccount extends Command
{
    protected $signature = 'wolforix:create-metaapi-trading-account
        {login : MT5 login/account reference to onboard}
        {--dry-run : Show the account that would be created without writing}
        {--user-id= : Optional user id to attach to the created trading account}';

    protected $description = 'Create a minimal TradingAccount row for an existing MetaApi MT5 pool account without printing secrets.';

    public function handle(MetaApiClient $metaApi, MetaApiOnboardingService $onboardingService): int
    {
        $login = trim((string) $this->argument('login'));
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->userIdOption();

        if ($login === '') {
            $this->error('A non-empty MT5 login is required.');

            return self::FAILURE;
        }

        $poolEntry = $this->poolEntryForLogin($login);
        $existingAccount = $this->existingTradingAccount($login, $poolEntry);
        $metaApiAccountId = $this->resolveMetaApiAccountId($login, $poolEntry, $metaApi);
        $server = $this->canonicalServer($poolEntry?->server);
        $warnings = [];
        $errors = [];

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $errors[] = 'pool_entry_missing';
        }

        if ($existingAccount instanceof TradingAccount) {
            $errors[] = 'trading_account_already_exists';
        }

        if (! $this->looksLikeMetaApiAccountId((string) $metaApiAccountId)) {
            $errors[] = 'metaapi_account_id_missing';
        }

        if ($userId !== null && ! User::query()->whereKey($userId)->exists()) {
            $errors[] = 'user_not_found';
        }

        if ($userId === null && $poolEntry?->allocated_user_id === null) {
            $warnings[] = 'user_id_unassigned';
        }

        $accountReference = $this->nextAccountReference($login);
        $attributes = $poolEntry instanceof Mt5AccountPoolEntry && $this->looksLikeMetaApiAccountId((string) $metaApiAccountId)
            ? $this->accountAttributes($poolEntry, (string) $metaApiAccountId, $server, $accountReference, $userId)
            : [];

        $this->info('MetaApi trading account onboarding');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();

        $this->table(['Field', 'Value'], [
            ['Login', $login],
            ['Mode', $dryRun ? 'dry-run' : 'apply'],
            ['Status', $errors === [] ? ($dryRun ? 'ready' : 'ready_to_create') : 'blocked'],
            ['Pool entry', $poolEntry instanceof Mt5AccountPoolEntry ? (string) $poolEntry->id : '-'],
            ['Pool allocated account', (string) ($poolEntry?->allocated_trading_account_id ?: '-')],
            ['Existing trading account', $existingAccount instanceof TradingAccount ? '#'.$existingAccount->id.' '.(string) $existingAccount->account_reference : '-'],
            ['Planned account reference', (string) ($attributes['account_reference'] ?? '-')],
            ['Planned user id', (string) ($attributes['user_id'] ?? '-')],
            ['MetaApi account', (string) ($metaApiAccountId ?: '-')],
            ['Server', $server ?: '-'],
        ]);

        $this->printList('Warnings', $warnings);
        $this->printList('Errors', $errors);

        if ($errors !== []) {
            $this->printList('Admin action', $this->adminActions($login, $errors, $existingAccount));

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->printPlan($attributes);
            $this->warn('DRY RUN ONLY - no database changes were made.');

            return self::SUCCESS;
        }

        $account = DB::transaction(function () use ($login, $poolEntry, $attributes, $onboardingService): TradingAccount {
            /** @var Mt5AccountPoolEntry $lockedPoolEntry */
            $lockedPoolEntry = Mt5AccountPoolEntry::query()
                ->lockForUpdate()
                ->whereKey($poolEntry->id)
                ->firstOrFail();

            $existing = $this->existingTradingAccount($login, $lockedPoolEntry);

            if ($existing instanceof TradingAccount) {
                return $existing;
            }

            /** @var TradingAccount $account */
            $account = TradingAccount::query()->create($attributes);

            $poolMeta = is_array($lockedPoolEntry->meta) ? $lockedPoolEntry->meta : [];
            $poolMeta['metaapi_onboarded_trading_account_id'] = $account->id;
            $poolMeta['metaapi_onboarded_at'] = now()->toIso8601String();

            $lockedPoolEntry->forceFill([
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'allocated_at' => $lockedPoolEntry->allocated_at ?? now(),
                'is_available' => false,
                'source_status' => 'assigned',
                'meta' => $poolMeta,
            ])->save();

            $account = $onboardingService->initialize($account, [
                'source' => 'metaapi_trading_account_onboarding_helper',
            ]);

            return $onboardingService->markAssigned($account, $lockedPoolEntry, [
                'source' => 'metaapi_trading_account_onboarding_helper',
            ]);
        });

        $this->newLine();
        $this->info('Trading account created and pool entry bound.');
        $this->table(['Field', 'Value'], [
            ['Trading account', '#'.$account->id],
            ['Account reference', (string) $account->account_reference],
            ['Platform login', (string) $account->platform_login],
            ['MetaApi account', (string) data_get($account->meta, 'metaapi_account_id')],
            ['Pool entry', (string) data_get($account->meta, 'mt5_pool_entry.id')],
        ]);

        return self::SUCCESS;
    }

    private function userIdOption(): ?int
    {
        $value = trim((string) $this->option('user-id'));

        return $value === '' ? null : (int) $value;
    }

    private function poolEntryForLogin(string $login): ?Mt5AccountPoolEntry
    {
        return Mt5AccountPoolEntry::query()
            ->where('login', $login)
            ->latest('allocated_at')
            ->latest('id')
            ->first();
    }

    private function existingTradingAccount(string $login, ?Mt5AccountPoolEntry $poolEntry): ?TradingAccount
    {
        if ($poolEntry?->allocated_trading_account_id !== null) {
            $account = $poolEntry->allocatedTradingAccount;

            if ($account instanceof TradingAccount) {
                return $account;
            }
        }

        $metaApiAccountId = (string) data_get($poolEntry?->meta, 'metaapi_account_id');

        return TradingAccount::query()
            ->where(function (Builder $query) use ($login, $metaApiAccountId): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login)
                    ->orWhere('account_reference', 'like', '%'.$login.'%')
                    ->orWhere('meta->mt5_sync->identifier', $login)
                    ->orWhere('meta->mt5_pool_entry->login', $login);

                if ($this->looksLikeMetaApiAccountId($metaApiAccountId)) {
                    $query->orWhere('meta->metaapi_account_id', $metaApiAccountId)
                        ->orWhere('meta->mt5_sync->metaapi_account_id', $metaApiAccountId)
                        ->orWhere('meta->mt5_pool_entry->metaapi_account_id', $metaApiAccountId);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function resolveMetaApiAccountId(string $login, ?Mt5AccountPoolEntry $poolEntry, MetaApiClient $metaApi): ?string
    {
        $poolAccountId = (string) data_get($poolEntry?->meta, 'metaapi_account_id');

        if ($this->looksLikeMetaApiAccountId($poolAccountId)) {
            return $poolAccountId;
        }

        if (! $metaApi->isConfigured()) {
            return null;
        }

        $result = $metaApi->readAccounts($login);

        if (! (bool) ($result['ok'] ?? false)) {
            return null;
        }

        $matches = collect($this->rowsFromPayload($result['payload'] ?? []))
            ->filter(fn (array $row): bool => (string) data_get($row, 'login') === $login)
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        $accountId = (string) (data_get($matches->first(), '_id') ?: data_get($matches->first(), 'id'));

        return $this->looksLikeMetaApiAccountId($accountId) ? $accountId : null;
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

    private function nextAccountReference(string $login): string
    {
        $base = 'WFX-MT5-'.$login;

        if (! TradingAccount::query()->where('account_reference', $base)->exists()) {
            return $base;
        }

        do {
            $reference = $base.'-'.Str::upper(Str::random(4));
        } while (TradingAccount::query()->where('account_reference', $reference)->exists());

        return $reference;
    }

    /**
     * @return array<string, mixed>
     */
    private function accountAttributes(
        Mt5AccountPoolEntry $poolEntry,
        string $metaApiAccountId,
        string $server,
        string $accountReference,
        ?int $userId,
    ): array {
        $login = (string) $poolEntry->login;
        $accountSize = (int) $poolEntry->account_size;
        $resolvedUserId = $userId ?? $poolEntry->allocated_user_id;
        $meta = [
            'source' => 'metaapi-onboarding-helper',
            'metaapi_account_id' => $metaApiAccountId,
            'mt5_server' => $server,
            'broker' => data_get($poolEntry->meta, 'broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS),
            'provider' => data_get($poolEntry->meta, 'provider', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS),
            'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            'mt5_sync' => [
                'identifier' => $login,
                'account_reference' => $accountReference,
                'server' => $server,
                'broker' => data_get($poolEntry->meta, 'broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS),
                'status' => 'pending_activation',
                'metaapi_account_id' => $metaApiAccountId,
            ],
            'mt5_pool_entry' => [
                'id' => $poolEntry->id,
                'login' => $login,
                'source_pool' => $poolEntry->source_pool,
                'source_file' => $poolEntry->source_file,
                'source_batch' => $poolEntry->source_batch,
                'source_status' => $poolEntry->source_status,
                'broker' => data_get($poolEntry->meta, 'broker'),
                'platform' => data_get($poolEntry->meta, 'platform'),
                'metaapi_account_id' => $metaApiAccountId,
                'source_created_at' => optional($poolEntry->source_created_at)->toDateString(),
            ],
        ];

        return [
            'user_id' => $resolvedUserId,
            'account_size' => $accountSize,
            'account_reference' => $accountReference,
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $login,
            'platform_account_id' => $login,
            'platform_environment' => $server,
            'platform_status' => 'waiting_for_first_sync',
            'stage' => 'Challenge Step 1',
            'status' => 'Pending Activation',
            'account_type' => 'challenge',
            'account_phase' => 'challenge',
            'phase_index' => 1,
            'account_status' => 'pending_activation',
            'challenge_status' => 'pending_activation',
            'is_trial' => false,
            'is_funded' => false,
            'starting_balance' => $accountSize,
            'phase_starting_balance' => $accountSize,
            'phase_reference_balance' => $accountSize,
            'balance' => $accountSize,
            'equity' => $accountSize,
            'highest_equity_today' => $accountSize,
            'daily_drawdown' => 0,
            'daily_loss_used' => 0,
            'max_drawdown' => 0,
            'max_drawdown_used' => 0,
            'profit_loss' => 0,
            'total_profit' => 0,
            'today_profit' => 0,
            'drawdown_percent' => 0,
            'sync_status' => 'pending',
            'sync_source' => 'metaapi',
            'meta' => $meta,
        ];
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function printPlan(array $attributes): void
    {
        $this->newLine();
        $this->info('Planned TradingAccount');
        $this->table(['Field', 'Value'], [
            ['account_reference', (string) ($attributes['account_reference'] ?? '-')],
            ['user_id', (string) ($attributes['user_id'] ?? '-')],
            ['platform_login', (string) ($attributes['platform_login'] ?? '-')],
            ['platform_account_id', (string) ($attributes['platform_account_id'] ?? '-')],
            ['platform_environment', (string) ($attributes['platform_environment'] ?? '-')],
            ['account_status', (string) ($attributes['account_status'] ?? '-')],
            ['challenge_status', (string) ($attributes['challenge_status'] ?? '-')],
            ['sync_source', (string) ($attributes['sync_source'] ?? '-')],
            ['meta.metaapi_account_id', (string) data_get($attributes, 'meta.metaapi_account_id', '-')],
            ['meta.mt5_pool_entry.id', (string) data_get($attributes, 'meta.mt5_pool_entry.id', '-')],
        ]);
    }

    /**
     * @param  list<string>  $items
     */
    private function printList(string $label, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->info($label);

        foreach (array_values(array_unique($items)) as $item) {
            $this->line('- '.$item);
        }
    }

    /**
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function adminActions(string $login, array $errors, ?TradingAccount $existingAccount): array
    {
        $actions = [];

        if (in_array('pool_entry_missing', $errors, true)) {
            $actions[] = 'Create/import an MT5 pool row for login '.$login.' first.';
        }

        if (in_array('trading_account_already_exists', $errors, true) && $existingAccount instanceof TradingAccount) {
            $actions[] = 'Existing account found: #'.$existingAccount->id.' '.$existingAccount->account_reference.'. Use repair/sync commands instead of creating another row.';
        }

        if (in_array('metaapi_account_id_missing', $errors, true)) {
            $actions[] = 'Store a valid MetaApi UUID in pool meta.metaapi_account_id or configure MetaApi lookup and retry.';
        }

        if (in_array('user_not_found', $errors, true)) {
            $actions[] = 'Pass an existing --user-id value or omit --user-id to create the row without dashboard ownership.';
        }

        return $actions;
    }

    private function looksLikeMetaApiAccountId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id));
    }
}
