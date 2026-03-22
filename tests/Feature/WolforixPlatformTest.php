<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WolforixPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_successfully(): void
    {
        foreach ([
            route('login'),
            route('home'),
            route('faq'),
            route('terms'),
            route('risk-disclosure'),
            route('payout-policy'),
            route('refund-policy'),
            route('privacy-policy'),
            route('aml-kyc'),
            route('company-info'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_home_page_contains_the_refined_challenge_selector_and_fixed_disclaimer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('1-Step Challenge')
            ->assertSee('2-Step Challenge')
            ->assertSee('5K')
            ->assertSee('100K')
            ->assertSee('USD')
            ->assertSee('EUR')
            ->assertSee('GBP')
            ->assertSee('80% Profit Split')
            ->assertSee('$100K Simulated Capital')
            ->assertSee('Single Phase')
            ->assertSee('Funded Account')
            ->assertSee('20% OFF - Limited Launch Offer')
            ->assertSee('Launch Discount - Limited Time Only')
            ->assertSee('20% OFF - Limited Offer on all plans')
            ->assertSee('$49')
            ->assertSee('$39')
            ->assertSee('Payout Policy')
            ->assertSee('Dismiss notice')
            ->assertSee('Login')
            ->assertSee('Street address')
            ->assertSee('Country');
    }

    public function test_dashboard_foundation_pages_render_successfully(): void
    {
        foreach ([
            route('dashboard'),
            route('dashboard.accounts'),
            route('dashboard.payouts'),
            route('dashboard.settings'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_locale_switch_updates_session_and_redirects_back(): void
    {
        $response = $this->from(route('home'))->post(route('locale.update', 'de'), [
            'redirect' => route('home'),
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('locale', 'de');
    }

    public function test_checkout_requires_the_mandatory_agreement(): void
    {
        $response = $this->from(route('home'))->post(route('challenge.checkout.store'), [
            'full_name' => 'Test Trader',
            'email' => 'trader@example.com',
            'plan' => 'two-step-50000',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors([
                'street_address',
                'city',
                'postal_code',
                'country',
            ])
            ->assertSessionHasErrors('accept_terms');
    }

    public function test_checkout_stub_accepts_a_valid_payload(): void
    {
        $response = $this->from(route('home'))->post(route('challenge.checkout.store'), [
            'full_name' => 'Test Trader',
            'email' => 'trader@example.com',
            'street_address' => '1 Market Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'plan' => 'two-step-50000',
            'accept_terms' => '1',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('checkout_success');
    }

    public function test_payout_policy_contains_the_updated_cycle_wording(): void
    {
        $this->get(route('payout-policy'))
            ->assertOk()
            ->assertSee('Payouts are processed every 14 days with a maximum limit per cycle.');
    }

    public function test_company_information_contains_the_updated_address(): void
    {
        $this->get(route('company-info'))
            ->assertOk()
            ->assertSee('Suite RA01, 195-197 Wood Street, London, E17 3NU');
    }

    public function test_admin_clients_requires_basic_auth(): void
    {
        $this->get(route('admin.clients.index'))
            ->assertUnauthorized();
    }

    public function test_admin_can_view_client_list_and_metrics(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');

        $user = User::factory()->create([
            'name' => 'Admin Review Trader',
            'email' => 'admin-review@example.com',
            'plan_type' => '1-Step Challenge',
            'account_size' => 25000,
            'payment_amount' => 159,
            'status' => 'completed',
        ]);

        UserProfile::query()->create([
            'user_id' => $user->id,
            'preferred_language' => 'en',
            'country' => 'Spain',
            'city' => 'Madrid',
            'timezone' => 'Europe/Madrid',
            'kyc_status' => 'approved',
            'marketing_opt_in' => false,
        ]);

        $plan = ChallengePlan::query()->create([
            'slug' => 'one-step-25000',
            'name' => '1-Step 25K',
            'account_size' => 25000,
            'currency' => 'USD',
            'entry_fee' => 159,
            'profit_target' => 10,
            'daily_loss_limit' => 4,
            'max_loss_limit' => 8,
            'steps' => 1,
            'profit_share' => 80,
            'first_payout_days' => 14,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
            'is_active' => true,
        ]);

        TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'account_reference' => 'WFX-ADMIN-25001',
            'platform' => 'cTrader',
            'stage' => 'Challenge Step 1',
            'status' => 'Completed',
            'starting_balance' => 25000,
            'balance' => 28350,
            'total_profit' => 3350,
            'today_profit' => 250,
            'drawdown_percent' => 1.7,
            'consistency_limit_percent' => 40,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 4,
        ]);

        $this->withBasicAuth('admin', 'secret')
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Admin Review Trader')
            ->assertSee('Spain')
            ->assertSee('1-Step Challenge / 25K')
            ->assertSee('$159.00')
            ->assertSee('View Metrics');

        $this->withBasicAuth('admin', 'secret')
            ->get(route('admin.clients.show', $user))
            ->assertOk()
            ->assertSee('Admin Review Trader')
            ->assertSee('Profit')
            ->assertSee('$3,350.00')
            ->assertSee('Current Status')
            ->assertSee('Completed');
    }
}
