<?php

namespace Tests\Feature;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Mt5\Mt5AccountAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetaQuotesDemoValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('services.metaapi.enabled', true);
        config()->set('services.metaapi.token', 'test-token');
        config()->set('services.metaapi.provisioning_base_url', 'https://metaapi-provisioning.test');
        config()->set('services.metaapi.client_base_url', 'https://metaapi-client.test');
        config()->set('services.metaapi.validation.poll_delay_seconds', 0);
        config()->set('services.metaapi.validation.throttle_delay_ms', 0);
    }

    public function test_metaquotes_validation_command_writes_dry_run_report(): void
    {
        $this->artisan('metaquotes:validate-demo')
            ->expectsOutputToContain('Phase 1A MetaQuotes Demo Validation')
            ->expectsOutputToContain('Dry-run complete.')
            ->assertSuccessful();

        $this->assertNotEmpty(Storage::disk('local')->files('diagnostics'));
    }

    public function test_live_existing_login_registers_in_metaapi_polls_state_and_stores_pool_entry(): void
    {
        $accountId = '1eda642a-a9a3-457c-99af-3bc5e8d5c4c9';

        Http::fake([
            'https://metaapi-provisioning.test/users/current/accounts' => Http::response([
                'id' => $accountId,
                'state' => 'DEPLOYED',
            ], 201),
            "https://metaapi-provisioning.test/users/current/accounts/{$accountId}" => Http::response([
                '_id' => $accountId,
                'login' => '770001',
                'server' => 'MetaQuotes-Demo',
                'state' => 'DEPLOYED',
                'connectionStatus' => 'CONNECTED',
            ], 200),
            "https://metaapi-provisioning.test/users/current/accounts/{$accountId}/deploy" => Http::response('', 204),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/account-information*" => Http::response([
                'broker' => 'MetaQuotes',
                'server' => 'MetaQuotes-Demo',
                'balance' => 10000,
                'equity' => 10025,
                'login' => 770001,
                'tradeAllowed' => true,
            ], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/positions*" => Http::response([
                [
                    'id' => 'position-1',
                    'symbol' => 'EURUSD',
                    'profit' => 25,
                ],
            ], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/history-orders/time/*" => Http::response([], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/history-deals/time/*" => Http::response([], 200),
        ]);

        $this->artisan('metaquotes:validate-demo', [
            '--live' => true,
            '--login' => ['770001'],
            '--password' => ['main-secret'],
            '--investor-password' => ['investor-secret'],
            '--server' => 'MetaQuotes-Demo',
            '--store-pool' => true,
            '--polls' => 1,
            '--history-days' => 1,
        ])->assertSuccessful();

        $entry = Mt5AccountPoolEntry::query()
            ->where('login', '770001')
            ->where('server', 'MetaQuotes-Demo')
            ->firstOrFail();

        $this->assertSame('main-secret', $entry->password);
        $this->assertSame('investor-secret', $entry->investor_password);
        $this->assertSame('MetaQuotes', data_get($entry->meta, 'broker'));
        $this->assertSame('MT5', data_get($entry->meta, 'platform'));
        $this->assertSame($accountId, data_get($entry->meta, 'metaapi_account_id'));

        $this->assertNotEmpty(Storage::disk('local')->files('diagnostics'));
    }

    public function test_allocator_can_assign_metaquotes_pool_entries_by_source_and_broker(): void
    {
        $user = User::factory()->create();
        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-MT5-MQ-0001',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'account_type' => 'challenge',
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'account_status' => 'pending_activation',
            'challenge_status' => 'pending_activation',
            'status' => 'Pending Activation',
        ]);

        Mt5AccountPoolEntry::factory()->create([
            'login' => '770002',
            'password' => 'main-secret',
            'investor_password' => 'investor-secret',
            'server' => 'MetaQuotes-Demo',
            'account_size' => 10000,
            'source_file' => 'metaapi-demo-validation',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'meta' => [
                'broker' => 'MetaQuotes',
                'provider' => 'MetaQuotes',
                'platform' => 'MT5',
            ],
        ]);

        $entry = app(Mt5AccountAllocator::class)->allocate($account, [
            'source_file' => 'metaapi-demo-validation',
            'broker' => 'MetaQuotes',
            'platform' => 'MT5',
        ]);

        $this->assertInstanceOf(Mt5AccountPoolEntry::class, $entry);
        $this->assertSame('770002', $entry->login);
        $this->assertSame($account->id, $entry->fresh()->allocated_trading_account_id);

        $account->refresh();

        $this->assertSame('770002', $account->platform_login);
        $this->assertSame('MetaQuotes-Demo', $account->platform_environment);
        $this->assertSame('main-secret', data_get($account->meta, 'credentials.password'));
        $this->assertSame('investor-secret', data_get($account->meta, 'credentials.investor_password'));
    }

    public function test_create_validation_error_id_is_not_treated_as_metaapi_account_id(): void
    {
        Http::fake([
            'https://metaapi-provisioning.test/users/current/accounts' => Http::response([
                'id' => 46084,
                'error' => 'ValidationError',
                'message' => 'Server file not found',
            ], 400),
            'https://metaapi-provisioning.test/users/current/accounts?query=770003' => Http::response([], 200),
        ]);

        $this->artisan('metaquotes:validate-demo', [
            '--live' => true,
            '--login' => ['770003'],
            '--password' => ['main-secret'],
            '--investor-password' => ['investor-secret'],
            '--server' => 'Fusion Markets Pty - FusionMarkets Demo',
            '--store-pool' => true,
            '--debug-metaapi' => true,
        ])->assertSuccessful();

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/accounts/46084/deploy'));

        $entry = Mt5AccountPoolEntry::query()
            ->where('login', '770003')
            ->firstOrFail();

        $this->assertNull(data_get($entry->meta, 'metaapi_account_id'));

        $report = $this->latestDiagnosticReport();

        $this->assertSame(400, data_get($report, 'accounts.0.create_account.status'));
        $this->assertSame('validation_error_not_a_confirmed_account_id', data_get($report, 'accounts.0.create_account.reason'));
        $this->assertNull(data_get($report, 'accounts.0.metaapi_account_id'));
        $this->assertStringNotContainsString('main-secret', json_encode($report));
        $this->assertStringNotContainsString('investor-secret', json_encode($report));
    }

    public function test_create_validation_error_can_reuse_confirmed_existing_account_from_lookup(): void
    {
        $accountId = '1eda642a-a9a3-457c-99af-3bc5e8d5c4c9';

        Http::fake([
            'https://metaapi-provisioning.test/users/current/accounts' => Http::response([
                'id' => 55773,
                'error' => 'ValidationError',
                'message' => 'Trading account already exists',
            ], 400),
            'https://metaapi-provisioning.test/users/current/accounts?query=770004' => Http::response([
                [
                    '_id' => $accountId,
                    'login' => '770004',
                    'server' => 'Fusion Markets Pty - FusionMarkets Demo',
                    'state' => 'UNDEPLOYED',
                    'connectionStatus' => 'DISCONNECTED',
                ],
            ], 200),
            "https://metaapi-provisioning.test/users/current/accounts/{$accountId}" => Http::response([
                '_id' => $accountId,
                'login' => '770004',
                'server' => 'Fusion Markets Pty - FusionMarkets Demo',
                'state' => 'DEPLOYED',
                'connectionStatus' => 'CONNECTED',
            ], 200),
            "https://metaapi-provisioning.test/users/current/accounts/{$accountId}/deploy" => Http::response('', 204),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/account-information*" => Http::response([
                'balance' => 10000,
                'equity' => 10000,
                'login' => 770004,
            ], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/positions*" => Http::response([], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/history-orders/time/*" => Http::response([], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/history-deals/time/*" => Http::response([], 200),
        ]);

        $this->artisan('metaquotes:validate-demo', [
            '--live' => true,
            '--login' => ['770004'],
            '--password' => ['main-secret'],
            '--investor-password' => ['investor-secret'],
            '--server' => 'Fusion Markets Pty - FusionMarkets Demo',
            '--store-pool' => true,
            '--polls' => 1,
            '--debug-metaapi' => true,
        ])->assertSuccessful();

        $entry = Mt5AccountPoolEntry::query()
            ->where('login', '770004')
            ->firstOrFail();

        $this->assertSame($accountId, data_get($entry->meta, 'metaapi_account_id'));

        $report = $this->latestDiagnosticReport();

        $this->assertSame($accountId, data_get($report, 'accounts.0.metaapi_account_id'));
        $this->assertSame('exact_login_server_match', data_get($report, 'accounts.0.read_existing_accounts.reason'));
        $this->assertSame(204, data_get($report, 'accounts.0.deploy.status'));
    }

    public function test_manual_metaapi_account_id_skips_create_and_uses_dashboard_id(): void
    {
        $accountId = '865d3a4d-3803-486d-bdf3-a85679d9fad2';

        Http::fake([
            "https://metaapi-provisioning.test/users/current/accounts/{$accountId}" => Http::response([
                '_id' => $accountId,
                'login' => '770005',
                'server' => 'FusionMarkets-Demo',
                'state' => 'DEPLOYED',
                'connectionStatus' => 'CONNECTED',
            ], 200),
            "https://metaapi-provisioning.test/users/current/accounts/{$accountId}/deploy" => Http::response('', 204),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/account-information*" => Http::response([
                'balance' => 10000,
                'equity' => 10005,
                'login' => 770005,
            ], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/positions*" => Http::response([], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/history-orders/time/*" => Http::response([], 200),
            "https://metaapi-client.test/users/current/accounts/{$accountId}/history-deals/time/*" => Http::response([], 200),
        ]);

        $this->artisan('metaquotes:validate-demo', [
            '--live' => true,
            '--login' => ['770005'],
            '--password' => ['main-secret'],
            '--server' => 'FusionMarkets-Demo',
            '--metaapi-account-id' => $accountId,
            '--polls' => 1,
        ])->assertSuccessful();

        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://metaapi-provisioning.test/users/current/accounts');

        $report = $this->latestDiagnosticReport();

        $this->assertSame('skipped_manual_metaapi_account_id', data_get($report, 'accounts.0.create_account.status'));
        $this->assertSame($accountId, data_get($report, 'accounts.0.metaapi_account_id'));
        $this->assertSame('CONNECTED', data_get($report, 'accounts.0.polls.0.provisioning_account.connection_status'));
    }

    /**
     * @return array<string, mixed>
     */
    private function latestDiagnosticReport(): array
    {
        $path = collect(Storage::disk('local')->files('diagnostics'))
            ->sort()
            ->last();

        $this->assertNotNull($path);

        return json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
