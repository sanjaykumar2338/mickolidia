<?php

namespace Database\Seeders;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Order;
use App\Models\PaymentAttempt;
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
            'plan_type' => '2-Step Pro',
            'account_size' => 50000,
            'payment_amount' => 31.00,
            'status' => 'active',
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
            $order = Order::query()->updateOrCreate([
                'order_number' => 'WFX-ORD-DEMO50000',
            ], [
                'user_id' => $user->id,
                'challenge_plan_id' => $plan->id,
                'email' => $user->email,
                'full_name' => $user->name,
                'street_address' => '1 Market Street',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'country' => 'DE',
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
                'payment_provider' => 'stripe',
                'base_price' => 39.00,
                'discount_percent' => 20,
                'discount_amount' => 8.00,
                'final_price' => 31.00,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'external_checkout_id' => 'cs_demo_50000',
                'external_payment_id' => 'pi_demo_50000',
                'external_customer_id' => 'cus_demo_50000',
                'metadata' => [
                    'seeded' => true,
                    'locale' => 'en',
                ],
            ]);

            PaymentAttempt::query()->updateOrCreate([
                'order_id' => $order->id,
                'provider' => 'stripe',
            ], [
                'provider_session_id' => 'cs_demo_50000',
                'provider_payment_id' => 'pi_demo_50000',
                'amount' => 31.00,
                'currency' => 'USD',
                'status' => 'completed',
                'payload' => [
                    'seeded' => true,
                ],
            ]);

            ChallengePurchase::query()->updateOrCreate([
                'order_id' => $order->id,
            ], [
                'user_id' => $user->id,
                'challenge_plan_id' => $plan->id,
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
                'account_status' => 'active',
                'funded_status' => null,
                'meta' => [
                    'seeded' => true,
                ],
            ]);

            $account = TradingAccount::query()->updateOrCreate([
                'account_reference' => 'WFX-CT-50021',
            ], [
                'user_id' => $user->id,
                'challenge_plan_id' => $plan->id,
                'platform' => 'cTrader',
                'stage' => 'Challenge Step 1',
                'status' => 'Active',
                'account_type' => 'challenge',
                'is_trial' => false,
                'starting_balance' => 50000,
                'balance' => 54320,
                'equity' => 54320,
                'daily_drawdown' => 0,
                'max_drawdown' => 1200,
                'profit_loss' => 4320,
                'total_profit' => 4320,
                'today_profit' => 1625,
                'drawdown_percent' => 2.4,
                'consistency_limit_percent' => 40,
                'minimum_trading_days' => 3,
                'trading_days_completed' => 2,
                'allowed_symbols' => ['XAU/USD', 'EUR/USD', 'USD/JPY'],
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
