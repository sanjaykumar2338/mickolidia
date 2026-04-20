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
                'label' => __('site.dashboard.wolfi.insights.risk_alert.label'),
                'message' => $focusesDaily
                    ? __('site.dashboard.wolfi.insights.risk_alert.daily_message')
                    : __('site.dashboard.wolfi.insights.risk_alert.max_message'),
                'meta' => __('site.dashboard.wolfi.insights.risk_alert.meta', [
                    'daily' => $this->formatPercent($dailyUsage),
                    'max' => $this->formatPercent($maxUsage),
                    'pnl' => $account['floating_pnl'] ?? '$0.00',
                ]),
                'prompt' => __('site.dashboard.wolfi.insights.risk_alert.prompt'),
                'tone' => 'rose',
            ];
        }

        $targetProgress = (float) ($account['target_progress_percent'] ?? 0);

        if ($targetProgress > $profitTargetThreshold) {
            $insights[] = [
                'key' => 'profit_progress',
                'icon' => '🎯',
                'label' => __('site.dashboard.wolfi.insights.profit_progress.label'),
                'message' => __('site.dashboard.wolfi.insights.profit_progress.message'),
                'meta' => __('site.dashboard.wolfi.insights.profit_progress.meta', [
                    'progress' => $this->formatPercent($targetProgress),
                    'profit' => $account['realized_profit'] ?? '$0.00',
                    'balance' => $account['balance'] ?? '$0.00',
                ]),
                'prompt' => __('site.dashboard.wolfi.insights.profit_progress.prompt'),
                'tone' => 'amber',
            ];
        }

        $consistencyRatio = (float) ($account['consistency_ratio_percent'] ?? 0);

        if ($consistencyRatio > $consistencyThreshold) {
            $insights[] = [
                'key' => 'consistency_warning',
                'icon' => '📊',
                'label' => __('site.dashboard.wolfi.insights.consistency_warning.label'),
                'message' => __('site.dashboard.wolfi.insights.consistency_warning.message'),
                'meta' => __('site.dashboard.wolfi.insights.consistency_warning.meta', [
                    'ratio' => $this->formatPercent($consistencyRatio),
                    'profit' => $account['consistency_highest_day_profit'] ?? '$0.00',
                ]),
                'prompt' => __('site.dashboard.wolfi.insights.consistency_warning.prompt'),
                'tone' => 'sky',
            ];
        }

        if ((bool) ($account['payout_ready'] ?? false)) {
            $insights[] = [
                'key' => 'payout_readiness',
                'icon' => '💰',
                'label' => __('site.dashboard.wolfi.insights.payout_readiness.label'),
                'message' => __('site.dashboard.wolfi.insights.payout_readiness.message'),
                'meta' => __('site.dashboard.wolfi.insights.payout_readiness.meta', [
                    'split' => $this->formatPercent((float) ($account['profit_split_percent'] ?? 0)),
                    'window' => $account['payout_eligible_at'] ?? __('Not available yet'),
                ]),
                'prompt' => __('site.dashboard.wolfi.insights.payout_readiness.prompt'),
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
