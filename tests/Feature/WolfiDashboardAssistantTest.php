<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WolfiDashboardAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_compact_wolfi_entry_and_hub_contains_full_panel(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'balance' => 10350,
            'equity' => 10410,
            'profit_loss' => 60,
            'total_profit' => 350,
            'profit_target_progress_percent' => 35,
            'trading_days_completed' => 2,
            'challenge_status' => 'active',
            'account_status' => 'active',
            'status' => 'active',
            'last_synced_at' => Carbon::parse('2026-04-17 09:15:00'),
        ]);

        $this->actingAs($account->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Wolfi supports your')
            ->assertSee('Trading workspace')
            ->assertSee('Open Wolfi Hub')
            ->assertSee('wolfy-image/Dashboard.jpeg', false)
            ->assertSee('dashboard-wolfi-ring-avatar', false)
            ->assertDontSee('Grounded in Wolforix data')
            ->assertDontSee('dashboard\\/wolfi\\/respond', false);

        $this->actingAs($account->user)
            ->get(route('dashboard.wolfi', ['account' => $account->id]))
            ->assertOk()
            ->assertSee('Wolfi supports your')
            ->assertSee('Trading workspace')
            ->assertSee('dashboard\\/wolfi\\/respond', false)
            ->assertSee('Explain my dashboard')
            ->assertSee('Live response')
            ->assertSee('Rule-aware')
            ->assertSee('wolfi-dashboard-avatar-image-poster', false)
            ->assertSee('wolfy-image/Dashboard.jpeg', false);
    }

    public function test_dashboard_renders_smart_insight_cards_when_account_crosses_thresholds(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'balance' => 10780,
            'equity' => 10840,
            'profit_loss' => 60,
            'total_profit' => 780,
            'daily_loss_used' => 320,
            'max_drawdown_used' => 410,
            'profit_target_progress_percent' => 78,
            'trading_days_completed' => 3,
            'is_funded' => true,
            'challenge_status' => 'active',
            'account_status' => 'active',
            'status' => 'active',
            'payout_eligible_at' => Carbon::parse('2026-04-15 09:15:00'),
            'first_payout_eligible_at' => Carbon::parse('2026-04-10 09:15:00'),
            'rule_state' => [
                'consistency' => [
                    'status' => 'approaching',
                    'ratio_percent' => 36.4,
                    'highest_single_day_profit' => 320,
                ],
            ],
        ]);

        $this->actingAs($account->user)
            ->get(route('dashboard.wolfi', ['account' => $account->id]))
            ->assertOk()
            ->assertSee('Smart Insights')
            ->assertSee('Risk Alert')
            ->assertSee('Consistency Warning')
            ->assertSee('Profit Progress')
            ->assertSee('Payout Readiness')
            ->assertSee('Explain my drawdown risk and remaining room')
            ->assertSee('Explain my payout readiness');
    }

    public function test_dashboard_hides_smart_insights_when_account_data_is_unavailable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Smart Insights');

        $this->actingAs($user)
            ->get(route('dashboard.wolfi'))
            ->assertOk()
            ->assertDontSee('Smart Insights');
    }

    public function test_wolfi_endpoint_returns_account_specific_metrics_response(): void
    {
        $account = $this->createChallengeAccount('one_step', [
            'balance' => 10350,
            'equity' => 10410,
            'profit_loss' => 60,
            'total_profit' => 350,
            'daily_loss_used' => 120,
            'max_drawdown_used' => 240,
            'profit_target_progress_percent' => 35,
            'trading_days_completed' => 2,
            'challenge_status' => 'active',
            'account_status' => 'active',
            'status' => 'active',
        ]);

        $this->actingAs($account->user)
            ->postJson(route('dashboard.wolfi.respond'), [
                'message' => 'Explain my metrics',
                'page' => 'dashboard',
                'account_id' => $account->id,
            ])
            ->assertOk()
            ->assertJsonPath('group', 'performance_insights')
            ->assertJsonPath('title', 'Your metrics in plain English')
            ->assertJsonFragment([
                'label' => 'Balance',
                'value' => '$10,350.00',
            ])
            ->assertJsonFragment([
                'label' => 'Equity',
                'value' => '$10,410.00',
            ])
            ->assertJsonFragment([
                'label' => 'Floating P&L',
                'value' => '$60.00',
            ]);
    }

    public function test_wolfi_endpoint_falls_back_to_general_payout_guidance_without_account_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('dashboard.wolfi.respond'), [
                'message' => 'How do payouts work?',
                'page' => 'dashboard',
            ])
            ->assertOk()
            ->assertJsonPath('group', 'payouts')
            ->assertJsonPath('title', 'Payout timing and approval')
            ->assertJsonFragment([
                'label' => 'First withdrawal',
                'value' => '21 days',
            ])
            ->assertJsonFragment([
                'label' => 'Cycle',
                'value' => '14 days',
            ]);
    }

    public function test_wolfi_endpoint_does_not_expose_other_users_accounts(): void
    {
        $otherAccount = $this->createChallengeAccount('one_step', [
            'account_reference' => 'ACC-ONE-0007',
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.wolfi.respond'), [
                'message' => 'Explain my metrics',
                'page' => 'dashboard',
                'account_id' => $otherAccount->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('group', 'performance_insights');

        $this->assertStringNotContainsString(
            'ACC-ONE-0007',
            json_encode($response->json(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
        );
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
            'account_reference' => 'ACC-'.strtoupper($challengeType).'-0001',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_account_id' => 'MT5-00001',
            'platform_login' => '500001',
            'platform_environment' => 'demo',
            'platform_status' => 'connected',
            'stage' => $challengeType === 'one_step' ? 'Single Phase' : 'Challenge Step 1',
            'status' => 'active',
            'account_type' => 'challenge',
            'account_phase' => $challengeType === 'one_step' ? 'single_phase' : 'phase_1',
            'phase_index' => 1,
            'account_status' => 'active',
            'challenge_status' => 'active',
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
            'consistency_limit_percent' => 40,
            'minimum_trading_days' => (int) ($phase['minimum_trading_days'] ?? 3),
            'trading_days_completed' => 0,
            'last_synced_at' => Carbon::parse('2026-04-17 09:15:00'),
            'sync_status' => 'success',
            'sync_source' => 'mt5_ea',
            'rule_state' => [
                'consistency' => [
                    'status' => 'clear',
                    'ratio_percent' => 18.2,
                    'highest_single_day_profit' => 130,
                ],
            ],
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
}
