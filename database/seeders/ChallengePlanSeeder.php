<?php

namespace Database\Seeders;

use App\Models\ChallengePlan;
use Illuminate\Database\Seeder;

class ChallengePlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('wolforix.challenge_plans', []) as $plan) {
            ChallengePlan::updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'account_size' => $plan['account_size'],
                    'currency' => $plan['currency'],
                    'entry_fee' => $plan['entry_fee'],
                    'profit_target' => $plan['profit_target'],
                    'daily_loss_limit' => $plan['daily_loss_limit'],
                    'max_loss_limit' => $plan['max_loss_limit'],
                    'steps' => $plan['steps'],
                    'profit_share' => $plan['profit_share'],
                    'first_payout_days' => $plan['first_payout_days'],
                    'minimum_trading_days' => $plan['minimum_trading_days'],
                    'payout_cycle_days' => $plan['payout_cycle_days'],
                    'is_active' => true,
                ],
            );
        }
    }
}
