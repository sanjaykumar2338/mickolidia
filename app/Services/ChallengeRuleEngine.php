<?php

namespace App\Services;

use App\Models\TradingAccount;
use App\Services\Challenge\ChallengeProgressEngine;

class ChallengeRuleEngine
{
    public function __construct(
        private readonly ChallengeProgressEngine $progressEngine,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(TradingAccount $account): array
    {
        return $this->progressEngine->evaluate($account);
    }
}
