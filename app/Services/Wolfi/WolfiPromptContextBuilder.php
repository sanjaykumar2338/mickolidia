<?php

namespace App\Services\Wolfi;

use App\Models\TradingAccount;
use App\Models\User;
use App\Support\ChallengeAccountMetrics;
use Illuminate\Support\Carbon;

class WolfiPromptContextBuilder
{
    public function __construct(
        private readonly WolfiKnowledgeBase $knowledgeBase,
        private readonly ChallengeAccountMetrics $challengeAccountMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?TradingAccount $account, string $page): array
    {
        $user->loadMissing([
            'challengeTradingAccounts.challengePlan',
            'challengePurchases',
        ]);

        return [
            'assistant' => $this->knowledgeBase->assistantMeta(),
            'page' => [
                'key' => $page,
                ...$this->knowledgeBase->pageGuide($page),
            ],
            'navigation' => $this->knowledgeBase->navigationPages(),
            'portfolio' => [
                'challenge_count' => $user->challengeTradingAccounts->count(),
                'purchase_count' => $user->challengePurchases->count(),
                'has_account' => $account instanceof TradingAccount,
            ],
            'account' => $this->accountContext($account),
            'rules' => $this->rulesContext($account),
            'support' => $this->knowledgeBase->supportMeta(),
            'voice' => $this->knowledgeBase->voiceMeta(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function accountContext(?TradingAccount $account): ?array
    {
        if (! $account instanceof TradingAccount) {
            return null;
        }

        $metrics = $this->challengeAccountMetrics->resolve($account);
        $plan = $this->knowledgeBase->challengeModel((string) $account->challenge_type, (int) $account->account_size);
        $funded = $plan['funded'] ?? [];
        $consistency = (array) data_get($account->rule_state, 'consistency', []);
        $currency = (string) ($plan['currency'] ?? config('wolforix.default_currency', 'USD'));
        $consistencyLimitPercent = (float) ($account->consistency_limit_percent ?: $this->knowledgeBase->defaultConsistencyLimit());
        $dailyLossRemaining = max((float) $account->daily_drawdown_limit_amount - (float) $account->daily_loss_used, 0);
        $maxDrawdownRemaining = max((float) $account->max_drawdown_limit_amount - (float) $account->max_drawdown_used, 0);
        $dailyDrawdownUsagePercent = $this->usagePercent((float) $account->daily_loss_used, (float) $account->daily_drawdown_limit_amount);
        $maxDrawdownUsagePercent = $this->usagePercent((float) $account->max_drawdown_used, (float) $account->max_drawdown_limit_amount);
        $payoutEligibleAt = $account->payout_eligible_at ?? $account->first_payout_eligible_at;
        $consistencyRequired = (bool) ($funded['consistency_rule_required'] ?? false);
        $consistencyRatioPercent = round((float) ($consistency['ratio_percent'] ?? 0), 2);
        $payoutReady = (bool) $account->is_funded
            && ! (bool) $account->trading_blocked
            && ! in_array((string) ($account->challenge_status ?: $account->account_status ?: ''), ['failed', 'pending_activation'], true)
            && $account->trading_days_completed >= $account->minimum_trading_days
            && $payoutEligibleAt instanceof \DateTimeInterface
            && Carbon::instance($payoutEligibleAt)->lessThanOrEqualTo(now())
            && (! $consistencyRequired || $consistencyRatioPercent <= $consistencyLimitPercent);

        return [
            'id' => $account->id,
            'reference' => $account->account_reference ?: 'N/A',
            'plan_label' => $this->planLabel((string) $account->challenge_type, (int) $account->account_size),
            'challenge_type' => (string) $account->challenge_type,
            'challenge_phase' => $this->phaseLabel($account),
            'account_size' => (int) $account->account_size,
            'status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status ?: 'active')),
            'raw_status' => (string) ($account->challenge_status ?: $account->account_status ?: 'active'),
            'platform' => $account->platform ?: 'N/A',
            'platform_login' => $account->platform_login ?: 'Link pending',
            'platform_account_id' => $account->platform_account_id ?: 'Link pending',
            'sync_status' => $this->humanizeStatus((string) ($account->sync_status ?: 'pending')),
            'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            'last_synced_human' => $this->formatDateTime($account->last_synced_at),
            'is_funded' => (bool) $account->is_funded,
            'trading_blocked' => (bool) $account->trading_blocked,
            'balance_value' => round((float) $metrics['challenge_balance'], 2),
            'balance' => $this->formatMoney((float) $metrics['challenge_balance'], $currency),
            'equity_value' => round((float) $metrics['challenge_equity'], 2),
            'equity' => $this->formatMoney((float) $metrics['challenge_equity'], $currency),
            'floating_pnl_value' => round((float) $account->profit_loss, 2),
            'floating_pnl' => $this->formatMoney((float) $account->profit_loss, $currency),
            'realized_profit_value' => round((float) $metrics['realized_profit'], 2),
            'realized_profit' => $this->formatMoney((float) $metrics['realized_profit'], $currency),
            'daily_loss_used_value' => round((float) $account->daily_loss_used, 2),
            'daily_loss_used' => $this->formatMoney((float) $account->daily_loss_used, $currency),
            'daily_loss_remaining_value' => round($dailyLossRemaining, 2),
            'daily_loss_remaining' => $this->formatMoney($dailyLossRemaining, $currency),
            'daily_drawdown_limit_value' => round((float) $account->daily_drawdown_limit_amount, 2),
            'daily_drawdown_usage_percent' => $dailyDrawdownUsagePercent,
            'max_drawdown_used_value' => round((float) $account->max_drawdown_used, 2),
            'max_drawdown_used' => $this->formatMoney((float) $account->max_drawdown_used, $currency),
            'max_drawdown_remaining_value' => round($maxDrawdownRemaining, 2),
            'max_drawdown_remaining' => $this->formatMoney($maxDrawdownRemaining, $currency),
            'max_drawdown_limit_value' => round((float) $account->max_drawdown_limit_amount, 2),
            'max_drawdown_usage_percent' => $maxDrawdownUsagePercent,
            'trading_days' => sprintf('%d / %d', (int) $account->trading_days_completed, (int) $account->minimum_trading_days),
            'trading_days_completed' => (int) $account->trading_days_completed,
            'minimum_trading_days' => (int) $account->minimum_trading_days,
            'target_progress_percent' => round((float) $account->profit_target_progress_percent, 1),
            'profit_target_amount_value' => round((float) $account->profit_target_amount, 2),
            'profit_target_amount' => $this->formatMoney((float) $account->profit_target_amount, $currency),
            'profit_split_percent' => (float) $account->profit_split,
            'first_payout_days' => (int) ($funded['first_withdrawal_days'] ?? $account->challengePlan?->first_payout_days ?? 21),
            'payout_cycle_days' => (int) ($funded['payout_cycle_days'] ?? $account->challengePlan?->payout_cycle_days ?? 14),
            'payout_eligible_at' => $this->formatDateTime($account->payout_eligible_at),
            'payout_eligible_at_value' => $account->payout_eligible_at?->toIso8601String(),
            'first_payout_eligible_at' => $this->formatDateTime($account->first_payout_eligible_at),
            'first_payout_eligible_at_value' => $account->first_payout_eligible_at?->toIso8601String(),
            'consistency_status' => (string) ($consistency['status'] ?? $account->consistency_status ?? 'clear'),
            'consistency_ratio_percent' => $consistencyRatioPercent,
            'consistency_highest_day_profit_value' => round((float) ($consistency['highest_single_day_profit'] ?? 0), 2),
            'consistency_highest_day_profit' => $this->formatMoney((float) ($consistency['highest_single_day_profit'] ?? 0), $currency),
            'consistency_limit_percent' => $consistencyLimitPercent,
            'payout_ready' => $payoutReady,
            'payout_ready_reason' => $payoutReady ? 'window_open' : 'not_ready',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesContext(?TradingAccount $account): array
    {
        $catalog = collect($this->knowledgeBase->challengeCatalog())
            ->map(function (array $model, string $challengeType): array {
                $samplePlan = collect($model['plans'] ?? [])->sortKeys()->first();
                $phaseOne = $samplePlan['phases'][0] ?? [];
                $phaseTwo = $samplePlan['phases'][1] ?? null;

                return [
                    'challenge_type' => $challengeType,
                    'label' => (string) ($model['label'] ?? $challengeType),
                    'profit_target' => (float) ($phaseOne['profit_target'] ?? 0),
                    'daily_loss_limit' => (float) ($phaseOne['daily_loss_limit'] ?? 0),
                    'max_loss_limit' => (float) ($phaseOne['max_loss_limit'] ?? 0),
                    'minimum_trading_days' => (int) ($phaseOne['minimum_trading_days'] ?? 0),
                    'phase_two_profit_target' => (float) ($phaseTwo['profit_target'] ?? 0),
                    'phase_two_daily_loss_limit' => (float) ($phaseTwo['daily_loss_limit'] ?? 0),
                    'phase_two_max_loss_limit' => (float) ($phaseTwo['max_loss_limit'] ?? 0),
                    'funded_profit_split' => (float) ($samplePlan['funded']['profit_split'] ?? 0),
                    'first_payout_days' => (int) ($samplePlan['funded']['first_withdrawal_days'] ?? 21),
                    'payout_cycle_days' => (int) ($samplePlan['funded']['payout_cycle_days'] ?? 14),
                    'consistency_rule_required' => (bool) ($samplePlan['funded']['consistency_rule_required'] ?? false),
                ];
            })
            ->values()
            ->all();

        if (! $account instanceof TradingAccount) {
            return [
                'current' => null,
                'models' => $catalog,
                'default_consistency_limit' => $this->knowledgeBase->defaultConsistencyLimit(),
                'pass_fail_items' => $this->knowledgeBase->passFailItems(),
            ];
        }

        $plan = $this->knowledgeBase->challengeModel((string) $account->challenge_type, (int) $account->account_size);
        $phases = array_values((array) ($plan['phases'] ?? []));
        $phaseIndex = max((int) $account->phase_index, 1);
        $currentPhase = $phases[$phaseIndex - 1] ?? ($phases[0] ?? []);
        $fundedRules = (array) ($plan['funded'] ?? []);

        return [
            'current' => [
                'plan_label' => $this->planLabel((string) $account->challenge_type, (int) $account->account_size),
                'challenge_type' => (string) $account->challenge_type,
                'phase_label' => $this->phaseLabel($account),
                'profit_target' => (float) ($currentPhase['profit_target'] ?? 0),
                'daily_loss_limit' => (float) ($currentPhase['daily_loss_limit'] ?? 0),
                'max_loss_limit' => (float) ($currentPhase['max_loss_limit'] ?? 0),
                'minimum_trading_days' => (int) ($currentPhase['minimum_trading_days'] ?? 0),
                'leverage' => $currentPhase['leverage'] ?? null,
                'funded_profit_split' => (float) ($fundedRules['profit_split'] ?? $account->profit_split),
                'first_payout_days' => (int) ($fundedRules['first_withdrawal_days'] ?? $account->challengePlan?->first_payout_days ?? 21),
                'payout_cycle_days' => (int) ($fundedRules['payout_cycle_days'] ?? $account->challengePlan?->payout_cycle_days ?? 14),
                'consistency_rule_required' => (bool) ($fundedRules['consistency_rule_required'] ?? false),
            ],
            'models' => $catalog,
            'default_consistency_limit' => $this->knowledgeBase->defaultConsistencyLimit(),
            'pass_fail_items' => $this->knowledgeBase->passFailItems(),
        ];
    }

    private function planLabel(string $challengeType, int $accountSize): string
    {
        $label = config("wolforix.challenge_catalog.{$challengeType}.label", $challengeType);

        return sprintf('%s / %dK', $label, (int) ($accountSize / 1000));
    }

    private function phaseLabel(TradingAccount $account): string
    {
        return match (true) {
            $account->challenge_type === 'one_step' => 'Single Phase',
            (int) $account->phase_index > 1 => 'Phase 2',
            default => 'Phase 1',
        };
    }

    private function humanizeStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        $prefix = match (strtoupper($currency)) {
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        return ($amount < 0 ? '-' : '').$prefix.number_format(abs($amount), 2);
    }

    private function formatDateTime(mixed $value): string
    {
        if (! $value instanceof \DateTimeInterface) {
            return 'Not available yet';
        }

        return Carbon::instance($value)->setTimezone(config('app.timezone'))->format('M j, Y g:i A');
    }

    private function usagePercent(float $used, float $limit): float
    {
        if ($limit <= 0) {
            return 0.0;
        }

        return round(($used / $limit) * 100, 2);
    }
}
