<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Mt5AccountPoolEntry;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;
use App\Models\TradingAccountDay;
use App\Models\TradingAccountStatusHistory;
use App\Models\TradingAccountSyncLog;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvalidateMt5AccountReviewCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalidate_mt5_account_review_dry_run_is_read_only(): void
    {
        config()->set('services.mt5_deactivation.endpoint', '');

        [$account, $poolEntry] = $this->createConfirmedReviewContext();
        $before = $this->accountSnapshot($account->fresh());

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('wolforix:invalidate-mt5-account-review', [
            'account_reference' => 'WFX-MT5-00057-8HN7',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DRY RUN MT5 account manual-review invalidation', $output);
        $this->assertStringContainsString('Trading account before/after', $output);
        $this->assertStringContainsString('Planned final verification', $output);
        $this->assertStringContainsString('DRY RUN ONLY', $output);
        $this->assertStringContainsString('scalping_rule_violation', $output);
        $this->assertStringContainsString('broker_history_deleted', $output);
        $this->assertStringContainsString('validation_path_used', $output);
        $this->assertStringContainsString('pool allocation ownership match', $output);
        $this->assertStringContainsString('SAFE TO PROCEED', $output);
        $this->assertSame([], $writes);

        $this->assertSame($before, $this->accountSnapshot($account->fresh()));
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'id' => $poolEntry->id,
            'login' => '335374',
            'allocated_trading_account_id' => 62,
            'is_available' => false,
        ]);
        $this->assertSame(0, TradingAccountSyncLog::query()->where('trading_account_id', 62)->count());
    }

    public function test_invalidate_mt5_account_review_confirm_marks_failed_and_queues_mt5_deactivation_without_creating_or_deleting_history(): void
    {
        config()->set('services.mt5_deactivation.endpoint', '');

        [$account, $poolEntry] = $this->createConfirmedReviewContext();
        $ruleStateBefore = $account->fresh()->rule_state;
        $countsBefore = $this->protectedCounts($account->fresh());

        $exitCode = Artisan::call('wolforix:invalidate-mt5-account-review', [
            'account_reference' => 'WFX-MT5-00057-8HN7',
            '--confirm' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CONFIRMED MT5 account manual-review invalidation', $output);
        $this->assertStringContainsString('Final verification', $output);
        $this->assertStringContainsString('SAFE INVALIDATION COMPLETE', $output);

        $accountAfter = $account->fresh(['challengePurchase']);
        $this->assertSame('Failed', $accountAfter->status);
        $this->assertSame('failed', $accountAfter->account_status);
        $this->assertSame('failed', $accountAfter->challenge_status);
        $this->assertSame('failed', $accountAfter->challengePurchase?->account_status);
        $this->assertSame('335374', $accountAfter->platform_login);
        $this->assertSame('335374', $accountAfter->platform_account_id);
        $this->assertSame('disable_pending_ack', $accountAfter->platform_status);
        $this->assertSame('scalping_rule_violation', $accountAfter->failure_reason);
        $this->assertTrue((bool) $accountAfter->trading_blocked);
        $this->assertTrue((bool) $accountAfter->final_state_locked);
        $this->assertNotNull($accountAfter->failed_at);
        $this->assertSame($ruleStateBefore, $accountAfter->rule_state);

        $this->assertSame('manual_review', data_get($accountAfter->failure_context, 'source'));
        $this->assertSame('Miguel', data_get($accountAfter->failure_context, 'confirmed_by'));
        $this->assertTrue((bool) data_get($accountAfter->failure_context, 'under_60_second_trade'));
        $this->assertSame(60, data_get($accountAfter->failure_context, 'trade_duration_seconds_threshold'));
        $this->assertTrue((bool) data_get($accountAfter->failure_context, 'broker_history_preserved'));
        $this->assertFalse((bool) data_get($accountAfter->failure_context, 'replacement_account_created'));
        $this->assertFalse((bool) data_get($accountAfter->failure_context, 'phase_2_account_created'));

        $this->assertSame('confirmed_failed', data_get($accountAfter->meta, 'manual_review_invalidation.status'));
        $this->assertSame('fail_scalping_rule_violation', data_get($accountAfter->meta, 'mt5_deactivation.current_event_key'));
        $this->assertSame('disable_pending_ack', data_get($accountAfter->meta, 'mt5_deactivation.current.status'));
        $this->assertSame('disable_pending_ack', data_get($accountAfter->meta, 'mt5_deactivation.events.fail_scalping_rule_violation.status'));
        $this->assertSame('close_all_positions_and_disable_account', data_get($accountAfter->meta, 'mt5_deactivation.events.fail_scalping_rule_violation.action'));
        $this->assertSame('335374', data_get($accountAfter->meta, 'mt5_deactivation.events.fail_scalping_rule_violation.platform_login'));
        $this->assertSame('scalping_rule_violation', data_get($accountAfter->meta, 'mt5_deactivation.events.fail_scalping_rule_violation.failure_reason'));

        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'id' => $poolEntry->id,
            'login' => '335374',
            'allocated_trading_account_id' => 62,
            'allocated_user_id' => $account->user_id,
            'is_available' => false,
        ]);

        $this->assertSame($countsBefore, $this->protectedCounts($accountAfter));

        $deactivationLog = TradingAccountSyncLog::query()
            ->where('trading_account_id', 62)
            ->where('status', 'pending_ack')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('MT5 deactivation queued for EA acknowledgement.', $deactivationLog->message);
        $this->assertSame('fail_scalping_rule_violation', data_get($deactivationLog->payload, 'event'));
        $this->assertSame('335374', data_get($deactivationLog->payload, 'platform_login'));
    }

    public function test_invalidate_mt5_account_review_refuses_unexpected_account_reference_without_writes(): void
    {
        $account = $this->createConfirmedReviewContext()[0];
        $before = $this->accountSnapshot($account->fresh());

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('wolforix:invalidate-mt5-account-review', [
            'account_reference' => 'WFX-MT5-WRONG',
            '--confirm' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Refusing to continue', $output);
        $this->assertSame([], $writes);
        $this->assertSame($before, $this->accountSnapshot($account->fresh()));
    }

    public function test_invalidate_mt5_account_review_allows_direct_login_mismatch_when_pool_ownership_is_valid(): void
    {
        [$account] = $this->createConfirmedReviewContext([
            'platform_login' => '5050399203',
            'platform_account_id' => '5050399203',
        ]);
        $before = $this->accountSnapshot($account->fresh());

        $exitCode = Artisan::call('wolforix:invalidate-mt5-account-review', [
            'account_reference' => 'WFX-MT5-00057-8HN7',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('target_login_fields', $output);
        $this->assertStringContainsString('5050399203', $output);
        $this->assertStringContainsString('direct_login_match', $output);
        $this->assertStringContainsString('pool_allocation_ownership_match', $output);
        $this->assertStringContainsString('validation_path_used', $output);
        $this->assertStringContainsString('pool allocation ownership match', $output);
        $this->assertStringContainsString('SAFE TO PROCEED', $output);
        $this->assertStringNotContainsString('NOT SAFE', $output);
        $this->assertSame($before, $this->accountSnapshot($account->fresh()));
    }

    public function test_invalidate_mt5_account_review_blocks_wrong_pool_allocation(): void
    {
        [$account, $poolEntry] = $this->createConfirmedReviewContext([
            'platform_login' => '5050399203',
            'platform_account_id' => '5050399203',
        ]);

        $otherAccount = TradingAccount::unguarded(fn (): TradingAccount => TradingAccount::query()->create([
            'id' => 63,
            'user_id' => $account->user_id,
            'account_reference' => 'WFX-MT5-WRONG-OWNER',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
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
        ]));

        $poolEntry->forceFill([
            'allocated_trading_account_id' => $otherAccount->id,
            'allocated_user_id' => $account->user_id,
            'is_available' => false,
        ])->save();

        $exitCode = Artisan::call('wolforix:invalidate-mt5-account-review', [
            'account_reference' => 'WFX-MT5-00057-8HN7',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('NOT SAFE — invalidation blocked', $output);
        $this->assertStringContainsString('MT5 pool entry is not allocated to target trading account #62', $output);
        $this->assertStringNotContainsString('SAFE TO PROCEED', $output);
        $this->assertSame('active', $account->fresh()->account_status);
    }

    public function test_invalidate_mt5_account_review_blocks_unallocated_pool_entry_without_direct_login_match(): void
    {
        [$account, $poolEntry] = $this->createConfirmedReviewContext([
            'platform_login' => '5050399203',
            'platform_account_id' => '5050399203',
        ]);

        $poolEntry->forceFill([
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
        ])->save();

        $exitCode = Artisan::call('wolforix:invalidate-mt5-account-review', [
            'account_reference' => 'WFX-MT5-00057-8HN7',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('NOT SAFE — invalidation blocked', $output);
        $this->assertStringContainsString('neither target direct login fields nor MT5 pool allocation prove ownership of login 335374', $output);
        $this->assertStringContainsString('MT5 pool entry is not allocated to any trading account', $output);
        $this->assertStringNotContainsString('SAFE TO PROCEED', $output);
        $this->assertSame('active', $account->fresh()->account_status);
    }

    /**
     * @return array{0: TradingAccount, 1: Mt5AccountPoolEntry}
     */
    private function createConfirmedReviewContext(array $accountOverrides = [], array $poolOverrides = []): array
    {
        $user = User::factory()->create([
            'name' => 'Josué Andrés Agüero Franco',
            'email' => 'josublen457@gmail.com',
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
            'order_number' => 'WFX-ORD-REVIEW-1',
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'email' => $user->email,
            'full_name' => 'Josué Andrés Agüero Franco',
            'street_address' => '1 Review Street',
            'city' => 'Review City',
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
            'funded_status' => null,
            'started_at' => now()->subDays(2),
        ]);

        $account = TradingAccount::unguarded(fn (): TradingAccount => TradingAccount::query()->create(array_merge([
            'id' => 62,
            'user_id' => $user->id,
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
            'failed_at' => null,
            'failure_reason' => null,
            'trading_blocked' => false,
            'final_state_locked' => false,
            'rule_state' => [
                'daily_drawdown_breached' => false,
                'max_drawdown_breached' => false,
            ],
            'meta' => [
                'assignment_history' => [
                    [
                        'login' => '335374',
                        'reason' => 'pool allocation',
                    ],
                ],
            ],
        ], $accountOverrides)));

        $poolEntry = Mt5AccountPoolEntry::factory()->create(array_merge([
            'id' => 25,
            'login' => '335374',
            'server' => 'FusionMarkets-Demo',
            'password' => 'SUPER_SECRET_PASSWORD',
            'investor_password' => 'SUPER_SECRET_INVESTOR',
            'account_size' => 10000,
            'source_status' => 'available',
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $user->id,
            'allocated_at' => now()->subDays(2),
            'is_available' => false,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ], $poolOverrides));

        TradingAccountBalanceSnapshot::query()->create([
            'trading_account_id' => $account->id,
            'snapshot_at' => now()->subDay(),
            'balance' => 10000,
            'equity' => 10000,
            'profit_loss' => 0,
            'total_profit' => 0,
            'today_profit' => 0,
            'daily_drawdown' => 0,
            'max_drawdown' => 0,
            'drawdown_percent' => 0,
            'payload' => [
                'login' => '335374',
            ],
        ]);

        TradingAccountDay::query()->create([
            'trading_account_id' => $account->id,
            'phase_index' => 1,
            'trading_date' => now()->subDay()->toDateString(),
            'activity_count' => 2,
            'volume' => 1.5,
            'first_activity_at' => now()->subDay()->subHour(),
            'last_activity_at' => now()->subDay(),
            'source' => 'mt5_metrics',
        ]);

        TradingAccountStatusHistory::query()->create([
            'trading_account_id' => $account->id,
            'previous_status' => 'pending_activation',
            'new_status' => 'active',
            'previous_phase_index' => 1,
            'new_phase_index' => 1,
            'source' => 'test_fixture',
            'context' => [
                'note' => 'existing challenge history',
            ],
            'changed_at' => now()->subDays(2),
        ]);

        return [$account, $poolEntry];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountSnapshot(TradingAccount $account): array
    {
        return [
            'platform_login' => $account->platform_login,
            'platform_account_id' => $account->platform_account_id,
            'platform_status' => $account->platform_status,
            'status' => $account->status,
            'account_status' => $account->account_status,
            'challenge_status' => $account->challenge_status,
            'failed_at' => $account->failed_at,
            'failure_reason' => $account->failure_reason,
            'failure_context' => $account->failure_context,
            'trading_blocked' => (bool) $account->trading_blocked,
            'final_state_locked' => (bool) $account->final_state_locked,
            'rule_state' => $account->rule_state,
            'meta' => $account->meta,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function protectedCounts(TradingAccount $account): array
    {
        return [
            'trading_accounts' => TradingAccount::query()->count(),
            'orders' => Order::query()->count(),
            'snapshots' => TradingAccountBalanceSnapshot::query()->where('trading_account_id', $account->id)->count(),
            'trading_days' => TradingAccountDay::query()->where('trading_account_id', $account->id)->count(),
            'status_history' => TradingAccountStatusHistory::query()->where('trading_account_id', $account->id)->count(),
        ];
    }
}
