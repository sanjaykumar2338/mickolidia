<?php

namespace App\Services\Admin;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Mt5\Mt5AccountAllocator;
use App\Services\TradingAccounts\TradingAccountProvisioner;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminChallengeActivationService
{
    public function __construct(
        private readonly TradingAccountProvisioner $tradingAccountProvisioner,
        private readonly Mt5AccountAllocator $mt5AccountAllocator,
    ) {
    }

    public function activate(User $user): TradingAccount
    {
        return DB::transaction(function () use ($user): TradingAccount {
            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            [$purchase, $order, $plan] = $this->resolvePurchaseOrderAndPlan($lockedUser);
            $account = $this->resolveOrCreateAccount($lockedUser, $purchase, $order, $plan);

            if (! in_array((string) $account->account_status, ['', 'pending_activation'], true)) {
                throw new RuntimeException('This client already has an activated challenge account.');
            }

            $challengeType = (string) $purchase->challenge_type;
            $accountSize = (int) $purchase->account_size;
            $phaseDefinition = $this->phaseDefinition($challengeType, $accountSize);
            $startingBalance = round((float) ($purchase->account_size ?: $order?->account_size ?: $plan?->account_size ?: $account->starting_balance ?: 0), 2);
            $activatedAt = now();
            $profitTargetPercent = (float) ($phaseDefinition['profit_target'] ?? $plan?->profit_target ?? $account->profit_target_percent ?? 0);
            $dailyLossLimitPercent = (float) ($phaseDefinition['daily_loss_limit'] ?? $plan?->daily_loss_limit ?? $account->daily_drawdown_limit_percent ?? 0);
            $maxLossLimitPercent = (float) ($phaseDefinition['max_loss_limit'] ?? $plan?->max_loss_limit ?? $account->max_drawdown_limit_percent ?? 0);
            $minimumTradingDays = (int) ($phaseDefinition['minimum_trading_days'] ?? $plan?->minimum_trading_days ?? $account->minimum_trading_days ?? 0);
            $profitTargetAmount = round($startingBalance * ($profitTargetPercent / 100), 2);
            $dailyLossLimitAmount = round($startingBalance * ($dailyLossLimitPercent / 100), 2);
            $maxLossLimitAmount = round($startingBalance * ($maxLossLimitPercent / 100), 2);

            $previousStatus = $account->account_status;
            $previousPhaseIndex = (int) $account->phase_index;

            $account->forceFill([
                'user_id' => $lockedUser->id,
                'challenge_plan_id' => $plan?->id,
                'order_id' => $order?->id,
                'challenge_purchase_id' => $purchase->id,
                'challenge_type' => $challengeType,
                'account_size' => $accountSize,
                'account_reference' => $account->account_reference ?: $this->generateFallbackReference($purchase),
                'platform' => 'MT5',
                'platform_slug' => 'mt5',
                'platform_environment' => $account->platform_environment ?: 'demo',
                'platform_status' => 'awaiting_metrics',
                'stage' => $challengeType === 'one_step' ? 'Single Phase' : 'Challenge Step 1',
                'status' => 'Active',
                'account_type' => 'challenge',
                'account_phase' => $challengeType === 'one_step' ? 'single_phase' : 'phase_1',
                'phase_index' => 1,
                'account_status' => 'active',
                'challenge_status' => 'active',
                'is_funded' => false,
                'is_trial' => false,
                'activated_at' => $account->activated_at ?: $activatedAt,
                'phase_started_at' => $activatedAt,
                'passed_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
                'failure_context' => null,
                'starting_balance' => $startingBalance,
                'phase_starting_balance' => $startingBalance,
                'phase_reference_balance' => $startingBalance,
                'balance' => $startingBalance,
                'equity' => $startingBalance,
                'highest_equity_today' => $startingBalance,
                'daily_drawdown' => 0,
                'daily_loss_used' => 0,
                'max_drawdown' => 0,
                'max_drawdown_used' => 0,
                'profit_loss' => 0,
                'total_profit' => 0,
                'today_profit' => 0,
                'drawdown_percent' => 0,
                'profit_target_percent' => $profitTargetPercent,
                'profit_target_amount' => $profitTargetAmount,
                'profit_target_progress_percent' => 0,
                'daily_drawdown_limit_percent' => $dailyLossLimitPercent,
                'daily_drawdown_limit_amount' => $dailyLossLimitAmount,
                'max_drawdown_limit_percent' => $maxLossLimitPercent,
                'max_drawdown_limit_amount' => $maxLossLimitAmount,
                'profit_split' => (float) ($plan?->profit_share ?? $account->profit_split ?? 0),
                'minimum_trading_days' => $minimumTradingDays,
                'trading_days_completed' => 0,
                'sync_status' => 'pending',
                'sync_source' => 'admin_activation',
                'server_day' => $activatedAt->toDateString(),
                'last_synced_at' => null,
                'last_sync_started_at' => null,
                'last_sync_completed_at' => null,
                'last_evaluated_at' => $activatedAt,
                'sync_error' => null,
                'sync_error_at' => null,
                'rule_state' => [
                    'phase_steps' => $challengeType === 'one_step' ? 1 : 2,
                    'current_phase_key' => $challengeType === 'one_step' ? 'single_phase' : 'phase_1',
                    'phase_profit' => 0,
                    'phase_profit_target_amount' => $profitTargetAmount,
                    'phase_profit_target_remaining' => $profitTargetAmount,
                    'profit_target_met' => false,
                    'minimum_trading_days_met' => false,
                    'daily_drawdown_breached' => false,
                    'max_drawdown_breached' => false,
                    'daily_loss_used' => 0,
                    'daily_loss_remaining' => $dailyLossLimitAmount,
                    'max_drawdown_used' => 0,
                    'max_drawdown_remaining' => $maxLossLimitAmount,
                    'failure_reason' => null,
                ],
                'meta' => array_merge($account->meta ?? [], [
                    'source' => 'admin-activation',
                    'activation_order_id' => $order?->id,
                    'activation_purchase_id' => $purchase->id,
                ]),
            ])->save();

            $purchase->forceFill([
                'challenge_plan_id' => $plan?->id,
                'account_status' => 'active',
                'started_at' => $purchase->started_at ?: $activatedAt,
                'meta' => array_merge($purchase->meta ?? [], [
                    'trading_account_id' => $account->id,
                    'account_reference' => $account->account_reference,
                    'activated_at' => $activatedAt->toIso8601String(),
                ]),
            ])->save();

            if ($order !== null && $order->challenge_plan_id !== $plan?->id) {
                $order->forceFill([
                    'challenge_plan_id' => $plan?->id,
                    'user_id' => $lockedUser->id,
                ])->save();
            }

            $account->statusHistories()->create([
                'previous_status' => $previousStatus,
                'new_status' => 'active',
                'previous_phase_index' => $previousPhaseIndex,
                'new_phase_index' => 1,
                'source' => 'admin_activation',
                'context' => [
                    'challenge_purchase_id' => $purchase->id,
                    'order_id' => $order?->id,
                    'account_reference' => $account->account_reference,
                ],
                'changed_at' => $activatedAt,
            ]);

            $this->mt5AccountAllocator->allocate($account);

            return $account->fresh(['challengePlan', 'challengePurchase']) ?? $account;
        });
    }

    /**
     * @return array{0: ChallengePurchase, 1: Order|null, 2: ChallengePlan|null}
     */
    private function resolvePurchaseOrderAndPlan(User $user): array
    {
        /** @var ChallengePurchase|null $purchase */
        $purchase = ChallengePurchase::query()
            ->with(['order', 'challengePlan'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        $order = $purchase?->order;

        if (! $purchase) {
            /** @var Order|null $order */
            $order = Order::query()
                ->with('challengePlan')
                ->where('user_id', $user->id)
                ->where('payment_status', Order::PAYMENT_PAID)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                throw new RuntimeException('No paid challenge order is ready for activation for this client.');
            }

            $plan = $this->resolvePlan(
                challengeType: (string) $order->challenge_type,
                accountSize: (int) $order->account_size,
                currency: (string) $order->currency,
                existingPlan: $order->challengePlan,
            );

            $purchase = ChallengePurchase::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'user_id' => $user->id,
                    'challenge_plan_id' => $plan?->id,
                    'challenge_type' => $order->challenge_type,
                    'account_size' => $order->account_size,
                    'currency' => $order->currency,
                    'account_status' => 'pending_activation',
                    'funded_status' => null,
                    'started_at' => null,
                    'meta' => [
                        'source' => 'admin-activation',
                    ],
                ],
            );

            return [$purchase, $order, $plan];
        }

        if ($order instanceof Order && ! $order->isPaid()) {
            throw new RuntimeException('This client order is not paid, so the account cannot be activated yet.');
        }

        $plan = $this->resolvePlan(
            challengeType: (string) $purchase->challenge_type,
            accountSize: (int) $purchase->account_size,
            currency: (string) ($purchase->currency ?: $order?->currency ?: 'USD'),
            existingPlan: $purchase->challengePlan ?? $order?->challengePlan,
        );

        return [$purchase, $order, $plan];
    }

    private function resolveOrCreateAccount(
        User $user,
        ChallengePurchase $purchase,
        ?Order $order,
        ?ChallengePlan $plan,
    ): TradingAccount {
        /** @var TradingAccount|null $account */
        $account = TradingAccount::query()
            ->where('challenge_purchase_id', $purchase->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $account instanceof TradingAccount && $order instanceof Order) {
            $account = TradingAccount::query()
                ->where('order_id', $order->id)
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
        }

        if ($account instanceof TradingAccount) {
            return $account;
        }

        if ($order instanceof Order) {
            return $this->tradingAccountProvisioner->provision($order, $purchase, $plan);
        }

        /** @var TradingAccount $account */
        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan?->id,
            'challenge_purchase_id' => $purchase->id,
            'challenge_type' => $purchase->challenge_type,
            'account_size' => $purchase->account_size,
            'account_reference' => $this->generateFallbackReference($purchase),
            'status' => 'Pending Activation',
            'account_status' => 'pending_activation',
            'challenge_status' => 'pending_activation',
            'phase_index' => 1,
            'is_trial' => false,
            'is_funded' => false,
            'meta' => [
                'source' => 'admin-activation-fallback',
            ],
        ]);

        return $account;
    }

    private function resolvePlan(
        string $challengeType,
        int $accountSize,
        string $currency,
        ?ChallengePlan $existingPlan = null,
    ): ?ChallengePlan {
        if ($existingPlan instanceof ChallengePlan) {
            return $existingPlan;
        }

        $slug = str_replace('_', '-', $challengeType).'-'.$accountSize;
        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}");

        if (! is_array($definition)) {
            return ChallengePlan::query()->where('slug', $slug)->first();
        }

        return ChallengePlan::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $definition['name'],
                'account_size' => $accountSize,
                'currency' => $currency,
                'entry_fee' => $definition['entry_fee'] ?? $definition['discounted_price'] ?? 0,
                'profit_target' => $definition['profit_target'] ?? ($definition['phases'][0]['profit_target'] ?? 0),
                'daily_loss_limit' => $definition['daily_loss_limit'] ?? ($definition['phases'][0]['daily_loss_limit'] ?? 0),
                'max_loss_limit' => $definition['max_loss_limit'] ?? ($definition['phases'][0]['max_loss_limit'] ?? 0),
                'steps' => $definition['steps'] ?? count($definition['phases'] ?? []),
                'profit_share' => $definition['profit_share'] ?? ($definition['funded']['profit_split'] ?? 0),
                'first_payout_days' => $definition['first_payout_days'] ?? ($definition['funded']['first_withdrawal_days'] ?? 0),
                'minimum_trading_days' => $definition['minimum_trading_days'] ?? ($definition['phases'][0]['minimum_trading_days'] ?? 0),
                'payout_cycle_days' => $definition['payout_cycle_days'] ?? ($definition['funded']['payout_cycle_days'] ?? 0),
                'is_active' => true,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function phaseDefinition(string $challengeType, int $accountSize): array
    {
        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}");
        $phases = array_values((array) ($definition['phases'] ?? []));

        return $phases[0] ?? [];
    }

    private function generateFallbackReference(ChallengePurchase $purchase): string
    {
        return 'WFX-MT5-'.str_pad((string) $purchase->id, 5, '0', STR_PAD_LEFT).'-'.strtoupper(substr(md5((string) $purchase->id.'|'.$purchase->user_id), 0, 4));
    }
}
