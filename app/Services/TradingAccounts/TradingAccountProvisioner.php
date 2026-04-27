<?php

namespace App\Services\TradingAccounts;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Services\Mt5\Mt5AccountAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradingAccountProvisioner
{
    public function __construct(
        private readonly Mt5AccountAllocator $mt5AccountAllocator,
    ) {
    }

    public function provision(Order $order, ChallengePurchase $purchase, ?ChallengePlan $plan = null): TradingAccount
    {
        /** @var TradingAccount $account */
        $account = TradingAccount::query()->firstOrNew([
            'challenge_purchase_id' => $purchase->id,
            'phase_index' => 1,
        ]);

        $startingBalance = (float) ($order->account_size ?: $plan?->account_size ?: 0);
        $challengeType = (string) $purchase->challenge_type;
        $phaseLabel = $challengeType === 'two_step' ? 'Challenge Step 1' : 'Challenge Step 1';
        $platformEnvironment = (string) config('wolforix.mt5_account_pool.fusionmarkets.server', 'FusionMarkets-Demo');

        $profitTargetPercent = (float) ($plan?->profit_target ?? 0);
        $dailyLossLimitPercent = (float) ($plan?->daily_loss_limit ?? 0);
        $maxLossLimitPercent = (float) ($plan?->max_loss_limit ?? 0);

        $account->fill([
            'user_id' => $purchase->user_id,
            'challenge_plan_id' => $plan?->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => $purchase->id,
            'challenge_type' => $challengeType,
            'account_size' => $purchase->account_size,
            'account_reference' => $account->account_reference ?: $this->reference($purchase),
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_environment' => $platformEnvironment,
            'platform_status' => $account->platform_status ?: 'waiting_for_first_sync',
            'stage' => $phaseLabel,
            'status' => 'Pending Activation',
            'account_type' => 'challenge',
            'account_phase' => 'challenge',
            'phase_index' => 1,
            'account_status' => 'pending_activation',
            'challenge_status' => 'pending_activation',
            'is_funded' => false,
            'is_trial' => false,
            'starting_balance' => $startingBalance,
            'phase_starting_balance' => $startingBalance,
            'phase_reference_balance' => $startingBalance,
            'balance' => $account->balance ?: $startingBalance,
            'equity' => $account->equity ?: $startingBalance,
            'highest_equity_today' => $account->highest_equity_today ?: $startingBalance,
            'daily_drawdown' => 0,
            'daily_loss_used' => 0,
            'max_drawdown' => 0,
            'max_drawdown_used' => 0,
            'profit_loss' => 0,
            'total_profit' => 0,
            'today_profit' => 0,
            'drawdown_percent' => 0,
            'profit_target_percent' => $profitTargetPercent,
            'profit_target_amount' => round($startingBalance * ($profitTargetPercent / 100), 2),
            'profit_target_progress_percent' => 0,
            'daily_drawdown_limit_percent' => $dailyLossLimitPercent,
            'daily_drawdown_limit_amount' => round($startingBalance * ($dailyLossLimitPercent / 100), 2),
            'max_drawdown_limit_percent' => $maxLossLimitPercent,
            'max_drawdown_limit_amount' => round($startingBalance * ($maxLossLimitPercent / 100), 2),
            'profit_split' => (float) ($plan?->profit_share ?? 0),
            'consistency_limit_percent' => $account->consistency_limit_percent ?: 40,
            'minimum_trading_days' => (int) ($plan?->minimum_trading_days ?? 0),
            'trading_days_completed' => 0,
            'sync_status' => 'pending',
            'sync_source' => 'order_fulfillment',
            'phase_started_at' => now(),
            'meta' => array_merge($account->meta ?? [], [
                'source' => 'order-fulfillment',
                'payment_provider' => $order->payment_provider,
                'first_payout_days' => $plan?->first_payout_days,
                'payout_cycle_days' => $plan?->payout_cycle_days,
            ]),
        ]);

        DB::transaction(function () use ($account, $purchase): void {
            $account->save();

            $purchase->forceFill([
                'account_status' => 'pending_activation',
                'meta' => array_merge($purchase->meta ?? [], [
                    'trading_account_id' => $account->id,
                    'platform' => 'mt5',
                ]),
            ])->save();

            $this->mt5AccountAllocator->allocate($account);

            if ($account->wasRecentlyCreated) {
                $account->statusHistories()->create([
                    'previous_status' => null,
                    'new_status' => 'pending_activation',
                    'previous_phase_index' => null,
                    'new_phase_index' => (int) $account->phase_index,
                    'source' => 'provisioning',
                    'context' => [
                        'challenge_purchase_id' => $purchase->id,
                        'order_id' => $account->order_id,
                    ],
                    'changed_at' => now(),
                ]);
            }
        });

        return $account->fresh(['challengePlan', 'challengePurchase']) ?? $account;
    }

    private function reference(ChallengePurchase $purchase): string
    {
        return 'WFX-MT5-'.str_pad((string) $purchase->id, 5, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(4));
    }
}
