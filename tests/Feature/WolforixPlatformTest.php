<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Pricing\ChallengePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Fixtures\FakeStripePaymentGateway;

class WolforixPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_successfully(): void
    {
        foreach ([
            route('login'),
            route('home'),
            route('about'),
            route('faq'),
            route('trial.register'),
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

    public function test_about_page_contains_the_about_story_and_header_link(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('About')
            ->assertSee('Wolforix')
            ->assertSee('Our mission')
            ->assertSee('Identify, train, and fund traders who are ready to perform.')
            ->assertSee(route('about'), false);
    }

    public function test_checkout_requires_authentication_and_redirects_to_login_with_intended_destination(): void
    {
        $selection = [
            'challenge_type' => 'two_step',
            'account_size' => 50000,
            'currency' => 'EUR',
        ];
        $checkoutUrl = route('checkout.show', $selection);

        $this->get($checkoutUrl)
            ->assertRedirect(route('login'));

        $this->assertCheckoutSelectionMatchesUrl($selection, (string) session('url.intended'));
    }

    public function test_registration_returns_user_to_the_intended_checkout_flow(): void
    {
        $selection = [
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'currency' => 'USD',
        ];
        $checkoutUrl = route('checkout.show', $selection);

        $this->get($checkoutUrl)->assertRedirect(route('login'));

        $response = $this->post(route('register.store'), [
            'register_name' => 'Checkout Trader',
            'register_email' => 'checkout-auth@example.com',
            'register_password' => 'password123',
            'register_password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertCheckoutSelectionMatchesUrl($selection, $response->headers->get('Location'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'checkout-auth@example.com',
        ]);
    }

    public function test_login_returns_existing_user_to_the_intended_checkout_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'existing-checkout@example.com',
            'password' => 'password123',
        ]);

        $selection = [
            'challenge_type' => 'two_step',
            'account_size' => 100000,
            'currency' => 'GBP',
        ];
        $checkoutUrl = route('checkout.show', $selection);

        $this->get($checkoutUrl)->assertRedirect(route('login'));

        $response = $this->post(route('login.store'), [
            'login_email' => $user->email,
            'login_password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertCheckoutSelectionMatchesUrl($selection, $response->headers->get('Location'));

        $this->assertAuthenticatedAs($user);
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
            ->assertSee('Free Trial')
            ->assertSee('Single Phase')
            ->assertSee('Funded Account')
            ->assertSee('20% OFF - Limited Launch Offer')
            ->assertSee('Launch Discount - Limited Time Only')
            ->assertSee('20% OFF - Limited Offer on all plans')
            ->assertSee('$49')
            ->assertSee('$39')
            ->assertSee('Secure checkout')
            ->assertSee('Payout Policy')
            ->assertSee('Dismiss notice')
            ->assertSee('Login')
            ->assertSee('About')
            ->assertSee('Continue to Secure Checkout')
            ->assertSee('Stripe card checkout is live in this milestone.')
            ->assertDontSee('Our mission')
            ->assertDontSee('Identify, train, and fund traders who are ready to perform.');
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
        $response = $this->from(route('home'))->get(route('locale.update', [
            'locale' => 'fr',
            'redirect' => route('home'),
        ]));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('locale', 'fr');
        $response->assertCookie('wolforix_locale', 'fr');
    }

    public function test_french_locale_renders_core_homepage_copy(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Essai Gratuit')
            ->assertSee('Devise')
            ->assertSee('Challenge 1-Step');
    }

    public function test_checkout_page_renders_selected_plan_and_provider_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', [
            'challenge_type' => 'two_step',
            'account_size' => 50000,
            'currency' => 'EUR',
        ]))
            ->assertOk()
            ->assertSee('Complete your challenge order')
            ->assertSee('Stripe')
            ->assertSee('PayPal')
            ->assertSee('EUR');
    }

    public function test_checkout_requires_the_mandatory_agreement(): void
    {
        $this->useFakeStripeGateway();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
            ]))
            ->post(route('checkout.store'), [
            'full_name' => 'Test Trader',
            'email' => 'trader@example.com',
            'street_address' => '1 Market Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'challenge_type' => 'two_step',
            'account_size' => 50000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
        ]);

        $response
            ->assertRedirect(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
            ]))
            ->assertSessionHasErrors('accept_terms');
    }

    public function test_checkout_creates_an_order_for_the_authenticated_user_and_redirects_to_provider(): void
    {
        $this->useFakeStripeGateway();
        $user = User::factory()->create([
            'email' => 'account-owner@example.com',
        ]);

        $plan = app(ChallengePricingService::class)->resolvePlan('two_step', 50000, 'EUR');

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'Test Trader',
            'email' => 'billing-contact@example.com',
            'street_address' => '1 Market Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'challenge_type' => 'two_step',
            'account_size' => 50000,
            'currency' => 'EUR',
            'payment_provider' => 'stripe',
            'accept_terms' => '1',
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect('https://stripe.test/checkout/fake-session-'.$order->id);

        $this->assertSame('EUR', $order->currency);
        $this->assertSame('stripe', $order->payment_provider);
        $this->assertSame(Order::PAYMENT_PENDING, $order->payment_status);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('billing-contact@example.com', $order->email);
        $this->assertSame(number_format((float) $plan['discounted_price'], 2, '.', ''), (string) $order->final_price);
        $this->assertSame('fake-session-'.$order->id, $order->external_checkout_id);
        $this->assertCount(1, $order->paymentAttempts);
    }

    public function test_checkout_success_marks_order_paid_and_creates_purchase_for_the_authenticated_user(): void
    {
        $this->useFakeStripeGateway();
        $user = User::factory()->create([
            'email' => 'account-owner@example.com',
        ]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'Paid Trader',
            'email' => 'billing-paid@example.com',
            'street_address' => '2 Trade Street',
            'city' => 'London',
            'postal_code' => 'E17 3NU',
            'country' => 'GB',
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'accept_terms' => '1',
        ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('checkout.success', ['session_id' => $order->external_checkout_id]))
            ->assertOk()
            ->assertSee('Challenge order confirmed')
            ->assertSee($order->order_number);

        $order->refresh();

        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertDatabaseHas('challenge_purchases', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'challenge_type' => 'one_step',
            'account_status' => 'pending_activation',
        ]);
    }

    public function test_checkout_cancel_marks_order_canceled_and_preserves_retry_after_auth(): void
    {
        $this->useFakeStripeGateway();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'Retry Trader',
            'email' => 'retry-order@example.com',
            'street_address' => '3 Retry Lane',
            'city' => 'Madrid',
            'postal_code' => '28001',
            'country' => 'ES',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'GBP',
            'payment_provider' => 'stripe',
            'accept_terms' => '1',
        ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('checkout.cancel', ['order' => $order->order_number]))
            ->assertOk()
            ->assertSee('Retry Payment');

        $order->refresh();

        $this->assertSame(Order::PAYMENT_CANCELED, $order->payment_status);
        $this->assertSame(Order::STATUS_CANCELED, $order->order_status);
        $this->assertDatabaseMissing('challenge_purchases', [
            'order_id' => $order->id,
        ]);
    }

    public function test_stripe_webhook_is_idempotent_and_creates_one_purchase(): void
    {
        $this->useFakeStripeGateway();

        $user = User::factory()->create([
            'email' => 'webhook@example.com',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => null,
            'email' => $user->email,
            'full_name' => 'Webhook Trader',
            'street_address' => '4 Webhook Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
            'challenge_type' => 'two_step',
            'account_size' => 5000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 39.00,
            'discount_percent' => 20,
            'discount_amount' => 8.00,
            'final_price' => 31.00,
            'payment_status' => 'pending',
            'order_status' => 'awaiting_payment',
            'external_checkout_id' => 'fake-session-999',
            'external_payment_id' => 'fake-payment-999',
        ]);

        PaymentAttempt::query()->create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_session_id' => 'fake-session-999',
            'provider_payment_id' => 'fake-payment-999',
            'amount' => 31.00,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $payload = [
            'provider' => 'stripe',
            'event_id' => 'evt_test_1',
            'type' => 'checkout.session.completed',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'external_checkout_id' => 'fake-session-999',
            'external_payment_id' => 'fake-payment-999',
            'external_customer_id' => 'fake-customer-999',
            'amount' => 31.00,
            'currency' => 'USD',
            'status' => 'paid',
            'payload' => ['fake' => true],
            'source' => 'webhook',
        ];

        $this->postJson(route('payments.stripe.webhook'), $payload)->assertOk();
        $this->postJson(route('payments.stripe.webhook'), $payload)->assertOk();

        $this->assertSame(1, ChallengePurchase::query()->where('order_id', $order->id)->count());
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
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

    public function test_faq_contains_the_new_strategy_and_scalping_content(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Which instruments can I trade and what strategies are allowed?')
            ->assertSee('Trades closed in less than 60 seconds are strictly prohibited if they result in profit.');
    }

    public function test_terms_page_contains_news_and_scalping_rules(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('News Trading Rule')
            ->assertSee('It is prohibited to open or close trades 5 minutes before and 5 minutes after a high-impact news event.')
            ->assertSee('Scalping Rule')
            ->assertSee('Trades closed in less than 60 seconds are strictly prohibited if they result in profit.');
    }

    public function test_faq_contains_the_high_impact_news_rule(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Can I trade during high-impact news events?')
            ->assertSee('It is prohibited to open or close trades 5 minutes before and 5 minutes after a high-impact news event.');
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
            'first_payout_days' => 21,
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

        $order = Order::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'street_address' => '5 Billing Street',
            'city' => 'Madrid',
            'postal_code' => '28001',
            'country' => 'ES',
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 199,
            'discount_percent' => 20,
            'discount_amount' => 40.00,
            'final_price' => 159.00,
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'external_checkout_id' => 'cs_admin_25000',
            'external_payment_id' => 'pi_admin_25000',
        ]);

        ChallengePurchase::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'currency' => 'USD',
            'account_status' => 'active',
        ]);

        $this->withBasicAuth('admin', 'secret')
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Admin Review Trader')
            ->assertSee('Spain')
            ->assertSee('1-Step Challenge / 25K')
            ->assertSee('$159.00')
            ->assertSee('Stripe')
            ->assertSee('Paid')
            ->assertSee('View Metrics');

        $this->withBasicAuth('admin', 'secret')
            ->get(route('admin.clients.show', $user))
            ->assertOk()
            ->assertSee('Admin Review Trader')
            ->assertSee('Billing Summary')
            ->assertSee('Profit')
            ->assertSee('$3,350.00')
            ->assertSee('Current Status')
            ->assertSee('Active');
    }

    public function test_trial_registration_creates_a_trial_account_and_dashboard(): void
    {
        $response = $this->post(route('trial.store'), [
            'email' => 'trial@example.com',
            'password' => 'password123',
        ]);

        $user = User::query()->where('email', 'trial@example.com')->firstOrFail();
        $trialAccount = TradingAccount::query()
            ->where('user_id', $user->id)
            ->where('is_trial', true)
            ->first();

        $response->assertRedirect(route('trial.dashboard'));
        $this->assertNotNull($trialAccount);
        $this->assertSame('trial', $trialAccount->account_type);
        $this->assertEquals(10000.0, (float) $trialAccount->balance);

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('This is a Trial Account.')
            ->assertSee('No withdrawals')
            ->assertSee('XAU/USD');
    }

    public function test_trial_retry_archives_previous_trial_and_creates_a_new_one(): void
    {
        $user = User::factory()->create([
            'email' => 'retry-trial@example.com',
            'status' => 'active',
        ]);

        $trialAccount = TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-TRIAL-TEST01',
            'platform' => 'cTrader Demo',
            'stage' => 'Trial (Demo)',
            'status' => 'Ended',
            'account_type' => 'trial',
            'is_trial' => true,
            'starting_balance' => 10000,
            'balance' => 0,
            'equity' => 0,
            'daily_drawdown' => 500,
            'max_drawdown' => 1000,
            'profit_loss' => -1000,
            'total_profit' => -1000,
            'today_profit' => -250,
            'drawdown_percent' => 10,
            'consistency_limit_percent' => 40,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 1,
            'allowed_symbols' => ['XAU/USD', 'EUR/USD', 'USD/JPY'],
            'trial_status' => 'ended',
            'trial_started_at' => now()->subDays(4),
            'last_activity_at' => now()->subDay(),
            'ended_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->post(route('trial.retry'))
            ->assertRedirect(route('trial.dashboard'));

        $this->assertCount(2, TradingAccount::query()->where('user_id', $user->id)->where('is_trial', true)->get());
        $this->assertNotNull($trialAccount->fresh()->ended_at);
        $this->assertSame('ended', $trialAccount->fresh()->trial_status);
        $this->assertSame(
            'active',
            TradingAccount::query()
                ->where('user_id', $user->id)
                ->where('is_trial', true)
                ->latest('id')
                ->value('trial_status')
        );
    }

    private function useFakeStripeGateway(): void
    {
        config()->set('wolforix.payments.providers.stripe.class', FakeStripePaymentGateway::class);
    }

    /**
     * @param  array<string, int|string>  $selection
     */
    private function assertCheckoutSelectionMatchesUrl(array $selection, ?string $url): void
    {
        $this->assertNotNull($url);
        $this->assertSame(route('checkout.show', [], false), parse_url($url, PHP_URL_PATH));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame((string) $selection['challenge_type'], $query['challenge_type'] ?? null);
        $this->assertSame((string) $selection['account_size'], (string) ($query['account_size'] ?? ''));
        $this->assertSame((string) $selection['currency'], $query['currency'] ?? null);
    }
}
