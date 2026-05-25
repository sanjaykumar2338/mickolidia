<?php

namespace App\Services\MetaApi;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\Mt5\Mt5AccountAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MetaQuotesDemoValidationService
{
    public function __construct(
        private readonly MetaApiClient $metaApi,
        private readonly Mt5AccountAllocator $allocator,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $startedAt = now();
        $live = (bool) ($options['live'] ?? false);
        $count = max(1, (int) ($options['count'] ?? 1));
        $sourceFile = $this->sourceFile((string) ($options['source_file'] ?? config('wolforix.mt5_account_pool.metaquotes.source', 'metaapi-demo-validation')));
        $server = $this->stringOption($options, 'server', (string) config('wolforix.mt5_account_pool.metaquotes.server', 'MetaQuotes-Demo'));
        $broker = (string) config('wolforix.mt5_account_pool.metaquotes.broker', 'MetaQuotes');
        $platform = (string) config('wolforix.mt5_account_pool.metaquotes.platform', Mt5AccountPoolEntry::PLATFORM_MT5);
        $pool = $this->stringOption($options, 'pool', Mt5AccountPoolEntry::SOURCE_POOL_CLIENT);
        $batch = 'metaquotes-phase1a-'.$startedAt->format('YmdHis');
        $maxWithoutForce = max(1, (int) config('services.metaapi.validation.max_without_force', 2));

        if ($count > $maxWithoutForce && ! (bool) ($options['force_many'] ?? false)) {
            throw new RuntimeException("Refusing to validate {$count} accounts without --force-many. Start with {$maxWithoutForce} or fewer to avoid broker-side demo limits.");
        }

        $report = [
            'title' => 'Phase 1A MetaQuotes Demo Validation',
            'started_at' => $startedAt->toIso8601String(),
            'completed_at' => null,
            'mode' => $live ? 'live' : 'dry-run',
            'config' => [
                'metaapi_token_present' => $this->metaApi->isConfigured(),
                'provisioning_base_url' => config('services.metaapi.provisioning_base_url'),
                'client_base_url' => config('services.metaapi.client_base_url'),
                'profile_id' => config('services.metaapi.profile_id', 'default'),
                'broker' => $broker,
                'server' => $server,
                'platform' => $platform,
                'source_file' => $sourceFile,
                'pool' => $pool,
            ],
            'architecture_validation' => $this->architectureValidation($sourceFile, $broker, $platform, $pool),
            'scalability_assumptions' => $this->scalabilityAssumptions((int) ($options['polls'] ?? config('services.metaapi.validation.polls', 2))),
            'demo_creation' => [
                'requested' => (bool) ($options['create_demo'] ?? false),
                'count' => $count,
                'status' => 'not_run',
                'attempts' => [],
            ],
            'accounts' => [],
            'pool' => [
                'store_requested' => (bool) ($options['store_pool'] ?? false),
                'stored' => [],
                'assignment' => null,
            ],
            'stability' => [
                'account_information' => 'not_tested',
                'positions' => 'not_tested',
                'trade_history' => 'not_tested',
                'reconnect_recovery' => 'not_tested',
                'summary' => 'Dry-run only; run with --live and 1-2 accounts for vendor validation.',
            ],
            'notes' => [
                'MetaApi demo creation is broker-dependent; MetaApi documents that not all brokers support terminal-style demo creation and brokers can rate-limit or limit accounts per email.',
                'This harness uses REST polling for validation only. For 20-50 always-on accounts, use MetaApi streaming/SDK or a sparse poller for history to avoid wasteful RPC usage.',
                'Credentials are not written to diagnostic reports; passwords are only stored in encrypted model casts when --store-pool is used.',
            ],
        ];

        if (! $live) {
            return $this->finish($report);
        }

        if (! $this->metaApi->isConfigured()) {
            throw new RuntimeException('METAAPI_TOKEN is required for --live validation.');
        }

        $credentials = (bool) ($options['create_demo'] ?? false)
            ? $this->createDemoCredentials($options, $count, $server, $report)
            : $this->credentialsFromOptions($options, $server);

        if ($credentials === []) {
            $report['stability']['summary'] = 'No credentials were available for MetaApi registration.';

            return $this->finish($report);
        }

        foreach ($credentials as $credential) {
            $accountReport = $this->validateCredential(
                credential: $credential,
                options: $options,
                sourceFile: $sourceFile,
                broker: $broker,
                platform: $platform,
                pool: $pool,
                batch: $batch,
            );

            $report['accounts'][] = $accountReport;

            if (isset($accountReport['pool_entry'])) {
                $report['pool']['stored'][] = $accountReport['pool_entry'];
            }
        }

        if (filled($options['assign_account'] ?? null)) {
            $report['pool']['assignment'] = $this->assignStoredPoolAccount(
                tradingAccountId: (int) $options['assign_account'],
                sourceFile: $sourceFile,
                broker: $broker,
                platform: $platform,
                pool: $pool,
            );
        }

        $report['stability'] = $this->stabilitySummary($report['accounts']);

        return $this->finish($report);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $report
     * @return list<array<string, mixed>>
     */
    private function createDemoCredentials(array $options, int $count, string $server, array &$report): array
    {
        $required = [
            'email' => $this->stringOption($options, 'email', (string) config('services.metaapi.demo.email', '')),
            'name' => $this->stringOption($options, 'name', (string) config('services.metaapi.demo.name', 'Wolforix Phase 1A')),
            'phone' => $this->stringOption($options, 'phone', (string) config('services.metaapi.demo.phone', '')),
            'account_type' => $this->stringOption($options, 'account_type', (string) config('services.metaapi.demo.account_type', '')),
        ];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new RuntimeException('METAAPI_DEMO_'.Str::upper($field).' or --'.str_replace('_', '-', $field).' is required for --create-demo.');
            }
        }

