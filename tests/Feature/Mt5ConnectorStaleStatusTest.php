<?php

namespace Tests\Feature;

use App\Models\TradingAccount;
use App\Models\User;
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
        config(['trading.platforms.mt5.freshness.stale_seconds' => 300]);
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
            ->assertSee('Connector stale/offline. Please keep MT5 Desktop open with the Wolforix EA attached to an active chart.')
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
            ->assertSee('Connector stale/offline. Please keep MT5 Desktop open with the Wolforix EA attached to an active chart.');

        $this->actingAs($user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('Disconnected/Stale')
            ->assertSee('Last synced');
    }

    public function test_recent_ea_ping_does_not_keep_dashboard_connected_when_metrics_are_stale(): void
    {
        $user = User::factory()->create();
        $account = $this->createMt5Account($user);
        $meta = $account->meta;
        data_set($meta, 'mt5_sync.last_ea_ping_at', now()->subMinute()->toIso8601String());

        $account->forceFill(['meta' => $meta])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Disconnected/Stale')
            ->assertSee('Connector stale/offline. Please keep MT5 Desktop open with the Wolforix EA attached to an active chart.')
            ->assertDontSee('Connector status</dt> <dd class="font-semibold text-white">Connected', false);
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
            ->assertSee('Connector status')
            ->assertSee('Disconnected/Stale')
            ->assertSee('Stored connector flag')
            ->assertSee('connected')
            ->assertSee('Connector stale/offline. Please keep MT5 Desktop open with the Wolforix EA attached to an active chart.');
    }

    private function createMt5Account(User $user, bool $isTrial = false): TradingAccount
    {
        $lastSyncAt = now()->subMinutes(6);

        return TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => $isTrial ? 'WFX-TRIAL-STALE' : 'WFX-MT5-STALE',
            'platform' => $isTrial ? 'MT5 Demo' : 'MT5',
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'account_type' => $isTrial ? 'trial' : 'challenge',
            'is_trial' => $isTrial,
            'trial_status' => $isTrial ? 'active' : null,
            'trial_started_at' => $isTrial ? now()->subDay() : null,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'profit_target_percent' => 10,
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
            ],
        ]);
    }
}
