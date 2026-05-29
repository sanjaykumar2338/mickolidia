<?php

namespace Tests\Feature;

use App\Models\TradingAccount;
use App\Models\User;
use App\Support\Mt5ConnectorStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Mt5ConnectorStaleStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-09 12:00:00'));
        config([
            'trading.platforms.mt5.freshness.stale_seconds' => 300,
            'trading.platforms.mt5.freshness.heartbeat_seconds' => 90,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_trial_dashboard_and_setup_show_stale_status_with_last_sync_visible(): void
    {
        $user = User::factory()->create();
        $account = $this->createMt5Account($user, isTrial: true);

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('Disconnected/Stale')
            ->assertSee('Sync stale/offline. Please keep MT5 Desktop or the MetaApi cloud terminal connected for this account.')
            ->assertSee('Last sync:');

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.setup'))
            ->assertOk()
            ->assertSee($account->account_reference)
            ->assertSee('Disconnected/Stale')
            ->assertSee('Last sync:');
    }

    public function test_paid_dashboard_uses_stale_connector_status_instead_of_stored_connected_flag(): void
    {
        $user = User::factory()->create();
        $this->createMt5Account($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Disconnected/Stale')
            ->assertSee('Sync stale/offline. Please keep MT5 Desktop or the MetaApi cloud terminal connected for this account.');

        $this->actingAs($user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('Disconnected/Stale')
            ->assertSee('Last synced');
    }

    public function test_recent_ea_ping_keeps_dashboard_connected_when_metrics_are_stale(): void
    {
        $user = User::factory()->create();
        $account = $this->createMt5Account($user);
        $meta = $account->meta;
        data_set($meta, 'mt5_sync.last_ea_ping_at', now()->subMinute()->toIso8601String());

        $account->forceFill(['meta' => $meta])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Connected')
            ->assertDontSee('Sync stale/offline. Please keep MT5 Desktop or the MetaApi cloud terminal connected for this account.');
    }

    public function test_dashboard_goes_stale_when_ea_ping_expires_even_if_metric_sync_is_recent(): void
    {
        $user = User::factory()->create();
        $account = $this->createMt5Account($user);
        $lastSyncAt = now()->subMinute();
        $meta = $account->meta;
        data_set($meta, 'mt5_sync.last_successful_metric_update_at', $lastSyncAt->toIso8601String());
        data_set($meta, 'mt5_sync.last_synced_at', $lastSyncAt->toIso8601String());
        data_set($meta, 'mt5_sync.last_ea_ping_at', now()->subMinutes(3)->toIso8601String());

        $account->forceFill([
            'last_synced_at' => $lastSyncAt,
            'meta' => $meta,
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Disconnected/Stale')
            ->assertSee('Sync stale/offline. Please keep MT5 Desktop or the MetaApi cloud terminal connected for this account.');
    }

    public function test_successful_metrics_sync_immediately_clears_stale_connector_warning(): void
    {
        $user = User::factory()->create();
        $account = $this->createMt5Account($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Disconnected/Stale');

        $this->withHeaders([
            'Authorization' => 'Bearer '.data_get($account->meta, 'mt5_connector.secret_token'),
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => 10000,
            'equity' => 10000,
            'timestamp' => now()->toDateTimeString(),
            'server_day' => now()->toDateString(),
            'platform_login' => $account->platform_login,
            'platform_status' => 'connected',
            'trade_count' => 0,
        ])->assertOk();

        $account->refresh();

        $this->assertSame('connected', data_get($account->meta, 'mt5_sync.status'));
        $this->assertNull(data_get($account->meta, 'mt5_sync.last_ignored_reason'));
        $this->assertNotNull(data_get($account->meta, 'mt5_sync.last_successful_metric_update_at'));
        $connectorStatus = app(Mt5ConnectorStatus::class)->forAccount($account);
        $this->assertSame(Mt5ConnectorStatus::CONNECTED, $connectorStatus['status']);
        $this->assertNull($connectorStatus['message']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Connected');
    }

    public function test_admin_diagnostics_show_computed_stale_status_and_stored_flag_separately(): void
    {
        $user = User::factory()->create();
        $this->createMt5Account($user);

        $this->withSession([
            'admin.authenticated' => true,
            'admin.username' => 'admin',
        ])
            ->get(route('admin.clients.show', $user))
            ->assertOk()
            ->assertSee('Sync status')
            ->assertSee('Disconnected/Stale')
            ->assertSee('Stored sync flag')
            ->assertSee('connected')
            ->assertSee('Sync stale/offline. Please keep MT5 Desktop or the MetaApi cloud terminal connected for this account.');
    }

    private function createMt5Account(User $user, bool $isTrial = false): TradingAccount
    {
        $lastSyncAt = now()->subMinutes(6);

        return TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => $isTrial ? 'WFX-TRIAL-STALE' : 'WFX-MT5-STALE',
            'platform' => $isTrial ? 'MT5 Demo' : 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $isTrial ? '991100' : '991101',
            'platform_account_id' => $isTrial ? '991100' : '991101',
            'platform_status' => 'connected',
            'account_type' => $isTrial ? 'trial' : 'challenge',
            'is_trial' => $isTrial,
            'trial_status' => $isTrial ? 'active' : null,
            'trial_started_at' => $isTrial ? now()->subDay() : null,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'profit_target_percent' => 10,
            'profit_target_amount' => 1000,
            'daily_drawdown_limit_amount' => 500,
            'max_drawdown_limit_amount' => 1000,
            'minimum_trading_days' => 3,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'active',
            'sync_status' => 'success',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => $lastSyncAt,
            'last_sync_completed_at' => $lastSyncAt,
            'meta' => [
                'mt5_sync' => [
                    'status' => 'connected',
                    'last_successful_metric_update_at' => $lastSyncAt->toIso8601String(),
                    'last_synced_at' => $lastSyncAt->toIso8601String(),
                ],
                'mt5_connector' => [
                    'secret_token' => 'status-test-token',
                ],
            ],
        ]);
    }
}
