<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Mt5AccountPoolEntry;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\TradingAccountStatusHistory;
use App\Models\TradingAccountSyncLog;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiagnoseMt5AccountMappingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnose_mt5_account_mapping_identifies_safe_trial_account_to_invalidate_read_only(): void
    {
        [$trialAccount, $productionAccount, $poolEntry] = $this->createMismatchContext();

        TradingAccountStatusHistory::query()->create([
            'trading_account_id' => $trialAccount->id,
            'previous_status' => 'pending_activation',
            'new_status' => 'active',
            'source' => 'trial',
            'changed_at' => now()->subDays(2),
            'context' => [
                'login' => '335374',
            ],
        ]);
        TradingAccountStatusHistory::query()->create([
            'trading_account_id' => $productionAccount->id,
            'previous_status' => 'pending_activation',
            'new_status' => 'active',
            'source' => 'challenge',
            'changed_at' => now()->subDay(),
            'context' => [
                'login' => '335374',
            ],
        ]);
        TradingAccountSyncLog::query()->create([
            'trading_account_id' => $trialAccount->id,
            'platform' => 'mt5',
            'status' => 'success',
            'message' => 'trial sync',
            'started_at' => now()->subHours(4),
            'completed_at' => now()->subHours(4),
            'payload' => [
                'platform_login' => '335374',
                'platform_account_id' => '335374',
            ],
        ]);

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('wolforix:diagnose-mt5-account-mapping', ['login' => '335374']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('READ-ONLY MT5 account mapping diagnosis', $output);
        $this->assertStringContainsString('Relationship Graph', $output);
        $this->assertStringContainsString('Trading Accounts', $output);
        $this->assertStringContainsString('MT5 Account Pool Entries', $output);
        $this->assertStringContainsString('Users', $output);
        $this->assertStringContainsString('Orders And Challenges', $output);
        $this->assertStringContainsString('Timeline And History', $output);
        $this->assertStringContainsString('Reassignment History From Metadata', $output);
        $this->assertStringContainsString('WFX-TRIAL-0009-1FZA9', $output);
        $this->assertStringContainsString('WFX-MT5-00057-8HN7', $output);
        $this->assertStringContainsString('which_account_should_own_login', $output);
        $this->assertStringContainsString('direct_login_account_temporary_test', $output);
        $this->assertStringContainsString('pool_allocated_account_intended_production', $output);
        $this->assertStringContainsString('SAFE ACCOUNT TO INVALIDATE', $output);
        $this->assertStringContainsString('Recommended production action: Invalidate/remove login 335374 from WFX-TRIAL-0009-1FZA9', $output);
        $this->assertStringNotContainsString('SUPER_SECRET_PASSWORD', $output);
        $this->assertStringNotContainsString('SUPER_SECRET_INVESTOR', $output);
        $this->assertStringNotContainsString('do-not-print-token', $output);

        $this->assertSame([], $writes);
        $this->assertDatabaseHas('trading_accounts', [
            'id' => $trialAccount->id,
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_status' => 'connected',
            'challenge_status' => 'active',
        ]);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'id' => $poolEntry->id,
            'login' => '335374',
            'allocated_trading_account_id' => $productionAccount->id,
            'is_available' => false,
        ]);
    }

    public function test_diagnose_mt5_account_mapping_requires_manual_review_when_mapping_is_not_unique(): void
    {
        [$trialAccount] = $this->createMismatchContext();

        TradingAccount::query()->create([
            'user_id' => User::factory()->create(['email' => 'duplicate@example.com'])->id,
            'account_reference' => 'WFX-TRIAL-DUPLICATE',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'connected',
            'status' => 'Active',
            'account_status' => 'active',
            'challenge_status' => 'active',
            'account_type' => 'trial',
            'is_trial' => true,
            'trial_status' => 'active',
            'challenge_type' => 'trial',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
        ]);

        $exitCode = Artisan::call('wolforix:diagnose-mt5-account-mapping', ['login' => '335374']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('NOT SAFE / NEED MANUAL REVIEW', $output);
        $this->assertStringContainsString('Manual review reason: multiple trading_accounts rows currently store login 335374', $output);
        $this->assertStringContainsString('Recommended production action: Do not invalidate or deactivate any account for login 335374', $output);

        $this->assertDatabaseHas('trading_accounts', [
            'id' => $trialAccount->id,
            'platform_login' => '335374',
            'platform_status' => 'connected',
        ]);
    }

    /**
     * @return array{0: TradingAccount, 1: TradingAccount, 2: Mt5AccountPoolEntry}
     */
    private function createMismatchContext(): array
    {
        $trialUser = User::factory()->create([
            'name' => 'Trial Trader',
            'email' => 'trial@example.com',
        ]);
        $productionUser = User::factory()->create([
            'name' => 'Production Trader',
            'email' => 'production@example.com',
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
            'order_number' => 'WFX-ORD-PROD-1',
            'user_id' => $productionUser->id,
            'challenge_plan_id' => $plan->id,
            'email' => $productionUser->email,
            'full_name' => 'Production Trader',
            'street_address' => '1 Live Street',
            'city' => 'Liveville',
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
            'user_id' => $productionUser->id,
            'order_id' => $order->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'account_status' => 'active',
            'funded_status' => null,
            'started_at' => now()->subDay(),
        ]);
        $productionAccount = TradingAccount::query()->create([
            'user_id' => $productionUser->id,
            'challenge_plan_id' => $plan->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => $purchase->id,
            'account_reference' => 'WFX-MT5-00057-8HN7',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => null,
            'platform_account_id' => null,
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'waiting_for_first_sync',
            'status' => 'Active',
            'account_status' => 'active',
            'challenge_status' => 'active',
            'account_type' => 'challenge',
            'is_trial' => false,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'meta' => [
                'assignment_history' => [
                    [
                        'login' => '335374',
                        'reason' => 'pool allocation',
                        'secret_token' => 'do-not-print-token',
                    ],
                ],
            ],
        ]);
        $trialAccount = TradingAccount::query()->create([
            'user_id' => $trialUser->id,
            'account_reference' => 'WFX-TRIAL-0009-1FZA9',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'connected',
            'status' => 'Active',
            'account_status' => 'active',
            'challenge_status' => 'active',
            'account_type' => 'trial',
            'is_trial' => true,
            'trial_status' => 'active',
            'trial_started_at' => now()->subDays(3),
            'challenge_type' => 'trial',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'meta' => [
                'credentials' => [
                    'password' => 'SUPER_SECRET_PASSWORD',
                ],
            ],
        ]);
        $poolEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335374',
            'server' => 'FusionMarkets-Demo',
            'password' => 'SUPER_SECRET_PASSWORD',
            'investor_password' => 'SUPER_SECRET_INVESTOR',
            'account_size' => 10000,
            'source_status' => 'available',
            'allocated_trading_account_id' => $productionAccount->id,
            'allocated_user_id' => $productionUser->id,
            'allocated_at' => now()->subDay(),
            'is_available' => false,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'allocation_history' => [
                    [
                        'trading_account_id' => $productionAccount->id,
                        'password' => 'SUPER_SECRET_PASSWORD',
                    ],
                ],
            ],
        ]);

        return [$trialAccount, $productionAccount, $poolEntry];
    }
}