        $created = [];
        $report['demo_creation']['status'] = 'started';

        for ($index = 1; $index <= $count; $index++) {
            $email = $this->emailForAttempt($required['email'], $index, $count);
            $payload = [
                'balance' => (float) ($options['balance'] ?? config('services.metaapi.demo.balance', 10000)),
                'email' => $email,
                'leverage' => (int) ($options['leverage'] ?? config('services.metaapi.demo.leverage', 100)),
                'serverName' => $server,
                'name' => $required['name'],
                'accountType' => $required['account_type'],
                'phone' => $required['phone'],
                'keywords' => $this->keywords($options),
            ];

            $transactionId = $this->metaApi->transactionId();
            $attempt = $this->createDemoWithRetries($payload, $transactionId);
            $report['demo_creation']['attempts'][] = [
                'email' => $email,
                'transaction_id' => $transactionId,
                'responses' => $this->summarizeResponses($attempt['responses']),
                'credential_received' => $attempt['credential'] !== null,
            ];

            if ($attempt['credential'] !== null) {
                $created[] = $attempt['credential'];
            }

            $this->throttle();
        }

        $report['demo_creation']['status'] = count($created) === $count ? 'passed' : (count($created) > 0 ? 'partial' : 'failed');

        return $created;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{credential: array<string, mixed>|null, responses: list<array<string, mixed>>}
     */
    private function createDemoWithRetries(array $payload, string $transactionId): array
    {
        $responses = [];
        $maxAttempts = max(1, 1 + (int) config('services.metaapi.demo.accepted_retries', 3));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->metaApi->createMt5DemoAccount(
                profileId: (string) config('services.metaapi.profile_id', 'default'),
                payload: $payload,
                transactionId: $transactionId,
            );
            $responses[] = $result;

            if ((int) $result['status'] === 201 && filled(data_get($result, 'payload.login'))) {
                return [
                    'credential' => $this->credentialFromMetaApiPayload((array) $result['payload']),
                    'responses' => $responses,
                ];
            }

            if ((int) $result['status'] !== 202) {
                break;
            }

            $this->sleep((int) config('services.metaapi.demo.accepted_retry_delay_seconds', 30));
        }

