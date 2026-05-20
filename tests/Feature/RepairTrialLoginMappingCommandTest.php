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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepairTrialLoginMappingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_trial_login_mapping_dry_run_is_read_only(): void
    {
        [$trialAccount, $productionAccount, $poolEntry] = $this->createVerifiedMismatchContext();

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('wolforix:repair-trial-login-mapping', ['login' => '335374']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DRY RUN trial MT5 login mapping repair', $output);
        $this->assertStringContainsString('Trading account before/after', $output);
        $this->assertStringContainsString('MT5 pool allocation before/after', $output);
        $this->assertStringContainsString('Planned final verification', $output);
        $this->assertStringContainsString('DRY RUN ONLY', $output);
        $this->assertStringContainsString('WFX-TRIAL-0009-1FZA9', $output);
        $this->assertStringContainsString('WFX-MT5-00057-8HN7', $output);
        $this->assertStringNotContainsString('SUPER_SECRET_PASSWORD', $output);
        $this->assertStringNotContainsString('SUPER_SECRET_INVESTOR', $output);
        $this->assertSame([], $writes);

        $this->assertDatabaseHas('trading_accounts', [
            'id' => $trialAccount->id,
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_status' => 'connected',
            'sync_status' => 'success',
            'challenge_status' => 'active',
        ]);
        $this->assertSame('335374', data_get($trialAccount->fresh()->meta, 'mt5_sync.identifier'));
        $this->assertSame(25, data_get($trialAccount->fresh()->meta, 'mt5_pool_entry.id'));

        $this->assertDatabaseHas('trading_accounts', [
            'id' => $productionAccount->id,
            'account_reference' => 'WFX-MT5-00057-8HN7',
            'platform_status' => 'waiting_for_first_sync',
        ]);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'id' => $poolEntry->id,
            'login' => '335374',
            'allocated_trading_account_id' => $productionAccount->id,
            'is_available' => false,
        ]);
    }

    public function test_repair_trial_login_mapping_confirm_clears_only_trial_mapping(): void
    {
        [$trialAccount, $productionAccount, $poolEntry] = $this->createVerifiedMismatchContext();

        $productionBefore = $productionAccount->fresh();
        $poolBefore = $poolEntry->fresh();

        $exitCode = Artisan::call('wolforix:repair-trial-login-mapping', [
            'login' => '335374',
            '--confirm' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CONFIRMED trial MT5 login mapping repair', $output);
        $this->assertStringContainsString('Final verification', $output);
        $this->assertStringContainsString('SAFE REPAIR COMPLETE', $output);
        $this->assertStringContainsString('broker_mt5_login_deactivated', $output);
        $this->assertStringContainsString('orders_trades_snapshots_challenge_history_rule_state_changed', $output);
        $this->assertStringNotContainsString('SUPER_SECRET_PASSWORD', $output);

        $trialAfter = $trialAccount->fresh();
        $this->assertNull($trialAfter->platform_login);
        $this->assertNull($trialAfter->platform_account_id);
        $this->assertSame('pending_connection', $trialAfter->platform_status);
        $this->assertSame('pending', $trialAfter->sync_status);
        $this->assertSame('active', $trialAfter->account_status);
        $this->assertSame('active', $trialAfter->challenge_status);
        $this->assertFalse((bool) $trialAfter->trading_blocked);
        $this->assertFalse((bool) $trialAfter->final_state_locked);
        $this->assertNull($trialAfter->failed_at);
        $this->assertNull($trialAfter->failure_reason);
        $this->assertSame(['daily_drawdown_breached' => false], $trialAfter->rule_state);
        $this->assertNull(data_get($trialAfter->meta, 'credentials.password'));
        $this->assertNull(data_get($trialAfter->meta, 'mt5_sync.identifier'));
        $this->assertSame('login_mapping_cleared', data_get($trialAfter->meta, 'mt5_sync.status'));
        $this->assertNull(data_get($trialAfter->meta, 'mt5_pool_entry.id'));
        $this->assertSame('cleared', data_get($trialAfter->meta, 'mt5_trial_login_repair.status'));
        $this->assertSame('335374', data_get($trialAfter->meta, 'mt5_trial_login_repair.removed_login'));

        $productionAfter = $productionAccount->fresh();
        $this->assertSame($productionBefore->platform_login, $productionAfter->platform_login);
        $this->assertSame($productionBefore->platform_account_id, $productionAfter->platform_account_id);
        $this->assertSame($productionBefore->platform_status, $productionAfter->platform_status);
        $this->assertSame($productionBefore->sync_status, $productionAfter->sync_status);
        $this->assertSame($productionBefore->challenge_status, $productionAfter->challenge_status);
        $this->assertSame($productionBefore->rule_state, $productionAfter->rule_state);
        $this->assertSame($productionBefore->meta, $productionAfter->meta);

        $poolAfter = $poolEntry->fresh();
        $this->assertSame($poolBefore->allocated_trading_account_id, $poolAfter->allocated_trading_account_id);
        $this->assertSame($poolBefore->allocated_user_id, $poolAfter->allocated_user_id);
        $this->assertTrue($poolBefore->allocated_at->equalTo($poolAfter->allocated_at));
        $this->assertSame($poolBefore->is_available, $poolAfter->is_available);

        $diagnoseExitCode = Artisan::call('wolforix:diagnose-mt5-account-mapping', ['login' => '335374']);
        $diagnoseOutput = Artisan::output();

        $this->assertSame(0, $diagnoseExitCode);
        $this->assertStringContainsString('which_account_should_own_login: WFX-MT5-00057-8HN7 / TradingAccount #62', $diagnoseOutput);
        $this->assertStringContainsString('direct_login_account: none', $diagnoseOutput);
        $this->assertStringContainsString('NO MAPPING CONFLICT', $diagnoseOutput);
        $this->assertStringNotContainsString('Manual review reason:', $diagnoseOutput);
    }

    public function test_repair_trial_login_mapping_confirm_refuses_unexpected_direct_mapping(): void
    {
        [$trialAccount] = $this->createVerifiedMismatchContext();

        TradingAccount::unguarded(function (): void {
            TradingAccount::query()->create([
                'id' => 86,
                'user_id' => User::factory()->create(['email' => 'duplicate@example.com'])->id,
                'account_reference' => 'WFX-TRIAL-DUPLICATE',
                'platform' => 'MT5',
                'platform_slug' => 'mt5',
                'platform_login' => '335374',
                'platform_account_id' => '335374',
                'platform_environment' => 'FusionMarkets-Demo',
                'platform_status' => 'connected',
                'sync_status' => 'success',
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
        });

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('wolforix:repair-trial-login-mapping', [
            'login' => '335374',
            '--confirm' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('NOT SAFE — repair blocked', $output);
        $this->assertStringContainsString('unexpected trading account(s) also map to login 335374', $output);
        $this->assertSame([], $writes);
        $this->assertDatabaseHas('trading_accounts', [
            'id' => $trialAccount->id,
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_status' => 'connected',
        ]);
    }

    /**
     * @return array{0: TradingAccount, 1: TradingAccount, 2: Mt5AccountPoolEntry}
     */
    private function createVerifiedMismatchContext(): array
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

        $productionAccount = TradingAccount::unguarded(fn (): TradingAccount => TradingAccount::query()->create([
            'id' => 62,
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
            'sync_status' => 'pending',
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
            'rule_state' => [
                'daily_drawdown_breached' => false,
            ],
            'meta' => [
                'assignment_history' => [
                    [
                        'login' => '335374',
                        'reason' => 'pool allocation',
                    ],
                ],
            ],
        ]));

        $trialAccount = TradingAccount::unguarded(fn (): TradingAccount => TradingAccount::query()->create([
            'id' => 85,
            'user_id' => $trialUser->id,
            'account_reference' => 'WFX-TRIAL-0009-1FZA9',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'connected',
            'sync_status' => 'success',
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
            'failed_at' => null,
            'failure_reason' => null,
            'trading_blocked' => false,
            'final_state_locked' => false,
            'rule_state' => [
                'daily_drawdown_breached' => false,
            ],
            'meta' => [
                'credentials' => [
                    'server' => 'FusionMarkets-Demo',
                    'password' => 'SUPER_SECRET_PASSWORD',
                    'investor_password' => 'SUPER_SECRET_INVESTOR',
                ],
                'mt5_sync' => [
                    'identifier' => '335374',
                    'account_reference' => 'WFX-TRIAL-0009-1FZA9',
                    'server' => 'FusionMarkets-Demo',
                    'status' => 'connected',
                ],
                'mt5_pool_entry' => [
                    'id' => 25,
                    'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
                ],
            ],
        ]));

        $poolEntry = Mt5AccountPoolEntry::factory()->create([
            'id' => 25,
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
            ],
        ]);

        return [$trialAccount, $productionAccount, $poolEntry];
    }
}
