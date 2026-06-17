<?php

namespace App\Services\MetaApi;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\Mt5\Mt5AccountAllocator;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MetaQuotesDemoValidationService
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $debugResponses = [];

    public function __construct(
        private readonly MetaApiClient $metaApi,
        private readonly Mt5AccountAllocator $allocator,
        private readonly MetaQuotesAssignmentMetaApiService $assignmentMetaApi,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $this->debugResponses = [];
        $startedAt = now();
        $live = (bool) ($options['live'] ?? false);
        $poolOnly = (bool) ($options['pool_only'] ?? false);
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
                'history_days' => max(1, (int) ($options['history_days'] ?? config('services.metaapi.history.days', 7))),
                'history_limit' => max(1, (int) ($options['history_limit'] ?? config('services.metaapi.history.limit', 50))),
                'debug_metaapi' => (bool) ($options['debug_metaapi'] ?? false),
                'demo_creation_throttle_ms' => $this->effectiveThrottleMilliseconds(),
                'account_type' => config('services.metaapi.account_type'),
                'account_reliability' => config('services.metaapi.account_reliability'),
                'pool_only' => $poolOnly,
            ],
            'verified_endpoints' => [
                'create_account' => 'POST /users/current/accounts',
                'read_accounts' => 'GET /users/current/accounts?query={login}',
                'read_account' => 'GET /users/current/accounts/{accountId}',
                'read_account_replicas' => 'GET /users/current/accounts/{accountId}/replicas',
                'deploy_account' => 'POST /users/current/accounts/{accountId}/deploy',
                'read_account_information' => 'GET /users/current/accounts/{accountId}/account-information',
                'read_positions' => 'GET /users/current/accounts/{accountId}/positions',
                'read_history_orders' => 'GET /users/current/accounts/{accountId}/history-orders/time/{startTime}/{endTime}',
                'read_history_deals' => 'GET /users/current/accounts/{accountId}/history-deals/time/{startTime}/{endTime}',
            ],
            'architecture_validation' => $this->architectureValidation($sourceFile, $broker, $platform, $pool),
            'scalability_assumptions' => $this->scalabilityAssumptions((int) ($options['polls'] ?? config('services.metaapi.validation.polls', 2))),
            'demo_creation' => [
                'requested' => (bool) ($options['create_demo'] ?? false),
                'count' => $count,
                'status' => 'not_run',
                'batch_stop_reason' => null,
                'next_retry_after' => null,
                'attempts' => [],
            ],
            'accounts' => [],
            'pool' => [
                'store_requested' => (bool) ($options['store_pool'] ?? false) || $poolOnly,
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

        if ((bool) ($options['debug_metaapi'] ?? false)) {
            $report['debug_metaapi'] = [
                'responses' => [],
            ];
        }

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
            $report['stability']['summary'] = $this->emptyCredentialsSummary($options, $poolOnly, $report);

            return $this->finish($report);
        }

        if ($poolOnly) {
            foreach ($credentials as $credential) {
                $poolEntry = $this->storePoolEntry($credential, $sourceFile, $broker, $platform, $pool, $batch);
                $report['pool']['stored'][] = $poolEntry;
                $report['accounts'][] = [
                    'login' => (string) $credential['login'],
                    'server' => (string) $credential['server'],
                    'status' => MetaQuotesAssignmentMetaApiService::STATUS_POOL_ONLY,
                    'pool_entry' => $poolEntry,
                    'metaapi_account_id' => null,
                    'create_account' => [
                        'status' => 'skipped_pool_only',
                        'reason' => 'MetaApi registration is deferred until assignment.',
                    ],
                    'deploy' => [
                        'status' => 'skipped_pool_only',
                        'reason' => 'MetaApi deployment is deferred until assignment.',
                    ],
                    'polls' => [],
                ];

                if (isset($poolEntry['id'])) {
                    $entry = Mt5AccountPoolEntry::query()->find((int) $poolEntry['id']);

                    if ($entry instanceof Mt5AccountPoolEntry) {
                        $this->assignmentMetaApi->markPoolOnly($entry, [
                            'source' => 'metaquotes_validate_demo_pool_only',
                        ]);
                    }
                }
            }

            $report['stability']['summary'] = 'Pool-only mode completed. MetaApi registration, deployment, and sync were intentionally skipped.';

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

            $stopReason = $this->accountBatchStopReason($accountReport);

            if ($stopReason !== null) {
                $report['metaapi_batch_stop_reason'] = $stopReason;

                break;
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

        if (isset($report['metaapi_batch_stop_reason'])) {
            $report['stability']['summary'] = 'MetaApi validation stopped early after a billing/quota/rate-limit response: '.$report['metaapi_batch_stop_reason'];
        }

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
                'keywords' => $this->keywords($options, $server),
            ];

            $transactionId = $this->metaApi->transactionId();
            $attempt = $this->createDemoWithRetries($payload, $transactionId);
            $stopReason = $attempt['credential'] === null ? $this->demoBatchStopReason($attempt) : null;

            foreach ($attempt['responses'] as $responseIndex => $response) {
                $this->recordDebugResponse(
                    $options,
                    'create_mt5_demo_account.attempt_'.$index.'.response_'.($responseIndex + 1),
                    $response,
                    $attempt['credential'] ?? [],
                    $payload,
                );
            }

            $report['demo_creation']['attempts'][] = [
                'email' => $email,
                'transaction_id' => $transactionId,
                'responses' => $this->summarizeDemoCreationResponses($attempt['responses']),
                'credential_received' => $attempt['credential'] !== null,
                'batch_stop_reason' => $stopReason,
            ];

            if ($attempt['credential'] !== null) {
                $created[] = array_merge($attempt['credential'], [
                    'balance' => (float) $payload['balance'],
                ]);
            }

            if ($stopReason !== null) {
                $report['demo_creation']['batch_stop_reason'] = $stopReason;
                $report['demo_creation']['next_retry_after'] = $this->lastRetryAfter($attempt['responses']);

                break;
            }

            if ($index < $count) {
                $this->throttle();
            }
        }

        $report['demo_creation']['status'] = match (true) {
            count($created) === $count => 'passed',
            data_get($report, 'demo_creation.batch_stop_reason') !== null => count($created) > 0 ? 'partial_blocked' : 'blocked',
            count($created) > 0 => 'partial',
            default => 'failed',
        };

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
     * @param  array{credential: array<string, mixed>|null, responses: list<array<string, mixed>>}  $attempt
     */
    private function demoBatchStopReason(array $attempt): ?string
    {
        $responses = $attempt['responses'] ?? [];
        $lastKey = array_key_last($responses);
        $last = $lastKey !== null ? $responses[$lastKey] : null;

        return is_array($last) ? $this->demoCreationStopReason($last) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $responses
     */
    private function lastRetryAfter(array $responses): ?string
    {
        $lastKey = array_key_last($responses);
        $last = $lastKey !== null ? $responses[$lastKey] : null;

        return is_array($last) ? ($last['retry_after'] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $report
     */
    private function emptyCredentialsSummary(array $options, bool $poolOnly, array $report): string
    {
        if ($poolOnly && (bool) ($options['create_demo'] ?? false)) {
            $status = (string) data_get($report, 'demo_creation.status', 'failed');
            $reason = (string) data_get($report, 'demo_creation.batch_stop_reason', '');
            $suffix = $reason !== '' ? " Reason: {$reason}." : '';

            return "Pool-only demo creation did not return credentials; no pool entries were stored. Demo creation status: {$status}.{$suffix}";
        }

        if ($poolOnly) {
            return 'Pool-only mode did not receive credentials; use --create-demo or provide --login/--password to store inventory.';
        }

        return 'No credentials were available for MetaApi registration.';
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
                'balance' => (float) ($options['balance'] ?? config('services.metaapi.demo.balance', 10000)),
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
            'server_requested' => (string) $credential['server'],
            'server_metaapi' => null,
            'identity_warnings' => [],
            'connection_diagnostics' => null,
            'password_present' => filled($credential['password'] ?? null),
            'investor_password_present' => filled($credential['investor_password'] ?? null),
            'metaapi_account_id' => null,
            'create_account' => null,
            'read_existing_accounts' => null,
            'read_account' => null,
            'read_account_replicas' => null,
            'deploy' => null,
            'pool_entry' => null,
            'polls' => [],
        ];

        $metaApiAccountId = $this->manualMetaApiAccountId($options);

        if ($metaApiAccountId !== null) {
            $accountReport['create_account'] = [
                'status' => 'skipped_manual_metaapi_account_id',
                'ok' => null,
                'reason' => 'Using --metaapi-account-id; create/import was not called.',
            ];
        } else {
            $createResult = $this->createAccountWithRetries($credential, $options);
            $this->recordDebugResponse($options, 'create_account.final', $createResult, $credential);
            $accountReport['create_account'] = $this->summarizeResponse($createResult);
            $accountReport['create_account']['reason'] = $this->createFailureReason($createResult);
            $metaApiAccountId = $this->extractCreatedAccountId($createResult);

            if ($metaApiAccountId === null) {
                $existingLookup = $this->findExistingMetaApiAccount($credential, $options);
                $accountReport['read_existing_accounts'] = $existingLookup['summary'];
                $metaApiAccountId = $existingLookup['metaapi_account_id'];
            }
        }

        if ($metaApiAccountId === null) {
            if ((bool) ($options['store_pool'] ?? false)) {
                $accountReport['pool_entry'] = $this->storePoolEntry($credential, $sourceFile, $broker, $platform, $pool, $batch);
            }

            return $accountReport;
        }

        $accountReport['metaapi_account_id'] = $metaApiAccountId;

        $readAccountResult = $this->metaApi->readAccount($metaApiAccountId);
        $this->recordDebugResponse($options, 'read_account.before_deploy', $readAccountResult, $credential);
        $replicasResult = $this->metaApi->readAccountReplicas($metaApiAccountId);
        $this->recordDebugResponse($options, 'read_account_replicas.before_deploy', $replicasResult, $credential);
        $accountReport['read_account'] = $this->summarizeProvisioningAccount($readAccountResult);
        $accountReport['read_account_replicas'] = $this->summarizeReplicaDiagnostics($replicasResult);
        $accountReport['server_metaapi'] = data_get($readAccountResult, 'payload.server');
        $accountReport['identity_warnings'] = $this->identityWarnings($credential, $readAccountResult);
        $accountReport['connection_diagnostics'] = $this->connectionDiagnostics($readAccountResult, $replicasResult);

        $effectiveCredential = $this->credentialWithMetaApiServer($credential, $readAccountResult);
        $accountReport['server'] = (string) $effectiveCredential['server'];

        if ((bool) ($options['store_pool'] ?? false)) {
            $accountReport['pool_entry'] = $this->storePoolEntry($effectiveCredential, $sourceFile, $broker, $platform, $pool, $batch);
        }

        $this->stampMetaApiAccountId($effectiveCredential, $sourceFile, $metaApiAccountId);

        $deployResult = $this->metaApi->deployAccount($metaApiAccountId);
        $this->recordDebugResponse($options, 'deploy_account', $deployResult, $effectiveCredential);
        $accountReport['deploy'] = $this->summarizeResponse($deployResult);
        $accountReport['deploy']['reason'] = $this->deployFailureReason($deployResult, $metaApiAccountId);

        $deployStopReason = $this->metaApiStopReason($deployResult);

        if ($deployStopReason !== null) {
            $accountReport['polls_skipped_reason'] = $deployStopReason;

            return $accountReport;
        }

        $polls = max(1, (int) ($options['polls'] ?? config('services.metaapi.validation.polls', 2)));

        for ($poll = 1; $poll <= $polls; $poll++) {
            $accountReport['polls'][] = $this->pollTerminalState(
                accountId: $metaApiAccountId,
                poll: $poll,
                options: $options,
                credential: $effectiveCredential,
                historyDays: max(1, (int) ($options['history_days'] ?? config('services.metaapi.history.days', config('services.metaapi.validation.history_days', 7)))),
                historyLimit: max(1, (int) ($options['history_limit'] ?? config('services.metaapi.history.limit', 50))),
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
     * @param  array<string, mixed>  $accountReport
     */
    private function accountBatchStopReason(array $accountReport): ?string
    {
        foreach (['create_account', 'deploy'] as $key) {
            $response = $accountReport[$key] ?? null;

            if (! is_array($response)) {
                continue;
            }

            $stopReason = $this->metaApiStopReason($response);

            if ($stopReason !== null) {
                return $key.':'.$stopReason;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function metaApiStopReason(array $response): ?string
    {
        $status = (int) ($response['status'] ?? 0);
        $error = Str::lower(trim((string) ($response['error'] ?? '')));

        if ($status === 429) {
            return 'rate_limited';
        }

        if ($status === 403 && Str::contains($error, ['top up', 'billing', 'quota', 'high reliability', 'deployment'])) {
            return 'billing_or_feature_top_up_required';
        }

        if ($status === 402) {
            return 'payment_required';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function demoCreationStopReason(array $response): ?string
    {
        $stopReason = $this->metaApiStopReason($response);

        if ($stopReason !== null) {
            return $stopReason;
        }

        $status = (int) ($response['status'] ?? 0);
        $payload = is_array($response['payload'] ?? null) ? $response['payload'] : [];
        $details = data_get($payload, 'details');
        $text = Str::lower(trim(implode(' ', array_filter([
            (string) ($response['error'] ?? ''),
            (string) data_get($payload, 'error', ''),
            (string) data_get($payload, 'message', ''),
            is_scalar($details) ? (string) $details : (json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
        ]))));

        if ($status === 0) {
            return 'transport_or_timeout';
        }

        if ($status === 202) {
            return 'demo_creation_pending_timeout';
        }

        if ($status === 400 && Str::contains($text, [
            'too many demo accounts',
            'specific email, user name or phone number',
            'please use the name, email and phone number of your end users',
        ])) {
            return 'identity_limit';
        }

        if ($status === 400 && Str::contains($text, ['validation', 'invalid', 'should include'])) {
            return 'validation_error';
        }

        return $status >= 400 ? 'http_'.$status : null;
    }

    /**
     * @param  array<string, mixed>  $credential
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function createAccountWithRetries(array $credential, array $options): array
    {
        $transactionId = $this->metaApi->transactionId();
        $payload = array_filter([
            'login' => (string) $credential['login'],
            'password' => (string) $credential['password'],
            'name' => 'Wolforix Phase 1A '.$credential['login'],
            'server' => (string) $credential['server'],
            'platform' => 'mt5',
            'magic' => 20260525,
            'type' => config('services.metaapi.account_type'),
            'reliability' => config('services.metaapi.account_reliability'),
            'provisioningProfileId' => $this->provisioningProfileIdForCreate(),
            'keywords' => $this->keywords($options, (string) $credential['server']),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        $maxAttempts = max(1, 1 + (int) config('services.metaapi.demo.accepted_retries', 3));
        $lastResult = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->metaApi->createAccount($payload, $transactionId);
            $lastResult = $result;
            $this->recordDebugResponse($options, 'create_account.attempt_'.$attempt, $result, $credential, $payload);

            if ((int) $result['status'] !== 202) {
                return $result;
            }

            $this->sleep((int) config('services.metaapi.demo.accepted_retry_delay_seconds', 30));
        }

        return $lastResult;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractCreatedAccountId(array $response): ?string
    {
        $status = (int) ($response['status'] ?? 0);

        if (! in_array($status, [201, 202], true)) {
            return null;
        }

        $id = (string) (data_get($response, 'payload.id') ?: data_get($response, 'payload._id') ?: '');

        return $this->looksLikeMetaApiAccountId($id) ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $credential
     * @param  array<string, mixed>  $options
     * @return array{metaapi_account_id: string|null, summary: array<string, mixed>}
     */
    private function findExistingMetaApiAccount(array $credential, array $options): array
    {
        $lookup = $this->metaApi->readAccounts((string) $credential['login']);
        $this->recordDebugResponse($options, 'read_accounts.by_login', $lookup, $credential);
        $payload = $this->accountsPayload($lookup);
        $matches = collect($payload)
            ->filter(fn (array $account): bool => (string) ($account['login'] ?? '') === (string) $credential['login'])
            ->values();
        $exactServer = $matches
            ->filter(fn (array $account): bool => $this->serverMatches((string) ($account['server'] ?? ''), (string) $credential['server']))
            ->values();
        $selected = $exactServer->first();
        $selectionReason = 'no_existing_account_match';

        if (! is_array($selected) && $matches->count() === 1) {
            $selected = $matches->first();
            $selectionReason = 'single_login_match_server_mismatch';
        } elseif (is_array($selected)) {
            $selectionReason = 'exact_login_server_match';
        } elseif ($matches->count() > 1) {
            $selectionReason = 'multiple_login_matches_manual_metaapi_account_id_required';
        }

        $accountId = is_array($selected) ? (string) ($selected['_id'] ?? $selected['id'] ?? '') : '';

        return [
            'metaapi_account_id' => $this->looksLikeMetaApiAccountId($accountId) ? $accountId : null,
            'summary' => [
                'status' => $lookup['status'] ?? null,
                'ok' => $lookup['ok'] ?? null,
                'reason' => $selectionReason,
                'matches' => $matches->count(),
                'exact_server_matches' => $exactServer->count(),
                'selected_metaapi_account_id' => $this->looksLikeMetaApiAccountId($accountId) ? $accountId : null,
                'selected_login' => is_array($selected) ? ($selected['login'] ?? null) : null,
                'selected_server' => is_array($selected) ? ($selected['server'] ?? null) : null,
                'selected_state' => is_array($selected) ? ($selected['state'] ?? null) : null,
                'selected_connection_status' => is_array($selected) ? ($selected['connectionStatus'] ?? null) : null,
                'error' => $lookup['error'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function accountsPayload(array $response): array
    {
        $payload = $response['payload'] ?? [];

        if (is_array($payload) && array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        if (is_array(data_get($payload, 'items')) && array_is_list((array) data_get($payload, 'items'))) {
            return array_values(array_filter((array) data_get($payload, 'items'), 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function summarizeProvisioningAccount(array $response): array
    {
        return array_merge($this->summarizeResponse($response), [
            '_id' => data_get($response, 'payload._id'),
            'login' => data_get($response, 'payload.login'),
            'server' => data_get($response, 'payload.server'),
            'state' => data_get($response, 'payload.state'),
            'connection_status' => data_get($response, 'payload.connectionStatus'),
            'region' => data_get($response, 'payload.region'),
            'connections' => data_get($response, 'payload.connections', []),
            'replicas' => $this->replicaSummary((array) data_get($response, 'payload.replicas', [])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function summarizeReplicaDiagnostics(array $response): array
    {
        $replicas = $this->replicasPayload($response);

        return [
            'status' => $response['status'] ?? null,
            'ok' => $response['ok'] ?? null,
            'count' => count($replicas),
            'replicas' => $this->replicaSummary($replicas),
            'error' => $response['error'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $credential
     * @param  array<string, mixed>  $response
     * @return list<string>
     */
    private function identityWarnings(array $credential, array $response): array
    {
        $warnings = [];
        $metaApiLogin = (string) data_get($response, 'payload.login', '');
        $requestedLogin = (string) ($credential['login'] ?? '');
        $metaApiServer = (string) data_get($response, 'payload.server', '');
        $requestedServer = (string) ($credential['server'] ?? '');

        if ($metaApiLogin !== '' && $requestedLogin !== '' && $metaApiLogin !== $requestedLogin) {
            $warnings[] = "login_mismatch: requested {$requestedLogin}, MetaApi returned {$metaApiLogin}";
        }

        if ($metaApiServer !== '' && $requestedServer !== '' && $metaApiServer !== $requestedServer) {
            $warnings[] = $this->serverMatches($metaApiServer, $requestedServer)
                ? "server_alias_mismatch: requested {$requestedServer}, MetaApi canonical server is {$metaApiServer}"
                : "server_mismatch: requested {$requestedServer}, MetaApi returned {$metaApiServer}";
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function connectionDiagnostics(array $response, ?array $replicasResponse = null): array
    {
        $state = (string) data_get($response, 'payload.state', '');
        $connectionStatus = (string) data_get($response, 'payload.connectionStatus', '');
        $connections = (array) data_get($response, 'payload.connections', []);
        $accountReplicas = (array) data_get($response, 'payload.replicas', []);
        $replicas = $replicasResponse !== null ? $this->replicasPayload($replicasResponse) : [];
        $replicas = $replicas !== [] ? $replicas : $accountReplicas;

        return [
            'state' => $state !== '' ? $state : null,
            'connection_status' => $connectionStatus !== '' ? $connectionStatus : null,
            'region' => data_get($response, 'payload.region'),
            'connections' => $connections,
            'replicas' => $this->replicaSummary($replicas),
            'replica_lookup' => $replicasResponse !== null ? [
                'status' => $replicasResponse['status'] ?? null,
                'ok' => $replicasResponse['ok'] ?? null,
                'error' => $replicasResponse['error'] ?? null,
            ] : null,
            'probable_cause' => $this->disconnectedProbableCause($state, $connectionStatus, $connections, $replicas),
            'next_actions' => $this->disconnectedNextActions($state, $connectionStatus),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function replicasPayload(array $response): array
    {
        $payload = $response['payload'] ?? [];

        if (is_array($payload) && array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        if (is_array(data_get($payload, 'items')) && array_is_list((array) data_get($payload, 'items'))) {
            return array_values(array_filter((array) data_get($payload, 'items'), 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<int|string, mixed>  $replicas
     * @return list<array<string, mixed>>
     */
    private function replicaSummary(array $replicas): array
    {
        return collect($replicas)
            ->filter(fn (mixed $replica): bool => is_array($replica))
            ->map(fn (array $replica): array => [
                '_id' => $replica['_id'] ?? $replica['id'] ?? null,
                'region' => $replica['region'] ?? null,
                'state' => $replica['state'] ?? null,
                'connection_status' => $replica['connectionStatus'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $connections
     * @param  array<int|string, mixed>  $replicas
     */
    private function disconnectedProbableCause(string $state, string $connectionStatus, array $connections, array $replicas): string
    {
        if ($state !== 'DEPLOYED') {
            return 'MetaApi account is not fully deployed yet; deploy/redeploy may still be required.';
        }

        if ($connectionStatus === 'CONNECTED') {
            return 'MetaApi account is connected.';
        }

        if ($connectionStatus === 'DISCONNECTED_FROM_BROKER') {
            return 'MetaApi terminal is running but broker connection failed. Most likely causes: wrong MT5 password, account disabled/read-only restriction, broker server mismatch, or broker-side session/security restriction.';
        }

        if ($connectionStatus === 'DISCONNECTED' && $connections === [] && $replicas === []) {
            return 'MetaApi has deployed the cloud terminal but has not established an active MetaApi connection yet. Common causes: terminal still starting, wrong password, broker rejects login, server alias mismatch, or region/server settings need a provisioning profile.';
        }

        if ($connectionStatus === 'DISCONNECTED' && $replicas !== []) {
            return 'MetaApi has deployed the cloud terminal but the primary account or replica still reports DISCONNECTED. Common causes: broker authentication failure, wrong MT5 main password, account disabled at broker, server mismatch, or region/provisioning settings.';
        }

        return 'MetaApi reports the account is deployed but not connected. Inspect debug response payload, connectionStatus, replica statuses, and broker authentication/server details.';
    }

    /**
     * @return list<string>
     */
    private function disconnectedNextActions(string $state, string $connectionStatus): array
    {
        if ($connectionStatus === 'CONNECTED') {
            return [];
        }

        $actions = [
            'Use the exact MetaApi server value in validation and pool storage, for these accounts: FusionMarkets-Demo.',
            'Confirm the MT5 main password logs into this exact server in a terminal; investor password alone may not be enough for full trading features.',
            'If the account remains disconnected after deployment, redeploy from MetaApi dashboard and inspect broker authentication errors there.',
        ];

        if ($state === 'DEPLOYED') {
            $actions[] = 'Wait/poll longer only if deployment just happened; otherwise treat persistent DISCONNECTED as a broker auth/server/provisioning issue.';
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $credential
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function credentialWithMetaApiServer(array $credential, array $response): array
    {
        $server = trim((string) data_get($response, 'payload.server', ''));

        if ($server !== '') {
            $credential['server'] = $server;
        }

        return $credential;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function createFailureReason(array $response): ?string
    {
        $status = (int) ($response['status'] ?? 0);

        if (in_array($status, [201, 202], true) && $this->extractCreatedAccountId($response) !== null) {
            return 'created_or_processing_with_confirmed_account_id';
        }

        if ($status === 400) {
            return 'validation_error_not_a_confirmed_account_id';
        }

        if ($status === 404) {
            return 'provisioning_profile_or_create_resource_not_found';
        }

        return ($response['ok'] ?? false) ? null : 'metaapi_create_failed';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function deployFailureReason(array $response, string $metaApiAccountId): ?string
    {
        if ((bool) ($response['ok'] ?? false) || (int) ($response['status'] ?? 0) === 204) {
            return 'deploy_accepted_or_already_deployed';
        }

        if ((int) ($response['status'] ?? 0) === 404) {
            return "metaapi_account_id_not_found_or_wrong_region: {$metaApiAccountId}";
        }

        return 'metaapi_deploy_failed';
    }

    private function manualMetaApiAccountId(array $options): ?string
    {
        $accountId = trim((string) ($options['metaapi_account_id'] ?? ''));

        if ($accountId === '') {
            return null;
        }

        if (! $this->looksLikeMetaApiAccountId($accountId)) {
            throw new RuntimeException('--metaapi-account-id must be the MetaApi account _id from the dashboard, not the MT5 login or an error id.');
        }

        return $accountId;
    }

    private function provisioningProfileIdForCreate(): ?string
    {
        $profileId = trim((string) config('services.metaapi.profile_id', ''));

        return $profileId !== '' && $profileId !== 'default' ? $profileId : null;
    }

    private function looksLikeMetaApiAccountId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    private function serverMatches(string $metaApiServer, string $requestedServer): bool
    {
        if ($metaApiServer === $requestedServer) {
            return true;
        }

        return $this->normalizedServer($metaApiServer) === $this->normalizedServer($requestedServer);
    }

    private function normalizedServer(string $server): string
    {
        return Str::lower((string) preg_replace('/[^a-z0-9]+/i', '', $server));
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credential
     * @return array<string, mixed>
     */
    private function pollTerminalState(string $accountId, int $poll, array $options, array $credential, int $historyDays, int $historyLimit): array
    {
        $end = now()->utc();
        $provisioningAccount = $this->metaApi->readAccount($accountId);
        $accountReplicas = $this->metaApi->readAccountReplicas($accountId);
        $accountInformation = $this->metaApi->readAccountInformation($accountId, $poll === 1);
        $positions = $this->metaApi->readPositions($accountId);
        $historyOrders = $this->readHistoryWithFallbacks('orders', $accountId, $end, $historyDays, $historyLimit, $poll, $options, $credential);
        $historyDeals = $this->readHistoryWithFallbacks('deals', $accountId, $end, $historyDays, $historyLimit, $poll, $options, $credential);

        foreach ([
            'read_account.poll_'.$poll => $provisioningAccount,
            'read_account_replicas.poll_'.$poll => $accountReplicas,
            'read_account_information.poll_'.$poll => $accountInformation,
            'read_positions.poll_'.$poll => $positions,
        ] as $label => $response) {
            $this->recordDebugResponse($options, $label, $response, $credential);
        }

        return [
            'poll' => $poll,
            'polled_at' => now()->toIso8601String(),
            'provisioning_account' => [
                'status' => $provisioningAccount['status'],
                'ok' => $provisioningAccount['ok'],
                '_id' => data_get($provisioningAccount, 'payload._id'),
                'login' => data_get($provisioningAccount, 'payload.login'),
                'server' => data_get($provisioningAccount, 'payload.server'),
                'state' => data_get($provisioningAccount, 'payload.state'),
                'connection_status' => data_get($provisioningAccount, 'payload.connectionStatus'),
                'region' => data_get($provisioningAccount, 'payload.region'),
                'connections' => data_get($provisioningAccount, 'payload.connections', []),
                'replicas' => $this->replicaSummary((array) data_get($provisioningAccount, 'payload.replicas', [])),
                'diagnostics' => $this->connectionDiagnostics($provisioningAccount, $accountReplicas),
                'error' => $provisioningAccount['error'],
            ],
            'account_replicas' => $this->summarizeReplicaDiagnostics($accountReplicas),
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
                'degraded' => (bool) ($historyOrders['degraded'] ?? false),
                'attempts' => $historyOrders['attempts'] ?? [],
                'error' => $historyOrders['error'],
            ],
            'history_deals' => [
                'status' => $historyDeals['status'],
                'ok' => $historyDeals['ok'],
                'count' => is_array($historyDeals['payload']) ? count($historyDeals['payload']) : null,
                'degraded' => (bool) ($historyDeals['degraded'] ?? false),
                'attempts' => $historyDeals['attempts'] ?? [],
                'error' => $historyDeals['error'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credential
     * @return array<string, mixed>
     */
    private function readHistoryWithFallbacks(string $type, string $accountId, Carbon $end, int $historyDays, int $historyLimit, int $poll, array $options, array $credential): array
    {
        $ranges = $this->historyRanges($end, $historyDays);
        $attempts = [];
        $lastResponse = null;

        foreach ($ranges as $index => $range) {
            $response = $type === 'orders'
                ? $this->metaApi->readHistoryOrdersByTimeRange($accountId, $range['start'], $end, $historyLimit)
                : $this->metaApi->readDealsByTimeRange($accountId, $range['start'], $end, $historyLimit);

            $response['history_range'] = $range['label'];
            $response['degraded'] = $index > 0;
            $lastResponse = $response;

            $this->recordDebugResponse($options, 'read_history_'.$type.'.poll_'.$poll.'.attempt_'.($index + 1), $response, $credential);

            $attempts[] = [
                'range' => $range['label'],
                'status' => $response['status'] ?? null,
                'ok' => $response['ok'] ?? null,
                'error' => $response['error'] ?? null,
            ];

            if ((bool) ($response['ok'] ?? false)) {
                $response['attempts'] = $attempts;

                return $response;
            }
        }

        $lastResponse ??= [
            'action' => 'read_history_'.$type,
            'ok' => false,
            'status' => 0,
            'payload' => [],
            'body' => '',
            'retry_after' => null,
            'error' => 'No history attempts were executed.',
        ];
        $lastResponse['attempts'] = $attempts;
        $lastResponse['degraded'] = true;

        return $lastResponse;
    }

    /**
     * @return list<array{label: string, start: Carbon}>
     */
    private function historyRanges(Carbon $end, int $historyDays): array
    {
        $ranges = [
            [
                'label' => $historyDays.'d',
                'start' => $end->copy()->subDays($historyDays),
            ],
            [
                'label' => '1d',
                'start' => $end->copy()->subDay(),
            ],
            [
                'label' => '6h',
                'start' => $end->copy()->subHours(6),
            ],
        ];

        return collect($ranges)
            ->unique(fn (array $range): string => $range['start']->toIso8601String())
            ->values()
            ->all();
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
        $credentialIntegrity = $entry instanceof Mt5AccountPoolEntry
            ? $this->poolCredentialIntegrity($entry)
            : null;

        if ($entry instanceof Mt5AccountPoolEntry && ($entry->allocated_at !== null || $entry->allocated_trading_account_id !== null)) {
            return [
                'id' => $entry->id,
                'login' => $entry->login,
                'status' => 'existing_allocated_not_overwritten',
                'credential_integrity' => $credentialIntegrity,
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
            if ($this->poolCredentialIntegrityFailed($credentialIntegrity)) {
                $this->logPoolCredentialFailure($entry, $credentialIntegrity, 'repairing_from_live_metaapi_validation');
                $entry = $this->repairPoolEntryWithRawEncryption($entry, $attributes, 'invalid_encrypted_payload_repaired_from_live_metaapi_validation');
                $status = 'repaired_encrypted_credentials';
            } else {
                try {
                    $entry->forceFill($attributes)->save();
                    $status = 'updated';
                } catch (DecryptException) {
                    $credentialIntegrity = $this->poolCredentialIntegrity($entry);
                    $this->logPoolCredentialFailure($entry, $credentialIntegrity, 'fallback_repair_after_eloquent_save_decrypt_failure');
                    $entry = $this->repairPoolEntryWithRawEncryption($entry, $attributes, 'eloquent_save_decrypt_failure_repaired_from_live_metaapi_validation');
                    $status = 'repaired_after_decrypt_failure';
                }
            }
        } else {
            $entry = Mt5AccountPoolEntry::query()->create($attributes);
            $status = 'created';
        }

        $credentialIntegrityAfter = $this->poolCredentialIntegrity($entry);

        return [
            'id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
            'account_size' => (int) $entry->account_size,
            'status' => $status,
            'credential_integrity' => $credentialIntegrityAfter,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function poolCredentialIntegrity(Mt5AccountPoolEntry $entry): array
    {
        return [
            'password' => $this->poolCredentialColumnIntegrity($entry, 'password'),
            'investor_password' => $this->poolCredentialColumnIntegrity($entry, 'investor_password'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function poolCredentialColumnIntegrity(Mt5AccountPoolEntry $entry, string $column): array
    {
        $rawValue = $entry->getRawOriginal($column);
        $rawPresent = filled($rawValue);

        try {
            $value = $entry->{$column};

            return [
                'model' => Mt5AccountPoolEntry::class,
                'table' => $entry->getTable(),
                'column' => $column,
                'state' => filled($value) ? 'decryptable_present' : ($rawPresent ? 'decryptable_empty' : 'missing'),
                'raw_present' => $rawPresent,
                'encrypted_payload_shape' => $rawPresent ? $this->looksLikeEncryptedCastPayload((string) $rawValue) : false,
            ];
        } catch (DecryptException $exception) {
            return [
                'model' => Mt5AccountPoolEntry::class,
                'table' => $entry->getTable(),
                'column' => $column,
                'state' => $rawPresent ? 'decrypt_failed' : 'missing',
                'raw_present' => $rawPresent,
                'encrypted_payload_shape' => $rawPresent ? $this->looksLikeEncryptedCastPayload((string) $rawValue) : false,
                'exception' => $exception::class,
                'reason' => 'laravel_encrypted_cast_decrypt_failed',
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $integrity
     */
    private function poolCredentialIntegrityFailed(?array $integrity): bool
    {
        if ($integrity === null) {
            return false;
        }

        return collect($integrity)
            ->contains(fn (array $column): bool => ($column['state'] ?? null) === 'decrypt_failed');
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $integrity
     */
    private function logPoolCredentialFailure(Mt5AccountPoolEntry $entry, ?array $integrity, string $action): void
    {
        Log::warning('MetaApi validation found an unreadable encrypted MT5 pool credential column.', [
            'action' => $action,
            'model' => Mt5AccountPoolEntry::class,
            'table' => $entry->getTable(),
            'mt5_account_pool_entry_id' => $entry->id,
            'login' => $entry->login,
            'server' => $entry->server,
            'source_file' => $entry->source_file,
            'source_pool' => $entry->source_pool,
            'columns' => collect($integrity ?? [])
                ->filter(fn (array $column): bool => ($column['state'] ?? null) === 'decrypt_failed')
                ->map(fn (array $column): array => [
                    'column' => $column['column'] ?? null,
                    'state' => $column['state'] ?? null,
                    'reason' => $column['reason'] ?? null,
                    'exception' => $column['exception'] ?? null,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function repairPoolEntryWithRawEncryption(Mt5AccountPoolEntry $entry, array $attributes, string $reason): Mt5AccountPoolEntry
    {
        $meta = is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [];
        $meta['metaapi_pool_repaired_at'] = now()->toIso8601String();
        $meta['metaapi_pool_repair_reason'] = $reason;

        DB::table($entry->getTable())
            ->where('id', $entry->id)
            ->update([
                'login' => $attributes['login'],
                'password' => Crypt::encryptString((string) $attributes['password']),
                'investor_password' => filled($attributes['investor_password'] ?? null)
                    ? Crypt::encryptString((string) $attributes['investor_password'])
                    : null,
                'server' => $attributes['server'],
                'account_size' => $attributes['account_size'],
                'currency_code' => $attributes['currency_code'],
                'source_status' => $attributes['source_status'],
                'source_file' => $attributes['source_file'],
                'source_batch' => $attributes['source_batch'],
                'source_pool' => $attributes['source_pool'],
                'source_created_at' => $attributes['source_created_at'],
                'is_promo' => $attributes['is_promo'],
                'is_available' => $attributes['is_available'],
                'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

        /** @var Mt5AccountPoolEntry $repaired */
        $repaired = Mt5AccountPoolEntry::query()->findOrFail($entry->id);

        return $repaired;
    }

    private function looksLikeEncryptedCastPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && array_key_exists('iv', $payload)
            && array_key_exists('value', $payload)
            && array_key_exists('mac', $payload);
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
        $corePassed = $accountInfoPassed && $positionsPassed;
        $validationState = match (true) {
            $corePassed && $historyPassed => 'CONNECTED',
            $corePassed => 'PARTIAL_CONNECTED',
            default => 'BLOCKED',
        };

        return [
            'validation_state' => $validationState,
            'account_information' => $accountInfoPassed ? 'passed' : 'failed_or_partial',
            'positions' => $positionsPassed ? 'passed' : 'failed_or_partial',
            'trade_history' => $historyPassed ? 'passed' : ($corePassed ? 'degraded_non_blocking' : 'failed_or_partial'),
            'reconnect_recovery' => $polls->count() > count($accounts) ? 'basic_multi_poll_passed' : 'single_poll_only',
            'summary' => match ($validationState) {
                'CONNECTED' => 'MetaApi REST sync returned balance/equity, positions, and history for all polls.',
                'PARTIAL_CONNECTED' => 'MetaApi account info and positions are readable; history/orders are degraded and non-blocking for Phase 1A.',
                default => 'One or more required MetaApi terminal-state checks failed or returned incomplete data.',
            },
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

        if ((bool) data_get($report, 'config.debug_metaapi', false)) {
            $report['debug_metaapi']['responses'] = $this->debugResponses;
        }

        $path = 'diagnostics/metaquotes-demo-validation-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.json';
        $supportReportPath = (bool) data_get($report, 'demo_creation.requested', false)
            ? str_replace('.json', '-metaapi-support.md', $path)
            : null;

        $report['report_path'] = Storage::disk('local')->path($path);

        if ($supportReportPath !== null) {
            $report['support_report_path'] = Storage::disk('local')->path($supportReportPath);
        }

        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($supportReportPath !== null) {
            Storage::disk('local')->put($supportReportPath, $this->metaApiSupportReportMarkdown($report));
        }

        Log::info('Phase 1A MetaQuotes demo validation report written.', [
            'mode' => $report['mode'],
            'report_path' => $report['report_path'],
            'support_report_path' => $report['support_report_path'] ?? null,
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
     * @param  list<array<string, mixed>>  $responses
     * @return list<array<string, mixed>>
     */
    private function summarizeDemoCreationResponses(array $responses): array
    {
        return array_map(function (array $response): array {
            $summary = $this->summarizeResponse($response);
            $summary['batch_stop_reason'] = $this->demoCreationStopReason($response);

            return $summary;
        }, $responses);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function summarizeResponse(array $response): array
    {
        $payload = is_array($response['payload'] ?? null) ? $response['payload'] : [];
        $errorCode = data_get($payload, 'error') ?? ($response['error_code'] ?? null);
        $errorMessage = data_get($payload, 'message') ?? ($response['error_message'] ?? $response['error'] ?? null);

        return [
            'action' => $response['action'] ?? null,
            'status' => $response['status'] ?? null,
            'ok' => $response['ok'] ?? null,
            'retry_after' => $response['retry_after'] ?? null,
            'error' => $this->sanitizeForDebug($errorCode ?? ($response['error'] ?? null), []),
            'error_code' => $this->sanitizeForDebug($errorCode, []),
            'message' => $this->sanitizeForDebug(data_get($payload, 'message'), []),
            'error_message' => $this->sanitizeForDebug($errorMessage, []),
            'details' => $this->sanitizeForDebug(data_get($payload, 'details'), []),
            'request_id' => $response['request_id'] ?? null,
            'trace_id' => $response['trace_id'] ?? data_get($payload, 'traceId'),
            'transaction_id' => $response['transaction_id'] ?? data_get($response, 'request.transaction_id'),
            'request' => $this->sanitizeForDebug($response['request'] ?? null, []),
            'batch_stop_reason' => $this->metaApiStopReason($response),
            'payload_keys' => array_keys($payload),
            'id' => data_get($payload, 'id'),
            'state' => data_get($payload, 'state'),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $credential
     * @param  array<string, mixed>|null  $requestPayload
     */
    private function recordDebugResponse(array $options, string $label, array $response, array $credential, ?array $requestPayload = null): void
    {
        if (! (bool) ($options['debug_metaapi'] ?? false)) {
            return;
        }

        $debug = [
            'label' => $label,
            'action' => $response['action'] ?? null,
            'status' => $response['status'] ?? null,
            'ok' => $response['ok'] ?? null,
            'retry_after' => $response['retry_after'] ?? null,
            'request_id' => $response['request_id'] ?? null,
            'trace_id' => $response['trace_id'] ?? data_get($response, 'payload.traceId'),
            'transaction_id' => $response['transaction_id'] ?? data_get($response, 'request.transaction_id'),
            'error_code' => $this->sanitizeForDebug($response['error_code'] ?? data_get($response, 'payload.error'), $credential),
            'error_message' => $this->sanitizeForDebug($response['error_message'] ?? $response['error'] ?? null, $credential),
            'error' => $this->sanitizeForDebug($response['error'] ?? null, $credential),
            'details' => $this->sanitizeForDebug($response['details'] ?? data_get($response, 'payload.details'), $credential),
            'request' => $this->sanitizeForDebug($response['request'] ?? null, $credential),
            'request_payload' => $requestPayload !== null
                ? $this->sanitizeForDebug($requestPayload, $credential)
                : $this->sanitizeForDebug(data_get($response, 'request.payload'), $credential),
            'response_payload' => $this->sanitizeForDebug($response['payload'] ?? null, $credential),
            'response_body' => $this->sanitizeForDebug($response['body'] ?? null, $credential),
        ];

        Log::info('MetaApi validation debug response.', $debug);
        $this->debugResponses[] = $debug;
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    private function sanitizeForDebug(mixed $value, array $credential): mixed
    {
        $secrets = array_values(array_filter([
            (string) config('services.metaapi.token', ''),
            (string) ($credential['password'] ?? ''),
            (string) ($credential['investor_password'] ?? ''),
        ], static fn (string $secret): bool => $secret !== ''));

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/token|password|secret|authorization|auth/i', $key)) {
                    $sanitized[$key] = '[redacted]';

                    continue;
                }

                $sanitized[$key] = $this->sanitizeForDebug($item, $credential);
            }

            return $sanitized;
        }

        if (! is_string($value)) {
            return $value;
        }

        foreach ($secrets as $secret) {
            $value = str_replace($secret, '[redacted]', $value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function metaApiSupportReportMarkdown(array $report): string
    {
        $lines = [
            '# MetaApi Support Report: MetaQuotes MT5 Demo Creation',
            '',
            'Generated by Wolforix `metaquotes:validate-demo` on '.(string) data_get($report, 'completed_at', now()->toIso8601String()).'.',
            '',
            '## Summary',
            '',
            '- Mode: '.(string) data_get($report, 'mode', '-'),
            '- Demo creation status: '.(string) data_get($report, 'demo_creation.status', '-'),
            '- Batch stop reason: '.(string) (data_get($report, 'demo_creation.batch_stop_reason') ?: '-'),
            '- MetaApi provisioning base URL: '.(string) data_get($report, 'config.provisioning_base_url', '-'),
            '- Provisioning profile ID: '.(string) data_get($report, 'config.profile_id', '-'),
            '- Requested server: '.(string) data_get($report, 'config.server', '-'),
            '- Account type: '.(string) data_get($report, 'config.account_type', '-'),
            '- JSON diagnostic path: '.(string) data_get($report, 'report_path', '-'),
            '',
            '## Demo Creation Attempts',
            '',
        ];

        $attempts = (array) data_get($report, 'demo_creation.attempts', []);

        if ($attempts === []) {
            $lines[] = 'No demo creation attempts were recorded.';
            $lines[] = '';

            return implode("\n", $lines);
        }

        foreach ($attempts as $attemptIndex => $attempt) {
            if (! is_array($attempt)) {
                continue;
            }

            $lines[] = '### Attempt '.((int) $attemptIndex + 1);
            $lines[] = '';
            $lines[] = '- Email: '.(string) ($attempt['email'] ?? '-');
            $lines[] = '- Transaction ID: '.(string) ($attempt['transaction_id'] ?? '-');
            $lines[] = '- Credential received: '.((bool) ($attempt['credential_received'] ?? false) ? 'yes' : 'no');
            $lines[] = '- Attempt stop reason: '.(string) (($attempt['batch_stop_reason'] ?? null) ?: '-');
            $lines[] = '';

            foreach ((array) ($attempt['responses'] ?? []) as $responseIndex => $response) {
                if (! is_array($response)) {
                    continue;
                }

                $lines[] = '#### Response '.((int) $responseIndex + 1);
                $lines[] = '';
                $lines[] = '- HTTP status: '.$this->markdownValue($response['status'] ?? null);
                $lines[] = '- Error code: '.$this->markdownValue($response['error_code'] ?? $response['error'] ?? null);
                $lines[] = '- Error message: '.$this->markdownValue($response['error_message'] ?? $response['message'] ?? null);
                $lines[] = '- Request ID: '.$this->markdownValue($response['request_id'] ?? null);
                $lines[] = '- Trace ID: '.$this->markdownValue($response['trace_id'] ?? null);
                $lines[] = '- Transaction ID: '.$this->markdownValue($response['transaction_id'] ?? $attempt['transaction_id'] ?? null);
                $lines[] = '- Retry after: '.$this->markdownValue($response['retry_after'] ?? null);
                $lines[] = '';
                $lines[] = 'Request payload sent to MetaApi:';
                $lines[] = $this->jsonBlock(data_get($response, 'request.payload'));
                $lines[] = 'Equivalent curl command:';
                $lines[] = '```bash';
                $lines[] = (string) (data_get($response, 'request.curl') ?: '[curl unavailable]');
                $lines[] = '```';
                $lines[] = 'Full details array:';
                $lines[] = $this->jsonBlock($response['details'] ?? null);
                $lines[] = '';
            }
        }

        $debugResponses = collect((array) data_get($report, 'debug_metaapi.responses', []))
            ->filter(fn (mixed $response): bool => is_array($response)
                && Str::startsWith((string) ($response['label'] ?? ''), 'create_mt5_demo_account.'))
            ->values()
            ->all();

        if ($debugResponses !== []) {
            $lines[] = '## Sanitized Raw MetaApi Creation Logs';
            $lines[] = '';
            $lines[] = $this->jsonBlock($debugResponses);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function markdownValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[unprintable]';
    }

    private function jsonBlock(mixed $value): string
    {
        return "```json\n".(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null')."\n```";
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function keywords(array $options, ?string $server = null): array
    {
        $keywords = array_values(array_filter((array) ($options['keyword'] ?? []), fn ($value): bool => trim((string) $value) !== ''));

        if ($keywords === []) {
            $keywords = $this->serverKeywords($server);
        }

        if ($keywords === []) {
            $keywords = (array) config('services.metaapi.demo.keywords', []);
        }

        return array_values(array_unique(array_map(fn ($value): string => trim((string) $value), $keywords)));
    }

    /**
     * @return list<string>
     */
    private function serverKeywords(?string $server): array
    {
        $server = trim((string) $server);

        if ($server === '') {
            return [];
        }

        $withoutDemo = trim((string) preg_replace('/\b(demo|live|real)\b/i', '', $server));
        $parts = array_values(array_filter(array_map('trim', preg_split('/[-–—]+/', $withoutDemo) ?: [])));
        $keywords = [];

        if ($parts !== []) {
            $keywords[] = $parts[0];
            $keywords[] = end($parts);
        }

        $keywords[] = str_replace(' ', '', $server);

        return array_values(array_unique(array_filter($keywords)));
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
        $milliseconds = $this->effectiveThrottleMilliseconds();

        if ($milliseconds <= 0 || app()->runningUnitTests()) {
            return;
        }

        usleep($milliseconds * 1000);
    }

    private function effectiveThrottleMilliseconds(): int
    {
        $configured = max(0, (int) config('services.metaapi.validation.throttle_delay_ms', 65000));
        $minimum = max(0, (int) config('services.metaapi.validation.minimum_throttle_delay_ms', 65000));

        return max($configured, $minimum);
    }

    private function sleep(int $seconds): void
    {
        if ($seconds <= 0 || app()->runningUnitTests()) {
            return;
        }

        sleep($seconds);
    }
}
