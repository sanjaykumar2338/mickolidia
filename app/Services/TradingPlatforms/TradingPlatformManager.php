<?php

namespace App\Services\TradingPlatforms;

use App\Models\TradingAccount;
use InvalidArgumentException;

class TradingPlatformManager
{
    public function __construct(
        private readonly CTraderService $ctraderService,
    ) {
    }

    public function forAccount(TradingAccount $account): TradingPlatformClientInterface
    {
        return $this->driver((string) ($account->platform_slug ?: config('trading.platforms.default', 'ctrader')));
    }

    public function driver(string $platform): TradingPlatformClientInterface
    {
        return match (strtolower($platform)) {
            'ctrader' => $this->ctraderService,
            default => throw new InvalidArgumentException("Unsupported trading platform [{$platform}]."),
        };
    }
}
