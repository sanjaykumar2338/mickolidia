<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Mt5AccountPoolEntry;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerifyMt5AccountForActionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_mt5_account_for_action_is_read_only_and_masks_secrets(): void
    {
        config(['app.env' => 'production']);

        [$account, $poolEntry] = $this->createMappedMt5Account('335374');

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->artisan('wolforix:verify-mt5-account-for-action', ['login' => '335374'])
            ->expectsOutputToContain('READ-ONLY MT5 account verification')
            ->expectsOutputToContain('DB connection works.')
            ->expectsOutputToContain('unique_account_mapping')
            ->expectsOutputToContain('safe_to_deactivate')
            ->expectsOutputToContain('SAFE PRECHECK')
            ->expectsOutputToContain('trader@example.com')
            ->expectsOutputToContain('WFX-MT5-VERIFY-1')
            ->expectsOutputToContain('manual_review_detected')
            ->doesntExpectOutputToContain('SUPER_SECRET_PASSWORD')
            ->doesntExpectOutputToContain('SUPER_SECRET_INVESTOR')
            ->doesntExpectOutputToContain('do-not-print-token')
            ->assertExitCode(0);

        $this->assertSame([], $writes);
        $this->assertDatabaseHas('trading_accounts', [
            'id' => $account->id,
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'challenge_status' => 'active',
            'platform_status' => 'connected',
            'trading_blocked' => false,
            'final_state_locked' => false,
        ]);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'id' => $poolEntry->id,
            'login' => '335374',
            'allocated_trading_account_id' => $account->id,
            'is_available' => false,
        ]);
    }

    public function test_verify_mt5_account_for_action_detects_duplicate_trading_account_mapping(): void
    {
        config(['app.env' => 'production']);

        [$account] = $this->createMappedMt5Account('335374');

        TradingAccount::query()->create([
            'user_id' => User::factory()->create(['email' => 'duplicate@example.com'])->id,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'account_reference' => 'WFX-MT5-DUPLICATE',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'connected',
            'stage' => 'Single Phase',
            'status' => 'Active',
            'account_type' => 'challenge',
            'account_phase' => 'single_phase',
            'phase_index' => 1,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'rule_state' => [
                'failure_reason' => null,
            ],
        ]);

        $this->artisan('wolforix:verify-mt5-account-for-action', ['login' => '335374'])
            ->expectsOutputToContain('trading_account_rows')
            ->expectsOutputToContain('unique_account_mapping')
            ->expectsOutputToContain('NOT SAFE')
            ->expectsOutputToContain('duplicate trading accounts map to login 335374')
            ->assertExitCode(0);

        $this->assertDatabaseHas('trading_accounts', [
            'id' => $account->id,
            'challenge_status' => 'active',
            'platform_status' => 'connected',
        ]);
    }

    public function test_verify_mt5_account_for_action_detects_duplicate_pool_mapping(): void
    {
        config(['app.env' => 'production']);

        [$account] = $this->createMappedMt5Account('335374');

        Mt5AccountPoolEntry::factory()->create([
            'login' => '335374',
            'server' => 'FusionMarkets-Demo-2',
            'account_size' => 10000,
            'source_status' => 'available',
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $account->user_id,
            'allocated_at' => now(),
            'is_available' => false,
        ]);

        $this->artisan('wolforix:verify-mt5-account-for-action', ['login' => '335374'])
            ->expectsOutputToContain('mt5_pool_rows')
            ->expectsOutputToContain('unique_pool_mapping')
            ->expectsOutputToContain('NOT SAFE')
            ->expectsOutputToContain('duplicate MT5 pool entries map to login 335374')
            ->assertExitCode(0);

        $this->assertDatabaseHas('trading_accounts', [
            'id' => $account->id,
            'challenge_status' => 'active',
            'platform_status' => 'connected',
        ]);
    }

    public function test_verify_mt5_account_for_action_warns_outside_production(): void
    {
        config(['app.env' => 'local']);

        $this->createMappedMt5Account('335374');

        $this->artisan('wolforix:verify-mt5-account-for-action', ['login' => '335374'])
            ->expectsOutputToContain('WARNING — not production environment')
            ->expectsOutputToContain('NOT SAFE')
            ->expectsOutputToContain('not production environment')
            ->assertExitCode(0);
    }

    /**
     * @return array{0: TradingAccount, 1: Mt5AccountPoolEntry}
     */
    private function createMappedMt5Account(string $login): array
    {
        $user = User::factory()->create([
            'email' => 'trader@example.com',
        ]);
        $plan = ChallengePlan::query()->create([
            'slug' => 'one-step-10000',
            'name' => 'One Step 10K',
            'account_size' => 10000,
            'currency' => 'USD',
            'entry_fee' => 99,
            'profit_target' => 10,
            'daily_loss_limit' => 5,
            'max_loss_limit' => 10,
            'steps' => 1,
            'profit_share' => 80,
            'first_payout_days' => 14,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'WFX-ORD-VERIFY-1',
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'email' => $user->email,
            'full_name' => 'Read Only Trader',
            'street_address' => '1 Test Street',
            'city' => 'Testville',
            'postal_code' => '12345',
            'country' => 'US',
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 99,
            'discount_amount' => 0,
            'final_price' => 99,
            'payment_status' => Order::PAYMENT_PAID,
            'order_status' => Order::STATUS_COMPLETED,
        ]);
        $purchase = ChallengePurchase::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'account_status' => 'active',
            'started_at' => now(),
        ]);
        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => $purchase->id,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'account_reference' => 'WFX-MT5-VERIFY-1',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $login,
            'platform_account_id' => $login,
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'connected',
            'stage' => 'Single Phase',
            'status' => 'Active',
            'account_type' => 'challenge',
            'account_phase' => 'single_phase',
            'phase_index' => 1,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10125.50,
            'equity' => 10110.25,
            'profit_loss' => 125.50,
            'total_profit' => 125.50,
            'today_profit' => 25.50,
            'daily_drawdown' => 0,
            'daily_loss_used' => 0,
            'max_drawdown' => 0,
            'max_drawdown_used' => 0,
            'profit_target_percent' => 10,
            'profit_target_amount' => 1000,
            'profit_target_progress_percent' => 12.55,
            'daily_drawdown_limit_percent' => 5,
            'daily_drawdown_limit_amount' => 500,
            'max_drawdown_limit_percent' => 10,
            'max_drawdown_limit_amount' => 1000,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 2,
            'sync_status' => 'success',
            'last_synced_at' => now(),
            'last_evaluated_at' => now(),
            'rule_state' => [
                'failure_reason' => null,
                'daily_drawdown_breached' => false,
                'max_drawdown_breached' => false,
                'manual_review' => [
                    'status' => 'none',
                ],
            ],
            'meta' => [
                'credentials' => [
                    'password' => 'SUPER_SECRET_PASSWORD',
                    'investor_password' => 'SUPER_SECRET_INVESTOR',
                ],
                'mt5_connector' => [
                    'secret_token' => 'do-not-print-token',
                ],
                'mt5_deactivation' => [],
                'mt5_sync' => [
                    'identifier' => $login,
                    'status' => 'connected',
                ],
            ],
        ]);
        $poolEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => $login,
            'password' => 'SUPER_SECRET_PASSWORD',
            'investor_password' => 'SUPER_SECRET_INVESTOR',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_status' => 'available',
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $user->id,
            'allocated_at' => now(),
            'is_available' => false,
        ]);

        return [$account, $poolEntry];
    }
}
