<?php

namespace App\Services\TradingAccounts;

use App\Services\ChallengeRuleEngine;
use App\Models\TradingAccount;

class ChallengeRuleEvaluator
{
    public function __construct(
        private readonly ChallengeRuleEngine $ruleEngine,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(TradingAccount $account): array
    {
        return $this->ruleEngine->evaluate($account);
    }
}
