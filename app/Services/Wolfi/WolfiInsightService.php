<?php

namespace App\Services\Wolfi;

class WolfiInsightService
{
    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function generate(array $context): array
    {
        $account = data_get($context, 'account');

        if (! is_array($account)) {
            return [];
        }

        $insights = [];
        $drawdownThreshold = (float) config('wolfi.smart_insights.thresholds.drawdown_usage', 70);
        $profitTargetThreshold = (float) config('wolfi.smart_insights.thresholds.profit_target_progress', 70);
        $consistencyThreshold = (float) config('wolfi.smart_insights.thresholds.consistency_ratio', 35);

        $dailyUsage = (float) ($account['daily_drawdown_usage_percent'] ?? 0);
        $maxUsage = (float) ($account['max_drawdown_usage_percent'] ?? 0);

        if ($dailyUsage > $drawdownThreshold || $maxUsage > $drawdownThreshold) {
            $focusesDaily = $dailyUsage >= $maxUsage;

            $insights[] = [
                'key' => 'risk_alert',
                'icon' => '⚠',
                'label' => 'Risk Alert',
                'message' => $focusesDaily
                    ? 'Your account is approaching the daily drawdown limit.'
                    : 'Your account is approaching the max drawdown limit.',
                'meta' => sprintf(
                    'Daily usage %s%% • Max usage %s%% • Floating P&L %s',
                    $this->formatPercent($dailyUsage),
                    $this->formatPercent($maxUsage),
                    $account['floating_pnl'] ?? '$0.00',
                ),
                'prompt' => 'Explain my drawdown risk and remaining room',
                'tone' => 'rose',
            ];
        }

        $targetProgress = (float) ($account['target_progress_percent'] ?? 0);

        if ($targetProgress > $profitTargetThreshold) {
            $insights[] = [
                'key' => 'profit_progress',
                'icon' => '🎯',
                'label' => 'Profit Progress',
                'message' => 'You are close to reaching your profit target.',
                'meta' => sprintf(
                    'Progress %s%% • Realized profit %s • Balance %s',
                    $this->formatPercent($targetProgress),
                    $account['realized_profit'] ?? '$0.00',
                    $account['balance'] ?? '$0.00',
                ),
                'prompt' => 'Explain my metrics and profit target progress',
                'tone' => 'amber',
            ];
        }

        $consistencyRatio = (float) ($account['consistency_ratio_percent'] ?? 0);

        if ($consistencyRatio > $consistencyThreshold) {
            $insights[] = [
                'key' => 'consistency_warning',
                'icon' => '📊',
                'label' => 'Consistency Warning',
                'message' => 'You are approaching the consistency rule limit.',
                'meta' => sprintf(
                    'Single-day ratio %s%% • Highest day profit %s',
                    $this->formatPercent($consistencyRatio),
                    $account['consistency_highest_day_profit'] ?? '$0.00',
                ),
                'prompt' => 'Explain my consistency warning',
                'tone' => 'sky',
            ];
        }

        if ((bool) ($account['payout_ready'] ?? false)) {
            $insights[] = [
                'key' => 'payout_readiness',
                'icon' => '💰',
                'label' => 'Payout Readiness',
                'message' => 'Your account may be eligible for payout.',
                'meta' => sprintf(
                    'Profit split %s%% • Next payout window %s',
                    $this->formatPercent((float) ($account['profit_split_percent'] ?? 0)),
                    $account['payout_eligible_at'] ?? 'Not available yet',
                ),
                'prompt' => 'Explain my payout readiness',
                'tone' => 'emerald',
            ];
        }

        return $insights;
    }

    private function formatPercent(float $value): string
    {
        $formatted = number_format($value, 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
