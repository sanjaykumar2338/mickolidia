<?php

namespace Tests\Feature;

use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminClientMetricsTest extends TestCase
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

    public function test_admin_can_view_trader_metrics(): void
    {
        [$user] = $this->createTraderWithTrades();

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('Trader Metrics')
            ->assertSee('WFX-ADMIN-METRICS')
            ->assertSee('metrics-trader@example.com')
            ->assertSee('$10,250.00')
            ->assertSee('$10,300.00')
            ->assertSee('$50.00')
            ->assertSee('$250.00')
            ->assertSee('2 / 3')
            ->assertSee('Connected')
            ->assertSee('Ignored reason')
            ->assertSee('stale_timestamp')
            ->assertSee('EA disable status')
            ->assertSee('Disable Pending Ack');
    }

    public function test_trade_rows_display_correctly(): void
    {
        [$user] = $this->createTraderWithTrades();

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('OPEN-1')
            ->assertSee('CLOSED-1')
            ->assertSee('EURUSD')
            ->assertSee('XAUUSD')
            ->assertSee('buy')
            ->assertSee('sell')
            ->assertSee('1.00')
            ->assertSee('1.10000')
            ->assertSee('1.09000')
            ->assertSee('1.12000')
            ->assertSee('-$3.50')
            ->assertSee('$120.00')
            ->assertSee('open')
            ->assertSee('closed');
    }

    public function test_trade_filters_work(): void
    {
        [$user] = $this->createTraderWithTrades();

        $this->adminGet(route('admin.clients.metrics', [
            'user' => $user,
            'trade_status' => 'closed',
            'symbol' => 'XAUUSD',
            'date_from' => '2026-05-08',
            'date_to' => '2026-05-08',
        ]))
            ->assertOk()
            ->assertSee('CLOSED-1')
            ->assertSee('XAUUSD')
            ->assertDontSee('OPEN-1');
    }

    public function test_trade_history_is_paginated(): void
    {
        [$user, $account] = $this->createTraderWithTrades();
        $rows = [];

        for ($i = 1; $i <= 30; $i++) {
            $rows[] = [
                'deal_id' => 'PAGE-'.$i,
                'symbol' => 'GBPUSD',
                'type' => 'buy',
                'volume' => 0.10,
                'open_price' => 1.2000,
                'close_price' => 1.2010,
                'open_time' => Carbon::parse('2026-05-01 08:00:00')->addMinutes($i)->toIso8601String(),
                'close_time' => Carbon::parse('2026-05-01 09:00:00')->addMinutes($i)->toIso8601String(),
                'profit' => $i,
            ];
        }

        $account->balanceSnapshots()->create([
            'snapshot_at' => now(),
            'balance' => 10250,
            'equity' => 10300,
            'profit_loss' => 50,
            'total_profit' => 250,
            'today_profit' => 75,
            'payload' => [
                'open_positions' => [],
                'trade_history' => $rows,
            ],
        ]);

        $this->adminGet(route('admin.clients.metrics', [
            'user' => $user,
            'per_page' => 10,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('PAGE-21')
            ->assertSee('PAGE-12')
            ->assertDontSee('PAGE-30')
            ->assertDontSee('PAGE-5');
    }

    public function test_unauthorized_users_cannot_access_admin_metrics_page(): void
    {
        [$user] = $this->createTraderWithTrades();

        $this->get(route('admin.clients.metrics', $user))
            ->assertRedirect(route('admin.login'));
    }

    private function adminGet(string $url)
    {
        return $this->withSession([
            'admin.authenticated' => true,
            'admin.username' => 'admin',
        ])->get($url);
    }

    /**
     * @return array{0:User,1:TradingAccount}
     */
    private function createTraderWithTrades(): array
    {
        $user = User::factory()->create([
            'name' => 'Metrics Trader',
            'email' => 'metrics-trader@example.com',
        ]);

        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-ADMIN-METRICS',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'account_type' => 'challenge',
            'is_trial' => false,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'balance' => 10250,
            'equity' => 10300,
            'profit_loss' => 50,
            'today_profit' => 75,
            'total_profit' => 250,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 2,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'active',
            'sync_status' => 'success',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => now()->subMinute(),
            'last_sync_completed_at' => now()->subMinute(),
            'meta' => [
                'mt5_sync' => [
                    'status' => 'connected',
                    'last_ea_ping_at' => now()->subSeconds(20)->toIso8601String(),
                    'last_successful_metric_update_at' => now()->subMinute()->toIso8601String(),
                    'last_ignored_reason' => 'stale_timestamp',
                    'last_payload_summary' => [
                        'balance' => 10250,
                        'equity' => 10300,
                        'positions_count' => 1,
                        'closed_positions_count' => 1,
                    ],
                ],
                'mt5_deactivation' => [
                    'current' => [
                        'event' => 'fail_daily_loss_breached',
                        'status' => 'disable_pending_ack',
                    ],
                ],
            ],
        ]);

        $account->balanceSnapshots()->create([
            'snapshot_at' => now(),
            'balance' => 10250,
            'equity' => 10300,
            'profit_loss' => 50,
            'total_profit' => 250,
            'today_profit' => 75,
            'payload' => [
                'open_positions' => [[
                    'position_id' => 'OPEN-1',
                    'symbol' => 'EURUSD',
                    'type' => 'buy',
                    'volume' => 1.0,
                    'open_price' => 1.1000,
                    'stop_loss' => 1.0900,
                    'take_profit' => 1.1200,
                    'open_time' => '2026-05-09 09:00:00',
                    'profit' => 25,
                    'commission' => -3.5,
                    'swap' => -0.2,
                ]],
                'trade_history' => [[
                    'deal_id' => 'CLOSED-1',
                    'symbol' => 'XAUUSD',
                    'type' => 'sell',
                    'volume' => 0.5,
                    'open_price' => 2350.10,
                    'close_price' => 2340.10,
                    'stop_loss' => 2360.00,
                    'take_profit' => 2330.00,
                    'open_time' => '2026-05-08 08:00:00',
                    'close_time' => '2026-05-08 10:00:00',
                    'profit' => 120,
                    'commission' => -4,
                    'swap' => -1,
                ]],
            ],
        ]);

        TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => 'mt5',
            'status' => 'success',
            'message' => 'MT5 metrics applied successfully.',
            'completed_at' => now()->subMinute(),
        ]);

        return [$user, $account];
    }
}
