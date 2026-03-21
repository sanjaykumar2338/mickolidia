<?php

namespace Database\Seeders;

use App\Models\ChallengePlan;
use App\Models\PayoutRequest;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChallengePlanSeeder::class);

        $user = User::query()->updateOrCreate([
            'email' => 'demo@wolforix.test',
        ], [
            'name' => 'Wolforix Demo Trader',
            'password' => Hash::make('password'),
        ]);

        UserProfile::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'preferred_language' => 'en',
            'country' => 'Germany',
            'city' => 'Berlin',
            'timezone' => 'Europe/Berlin',
            'kyc_status' => 'pending',
        ]);

        $plan = ChallengePlan::query()->where('slug', 'two-step-50000')->first()
            ?? ChallengePlan::query()->first();

        if ($plan instanceof ChallengePlan) {
            $account = TradingAccount::query()->updateOrCreate([
                'account_reference' => 'WFX-CT-50021',
            ], [
                'user_id' => $user->id,
                'challenge_plan_id' => $plan->id,
                'platform' => 'cTrader',
                'stage' => 'Challenge Step 1',
                'status' => 'Active',
                'starting_balance' => 50000,
                'balance' => 54320,
                'total_profit' => 4320,
                'today_profit' => 1625,
                'drawdown_percent' => 2.4,
                'consistency_limit_percent' => 40,
                'minimum_trading_days' => 3,
                'trading_days_completed' => 2,
                'synced_at' => now(),
                'meta' => [
                    'source' => 'milestone-1-demo',
                    'note' => 'Mock data for dashboard preview',
                ],
            ]);

            PayoutRequest::query()->updateOrCreate([
                'trading_account_id' => $account->id,
                'status' => 'pending',
            ], [
                'user_id' => $user->id,
                'requested_amount' => 2200,
                'eligible_amount' => 1980,
                'currency' => 'EUR',
                'requested_at' => now()->subDays(2),
                'notes' => 'Demo payout record seeded for Milestone 1 placeholder UI.',
            ]);
        }
    }
}
