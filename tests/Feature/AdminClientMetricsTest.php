<?php

namespace Tests\Feature;

use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Models\User;
use App\Services\TradingAccounts\TradeHistoryPanelBuilder;
use App\Support\ChallengeCalculationBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
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
            ->assertSee('Today Summary')
            ->assertSee('Today Closed P/L')
            ->assertSee('Today closed trades count')
            ->assertSee('Today open trades count')
            ->assertSee('Current floating PnL')
            ->assertSee('Last synced at')
            ->assertSee('Connected')
            ->assertSee('Ignored reason')
            ->assertSee('stale_timestamp')
            ->assertSee('MT5 disable status')
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
            ->assertSee('May 09, 2026 09:00')
            ->assertSee('Ongoing')
            ->assertSee('May 08, 2026 08:00')
            ->assertSee('May 08, 2026 10:00')
            ->assertSee('-$3.50')
            ->assertSee('$120.00')
            ->assertSee('Realized P/L')
            ->assertSee('Floating P/L')
            ->assertSee('open')
            ->assertSee('closed');
    }

    public function test_metaapi_deal_execution_time_maps_to_closed_trade_close_time(): void
    {
        [, $account] = $this->createTraderWithTrades();
        $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();

        $snapshot->forceFill([
            'payload' => [
                'open_positions' => [[
                    'position_id' => 'OPEN-TIME-1',
                    'symbol' => 'EURUSD',
                    'type' => 'POSITION_TYPE_BUY',
                    'volume' => 1,
                    'time' => '2026-05-09T09:15:00Z',
                    'profit' => 12.5,
                    'commission' => -0.50,
                    'swap' => 0,
                ]],
                'history_deals' => [[
                    'id' => 'DEAL-TIME-1',
                    'symbol' => 'GBPUSD',
                    'type' => 'DEAL_TYPE_SELL',
                    'volume' => 0.5,
                    'time' => '2026-05-09T10:45:00Z',
                    'profit' => 36,
                    'commission' => -2,
                    'swap' => -1,
                ]],
            ],
        ])->save();

        $panel = app(TradeHistoryPanelBuilder::class)->build($account);
        $openRow = collect($panel['rows'])->firstWhere('id', 'OPEN-TIME-1');
        $closedRow = collect($panel['rows'])->firstWhere('id', 'DEAL-TIME-1');

        $this->assertSame('May 09, 2026 09:15', $openRow['open_date']);
        $this->assertSame('Ongoing', $openRow['close_date']);
        $this->assertSame('$12.50', $openRow['profit']);
        $this->assertSame('—', $closedRow['open_date']);
        $this->assertSame('May 09, 2026 10:45', $closedRow['close_date']);
        $this->assertSame('$36.00', $closedRow['profit']);
        $this->assertSame('-$2.00', $closedRow['commission']);
        $this->assertSame('-$1.00', $closedRow['swap']);
        $this->assertSame('$33.00', $closedRow['net_result']);
    }

    public function test_metaapi_history_uses_broker_close_time_and_ignores_opening_deals(): void
    {
        [, $account] = $this->createTraderWithTrades();
        $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();

        $snapshot->forceFill([
            'payload' => [
                'history_deals' => [
                    [
                        'id' => 'DEAL-OPEN-IGNORED',
                        'symbol' => 'EURUSD',
                        'type' => 'DEAL_TYPE_BUY',
                        'entryType' => 'DEAL_ENTRY_IN',
                        'volume' => 0.5,
                        'time' => '2026-05-09T07:00:00.000Z',
                        'brokerTime' => '2026-05-09 10:00:00.000',
                        'profit' => 0,
                    ],
                    [
                        'id' => 'DEAL-CLOSE-BROKER',
                        'symbol' => 'EURUSD',
                        'type' => 'DEAL_TYPE_SELL',
                        'entryType' => 'DEAL_ENTRY_OUT',
                        'volume' => 0.5,
                        'time' => '2026-05-09T10:45:00.000Z',
                        'brokerTime' => '2026-05-09 13:45:00.000',
                        'profit' => 48,
                        'commission' => -2,
                        'swap' => -0.5,
                    ],
                ],
                'history_orders' => [[
                    'id' => 'ORDER-DONE-TIME',
                    'symbol' => 'GBPUSD',
                    'type' => 'ORDER_TYPE_BUY',
                    'state' => 'ORDER_STATE_FILLED',
                    'volume' => 1,
                    'time' => '2026-05-09T08:00:00.000Z',
                    'brokerTime' => '2026-05-09 11:00:00.000',
                    'doneTime' => '2026-05-09T09:30:00.000Z',
                    'doneBrokerTime' => '2026-05-09 12:30:00.000',
                    'profit' => 12,
                ]],
            ],
        ])->save();

        $panel = app(TradeHistoryPanelBuilder::class)->build($account);
        $rows = collect($panel['rows']);

        $this->assertNull($rows->firstWhere('id', 'DEAL-OPEN-IGNORED'));

        $deal = $rows->firstWhere('id', 'DEAL-CLOSE-BROKER');
        $this->assertSame('—', $deal['open_date']);
        $this->assertSame('May 09, 2026 13:45', $deal['close_date']);
        $this->assertSame('$45.50', $deal['net_result']);

        $order = $rows->firstWhere('id', 'ORDER-DONE-TIME');
        $this->assertSame('May 09, 2026 11:00', $order['open_date']);
        $this->assertSame('May 09, 2026 12:30', $order['close_date']);
    }

    public function test_daily_loss_explanation_clarifies_profitable_intraday_pullback(): void
    {
        [$user, $account] = $this->createTraderWithTrades();

        $account->forceFill([
            'balance' => 10400,
            'equity' => 10300,
            'highest_equity_today' => 10500,
            'daily_loss_used' => 200,
            'daily_drawdown' => 200,
            'total_profit' => 400,
            'rule_state' => [
                'highest_challenge_equity_today' => 10500,
                'challenge_balance' => 10400,
                'challenge_equity' => 10300,
                'rules' => [
                    'daily_drawdown_limit_amount' => 500,
                    'max_drawdown_limit_amount' => 1000,
                ],
            ],
        ])->save();

        $breakdown = app(ChallengeCalculationBreakdown::class)->forAccount($account->fresh());

        $this->assertSame(200.0, $breakdown['daily_loss_used']);
        $this->assertSame('max(today_highest_challenge_equity - current_challenge_equity, 0)', $breakdown['formula']['daily_loss_used']);

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('Daily loss from intraday high')
            ->assertSee('max(today_highest_challenge_equity - current_challenge_equity, 0)');
    }

    public function test_breach_closed_trade_rows_are_labeled_without_hiding_profit(): void
    {
        [$user, $account] = $this->createTraderWithTrades();
        $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();
        $payload = $snapshot->payload;
        $payload['trade_history'][0]['profit'] = -5;
        $payload['trade_history'][0]['auto_closed_by_breach'] = true;
        $payload['trade_history'][0]['close_reason'] = 'rule_breach';
        $payload['trade_history'][0]['close_source'] = 'wolforix_ea';

        $snapshot->forceFill([
            'payload' => $payload,
        ])->save();

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('CLOSED-1')
            ->assertSee('auto closed by breach')
            ->assertSee('-$5.00');
    }

    public function test_breach_closed_trade_rows_can_be_labeled_from_close_ack_references(): void
    {
        [$user, $account] = $this->createTraderWithTrades();
        $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();
        $payload = $snapshot->payload;
        $payload['trade_history'][0]['position_id'] = 'AUTO-CLOSED-POSITION-1';
        $payload['trade_history'][0]['profit'] = 5;

        $snapshot->forceFill([
            'payload' => $payload,
        ])->save();

        $meta = $account->meta;
        $meta['mt5_deactivation']['current']['closed_position_identifiers'] = ['AUTO-CLOSED-POSITION-1'];

        $account->forceFill([
            'meta' => $meta,
        ])->save();

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('auto closed by breach')
            ->assertSee('$5.00');
    }

    public function test_breach_closed_trade_rows_can_be_labeled_from_breach_close_window(): void
    {
        [$user, $account] = $this->createTraderWithTrades();
        $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();
        $payload = $snapshot->payload;
        $payload['trade_history'][0]['deal_id'] = 'WINDOW-CLOSED-1';
        $payload['trade_history'][0]['position_id'] = 'MT5-HISTORY-ID-DID-NOT-MATCH-CLOSE-ACK';
        $payload['trade_history'][0]['close_time'] = '2026-05-09 12:01:00';
        $payload['trade_history'][0]['profit'] = -15;

        $snapshot->forceFill([
            'snapshot_at' => '2026-05-09 12:01:30',
            'payload' => $payload,
        ])->save();

        $meta = $account->meta;
        $meta['mt5_deactivation']['current'] = array_merge($meta['mt5_deactivation']['current'], [
            'closed_positions_count' => 1,
            'requested_at' => '2026-05-09T12:00:05+00:00',
            'executed_at' => '2026-05-09T12:01:30+00:00',
        ]);

        $account->forceFill([
            'challenge_status' => 'failed',
            'failure_reason' => 'daily_loss_breached',
            'failed_at' => '2026-05-09 12:00:00',
            'failure_context' => [
                'breach_timestamp' => '2026-05-09T12:00:00+00:00',
                'rule_breached' => 'daily_loss_breached',
            ],
            'meta' => $meta,
        ])->save();

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('WINDOW-CLOSED-1')
            ->assertSee('auto closed by breach')
            ->assertSee('-$15.00');
    }

    public function test_today_pnl_calculates_from_closed_trades_when_payload_omits_today_profit(): void
    {
        [$user, $account] = $this->createTraderWithTrades();
        $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();
        $payload = $snapshot->payload;
        unset($payload['today_profit']);
        $payload['trade_history'] = [[
            'deal_id' => 'TODAY-CLOSED-1',
            'symbol' => 'EURUSD',
            'type' => 'buy',
            'volume' => 0.25,
            'open_price' => 1.1050,
            'close_price' => 1.1080,
            'open_time' => '2026-05-09 10:00:00',
            'close_time' => '2026-05-09 11:00:00',
            'profit' => 42,
            'commission' => -2,
            'swap' => -1,
        ]];

        $snapshot->forceFill([
            'today_profit' => 0,
            'payload' => $payload,
        ])->save();
        $account->forceFill(['today_profit' => 0])->save();

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('TODAY-CLOSED-1')
            ->assertSee('Calculated from today’s closed trades')
            ->assertSee('$39.00')
            ->assertSee('Today closed trades count')
            ->assertSee('1');
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

    public function test_today_trade_filter_uses_current_day_window(): void
    {
        [$user] = $this->createTraderWithTrades();

        $this->adminGet(route('admin.clients.metrics', [
            'user' => $user,
            'date_filter' => 'today',
        ]))
            ->assertOk()
            ->assertSee('OPEN-1')
            ->assertDontSee('CLOSED-1');
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

    public function test_stale_connector_warning_appears(): void
    {
        [$user, $account] = $this->createTraderWithTrades();
        $meta = $account->meta;
        data_set($meta, 'mt5_sync.last_ea_ping_at', now()->subMinutes(10)->toIso8601String());
        data_set($meta, 'mt5_sync.last_successful_metric_update_at', now()->subMinutes(10)->toIso8601String());

        $account->forceFill([
            'last_synced_at' => now()->subMinutes(10),
            'meta' => $meta,
        ])->save();

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('MT5 data may be outdated because the account has not synced recently.');
    }

    public function test_admin_metrics_separate_mt5_broker_balance_from_challenge_calculation(): void
    {
        [$user, $account] = $this->createTraderWithTrades();

        $account->forceFill([
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'balance' => 99412.37,
            'equity' => 99411.67,
            'profit_loss' => -0.70,
            'total_profit' => -587.63,
            'profit_target_percent' => 10,
            'profit_target_amount' => 1000,
            'daily_drawdown_limit_amount' => 400,
            'max_drawdown_limit_amount' => 800,
            'rule_state' => [
                'broker_phase_reference_balance' => 100000,
                'broker_reference_source' => 'test_reference',
                'highest_challenge_equity_today' => 10000,
                'rules' => [
                    'profit_target_percent' => 10,
                    'daily_drawdown_limit_amount' => 400,
                    'max_drawdown_limit_amount' => 800,
                ],
            ],
        ])->save();

        $account->balanceSnapshots()->create([
            'snapshot_at' => now()->addSecond(),
            'balance' => 99412.37,
            'equity' => 99411.67,
            'profit_loss' => -0.70,
            'total_profit' => -587.63,
            'today_profit' => 0,
            'payload' => [
                'starting_balance' => 10000,
                'broker_phase_reference_balance' => 100000,
                'balance' => 99412.37,
                'equity' => 99411.67,
                'open_positions' => [],
                'trade_history' => [],
            ],
        ]);

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('Challenge balance')
            ->assertSee('$9,412.37')
            ->assertSee('Raw MT5 Broker Balance')
            ->assertSee('$99,412.37')
            ->assertSee('Broker reference')
            ->assertSee('$100,000.00')
            ->assertSee('Profit target progress')
            ->assertSee('0.0%');
    }

    public function test_daily_loss_uses_intraday_high_water_equity_even_when_balance_is_profitable(): void
    {
        [$user, $account] = $this->createTraderWithTrades();

        $account->forceFill([
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'balance' => 10036,
            'equity' => 10000,
            'profit_loss' => -36,
            'total_profit' => 36,
            'daily_drawdown_limit_amount' => 500,
            'rule_state' => [
                'broker_phase_reference_balance' => 10000,
                'highest_challenge_equity_today' => 10540.06,
                'rules' => [
                    'daily_drawdown_limit_amount' => 500,
                    'max_drawdown_limit_amount' => 1000,
                ],
            ],
        ])->save();

        $snapshot = $account->balanceSnapshots()->create([
            'snapshot_at' => now()->addSecond(),
            'balance' => 10036,
            'equity' => 10000,
            'profit_loss' => -36,
            'total_profit' => 36,
            'today_profit' => 36,
            'payload' => [
                'broker_phase_reference_balance' => 10000,
                'balance' => 10036,
                'equity' => 10000,
                'open_positions' => [],
                'trade_history' => [],
            ],
        ]);

        $calculation = app(ChallengeCalculationBreakdown::class)->forAccount($account, $snapshot);

        $this->assertSame(36.0, $calculation['realized_profit']);
        $this->assertSame(10540.06, $calculation['highest_challenge_equity_today']);
        $this->assertSame(540.06, $calculation['daily_loss_used']);
        $this->assertTrue($calculation['daily_breach']);
        $this->assertSame(
            'max(today_highest_challenge_equity - current_challenge_equity, 0)',
            $calculation['formula']['daily_loss_used']
        );

        $this->adminGet(route('admin.clients.metrics', $user))
            ->assertOk()
            ->assertSee('Highest challenge equity today')
            ->assertSee('$10,540.06')
            ->assertSee('Daily loss from intraday high')
            ->assertSee('$540.06 / $500.00');
    }

    public function test_admin_client_show_uses_challenge_balance_not_raw_mt5_balance(): void
    {
        [$user, $account] = $this->createTraderWithTrades();

        $account->forceFill([
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'balance' => 99412.37,
            'equity' => 99411.67,
            'total_profit' => -587.63,
            'profit_target_percent' => 10,
            'profit_target_amount' => 1000,
            'rule_state' => [
                'broker_phase_reference_balance' => 100000,
                'highest_challenge_equity_today' => 10000,
                'rules' => [
                    'profit_target_percent' => 10,
                    'daily_drawdown_limit_amount' => 400,
                    'max_drawdown_limit_amount' => 800,
                ],
            ],
        ])->save();

        $account->balanceSnapshots()->create([
            'snapshot_at' => now()->addSecond(),
            'balance' => 99412.37,
            'equity' => 99411.67,
            'profit_loss' => -0.70,
            'total_profit' => -587.63,
            'today_profit' => 0,
            'payload' => [
                'broker_phase_reference_balance' => 100000,
                'open_positions' => [],
                'trade_history' => [],
            ],
        ]);

        $this->adminGet(route('admin.clients.show', $user))
            ->assertOk()
            ->assertSee('Challenge Balance')
            ->assertSee('$9,412.37')
            ->assertSee('Challenge Equity')
            ->assertSee('$9,411.67')
            ->assertSee('Breach Status')
            ->assertDontSee('$99,412.37');
    }

    public function test_challenge_calculation_diagnostic_command_reports_sources(): void
    {
        [, $account] = $this->createTraderWithTrades();

        $exitCode = Artisan::call('wolforix:diagnose-challenge-calculation', [
            'account_reference' => $account->account_reference,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Read-only challenge calculation diagnosis', $output);
        $this->assertStringContainsString('Account and baseline sources', $output);
        $this->assertStringContainsString('Separated calculation values', $output);
        $this->assertStringContainsString('Broker phase reference balance', $output);
        $this->assertStringContainsString('Diagnostic decision', $output);
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
                'today_profit' => 75,
                'open_profit' => 25,
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
