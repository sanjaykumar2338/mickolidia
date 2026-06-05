<?php

namespace Tests\Feature;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\MetaApi\MetaQuotesAssignmentMetaApiService;
use App\Services\MetaQuotes\MetaQuotesPoolProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaQuotesAssignmentMetaApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.metaapi.enabled', true);
        config()->set('services.metaapi.token', 'test-token');
        config()->set('services.metaapi.provisioning_base_url', 'https://metaapi-provisioning.test');
        config()->set('services.metaapi.client_base_url', 'https://metaapi-client.test');
        config()->set('services.metaapi.account_type', 'cloud-g2');
        config()->set('services.metaapi.account_reliability', 'regular');
        config()->set('services.metaapi.demo.accepted_retries', 0);
        config()->set('services.metaapi.onboarding.billing_block_retry_minutes', 60);
    }

    public function test_assignment_registers_regular_metaapi_account_and_deploys_after_pool_row_is_bound(): void
    {
        $metaApiAccountId = '11111111-2222-4333-8444-555555555555';
        $account = $this->tradingAccount(5000);
        $entry = $this->metaQuotesPoolEntry('107990010', 5000);

        Http::fake(function ($request) use ($metaApiAccountId) {
            if ($request->method() === 'POST' && $request->url() === 'https://metaapi-provisioning.test/users/current/accounts') {
                return Http::response([
                    'id' => $metaApiAccountId,
                    'state' => 'UNDEPLOYED',
                ], 201);
            }

            if ($request->method() === 'GET' && $request->url() === "https://metaapi-provisioning.test/users/current/accounts/{$metaApiAccountId}") {
                return Http::response([
                    '_id' => $metaApiAccountId,
                    'login' => '107990010',
                    'server' => 'MetaQuotes-Demo',
                    'state' => 'UNDEPLOYED',
                    'connectionStatus' => 'DISCONNECTED',
                ], 200);
            }

            if ($request->method() === 'POST' && $request->url() === "https://metaapi-provisioning.test/users/current/accounts/{$metaApiAccountId}/deploy") {
                return Http::response('', 204);
            }

            return Http::response([
                'message' => 'Unexpected MetaApi request: '.$request->method().' '.$request->url(),
            ], 500);
        });

        $result = app(MetaQuotesPoolProvisioningService::class)->provisionForAccount($account, [
            'server' => 'MetaQuotes-Demo',
            'source_pool' => 'metaquotes_demo_pool',
            'source_file' => 'metaquotes_demo_pool',
            'broker' => 'MetaQuotes',
            'platform' => 'MT5',
        ]);

        $account->refresh();
        $entry->refresh();

        $this->assertSame('assigned', $result['status']);
        $this->assertSame('deployed', data_get($result, 'metaapi_assignment.status'), json_encode($result['metaapi_assignment'] ?? [], JSON_PRETTY_PRINT));
        $this->assertSame($account->id, $entry->allocated_trading_account_id);
        $this->assertSame($metaApiAccountId, data_get($entry->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'mt5_sync.metaapi_account_id'));
        $this->assertSame('deployed', data_get($entry->meta, 'metaapi_workflow.status'));
        $this->assertSame('deployed', data_get($account->meta, 'metaapi_workflow.status'));
        $this->assertSame('metaapi', $account->sync_source);

        $createAccountRequest = collect(Http::recorded())
            ->first(fn (array $record): bool => $record[0]->method() === 'POST'
                && $record[0]->url() === 'https://metaapi-provisioning.test/users/current/accounts');

        $this->assertSame('regular', $createAccountRequest[0]->data()['reliability'] ?? null);
    }

    public function test_billing_block_prevents_repeated_metaapi_deploy_attempts(): void
    {
        $account = $this->tradingAccount(5000);
        $entry = $this->metaQuotesPoolEntry('107990011', 5000, [
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $account->user_id,
            'allocated_at' => now(),
            'is_available' => false,
            'source_status' => 'assigned',
            'meta' => [
                'broker' => 'MetaQuotes',
                'provider' => 'MetaQuotes',
                'platform' => 'MT5',
                'metaapi_account_id' => '22222222-2222-4222-8222-222222222222',
                'metaapi_workflow' => [
                    'status' => 'metaapi_billing_blocked',
                    'stage' => 'deploy',
                    'blocked_until' => now()->addHour()->toIso8601String(),
                ],
            ],
        ]);
        $account->forceFill([
            'platform_login' => $entry->login,
            'platform_account_id' => $entry->login,
            'platform_environment' => $entry->server,
            'meta' => [
                'metaapi_account_id' => '22222222-2222-4222-8222-222222222222',
                'mt5_pool_entry' => [
                    'id' => $entry->id,
                    'login' => $entry->login,
                ],
                'metaapi_workflow' => [
                    'status' => 'metaapi_billing_blocked',
                    'stage' => 'deploy',
                    'blocked_until' => now()->addHour()->toIso8601String(),
                ],
            ],
        ])->save();

        Http::fake();

        $result = app(MetaQuotesAssignmentMetaApiService::class)->ensureReadyForAssignedAccount($account, $entry);

        $this->assertSame('metaapi_billing_blocked', $result['status']);
        $this->assertTrue((bool) ($result['skipped'] ?? false));
        Http::assertNothingSent();
    }

    public function test_fusionmarkets_assignment_does_not_trigger_metaquotes_metaapi_workflow(): void
    {
        $account = $this->tradingAccount(10000);
        $entry = Mt5AccountPoolEntry::factory()->create([
            'login' => '340190',
            'password' => 'main-secret',
            'investor_password' => 'investor-secret',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        Http::fake();

        $result = app(MetaQuotesPoolProvisioningService::class)->provisionForAccount($account, [
            'server' => 'FusionMarkets-Demo',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
            'platform' => 'MT5',
        ]);

        $account->refresh();
        $entry->refresh();

        $this->assertSame('assigned', $result['status']);
        $this->assertSame('skipped_non_metaquotes', data_get($result, 'metaapi_assignment.status'));
        $this->assertSame($account->id, $entry->allocated_trading_account_id);
        $this->assertNull(data_get($entry->meta, 'metaapi_workflow.status'));
        $this->assertNull(data_get($account->meta, 'metaapi_workflow.status'));
        Http::assertNothingSent();
    }

    private function tradingAccount(int $accountSize): TradingAccount
    {
        $user = User::factory()->create();

        return TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-MT5-TEST-'.fake()->unique()->numerify('####'),
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_environment' => 'MetaQuotes-Demo',
            'platform_status' => 'waiting_for_first_sync',
            'account_type' => 'challenge',
            'challenge_type' => 'one_step',
            'account_size' => $accountSize,
            'starting_balance' => $accountSize,
            'phase_starting_balance' => $accountSize,
            'phase_reference_balance' => $accountSize,
            'balance' => $accountSize,
            'equity' => $accountSize,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
            'sync_status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function metaQuotesPoolEntry(string $login, int $accountSize, array $overrides = []): Mt5AccountPoolEntry
    {
        return Mt5AccountPoolEntry::factory()->create(array_replace_recursive([
            'login' => $login,
            'password' => 'main-secret',
            'investor_password' => 'investor-secret',
            'server' => 'MetaQuotes-Demo',
            'account_size' => $accountSize,
            'source_file' => 'metaquotes_demo_pool',
            'source_pool' => 'metaquotes_demo_pool',
            'source_status' => 'available',
            'is_available' => true,
            'allocated_at' => null,
            'allocated_trading_account_id' => null,
            'meta' => [
                'broker' => 'MetaQuotes',
                'provider' => 'MetaQuotes',
                'platform' => 'MT5',
            ],
        ], $overrides));
    }
}
