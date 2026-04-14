<?php

namespace Tests\Feature;

use App\Mail\ChallengeFailedMail;
use App\Mail\ChallengePassedMail;
use App\Mail\PhaseOnePassedMail;
use App\Mail\PhaseTwoAccountDetailsMail;
use App\Models\ChallengePlan;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChallengeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mt5_ingestion.token', 'integration-secret');
        Mail::fake();
        Storage::fake('public');
    }

    public function test_one_step_account_stays_active_below_target(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10200, 10180, [
            'trade_count' => 2,
        ])->assertOk()
            ->assertJsonPath('challenge_status', 'active');

        $account->refresh();

        $this->assertSame('active', $account->challenge_status);
        $this->assertSame(1, (int) $account->phase_index);
        $this->assertSame(1, (int) $account->trading_days_completed);
        $this->assertSame('single_phase', (string) $account->account_phase);
        $this->assertSame('mt5_ea', (string) $account->sync_source);
        $this->assertNull($account->failure_reason);
    }

    public function test_metrics_endpoint_accepts_mt5_alias_fields_like_server_time_and_trading_days(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'account_size' => 5000,
            'starting_balance' => 5000,
            'phase_starting_balance' => 5000,
            'phase_reference_balance' => 5000,
            'balance' => 5000,
            'equity' => 5000,
            'highest_equity_today' => 5000,
            'profit_target_amount' => 500,
            'daily_drawdown_limit_amount' => 200,
            'max_drawdown_limit_amount' => 400,
            'account_reference' => 'WFX-CT-00001-CERT',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer integration-secret',
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => 5000,
            'equity' => 5000,
            'open_profit' => 0,
            'highest_equity_today' => 5000,
            'daily_loss_used' => 0,
            'max_drawdown_used' => 0,
            'trading_days' => 1,
            'phase' => 'single_phase',
            'challenge_status' => 'active',
            'server_time' => '2026-04-07 23:40:00',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('account_reference', 'WFX-CT-00001-CERT')
            ->assertJsonPath('challenge_status', 'active')
            ->assertJsonPath('phase_index', 1)
            ->assertJsonPath('trading_days_completed', 1);

        $account->refresh();

        $this->assertSame('2026-04-07', optional($account->server_day)->toDateString());
        $this->assertSame(1, (int) $account->trading_days_completed);
        $this->assertSame('active', $account->challenge_status);
    }

    public function test_metrics_endpoint_accepts_mt5_dotted_server_time_format(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'account_size' => 5000,
            'starting_balance' => 5000,
            'phase_starting_balance' => 5000,
            'phase_reference_balance' => 5000,
            'balance' => 5000,
            'equity' => 5000,
            'highest_equity_today' => 5000,
            'profit_target_amount' => 500,
            'daily_drawdown_limit_amount' => 200,
            'max_drawdown_limit_amount' => 400,
            'account_reference' => 'WFX-CT-00001-DOTTED',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer integration-secret',
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => 5000,
            'equity' => 5000,
            'open_profit' => 0,
            'trading_days' => 1,
            'phase' => 'single_phase',
            'server_time' => '2026.04.07 22:11:54',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('account_reference', 'WFX-CT-00001-DOTTED')
            ->assertJsonPath('challenge_status', 'active')
            ->assertJsonPath('trading_days_completed', 1);

        $account->refresh();

        $this->assertSame('2026-04-07', optional($account->server_day)->toDateString());
        $this->assertSame('active', $account->challenge_status);
    }

    public function test_metrics_endpoint_accepts_numeric_string_payloads_and_keeps_realized_profit_separate(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'account_reference' => 'WFX-CT-00001-STRINGY',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer integration-secret',
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => '10000.50',
            'equity' => '10125.75',
            'open_profit' => '125.25',
            'trading_days' => '1',
            'positions_count' => '2',
            'has_activity' => 'true',
            'phase' => 'single_phase',
            'sync_trigger' => 'floating_pnl_change',
            'server_time' => '2026-04-07 23:40:00',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('challenge_status', 'active')
            ->assertJsonPath('trading_days_completed', 1);

        $account->refresh();

        $this->assertSame('10000.50', (string) $account->balance);
        $this->assertSame('10125.75', (string) $account->equity);
        $this->assertSame('125.25', (string) $account->profit_loss);
        $this->assertSame('0.50', (string) $account->total_profit);
        $this->assertSame('success', $account->sync_status);
    }

    public function test_metrics_endpoint_returns_422_for_invalid_server_time_format(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->withHeaders([
            'Authorization' => 'Bearer integration-secret',
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => 10000,
            'equity' => 10000,
            'server_time' => '07/04/2026 22:11:54 invalid',
        ])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid server_time format',
            ]);
    }

    public function test_one_step_target_does_not_pass_before_minimum_trading_days(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10600, 10580, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 11050, 11010, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('active', $account->challenge_status);
        $this->assertSame(2, (int) $account->trading_days_completed);
        $this->assertNull($account->passed_at);
    }

    public function test_one_step_passes_after_target_and_three_trading_days(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10400, 10380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 10800, 10780, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11020, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('passed', $account->challenge_status);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertNotNull($account->passed_at);
        $this->assertNotNull($account->passed_email_sent_at);
        $this->assertNotNull($account->funded_pass_email_sent_at);
        $this->assertNotNull($account->certificate_path);
        $this->assertNotNull($account->certificate_generated_at);
        $this->assertNull($account->failure_reason);
        Storage::disk('public')->assertExists((string) $account->certificate_path);
        $this->assertSame('image/png', mime_content_type(Storage::disk('public')->path((string) $account->certificate_path)));

        Mail::assertSent(ChallengePassedMail::class, function (ChallengePassedMail $mail) use ($account): bool {
            return ($mail->certificate['path'] ?? null) === $account->certificate_path
                && count($mail->attachments()) === 1;
        });

        $certificatePath = (string) $account->certificate_path;
        $certificateGeneratedAt = $account->certificate_generated_at?->toDateTimeString();
        $passedEmailSentAt = $account->passed_email_sent_at?->toDateTimeString();
        $fundedPassEmailSentAt = $account->funded_pass_email_sent_at?->toDateTimeString();

        $this->pushMetrics($account, '2026-04-07 09:00:10', 11080, 11040, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame($certificatePath, (string) $account->certificate_path);
        $this->assertSame($certificateGeneratedAt, $account->certificate_generated_at?->toDateTimeString());
        $this->assertSame($passedEmailSentAt, $account->passed_email_sent_at?->toDateTimeString());
        $this->assertSame($fundedPassEmailSentAt, $account->funded_pass_email_sent_at?->toDateTimeString());
        Mail::assertSent(ChallengePassedMail::class, 1);
    }

    public function test_one_step_fails_when_daily_loss_limit_is_breached(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('final_state_locked', true)
            ->assertJsonPath('close_positions_required', true)
            ->assertJsonPath('ea_action', 'close_all_positions_and_block_trading');

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('daily_loss_breached', $account->failure_reason);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertNotNull($account->failed_at);
        $this->assertNotNull($account->failed_email_sent_at);
        Mail::assertSent(ChallengeFailedMail::class, 1);
    }

    public function test_one_step_fails_when_max_drawdown_is_breached(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 9150, 9700, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('max_drawdown_breached', $account->failure_reason);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        Mail::assertSent(ChallengeFailedMail::class, 1);
    }

    public function test_final_state_emails_and_fail_actions_are_idempotent_on_repeated_sync(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('ea_action', 'close_all_positions_and_block_trading');

        $account->refresh();
        $failedAt = $account->failed_at?->toDateTimeString();
        $failedEmailSentAt = $account->failed_email_sent_at?->toDateTimeString();

        $this->pushMetrics($account, '2026-04-05 09:00:10', 10000, 9400, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('close_positions_required', true);

        $account->refresh();

        $this->assertSame($failedAt, $account->failed_at?->toDateTimeString());
        $this->assertSame($failedEmailSentAt, $account->failed_email_sent_at?->toDateTimeString());
        Mail::assertSent(ChallengeFailedMail::class, 1);
    }

    public function test_five_k_dashboard_uses_challenge_relative_balance_and_equity(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'account_size' => 5000,
            'starting_balance' => 5000,
            'phase_starting_balance' => 5000,
            'phase_reference_balance' => 5000,
            'balance' => 5000,
            'equity' => 5000,
            'profit_target_amount' => 500,
            'daily_drawdown_limit_amount' => 200,
            'max_drawdown_limit_amount' => 400,
        ]);

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10562.64, 10596.80, [
            'open_profit' => 34.16,
            'total_profit' => 5562.64,
            'trade_count' => 0,
        ])->assertOk();

        $account->refresh();

        $this->assertSame('562.64', (string) $account->total_profit);

        $this->actingAs($account->user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('Initial balance')
            ->assertSee('$5,000.00')
            ->assertSee('Current balance')
            ->assertSee('$5,562.64')
            ->assertSee('Challenge equity')
            ->assertSee('$5,596.80')
            ->assertSee('Recognized profit')
            ->assertSee('$562.64')
            ->assertDontSee('$10,562.64');
    }

    public function test_dashboard_index_shows_command_center_stats_and_safe_mt5_access(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-12 12:00:00'));

        try {
            $account = $this->createChallengeAccount('one_step', [
                'balance' => 10140,
                'equity' => 10110,
                'profit_loss' => -30,
                'total_profit' => 140,
                'today_profit' => 80,
                'platform_login' => '889900',
                'platform_environment' => 'demo',
                'last_synced_at' => now()->subMinutes(5),
                'meta' => [
                    'mt5_server' => 'Wolforix-Demo',
                ],
            ]);

            $account->balanceSnapshots()->create([
                'snapshot_at' => now()->subMinutes(5),
                'balance' => 10140,
                'equity' => 10110,
                'profit_loss' => -30,
                'total_profit' => 140,
                'today_profit' => 80,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0,
                'payload' => [
                    'trade_history' => [
                        [
                            'deal_id' => 'D-1001',
                            'symbol' => 'XAUUSD',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-10 08:00:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-10 10:00:00')->timestamp,
                            'volume' => 1.25,
                            'commission' => -2,
                            'net_profit' => 120,
                        ],
                        [
                            'deal_id' => 'D-1002',
                            'symbol' => 'BTCUSD',
                            'trade_side' => 'sell',
                            'open_timestamp' => Carbon::parse('2026-04-11 09:00:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-11 10:00:00')->timestamp,
                            'volume' => 0.4,
                            'commission' => -1,
                            'net_profit' => -60,
                        ],
                        [
                            'deal_id' => 'D-1003',
                            'symbol' => 'XAUUSD',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-11 13:00:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-11 17:00:00')->timestamp,
                            'volume' => 1,
                            'commission' => -1,
                            'net_profit' => 80,
                        ],
                    ],
                    'open_positions' => [
                        [
                            'position_id' => 'P-9001',
                            'symbol' => 'NVDA',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-12 09:00:00')->timestamp,
                            'volume' => 2,
                            'net_unrealized_pnl' => -30,
                        ],
                    ],
                ],
            ]);

            $account->tradingDays()->create([
                'phase_index' => 1,
                'trading_date' => '2026-04-11',
                'activity_count' => 3,
                'volume' => 2.65,
                'first_activity_at' => Carbon::parse('2026-04-11 09:00:00'),
                'last_activity_at' => Carbon::parse('2026-04-11 17:00:00'),
                'source' => 'mt5_ea',
            ]);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Welcome back')
                ->assertSee('All')
                ->assertSee('Active')
                ->assertSee('Inactive')
                ->assertSee('Account summary')
                ->assertSee('Credentials')
                ->assertSee('Share metrics')
                ->assertSee('Go to metrics')
                ->assertSee('Trading command center')
                ->assertSee('Current balance')
                ->assertSee('$10,140.00')
                ->assertSee('Time since first trade')
                ->assertSee('2 days')
                ->assertSee('Win ratio')
                ->assertSee('66.7%')
                ->assertSee('Most traded instruments')
                ->assertSee('XAUUSD')
                ->assertSee('BTCUSD')
                ->assertSee('NVDA')
                ->assertSee('MT5 access')
                ->assertDontSee('Open credentials panel')
                ->assertSee('MT5 account login')
                ->assertSee('889900')
                ->assertSee('Wolforix-Demo')
                ->assertSee('Secure disclosure not enabled')
                ->assertSee('Rule monitoring')
                ->assertSee('Statistics')
                ->assertSee('Average win')
                ->assertSee('$100.00')
                ->assertSee('Worst trade')
                ->assertSee('-$60.00')
                ->assertSee('Average trade duration')
                ->assertSee('2 hr')
                ->assertSee('Daily summary')
                ->assertSee('3 trades')
                ->assertSee('2.65')
                ->assertDontSee('admin');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_most_traded_reads_mt5_payload_variants(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00'));

        try {
            $account = $this->createChallengeAccount('one_step', [
                'balance' => 10120,
                'equity' => 10145,
                'profit_loss' => 25,
                'total_profit' => 120,
                'last_synced_at' => now(),
            ]);

            $account->balanceSnapshots()->create([
                'snapshot_at' => now(),
                'balance' => 10120,
                'equity' => 10145,
                'profit_loss' => 25,
                'total_profit' => 120,
                'today_profit' => 120,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0,
                'payload' => [
                    'positions' => [
                        [
                            'Ticket' => 'P-100',
                            'Symbol' => 'XAUUSD',
                            'Type' => 'BUY',
                            'Volume' => 1.2,
                            'Profit' => 25,
                            'Time' => Carbon::parse('2026-04-13 09:00:00')->timestamp,
                        ],
                    ],
                    'history' => [
                        [
                            'Ticket' => 'D-100',
                            'Symbol' => 'XAUUSD',
                            'Type' => 'SELL',
                            'Volume' => 0.8,
                            'Profit' => 95,
                            'Time' => Carbon::parse('2026-04-13 08:00:00')->timestamp,
                            'TimeClose' => Carbon::parse('2026-04-13 08:45:00')->timestamp,
                        ],
                        [
                            'Ticket' => 'D-101',
                            'Symbol' => 'BTCUSD',
                            'Type' => 'BUY',
                            'Volume' => 0.3,
                            'Profit' => -20,
                            'Time' => Carbon::parse('2026-04-13 07:00:00')->timestamp,
                            'TimeClose' => Carbon::parse('2026-04-13 07:20:00')->timestamp,
                        ],
                    ],
                ],
            ]);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Most traded instruments')
                ->assertSee('XAUUSD')
                ->assertSee('BTCUSD')
                ->assertSee('2 trades')
                ->assertSee('66.7%')
                ->assertDontSee('Top symbols will populate from synced open positions and closed trade history.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_most_traded_renders_single_symbol_aggregate_payload(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'balance' => 10562.64,
            'equity' => 10596.80,
            'profit_loss' => 34.16,
            'total_profit' => 562.64,
            'last_synced_at' => now(),
        ]);

        $account->balanceSnapshots()->create([
            'snapshot_at' => now(),
            'balance' => 10562.64,
            'equity' => 10596.80,
            'profit_loss' => 34.16,
            'total_profit' => 562.64,
            'today_profit' => 562.64,
            'daily_drawdown' => 0,
            'max_drawdown' => 0,
            'drawdown_percent' => 0,
            'payload' => [
                'symbol' => 'XAUUSD',
                'trade_count' => 20,
                'volume' => 2.5,
                'total_profit' => 562.64,
            ],
        ]);

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Most traded instruments')
            ->assertSee('XAUUSD')
            ->assertSee('20 trades')
            ->assertSee('100.0%')
            ->assertDontSee('Top symbols will populate from synced open positions and closed trade history.');
    }

    public function test_dashboard_trade_panel_shows_detailed_trade_fields_from_synced_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-14 12:30:00'));

        try {
            $account = $this->createChallengeAccount('one_step', [
                'balance' => 10240,
                'equity' => 10210,
                'profit_loss' => -30,
                'total_profit' => 240,
                'last_synced_at' => now(),
                'sync_source' => 'mt5_ea',
            ]);

            $account->balanceSnapshots()->create([
                'snapshot_at' => now(),
                'balance' => 10240,
                'equity' => 10210,
                'profit_loss' => -30,
                'total_profit' => 240,
                'today_profit' => 240,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0,
                'payload' => [
                    'trade_history' => [
                        [
                            'deal_id' => 'D-2001',
                            'symbol' => 'EURUSD',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-14 09:00:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-14 09:45:00')->timestamp,
                            'entry_price' => 1.08215,
                            'exit_price' => 1.08355,
                            'volume' => 0.8,
                            'profit' => 125.50,
                            'commission' => -2.25,
                            'swap' => -0.75,
                        ],
                        [
                            'deal_id' => 'D-2002',
                            'symbol' => 'BTCUSD',
                            'trade_side' => 'sell',
                            'open_timestamp' => Carbon::parse('2026-04-14 07:10:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-14 08:15:00')->timestamp,
                            'entry_price' => 67890.10,
                            'exit_price' => 67955.80,
                            'volume' => 0.25,
                            'profit' => -60.00,
                            'commission' => -1.00,
                            'swap' => 0,
                        ],
                    ],
                    'open_positions' => [
                        [
                            'position_id' => 'P-7001',
                            'symbol' => 'XAUUSD',
                            'trade_side' => 'sell',
                            'open_timestamp' => Carbon::parse('2026-04-14 10:15:00')->timestamp,
                            'entry_price' => 3235.40,
                            'volume' => 1.2,
                            'profit' => -18.40,
                            'commission' => -1.40,
                            'swap' => 0.20,
                        ],
                    ],
                ],
            ]);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Entry')
                ->assertSee('Exit')
                ->assertSee('Duration')
                ->assertSee('Commission')
                ->assertSee('Swap')
                ->assertSee('Net result')
                ->assertSee('EURUSD')
                ->assertSee('BTCUSD')
                ->assertSee('XAUUSD')
                ->assertSee('Buy')
                ->assertSee('Sell')
                ->assertSee('Win')
                ->assertSee('Loss')
                ->assertSee('Open')
                ->assertSee('1.08215')
                ->assertSee('1.08355')
                ->assertSee('00h 45m')
                ->assertSee('02h 15m')
                ->assertSee('$125.50')
                ->assertSee('$122.50')
                ->assertSee('-$61.00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_trade_panel_falls_back_to_latest_persisted_detailed_rows_when_newest_snapshot_is_summary_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-14 12:30:00'));

        try {
            $account = $this->createChallengeAccount('one_step', [
                'balance' => 10240,
                'equity' => 10210,
                'profit_loss' => -30,
                'total_profit' => 240,
                'last_synced_at' => now(),
                'sync_source' => 'mt5_ea',
            ]);

            $account->balanceSnapshots()->create([
                'snapshot_at' => Carbon::parse('2026-04-14 12:10:00'),
                'balance' => 10235,
                'equity' => 10205,
                'profit_loss' => -30,
                'total_profit' => 235,
                'today_profit' => 235,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0,
                'payload' => [
                    'trade_history' => [
                        [
                            'deal_id' => 'D-2301',
                            'symbol' => 'EURUSD',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-14 09:00:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-14 09:45:00')->timestamp,
                            'entry_price' => 1.08215,
                            'exit_price' => 1.08355,
                            'volume' => 0.8,
                            'profit' => 125.50,
                        ],
                    ],
                    'open_positions' => [
                        [
                            'position_id' => 'P-7301',
                            'symbol' => 'XAUUSD',
                            'trade_side' => 'sell',
                            'open_timestamp' => Carbon::parse('2026-04-14 10:15:00')->timestamp,
                            'entry_price' => 3235.40,
                            'volume' => 1.2,
                            'profit' => -18.40,
                        ],
                    ],
                ],
            ]);

            $account->balanceSnapshots()->create([
                'snapshot_at' => Carbon::parse('2026-04-14 12:29:00'),
                'balance' => 10240,
                'equity' => 10210,
                'profit_loss' => -30,
                'total_profit' => 240,
                'today_profit' => 240,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0,
                'payload' => [
                    'positions_count' => 1,
                    'trade_count' => 1,
                    'has_activity' => true,
                ],
            ]);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('EURUSD')
                ->assertSee('XAUUSD')
                ->assertSee('Showing the latest persisted detailed trade rows. A newer MT5 sync updated account metrics without row-level trade data.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_trade_panel_reads_alternate_mt5_trade_payload_keys(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-14 12:30:00'));

        try {
            $account = $this->createChallengeAccount('one_step', [
                'balance' => 10190,
                'equity' => 10140,
                'profit_loss' => -50,
                'total_profit' => 190,
                'last_synced_at' => now(),
                'sync_source' => 'mt5_ea',
            ]);

            $account->balanceSnapshots()->create([
                'snapshot_at' => now(),
                'balance' => 10190,
                'equity' => 10140,
                'profit_loss' => -50,
                'total_profit' => 190,
                'today_profit' => 190,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0,
                'payload' => [
                    'closedOrders' => [
                        [
                            'ticket_number' => 'D-2401',
                            'instrument_name' => 'EURUSD',
                            'Type' => 1,
                            'time_open' => Carbon::parse('2026-04-14 08:30:00')->timestamp,
                            'time_close' => Carbon::parse('2026-04-14 10:00:00')->timestamp,
                            'price_open' => 1.10020,
                            'price_close' => 1.09890,
                            'volume_lots' => 0.75,
                            'profit' => 98.25,
                        ],
                    ],
                    'openTrades' => [
                        [
                            'ticket_number' => 'P-2402',
                            'instrument_name' => 'XAUUSD',
                            'Type' => 0,
                            'time_open' => Carbon::parse('2026-04-14 11:20:00')->timestamp,
                            'price_open' => 3230.40,
                            'volume_lots' => 0.40,
                            'profit' => -12.50,
                        ],
                    ],
                ],
            ]);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('EURUSD')
                ->assertSee('XAUUSD')
                ->assertSee('Sell')
                ->assertSee('Buy')
                ->assertSee('01h 30m')
                ->assertSee('3,230.40')
                ->assertSee('1.09890');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_trade_panel_uses_snapshot_time_for_open_trade_duration_when_mt5_server_clock_is_ahead(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-14 09:30:00'));

        try {
            $account = $this->createChallengeAccount('one_step', [
                'balance' => 10240,
                'equity' => 10210,
                'profit_loss' => -30,
                'total_profit' => 240,
                'last_synced_at' => now(),
                'sync_source' => 'mt5_ea',
            ]);

            $account->balanceSnapshots()->create([
                'snapshot_at' => Carbon::parse('2026-04-14 12:30:00'),
                'balance' => 10240,
                'equity' => 10210,
                'profit_loss' => -30,
                'total_profit' => 240,
                'today_profit' => 240,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0,
                'payload' => [
                    'open_positions' => [
                        [
                            'position_id' => 'P-9001',
                            'symbol' => 'USDJPY',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-14 12:15:00')->timestamp,
                            'entry_price' => 153.245,
                            'volume' => 0.1,
                            'profit' => 8.40,
                        ],
                    ],
                ],
            ]);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('USDJPY')
                ->assertSee('00h 15m');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_trade_panel_explains_when_activity_arrives_without_row_level_trade_payload(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'balance' => 10080,
            'equity' => 10065,
            'profit_loss' => -15,
            'total_profit' => 80,
            'last_synced_at' => now(),
            'sync_source' => 'mt5_ea',
        ]);

        $account->balanceSnapshots()->create([
            'snapshot_at' => now(),
            'balance' => 10080,
            'equity' => 10065,
            'profit_loss' => -15,
            'total_profit' => 80,
            'today_profit' => 80,
            'daily_drawdown' => 0,
            'max_drawdown' => 0,
            'drawdown_percent' => 0,
            'payload' => [
                'positions_count' => 1,
                'closed_positions_count' => 2,
                'trade_count' => 3,
                'has_activity' => true,
            ],
        ]);

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('MT5 sync is updating this account, but recent snapshots still do not include row-level open or closed trade rows. The account summary can refresh before detailed rows arrive, and this table only fills from real synced MT5 trade data.');
    }

    public function test_two_step_phase_one_pass_transitions_to_phase_two_and_resets_references(): void
    {
        $account = $this->createChallengeAccount('two_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10400, 10380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 10800, 10780, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11030, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('active', $account->challenge_status);
        $this->assertSame(2, (int) $account->phase_index);
        $this->assertSame('phase_2', (string) $account->account_phase);
        $this->assertSame(0, (int) $account->trading_days_completed);
        $this->assertSame('10000.00', (string) $account->phase_starting_balance);
        $this->assertSame('10000.00', (string) $account->phase_reference_balance);
        $this->assertSame(11050.00, (float) ($account->rule_state['broker_phase_reference_balance'] ?? 0));
        $this->assertSame('5.00', (string) $account->profit_target_percent);
        $this->assertNotEmpty($account->rule_state['phase_history'] ?? []);
        $this->assertNotNull($account->phase_one_pass_email_sent_at);
        $this->assertNotNull($account->phase_two_credentials_email_sent_at);
        Mail::assertSent(PhaseOnePassedMail::class, 1);
        Mail::assertSent(PhaseTwoAccountDetailsMail::class, 1);

        $phaseOneSentAt = $account->phase_one_pass_email_sent_at?->toDateTimeString();
        $phaseTwoCredentialsSentAt = $account->phase_two_credentials_email_sent_at?->toDateTimeString();

        $this->pushMetrics($account, '2026-04-07 09:00:30', 11060, 11035, ['trade_count' => 0])->assertOk();

        $account->refresh();

        $this->assertSame($phaseOneSentAt, $account->phase_one_pass_email_sent_at?->toDateTimeString());
        $this->assertSame($phaseTwoCredentialsSentAt, $account->phase_two_credentials_email_sent_at?->toDateTimeString());
        Mail::assertSent(PhaseOnePassedMail::class, 1);
        Mail::assertSent(PhaseTwoAccountDetailsMail::class, 1);
    }

    public function test_two_step_phase_two_passes_after_three_trading_days(): void
    {
        $account = $this->createChallengeAccount('two_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10400, 10380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 10800, 10780, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11030, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->pushMetrics($account, '2026-04-08 09:00:00', 11250, 11210, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-09 09:00:00', 11400, 11380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-10 09:00:00', 11560, 11520, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('passed', $account->challenge_status);
        $this->assertSame(2, (int) $account->phase_index);
        $this->assertNotNull($account->passed_at);
    }

    public function test_two_step_phase_two_breach_fails_the_challenge(): void
    {
        $account = $this->createChallengeAccount('two_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10400, 10380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 10800, 10780, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11030, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->pushMetrics($account, '2026-04-08 09:00:00', 9990, 10700, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('max_drawdown_breached', $account->failure_reason);
    }

    public function test_dashboard_accounts_page_renders_challenge_progress_and_failure_reason(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'challenge_status' => 'failed',
            'account_status' => 'failed',
            'failure_reason' => 'daily_loss_breached',
            'profit_loss' => -150,
            'daily_loss_used' => 500,
            'daily_drawdown_limit_amount' => 400,
            'max_drawdown_used' => 500,
            'max_drawdown_limit_amount' => 800,
            'trading_days_completed' => 2,
            'last_synced_at' => now(),
            'last_evaluated_at' => now(),
            'sync_source' => 'mt5_ea',
        ]);

        $this->actingAs($account->user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('MT5 live sync')
            ->assertSee('Floating P&amp;L', false)
            ->assertSee('Sync freshness')
            ->assertSee('Challenge progress')
            ->assertSee('Failure reason')
            ->assertSee('Daily Loss Breached')
            ->assertSee('Single Phase')
            ->assertSee('MT5 EA');
    }

    public function test_dashboard_accounts_page_shows_empty_state_without_accounts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('No challenge accounts linked yet');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createChallengeAccount(string $challengeType, array $overrides = []): TradingAccount
    {
        $user = $overrides['user'] ?? User::factory()->create();
        $accountSize = (int) ($overrides['account_size'] ?? 10000);
        $plan = $this->createPlan($challengeType, $accountSize);
        $phase = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}.phases.0");

        unset($overrides['user']);

        return TradingAccount::query()->create(array_merge([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => $challengeType,
            'account_size' => $accountSize,
            'account_reference' => 'ACC-'.strtoupper($challengeType).'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_account_id' => 'MT5-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'platform_login' => (string) random_int(100000, 999999),
            'platform_environment' => 'demo',
            'platform_status' => 'connected',
            'stage' => $challengeType === 'one_step' ? 'Single Phase' : 'Challenge Step 1',
            'status' => 'Pending Activation',
            'account_type' => 'challenge',
            'account_phase' => $challengeType === 'one_step' ? 'single_phase' : 'phase_1',
            'phase_index' => 1,
            'account_status' => 'pending_activation',
            'challenge_status' => 'pending_activation',
            'is_funded' => false,
            'is_trial' => false,
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
            'profit_target_percent' => (float) ($phase['profit_target'] ?? 0),
            'profit_target_amount' => round($accountSize * ((float) ($phase['profit_target'] ?? 0) / 100), 2),
            'profit_target_progress_percent' => 0,
            'daily_drawdown_limit_percent' => (float) ($phase['daily_loss_limit'] ?? 0),
            'daily_drawdown_limit_amount' => round($accountSize * ((float) ($phase['daily_loss_limit'] ?? 0) / 100), 2),
            'max_drawdown_limit_percent' => (float) ($phase['max_loss_limit'] ?? 0),
            'max_drawdown_limit_amount' => round($accountSize * ((float) ($phase['max_loss_limit'] ?? 0) / 100), 2),
            'profit_split' => 80,
            'minimum_trading_days' => (int) ($phase['minimum_trading_days'] ?? 3),
            'trading_days_completed' => 0,
            'sync_status' => 'pending',
            'sync_source' => null,
            'rule_state' => [],
            'meta' => [],
        ], $overrides));
    }

    private function createPlan(string $challengeType, int $accountSize): ChallengePlan
    {
        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}");

        return ChallengePlan::query()->create([
            'slug' => $definition['slug'],
            'name' => $definition['name'],
            'account_size' => $accountSize,
            'currency' => $definition['currency'],
            'entry_fee' => $definition['entry_fee'],
            'profit_target' => $definition['profit_target'],
            'daily_loss_limit' => $definition['daily_loss_limit'],
            'max_loss_limit' => $definition['max_loss_limit'],
            'steps' => $definition['steps'],
            'profit_share' => $definition['profit_share'],
            'first_payout_days' => $definition['first_payout_days'],
            'minimum_trading_days' => $definition['minimum_trading_days'],
            'payout_cycle_days' => $definition['payout_cycle_days'],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pushMetrics(TradingAccount $account, string $timestamp, float $balance, float $equity, array $extra = [])
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer integration-secret',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), array_merge([
            'balance' => $balance,
            'equity' => $equity,
            'timestamp' => $timestamp,
            'server_day' => substr($timestamp, 0, 10),
            'platform_status' => 'connected',
        ], $extra));
    }
}
