<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

class ChallengePricingService
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(?string $currency = null): array
    {
        $catalog = [];

        foreach (config('wolforix.challenge_models', []) as $challengeType => $definition) {
            $catalog[$challengeType] = [
                'steps' => $definition['steps'],
                'phases' => array_values($definition['phases']),
                'funded' => $definition['funded'],
                'plans' => [],
            ];

            foreach (array_keys($definition['pricing']) as $accountSize) {
                $catalog[$challengeType]['plans'][(int) $accountSize] = $this->resolvePlan(
                    $challengeType,
                    (int) $accountSize,
                    $currency,
                );
            }
        }

        return $catalog;
    }

    /**
     * @return array<int, int>
     */
    public function sizes(): array
    {
        $sizes = [];

        foreach (config('wolforix.challenge_models', []) as $definition) {
            foreach (array_keys($definition['pricing']) as $accountSize) {
                $sizes[(int) $accountSize] = (int) $accountSize;
            }
        }

        ksort($sizes);

        return array_values($sizes);
    }

    public function defaultChallengeType(): ?string
    {
        return array_key_first(config('wolforix.challenge_models', []));
    }

    public function defaultChallengeSize(?string $challengeType = null): ?string
    {
        $challengeType ??= $this->defaultChallengeType();

        if (! is_string($challengeType)) {
            return null;
        }

        $pricing = config("wolforix.challenge_models.{$challengeType}.pricing", []);

        return ($firstKey = array_key_first($pricing)) !== null
            ? (string) $firstKey
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePlan(string $challengeType, int $accountSize, ?string $currency = null): array
    {
        $definition = config("wolforix.challenge_models.{$challengeType}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unsupported challenge type [{$challengeType}].");
        }

        $pricing = $definition['pricing'] ?? [];
        $usdBasePrice = $pricing[$accountSize] ?? null;

        if (! is_numeric($usdBasePrice)) {
            throw new InvalidArgumentException("Unsupported account size [{$accountSize}] for [{$challengeType}].");
        }

        $currency = $this->normalizeCurrency($currency);
        $priceSnapshot = $this->priceSnapshot((float) $usdBasePrice, $currency);
        $firstPhase = $definition['phases'][0];

        return [
            'slug' => $this->slug($challengeType, $accountSize),
            'name' => $definition['steps'].'-Step '.((int) ($accountSize / 1000)).'K',
            'challenge_type' => $challengeType,
            'account_size' => $accountSize,
            'currency' => $currency,
            'base_currency' => 'USD',
            'base_price' => $priceSnapshot['base_price'],
            'list_price' => $priceSnapshot['list_price'],
            'discounted_price' => $priceSnapshot['final_price'],
            'entry_fee' => $priceSnapshot['final_price'],
            'discount' => [
                'enabled' => $priceSnapshot['discount_enabled'],
                'type' => config('wolforix.launch_discount.type', 'percentage'),
                'percent' => $priceSnapshot['discount_percent'],
                'amount' => $priceSnapshot['discount_amount'],
                'badge' => config('wolforix.launch_discount.badge', '20% OFF - Limited Launch Offer'),
                'urgency_text' => config('wolforix.launch_discount.urgency_text', 'Launch Discount - Limited Time Only'),
            ],
            'steps' => $definition['steps'],
            'phases' => array_values($definition['phases']),
            'funded' => $definition['funded'],
            'profit_target' => $firstPhase['profit_target'],
            'daily_loss_limit' => $firstPhase['daily_loss_limit'],
            'max_loss_limit' => $firstPhase['max_loss_limit'],
            'profit_share' => $definition['funded']['profit_split'],
            'first_payout_days' => $definition['funded']['first_withdrawal_days'] ?? $definition['funded']['payout_cycle_days'],
            'minimum_trading_days' => $firstPhase['minimum_trading_days'],
            'payout_cycle_days' => $definition['funded']['payout_cycle_days'],
            'maximum_trading_days' => $firstPhase['maximum_trading_days'],
            'leverage' => $firstPhase['leverage'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePlanBySlug(string $slug, ?string $currency = null): array
    {
        foreach ($this->catalog($currency) as $challengeType => $definition) {
            foreach ($definition['plans'] as $accountSize => $plan) {
                if (($plan['slug'] ?? null) === $slug) {
                    return $this->resolvePlan($challengeType, (int) $accountSize, $currency);
                }
            }
        }

        throw new InvalidArgumentException("Unsupported plan slug [{$slug}].");
    }

    public function supportedProviderCurrencies(): array
    {
        return array_keys(config('wolforix.currencies', []));
    }

    public function slug(string $challengeType, int $accountSize): string
    {
        return str_replace('_', '-', $challengeType).'-'.$accountSize;
    }

    /**
     * @return array<string, mixed>
     */
    public function priceSnapshot(float $usdBasePrice, ?string $currency = null): array
    {
        $currency = $this->normalizeCurrency($currency);
        $rate = (float) config("wolforix.currencies.{$currency}.rate", 1);
        $launchDiscount = config('wolforix.launch_discount', []);
        $discountEnabled = (bool) ($launchDiscount['enabled'] ?? false);
        $discountPercent = $discountEnabled ? (float) ($launchDiscount['percent'] ?? 0) : null;

        $listPrice = round($usdBasePrice * $rate, 0);
        $finalPrice = $discountEnabled
            ? round($listPrice * ((100 - (float) $discountPercent) / 100), 0)
            : $listPrice;

        return [
            'currency' => $currency,
            'base_price' => round($usdBasePrice, 2),
            'list_price' => $listPrice,
            'final_price' => $finalPrice,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountEnabled ? round($listPrice - $finalPrice, 0) : 0.0,
            'discount_enabled' => $discountEnabled,
        ];
    }

    private function normalizeCurrency(?string $currency): string
    {
        $currency = strtoupper((string) ($currency ?: config('wolforix.default_currency', 'USD')));

        if (! array_key_exists($currency, config('wolforix.currencies', []))) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }

        return $currency;
    }
}
