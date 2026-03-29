<?php

namespace App\Services\TradingPlatforms;

use App\Models\TradingAccount;

interface TradingPlatformClientInterface
{
    public function slug(): string;

    public function isEnabled(): bool;

    public function isConfigured(): bool;

    /**
     * @return array<string, mixed>
     */
    public function fetchAccountSnapshot(TradingAccount $account): array;
}
