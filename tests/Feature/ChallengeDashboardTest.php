<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mt5_ingestion.token', 'integration-secret');
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
        $this->assertNotNull($account->passed_at);
        $this->assertNull($account->failure_reason);
    }

    public function test_one_step_fails_when_daily_loss_limit_is_breached(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 10000, 9500, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('daily_loss_breached', $account->failure_reason);
        $this->assertNotNull($account->failed_at);
    }

    public function test_one_step_fails_when_max_drawdown_is_breached(): void
    {
        $account = $this->createChallengeAccount('one_step');

        $this->pushMetrics($account, '2026-04-05 09:00:00', 9150, 9700, ['trade_count' => 1])->assertOk();

        $account->refresh();

        $this->assertSame('failed', $account->challenge_status);
        $this->assertSame('max_drawdown_breached', $account->failure_reason);
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
        $this->assertSame('11050.00', (string) $account->phase_reference_balance);
        $this->assertSame('5.00', (string) $account->profit_target_percent);
        $this->assertNotEmpty($account->rule_state['phase_history'] ?? []);
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
