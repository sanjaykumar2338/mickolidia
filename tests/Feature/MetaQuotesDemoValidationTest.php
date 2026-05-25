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
        Http::fake([
            'https://metaapi-provisioning.test/users/current/accounts' => Http::response([
                'id' => 'meta-account-1',
                'state' => 'DEPLOYED',
            ], 201),
            'https://metaapi-provisioning.test/users/current/accounts/meta-account-1/deploy' => Http::response('', 204),
            'https://metaapi-client.test/users/current/accounts/meta-account-1/account-information*' => Http::response([
                'broker' => 'MetaQuotes',
                'server' => 'MetaQuotes-Demo',
                'balance' => 10000,
                'equity' => 10025,
                'login' => 770001,
                'tradeAllowed' => true,
            ], 200),
            'https://metaapi-client.test/users/current/accounts/meta-account-1/positions*' => Http::response([
                [
                    'id' => 'position-1',
                    'symbol' => 'EURUSD',
                    'profit' => 25,
                ],
            ], 200),
            'https://metaapi-client.test/users/current/accounts/meta-account-1/history-orders/time/*' => Http::response([], 200),
            'https://metaapi-client.test/users/current/accounts/meta-account-1/history-deals/time/*' => Http::response([], 200),
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
        $this->assertSame('meta-account-1', data_get($entry->meta, 'metaapi_account_id'));

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
}
