<?php

namespace Tests\Feature;

use App\Mail\ChallengeFailedMail;
use App\Mail\ChallengePassedMail;
use App\Mail\ChallengePhasePassSupportNotificationMail;
use App\Mail\ConsistencyAlertMail;
use App\Mail\PhaseOnePassedMail;
use App\Mail\PhaseTwoAccountDetailsMail;
use App\Mail\TrustpilotReviewRequestMail;
use App\Models\ChallengePlan;
use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Models\User;
use App\Services\MetaApi\MetaApiLiveSyncService;
use App\Services\MetaApi\MetaApiOnboardingService;
use App\Services\Reviews\TrustpilotReviewRequestMailer;
use App\Services\TradingAccounts\TradeHistoryPanelBuilder;
use App\Support\Mt5ConnectorStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChallengeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
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

    public function test_mt5_metrics_requires_the_account_secret_token(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->postJson(route('api.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), $this->metricsPayload())
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => 'Invalid token',
            ]);
    }

    public function test_mt5_metrics_rejects_invalid_and_global_env_tokens(): void
    {
        $account = $this->createChallengeAccount('one_step');

        foreach (['wrong-token', 'legacy-global-token'] as $token) {
            $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
            ])->postJson(route('api.mt5.metrics', [
                'accountIdentifier' => $account->account_reference,
            ]), $this->metricsPayload())
                ->assertUnauthorized()
                ->assertExactJson([
                    'error' => 'Invalid token',
                ]);
        }
    }

    public function test_mt5_metrics_accepts_secret_token_from_request_body(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->postJson(route('api.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), array_merge($this->metricsPayload(), [
            'secret_token' => data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
        ]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('account_reference', $account->account_reference);
    }

    public function test_mt5_metrics_rejects_token_from_another_account(): void
    {
        $account = $this->createChallengeAccount('one_step');
        $otherAccount = $this->createChallengeAccount('one_step', [
            'account_size' => 5000,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.data_get($otherAccount->fresh()->meta, 'mt5_connector.secret_token'),
        ])->postJson(route('api.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), $this->metricsPayload())
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => 'Invalid token',
            ]);
    }

    public function test_metrics_endpoint_uses_account_reference_for_fusionmarkets_account(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'account_size' => 25000,
            'starting_balance' => 25000,
            'phase_starting_balance' => 25000,
            'phase_reference_balance' => 25000,
            'balance' => 25000,
            'equity' => 25000,
            'highest_equity_today' => 25000,
            'profit_target_amount' => 2500,
            'daily_drawdown_limit_amount' => 1000,
            'max_drawdown_limit_amount' => 2000,
            'account_reference' => 'WFX-MT5-FUSION-25000',
            'platform_account_id' => '335411',
            'platform_login' => '335411',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'waiting_for_first_sync',
            'meta' => [
                'broker' => 'FusionMarkets',
                'mt5_sync' => [
                    'identifier' => '335411',
                    'status' => 'waiting_for_first_sync',
                ],
            ],
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => 25080,
            'equity' => 25110,
            'open_profit' => 30,
            'trade_count' => 2,
            'trading_days' => 1,
            'platform_login' => '335411',
            'platform_account_id' => '335411',
            'platform_environment' => 'FusionMarkets-Demo',
            'server_time' => '2026.04.23 14:15:16',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('account_id', $account->id)
            ->assertJsonPath('account_reference', 'WFX-MT5-FUSION-25000');

        $account->refresh();

        $this->assertSame('success', $account->sync_status);
        $this->assertSame('connected', $account->platform_status);
        $this->assertSame('mt5_ea', $account->sync_source);
        $this->assertSame('connected', data_get($account->meta, 'mt5_sync.status'));
        $this->assertSame(1, (int) $account->trading_days_completed);
        $this->assertSame(25080.00, (float) $account->balance);
        $this->assertSame(25110.00, (float) $account->equity);
    }

    public function test_metrics_endpoint_accepts_unique_mt5_login_identifier_fallback(): void
    {
        $account = $this->createChallengeAccount('two_step', [
            'account_reference' => 'WFX-MT5-00062-NSTY',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'waiting_for_first_sync',
            'sync_status' => 'pending',
            'balance' => 10000,
            'equity' => 10000,
            'highest_equity_today' => 10000,
            'meta' => [
                'mt5_sync' => [
                    'identifier' => '335405',
                    'status' => 'waiting_for_first_sync',
                ],
            ],
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => '335405',
        ]), [
            'balance' => 10025,
            'equity' => 10010,
            'open_profit' => -15,
            'positions_count' => 1,
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'server_time' => '2026.05.22 11:00:00',
            'sync_trigger' => 'timer',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('account_id', $account->id)
            ->assertJsonPath('account_reference', 'WFX-MT5-00062-NSTY');

        $account->refresh();

        $this->assertSame('success', $account->sync_status);
        $this->assertSame('mt5_ea', $account->sync_source);
        $this->assertSame('10025.00', (string) $account->balance);
        $this->assertSame('10010.00', (string) $account->equity);
        $this->assertSame('335405', data_get($account->meta, 'mt5_sync.platform_login'));
        $this->assertDatabaseHas('trading_account_balance_snapshots', [
            'trading_account_id' => $account->id,
            'balance' => 10025,
            'equity' => 10010,
        ]);
    }

    public function test_metrics_endpoint_logs_unknown_identifier_rejection_for_sync_diagnosis(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer dead-token',
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => '335405',
        ]), [
            'balance' => 10000,
            'equity' => 10000,
            'platform_login' => '335405',
            'server_time' => '2026.05.22 11:00:00',
        ])->assertStatus(422);

        $latestLog = TradingAccountSyncLog::query()->latest('id')->firstOrFail();

        $this->assertNull($latestLog->trading_account_id);
        $this->assertSame('rejected', $latestLog->status);
        $this->assertSame('account_identifier_not_found', $latestLog->error_message);
        $this->assertSame('335405', data_get($latestLog->payload, 'platform_login'));
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
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
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
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
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

    public function test_mt5_metrics_counts_activity_from_position_counts_and_derives_today_profit_from_closed_rows(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'account_reference' => 'WFX-CT-00001-LIVE',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => 10075,
            'equity' => 10115,
            'open_profit' => 40,
            'positions_count' => 1,
            'closed_positions_count' => 1,
            'phase' => 'one_step',
            'sync_trigger' => 'trade_history_add',
            'server_time' => '2026-04-08 10:30:00',
            'trade_history' => [
                [
                    'deal_id' => 90001,
                    'symbol' => 'XAUUSD',
                    'execution_timestamp' => '2026-04-08 10:15:00',
                    'profit' => 75,
                    'commission' => -3,
                    'swap' => -1,
                ],
                [
                    'deal_id' => 90002,
                    'symbol' => 'EURUSD',
                    'execution_timestamp' => '2026-04-07 16:00:00',
                    'profit' => 20,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('trading_days_completed', 1);

        $account->refresh();

        $this->assertSame(1, (int) $account->trading_days_completed);
        $this->assertSame('71.00', (string) $account->today_profit);
        $this->assertSame('40.00', (string) $account->profit_loss);
        $this->assertSame('75.00', (string) $account->total_profit);
        $this->assertSame('trade_history_add', data_get($account->meta, 'mt5_sync.last_sync_trigger'));
        $this->assertSame(2, data_get($account->meta, 'mt5_sync.last_payload_summary.trade_history_rows'));
        $this->assertDatabaseHas('trading_account_days', [
            'trading_account_id' => $account->id,
            'trading_date' => '2026-04-08 00:00:00',
            'activity_count' => 1,
        ]);
        $this->assertSame('success', TradingAccountSyncLog::query()->latest('id')->value('status'));
    }

    public function test_metrics_endpoint_returns_422_for_invalid_server_time_format(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->withHeaders([
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
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

        $account->refresh();

        $this->assertSame('error', $account->sync_status);
        $this->assertSame('payload_normalization_failed', data_get($account->meta, 'mt5_sync.last_rejected_reason'));
        $this->assertSame('rejected', TradingAccountSyncLog::query()->latest('id')->value('status'));
    }

    public function test_mt5_metrics_rejects_invalid_payloads_with_diagnostics_after_authentication(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->withHeaders([
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
            'Accept' => 'application/json',
        ])->postJson(route('api.integrations.mt5.metrics', [
            'accountIdentifier' => $account->account_reference,
        ]), [
            'balance' => 10000,
            'server_time' => '2026-04-07 22:11:54',
            'sync_trigger' => 'timer',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'MT5 metrics payload rejected.');

        $account->refresh();
        $latestLog = TradingAccountSyncLog::query()->latest('id')->firstOrFail();

        $this->assertSame('error', $account->sync_status);
        $this->assertStringContainsString('payload_validation_failed', (string) $account->sync_error);
        $this->assertSame('payload_validation_failed', data_get($account->meta, 'mt5_sync.last_rejected_reason'));
        $this->assertSame('rejected', $latestLog->status);
        $this->assertSame('payload_validation_failed', $latestLog->error_message);
    }

    public function test_mt5_metrics_ignores_stale_sync_payloads_without_regressing_dashboard_metrics(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-08 10:00:00', 10080, 10120, [
            'open_profit' => 40,
            'positions_count' => 1,
        ])->assertOk();

        $this->pushMetrics($account, '2026-04-08 09:59:59', 9990, 9995, [
            'open_profit' => 5,
            'positions_count' => 1,
            'sync_trigger' => 'retry',
        ])->assertOk();

        $account->refresh();

        $this->assertSame('10080.00', (string) $account->balance);
        $this->assertSame('10120.00', (string) $account->equity);
        $this->assertSame('40.00', (string) $account->profit_loss);
        $this->assertSame('2026-04-08 10:00:00', $account->last_synced_at?->toDateTimeString());
        $this->assertSame('stale_timestamp', data_get($account->meta, 'mt5_sync.last_ignored_reason'));
        $this->assertSame('ignored', TradingAccountSyncLog::query()->latest('id')->value('status'));
    }

    public function test_metaapi_metrics_sync_updates_dashboard_metrics_and_trade_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 09:00:00'));

        try {
            $account = $this->createMetaApiChallengeAccount('340134');
            $metaApiAccountId = $this->attachMetaApiPoolEntry($account, '7ed465cc-2315-4311-b4a1-4cc90f66e332');

            $this->fakeMetaApiSync($metaApiAccountId, [
                'balance' => 10080.25,
                'equity' => 10125.50,
                'margin' => 320.10,
                'freeMargin' => 9805.40,
                'leverage' => 100,
                'login' => '340134',
            ], [
                [
                    'id' => 'P-340134-1',
                    'symbol' => 'XAUUSD',
                    'type' => 'POSITION_TYPE_BUY',
                    'openTime' => '2026-05-26T08:40:00.000Z',
                    'openPrice' => 2340.55,
                    'volume' => 0.5,
                    'profit' => 45.25,
                    'commission' => 0,
                    'swap' => 0,
                ],
            ], [
                [
                    'id' => 'D-340134-1',
                    'symbol' => 'EURUSD',
                    'type' => 'DEAL_TYPE_BUY',
                    'time' => '2026-05-26T08:20:00.000Z',
                    'profit' => 80.25,
                    'commission' => -2,
                    'swap' => 0,
                    'volume' => 0.2,
                ],
            ]);

            $result = app(MetaApiLiveSyncService::class)->syncByLogin('340134');

            $this->assertSame('success', $result['status']);
            $this->assertSame('CONNECTED', $result['validation_state']);
            $this->assertTrue($result['account_information_readable']);
            $this->assertTrue($result['positions_readable']);
            $this->assertTrue($result['history_readable']);

            $account->refresh();

            $this->assertSame('metaapi', $account->sync_source);
            $this->assertSame('success', $account->sync_status);
            $this->assertSame('connected', $account->platform_status);
            $this->assertSame('10080.25', (string) $account->balance);
            $this->assertSame('10125.50', (string) $account->equity);
            $this->assertSame('45.25', (string) $account->profit_loss);
            $this->assertSame('connected', data_get($account->meta, 'mt5_sync.status'));
            $this->assertSame($metaApiAccountId, data_get($account->meta, 'mt5_sync.metaapi_account_id'));
            $this->assertSame(1, data_get($account->meta, 'mt5_sync.last_payload_summary.positions_count'));
            $this->assertSame(1, data_get($account->meta, 'mt5_sync.last_payload_summary.trade_history_rows'));

            $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();
            $this->assertSame($metaApiAccountId, data_get($snapshot->payload, 'metaapi_account_id'));
            $this->assertSame(320.10, data_get($snapshot->payload, 'margin'));
            $this->assertSame(9805.40, data_get($snapshot->payload, 'free_margin'));

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Challenge Balance')
                ->assertSee('$10,080.25')
                ->assertSee('Challenge Equity')
                ->assertSee('$10,125.50')
                ->assertSee('Floating P&amp;L', false)
                ->assertSee('$45.25')
                ->assertSee('XAUUSD')
                ->assertSee('EURUSD')
                ->assertSee('MetaApi');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_metaapi_first_sync_activates_lifecycle_and_records_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        try {
            $account = $this->createMetaApiChallengeAccount('340150');
            $metaApiAccountId = $this->attachMetaApiPoolEntry($account, 'abababab-abab-4bab-8bab-abababababab');

            $this->fakeMetaApiSync($metaApiAccountId, [
                'balance' => 10005,
                'equity' => 10015,
                'login' => '340150',
            ], [
                [
                    'id' => 'P-LIFE-1',
                    'symbol' => 'EURUSD',
                    'profit' => 10,
                    'volume' => 0.1,
                ],
            ]);

            $result = app(MetaApiLiveSyncService::class)->syncByLogin('340150');

            $account->refresh();

            $this->assertSame('success', $result['status']);
            $this->assertSame('connected', data_get($account->meta, 'metaapi_lifecycle.state'));
            $this->assertSame('connected', data_get($account->meta, 'metaapi_lifecycle.sync_health'));
            $this->assertSame('active', $account->account_status);
            $this->assertSame('active', $account->challenge_status);
            $this->assertNotNull($account->activated_at);

            $events = collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all();
            $this->assertContains('account_activated', $events);
            $this->assertContains('account_connected', $events);

            $this->actingAs($account->user)
                ->get(route('dashboard.accounts'))
                ->assertOk()
                ->assertSee('Lifecycle')
                ->assertSee('Sync health')
                ->assertSee('Connected');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_phase_2_onboarding_first_sync_marks_ready_to_trade_and_dashboard_indicator(): void
    {
        $account = $this->createMetaApiChallengeAccount('340160');
        $metaApiAccountId = $this->attachMetaApiPoolEntry($account, 'eaeaeaea-eaea-4aea-8aea-eaeaeaeaeaea');

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10025,
            'equity' => 10040,
            'login' => '340160',
        ], [
            [
                'id' => 'P-ONBOARD-1',
                'symbol' => 'EURUSD',
                'profit' => 15,
                'volume' => 0.1,
            ],
        ]);

        $result = app(MetaApiLiveSyncService::class)->syncByLogin('340160');

        $account->refresh();

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['phase_2_ready']);
        $this->assertSame('ready_to_trade', $result['onboarding_state']);
        $this->assertSame('ready_to_trade', data_get($account->meta, 'metaapi_onboarding.state'));
        $this->assertTrue((bool) data_get($account->meta, 'metaapi_onboarding.ready_to_trade'));
        $this->assertNotNull(data_get($account->meta, 'metaapi_onboarding.first_sync_received_at'));
        $this->assertNotNull(data_get($account->meta, 'metaapi_onboarding.completed_at'));

        $events = collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all();
        $this->assertContains('ready_to_trade', $events);
        $this->assertContains('onboarding_completed', $events);

        $this->actingAs($account->user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('Onboarding')
            ->assertSee('Ready To Trade')
            ->assertSee('Ready to trade')
            ->assertSee('Yes');
    }

    public function test_phase_2_pool_assignment_command_assigns_available_pool_account_and_blocks_duplicates(): void
    {
        $account = $this->createMetaApiChallengeAccount('340161', [
            'platform_login' => null,
            'platform_account_id' => null,
            'platform_environment' => 'FusionMarkets-Demo',
            'meta' => [],
        ]);
        $metaApiAccountId = 'fafafafa-fafa-4afa-8afa-fafafafafafa';

        $entry = Mt5AccountPoolEntry::factory()->create([
            'login' => '340161',
            'password' => 'assignment-pass',
            'investor_password' => 'assignment-investor',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'metaapi_account_id' => $metaApiAccountId,
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        $exitCode = Artisan::call('wolforix:assign-pool-account', [
            'login' => $account->account_reference,
            '--pool-login' => '340161',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('assigned', Artisan::output());

        $account->refresh();
        $entry->refresh();

        $this->assertSame($account->id, $entry->allocated_trading_account_id);
        $this->assertSame('340161', $account->platform_login);
        $this->assertSame('FusionMarkets-Demo', $account->platform_environment);
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'mt5_sync.metaapi_account_id'));
        $this->assertSame('waiting_metaapi_connection', data_get($account->meta, 'metaapi_onboarding.state'));
        $this->assertContains('account_assigned', collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all());

        $otherAccount = $this->createMetaApiChallengeAccount('340162', [
            'account_size' => 25000,
            'platform_login' => null,
            'platform_account_id' => null,
        ]);

        $duplicateExit = Artisan::call('wolforix:assign-pool-account', [
            'login' => $otherAccount->account_reference,
            '--pool-login' => '340161',
        ]);

        $this->assertSame(1, $duplicateExit);
        $this->assertStringContainsString('pool_entry_allocated_elsewhere', Artisan::output());
        $this->assertSame($account->id, $entry->fresh()->allocated_trading_account_id);
    }

    public function test_phase_2_onboarding_diagnostics_and_event_commands_report_readiness(): void
    {
        $account = $this->createMetaApiChallengeAccount('340163');
        $metaApiAccountId = $this->attachMetaApiPoolEntry($account, 'fbfbfbfb-fbfb-4bfb-8bfb-fbfbfbfbfbfb');

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10010,
            'equity' => 10018,
            'login' => '340163',
        ]);

        app(MetaApiLiveSyncService::class)->syncByLogin('340163');

        $this->assertSame(0, Artisan::call('wolforix:diagnose-onboarding', [
            'login' => '340163',
            '--json' => true,
        ]));
        $this->assertStringContainsString('Onboarding state', Artisan::output());
        $this->assertSame('ready_to_trade', data_get($account->fresh()->meta, 'metaapi_onboarding.state'));

        $this->assertSame(0, Artisan::call('wolforix:diagnose-lifecycle-readiness', [
            'login' => '340163',
        ]));
        $this->assertStringContainsString('MetaApi lifecycle readiness diagnostics', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:test-onboarding-events', [
            'login' => '340163',
        ]));
        $this->assertStringContainsString('Phase 2 onboarding event hook test', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:diagnose-pool-assignment'));
        $this->assertStringContainsString('Phase 2 pool assignment diagnostics', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:test-discord-webhook'));
        $this->assertStringContainsString('Discord webhook readiness', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:test-telegram-webhook'));
        $this->assertStringContainsString('Telegram webhook readiness', Artisan::output());

        $account->refresh();
        $events = collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all();
        $this->assertContains('test_ready_to_trade', $events);
        $this->assertContains('test_onboarding_completed', $events);
    }

    public function test_phase_2b_ready_to_trade_is_derived_from_connected_readable_lifecycle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-27 10:00:00'));

        try {
            $account = $this->createMetaApiChallengeAccount('340164', [
                'account_status' => 'active',
                'challenge_status' => 'active',
                'platform_status' => 'connected',
                'sync_status' => 'success',
                'sync_source' => 'metaapi',
                'last_synced_at' => now(),
                'balance' => 10050,
                'equity' => 10075,
            ]);
            $this->attachMetaApiPoolEntry($account, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee');
            $meta = is_array($account->fresh()->meta) ? $account->fresh()->meta : [];
            data_set($meta, 'metaapi_onboarding.state', 'ready_to_trade');
            data_set($meta, 'metaapi_onboarding.ready_to_trade', false);
            data_set($meta, 'metaapi_lifecycle.state', 'connected');
            data_set($meta, 'metaapi_lifecycle.sync_health', 'connected');
            data_set($meta, 'metaapi_lifecycle.core_sync_health', 'connected');
            data_set($meta, 'mt5_sync.status', 'connected');
            data_set($meta, 'mt5_sync.last_successful_metric_update_at', now()->toIso8601String());
            $account->forceFill(['meta' => $meta])->save();

            $diagnostic = app(MetaApiOnboardingService::class)->diagnose($account);

            $this->assertTrue(data_get($diagnostic, 'sync_readiness.ready_to_trade'));
            $this->assertFalse(data_get($diagnostic, 'sync_readiness.stored_ready_to_trade'));
            $this->assertContains('ready_to_trade_flag_inconsistent', $diagnostic['warnings']);

            $this->assertSame(0, Artisan::call('wolforix:diagnose-onboarding', [
                'login' => '340164',
                '--json' => true,
            ]));
            $this->assertStringContainsString('"ready_to_trade": true', Artisan::output());

            $this->assertSame(0, Artisan::call('wolforix:diagnose-lifecycle-readiness', [
                'login' => '340164',
                '--json' => true,
            ]));
            $this->assertStringContainsString('"ready_to_trade": true', Artisan::output());

            $this->actingAs($account->user)
                ->get(route('dashboard.accounts'))
                ->assertOk()
                ->assertSee('Ready To Trade')
                ->assertSee('Ready to trade')
                ->assertSee('Yes');

            data_set($meta, 'metaapi_lifecycle.sync_health', 'stale');
            data_set($meta, 'metaapi_lifecycle.core_sync_health', 'stale');
            $account->forceFill([
                'platform_status' => 'connected',
                'meta' => $meta,
            ])->save();

            $staleDiagnostic = app(MetaApiOnboardingService::class)->diagnose($account);

            $this->assertFalse(data_get($staleDiagnostic, 'sync_readiness.ready_to_trade'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_visibility_finalization_shows_metaapi_status_for_trader_admin_and_command(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-27 12:00:00'));

        try {
            $account = $this->createMetaApiChallengeAccount('340134');
            $this->markMetaApiDashboardReady($account, '7ed465cc-2315-4311-b4a1-4cc90f66e332');

            $secondAccount = $this->createChallengeAccount('two_step', [
                'platform' => 'MT5',
                'platform_slug' => 'mt5',
                'platform_login' => '335400',
                'platform_account_id' => '335400',
                'platform_environment' => 'FusionMarkets-Demo',
                'platform_status' => 'waiting_for_first_sync',
                'sync_status' => 'pending',
            ]);
            $this->markMetaApiDashboardReady($secondAccount, 'ed749805-4cad-4622-a0bc-3b1c8dd241d2');
            $this->createOutOfScopeMetaApiProblemAccount('335436', 5000);
            $this->createOutOfScopeMetaApiProblemAccount('52841770', 25000);
            $this->createOutOfScopeMetaApiProblemAccount('52841775', 50000);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Dashboard source')
                ->assertSee('MetaApi')
                ->assertSee('Challenge Balance')
                ->assertSee('$10,000.00')
                ->assertSee('Challenge Equity')
                ->assertSee('Connected')
                ->assertSee('Ready to trade')
                ->assertSee('Phase 1 ready')
                ->assertSee('Yes')
                ->assertSee('Open positions')
                ->assertDontSee('7ed465cc-2315-4311-b4a1-4cc90f66e332')
                ->assertDontSee('metaapi-token-secret');

            $this->actingAs($account->user)
                ->get(route('dashboard.accounts'))
                ->assertOk()
                ->assertSee('Data source')
                ->assertSee('MetaApi')
                ->assertSee('Sync health')
                ->assertSee('Connected')
                ->assertSee('Ready To Trade')
                ->assertSee('Phase 1 ready')
                ->assertDontSee('7ed465cc-2315-4311-b4a1-4cc90f66e332')
                ->assertDontSee('metaapi-token-secret');

            $adminIndexResponse = $this->withSession([
                'admin.authenticated' => true,
                'admin.username' => 'admin',
            ])->get(route('admin.clients.index'))
                ->assertOk()
                ->assertSee('Validated MetaApi accounts')
                ->assertSee('340134')
                ->assertSee('335400')
                ->assertSee('MetaApi')
                ->assertSee('Phase 1 visibility scope only')
                ->assertSee('Click View Metrics to review balance, equity, drawdown, positions, history, and sync details.')
                ->assertSee('Actions')
                ->assertSee('View Metrics')
                ->assertSee(route('admin.clients.metrics', ['user' => $account->user_id, 'account' => $account->id]), false)
                ->assertSee(route('admin.clients.metrics', ['user' => $secondAccount->user_id, 'account' => $secondAccount->id]), false)
                ->assertDontSee('metaapi-token-secret');
            $metaApiSummary = $adminIndexResponse->viewData('metaApiSummary');

            $this->assertSame(2, $metaApiSummary['total']);
            $this->assertSame(2, $metaApiSummary['connected']);
            $this->assertSame(0, $metaApiSummary['disconnected']);
            $this->assertSame(0, $metaApiSummary['sync_issues']);
            $this->assertSame(0, $metaApiSummary['onboarding_queue']);
            $this->assertSame(2, $metaApiSummary['ready_to_trade']);

            $this->withSession([
                'admin.authenticated' => true,
                'admin.username' => 'admin',
            ])->get(route('admin.clients.show', ['user' => $account->user, 'account' => $account->id]))
                ->assertOk()
                ->assertSee('Sync Source')
                ->assertSee('MetaApi')
                ->assertSee('Lifecycle state')
                ->assertSee('Sync health')
                ->assertSee('Onboarding')
                ->assertSee('Ready to trade')
                ->assertSee('Latest log')
                ->assertSee('Success')
                ->assertDontSee('metaapi-token-secret');

            $this->withSession([
                'admin.authenticated' => true,
                'admin.username' => 'admin',
            ])->get(route('admin.clients.metrics', ['user' => $account->user, 'account' => $account->id]))
                ->assertOk()
                ->assertSee('Dashboard source')
                ->assertSee('MetaApi')
                ->assertSee('Ready to trade')
                ->assertSee('Phase 1 ready')
                ->assertSee('Open positions')
                ->assertDontSee('metaapi-token-secret');

            $this->assertSame(0, Artisan::call('wolforix:dashboard-visibility-check', [
                '--json' => true,
            ]));
            $output = Artisan::output();

            $this->assertStringContainsString('Dashboard visibility check', $output);
            $this->assertStringContainsString('validated accounts checked: 2', $output);
            $this->assertStringContainsString('trader dashboard data readiness: ready', $output);
            $this->assertStringContainsString('admin dashboard data readiness: ready', $output);
            $this->assertStringContainsString('340134', $output);
            $this->assertStringContainsString('335400', $output);
            $this->assertStringContainsString('"sync_source": "MetaApi"', $output);
            $this->assertStringNotContainsString('7ed465cc-2315-4311-b4a1-4cc90f66e332', $output);
            $this->assertStringNotContainsString('ed749805-4cad-4622-a0bc-3b1c8dd241d2', $output);
            $this->assertStringNotContainsString('metaapi-token-secret', $output);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createOutOfScopeMetaApiProblemAccount(string $login, int $accountSize): TradingAccount
    {
        return $this->createChallengeAccount('one_step', [
            'account_size' => $accountSize,
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $login,
            'platform_account_id' => $login,
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'disconnected',
            'sync_status' => 'error',
            'sync_source' => 'metaapi',
            'sync_error' => 'metaapi_account_id_missing',
            'meta' => [
                'metaapi_onboarding' => [
                    'state' => 'waiting_metaapi_connection',
                ],
                'metaapi_lifecycle' => [
                    'state' => 'disconnected',
                    'sync_health' => 'disconnected',
                ],
                'mt5_sync' => [
                    'identifier' => $login,
                    'status' => 'error',
                    'last_error' => 'metaapi_account_id_missing',
                ],
            ],
        ]);
    }

    public function test_dashboard_visibility_shows_stale_metaapi_warning_without_exposing_secrets(): void
    {
        $account = $this->createMetaApiChallengeAccount('340166', [
            'account_status' => 'active',
            'challenge_status' => 'active',
            'platform_status' => 'connected',
            'sync_status' => 'success',
            'sync_source' => 'metaapi',
            'last_synced_at' => now()->subHour(),
            'balance' => 10000,
            'equity' => 10000,
        ]);
        $this->attachMetaApiPoolEntry($account, 'abababab-abab-4bab-8bab-abababababab');

        $meta = is_array($account->fresh()->meta) ? $account->fresh()->meta : [];
        data_set($meta, 'metaapi.token', 'metaapi-token-secret');
        data_set($meta, 'metaapi_lifecycle.state', 'stale');
        data_set($meta, 'metaapi_lifecycle.sync_health', 'stale');
        data_set($meta, 'metaapi_lifecycle.core_sync_health', 'stale');
        data_set($meta, 'metaapi_onboarding.state', 'ready_to_trade');
        data_set($meta, 'mt5_sync.status', 'stale');
        data_set($meta, 'mt5_sync.last_successful_metric_update_at', now()->subHour()->toIso8601String());
        $account->forceFill(['meta' => $meta])->save();

        $this->actingAs($account->user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('Sync attention needed')
            ->assertSee('MetaApi sync is stale')
            ->assertSee('Ready to trade')
            ->assertSee('No')
            ->assertDontSee('metaapi-token-secret')
            ->assertDontSee('abababab-abab-4bab-8bab-abababababab');
    }

    public function test_validated_metaapi_metrics_action_repairs_user_binding_from_pool(): void
    {
        $account = $this->createMetaApiChallengeAccount('340134');
        $userId = $account->user_id;
        $this->markMetaApiDashboardReady($account, '7ed465cc-2315-4311-b4a1-4cc90f66e332')
            ->forceFill(['user_id' => null])
            ->save();

        $this->withSession([
            'admin.authenticated' => true,
            'admin.username' => 'admin',
        ])->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Validated MetaApi accounts')
            ->assertSee('340134')
            ->assertSee('View Metrics')
            ->assertSee(route('admin.clients.metrics', ['user' => $userId, 'account' => $account->id]), false)
            ->assertDontSee('Missing required parameter');

        $this->assertSame($userId, $account->refresh()->user_id);
    }

    public function test_validated_metaapi_metrics_action_does_not_crash_when_account_has_no_user_binding(): void
    {
        $account = $this->createMetaApiChallengeAccount('340134');
        $this->markMetaApiDashboardReady($account, '7ed465cc-2315-4311-b4a1-4cc90f66e332')
            ->forceFill(['user_id' => null])
            ->save();
        Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->update(['allocated_user_id' => null]);

        $this->withSession([
            'admin.authenticated' => true,
            'admin.username' => 'admin',
        ])->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Validated MetaApi accounts')
            ->assertSee('340134')
            ->assertSee('Unavailable')
            ->assertDontSee('Missing required parameter');
    }

    public function test_validated_metaapi_metrics_action_prefers_user_bound_account_over_detached_duplicate(): void
    {
        $account = $this->createMetaApiChallengeAccount('340134');
        $this->markMetaApiDashboardReady($account, '7ed465cc-2315-4311-b4a1-4cc90f66e332');
        $detachedDuplicate = $this->createChallengeAccount('one_step', [
            'account_size' => 5000,
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '340134',
            'platform_account_id' => '340134',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'connected',
            'sync_status' => 'success',
            'sync_source' => 'metaapi',
            'meta' => [
                'metaapi_account_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
                'metaapi_lifecycle' => [
                    'state' => 'connected',
                    'sync_health' => 'connected',
                ],
                'metaapi_onboarding' => [
                    'state' => 'ready_to_trade',
                ],
                'mt5_sync' => [
                    'identifier' => '340134',
                    'metaapi_account_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
                ],
            ],
        ]);
        $detachedDuplicate->forceFill(['user_id' => null])->save();

        $response = $this->withSession([
            'admin.authenticated' => true,
            'admin.username' => 'admin',
        ])->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('View Metrics')
            ->assertSee(route('admin.clients.metrics', ['user' => $account->user_id, 'account' => $account->id]), false);

        $row = collect($response->viewData('metaApiSummary')['validated_account_rows'])
            ->firstWhere('login', '340134');

        $this->assertSame(
            route('admin.clients.metrics', ['user' => $account->user_id, 'account' => $account->id]),
            $row['metrics_url'],
        );
    }

    public function test_phase_2b_webhook_dry_runs_and_final_lifecycle_readiness_commands_are_safe(): void
    {
        $account = $this->createMetaApiChallengeAccount('340165');
        $metaApiAccountId = $this->attachMetaApiPoolEntry($account, 'efefefef-efef-4fef-8fef-efefefefefef');

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10020,
            'equity' => 10030,
            'login' => '340165',
        ]);

        app(MetaApiLiveSyncService::class)->syncByLogin('340165');
        $account->refresh();

        Http::fake();

        $this->assertSame(0, Artisan::call('wolforix:test-discord-webhook', [
            '--dry-run' => true,
            '--json' => true,
        ]));
        $this->assertStringContainsString('"status": "dry_run"', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:test-telegram-webhook', [
            '--dry-run' => true,
            '--json' => true,
        ]));
        $this->assertStringContainsString('"status": "dry_run"', Artisan::output());
        Http::assertNothingSent();

        $eventsBefore = collect((array) data_get($account->fresh()->meta, 'metaapi_events', []))->count();
        $this->assertSame(0, Artisan::call('wolforix:test-onboarding-events', [
            'login' => '340165',
            '--dry-run' => true,
            '--json' => true,
        ]));
        $this->assertStringContainsString('"dry_run": true', Artisan::output());
        $eventsAfter = collect((array) data_get($account->fresh()->meta, 'metaapi_events', []))->count();
        $this->assertSame($eventsBefore, $eventsAfter);

        $this->assertSame(0, Artisan::call('wolforix:diagnose-broker-abstraction', [
            '--json' => true,
        ]));
        $this->assertStringContainsString('Broker abstraction readiness', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:phase1-readiness-report', [
            '--json' => true,
        ]));
        $this->assertStringContainsString('onboarding', Artisan::output());
    }

    public function test_phase_2c_metaapi_sync_anomalies_ignore_legacy_ea_false_positives_for_signoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-27 11:00:00'));

        try {
            $metaApiAccount = $this->createMetaApiChallengeAccount('340134', [
                'account_status' => 'active',
                'challenge_status' => 'active',
                'platform_status' => 'connected',
                'sync_status' => 'success',
                'sync_source' => 'metaapi',
                'last_synced_at' => now()->subHour(),
                'last_sync_completed_at' => now()->subHour(),
                'balance' => 10000,
                'equity' => 10020,
            ]);
            $this->attachMetaApiPoolEntry($metaApiAccount, '12121212-1212-4212-8212-121212121212');
            $meta = is_array($metaApiAccount->fresh()->meta) ? $metaApiAccount->fresh()->meta : [];
            data_set($meta, 'metaapi_lifecycle.state', 'connected');
            data_set($meta, 'metaapi_lifecycle.sync_health', 'connected');
            data_set($meta, 'metaapi_lifecycle.core_sync_health', 'connected');
            data_set($meta, 'mt5_sync.status', 'connected');
            data_set($meta, 'mt5_sync.last_successful_metric_update_at', now()->subHour()->toIso8601String());
            $metaApiAccount->forceFill(['meta' => $meta])->save();

            $legacyAccount = $this->createChallengeAccount('one_step', [
                'account_reference' => 'WFX-MT5-LEGACY-STALE',
                'account_size' => 25000,
                'platform_login' => '990166',
                'platform_account_id' => '990166',
                'platform_status' => 'connected',
                'sync_status' => 'success',
                'sync_source' => 'mt5_ea',
                'last_synced_at' => now()->subHour(),
                'last_sync_completed_at' => now()->subHour(),
                'meta' => [
                    'mt5_sync' => [
                        'status' => 'connected',
                        'last_successful_metric_update_at' => now()->subHour()->toIso8601String(),
                    ],
                ],
            ]);
            $historicalAccount = $this->createChallengeAccount('one_step', [
                'account_reference' => 'WFX-MT5-HISTORICAL-METAAPI',
                'account_size' => 50000,
                'platform_login' => '990167',
                'platform_account_id' => '990167',
                'platform_status' => 'connected',
                'sync_status' => 'error',
                'sync_source' => null,
                'sync_error' => 'metaapi_account_id_missing',
                'sync_error_at' => now()->subHour(),
                'last_synced_at' => null,
                'last_sync_completed_at' => now()->subHour(),
                'meta' => [
                    'mt5_sync' => [
                        'status' => 'pending',
                        'last_error' => 'metaapi_account_id_missing',
                    ],
                ],
            ]);
            $excludedAccount = $this->createChallengeAccount('one_step', [
                'account_reference' => 'WFX-MT5-EXCLUDED-METAAPI',
                'account_size' => 100000,
                'platform_login' => '335436',
                'platform_account_id' => '335436',
                'platform_environment' => 'FusionMarkets-Demo',
                'platform_status' => 'connected',
                'sync_status' => 'error',
                'sync_source' => 'metaapi',
                'sync_error' => 'metaapi_account_id_missing',
                'sync_error_at' => now()->subHour(),
                'meta' => [
                    'metaapi_onboarding' => [
                        'state' => 'waiting_metaapi_connection',
                    ],
                    'mt5_sync' => [
                        'status' => 'pending',
                        'last_error' => 'metaapi_account_id_missing',
                    ],
                ],
            ]);

            $this->assertSame(0, Artisan::call('wolforix:diagnose-sync-anomalies', [
                '--json' => true,
            ]));
            $output = Artisan::output();

            $this->assertStringContainsString('legacy_ea_no_recent_heartbeat_or_metric_sync', $output);
            $this->assertStringContainsString('excluded_by_phase1_scope', $output);
            $this->assertStringContainsString('historical_not_onboarded_metaapi_account', $output);
            $this->assertStringContainsString('explicitly_excluded_login', $output);
            $this->assertStringContainsString('"metaapi_stale": 0', $output);
            $this->assertStringContainsString('"validated_accounts": [', $output);
            $this->assertStringContainsString('"340134"', $output);
            $this->assertStringContainsString('"335400"', $output);
            $this->assertStringContainsString('"active_metaapi_validation_accounts": 1', $output);
            $this->assertStringContainsString('"historical_metaapi_not_onboarded": 1', $output);
            $this->assertStringContainsString('"excluded_by_phase1_scope": 2', $output);
            $this->assertStringContainsString('"metaapi_issues": 0', $output);
            $this->assertStringContainsString('"legacy_ignored_for_metaapi_signoff": 1', $output);
            $this->assertStringContainsString((string) $legacyAccount->platform_login, $output);
            $this->assertStringContainsString((string) $historicalAccount->platform_login, $output);
            $this->assertStringContainsString((string) $excludedAccount->platform_login, $output);

            $this->assertSame(0, Artisan::call('wolforix:phase1-readiness-report', [
                '--json' => true,
            ]));
            $readiness = Artisan::output();

            $this->assertStringContainsString('"status": "ready"', $readiness);
            $this->assertStringContainsString('validated_accounts=[340134,335400]', $readiness);
            $this->assertStringContainsString('1 historical MetaApi records not onboarded into MetaApi', $readiness);
            $this->assertStringContainsString('2 account(s) are excluded by Phase 1 scope and do not affect readiness.', $readiness);
            $this->assertStringContainsString('legacy EA fallback account(s) show stale/disconnected/error sync anomalies and are ignored for MetaApi Phase 1 signoff', $readiness);
            $this->assertStringContainsString('"metaapi_issues": 0', $readiness);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_final_mvp_closeout_onboarded_metaapi_missing_uuid_still_blocks_readiness(): void
    {
        $account = $this->createMetaApiChallengeAccount('340134', [
            'sync_source' => 'metaapi',
            'sync_status' => 'error',
            'sync_error' => 'metaapi_account_id_missing',
            'meta' => [
                'metaapi_onboarding' => [
                    'state' => 'waiting_metaapi_connection',
                ],
                'mt5_sync' => [
                    'status' => 'pending',
                    'last_error' => 'metaapi_account_id_missing',
                ],
            ],
        ]);

        $this->assertSame(0, Artisan::call('wolforix:diagnose-sync-anomalies', [
            '--json' => true,
        ]));
        $output = Artisan::output();

        $this->assertStringContainsString('"active_metaapi_validation_accounts": 1', $output);
        $this->assertStringContainsString('"historical_metaapi_not_onboarded": 0', $output);
        $this->assertStringContainsString('"metaapi_issues": 1', $output);
        $this->assertStringContainsString('sync_error: metaapi_account_id_missing', $output);
        $this->assertStringContainsString((string) $account->platform_login, $output);

        $this->assertSame(0, Artisan::call('wolforix:phase1-readiness-report', [
            '--json' => true,
        ]));
        $readiness = Artisan::output();

        $this->assertStringContainsString('needs_attention', $readiness);
        $this->assertStringContainsString('"metaapi_issues": 1', $readiness);
    }

    public function test_metaapi_repair_command_assigns_pool_entry_and_persists_uuid_without_overwriting(): void
    {
        $account = $this->createMetaApiChallengeAccount('340140', [
            'platform_environment' => 'Fusion Markets Pty - FusionMarkets Demo',
        ]);
        $metaApiAccountId = '66666666-6666-4666-8666-666666666666';

        Mt5AccountPoolEntry::factory()->create([
            'login' => '340140',
            'server' => 'Fusion Markets Pty - FusionMarkets Demo',
            'account_size' => 10000,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'metaapi_account_id' => $metaApiAccountId,
            ],
        ]);

        $exitCode = Artisan::call('wolforix:repair-metaapi-account', [
            'login' => '340140',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('MetaApi account mapping repair', $output);
        $this->assertStringContainsString('assign_pool_to_trading_account', $output);
        $this->assertStringContainsString('normalize_pool_server', $output);

        $entry = Mt5AccountPoolEntry::query()->where('login', '340140')->firstOrFail();
        $account->refresh();

        $this->assertSame($account->id, $entry->allocated_trading_account_id);
        $this->assertSame($account->user_id, $entry->allocated_user_id);
        $this->assertFalse((bool) $entry->is_available);
        $this->assertSame('assigned', $entry->source_status);
        $this->assertSame('FusionMarkets-Demo', $entry->server);
        $this->assertSame('FusionMarkets-Demo', $account->platform_environment);
        $this->assertSame($metaApiAccountId, data_get($entry->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'mt5_sync.metaapi_account_id'));
        $this->assertSame($entry->id, data_get($account->meta, 'mt5_pool_entry.id'));
    }

    public function test_metaapi_repair_command_assigns_unallocated_pool_entry_with_assign_option(): void
    {
        $account = $this->createMetaApiChallengeAccount('340134', [
            'platform_login' => null,
            'platform_account_id' => null,
            'meta' => [
                'mt5_sync' => [
                    'identifier' => '340134',
                ],
            ],
        ]);
        $metaApiAccountId = '7ed465cc-2315-4311-b4a1-4cc90f66e332';

        Mt5AccountPoolEntry::factory()->create([
            'login' => '340134',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'metaapi_account_id' => $metaApiAccountId,
            ],
        ]);

        $exitCode = Artisan::call('wolforix:repair-metaapi-account', [
            'login' => '340134',
            '--assign' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Assign mode', $output);
        $this->assertStringContainsString('login_metadata', $output);
        $this->assertStringContainsString('assign_pool_to_trading_account', $output);

        $entry = Mt5AccountPoolEntry::query()->where('login', '340134')->firstOrFail();
        $account->refresh();

        $this->assertSame($account->id, $entry->allocated_trading_account_id);
        $this->assertSame($account->user_id, $entry->allocated_user_id);
        $this->assertSame('340134', $account->platform_login);
        $this->assertSame('340134', $account->platform_account_id);
        $this->assertSame('340134', data_get($account->meta, 'mt5_sync.identifier'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'metaapi_account_id'));
    }

    public function test_metaapi_repair_merges_duplicate_canonical_pool_row_without_unique_violation(): void
    {
        $account = $this->createMetaApiChallengeAccount('340147', [
            'platform_environment' => 'Fusion Markets Pty - FusionMarkets Demo',
        ]);
        $metaApiAccountId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

        $source = Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => '340147',
                'password' => 'source-secret',
                'investor_password' => 'source-investor',
                'server' => 'Fusion Markets Pty - FusionMarkets Demo',
                'account_size' => 10000,
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'source_status' => 'assigned',
                'meta' => [
                    'metaapi_account_id' => $metaApiAccountId,
                    'legacy_server_row' => true,
                ],
            ]);
        $canonical = Mt5AccountPoolEntry::factory()->create([
            'login' => '340147',
            'password' => 'canonical-secret',
            'investor_password' => 'canonical-investor',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'canonical_server_row' => true,
            ],
        ]);
        $account->forceFill([
            'meta' => array_merge((array) $account->meta, [
                'mt5_pool_entry' => [
                    'id' => $source->id,
                ],
            ]),
        ])->save();

        $exitCode = Artisan::call('wolforix:repair-metaapi-account', [
            'login' => '340147',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('merge_duplicate_canonical_pool_entry', $output);
        $this->assertDatabaseMissing('mt5_account_pool_entries', [
            'id' => $source->id,
        ]);

        $canonical->refresh();
        $account->refresh();

        $this->assertSame($account->id, $canonical->allocated_trading_account_id);
        $this->assertSame($account->user_id, $canonical->allocated_user_id);
        $this->assertSame('FusionMarkets-Demo', $canonical->server);
        $this->assertSame('source-secret', $canonical->password);
        $this->assertSame('source-investor', $canonical->investor_password);
        $this->assertSame($metaApiAccountId, data_get($canonical->meta, 'metaapi_account_id'));
        $this->assertTrue((bool) data_get($canonical->meta, 'legacy_server_row'));
        $this->assertTrue((bool) data_get($canonical->meta, 'canonical_server_row'));
        $this->assertSame($source->id, data_get($canonical->meta, 'canonical_pool_merge.0.merged_from_pool_entry_id'));
        $this->assertSame($canonical->id, data_get($account->meta, 'mt5_pool_entry.id'));
    }

    public function test_metaapi_repair_skips_canonical_normalization_when_duplicate_is_allocated_elsewhere(): void
    {
        $account = $this->createMetaApiChallengeAccount('340148', [
            'platform_environment' => 'Fusion Markets Pty - FusionMarkets Demo',
        ]);
        $otherAccount = $account->replicate();
        $otherAccount->forceFill([
            'account_reference' => 'ACC-ONE-DUP-CANON',
            'platform_login' => '340149',
            'platform_account_id' => '340149',
        ])->save();
        $metaApiAccountId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

        $source = Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => '340148',
                'server' => 'Fusion Markets Pty - FusionMarkets Demo',
                'account_size' => 10000,
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'source_status' => 'assigned',
                'meta' => [
                    'metaapi_account_id' => $metaApiAccountId,
                ],
            ]);
        $canonical = Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => '340148',
                'server' => 'FusionMarkets-Demo',
                'account_size' => 10000,
                'allocated_trading_account_id' => $otherAccount->id,
                'allocated_user_id' => $otherAccount->user_id,
                'source_status' => 'assigned',
                'meta' => [
                    'metaapi_account_id' => $metaApiAccountId,
                ],
            ]);

        $exitCode = Artisan::call('wolforix:repair-metaapi-account', [
            'login' => '340148',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('normalize_pool_server_skipped_duplicate_allocated_elsewhere', $output);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'id' => $source->id,
            'server' => 'Fusion Markets Pty - FusionMarkets Demo',
            'allocated_trading_account_id' => $account->id,
        ]);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'id' => $canonical->id,
            'server' => 'FusionMarkets-Demo',
            'allocated_trading_account_id' => $otherAccount->id,
        ]);
    }

    public function test_metaapi_repair_command_reports_admin_action_when_no_trading_account_exists(): void
    {
        Mt5AccountPoolEntry::factory()->create([
            'login' => '340142',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'metaapi_account_id' => '88888888-8888-4888-8888-888888888888',
            ],
        ]);

        $exitCode = Artisan::call('wolforix:repair-metaapi-account', [
            'login' => '340142',
            '--assign' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('trading_account_missing', $output);
        $this->assertStringContainsString('Admin action required', $output);
        $this->assertStringContainsString('php artisan wolforix:repair-metaapi-account 340142 --assign', $output);
    }

    public function test_list_unassigned_metaapi_accounts_shows_only_unassigned_metaapi_pool_rows(): void
    {
        $candidateAccount = $this->createMetaApiChallengeAccount('340134', [
            'platform_login' => null,
            'platform_account_id' => null,
            'meta' => [
                'mt5_sync' => [
                    'identifier' => '340134',
                ],
            ],
        ]);

        Mt5AccountPoolEntry::factory()->create([
            'login' => '340134',
            'server' => 'FusionMarkets-Demo',
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'metaapi_account_id' => '7ed465cc-2315-4311-b4a1-4cc90f66e332',
            ],
        ]);

        Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => '335400',
                'server' => 'FusionMarkets-Demo',
                'allocated_trading_account_id' => $candidateAccount->id,
                'allocated_user_id' => $candidateAccount->user_id,
                'meta' => [
                    'metaapi_account_id' => 'ed749805-4cad-4622-a0bc-3b1c8dd241d2',
                ],
            ]);

        Mt5AccountPoolEntry::factory()->create([
            'login' => '340143',
            'server' => 'FusionMarkets-Demo',
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'source' => 'no-metaapi-id',
            ],
        ]);

        $exitCode = Artisan::call('wolforix:list-unassigned-metaapi-accounts');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('340134', $output);
        $this->assertStringNotContainsString('335400', $output);
        $this->assertStringNotContainsString('340143', $output);
        $this->assertStringContainsString('wolforix:repair-metaapi-account 340134 --assign', $output);
    }

    public function test_create_metaapi_trading_account_dry_run_prints_plan_without_writing(): void
    {
        Mt5AccountPoolEntry::factory()->create([
            'login' => '340144',
            'server' => 'Fusion Markets Pty - FusionMarkets Demo',
            'account_size' => 10000,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'metaapi_account_id' => '99999999-9999-4999-9999-999999999999',
            ],
        ]);

        $exitCode = Artisan::call('wolforix:create-metaapi-trading-account', [
            'login' => '340144',
            '--dry-run' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DRY RUN ONLY', $output);
        $this->assertStringContainsString('WFX-MT5-340144', $output);
        $this->assertDatabaseMissing('trading_accounts', [
            'platform_login' => '340144',
        ]);
    }

    public function test_create_metaapi_trading_account_creates_safe_row_and_binds_pool_entry(): void
    {
        $user = User::factory()->create();
        $metaApiAccountId = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

        Mt5AccountPoolEntry::factory()->create([
            'login' => '340145',
            'server' => 'Fusion Markets Pty - FusionMarkets Demo',
            'account_size' => 25000,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => $user->id,
            'allocated_at' => null,
            'is_available' => true,
            'source_status' => 'available',
            'meta' => [
                'metaapi_account_id' => $metaApiAccountId,
            ],
        ]);

        $exitCode = Artisan::call('wolforix:create-metaapi-trading-account', [
            'login' => '340145',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Trading account created and pool entry bound', $output);

        $account = TradingAccount::query()->where('platform_login', '340145')->firstOrFail();
        $entry = Mt5AccountPoolEntry::query()->where('login', '340145')->firstOrFail();

        $this->assertSame($user->id, $account->user_id);
        $this->assertSame('340145', $account->platform_account_id);
        $this->assertSame('FusionMarkets-Demo', $account->platform_environment);
        $this->assertSame('WFX-MT5-340145', $account->account_reference);
        $this->assertSame('pending_activation', $account->account_status);
        $this->assertSame('pending_activation', $account->challenge_status);
        $this->assertSame('metaapi', $account->sync_source);
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'metaapi_account_id'));
        $this->assertSame($entry->id, data_get($account->meta, 'mt5_pool_entry.id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'mt5_pool_entry.metaapi_account_id'));
        $this->assertSame($account->id, $entry->allocated_trading_account_id);
        $this->assertSame($user->id, $entry->allocated_user_id);
        $this->assertFalse((bool) $entry->is_available);
        $this->assertSame('assigned', $entry->source_status);
    }

    public function test_create_metaapi_trading_account_refuses_existing_valid_account(): void
    {
        $account = $this->createMetaApiChallengeAccount('340146');

        Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => '340146',
                'server' => 'FusionMarkets-Demo',
                'account_size' => 10000,
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'meta' => [
                    'metaapi_account_id' => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
                ],
            ]);

        $exitCode = Artisan::call('wolforix:create-metaapi-trading-account', [
            'login' => '340146',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('trading_account_already_exists', $output);
        $this->assertSame(1, TradingAccount::query()->where('platform_login', '340146')->count());
    }

    public function test_metaapi_sync_auto_heals_pool_uuid_into_trading_account_before_sync(): void
    {
        $account = $this->createMetaApiChallengeAccount('340141');
        $metaApiAccountId = '77777777-7777-4777-8777-777777777777';
        $account->forceFill([
            'meta' => array_merge((array) $account->meta, [
                'metaapi_account_id' => 'legacy-local-id',
                'mt5_sync' => [
                    'metaapi_account_id' => 'legacy-local-id',
                ],
            ]),
        ])->save();

        Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => '340141',
                'server' => 'FusionMarkets-Demo',
                'account_size' => 10000,
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'meta' => [
                    'metaapi_account_id' => $metaApiAccountId,
                ],
            ]);

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10012,
            'equity' => 10022,
            'login' => '340141',
        ]);

        $result = app(MetaApiLiveSyncService::class)->syncByLogin('340141');

        $account->refresh();

        $this->assertSame('success', $result['status']);
        $this->assertSame('CONNECTED', $result['validation_state']);
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'mt5_sync.metaapi_account_id'));
        $this->assertSame('10012.00', (string) $account->balance);
        $this->assertSame('10022.00', (string) $account->equity);
        $this->assertDatabaseMissing('trading_account_sync_logs', [
            'trading_account_id' => $account->id,
            'error_message' => 'metaapi_account_id_missing',
        ]);
    }

    public function test_metaapi_sync_auto_heals_missing_uuid_from_metaapi_lookup(): void
    {
        $account = $this->createMetaApiChallengeAccount('335400');
        $metaApiAccountId = 'ed749805-4cad-4622-a0bc-3b1c8dd241d2';

        Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => '335400',
                'server' => 'FusionMarkets-Demo',
                'account_size' => 10000,
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'meta' => [
                    'source' => 'missing_metaapi_id_test',
                ],
            ]);

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10033,
            'equity' => 10044,
            'login' => '335400',
        ], includeAccountsLookup: true);

        $result = app(MetaApiLiveSyncService::class)->syncByLogin('335400');

        $entry = Mt5AccountPoolEntry::query()->where('login', '335400')->firstOrFail();
        $account->refresh();

        $this->assertSame('success', $result['status']);
        $this->assertSame($metaApiAccountId, $result['metaapi_account_id']);
        $this->assertSame($metaApiAccountId, data_get($entry->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'metaapi_account_id'));
        $this->assertSame($metaApiAccountId, data_get($account->meta, 'mt5_sync.metaapi_account_id'));
        $this->assertSame('10033.00', (string) $account->balance);
        $this->assertSame('10044.00', (string) $account->equity);
        $this->assertDatabaseMissing('trading_account_sync_logs', [
            'trading_account_id' => $account->id,
            'error_message' => 'metaapi_account_id_missing',
        ]);
    }

    public function test_metaapi_daily_drawdown_breach_from_equity_locks_account_and_notifies(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 09:10:00'));

        try {
            $account = $this->createMetaApiChallengeAccount('340135');
            $metaApiAccountId = $this->attachMetaApiPoolEntry($account, '11111111-1111-4111-8111-111111111111');

            $this->fakeMetaApiSync($metaApiAccountId, [
                'balance' => 10000,
                'equity' => 9500,
                'login' => '340135',
            ], [
                [
                    'id' => 'P-LOSS-1',
                    'symbol' => 'XAUUSD',
                    'profit' => -500,
                    'volume' => 1,
                ],
            ]);

            $result = app(MetaApiLiveSyncService::class)->syncByLogin('340135');

            $this->assertSame('success', $result['status']);

            $account->refresh();

            $this->assertSame('failed', $account->challenge_status);
            $this->assertSame('daily_loss_breached', $account->failure_reason);
            $this->assertTrue((bool) $account->trading_blocked);
            $this->assertTrue((bool) $account->final_state_locked);
            $this->assertSame('disable_pending_ack', $account->platform_status);
            $this->assertSame('metaapi', $account->sync_source);
            $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));
            $this->assertDatabaseHas('trading_account_status_histories', [
                'trading_account_id' => $account->id,
                'previous_status' => 'pending_activation',
                'new_status' => 'failed',
                'source' => 'metaapi',
            ]);
            $this->assertDatabaseHas('trading_account_sync_logs', [
                'trading_account_id' => $account->id,
                'platform' => 'metaapi',
                'status' => 'success',
            ]);
            Mail::assertSent(ChallengeFailedMail::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_metaapi_max_drawdown_breach_from_balance_locks_account(): void
    {
        $account = $this->createMetaApiChallengeAccount('340136');
        $metaApiAccountId = $this->attachMetaApiPoolEntry($account, '22222222-2222-4222-8222-222222222222');

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 9150,
            'equity' => 9700,
            'login' => '340136',
        ], [
            [
                'id' => 'P-MAX-1',
                'symbol' => 'US30',
                'profit' => 550,
                'volume' => 1,
            ],
        ]);

        app(MetaApiLiveSyncService::class)->syncByLogin('340136');

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('max_drawdown_breached', $account->failure_reason);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_max_drawdown_breached.status'));
        Mail::assertSent(ChallengeFailedMail::class, 1);
    }

    public function test_metaapi_breach_failed_locked_account_remains_failed_after_recovery(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 09:20:00'));

        try {
            $account = $this->createMetaApiChallengeAccount('340137');
            $metaApiAccountId = $this->attachMetaApiPoolEntry($account, '33333333-3333-4333-8333-333333333333');

            $this->fakeMetaApiSync($metaApiAccountId, [
                'balance' => 10000,
                'equity' => 9500,
                'login' => '340137',
            ], [
                [
                    'id' => 'P-LOCK-LOSS',
                    'symbol' => 'XAUUSD',
                    'profit' => -500,
                    'volume' => 1,
                ],
            ]);

            app(MetaApiLiveSyncService::class)->syncByLogin('340137');

            $account->refresh();
            $failedAt = $account->failed_at?->toDateTimeString();
            $lockedBalance = (string) $account->balance;
            $lockedEquity = (string) $account->equity;

            Carbon::setTestNow(Carbon::parse('2026-05-26 09:25:00'));
            $this->fakeMetaApiSync($metaApiAccountId, [
                'balance' => 10500,
                'equity' => 10500,
                'login' => '340137',
            ]);

            app(MetaApiLiveSyncService::class)->syncByLogin('340137');

            $account->refresh();

            $this->assertSame('failed', $account->challenge_status);
            $this->assertSame('daily_loss_breached', $account->failure_reason);
            $this->assertSame($failedAt, $account->failed_at?->toDateTimeString());
            $this->assertSame($lockedBalance, (string) $account->balance);
            $this->assertSame($lockedEquity, (string) $account->equity);
            $this->assertSame('connected', data_get($account->meta, 'mt5_sync.status'));
            $this->assertSame(2, $account->syncLogs()->where('platform', 'metaapi')->count());
            Mail::assertSent(ChallengeFailedMail::class, 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_metaapi_stale_disconnected_account_shows_warning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 09:30:00'));

        try {
            config()->set('services.metaapi.sync.stale_minutes', 10);

            $account = $this->createMetaApiChallengeAccount('340138', [
                'sync_source' => 'metaapi',
                'last_synced_at' => Carbon::parse('2026-05-26 09:00:00'),
                'meta' => [
                    'mt5_sync' => [
                        'status' => 'connected',
                        'metaapi_account_id' => '44444444-4444-4444-8444-444444444444',
                        'last_successful_metric_update_at' => '2026-05-26T09:00:00+00:00',
                    ],
                ],
            ]);
            $metaApiAccountId = $this->attachMetaApiPoolEntry($account, '44444444-4444-4444-8444-444444444444');

            Http::fake([
                "https://metaapi-provisioning.test/users/current/accounts/{$metaApiAccountId}" => Http::response([
                    '_id' => $metaApiAccountId,
                    'login' => '340138',
                    'server' => 'FusionMarkets-Demo',
                    'state' => 'DEPLOYED',
                    'connectionStatus' => 'DISCONNECTED',
                    'region' => 'london',
                ]),
                "https://metaapi-client.test/users/current/accounts/{$metaApiAccountId}/account-information*" => Http::response([
                    'message' => 'Terminal state is not connected.',
                ], 504),
                "https://metaapi-client.test/users/current/accounts/{$metaApiAccountId}/positions*" => Http::response([
                    'message' => 'Terminal state is not connected.',
                ], 504),
            ]);

            $result = app(MetaApiLiveSyncService::class)->syncByLogin('340138');

            $this->assertSame('error', $result['status']);

            $account->refresh();
            $status = app(Mt5ConnectorStatus::class)->forAccount($account);

            $this->assertSame('error', $account->sync_status);
            $this->assertSame('disconnected', $account->platform_status);
            $this->assertSame('stale', $status['status']);
            $this->assertTrue($status['is_stale']);
            $this->assertStringContainsString('MetaApi cloud terminal', $status['message']);

            $this->actingAs($account->user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Disconnected/Stale')
                ->assertSee('MetaApi cloud terminal');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_metaapi_metrics_history_timeout_does_not_fail_full_sync(): void
    {
        $account = $this->createMetaApiChallengeAccount('340139');
        $metaApiAccountId = $this->attachMetaApiPoolEntry($account, '55555555-5555-4555-8555-555555555555');

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10020,
            'equity' => 10010,
            'login' => '340139',
        ], [
            [
                'id' => 'P-HISTORY-TIMEOUT',
                'symbol' => 'GBPUSD',
                'profit' => -10,
                'volume' => 0.2,
            ],
        ], historyThrows: true);

        $result = app(MetaApiLiveSyncService::class)->syncByLogin('340139');

        $account->refresh();

        $this->assertSame('partial', $result['status']);
        $this->assertSame('PARTIAL_CONNECTED', $result['validation_state']);
        $this->assertTrue($result['account_information_readable']);
        $this->assertTrue($result['positions_readable']);
        $this->assertFalse($result['history_readable']);
        $this->assertSame('success', $account->sync_status);
        $this->assertSame('metaapi', $account->sync_source);
        $this->assertSame('10020.00', (string) $account->balance);
        $this->assertSame('10010.00', (string) $account->equity);
        $this->assertSame('partial', TradingAccountSyncLog::query()->latest('id')->value('status'));
        $this->assertSame('degraded', data_get($account->balanceSnapshots()->latest('id')->firstOrFail()->payload, 'history_status'));
        $this->assertSame('degraded', data_get($account->fresh()->meta, 'metaapi_lifecycle.sync_health'));
        $this->assertContains('sync_failure', collect((array) data_get($account->fresh()->meta, 'metaapi_events', []))->pluck('type')->all());
    }

    public function test_metaapi_disconnect_then_reconnect_records_recovery_lifecycle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:20:00'));

        try {
            config()->set('services.metaapi.sync.retry_delay_ms', 0);
            $account = $this->createMetaApiChallengeAccount('340151', [
                'sync_source' => 'metaapi',
                'platform_status' => 'disconnected',
                'sync_status' => 'error',
                'last_synced_at' => Carbon::parse('2026-05-26 09:40:00'),
                'meta' => [
                    'metaapi_lifecycle' => [
                        'state' => 'disconnected',
                        'sync_health' => 'disconnected',
                        'last_disconnected_at' => '2026-05-26T09:45:00+00:00',
                    ],
                    'mt5_sync' => [
                        'status' => 'disconnected',
                        'last_successful_metric_update_at' => '2026-05-26T09:40:00+00:00',
                    ],
                ],
            ]);
            $metaApiAccountId = $this->attachMetaApiPoolEntry($account, 'bcbcbcbc-bcbc-4cbc-8cbc-bcbcbcbcbcbc');

            Carbon::setTestNow(Carbon::parse('2026-05-26 10:25:00'));
            $this->fakeMetaApiSync($metaApiAccountId, [
                'balance' => 10011,
                'equity' => 10021,
                'login' => '340151',
            ]);

            $recovered = app(MetaApiLiveSyncService::class)->syncByLogin('340151');
            $this->assertSame('success', $recovered['status']);

            $account->refresh();
            $this->assertSame('connected', data_get($account->meta, 'metaapi_lifecycle.state'));
            $this->assertSame('recovered', data_get($account->meta, 'metaapi_lifecycle.sync_health'));
            $this->assertSame(1, data_get($account->meta, 'metaapi_lifecycle.recovery_count'));

            $events = collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all();
            $this->assertContains('account_recovered', $events);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_metaapi_breach_notification_content_is_supportive_and_commands_record_events(): void
    {
        $account = $this->createMetaApiChallengeAccount('340152');
        $metaApiAccountId = $this->attachMetaApiPoolEntry($account, 'cdcdcdcd-cdcd-4dcd-8dcd-cdcdcdcdcdcd');

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10000,
            'equity' => 9490,
            'login' => '340152',
        ], [
            [
                'id' => 'P-NOTIFY-1',
                'symbol' => 'XAUUSD',
                'profit' => -510,
                'volume' => 1,
            ],
        ]);

        app(MetaApiLiveSyncService::class)->syncByLogin('340152');
        $account->refresh();

        Mail::assertSent(ChallengeFailedMail::class, function (ChallengeFailedMail $mail): bool {
            $rendered = $mail->render();

            return str_contains($rendered, 'challenge cannot continue in its current phase')
                && str_contains($rendered, 'future participation')
                && str_contains($rendered, 'useful feedback rather than a setback');
        });

        $this->assertSame('breached', data_get($account->meta, 'metaapi_lifecycle.state'));
        $this->assertContains('challenge_breached', collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all());

        $exitCode = Artisan::call('wolforix:test-breach-notification', [
            'login' => '340152',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No external email', Artisan::output());

        $account->refresh();
        $this->assertContains('breach_notification_tested', collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all());
    }

    public function test_metaapi_lifecycle_diagnostics_and_event_test_commands_report_state(): void
    {
        $account = $this->createMetaApiChallengeAccount('340153');
        $metaApiAccountId = $this->attachMetaApiPoolEntry($account, 'dededede-dede-4ede-8ede-dededededede');

        $this->fakeMetaApiSync($metaApiAccountId, [
            'balance' => 10001,
            'equity' => 10002,
            'login' => '340153',
        ]);

        app(MetaApiLiveSyncService::class)->syncByLogin('340153');

        $this->assertSame(0, Artisan::call('wolforix:diagnose-account-lifecycle', [
            'login' => '340153',
        ]));
        $this->assertStringContainsString('Lifecycle state', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:diagnose-sync-health', [
            'login' => '340153',
        ]));
        $this->assertStringContainsString('Sync health', Artisan::output());

        $this->assertSame(0, Artisan::call('wolforix:test-metaapi-events', [
            'login' => '340153',
        ]));
        $this->assertStringContainsString('MetaApi event hook test', Artisan::output());

        $account->refresh();
        $events = collect((array) data_get($account->meta, 'metaapi_events', []))->pluck('type')->all();
        $this->assertContains('test_account_connected', $events);
        $this->assertContains('test_sync_failure', $events);
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
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11020, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'passed')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('final_state_locked', true)
            ->assertJsonPath('close_positions_required', true)
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_event', 'pass_finalized')
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('passed', $account->challenge_status);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertSame('disable_pending_ack', $account->platform_status);
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.pass_finalized.status'));
        $this->assertNotEmpty(data_get($account->meta, 'support_notifications.events.pass_finalized.notified_at'));
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
        Mail::assertSent(TrustpilotReviewRequestMail::class, function (TrustpilotReviewRequestMail $mail): bool {
            return $mail->reviewUrl === 'https://de.trustpilot.com/review/wolforix.com'
                && ! str_contains($mail->reviewUrl, '?')
                && ! $mail->reminder;
        });
        Mail::assertSent(ChallengePhasePassSupportNotificationMail::class, function (ChallengePhasePassSupportNotificationMail $mail) use ($account): bool {
            return $mail->hasTo((string) config('wolforix.support.email'))
                && $mail->details['account_reference'] === $account->account_reference
                && $mail->details['client_email'] === $account->user->email
                && $mail->details['phase'] === 'Single Phase'
                && $mail->details['reason'] === 'Passed'
                && $mail->details['mt5_deactivation_status'] === 'Disable Pending Ack';
        });

        $this->assertSame('https://de.trustpilot.com/review/wolforix.com', config('wolforix.review_requests.trustpilot.url'));
        $this->assertNotEmpty(data_get($account->meta, 'trustpilot_review.initial_requested_at'));
        $this->assertNotEmpty(data_get($account->meta, 'trustpilot_review.reminder_due_at'));

        $certificatePath = (string) $account->certificate_path;
        $certificateGeneratedAt = $account->certificate_generated_at?->toDateTimeString();
        $passedEmailSentAt = $account->passed_email_sent_at?->toDateTimeString();
        $fundedPassEmailSentAt = $account->funded_pass_email_sent_at?->toDateTimeString();
        $lockedBalance = (string) $account->balance;
        $lockedEquity = (string) $account->equity;
        $lockedTradingDays = (int) $account->trading_days_completed;

        $this->pushMetrics($account, '2026-04-07 09:00:10', 11080, 11040, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'passed')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame($lockedBalance, (string) $account->balance);
        $this->assertSame($lockedEquity, (string) $account->equity);
        $this->assertSame($lockedTradingDays, (int) $account->trading_days_completed);
        $this->assertSame($certificatePath, (string) $account->certificate_path);
        $this->assertSame($certificateGeneratedAt, $account->certificate_generated_at?->toDateTimeString());
        $this->assertSame($passedEmailSentAt, $account->passed_email_sent_at?->toDateTimeString());
        $this->assertSame($fundedPassEmailSentAt, $account->funded_pass_email_sent_at?->toDateTimeString());

        $this->pushMetrics($account, '2026-04-07 09:00:20', 11080, 11040, [
            'trade_count' => 0,
            'trading_blocked_ack' => true,
        ])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_required', false)
            ->assertJsonPath('mt5_deactivation_status', 'disabled')
            ->assertJsonPath('ea_action', 'block_trading');

        $account->refresh();

        $this->assertSame('disabled', $account->platform_status);
        $this->assertSame('disabled', data_get($account->meta, 'mt5_deactivation.events.pass_finalized.status'));

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Challenge passed')
            ->assertSee('MT5 disabled')
            ->assertSee('MT5 disable status')
            ->assertSee('Disabled');

        $this->actingAs($account->user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('MT5 disable status')
            ->assertSee('Disabled');

        Mail::assertSent(ChallengePassedMail::class, 1);
        Mail::assertSent(TrustpilotReviewRequestMail::class, 1);
        Mail::assertSent(ChallengePhasePassSupportNotificationMail::class, 1);
    }

    public function test_dashboard_certificate_download_uses_authenticated_route(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'account_reference' => 'WFX-CT-00001-CERT',
        ]);

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10400, 10380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 10800, 10780, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11020, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $downloadUrl = route('dashboard.certificates.download', $account);

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($downloadUrl, false)
            ->assertDontSee('/storage/certificates/', false);

        $this->actingAs($account->user)
            ->get($downloadUrl)
            ->assertOk()
            ->assertDownload('wolforix-certificate-wfx-ct-00001-cert.png');

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get($downloadUrl)
            ->assertForbidden();
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
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_event', 'fail_daily_loss_breached')
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('daily_loss_breached', $account->failure_reason);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertSame('disable_pending_ack', $account->platform_status);
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));
        $this->assertNotNull($account->failed_at);
        $this->assertNotNull($account->failed_email_sent_at);
        Mail::assertSent(ChallengeFailedMail::class, function (ChallengeFailedMail $mail) use ($account): bool {
            return $mail->hasTo($account->user->email)
                && $mail->details['client_name'] === $account->user->name
                && $mail->details['client_email'] === $account->user->email
                && $mail->details['account_reference'] === $account->account_reference
                && $mail->details['mt5_login'] === $account->platform_login
                && $mail->details['rule'] === 'Daily loss limit'
                && $mail->details['reason'] === 'Daily loss limit'
                && $mail->details['violation_timestamp'] === $account->failed_at?->toDateTimeString()
                && $mail->details['final_account_status'] === 'Failed';
        });
        Mail::assertSent(TrustpilotReviewRequestMail::class, 1);
        Mail::assertNotSent(ChallengePhasePassSupportNotificationMail::class);
        $this->assertNotEmpty(data_get($account->meta, 'trustpilot_review.initial_requested_at'));
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
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_max_drawdown_breached.status'));
        Mail::assertSent(ChallengeFailedMail::class, 1);
    }

    public function test_mt5_floating_equity_breach_from_screenshot_fails_permanently_on_first_sync(): void
    {
        $account = $this->createChallengeAccount('two_step', [
            'account_reference' => 'WFX-MT5-335405',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'highest_equity_today' => 0,
            'rule_state' => [],
        ]);

        $this->pushMetrics($account, '2026-05-22 10:15:00', 9915.42, 8494.92, [
            'open_profit' => -1420.50,
            'profit_loss' => -1420.50,
            'positions_count' => 2,
            'trade_count' => 1,
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
        ])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('failure_reason', 'daily_loss_breached')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('final_state_locked', true)
            ->assertJsonPath('mt5_deactivation_event', 'fail_daily_loss_breached')
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('daily_loss_breached', $account->failure_reason);
        $this->assertTrue((bool) data_get($account->rule_state, 'daily_drawdown_breached'));
        $this->assertTrue((bool) data_get($account->rule_state, 'max_drawdown_breached'));
        $this->assertSame(1505.08, (float) data_get($account->rule_state, 'daily_loss_used'));
        $this->assertSame(1505.08, (float) data_get($account->rule_state, 'max_drawdown_used'));
        $this->assertSame(8494.92, (float) data_get($account->failure_context, 'equity_at_breach'));
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));

        $failedAt = $account->failed_at?->toDateTimeString();
        $disableRequestedAt = (string) data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.requested_at');
        $lockedBalance = (string) $account->balance;
        $lockedEquity = (string) $account->equity;

        $this->pushMetrics($account, '2026-05-22 10:30:00', 9785.36, 9785.36, [
            'open_profit' => 0,
            'profit_loss' => 0,
            'positions_count' => 0,
            'trade_count' => 0,
            'has_activity' => false,
            'platform_login' => '335405',
        ])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('failure_reason', 'daily_loss_breached')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('mt5_deactivation_event', 'fail_daily_loss_breached')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame($failedAt, $account->failed_at?->toDateTimeString());
        $this->assertSame($disableRequestedAt, (string) data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.requested_at'));
        $this->assertSame($lockedBalance, (string) $account->balance);
        $this->assertSame($lockedEquity, (string) $account->equity);
        $this->assertSame(1, (int) data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.attempts'));
        Mail::assertSent(ChallengeFailedMail::class, 1);
    }

    public function test_final_state_locked_account_never_reopens_even_if_status_was_inconsistent(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'status' => 'Active',
            'account_status' => 'active',
            'challenge_status' => 'active',
            'failure_reason' => 'daily_loss_breached',
            'failed_at' => Carbon::parse('2026-05-22 10:15:00'),
            'trading_blocked' => true,
            'final_state_locked' => true,
            'balance' => 9500,
            'equity' => 9500,
        ]);

        $this->pushMetrics($account, '2026-05-22 10:30:00', 11050, 11050, [
            'trade_count' => 1,
            'challenge_status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('failure_reason', 'daily_loss_breached')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('final_state_locked', true);

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('failed', $account->account_status);
        $this->assertSame('Failed', $account->status);
        $this->assertSame('9500.00', (string) $account->equity);
        $this->assertTrue((bool) $account->final_state_locked);
    }

    public function test_breach_invalidation_diagnostic_reports_and_can_fix_unlocked_breached_account(): void
    {
        $account = $this->createChallengeAccount('two_step', [
            'account_reference' => 'WFX-MT5-DIAG-335405',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'balance' => 9785.36,
            'equity' => 9785.36,
            'highest_equity_today' => 0,
            'rule_state' => [],
        ]);

        $account->balanceSnapshots()->create([
            'snapshot_at' => Carbon::parse('2026-05-22 10:15:00'),
            'balance' => 9915.42,
            'equity' => 8494.92,
            'profit_loss' => -1420.50,
            'total_profit' => -84.58,
            'today_profit' => -84.58,
            'daily_drawdown' => 0,
            'max_drawdown' => 1505.08,
            'payload' => [
                'balance' => 9915.42,
                'equity' => 8494.92,
                'open_profit' => -1420.50,
                'platform_login' => '335405',
                'platform_environment' => 'FusionMarkets-Demo',
            ],
        ]);

        $exitCode = Artisan::call('wolforix:diagnose-breach-invalidation', [
            'account' => '335405',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Breach invalidation diagnosis', $output);
        $this->assertStringContainsString('account_login', $output);
        $this->assertStringContainsString('335405', $output);
        $this->assertStringContainsString('FusionMarkets-Demo', $output);
        $this->assertStringContainsString('daily_breach', $output);
        $this->assertStringContainsString('max_total_breach', $output);
        $this->assertStringContainsString('FAIL: breach evidence exists but the account is not permanently failed/locked.', $output);

        $exitCode = Artisan::call('wolforix:diagnose-breach-invalidation', [
            'account' => '335405',
            '--fix' => true,
            '--confirm' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Repair applied.', $output);

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('daily_loss_breached', $account->failure_reason);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));
    }

    public function test_breach_invalidation_diagnostic_explains_missing_mt5_sync_data(): void
    {
        $account = $this->createChallengeAccount('two_step', [
            'account_reference' => 'WFX-MT5-00062-NSTY',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'pending_credential_repair',
            'sync_status' => 'pending',
            'balance' => 10000,
            'equity' => 10000,
            'meta' => [
                'mt5_credential_repair' => [
                    'status' => 'pending',
                    'reason' => 'mapping_repair_not_confirmed',
                ],
                'mt5_sync' => [
                    'identifier' => '335405',
                    'status' => 'pending_credential_repair',
                    'account_reference' => 'WFX-MT5-00062-NSTY',
                ],
            ],
        ]);

        Mt5AccountPoolEntry::factory()->create([
            'login' => '335405',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $account->user_id,
            'allocated_at' => now()->subDay(),
            'is_available' => false,
        ]);

        $exitCode = Artisan::call('wolforix:diagnose-breach-invalidation', [
            'account' => '335405',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('MT5 sync ingestion diagnosis', $output);
        $this->assertStringContainsString('credential_repair.status', $output);
        $this->assertStringContainsString('NO_STORED_SNAPSHOT: credential repair is pending', $output);
        $this->assertStringContainsString('UNKNOWN: no stored MT5 snapshot/trade evidence exists.', $output);
    }

    public function test_breach_invalidation_diagnostic_can_apply_manual_screenshot_evidence_when_sync_is_missing(): void
    {
        $account = $this->createChallengeAccount('two_step', [
            'account_reference' => 'WFX-MT5-EVIDENCE-335405',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'status' => 'Active',
            'account_status' => 'active',
            'challenge_status' => 'active',
            'balance' => 10000,
            'equity' => 10000,
            'highest_equity_today' => 0,
            'rule_state' => [],
        ]);

        $options = [
            'account' => '335405',
            '--evidence-balance' => '9915.42',
            '--evidence-equity' => '8494.92',
            '--evidence-floating-pnl' => '-1420.50',
            '--evidence-at' => '2026-05-22 10:15:00',
            '--evidence-server' => 'FusionMarkets-Demo',
        ];

        $exitCode = Artisan::call('wolforix:diagnose-breach-invalidation', $options);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('manual_evidence', $output);
        $this->assertStringContainsString('daily_loss_breached', $output);
        $this->assertStringContainsString('FAIL: breach evidence exists but the account is not permanently failed/locked.', $output);

        $account->refresh();

        $this->assertSame('active', $account->challenge_status);
        $this->assertSame(0, $account->balanceSnapshots()->count());

        $exitCode = Artisan::call('wolforix:diagnose-breach-invalidation', array_merge($options, [
            '--fix' => true,
            '--confirm' => true,
        ]));
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Repair applied.', $output);

        $account->refresh();
        $snapshot = $account->balanceSnapshots()->latest('id')->firstOrFail();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('daily_loss_breached', $account->failure_reason);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertSame('8494.92', (string) $account->equity);
        $this->assertSame('-1420.50', (string) $account->profit_loss);
        $this->assertSame('manual_screenshot_evidence', data_get($snapshot->payload, 'source'));
        $this->assertSame('manual_evidence_applied', data_get($account->meta, 'mt5_sync.status'));
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));
    }

    public function test_failed_account_reaches_disabled_after_acknowledgement(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $this->pushMetrics($account, '2026-04-05 09:00:20', 10000, 9500, [
            'trade_count' => 0,
            'trading_blocked_ack' => true,
        ])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_required', false)
            ->assertJsonPath('mt5_deactivation_status', 'disabled')
            ->assertJsonPath('ea_action', 'block_trading');

        $account->refresh();

        $this->assertSame('disabled', $account->platform_status);
        $this->assertSame('disabled', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('MT5 disable status')
            ->assertSee('Disabled');
    }

    public function test_failed_account_does_not_reach_disabled_until_positions_close_successfully(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $this->pushMetrics($account, '2026-04-05 09:00:20', 10000, 9500, [
            'trading_blocked_ack' => false,
            'close_success' => false,
            'close_pending' => false,
            'positions_close_status' => 'close_failed',
            'positions_remaining_count' => 1,
            'closed_positions_on_disable_count' => 0,
            'failed_close_tickets' => ['123456'],
            'close_failed_reasons' => ['ticket=123456 retcode=10027 error=4752 message=auto trading disabled by client'],
        ])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('disable_pending_ack', $account->platform_status);
        $this->assertSame('close_failed', data_get($account->meta, 'mt5_deactivation.current.close_status'));
        $this->assertSame(['123456'], data_get($account->meta, 'mt5_deactivation.current.failed_close_tickets'));

        $this->pushMetrics($account, '2026-04-05 09:00:40', 10000, 9500, [
            'trading_blocked_ack' => true,
            'close_success' => true,
            'positions_close_status' => 'closed_successfully',
            'positions_remaining_count' => 0,
            'closed_positions_on_disable_count' => 1,
            'failed_close_tickets' => [],
        ])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_required', false)
            ->assertJsonPath('mt5_deactivation_status', 'disabled')
            ->assertJsonPath('ea_action', 'block_trading');

        $account->refresh();

        $this->assertSame('disabled', $account->platform_status);
        $this->assertSame('closed_successfully', data_get($account->meta, 'mt5_deactivation.current.close_status'));
        $this->assertSame(1, data_get($account->meta, 'mt5_deactivation.current.closed_positions_count'));
    }

    public function test_failed_account_support_notification_can_be_enabled_without_duplicates(): void
    {
        config()->set('wolforix.support.notify_failures', true);

        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])->assertOk();

        Mail::assertSent(ChallengePhasePassSupportNotificationMail::class, function (ChallengePhasePassSupportNotificationMail $mail) use ($account): bool {
            return $mail->hasTo((string) config('wolforix.support.email'))
                && $mail->details['account_reference'] === $account->account_reference
                && $mail->details['final_status'] === 'Failed'
                && $mail->details['reason'] === 'Daily loss limit'
                && $mail->details['mt5_deactivation_status'] === 'Disable Pending Ack';
        });

        $this->pushMetrics($account, '2026-04-05 09:00:10', 10000, 9400, ['trade_count' => 1])->assertOk();

        Mail::assertSent(ChallengePhasePassSupportNotificationMail::class, 1);
    }

    public function test_configured_mt5_deactivation_bridge_is_called_for_final_pass(): void
    {
        config()->set('services.mt5_deactivation.endpoint', 'https://bridge.example.test/disable');
        config()->set('services.mt5_deactivation.token', 'bridge-secret');

        Http::fake([
            'https://bridge.example.test/disable' => Http::response([
                'disabled' => true,
                'provider' => 'bridge',
            ]),
        ]);

        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10400, 10380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 10800, 10780, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11020, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'passed')
            ->assertJsonPath('mt5_deactivation_required', false)
            ->assertJsonPath('mt5_deactivation_status', 'disabled')
            ->assertJsonPath('ea_action', 'block_trading');

        $account->refresh();

        $this->assertSame('disabled', $account->platform_status);
        $this->assertSame('disabled', data_get($account->meta, 'mt5_deactivation.events.pass_finalized.status'));

        Http::assertSent(function ($request) use ($account): bool {
            return $request->url() === 'https://bridge.example.test/disable'
                && $request->hasHeader('Authorization', 'Bearer bridge-secret')
                && $request['action'] === 'close_all_positions_and_disable_account'
                && $request['event'] === 'pass_finalized'
                && $request['account_reference'] === $account->account_reference
                && $request['final_status'] === 'passed';
        });
    }

    public function test_failed_mt5_deactivation_bridge_response_is_persisted_and_not_spammed(): void
    {
        config()->set('services.mt5_deactivation.endpoint', 'https://bridge.example.test/disable');
        config()->set('services.mt5_deactivation.retry_after_seconds', 300);

        Http::fake([
            'https://bridge.example.test/disable' => Http::response([
                'message' => 'broker unavailable',
            ], 500),
        ]);

        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_status', 'disable_failed')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('failed', $account->account_status);
        $this->assertTrue((bool) $account->trading_blocked);
        $this->assertTrue((bool) $account->final_state_locked);
        $this->assertSame('disable_failed', $account->platform_status);
        $this->assertSame('disable_failed', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));
        $this->assertSame(500, data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.bridge_status'));
        $this->assertSame('Bridge returned HTTP 500.', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.error'));
        $this->assertSame('broker unavailable', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.bridge_response.message'));

        $failureLog = TradingAccountSyncLog::query()
            ->where('trading_account_id', $account->id)
            ->where('status', 'error')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('MT5 deactivation bridge request failed.', $failureLog->message);
        $this->assertSame('Bridge returned HTTP 500.', $failureLog->error_message);

        Http::assertSentCount(1);

        $this->pushMetrics($account, '2026-04-05 09:00:10', 10000, 9400, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('mt5_deactivation_status', 'disable_failed');

        Http::assertSentCount(1);

        $this->withSession([
            'admin.authenticated' => true,
            'admin.username' => 'admin',
        ])->get(route('admin.clients.metrics', $account->user))
            ->assertOk()
            ->assertSee('EA disable status')
            ->assertSee('Disable Failed')
            ->assertSee('Disable failure reason')
            ->assertSee('Bridge returned HTTP 500.')
            ->assertSee('Disable response')
            ->assertSee('broker unavailable');
    }

    public function test_mt5_permission_flag_can_confirm_disable_without_explicit_ack(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack');

        $this->pushMetrics($account, '2026-04-05 09:00:20', 10000, 9500, [
            'trade_count' => 0,
            'trade_allowed' => false,
        ])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_required', false)
            ->assertJsonPath('mt5_deactivation_status', 'disabled')
            ->assertJsonPath('ea_action', 'block_trading');

        $account->refresh();

        $this->assertSame('disabled', $account->platform_status);
        $this->assertSame('disabled', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));
        $this->assertSame('Trading disabled by MT5', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.trading_permission_state'));
        $this->assertFalse(data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.trading_permission_payload.trading_allowed'));
        $this->assertNotEmpty(data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.executed_at'));
    }

    public function test_final_state_emails_and_fail_actions_are_idempotent_on_repeated_sync(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();
        $failedAt = $account->failed_at?->toDateTimeString();
        $failedEmailSentAt = $account->failed_email_sent_at?->toDateTimeString();
        $disableRequestedAt = (string) data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.requested_at');
        $lockedBalance = (string) $account->balance;
        $lockedEquity = (string) $account->equity;

        $this->pushMetrics($account, '2026-04-05 09:00:10', 10000, 9400, [
            'trade_count' => 1,
            'trade_history' => [
                $this->closedTrade('D-LOCKED-1', '2026-04-05 09:00:00', '2026-04-05 09:00:05', -100),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('close_positions_required', true)
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_event', 'fail_daily_loss_breached')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame($failedAt, $account->failed_at?->toDateTimeString());
        $this->assertSame($failedEmailSentAt, $account->failed_email_sent_at?->toDateTimeString());
        $this->assertSame($disableRequestedAt, (string) data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.requested_at'));
        $this->assertSame($lockedBalance, (string) $account->balance);
        $this->assertSame($lockedEquity, (string) $account->equity);
        $this->assertSame(2, $account->balanceSnapshots()->count());
        $this->assertSame('disable_pending_ack', $account->platform_status);
        $this->assertSame('connected', data_get($account->meta, 'mt5_sync.status'));

        $tradesPanel = app(TradeHistoryPanelBuilder::class)->build($account);

        $this->assertTrue($tradesPanel['is_available']);
        $this->assertSame(1, $tradesPanel['summary']['closed']);
        $this->assertSame('D-LOCKED-1', $tradesPanel['rows'][0]['id']);
        Mail::assertSent(ChallengeFailedMail::class, 1);
        Mail::assertSent(TrustpilotReviewRequestMail::class, 1);
    }

    public function test_failed_account_can_remain_connector_connected_without_reopening_status(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack');

        $account->refresh();
        $lockedEquity = (string) $account->equity;

        $this->pushMetrics($account, '2026-04-05 09:05:00', 10220, 10220, [
            'trade_count' => 0,
            'has_activity' => false,
        ])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'failed')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('daily_loss_breached', $account->failure_reason);
        $this->assertSame($lockedEquity, (string) $account->equity);
        $this->assertSame('connected', data_get($account->meta, 'mt5_sync.status'));
        $this->assertSame(10220, data_get($account->meta, 'mt5_sync.last_payload_summary.equity'));
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.fail_daily_loss_breached.status'));
        $this->assertSame(9500.0, (float) data_get($account->failure_context, 'equity_at_breach'));

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Account Closed: Rule Breach Detected')
            ->assertSee('connector may still show recent sync activity')
            ->assertSee('Pending MT5 acknowledgement')
            ->assertDontSee('Not Connected');

        $this->withSession([
            'admin.authenticated' => true,
            'admin.username' => 'admin',
        ])->get(route('admin.clients.show', $account->user))
            ->assertOk()
            ->assertSee('MT5 Sync Diagnostics')
            ->assertSee('Connector status')
            ->assertSee('connected')
            ->assertSee('Breach reason')
            ->assertSee('Daily Loss Breached')
            ->assertSee('Breach rule')
            ->assertSee('Equity at breach')
            ->assertSee('$9,500.00')
            ->assertSee('Disable event')
            ->assertSee('fail_daily_loss_breached')
            ->assertSee('Disable status')
            ->assertSee('Disable Pending Ack');
    }

    public function test_trustpilot_review_reminder_waits_until_due(): void
    {
        config()->set('wolforix.review_requests.trustpilot.reminder_delay_days', 7);

        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10400, 10380, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-06 09:00:00', 10800, 10780, ['trade_count' => 1])->assertOk();
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11020, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame(
            Carbon::parse((string) data_get($account->meta, 'trustpilot_review.initial_requested_at'))->addDays(7)->toIso8601String(),
            Carbon::parse((string) data_get($account->meta, 'trustpilot_review.reminder_due_at'))->toIso8601String(),
        );

        Mail::fake();

        $this->assertSame(0, app(TrustpilotReviewRequestMailer::class)->sendDueReminders());
        Mail::assertNotSent(TrustpilotReviewRequestMail::class);

        $meta = $account->meta ?? [];
        data_set($meta, 'trustpilot_review.reminder_due_at', now()->subMinute()->toIso8601String());
        $account->forceFill(['meta' => $meta])->save();

        $this->assertSame(1, app(TrustpilotReviewRequestMailer::class)->sendDueReminders());
        Mail::assertSent(TrustpilotReviewRequestMail::class, function (TrustpilotReviewRequestMail $mail): bool {
            return $mail->reviewUrl === 'https://de.trustpilot.com/review/wolforix.com'
                && ! str_contains($mail->reviewUrl, '?')
                && $mail->reminder;
        });

        $this->assertSame(0, app(TrustpilotReviewRequestMailer::class)->sendDueReminders());
    }

    public function test_consistency_rule_stays_clear_below_approach_threshold(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'last_synced_at' => now(),
            'sync_source' => 'mt5_ea',
        ]);

        $this->pushMetrics($account, '2026-04-03 12:00:00', 10300, 10300, [
            'trade_count' => 3,
            'trade_history' => [
                $this->closedTrade('CONS-1001', '2026-04-01 09:00:00', '2026-04-01 11:30:00', 100),
                $this->closedTrade('CONS-1002', '2026-04-02 09:00:00', '2026-04-02 11:15:00', 100),
                $this->closedTrade('CONS-1003', '2026-04-03 08:45:00', '2026-04-03 11:45:00', 100),
            ],
        ])->assertOk();

        $account->refresh();

        $this->assertSame('clear', $account->consistency_status);
        $this->assertNull($account->consistency_last_trigger_threshold);
        $this->assertNull($account->consistency_triggered_at);
        $this->assertSame(300.0, (float) data_get($account->rule_state, 'consistency.current_month_profit'));
        $this->assertSame(33.33, (float) data_get($account->rule_state, 'consistency.ratio_percent'));
        Mail::assertNotSent(ConsistencyAlertMail::class);

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('You are approaching the consistency rule limit. Profits should be spread across multiple trading days.')
            ->assertDontSee('Your profit concentration has reached the consistency rule threshold. Review your trading-day distribution.');
    }

    public function test_consistency_rule_triggers_approaching_warning_and_email_once(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'last_synced_at' => now(),
            'sync_source' => 'mt5_ea',
        ]);

        $this->pushMetrics($account, '2026-04-03 12:00:00', 10330, 10330, [
            'trade_count' => 3,
            'trade_history' => [
                $this->closedTrade('CONS-2001', '2026-04-01 09:00:00', '2026-04-01 11:30:00', 130),
                $this->closedTrade('CONS-2002', '2026-04-02 09:00:00', '2026-04-02 11:15:00', 100),
                $this->closedTrade('CONS-2003', '2026-04-03 08:45:00', '2026-04-03 11:45:00', 100),
            ],
        ])->assertOk();

        $account->refresh();

        $this->assertSame('approaching', $account->consistency_status);
        $this->assertSame('35.00', (string) $account->consistency_last_trigger_threshold);
        $this->assertNotNull($account->consistency_triggered_at);
        $this->assertNotNull($account->consistency_approach_email_sent_at);
        $this->assertNull($account->consistency_breach_email_sent_at);
        $this->assertSame(39.39, (float) data_get($account->rule_state, 'consistency.ratio_percent'));

        Mail::assertSent(ConsistencyAlertMail::class, function (ConsistencyAlertMail $mail) use ($account): bool {
            return $mail->hasTo($account->user->email)
                && $mail->details['status'] === 'approaching'
                && $mail->details['current_month_profit'] === '$330.00'
                && $mail->details['highest_single_day_profit'] === '$130.00'
                && $mail->details['ratio_percent'] === '39.39%';
        });

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You are approaching the consistency rule limit. Profits should be spread across multiple trading days.')
            ->assertSee('$330.00')
            ->assertSee('$130.00')
            ->assertSee('39.39%');

        $this->actingAs($account->user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('You are approaching the consistency rule limit. Profits should be spread across multiple trading days.')
            ->assertSee('39.39%');
    }

    public function test_consistency_rule_triggers_breach_warning_and_email_once(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'last_synced_at' => now(),
            'sync_source' => 'mt5_ea',
        ]);

        $this->pushMetrics($account, '2026-04-03 12:00:00', 10500, 10500, [
            'trade_count' => 3,
            'trade_history' => [
                $this->closedTrade('CONS-3001', '2026-04-01 09:00:00', '2026-04-01 11:30:00', 200),
                $this->closedTrade('CONS-3002', '2026-04-02 09:00:00', '2026-04-02 11:15:00', 150),
                $this->closedTrade('CONS-3003', '2026-04-03 08:45:00', '2026-04-03 11:45:00', 150),
            ],
        ])->assertOk();

        $account->refresh();

        $this->assertSame('breach', $account->consistency_status);
        $this->assertSame('40.00', (string) $account->consistency_last_trigger_threshold);
        $this->assertNotNull($account->consistency_triggered_at);
        $this->assertNull($account->consistency_approach_email_sent_at);
        $this->assertNotNull($account->consistency_breach_email_sent_at);
        $this->assertSame(40.0, (float) data_get($account->rule_state, 'consistency.ratio_percent'));

        Mail::assertSent(ConsistencyAlertMail::class, function (ConsistencyAlertMail $mail) use ($account): bool {
            return $mail->hasTo($account->user->email)
                && $mail->details['status'] === 'breach'
                && $mail->details['current_month_profit'] === '$500.00'
                && $mail->details['highest_single_day_profit'] === '$200.00'
                && $mail->details['ratio_percent'] === '40.00%';
        });

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your profit concentration has reached the consistency rule threshold. Review your trading-day distribution.')
            ->assertSee('$500.00')
            ->assertSee('$200.00')
            ->assertSee('40.00%');
    }

    public function test_repeated_sync_does_not_resend_duplicate_consistency_alerts(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'last_synced_at' => now(),
            'sync_source' => 'mt5_ea',
        ]);

        $this->pushMetrics($account, '2026-04-03 12:00:00', 10330, 10330, [
            'trade_count' => 3,
            'trade_history' => [
                $this->closedTrade('CONS-4001', '2026-04-01 09:00:00', '2026-04-01 11:30:00', 130),
                $this->closedTrade('CONS-4002', '2026-04-02 09:00:00', '2026-04-02 11:15:00', 100),
                $this->closedTrade('CONS-4003', '2026-04-03 08:45:00', '2026-04-03 11:45:00', 100),
            ],
        ])->assertOk();

        $account->refresh();
        $approachSentAt = $account->consistency_approach_email_sent_at?->toDateTimeString();

        $this->pushMetrics($account, '2026-04-03 12:05:00', 10330, 10330, [
            'trade_count' => 3,
            'trade_history' => [
                $this->closedTrade('CONS-4001', '2026-04-01 09:00:00', '2026-04-01 11:30:00', 130),
                $this->closedTrade('CONS-4002', '2026-04-02 09:00:00', '2026-04-02 11:15:00', 100),
                $this->closedTrade('CONS-4003', '2026-04-03 08:45:00', '2026-04-03 11:45:00', 100),
            ],
        ])->assertOk();

        $account->refresh();

        $this->assertSame($approachSentAt, $account->consistency_approach_email_sent_at?->toDateTimeString());
        Mail::assertSent(ConsistencyAlertMail::class, 1);
    }

    public function test_consistency_rule_ignores_zero_or_negative_monthly_profit(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'last_synced_at' => now(),
            'sync_source' => 'mt5_ea',
        ]);

        $this->pushMetrics($account, '2026-04-02 12:00:00', 9950, 9950, [
            'trade_count' => 2,
            'trade_history' => [
                $this->closedTrade('CONS-5001', '2026-04-01 09:00:00', '2026-04-01 11:30:00', 120),
                $this->closedTrade('CONS-5002', '2026-04-02 09:00:00', '2026-04-02 11:15:00', -170),
            ],
        ])->assertOk();

        $account->refresh();

        $this->assertSame('clear', $account->consistency_status);
        $this->assertNull($account->consistency_last_trigger_threshold);
        $this->assertNull($account->consistency_triggered_at);
        $this->assertSame(-50.0, (float) data_get($account->rule_state, 'consistency.current_month_profit'));
        Mail::assertNotSent(ConsistencyAlertMail::class);

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('You are approaching the consistency rule limit. Profits should be spread across multiple trading days.')
            ->assertDontSee('Your profit concentration has reached the consistency rule threshold. Review your trading-day distribution.');
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
            ->assertSee('Challenge Balance')
            ->assertSee('$5,562.64')
            ->assertSee('Challenge Equity')
            ->assertSee('$5,596.80')
            ->assertSee('Realized P/L')
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
                    'credentials' => [
                        'password' => 'trade-pass-889900',
                        'investor_password' => 'investor-pass-889900',
                    ],
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
                ->assertSee('Logout')
                ->assertSee('Account summary')
                ->assertSee('Credentials')
                ->assertSee('Share metrics')
                ->assertSee('Go to metrics')
                ->assertSee('Trading command center')
                ->assertSee('Challenge Balance')
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
                ->assertSee('trade-pass-889900')
                ->assertSee('investor-pass-889900')
                ->assertDontSee('Secure disclosure not enabled')
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
        $this->pushMetrics($account, '2026-04-07 09:00:00', 11050, 11030, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'active')
            ->assertJsonPath('phase_index', 2)
            ->assertJsonPath('close_positions_required', true)
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_event', 'phase_1_pass_finalized')
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('active', $account->challenge_status);
        $this->assertSame(2, (int) $account->phase_index);
        $this->assertSame('disable_pending_ack', $account->platform_status);
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.phase_1_pass_finalized.status'));
        $this->assertNotEmpty(data_get($account->meta, 'support_notifications.events.phase_1_pass_finalized.notified_at'));
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
        Mail::assertSent(ChallengePhasePassSupportNotificationMail::class, function (ChallengePhasePassSupportNotificationMail $mail) use ($account): bool {
            return $mail->hasTo((string) config('wolforix.support.email'))
                && $mail->details['account_reference'] === $account->account_reference
                && $mail->details['phase'] === 'Phase 1'
                && $mail->details['reason'] === 'Phase Passed'
                && $mail->details['mt5_deactivation_status'] === 'Disable Pending Ack';
        });

        $phaseOneSentAt = $account->phase_one_pass_email_sent_at?->toDateTimeString();
        $phaseTwoCredentialsSentAt = $account->phase_two_credentials_email_sent_at?->toDateTimeString();

        $this->pushMetrics($account, '2026-04-07 09:00:30', 11060, 11035, ['trade_count' => 0])
            ->assertOk()
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('disable_pending_ack', $account->platform_status);
        $this->assertSame($phaseOneSentAt, $account->phase_one_pass_email_sent_at?->toDateTimeString());
        $this->assertSame($phaseTwoCredentialsSentAt, $account->phase_two_credentials_email_sent_at?->toDateTimeString());
        Mail::assertSent(PhaseOnePassedMail::class, 1);
        Mail::assertSent(PhaseTwoAccountDetailsMail::class, 1);
        Mail::assertSent(ChallengePhasePassSupportNotificationMail::class, 1);
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
        $this->pushMetrics($account, '2026-04-10 09:00:00', 11560, 11520, ['trade_count' => 1])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'passed')
            ->assertJsonPath('trading_blocked', true)
            ->assertJsonPath('mt5_deactivation_required', true)
            ->assertJsonPath('mt5_deactivation_event', 'pass_finalized')
            ->assertJsonPath('mt5_deactivation_status', 'disable_pending_ack')
            ->assertJsonPath('ea_action', 'close_all_positions_and_disable_account');

        $account->refresh();

        $this->assertSame('passed', $account->challenge_status);
        $this->assertSame(2, (int) $account->phase_index);
        $this->assertSame('disable_pending_ack', data_get($account->meta, 'mt5_deactivation.events.pass_finalized.status'));
        $this->assertNotEmpty(data_get($account->meta, 'support_notifications.events.pass_finalized.notified_at'));
        $this->assertNotNull($account->passed_at);
        Mail::assertSent(ChallengePhasePassSupportNotificationMail::class, 2);
    }

    public function test_phase_progress_emails_are_blocked_when_phase_two_state_lacks_normalized_phase_one_pass_evidence(): void
    {
        $account = $this->createChallengeAccount('two_step', [
            'activated_at' => Carbon::parse('2026-04-05 09:00:00'),
            'status' => 'Active',
            'account_status' => 'active',
            'challenge_status' => 'active',
            'stage' => 'Challenge Step 2',
            'account_phase' => 'phase_2',
            'phase_index' => 2,
            'balance' => 99750,
            'equity' => 99750,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'profit_target_percent' => 5,
            'profit_target_amount' => 500,
            'profit_target_progress_percent' => 0,
            'rule_state' => [
                'broker_phase_reference_balance' => 100000,
                'broker_reference_source' => 'rule_state',
                'challenge_starting_balance' => 10000,
                'challenge_balance' => 9750,
                'challenge_equity' => 9750,
                'phase_profit' => -250,
                'phase_profit_target_amount' => 500,
                'profit_target_met' => false,
                'minimum_trading_days_met' => false,
                'phase_history' => [],
            ],
        ]);

        $this->pushMetrics($account, '2026-04-08 09:00:00', 99750, 99750, [
            'trade_count' => 1,
            'platform_login' => $account->platform_login,
        ])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'active');

        $account->refresh();

        $this->assertNull($account->phase_one_pass_email_sent_at);
        $this->assertNull($account->phase_two_credentials_email_sent_at);
        Mail::assertNotSent(PhaseOnePassedMail::class);
        Mail::assertNotSent(PhaseTwoAccountDetailsMail::class);
        Mail::assertNotSent(ChallengePhasePassSupportNotificationMail::class);
    }

    public function test_final_pass_email_is_blocked_when_passed_state_lacks_normalized_profit_target_evidence(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'activated_at' => Carbon::parse('2026-04-05 09:00:00'),
            'status' => 'Passed',
            'account_status' => 'passed',
            'challenge_status' => 'passed',
            'passed_at' => Carbon::parse('2026-04-07 09:00:00'),
            'final_state_locked' => true,
            'trading_blocked' => true,
            'balance' => 99415.87,
            'equity' => 99415.87,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'profit_target_percent' => 10,
            'profit_target_amount' => 1000,
            'profit_target_progress_percent' => 0,
            'passed_email_sent_at' => null,
            'funded_pass_email_sent_at' => null,
            'rule_state' => [
                'broker_phase_reference_balance' => 100000,
                'broker_reference_source' => 'rule_state',
                'challenge_starting_balance' => 10000,
                'challenge_balance' => 9415.87,
                'challenge_equity' => 9415.87,
                'phase_profit' => -584.13,
                'phase_profit_target_amount' => 1000,
                'profit_target_met' => false,
                'minimum_trading_days_met' => true,
            ],
        ]);

        $this->pushMetrics($account, '2026-04-08 09:00:00', 99415.87, 99415.87, [
            'trade_count' => 1,
            'platform_login' => $account->platform_login,
        ])
            ->assertOk()
            ->assertJsonPath('challenge_status', 'passed');

        $account->refresh();

        $this->assertNull($account->passed_email_sent_at);
        $this->assertNull($account->funded_pass_email_sent_at);
        Mail::assertNotSent(ChallengePassedMail::class);
        Mail::assertNotSent(TrustpilotReviewRequestMail::class);
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

    public function test_client_dashboard_cards_use_normalized_challenge_values_for_broker_sized_mt5_accounts(): void
    {
        $account = $this->createChallengeAccount('two_step', [
            'account_reference' => 'WFX-MT5-NORMALIZED',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'balance' => 99415.87,
            'equity' => 99415.87,
            'profit_loss' => 0,
            'today_profit' => 1.50,
            'total_profit' => -584.13,
            'daily_loss_used' => 0.10,
            'max_drawdown_used' => 584.13,
            'profit_target_percent' => 10,
            'profit_target_amount' => 1000,
            'daily_drawdown_limit_amount' => 500,
            'max_drawdown_limit_amount' => 1000,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
            'sync_status' => 'success',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => now(),
            'last_sync_completed_at' => now(),
            'rule_state' => [
                'broker_phase_reference_balance' => 100000,
                'highest_challenge_equity_today' => 9415.97,
                'rules' => [
                    'profit_target_percent' => 10,
                    'daily_drawdown_limit_amount' => 500,
                    'max_drawdown_limit_amount' => 1000,
                ],
            ],
        ]);

        $account->balanceSnapshots()->create([
            'snapshot_at' => now(),
            'balance' => 99415.87,
            'equity' => 99415.87,
            'profit_loss' => 0,
            'total_profit' => -584.13,
            'today_profit' => 1.50,
            'payload' => [
                'balance' => 99415.87,
                'equity' => 99415.87,
                'broker_phase_reference_balance' => 100000,
                'open_positions' => [],
                'trade_history' => [],
            ],
        ]);

        foreach ([route('dashboard'), route('dashboard.accounts')] as $url) {
            $this->actingAs($account->user)
                ->get($url)
                ->assertOk()
                ->assertSee('Challenge Balance')
                ->assertSee('$9,415.87')
                ->assertSee('Challenge Equity')
                ->assertSee('Realized P/L')
                ->assertSee('-$584.13')
                ->assertSee('Today P/L')
                ->assertSee('$1.50')
                ->assertSee('No breach')
                ->assertDontSee('$99,415.87');
        }
    }

    private function createMetaApiChallengeAccount(string $login, array $overrides = []): TradingAccount
    {
        return $this->createChallengeAccount('one_step', array_merge([
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $login,
            'platform_account_id' => $login,
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'waiting_for_first_sync',
            'sync_status' => 'pending',
        ], $overrides));
    }

    private function attachMetaApiPoolEntry(TradingAccount $account, string $metaApiAccountId): string
    {
        $entry = Mt5AccountPoolEntry::factory()
            ->allocated()
            ->create([
                'login' => (string) $account->platform_login,
                'server' => 'FusionMarkets-Demo',
                'account_size' => (int) $account->account_size,
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'source_status' => 'assigned',
                'source_file' => 'metaapi-phase1b-test',
                'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_INTERNAL,
                'meta' => [
                    'metaapi_account_id' => $metaApiAccountId,
                    'source' => 'metaapi_phase1b_test',
                ],
            ]);

        $meta = is_array($account->meta) ? $account->meta : [];
        data_set($meta, 'metaapi_account_id', $metaApiAccountId);
        data_set($meta, 'mt5_pool_entry.id', $entry->id);
        data_set($meta, 'mt5_pool_entry.metaapi_account_id', $metaApiAccountId);
        data_set($meta, 'mt5_sync.metaapi_account_id', $metaApiAccountId);

        $account->forceFill(['meta' => $meta])->save();

        return $metaApiAccountId;
    }

    private function markMetaApiDashboardReady(TradingAccount $account, string $metaApiAccountId): TradingAccount
    {
        $this->attachMetaApiPoolEntry($account, $metaApiAccountId);

        $meta = is_array($account->fresh()->meta) ? $account->fresh()->meta : [];
        data_set($meta, 'metaapi.token', 'metaapi-token-secret');
        data_set($meta, 'metaapi_lifecycle.state', 'connected');
        data_set($meta, 'metaapi_lifecycle.sync_health', 'connected');
        data_set($meta, 'metaapi_lifecycle.core_sync_health', 'connected');
        data_set($meta, 'metaapi_onboarding.state', 'ready_to_trade');
        data_set($meta, 'metaapi_onboarding.ready_to_trade', true);
        data_set($meta, 'mt5_sync.status', 'connected');
        data_set($meta, 'mt5_sync.last_successful_metric_update_at', now()->toIso8601String());
        data_set($meta, 'mt5_sync.last_payload_summary.positions_count', 0);
        data_set($meta, 'mt5_sync.last_payload_summary.trade_history_rows', 0);
        data_set($meta, 'mt5_sync.last_payload_summary.balance', 10000);
        data_set($meta, 'mt5_sync.last_payload_summary.equity', 10000);

        $account->forceFill([
            'account_status' => 'active',
            'challenge_status' => 'active',
            'platform_status' => 'connected',
            'sync_status' => 'success',
            'sync_source' => 'metaapi',
            'last_synced_at' => now(),
            'last_sync_completed_at' => now(),
            'last_evaluated_at' => now(),
            'activated_at' => now(),
            'balance' => 10000,
            'equity' => 10000,
            'profit_loss' => 0,
            'total_profit' => 0,
            'today_profit' => 0,
            'sync_error' => null,
            'meta' => $meta,
        ])->save();

        TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => 'metaapi',
            'status' => 'success',
            'message' => 'MetaApi dashboard visibility test sync completed.',
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
            'payload' => [
                'source' => 'metaapi_dashboard_visibility_test',
                'balance' => 10000,
                'equity' => 10000,
                'positions_count' => 0,
            ],
        ]);

        return $account->refresh();
    }

    /**
     * @param  array<string, mixed>  $accountInformation
     * @param  list<array<string, mixed>>  $positions
     * @param  list<array<string, mixed>>  $deals
     * @param  list<array<string, mixed>>  $orders
     * @param  array<string, mixed>  $account
     */
    private function fakeMetaApiSync(
        string $metaApiAccountId,
        array $accountInformation,
        array $positions = [],
        array $deals = [],
        array $orders = [],
        array $account = [],
        bool $historyThrows = false,
        bool $includeAccountsLookup = false,
    ): void {
        $this->enableMetaApiForTests();

        $login = (string) ($accountInformation['login'] ?? $account['login'] ?? '340000');
        $historyOrdersResponse = $historyThrows
            ? fn () => throw new ConnectionException('cURL error 28: SSL connection timeout')
            : Http::response($orders);
        $historyDealsResponse = $historyThrows
            ? fn () => throw new ConnectionException('cURL error 28: SSL connection timeout')
            : Http::response($deals);

        $fakes = [
            "https://metaapi-provisioning.test/users/current/accounts/{$metaApiAccountId}" => Http::response(array_merge([
                '_id' => $metaApiAccountId,
                'login' => $login,
                'server' => 'FusionMarkets-Demo',
                'state' => 'DEPLOYED',
                'connectionStatus' => 'CONNECTED',
                'region' => 'london',
            ], $account)),
            "https://metaapi-client.test/users/current/accounts/{$metaApiAccountId}/account-information*" => Http::response($accountInformation),
            "https://metaapi-client.test/users/current/accounts/{$metaApiAccountId}/positions*" => Http::response($positions),
            "https://metaapi-client.test/users/current/accounts/{$metaApiAccountId}/history-orders/time/*" => $historyOrdersResponse,
            "https://metaapi-client.test/users/current/accounts/{$metaApiAccountId}/history-deals/time/*" => $historyDealsResponse,
        ];

        if ($includeAccountsLookup) {
            $fakes["https://metaapi-provisioning.test/users/current/accounts?query={$login}"] = Http::response([
                array_merge([
                    '_id' => $metaApiAccountId,
                    'login' => $login,
                    'server' => 'FusionMarkets-Demo',
                    'state' => 'DEPLOYED',
                    'connectionStatus' => 'CONNECTED',
                    'region' => 'london',
                ], $account),
            ]);
        }

        Http::fake($fakes);
    }

    private function enableMetaApiForTests(): void
    {
        config()->set('services.metaapi.enabled', true);
        config()->set('services.metaapi.token', 'test-token');
        config()->set('services.metaapi.provisioning_base_url', 'https://metaapi-provisioning.test');
        config()->set('services.metaapi.client_base_url', 'https://metaapi-client.test');
        config()->set('services.metaapi.history.days', 7);
        config()->set('services.metaapi.history.limit', 50);
        config()->set('services.metaapi.history.timeout', 1);
        config()->set('services.metaapi.sync.stale_minutes', 10);
        config()->set('services.metaapi.sync.retries', 1);
        config()->set('services.metaapi.sync.retry_delay_ms', 0);
        config()->set('services.metaapi.onboarding.max_retries', 5);
        config()->set('services.metaapi.onboarding.retry_delay_minutes', 0);
        config()->set('services.metaapi.onboarding.connection_wait_minutes', 15);
        config()->set('services.metaapi.events.email_enabled', true);
        config()->set('services.metaapi.events.discord_enabled', false);
        config()->set('services.metaapi.events.discord_webhook_url', null);
        config()->set('services.metaapi.events.telegram_enabled', false);
        config()->set('services.metaapi.events.telegram_bot_token', null);
        config()->set('services.metaapi.events.telegram_chat_id', null);
        config()->set('services.metaapi.events.crm_webhook_enabled', false);
        config()->set('services.metaapi.events.crm_webhook_url', null);
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
            'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
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

    /**
     * @return array<string, mixed>
     */
    private function metricsPayload(array $extra = []): array
    {
        return array_merge([
            'balance' => 10000,
            'equity' => 10000,
            'timestamp' => '2026-04-07 12:00:00',
            'server_day' => '2026-04-07',
            'trade_count' => 0,
            'has_activity' => false,
        ], $extra);
    }

    private function closedTrade(string $dealId, string $openedAt, string $closedAt, float $netProfit, string $symbol = 'XAUUSD'): array
    {
        return [
            'deal_id' => $dealId,
            'symbol' => $symbol,
            'trade_side' => 'buy',
            'open_timestamp' => Carbon::parse($openedAt)->timestamp,
            'execution_timestamp' => Carbon::parse($closedAt)->timestamp,
            'net_profit' => $netProfit,
        ];
    }
}