        return [
            'credential' => null,
            'responses' => $responses,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    private function credentialsFromOptions(array $options, string $server): array
    {
        $logins = array_values(array_filter((array) ($options['login'] ?? []), fn ($value): bool => trim((string) $value) !== ''));
        $passwords = array_values((array) ($options['password'] ?? []));
        $investorPasswords = array_values((array) ($options['investor_password'] ?? []));

        if ($logins === []) {
            return [];
        }

        $credentials = [];

        foreach ($logins as $index => $login) {
            $password = trim((string) ($passwords[$index] ?? $passwords[0] ?? ''));

            if ($password === '') {
                throw new RuntimeException('Every --login requires a matching --password, or one shared --password.');
            }

            $credentials[] = [
                'login' => trim((string) $login),
                'password' => $password,
                'investor_password' => trim((string) ($investorPasswords[$index] ?? $investorPasswords[0] ?? '')) ?: null,
                'server' => $server,
                'source' => 'existing_credentials',
                'balance' => (float) config('services.metaapi.demo.balance', 10000),
            ];
        }

        return $credentials;
    }

    /**
     * @param  array<string, mixed>  $credential
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function validateCredential(array $credential, array $options, string $sourceFile, string $broker, string $platform, string $pool, string $batch): array
    {
        $accountReport = [
            'login' => (string) $credential['login'],
            'server' => (string) $credential['server'],
            'password_present' => filled($credential['password'] ?? null),
            'investor_password_present' => filled($credential['investor_password'] ?? null),
            'metaapi_account_id' => null,
            'create_account' => null,
            'deploy' => null,
            'polls' => [],
        ];

        if ((bool) ($options['store_pool'] ?? false)) {
            $accountReport['pool_entry'] = $this->storePoolEntry($credential, $sourceFile, $broker, $platform, $pool, $batch);
        }

        $createResult = $this->metaApi->createAccount([
            'login' => (string) $credential['login'],
            'password' => (string) $credential['password'],
            'name' => 'Wolforix Phase 1A '.$credential['login'],
            'server' => (string) $credential['server'],
            'platform' => 'mt5',
            'magic' => 20260525,
            'keywords' => $this->keywords($options),
        ], $this->metaApi->transactionId());

        $accountReport['create_account'] = $this->summarizeResponse($createResult);
        $metaApiAccountId = (string) data_get($createResult, 'payload.id', '');

        if ($metaApiAccountId === '') {
            return $accountReport;
        }

        $accountReport['metaapi_account_id'] = $metaApiAccountId;
        $this->stampMetaApiAccountId($credential, $sourceFile, $metaApiAccountId);

        $deployResult = $this->metaApi->deployAccount($metaApiAccountId);
        $accountReport['deploy'] = $this->summarizeResponse($deployResult);

        $polls = max(1, (int) ($options['polls'] ?? config('services.metaapi.validation.polls', 2)));

        for ($poll = 1; $poll <= $polls; $poll++) {
            $accountReport['polls'][] = $this->pollTerminalState(
                accountId: $metaApiAccountId,
                poll: $poll,
                historyDays: max(1, (int) ($options['history_days'] ?? config('services.metaapi.validation.history_days', 7))),
            );

            if ($poll < $polls) {
                $this->sleep((int) ($options['poll_delay'] ?? config('services.metaapi.validation.poll_delay_seconds', 15)));
            }
        }

        Log::info('MetaQuotes demo validation account completed.', [
            'login' => $credential['login'],
            'server' => $credential['server'],
            'metaapi_account_id' => $metaApiAccountId,
            'polls' => count($accountReport['polls']),
        ]);

        return $accountReport;
    }

    /**
     * @return array<string, mixed>
     */
    private function pollTerminalState(string $accountId, int $poll, int $historyDays): array
    {
        $end = now()->utc();
        $start = $end->copy()->subDays($historyDays);
        $accountInformation = $this->metaApi->readAccountInformation($accountId, $poll === 1);
        $positions = $this->metaApi->readPositions($accountId);
        $historyOrders = $this->metaApi->readHistoryOrdersByTimeRange($accountId, $start, $end, 100);
        $historyDeals = $this->metaApi->readDealsByTimeRange($accountId, $start, $end, 100);

        return [
            'poll' => $poll,
            'polled_at' => now()->toIso8601String(),
            'account_information' => [
                'status' => $accountInformation['status'],
                'ok' => $accountInformation['ok'],
                'balance' => data_get($accountInformation, 'payload.balance'),
                'equity' => data_get($accountInformation, 'payload.equity'),
                'login' => data_get($accountInformation, 'payload.login'),
                'trade_allowed' => data_get($accountInformation, 'payload.tradeAllowed'),
                'error' => $accountInformation['error'],
            ],
            'positions' => [
                'status' => $positions['status'],
                'ok' => $positions['ok'],
                'count' => is_array($positions['payload']) ? count($positions['payload']) : null,
                'error' => $positions['error'],
            ],
            'history_orders' => [
                'status' => $historyOrders['status'],
                'ok' => $historyOrders['ok'],
                'count' => is_array($historyOrders['payload']) ? count($historyOrders['payload']) : null,
                'error' => $historyOrders['error'],
            ],
            'history_deals' => [
                'status' => $historyDeals['status'],
                'ok' => $historyDeals['ok'],
                'count' => is_array($historyDeals['payload']) ? count($historyDeals['payload']) : null,
                'error' => $historyDeals['error'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $credential
     * @return array<string, mixed>
     */
    private function storePoolEntry(array $credential, string $sourceFile, string $broker, string $platform, string $pool, string $batch): array
    {
        /** @var Mt5AccountPoolEntry|null $entry */
        $entry = Mt5AccountPoolEntry::query()
            ->where('login', (string) $credential['login'])
            ->where('server', (string) $credential['server'])
            ->first();

        if ($entry instanceof Mt5AccountPoolEntry && ($entry->allocated_at !== null || $entry->allocated_trading_account_id !== null)) {
            return [
                'id' => $entry->id,
                'login' => $entry->login,
                'status' => 'existing_allocated_not_overwritten',
            ];
        }

        $attributes = [
            'login' => (string) $credential['login'],
            'password' => (string) $credential['password'],
            'investor_password' => filled($credential['investor_password'] ?? null) ? (string) $credential['investor_password'] : null,
            'server' => (string) $credential['server'],
            'account_size' => (int) round((float) ($credential['balance'] ?? config('services.metaapi.demo.balance', 10000))),
            'currency_code' => 'USD',
            'source_status' => 'available',
            'source_file' => $sourceFile,
            'source_batch' => $batch,
            'source_pool' => $pool,
            'source_created_at' => now()->toDateString(),
            'is_promo' => false,
            'is_available' => true,
            'meta' => [
                'broker' => $broker,
                'provider' => $broker,
                'platform' => $platform,
                'source' => 'metaapi_phase1a_validation',
                'created_by' => 'metaquotes:validate-demo',
            ],
        ];

        if ($entry instanceof Mt5AccountPoolEntry) {
            $entry->forceFill($attributes)->save();
            $status = 'updated';
        } else {
            $entry = Mt5AccountPoolEntry::query()->create($attributes);
            $status = 'created';
        }

        return [
            'id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
            'account_size' => (int) $entry->account_size,
            'status' => $status,
        ];
    }

    private function stampMetaApiAccountId(array $credential, string $sourceFile, string $metaApiAccountId): void
    {
        $entry = Mt5AccountPoolEntry::query()
            ->where('login', (string) $credential['login'])
            ->where('server', (string) $credential['server'])
            ->where('source_file', $sourceFile)
            ->first();

        if (! $entry instanceof Mt5AccountPoolEntry) {
            return;
        }

        $meta = is_array($entry->meta) ? $entry->meta : [];
        $meta['metaapi_account_id'] = $metaApiAccountId;
        $meta['metaapi_registered_at'] = now()->toIso8601String();

        $entry->forceFill(['meta' => $meta])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function assignStoredPoolAccount(int $tradingAccountId, string $sourceFile, string $broker, string $platform, string $pool): array
    {
        $account = TradingAccount::query()->find($tradingAccountId);

        if (! $account instanceof TradingAccount) {
            return [
                'status' => 'failed',
                'message' => "Trading account #{$tradingAccountId} was not found.",
            ];
        }

        $entry = DB::transaction(fn (): ?Mt5AccountPoolEntry => $this->allocator->allocate($account, [
            'source_pool' => $pool,
            'source_file' => $sourceFile,
            'broker' => $broker,
            'platform' => $platform,
        ]));

        if (! $entry instanceof Mt5AccountPoolEntry) {
            return [
                'status' => 'not_assigned',
                'message' => 'No matching available pool entry was assignable.',
                'trading_account_id' => $account->id,
            ];
        }

        return [
            'status' => 'assigned',
            'trading_account_id' => $account->id,
            'pool_entry_id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
            'account_status_after_assignment' => $account->fresh()?->account_status,
            'platform_status_after_assignment' => $account->fresh()?->platform_status,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @return array<string, mixed>
     */
    private function stabilitySummary(array $accounts): array
    {
        $polls = collect($accounts)->flatMap(fn (array $account): array => $account['polls'] ?? [])->values();

        if ($polls->isEmpty()) {
            return [
                'account_information' => 'not_tested',
                'positions' => 'not_tested',
                'trade_history' => 'not_tested',
                'reconnect_recovery' => 'not_tested',
                'summary' => 'No terminal-state polls were completed.',
            ];
        }

        $accountInfoPassed = $polls->every(fn (array $poll): bool => (bool) data_get($poll, 'account_information.ok')
            && data_get($poll, 'account_information.balance') !== null
            && data_get($poll, 'account_information.equity') !== null);
        $positionsPassed = $polls->every(fn (array $poll): bool => (bool) data_get($poll, 'positions.ok'));
        $historyPassed = $polls->every(fn (array $poll): bool => (bool) data_get($poll, 'history_orders.ok') && (bool) data_get($poll, 'history_deals.ok'));

        return [
            'account_information' => $accountInfoPassed ? 'passed' : 'failed_or_partial',
            'positions' => $positionsPassed ? 'passed' : 'failed_or_partial',
            'trade_history' => $historyPassed ? 'passed' : 'failed_or_partial',
            'reconnect_recovery' => $polls->count() > count($accounts) ? 'basic_multi_poll_passed' : 'single_poll_only',
            'summary' => $accountInfoPassed && $positionsPassed && $historyPassed
                ? 'MetaApi REST sync returned balance/equity, positions, and history for all polls.'
                : 'One or more MetaApi terminal-state checks failed or returned incomplete data.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function architectureValidation(string $sourceFile, string $broker, string $platform, string $pool): array
    {
        $available = Mt5AccountPoolEntry::query()
            ->where('source_file', $sourceFile)
            ->where('source_pool', $pool)
            ->where('meta->broker', $broker)
            ->where('meta->platform', $platform)
            ->where('is_available', true)
            ->whereNull('allocated_at')
            ->count();
        $assigned = Mt5AccountPoolEntry::query()
            ->where('source_file', $sourceFile)
            ->where('source_pool', $pool)
            ->where('meta->broker', $broker)
            ->where('meta->platform', $platform)
            ->whereNotNull('allocated_at')
            ->count();

        return [
            'pool_model' => 'existing mt5_account_pool_entries + trading_accounts lifecycle',
            'states' => [
                'available' => 'mt5_account_pool_entries.is_available=true and allocated_at=null',
                'assigned' => 'pool entry allocated_trading_account_id set, trading account hydrated with login/server',
                'active' => 'trading_accounts.account_status/challenge_status become active after first successful metric snapshot',
                'breached_or_completed' => 'challenge rule engine locks trading_accounts as failed/passed; pool entry remains historical allocation evidence',
            ],
            'current_metaquotes_pool_counts' => [
                'available' => $available,
                'assigned' => $assigned,
            ],
            'automatic_assignment' => 'Mt5AccountAllocator now accepts broker/source criteria; production default remains FusionMarkets unless WOLFORIX_MT5_ACTIVE_* is changed.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scalabilityAssumptions(int $polls): array
    {
        return [
            'target' => '20-50 accounts',
            'demo_creation' => 'Create slowly in small batches; broker may rate-limit and may limit demo accounts per email.',
            'validation_polling' => 'REST checks are acceptable for 1-2 account validation.',
            'production_sync' => 'Use MetaApi streaming/SDK or sparse scheduled REST checks for always-on monitoring.',
            'rate_limit_notes' => [
                'REST accountInformation and positions are documented at 50 CPU credits each.',
                'History-by-time calls have a larger variable cost and should not run every few seconds.',
                'Multiple polls in this harness test basic reconnect/recovery behavior; they are not a final production polling cadence.',
            ],
            'configured_validation_polls' => $polls,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function finish(array $report): array
    {
        $report['completed_at'] = now()->toIso8601String();
        $path = 'diagnostics/metaquotes-demo-validation-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.json';

        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $report['report_path'] = Storage::disk('local')->path($path);

        Log::info('Phase 1A MetaQuotes demo validation report written.', [
            'mode' => $report['mode'],
            'report_path' => $report['report_path'],
            'accounts' => count($report['accounts'] ?? []),
            'stability' => $report['stability']['summary'] ?? null,
        ]);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function credentialFromMetaApiPayload(array $payload): array
    {
        return [
            'login' => (string) $payload['login'],
            'password' => (string) $payload['password'],
            'investor_password' => filled($payload['investorPassword'] ?? null) ? (string) $payload['investorPassword'] : null,
            'server' => (string) ($payload['serverName'] ?? config('services.metaapi.demo.server_name', 'MetaQuotes-Demo')),
            'source' => 'metaapi_demo_creation',
            'balance' => (float) config('services.metaapi.demo.balance', 10000),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $responses
     * @return list<array<string, mixed>>
     */
    private function summarizeResponses(array $responses): array
    {
        return array_map(fn (array $response): array => $this->summarizeResponse($response), $responses);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function summarizeResponse(array $response): array
    {
        return [
            'action' => $response['action'] ?? null,
            'status' => $response['status'] ?? null,
            'ok' => $response['ok'] ?? null,
            'retry_after' => $response['retry_after'] ?? null,
            'error' => $response['error'] ?? null,
            'payload_keys' => is_array($response['payload'] ?? null) ? array_keys($response['payload']) : [],
            'id' => data_get($response, 'payload.id'),
            'state' => data_get($response, 'payload.state'),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function keywords(array $options): array
    {
        $keywords = array_values(array_filter((array) ($options['keyword'] ?? []), fn ($value): bool => trim((string) $value) !== ''));

        if ($keywords === []) {
            $keywords = (array) config('services.metaapi.demo.keywords', ['MetaQuotes']);
        }

        return array_values(array_unique(array_map(fn ($value): string => trim((string) $value), $keywords)));
    }

    private function stringOption(array $options, string $key, string $default): string
    {
        $value = $options[$key] ?? null;

        return trim((string) (filled($value) ? $value : $default));
    }

    private function sourceFile(string $sourceFile): string
    {
        return basename(trim($sourceFile)) ?: 'metaapi-demo-validation';
    }

    private function emailForAttempt(string $email, int $index, int $count): string
    {
        if ($count === 1) {
            return $email;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $email;
        }

        return $local.'+phase1a-'.$index.'@'.$domain;
    }

    private function throttle(): void
    {
        $milliseconds = max(0, (int) config('services.metaapi.validation.throttle_delay_ms', 1500));

        if ($milliseconds <= 0 || app()->runningUnitTests()) {
            return;
        }

        usleep($milliseconds * 1000);
    }

    private function sleep(int $seconds): void
    {
        if ($seconds <= 0 || app()->runningUnitTests()) {
            return;
        }

        sleep($seconds);
    }
}
