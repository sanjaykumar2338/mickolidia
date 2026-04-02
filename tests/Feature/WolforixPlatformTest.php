<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Pricing\ChallengePricingService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;
use Tests\Fixtures\FakePayPalPaymentGateway;
use Tests\Fixtures\FakeStripePaymentGateway;

class WolforixPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_public_pages_render_successfully(): void
    {
        foreach ([
            route('login'),
            route('password.request'),
            route('home'),
            route('about'),
            route('security'),
            route('contact'),
            route('faq'),
            route('news'),
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
            ->assertSee('Contact Us')
            ->assertSee('Wolforix')
            ->assertSee('Our mission')
            ->assertSee('Identify, train, and fund traders who are ready to perform.')
            ->assertSee(route('about'), false)
            ->assertSee(route('contact'), false);
    }

    public function test_contact_page_contains_support_channels_and_voice_assistant(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Contact Us')
            ->assertSee(config('wolforix.support.email'))
            ->assertSee('Live chat')
            ->assertSee('Wolfi AI assistant')
            ->assertSee('Play answer')
            ->assertSee('Suggested prompts')
            ->assertSee('Can I trade during news?')
            ->assertSee('"locale":"en"', false)
            ->assertSee('"locale":"de"', false)
            ->assertSee('"locale":"es"', false)
            ->assertSee('"locale":"fr"', false)
            ->assertSee(route('faq'), false);
    }

    public function test_news_page_renders_demo_calendar_filters_and_events(): void
    {
        $this->get(route('news'))
            ->assertOk()
            ->assertSee('Economic News Calendar')
            ->assertSee('Europe/Berlin')
            ->assertSee('Time')
            ->assertSee('Currency')
            ->assertSee('Impact')
            ->assertSee('Event name')
            ->assertSee('Forecast')
            ->assertSee('Previous')
            ->assertSee('Demo calendar feed')
            ->assertSee('Demo calendar mode')
            ->assertSee('High impact only')
            ->assertSee('Non-Farm Payrolls')
            ->assertSee('forexfactory.com');
    }

    public function test_news_page_can_filter_demo_events_by_impact_and_currency(): void
    {
        $this->get(route('news', [
            'impact' => 'high',
            'currency' => 'USD',
            'range' => 'this_week',
        ]))
            ->assertOk()
            ->assertSee('ISM Services PMI')
            ->assertSee('Non-Farm Payrolls')
            ->assertDontSee('Germany Factory Orders MoM')
            ->assertDontSee('BoE MPC Member Speech');
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
        Mail::fake();

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
        Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail): bool {
            return $mail->hasTo('checkout-auth@example.com')
                && $mail->user->email === 'checkout-auth@example.com';
        });
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

    public function test_facebook_social_callback_creates_user_and_links_provider(): void
    {
        Mail::fake();

        config()->set('services.facebook.client_id', 'facebook-client');
        config()->set('services.facebook.client_secret', 'facebook-secret');
        config()->set('services.facebook.redirect_uri', 'http://localhost/auth/facebook/callback');

        Http::fake([
            'https://graph.facebook.com/v20.0/oauth/access_token*' => Http::response([
                'access_token' => 'facebook-token',
            ]),
            'https://graph.facebook.com/me*' => Http::response([
                'id' => 'facebook-user-001',
                'name' => 'Facebook Trader',
                'email' => 'social-facebook@example.com',
                'picture' => [
                    'data' => [
                        'url' => 'https://cdn.example.com/facebook-trader.png',
                    ],
                ],
            ]),
        ]);

        $response = $this->withSession([
            'social_auth_state_facebook' => 'facebook-state',
        ])->get(route('social.callback', [
            'provider' => 'facebook',
            'state' => 'facebook-state',
            'code' => 'facebook-code',
        ]));

        $response->assertRedirect(route('home'));

        $user = User::query()->where('email', 'social-facebook@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'facebook',
            'provider_user_id' => 'facebook-user-001',
            'provider_email' => 'social-facebook@example.com',
        ]);

        Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail): bool {
            return $mail->hasTo('social-facebook@example.com');
        });
    }

    public function test_apple_social_callback_links_existing_user_by_email(): void
    {
        config()->set('services.apple.client_id', 'apple-client');
        config()->set('services.apple.client_secret', 'apple-secret');
        config()->set('services.apple.redirect_uri', 'http://localhost/auth/apple/callback');

        $user = User::factory()->create([
            'email' => 'apple-linked@example.com',
            'password' => 'password123',
        ]);

        Http::fake([
            'https://appleid.apple.com/auth/token' => Http::response([
                'id_token' => $this->fakeIdToken([
                    'iss' => 'https://appleid.apple.com',
                    'aud' => 'apple-client',
                    'exp' => now()->addMinutes(10)->timestamp,
                    'sub' => 'apple-user-001',
                    'email' => 'apple-linked@example.com',
                    'email_verified' => true,
                    'is_private_email' => false,
                ]),
            ]),
        ]);

        $response = $this->withSession([
            'social_auth_state_apple' => 'apple-state',
        ])->post(route('social.callback', ['provider' => 'apple']), [
            'state' => 'apple-state',
            'code' => 'apple-code',
            'user' => json_encode([
                'name' => [
                    'firstName' => 'Apple',
                    'lastName' => 'Trader',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_user_id' => 'apple-user-001',
            'provider_email' => 'apple-linked@example.com',
        ]);
    }

    public function test_users_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset-trader@example.com',
            'password' => 'old-password123',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot Password?')
            ->assertSee(route('password.request'), false);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])
            ->assertRedirect()
            ->assertSessionHas('status', __('site.auth.passwords.status.sent'));

        $resetToken = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$resetToken): bool {
            $resetToken = $notification->token;

            return true;
        });

        $this->assertNotNull($resetToken);

        $this->get(route('password.reset', [
            'token' => $resetToken,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Create a new password');

        $brokerToken = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $brokerToken,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('site.auth.passwords.status.reset'));

        $loginResponse = $this->post(route('login.store'), [
            'login_email' => $user->email,
            'login_password' => 'new-password123',
        ]);

        $loginResponse->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_login_page_prioritizes_the_sign_in_form_before_checkout_explanation(): void
    {
        $this->withSession([
            'url.intended' => route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
            ]),
        ])->get(route('login'))
            ->assertOk()
            ->assertSeeInOrder([
                __('site.auth.login.title'),
                __('site.auth.register.title'),
                __('site.auth.notice'),
            ])
            ->assertSee('Continue with Google')
            ->assertSee('Continue with Facebook')
            ->assertSee('Continue with Apple')
            ->assertSee('or continue with');
    }

    public function test_home_page_contains_the_refined_challenge_selector_and_fixed_disclaimer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Modern Prop Trading')
            ->assertSee('Get Funded. Get Paid. No Time Limits.')
            ->assertSee('Pass the challenge. Access funded accounts. Withdraw fast.')
            ->assertSee('1-Step Instant')
            ->assertSee('2-Step Pro')
            ->assertSee('Pass in one step. Get funded faster. No delays. No second phase.')
            ->assertSee('Lower risk. Higher scaling potential. Designed for consistency and long-term growth.')
            ->assertSee('5K')
            ->assertSee('100K')
            ->assertSee('USD')
            ->assertSee('EUR')
            ->assertSee('GBP')
            ->assertSee('🇺🇸')
            ->assertSee('🇪🇺')
            ->assertSee('🇬🇧')
            ->assertSee('Instant Funding Access')
            ->assertSee('Fast Payouts')
            ->assertSee('Scaling +25% Capital')
            ->assertSee('Up to 90% Profit Split')
            ->assertSee('Trust / Security')
            ->assertSee('Secure infrastructure')
            ->assertSee('Advanced risk control')
            ->assertSee('Real-time monitoring')
            ->assertSee('ISO/IEC 27001 aligned')
            ->assertSee('Talk to Your AI Assistant')
            ->assertSee('Start Chat')
            ->assertSee('Can I trade during news?')
            ->assertSee('AI Assistant')
            ->assertSee('We use cookies to improve your experience and support essential site functionality.')
            ->assertSee('Learn More')
            ->assertSee('Accept')
            ->assertSee('Free Trial')
            ->assertSee('No risk. No credit card.')
            ->assertSee('Single Phase')
            ->assertSee('Funded Account')
            ->assertSee('Get funded and start earning profits from your very first payout.')
            ->assertSee('Choose your model, pass the evaluation, and access real capital with clear rules and fast payouts.')
            ->assertSee('Launch Discount - Limited Time Only')
            ->assertSee('20% OFF - Launch Access Ending Soon')
            ->assertSee(config('wolforix.launch_discount.code'))
            ->assertSee('Start Challenge')
            ->assertSee('Get Plan')
            ->assertSee('Get Discount')
            ->assertSee('Ignore')
            ->assertSee('$49')
            ->assertSee('Secure checkout')
            ->assertSee('Payout Policy')
            ->assertSee('Dismiss notice')
            ->assertSee('Login')
            ->assertSee('NEWS')
            ->assertSee('About')
            ->assertSee('Contact Us')
            ->assertSee('Search the site')
            ->assertSee('Pay with card through Stripe using the same protected order and fulfillment flow.')
            ->assertSee('Security aligned with ISO/IEC 27001 standards (in progress)')
            ->assertSee('Wolforix does not provide brokerage services, investment advice, or portfolio management.')
            ->assertSee(asset('trading123.png'), false)
            ->assertSee(asset('newfolder/mobile1.webp'), false)
            ->assertDontSee('Dashboard Preview')
            ->assertDontSee('Our mission')
            ->assertDontSee('Identify, train, and fund traders who are ready to perform.');
    }

    public function test_launch_offer_apply_persists_discount_for_the_session(): void
    {
        $response = $this->from(route('home'))
            ->post(route('launch-offer.update'), [
                'decision' => 'apply',
                'redirect_to' => route('home').'#plans',
            ]);

        $response
            ->assertRedirect(route('home').'#plans')
            ->assertSessionHas('launch_offer.decision', 'apply')
            ->assertSessionHas('launch_offer.applied', true)
            ->assertSessionHas('launch_offer.promo_code', config('wolforix.launch_discount.code'));

        $this->withSession([
            'launch_offer' => [
                'decision' => 'apply',
                'applied' => true,
                'promo_code' => config('wolforix.launch_discount.code'),
            ],
        ])->get(route('home'))
            ->assertOk()
            ->assertSee('Get Plan')
            ->assertSee('$39')
            ->assertSee('20% OFF - Limited Launch Offer');
    }

    public function test_launch_offer_ignore_keeps_regular_pricing_visible(): void
    {
        $response = $this->from(route('home'))
            ->post(route('launch-offer.update'), [
                'decision' => 'ignore',
                'redirect_to' => route('home'),
            ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('launch_offer', function (array $launchOffer): bool {
                return ($launchOffer['decision'] ?? null) === 'ignore'
                    && ($launchOffer['applied'] ?? null) === false
                    && array_key_exists('promo_code', $launchOffer)
                    && $launchOffer['promo_code'] === null;
            });

        $this->withSession([
            'launch_offer' => [
                'decision' => 'ignore',
                'applied' => false,
                'promo_code' => null,
            ],
        ])->get(route('home'))
            ->assertOk()
            ->assertDontSee('20% OFF - Launch Access Ending Soon')
            ->assertSee('$49')
            ->assertDontSee('$39');
    }

    public function test_dashboard_routes_require_authentication(): void
    {
        foreach ([
            route('dashboard'),
            route('dashboard.accounts'),
            route('dashboard.payouts'),
            route('dashboard.settings'),
            route('ctrader.auth.connect'),
            route('ctrader.auth.redirect'),
            route('ctrader.auth.callback'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('ctrader.auth.link-account'), [
                'trading_account_id' => 1,
                'platform_account_id' => '12345',
            ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_start_ctrader_authorization_flow(): void
    {
        $user = User::factory()->create();

        config([
            'services.ctrader.client_id' => 'client-id',
            'services.ctrader.client_secret' => 'client-secret',
            'services.ctrader.auth_url' => 'https://id.ctrader.com/my/settings/openapi/grantingaccess/',
            'services.ctrader.token_url' => 'https://openapi.ctrader.com/apps/token',
            'services.ctrader.scope' => 'accounts',
            'services.ctrader.redirect_uri' => 'http://localhost/auth/callback',
        ]);

        $this->actingAs($user)
            ->get(route('ctrader.auth.redirect'))
            ->assertRedirect('https://id.ctrader.com/my/settings/openapi/grantingaccess/?client_id=client-id&redirect_uri=http%3A%2F%2Flocalhost%2Fauth%2Fcallback&scope=accounts&product=web');
    }

    public function test_ctrader_callback_requires_an_authorization_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ctrader.auth.callback'))
            ->assertRedirect(route('dashboard.accounts'))
            ->assertSessionHas('error', 'cTrader did not return an authorization code.');
    }

    public function test_authenticated_users_can_access_dashboard_foundation_pages(): void
    {
        $user = User::factory()->create();

        foreach ([
            route('dashboard'),
            route('dashboard.accounts'),
            route('dashboard.payouts'),
            route('dashboard.settings'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
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
            ->assertSee('1-Step Instant');
    }

    public function test_spanish_locale_uses_compact_currency_labels_on_the_homepage(): void
    {
        $this->withSession(['locale' => 'es'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Prueba Gratis')
            ->assertSee('Moneda')
            ->assertSee('$ · USD')
            ->assertSee('£ · GBP')
            ->assertDontSee('Dólar estadounidense')
            ->assertDontSee('Libra esterlina');
    }

    public function test_security_page_contains_key_trust_sections(): void
    {
        $this->get(route('security'))
            ->assertOk()
            ->assertSee('Trust & Security')
            ->assertSee('Security')
            ->assertSee('Risk Management')
            ->assertSee('Data Protection')
            ->assertSee('Roadmap')
            ->assertSee('ISO/IEC 27001 alignment is currently in progress.');
    }

    public function test_checkout_page_renders_selected_plan_and_provider_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', [
            'challenge_type' => 'two_step',
            'account_size' => 50000,
            'currency' => 'EUR',
            'promo_code' => config('wolforix.launch_discount.code'),
        ]))
            ->assertOk()
            ->assertSee('Complete your challenge order')
            ->assertSee('Promo code')
            ->assertSee('Apply')
            ->assertSee(config('wolforix.launch_discount.code'))
            ->assertSee('Code applied successfully')
            ->assertSee('Regular price')
            ->assertSee('Stripe')
            ->assertSee('PayPal')
            ->assertSee('EUR')
            ->assertSee('Your data is protected using industry-standard security practices aligned with ISO/IEC 27001.')
            ->assertSee('Terms & Conditions')
            ->assertSee('country of residence')
            ->assertSee('Cancellation and Refund Policy');
    }

    public function test_checkout_promo_preview_returns_discounted_pricing_for_valid_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('checkout.promo.preview'), [
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
                'promo_code' => config('wolforix.launch_discount.code'),
            ])
            ->assertOk()
            ->assertJson([
                'applied' => true,
                'promo_code' => config('wolforix.launch_discount.code'),
                'message' => 'Code applied successfully',
                'pricing' => [
                    'discount_enabled' => true,
                    'discounted_price' => '231.00',
                    'list_price' => '289.00',
                    'currency' => 'USD',
                ],
            ]);
    }

    public function test_checkout_promo_preview_returns_invalid_feedback_for_bad_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('checkout.promo.preview'), [
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
                'promo_code' => 'INVALIDCODE',
            ])
            ->assertOk()
            ->assertJson([
                'applied' => false,
                'promo_code' => null,
                'message' => 'Invalid/expired code',
                'pricing' => [
                    'discount_enabled' => false,
                    'discounted_price' => '289.00',
                    'list_price' => '289.00',
                    'currency' => 'USD',
                ],
            ]);
    }

    public function test_checkout_page_uses_regular_pricing_when_launch_offer_is_ignored(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession([
                'launch_offer' => [
                    'decision' => 'ignore',
                    'applied' => false,
                    'promo_code' => null,
                ],
            ])->get(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'EUR',
            ]))
            ->assertOk()
            ->assertDontSee('Launch promo code')
            ->assertSee('266.00')
            ->assertDontSee('213.00');
    }

    public function test_100k_plans_apply_the_profit_split_upgrade_rules(): void
    {
        $pricingService = app(ChallengePricingService::class);

        foreach (['one_step', 'two_step'] as $challengeType) {
            $plan = $pricingService->resolvePlan($challengeType, 100000, 'USD');

            $this->assertSame(85, $plan['funded']['profit_split']);
            $this->assertSame(90, $plan['funded']['profit_split_upgrade']['profit_split']);
            $this->assertSame(2, $plan['funded']['profit_split_upgrade']['after_consecutive_payouts']);
        }

        $standardPlan = $pricingService->resolvePlan('two_step', 50000, 'USD');

        $this->assertSame(80, $standardPlan['funded']['profit_split']);
        $this->assertArrayNotHasKey('profit_split_upgrade', $standardPlan['funded']);
    }

    public function test_checkout_page_displays_100k_profit_split_upgrade_copy(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 100000,
                'currency' => 'USD',
            ]))
            ->assertOk()
            ->assertSee('85%')
            ->assertSee('90% after 2 consecutive payouts');
    }

    public function test_authenticated_checkout_page_shows_logout_in_header_instead_of_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', [
                'challenge_type' => 'one_step',
                'account_size' => 5000,
                'currency' => 'USD',
            ]))
            ->assertOk()
            ->assertSee('Logout')
            ->assertDontSee('Login');
    }

    public function test_checkout_requires_the_mandatory_confirmations(): void
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
            ->assertSessionHasErrors([
                'accept_terms_and_residency',
                'accept_refund_policy',
            ]);
    }

    public function test_checkout_rejects_an_invalid_launch_promo_code(): void
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
                'full_name' => 'Promo Test Trader',
                'email' => 'promo-invalid@example.com',
                'street_address' => '7 Promo Street',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'country' => 'DE',
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
                'promo_code' => 'INVALIDCODE',
                'payment_provider' => 'stripe',
                'accept_terms_and_residency' => '1',
                'accept_refund_policy' => '1',
            ]);

        $response
            ->assertRedirect(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'currency' => 'USD',
            ]))
            ->assertSessionHasErrors(['promo_code']);
    }

    public function test_checkout_creates_an_order_for_the_authenticated_user_and_redirects_to_provider(): void
    {
        $this->useFakeStripeGateway();
        $user = User::factory()->create([
            'email' => 'account-owner@example.com',
        ]);

        $plan = app(ChallengePricingService::class)->resolvePlan('two_step', 50000, 'EUR', true);

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
            'promo_code' => config('wolforix.launch_discount.code'),
            'payment_provider' => 'stripe',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
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
        $this->assertTrue($order->metadata['checkout_confirmations']['terms_and_residency']['accepted']);
        $this->assertSame('DE', $order->metadata['checkout_confirmations']['terms_and_residency']['country']);
        $this->assertTrue($order->metadata['checkout_confirmations']['refund_policy']['accepted']);
        $this->assertSame(config('wolforix.launch_discount.code'), $order->metadata['launch_promo']['code']);
        $this->assertTrue($order->metadata['launch_promo']['applied']);
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
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
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
        $this->assertDatabaseHas('trading_accounts', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => ChallengePurchase::query()->where('order_id', $order->id)->value('id'),
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'platform_slug' => 'ctrader',
            'account_status' => 'pending_activation',
        ]);
    }

    public function test_checkout_can_redirect_to_paypal_when_the_gateway_is_enabled(): void
    {
        $this->useFakePayPalGateway();
        $user = User::factory()->create([
            'email' => 'paypal-owner@example.com',
        ]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'PayPal Trader',
            'email' => 'paypal-billing@example.com',
            'street_address' => '8 Sandbox Street',
            'city' => 'Lisbon',
            'postal_code' => '1100-101',
            'country' => 'PT',
            'challenge_type' => 'two_step',
            'account_size' => 25000,
            'currency' => 'USD',
            'payment_provider' => 'paypal',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect('https://paypal.test/checkout/fake-paypal-order-'.$order->id);
        $this->assertSame('paypal', $order->payment_provider);
        $this->assertSame('fake-paypal-order-'.$order->id, $order->external_checkout_id);
        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'provider' => 'paypal',
            'provider_session_id' => 'fake-paypal-order-'.$order->id,
            'status' => 'redirected',
        ]);
    }

    public function test_paypal_success_captures_the_order_and_reuses_the_existing_fulfillment_flow(): void
    {
        $this->useFakePayPalGateway();
        $user = User::factory()->create([
            'email' => 'paypal-success@example.com',
        ]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'PayPal Success Trader',
            'email' => 'paypal-success-billing@example.com',
            'street_address' => '11 PayPal Road',
            'city' => 'Dublin',
            'postal_code' => 'D02',
            'country' => 'IE',
            'challenge_type' => 'one_step',
            'account_size' => 5000,
            'currency' => 'USD',
            'payment_provider' => 'paypal',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ]);

        $order = Order::query()->firstOrFail();

        $this->get(route('paypal.success', [
            'order' => $order->order_number,
            'token' => $order->external_checkout_id,
        ]))
            ->assertOk()
            ->assertSee('Challenge order confirmed')
            ->assertSee($order->order_number);

        $order->refresh();

        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame('fake-paypal-capture-'.$order->id, $order->external_payment_id);
        $this->assertDatabaseHas('challenge_purchases', [
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'provider' => 'paypal',
            'provider_payment_id' => 'fake-paypal-capture-'.$order->id,
            'status' => 'completed',
        ]);
    }

    public function test_paypal_cancel_marks_the_order_canceled_and_keeps_retry_available(): void
    {
        $this->useFakePayPalGateway();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'PayPal Retry Trader',
            'email' => 'paypal-retry@example.com',
            'street_address' => '12 Retry Street',
            'city' => 'Rome',
            'postal_code' => '00100',
            'country' => 'IT',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'EUR',
            'payment_provider' => 'paypal',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ]);

        $order = Order::query()->firstOrFail();

        $this->get(route('paypal.cancel', [
            'order' => $order->order_number,
            'token' => $order->external_checkout_id,
        ]))
            ->assertOk()
            ->assertSee('Retry Payment')
            ->assertSee($order->order_number);

        $order->refresh();

        $this->assertSame(Order::PAYMENT_CANCELED, $order->payment_status);
        $this->assertSame(Order::STATUS_CANCELED, $order->order_status);
        $this->assertDatabaseMissing('challenge_purchases', [
            'order_id' => $order->id,
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
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
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
        $this->assertSame(1, TradingAccount::query()->where('order_id', $order->id)->count());
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_dashboard_uses_real_trading_account_metrics_when_available(): void
    {
        $user = User::factory()->create([
            'name' => 'Metrics Trader',
            'email' => 'metrics@example.com',
        ]);

        UserProfile::query()->create([
            'user_id' => $user->id,
            'preferred_language' => 'en',
            'timezone' => 'Europe/Berlin',
        ]);

        $plan = ChallengePlan::query()->create([
            'slug' => 'two-step-50000',
            'name' => '2-Step Pro 50K',
            'account_size' => 50000,
            'currency' => 'USD',
            'entry_fee' => 289,
            'profit_target' => 10,
            'daily_loss_limit' => 5,
            'max_loss_limit' => 10,
            'steps' => 2,
            'profit_share' => 80,
            'first_payout_days' => 21,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
            'is_active' => true,
        ]);

        TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'two_step',
            'account_size' => 50000,
            'account_reference' => 'WFX-CT-50099',
            'platform' => 'cTrader',
            'platform_slug' => 'ctrader',
            'platform_account_id' => 'ct-live-50099',
            'platform_login' => '50099',
            'platform_environment' => 'demo',
            'platform_status' => 'connected',
            'stage' => 'Challenge Step 1',
            'status' => 'Active',
            'account_type' => 'challenge',
            'account_phase' => 'challenge',
            'phase_index' => 1,
            'account_status' => 'active',
            'starting_balance' => 50000,
            'balance' => 52340,
            'equity' => 52010,
            'profit_loss' => 2340,
            'total_profit' => 2340,
            'today_profit' => 440,
            'daily_drawdown' => 210,
            'max_drawdown' => 580,
            'drawdown_percent' => 1.16,
            'profit_target_percent' => 10,
            'profit_target_amount' => 5000,
            'profit_target_progress_percent' => 46.8,
            'daily_drawdown_limit_percent' => 5,
            'daily_drawdown_limit_amount' => 2500,
            'max_drawdown_limit_percent' => 10,
            'max_drawdown_limit_amount' => 5000,
            'profit_split' => 80,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 2,
            'sync_status' => 'success',
            'last_synced_at' => now(),
            'synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('WFX-CT-50099')
            ->assertSee('ct-live-50099')
            ->assertSee('$52,340.00');

        $this->actingAs($user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('WFX-CT-50099')
            ->assertSee('47%');
    }

    public function test_trading_sync_command_marks_account_skipped_when_ctrader_credentials_are_missing(): void
    {
        config()->set('trading.sync.enabled', true);
        config()->set('trading.platforms.ctrader.enabled', true);
        config()->set('trading.platforms.ctrader.use_mock_data', false);
        config()->set('services.ctrader.client_id', null);
        config()->set('services.ctrader.client_secret', null);

        $account = TradingAccount::query()->create([
            'user_id' => User::factory()->create()->id,
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'account_reference' => 'WFX-CT-SKIP01',
            'platform' => 'cTrader',
            'platform_slug' => 'ctrader',
            'stage' => 'Challenge Step 1',
            'status' => 'Pending Activation',
            'account_type' => 'challenge',
            'account_phase' => 'challenge',
            'phase_index' => 1,
            'account_status' => 'pending_activation',
            'starting_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
        ]);

        $this->artisan('trading:sync-accounts', [
            '--account' => $account->id,
        ])->assertExitCode(0);

        $this->assertSame('skipped', $account->fresh()->sync_status);
        $this->assertStringContainsString('credentials', (string) $account->fresh()->sync_error);
    }

    public function test_mock_ctrader_sync_updates_metrics_and_creates_snapshot(): void
    {
        config()->set('trading.sync.enabled', true);
        config()->set('trading.platforms.ctrader.enabled', true);
        config()->set('trading.platforms.ctrader.use_mock_data', true);

        $account = TradingAccount::query()->create([
            'user_id' => User::factory()->create()->id,
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'account_reference' => 'WFX-CT-MOCK01',
            'platform' => 'cTrader',
            'platform_slug' => 'ctrader',
            'stage' => 'Challenge Step 1',
            'status' => 'Pending Activation',
            'account_type' => 'challenge',
            'account_phase' => 'challenge',
            'phase_index' => 1,
            'account_status' => 'pending_activation',
            'starting_balance' => 25000,
            'balance' => 25000,
            'equity' => 25000,
            'profit_target_percent' => 10,
            'profit_target_amount' => 2500,
            'daily_drawdown_limit_percent' => 5,
            'daily_drawdown_limit_amount' => 1250,
            'max_drawdown_limit_percent' => 10,
            'max_drawdown_limit_amount' => 2500,
            'minimum_trading_days' => 3,
        ]);

        $this->artisan('trading:sync-accounts', [
            '--account' => $account->id,
        ])->assertExitCode(0);

        $account->refresh();

        $this->assertSame('success', $account->sync_status);
        $this->assertNotNull($account->last_synced_at);
        $this->assertNotNull($account->activated_at);
        $this->assertDatabaseHas('trading_account_balance_snapshots', [
            'trading_account_id' => $account->id,
        ]);
    }

    public function test_payout_policy_contains_the_updated_cycle_wording(): void
    {
        $this->get(route('payout-policy'))
            ->assertOk()
            ->assertSee('First withdrawal requests become available after 21 days.')
            ->assertSee('Payments within 24 hours');
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
            'plan_type' => '1-Step Instant',
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
            ->assertSee('1-Step Instant / 25K')
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

    public function test_existing_user_can_submit_the_trial_form_and_enter_the_free_demo_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'existing-demo@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $response = $this->post(route('trial.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response
            ->assertRedirect(route('trial.dashboard'))
            ->assertSessionHas('trial_user_id', $user->id);

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('trading_accounts', [
            'user_id' => $user->id,
            'is_trial' => true,
            'trial_status' => 'active',
        ]);
    }

    public function test_authenticated_user_can_start_a_trial_from_the_trial_page(): void
    {
        $user = User::factory()->create([
            'email' => 'existing-trial-user@example.com',
            'password' => 'password123',
        ]);

        $this->actingAs($user)
            ->get(route('trial.register'))
            ->assertRedirect(route('trial.dashboard'))
            ->assertSessionHas('trial_user_id', $user->id);

        $trialAccount = TradingAccount::query()
            ->where('user_id', $user->id)
            ->where('is_trial', true)
            ->first();

        $this->assertNotNull($trialAccount);
        $this->assertSame('active', $trialAccount->trial_status);
    }

    public function test_existing_user_login_from_trial_page_returns_to_trial_access(): void
    {
        $user = User::factory()->create([
            'email' => 'trial-login@example.com',
            'password' => 'password123',
        ]);

        $this->get(route('trial.register'))->assertOk();

        $this->post(route('login.store'), [
            'login_email' => $user->email,
            'login_password' => 'password123',
        ])->assertRedirect(route('trial.register'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('trial.register'))
            ->assertRedirect(route('trial.dashboard'))
            ->assertSessionHas('trial_user_id', $user->id);

        $this->assertDatabaseHas('trading_accounts', [
            'user_id' => $user->id,
            'is_trial' => true,
            'trial_status' => 'active',
        ]);
    }

    public function test_authenticated_user_with_ended_trial_is_sent_to_the_trial_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'ended-trial-user@example.com',
            'password' => 'password123',
        ]);

        TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-TRIAL-ENDED01',
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
            ->get(route('trial.register'))
            ->assertRedirect(route('trial.dashboard'))
            ->assertSessionHas('trial_user_id', $user->id);

        $this->actingAs($user)
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('Your trial has ended.');

        $this->assertCount(1, TradingAccount::query()->where('user_id', $user->id)->where('is_trial', true)->get());
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

    private function useFakePayPalGateway(): void
    {
        config()->set('wolforix.payments.providers.paypal.class', FakePayPalPaymentGateway::class);
        config()->set('wolforix.payments.providers.paypal.enabled', true);
        config()->set('wolforix.payments.providers.paypal.coming_soon', false);
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

    /**
     * @param  array<string, mixed>  $claims
     */
    private function fakeIdToken(array $claims): string
    {
        $header = [
            'alg' => 'none',
            'typ' => 'JWT',
        ];

        return implode('.', [
            rtrim(strtr(base64_encode(json_encode($header, JSON_THROW_ON_ERROR)), '+/', '-_'), '='),
            rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '='),
            'signature',
        ]);
    }
}
