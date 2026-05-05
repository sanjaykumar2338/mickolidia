<?php

namespace Tests\Feature;

use App\Mail\ChallengeAccountDetailsMail;
use App\Mail\ChallengePurchaseConfirmationMail;
use App\Mail\ChallengePurchaseSupportNotificationMail;
use App\Mail\TrialAccountInstructionsMail;
use App\Mail\TrialBreachedMail;
use App\Mail\TrialPassedMail;
use App\Mail\TrustpilotReviewRequestMail;
use App\Mail\WelcomeMail;
use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Invoice;
use App\Models\Mt5AccountPoolEntry;
use App\Models\Mt5PromoCode;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\WolforixResetPasswordNotification;
use App\Services\Challenge\ChallengeLifecycleMailer;
use App\Services\Pricing\ChallengePricingService;
use App\Support\PublicContentIndex;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Fixtures\FakePayPalPaymentGateway;
use Tests\Fixtures\FakeStripePaymentGateway;
use Tests\TestCase;

class WolforixPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('public');
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

    public function test_homepage_social_share_meta_uses_wolforix_platform_copy(): void
    {
        $description = 'Wolforix is a modern prop trading platform built for disciplined traders. Access evaluation accounts, track performance, manage payouts, and trade with clear rules, secure infrastructure, and scalable capital opportunities.';

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Wolforix Prop Firm Platform</title>', false)
            ->assertSee('<meta name="description" content="'.$description.'">', false)
            ->assertSee('<meta property="og:title" content="Wolforix Prop Firm Platform">', false)
            ->assertSee('<meta property="og:description" content="'.$description.'">', false)
            ->assertSee('<meta property="og:image" content="'.asset('trading123.png').'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="twitter:title" content="Wolforix Prop Firm Platform">', false)
            ->assertSee('<meta name="twitter:description" content="'.$description.'">', false)
            ->assertSee('<meta name="twitter:image" content="'.asset('trading123.png').'">', false)
            ->assertDontSee('Milestone 1 foundation for the Wolforix prop firm platform');
    }

    public function test_homepage_uses_static_hero_image_and_locale_platform_banner_below_plans(): void
    {
        foreach ([
            'en' => 'desktop-view/95D6764F-5789-4965-A3F2-8F32B5A32B62-english.png',
            'de' => 'desktop-view/AA9B97AE-643E-4443-88EA-6D0128E18ACB-german.png',
            'es' => 'desktop-view/B27C71CD-AEFA-47A7-9692-97F9FA2D5067-spanish.png',
            'fr' => 'desktop-view/95D6764F-5789-4965-A3F2-8F32B5A32B62-english.png',
        ] as $locale => $imagePath) {
            $response = $this->withSession(['locale' => $locale])
                ->get(route('home'))
                ->assertOk()
                ->assertSee(asset('trading123.png'), false)
                ->assertSee(asset($imagePath), false)
                ->assertSee('alt="Wolforix trading dashboard"', false);

            $content = $response->getContent();
            $heroImagePosition = strpos($content, asset('trading123.png'));
            $plansPosition = strpos($content, 'id="plans"');
            $localizedImagePosition = strpos($content, asset($imagePath));

            $this->assertNotFalse($heroImagePosition);
            $this->assertNotFalse($plansPosition);
            $this->assertNotFalse($localizedImagePosition);
            $this->assertLessThan($plansPosition, $heroImagePosition);
            $this->assertGreaterThan($plansPosition, $localizedImagePosition);
        }
    }

    public function test_homepage_security_trust_copy_is_localized(): void
    {
        $this->withSession(['locale' => 'es'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Seguridad visible desde el primer momento')
            ->assertSee('Wolforix refuerza la confianza con una infraestructura robusta, controles de riesgo avanzados, monitorización continua y un proceso activo de alineación con la norma ISO/IEC 27001.')
            ->assertSee('Hosting protegido y accesos operativos estrictamente controlados en los sistemas centrales.')
            ->assertSee('El proceso de implementación está en curso y forma parte de nuestro compromiso con los estándares internacionales de seguridad.');

        foreach (array_keys(config('wolforix.supported_locales')) as $locale) {
            $trust = trans('site.home.trust', [], $locale);

            $this->assertIsArray($trust);
            $this->assertNotEmpty($trust['title'] ?? null);
            $this->assertNotEmpty($trust['description'] ?? null);
            $this->assertCount(4, $trust['items'] ?? []);
            $this->assertStringContainsString('ISO/IEC 27001', json_encode($trust, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
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

    public function test_public_footer_renders_clean_social_links(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-footer-brand', false)
            ->assertSee('https://www.facebook.com/share/1JQhTJwzJq/', false)
            ->assertSee('https://www.instagram.com/p/DXvmljMjOHS/?igsh=MXhvN3J2MTFjeTlkdA', false)
            ->assertSee('https://t.me/wolforix', false)
            ->assertSee('https://x.com/wolforixhq', false)
            ->assertSee('https://youtube.com/@wolforix', false)
            ->assertDontSee('mibextid', false)
            ->assertDontSee('v3aluoTJ6BAc1gh7kuZvMg', false);
    }

    public function test_contact_page_contains_support_channels_and_voice_assistant(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Contact Us')
            ->assertSee(config('wolforix.support.email'))
            ->assertSee('Live chat')
            ->assertSee('Talk with Wolfi')
            ->assertSee('Your trading assistant is ready to help.')
            ->assertSee('Tap to talk')
            ->assertSee('new-wolfy.webp', false)
            ->assertSee('data-wolfi-live-scene', false)
            ->assertSee('data-wolfi-talk-control', false)
            ->assertSee('Wolfi\'s answer')
            ->assertSee('Play answer')
            ->assertSee('Suggested prompts')
            ->assertSee('Can I trade during news?')
            ->assertSee('How often are payouts processed?')
            ->assertDontSee('What happens if I hit '.'max drawdown?')
            ->assertSee('Let me make sure I answer the right thing.')
            ->assertSee('"locale":"en"', false)
            ->assertDontSee('"locale":"de"', false)
            ->assertDontSee('"locale":"es"', false)
            ->assertDontSee('"locale":"fr"', false)
            ->assertDontSee('"locale":"hi"', false)
            ->assertDontSee('"locale":"it"', false)
            ->assertDontSee('"locale":"pt"', false)
            ->assertSee(route('faq'), false);
    }

    public function test_german_contact_page_uses_the_refined_wolfi_copy(): void
    {
        $this->withSession([
            'locale' => 'de',
        ])->get(route('contact'))
            ->assertOk()
            ->assertSee('Support, Live-Chat und FAQ-Sprachhilfe an einem Ort.')
            ->assertSee('"locale":"de"', false)
            ->assertDontSee('"locale":"en"', false)
            ->assertSee('Sprich mit Wolfi')
            ->assertSee('Wolfis Antwort')
            ->assertSee('Ich höre zu... tippe erneut zum Stoppen.')
            ->assertSee('Ich will sicherstellen, dass ich die richtige Frage beantworte.')
            ->assertSee('Starte mit einer kurzen Frage, und Wolfi führt dich von dort aus weiter.');
    }

    public function test_french_home_page_uses_wolfi_branding_in_the_assistant_promo(): void
    {
        $this->withSession([
            'locale' => 'fr',
        ])->get(route('home'))
            ->assertOk()
            ->assertSee('Assistant Wolfi')
            ->assertSee('Parlez avec Wolfi')
            ->assertSee('WOLFI')
            ->assertSee('Disponible 24/7')
            ->assertDontSee('Parlez à votre Assistant IA');
    }

    public function test_spanish_home_page_uses_wolfi_branding_in_the_assistant_promo(): void
    {
        $this->withSession([
            'locale' => 'es',
        ])->get(route('home'))
            ->assertOk()
            ->assertSee('Asistente Wolfi')
            ->assertSee('Habla con Wolfi')
            ->assertSee('WOLFI')
            ->assertSee('Disponible 24/7')
            ->assertDontSee('Habla con tu Asistente IA');
    }

    public function test_hindi_italian_and_portuguese_locales_render_core_homepage_copy(): void
    {
        foreach ([
            'hi' => ['मुफ़्त ट्रायल', 'मुद्रा', 'Wolfi से बात करें'],
            'it' => ['Prova Gratuita', 'Valuta', 'Parla con Wolfi'],
            'pt' => ['Teste Gratuito', 'Moeda', 'Fale com Wolfi'],
        ] as $locale => $expectations) {
            $response = $this->withSession(['locale' => $locale])
                ->get(route('home'));

            $response->assertOk();

            foreach ($expectations as $expectation) {
                $response->assertSee($expectation);
            }
        }
    }

    public function test_hindi_italian_and_portuguese_login_pages_use_localized_email_placeholders(): void
    {
        foreach ([
            'hi' => 'trader@example.in',
            'it' => 'trader@esempio.it',
            'pt' => 'trader@exemplo.pt',
        ] as $locale => $placeholder) {
            $this->withSession(['locale' => $locale])
                ->get(route('login'))
                ->assertOk()
                ->assertSee($placeholder);
        }
    }

    public function test_hindi_italian_and_portuguese_dashboard_accounts_pages_render_localized_labels(): void
    {
        $translator = app('translator');

        foreach (['hi', 'it', 'pt'] as $locale) {
            $user = User::factory()->create([
                'email' => "dashboard-{$locale}@example.com",
            ]);

            UserProfile::query()->create([
                'user_id' => $user->id,
                'preferred_language' => $locale,
                'timezone' => 'Europe/Berlin',
            ]);

            TradingAccount::query()->create([
                'user_id' => $user->id,
                'challenge_type' => 'two_step',
                'account_size' => 50000,
                'account_reference' => "WFX-{$locale}-50099",
                'platform' => 'MT5',
                'platform_slug' => 'mt5',
                'platform_account_id' => "mt5-{$locale}-50099",
                'platform_login' => "500{$locale}",
                'platform_environment' => 'demo',
                'platform_status' => 'connected',
                'stage' => 'Challenge Step 1',
                'status' => 'Active',
                'account_type' => 'challenge',
                'account_phase' => 'challenge',
                'phase_index' => 1,
                'account_status' => 'active',
                'challenge_status' => 'active',
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

            $response = $this->withSession(['locale' => $locale])
                ->actingAs($user)
                ->get(route('dashboard.accounts'));

            $response->assertOk()
                ->assertSee($translator->get('site.dashboard.accounts_page.title', [], $locale))
                ->assertSee($translator->get('Challenge progress', [], $locale))
                ->assertSee($translator->get('Sync details', [], $locale))
                ->assertDontSee('Challenge progress')
                ->assertDontSee('Sync details');
        }
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
            ->assertSee('data-password-reset-link', false)
            ->assertSee(route('password.request'), false);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])
            ->assertRedirect()
            ->assertSessionHas('status', __('site.auth.passwords.status.sent'));

        $resetToken = null;

        Notification::assertSentTo($user, WolforixResetPasswordNotification::class, function (WolforixResetPasswordNotification $notification) use (&$resetToken): bool {
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
            ->assertSee('Secure Infrastructure')
            ->assertSee('24H Trading Rewards')
            ->assertSee('Market Pulse (Live News)')
            ->assertSee('AI Assistant Wolfi')
            ->assertSee('Wolfi Assistant')
            ->assertSee('Talk with Wolfi')
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
            ->assertSee('View Security')
            ->assertSee('Global reach')
            ->assertSee('Powering traders across')
            ->assertSee('One standard.')
            ->assertSee('Wolforix connects traders worldwide under one unified infrastructure: fast, precise, and built for performance.')
            ->assertSee('Market Pulse')
            ->assertSee('Real-time insights to help you trade smarter and react faster.')
            ->assertSee('Open live market news')
            ->assertSee('View full calendar')
            ->assertSee('Economic News Calendar')
            ->assertSee('WOLFI')
            ->assertSee('Always on. Always ready.')
            ->assertSee('Let Wolfi guide you.')
            ->assertSee('24/7 available')
            ->assertSee('new-wolfy.webp', false)
            ->assertSee('assistant-home-visual-image', false)
            ->assertSee('challenge-desktop-comparison', false)
            ->assertSee('Best Value')
            ->assertSee('challenge-comparison-best-value', false)
            ->assertSee('data-challenge-size="50000"', false)
            ->assertSee('hidden challenge-comparison-best-value', false)
            ->assertSee('challenge-comparison-price-current', false)
            ->assertSee('challenge-comparison-price-original', false)
            ->assertDontSee('challenge-plans/desktop', false)
            ->assertSee('Talk with Wolfi')
            ->assertSee('Open Wolfi')
            ->assertSee('Can I trade during news?')
            ->assertSee('How often are payouts processed?')
            ->assertDontSee('What happens if I hit '.'max drawdown?')
            ->assertSee('Wolfi Assistant')
            ->assertSee('We use cookies to improve your experience and support essential site functionality.')
            ->assertSee('Learn More')
            ->assertSee('Accept')
            ->assertSee('Free Trial')
            ->assertSee('No risk. No credit card.')
            ->assertSee('Single Phase')
            ->assertSee('Funded Account')
            ->assertSee('Trade 1,000+ Instruments on MT5')
            ->assertSee('All instruments available in your MT5 account are fully supported and automatically tracked.')
            ->assertSee('Platform:')
            ->assertSee('MT5')
            ->assertSee('Launch Discount - Limited Time Only')
            ->assertSee('20% OFF - Launch Access Ending Soon')
            ->assertSee(config('wolforix.launch_discount.code'))
            ->assertSee('Start Challenge')
            ->assertSee('Get Plan')
            ->assertSee('Get Discount')
            ->assertSee('Ignore')
            ->assertSee('$49')
            ->assertSee('Payout Policy')
            ->assertSee('Dismiss notice')
            ->assertSee('Login')
            ->assertSee('NEWS')
            ->assertSee('About')
            ->assertSee('Contact Us')
            ->assertSee('Search the site')
            ->assertSee('View Security')
            ->assertSee('Visa')
            ->assertSee('Apple Pay')
            ->assertSee('Google Pay')
            ->assertSee('Wolforix Community Access')
            ->assertSeeInOrder([
                'Legal & Policies',
                'View full legal information',
            ])
            ->assertSee('View full legal information')
            ->assertDontSee('Open main navigation')
            ->assertSee('https://youtube.com/@wolforix?si=NtJ-jmS20024s7m3', false)
            ->assertSee('https://www.instagram.com/p/DXvmljMjOHS/?igsh=MXhvN3J2MTFjeTlkdA', false)
            ->assertSee('https://t.me/wolforix', false)
            ->assertSee('https://chat.whatsapp.com/KSvvnEQFDUgKDM8EQXNWIT?mode=gi_t', false)
            ->assertSee('Back to top')
            ->assertSee('Company Number: 17111904')
            ->assertSee('By using this website, you agree to our Terms and Conditions, Privacy Policy, Payout Policy, Refund Policy, and all related legal documents.')
            ->assertSee(asset('trading123.png'), false)
            ->assertSee(asset('newfolder/mobile1.webp'), false)
            ->assertSee(asset('IMG_9315.webp'), false)
            ->assertDontSee('Milestone 1 scope')
            ->assertDontSee('What this foundation already covers')
            ->assertDontSee('Secure checkout')
            ->assertDontSee('Dashboard Preview')
            ->assertDontSee('Our mission')
            ->assertDontSee('Identify, train, and fund traders who are ready to perform.');
    }

    public function test_only_the_50k_homepage_plan_is_marked_best_value(): void
    {
        $content = $this->get(route('home'))
            ->assertOk()
            ->content();

        $this->assertSame(1, substr_count($content, 'class="challenge-comparison-best-value"'));
        $this->assertSame(4, substr_count($content, 'class="hidden challenge-comparison-best-value"'));
        $this->assertStringContainsString('data-challenge-size="50000"', $content);
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

    public function test_launch_offer_ignore_can_be_saved_without_redirect_for_popup_close(): void
    {
        $this->postJson(route('launch-offer.update'), [
            'decision' => 'ignore',
            'redirect_to' => route('home'),
        ])
            ->assertOk()
            ->assertJson([
                'decision' => 'ignore',
                'applied' => false,
                'promo_code' => null,
                'redirect_to' => route('home'),
            ])
            ->assertSessionHas('launch_offer', function (array $launchOffer): bool {
                return ($launchOffer['decision'] ?? null) === 'ignore'
                    && ($launchOffer['applied'] ?? null) === false
                    && array_key_exists('promo_code', $launchOffer)
                    && $launchOffer['promo_code'] === null;
            });
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
            ->assertSee('€ · EUR')
            ->assertSee('£ · GBP')
            ->assertDontSee('Dólar estadounidense')
            ->assertDontSee('Libra esterlina');
    }

    public function test_supported_locales_use_standardized_currency_codes_on_the_homepage(): void
    {
        foreach (['en', 'de', 'es', 'fr', 'hi', 'it', 'pt'] as $locale) {
            $this->withSession(['locale' => $locale])
                ->get(route('home'))
                ->assertOk()
                ->assertSee('$ · USD')
                ->assertSee('€ · EUR')
                ->assertSee('£ · GBP');
        }
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

    public function test_country_eligibility_includes_paraguay_and_excludes_faq_restricted_countries(): void
    {
        $countries = config('wolforix.countries');
        $checkoutCountries = config('wolforix.checkout_countries');
        $restrictedCountries = config('wolforix.restricted_countries');

        $this->assertSame('Paraguay', $countries['PY'] ?? null);
        $this->assertSame('Paraguay', $checkoutCountries['PY'] ?? null);
        $this->assertContains('PY', array_keys($checkoutCountries));

        sort($restrictedCountries);

        $this->assertSame(['CU', 'IR', 'KP', 'RU', 'SD', 'SY', 'VE'], $restrictedCountries);

        foreach ($restrictedCountries as $countryCode) {
            $this->assertArrayHasKey($countryCode, $countries);
            $this->assertArrayNotHasKey($countryCode, $checkoutCountries);
        }

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('The currently restricted countries are Iran, North Korea, Syria, Sudan, Cuba, Russia, and Venezuela.')
            ->assertDontSee('Paraguay');
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
            ->assertSee('Choose your payment method')
            ->assertSee('Pay with Card')
            ->assertSee('Pay with PayPal')
            ->assertSee('Stripe')
            ->assertSee('PayPal')
            ->assertSee('EUR')
            ->assertSee('value="PY"', false)
            ->assertSee('Paraguay')
            ->assertDontSee('value="VE"', false)
            ->assertSee('wolfy-mobile.webp', false)
            ->assertSee('assistant-mascot-visual-checkout', false)
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

    public function test_checkout_giveaway_promo_preview_returns_zero_payment_state(): void
    {
        $user = User::factory()->create();
        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335374');

        $this->actingAs($user)
            ->postJson(route('checkout.promo.preview'), [
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ])
            ->assertOk()
            ->assertJson([
                'applied' => true,
                'promo_code' => $promoCode->code,
                'message' => 'Promo code applied. No payment is required for this giveaway account.',
                'payment_required' => false,
                'checkout_mode' => 'giveaway',
                'pricing' => [
                    'discount_enabled' => true,
                    'discounted_price' => '0.00',
                    'currency' => 'USD',
                ],
            ]);
    }

    public function test_checkout_giveaway_promo_apply_and_reload_use_the_same_validation(): void
    {
        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335400', [
            'login' => '335400',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('checkout.promo.preview'), [
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ])
            ->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('payment_required', false)
            ->assertJsonPath('checkout_mode', 'giveaway');

        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ]))
            ->assertOk()
            ->assertSee($promoCode->code)
            ->assertSee('Promo code applied. No payment is required for this giveaway account.')
            ->assertSee('data-payment-required="false"', false)
            ->assertDontSee('data-feedback-state="error"', false);
    }

    public function test_checkout_giveaway_promo_wrong_account_size_returns_specific_error_and_redirect_target(): void
    {
        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335400', [
            'login' => '335400',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('checkout.promo.preview'), [
                'challenge_type' => 'two_step',
                'account_size' => 100000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ])
            ->assertOk()
            ->assertJsonPath('applied', false)
            ->assertJsonPath('message', 'This promo code is only valid for the $10K 2-step evaluation account.')
            ->assertJsonPath('selection.challenge_type', 'two_step')
            ->assertJsonPath('selection.account_size', 10000)
            ->assertJsonPath('selection.promo_code', $promoCode->code)
            ->assertJsonPath('redirect_url', route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ]));
    }

    public function test_checkout_giveaway_promo_url_with_wrong_account_size_redirects_to_correct_plan(): void
    {
        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335400', [
            'login' => '335400',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 100000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ]))
            ->assertRedirect(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ]));
    }

    public function test_checkout_giveaway_promo_page_hides_payment_options_when_valid(): void
    {
        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335374');

        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', [
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'promo_code' => $promoCode->code,
            ]))
            ->assertOk()
            ->assertSee('Promo code applied. No payment is required for this giveaway account.')
            ->assertSee('data-payment-required="false"', false)
            ->assertSee('data-checkout-payment-section', false)
            ->assertSee('hidden rounded-[1.8rem] border border-white/8 bg-white/3 p-5', false)
            ->assertSee('Complete Free Checkout');
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
                'message' => 'Invalid or expired promo code.',
                'pricing' => [
                    'discount_enabled' => false,
                    'discounted_price' => '289.00',
                    'list_price' => '289.00',
                    'currency' => 'USD',
                ],
            ]);
    }

    public function test_checkout_giveaway_promo_code_normalization_works(): void
    {
        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335400', [
            'login' => '335400',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('checkout.promo.preview'), [
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'promo_code' => '  wfxgive 335400  ',
            ])
            ->assertOk()
            ->assertJson([
                'applied' => true,
                'promo_code' => $promoCode->code,
                'payment_required' => false,
                'checkout_mode' => 'giveaway',
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

    public function test_checkout_rejects_faq_restricted_country_before_order_creation(): void
    {
        $this->useFakeStripeGateway();

        $this->actingAs(User::factory()->create())
            ->post(route('checkout.store'), [
                'full_name' => 'Restricted Trader',
                'email' => 'restricted@example.com',
                'street_address' => '1 Blocked Street',
                'city' => 'Caracas',
                'postal_code' => '1010',
                'country' => 'VE',
                'challenge_type' => 'two_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'payment_provider' => 'stripe',
                'accept_terms_and_residency' => '1',
                'accept_refund_policy' => '1',
            ])
            ->assertSessionHasErrors(['country']);

        $this->assertSame(0, Order::query()->count());
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

    public function test_checkout_accepts_paraguay_and_stores_profile_country_after_fulfillment(): void
    {
        $this->useFakeStripeGateway();
        Mail::fake();
        Storage::fake('public');

        $user = User::factory()->create([
            'email' => 'paraguay@example.com',
        ]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'Paraguay Trader',
            'email' => 'paraguay-billing@example.com',
            'street_address' => '1 Asuncion Street',
            'city' => 'Asuncion',
            'postal_code' => '1209',
            'country' => 'py',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ]);

        $order = Order::query()->firstOrFail();

        $this->assertSame('PY', $order->country);
        $this->assertSame('PY', $order->metadata['checkout_confirmations']['terms_and_residency']['country']);

        $this->actingAs($user)
            ->get(route('checkout.success', ['session_id' => $order->external_checkout_id]))
            ->assertOk()
            ->assertSee('Challenge order confirmed');

        $profile = $user->profile()->firstOrFail();

        $this->assertSame('Paraguay', $profile->country);
        $this->assertDatabaseHas('challenge_purchases', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'challenge_type' => 'two_step',
            'account_size' => 10000,
        ]);
    }

    public function test_checkout_success_marks_order_paid_and_creates_purchase_for_the_authenticated_user(): void
    {
        $this->useFakeStripeGateway();
        Mail::fake();
        Storage::fake('public');

        $user = User::factory()->create([
            'email' => 'account-owner@example.com',
        ]);

        $poolEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335411',
            'password' => 'fusion-trading-pass',
            'investor_password' => 'fusion-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 25000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_available' => true,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
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
            'platform_slug' => 'mt5',
            'account_status' => 'pending_activation',
        ]);

        $account = TradingAccount::query()->where('order_id', $order->id)->firstOrFail();
        $poolEntry->refresh();
        $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertMatchesRegularExpression('/^WF-\d{6}$/', $invoice->invoice_number);
        $this->assertSame($user->id, $invoice->user_id);
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->pdf_generated_at);
        Storage::disk('public')->assertExists((string) $invoice->pdf_path);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('public')->get((string) $invoice->pdf_path));
        $this->assertSame('335411', $account->platform_login);
        $this->assertSame('FusionMarkets-Demo', data_get($account->meta, 'credentials.server'));
        $this->assertSame('fusion-trading-pass', data_get($account->meta, 'credentials.password'));
        $this->assertSame('fusion-investor-pass', data_get($account->meta, 'credentials.investor_password'));
        $this->assertSame($account->id, $poolEntry->allocated_trading_account_id);
        $this->assertFalse((bool) $poolEntry->is_available);
        $this->assertNotNull($account->challenge_purchase_email_sent_at);
        $this->assertNotNull(data_get($order->fresh()->metadata, 'emails.support_purchase_notification_sent_at'));
        Mail::assertSent(ChallengePurchaseConfirmationMail::class, 1);
        Mail::assertSent(ChallengeAccountDetailsMail::class, 1);
        Mail::assertSent(ChallengePurchaseSupportNotificationMail::class, 1);
        Mail::assertSent(ChallengePurchaseSupportNotificationMail::class, function (ChallengePurchaseSupportNotificationMail $mail) use ($account): bool {
            return $mail->hasTo((string) config('wolforix.support.email'))
                && $mail->details['client_name'] === 'Paid Trader'
                && $mail->details['client_email'] === 'billing-paid@example.com'
                && $mail->details['account_size'] === '$25,000.00'
                && $mail->details['mt5_login'] === '335411'
                && $mail->details['mt5_server'] === 'FusionMarkets-Demo'
                && $mail->details['broker'] === 'FusionMarkets'
                && $mail->details['account_reference'] === $account->account_reference
                && $mail->details['remaining_same_size'] === '0'
                && $mail->details['remaining_total'] === '0';
        });

        $purchaseMail = new ChallengePurchaseConfirmationMail($order->fresh(['challengePurchase.tradingAccounts']) ?? $order);

        $this->assertSame($account->account_reference, $purchaseMail->accountReference);
        $this->assertIsArray($purchaseMail->accountAccessDetails);
        $this->assertSame('MT5', $purchaseMail->accountAccessDetails['platform']);
        $this->assertSame('FusionMarkets', $purchaseMail->accountAccessDetails['broker']);
        $this->assertSame('335411', $purchaseMail->accountAccessDetails['login_id']);
        $this->assertSame('FusionMarkets-Demo', $purchaseMail->accountAccessDetails['server']);
        $this->assertSame('fusion-trading-pass', $purchaseMail->accountAccessDetails['password']);
        $this->assertSame('fusion-investor-pass', $purchaseMail->accountAccessDetails['investor_password']);

        $this->actingAs($user)
            ->get(route('dashboard.accounts'))
            ->assertOk()
            ->assertSee('Download Invoice')
            ->assertSee($invoice->invoice_number);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Invoice ready')
            ->assertSee('335411')
            ->assertSee('FusionMarkets-Demo')
            ->assertSee('fusion-trading-pass')
            ->assertSee('fusion-investor-pass')
            ->assertDontSee('Secure disclosure not enabled')
            ->assertSee('Download Invoice')
            ->assertSee($invoice->invoice_number);

        $this->actingAs($user)
            ->get(route('dashboard.invoices.download', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('checkout.success', ['session_id' => $order->external_checkout_id]))
            ->assertOk();

        $this->assertSame(1, Invoice::query()->where('order_id', $order->id)->count());
        $this->assertSame($invoice->pdf_path, $invoice->fresh()->pdf_path);
        Mail::assertSent(ChallengePurchaseConfirmationMail::class, 1);
        Mail::assertSent(ChallengeAccountDetailsMail::class, 1);
        Mail::assertSent(ChallengePurchaseSupportNotificationMail::class, 1);
    }

    public function test_checkout_success_skips_mt5_pool_entries_with_invalid_encrypted_credentials(): void
    {
        $this->useFakeStripeGateway();
        Mail::fake();
        Storage::fake('public');

        $user = User::factory()->create([
            'email' => 'account-owner@example.com',
        ]);

        $badPoolEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335410',
            'password' => 'bad-trading-pass',
            'investor_password' => 'bad-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 25000,
            'source_created_at' => now()->subDays(2),
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_available' => true,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        DB::table('mt5_account_pool_entries')
            ->where('id', $badPoolEntry->id)
            ->update([
                'password' => 'not-a-valid-laravel-encrypted-value',
            ]);

        $goodPoolEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335411',
            'password' => 'fusion-trading-pass',
            'investor_password' => 'fusion-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 25000,
            'source_created_at' => now()->subDay(),
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_available' => true,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
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
            ->assertSee('Challenge order confirmed');

        $account = TradingAccount::query()->where('order_id', $order->id)->firstOrFail();
        $badPoolEntry->refresh();
        $goodPoolEntry->refresh();

        $this->assertSame('335411', $account->platform_login);
        $this->assertSame('fusion-trading-pass', data_get($account->meta, 'credentials.password'));
        $this->assertNull($badPoolEntry->allocated_trading_account_id);
        $this->assertTrue((bool) $badPoolEntry->is_available);
        $this->assertSame($account->id, $goodPoolEntry->allocated_trading_account_id);
        $this->assertFalse((bool) $goodPoolEntry->is_available);
    }

    public function test_checkout_success_still_completes_when_post_payment_email_delivery_fails(): void
    {
        $this->useFakeStripeGateway();
        Storage::fake('public');

        $this->mock(ChallengeLifecycleMailer::class, function ($mock): void {
            $mock->shouldReceive('sendPurchaseConfirmationIfNeeded')
                ->once()
                ->andThrow(new RuntimeException('SMTP unavailable'));
            $mock->shouldReceive('sendPurchaseCredentialsIfNeeded')
                ->once()
                ->andThrow(new RuntimeException('SMTP unavailable'));
            $mock->shouldReceive('sendPurchaseSupportNotificationIfNeeded')
                ->once()
                ->andThrow(new RuntimeException('SMTP unavailable'));
        });

        $user = User::factory()->create([
            'email' => 'account-owner@example.com',
        ]);

        $poolEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335411',
            'password' => 'fusion-trading-pass',
            'investor_password' => 'fusion-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 25000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_available' => true,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
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
            ->assertSee('Challenge order confirmed');

        $order->refresh();
        $account = TradingAccount::query()->where('order_id', $order->id)->firstOrFail();
        $poolEntry->refresh();

        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame('335411', $account->platform_login);
        $this->assertSame($account->id, $poolEntry->allocated_trading_account_id);
        $this->assertSame(1, Invoice::query()->where('order_id', $order->id)->count());
    }

    public function test_giveaway_promo_code_immediately_assigns_linked_promo_mt5_account_once(): void
    {
        $this->useFakeStripeGateway();
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'giveaway-owner@example.com',
        ]);

        $promoEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335374',
            'password' => 'promo-trading-pass',
            'investor_password' => 'promo-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_promo' => true,
            'is_available' => false,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'promo_marker' => 'Promo',
            ],
        ]);

        $normalEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335411',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_promo' => false,
            'is_available' => true,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        $promoCode = Mt5PromoCode::query()->create([
            'code' => 'WFXGIVE-335374',
            'mt5_account_pool_entry_id' => $promoEntry->id,
            'mt5_login' => '335374',
        ]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'Giveaway Trader',
            'email' => 'giveaway-billing@example.com',
            'street_address' => '10 Promo Street',
            'city' => 'Asuncion',
            'postal_code' => '1209',
            'country' => 'PY',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'promo_code' => 'WFXGIVE-335374',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ])->assertRedirect(route('dashboard.accounts'));

        $order = Order::query()->firstOrFail();
        $account = TradingAccount::query()->where('order_id', $order->id)->firstOrFail();
        $promoCode->refresh();
        $promoEntry->refresh();
        $normalEntry->refresh();

        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame('promo', $order->payment_provider);
        $this->assertSame(0, FakeStripePaymentGateway::$checkoutSessionsCreated);
        $this->assertSame('PY', $order->country);
        $this->assertSame('0.00', (string) $order->final_price);
        $this->assertSame('335374', $account->platform_login);
        $this->assertSame('promo-trading-pass', data_get($account->meta, 'credentials.password'));
        $this->assertNotNull($promoCode->used_at);
        $this->assertSame($user->id, $promoCode->used_by_user_id);
        $this->assertSame($order->id, $promoCode->used_order_id);
        $this->assertSame($account->id, $promoCode->used_trading_account_id);
        $this->assertSame($account->id, $promoEntry->allocated_trading_account_id);
        $this->assertNull($normalEntry->allocated_trading_account_id);
        $this->assertTrue((bool) $normalEntry->is_available);
        $this->assertSame('Paraguay', $user->profile()->firstOrFail()->country);
        Mail::assertSent(ChallengeAccountDetailsMail::class, 1);

        $this->actingAs(User::factory()->create())->post(route('checkout.store'), [
            'full_name' => 'Second Trader',
            'email' => 'second-giveaway@example.com',
            'street_address' => '11 Promo Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'promo_code' => 'WFXGIVE-335374',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ])->assertSessionHasErrors(['promo_code']);

        $this->actingAs(User::factory()->create())->post(route('checkout.store'), [
            'full_name' => 'Second Trader',
            'email' => 'second-giveaway@example.com',
            'street_address' => '11 Promo Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'promo_code' => 'WFXGIVE-335374',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ])->assertSessionHasErrors([
            'promo_code' => 'This promo code has already been used.',
        ]);
    }

    public function test_giveaway_promo_code_claim_accepts_plaintext_imported_mt5_credentials(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'plaintext-promo-owner@example.com',
        ]);

        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335400', [
            'login' => '335400',
        ]);

        DB::table('mt5_account_pool_entries')
            ->where('id', $promoCode->mt5_account_pool_entry_id)
            ->update([
                'password' => 'plain-imported-trading-pass',
                'investor_password' => 'plain-imported-investor-pass',
            ]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'full_name' => 'Plain Import Trader',
            'email' => 'plain-import-billing@example.com',
            'street_address' => '10 Promo Street',
            'city' => 'Asuncion',
            'postal_code' => '1209',
            'country' => 'PY',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'promo_code' => 'WFXGIVE-335400',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ])->assertRedirect(route('dashboard.accounts'));

        $order = Order::query()->firstOrFail();
        $account = TradingAccount::query()->where('order_id', $order->id)->firstOrFail();
        $promoCode->refresh();

        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame('promo', $order->payment_provider);
        $this->assertSame('335400', $account->platform_login);
        $this->assertSame('plain-imported-trading-pass', data_get($account->meta, 'credentials.password'));
        $this->assertSame('plain-imported-investor-pass', data_get($account->meta, 'credentials.investor_password'));
        $this->assertNotNull($promoCode->used_at);
        Mail::assertSent(ChallengeAccountDetailsMail::class, 1);
    }

    public function test_giveaway_promo_code_claim_reports_assignment_error_for_wrong_key_encrypted_credentials(): void
    {
        Mail::fake();

        $promoCode = $this->createGiveawayPromoCode('WFXGIVE-335400', [
            'login' => '335400',
        ]);

        $wrongKeyEncryptedPayload = base64_encode((string) json_encode([
            'iv' => base64_encode(random_bytes(16)),
            'value' => base64_encode('encrypted-with-another-key'),
            'mac' => str_repeat('0', 64),
            'tag' => '',
        ]));

        DB::table('mt5_account_pool_entries')
            ->where('id', $promoCode->mt5_account_pool_entry_id)
            ->update([
                'password' => $wrongKeyEncryptedPayload,
                'investor_password' => $wrongKeyEncryptedPayload,
            ]);

        $this->actingAs(User::factory()->create())->post(route('checkout.store'), [
            'full_name' => 'Wrong Key Trader',
            'email' => 'wrong-key-billing@example.com',
            'street_address' => '10 Promo Street',
            'city' => 'Asuncion',
            'postal_code' => '1209',
            'country' => 'PY',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'promo_code' => 'WFXGIVE-335400',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ])->assertSessionHasErrors([
            'promo_code' => 'This promo code is valid, but the giveaway account could not be assigned. Please contact support.',
        ]);

        $promoCode->refresh();

        $this->assertNull($promoCode->used_at);
        $this->assertSame(0, TradingAccount::query()->count());
        Mail::assertNothingSent();
    }

    public function test_giveaway_promo_code_rejects_faq_restricted_country_before_assignment(): void
    {
        Mail::fake();

        $promoEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335375',
            'password' => 'promo-trading-pass',
            'investor_password' => 'promo-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_promo' => true,
            'is_available' => false,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'promo_marker' => 'Promo',
            ],
        ]);

        $promoCode = Mt5PromoCode::query()->create([
            'code' => 'WFXGIVE-335375',
            'mt5_account_pool_entry_id' => $promoEntry->id,
            'mt5_login' => '335375',
        ]);

        $this->actingAs(User::factory()->create())->post(route('checkout.store'), [
            'full_name' => 'Restricted Giveaway Trader',
            'email' => 'restricted-giveaway@example.com',
            'street_address' => '11 Promo Street',
            'city' => 'Caracas',
            'postal_code' => '1010',
            'country' => 'VE',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'promo_code' => 'WFXGIVE-335375',
            'payment_provider' => 'stripe',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ])->assertSessionHasErrors(['country']);

        $promoCode->refresh();
        $promoEntry->refresh();

        $this->assertSame(0, Order::query()->count());
        $this->assertNull($promoCode->used_at);
        $this->assertNull($promoEntry->allocated_trading_account_id);
        Mail::assertNothingSent();
    }

    public function test_giveaway_promo_code_requires_the_10k_two_step_plan(): void
    {
        Mail::fake();

        $promoEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335374',
            'password' => 'promo-trading-pass',
            'investor_password' => 'promo-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_promo' => true,
            'is_available' => false,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'promo_marker' => 'Promo',
            ],
        ]);

        Mt5PromoCode::query()->create([
            'code' => 'WFXGIVE-335374',
            'mt5_account_pool_entry_id' => $promoEntry->id,
            'mt5_login' => '335374',
        ]);

        $basePayload = [
            'full_name' => 'Giveaway Trader',
            'email' => 'giveaway-billing@example.com',
            'street_address' => '10 Promo Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'currency' => 'USD',
            'promo_code' => 'WFXGIVE-335374',
            'payment_provider' => 'stripe',
            'accept_terms_and_residency' => '1',
            'accept_refund_policy' => '1',
        ];

        $this->actingAs(User::factory()->create())->post(route('checkout.store'), $basePayload + [
            'challenge_type' => 'one_step',
            'account_size' => 10000,
        ])->assertSessionHasErrors([
            'promo_code' => 'This promo code is only valid for the $10K 2-step evaluation account.',
        ]);

        $this->actingAs(User::factory()->create())->post(route('checkout.store'), $basePayload + [
            'challenge_type' => 'two_step',
            'account_size' => 25000,
        ])->assertSessionHasErrors([
            'promo_code' => 'This promo code is only valid for the $10K 2-step evaluation account.',
        ]);

        $promoEntry->refresh();

        $this->assertNull($promoEntry->allocated_trading_account_id);
    }

    public function test_dashboard_invoice_download_is_scoped_to_the_invoice_owner(): void
    {
        $owner = User::factory()->create([
            'email' => 'invoice-owner@example.com',
        ]);
        $otherUser = User::factory()->create([
            'email' => 'invoice-other@example.com',
        ]);

        $order = Order::query()->create([
            'user_id' => $owner->id,
            'email' => $owner->email,
            'full_name' => $owner->name,
            'street_address' => '1 Invoice Street',
            'city' => 'Madrid',
            'postal_code' => '28001',
            'country' => 'ES',
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 99,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'final_price' => 99,
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'external_checkout_id' => 'cs_invoice_scope',
            'external_payment_id' => 'pi_invoice_scope',
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'WF-SCOPE-0001',
            'user_id' => $owner->id,
            'order_id' => $order->id,
            'currency' => 'USD',
            'subtotal' => 99,
            'tax_amount' => 0,
            'total' => 99,
            'status' => 'paid',
            'issued_at' => now(),
        ]);

        $this->actingAs($otherUser)
            ->get(route('dashboard.invoices.download', $invoice))
            ->assertForbidden();
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
        Mail::fake();
        Storage::fake('public');

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
        $this->assertSame(1, Invoice::query()->where('order_id', $order->id)->count());
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
        Storage::disk('public')->assertExists((string) Invoice::query()->where('order_id', $order->id)->value('pdf_path'));
        Mail::assertSent(ChallengePurchaseConfirmationMail::class, 1);
        Mail::assertNotSent(ChallengeAccountDetailsMail::class);
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
            ->assertSee('The first payout can be requested after 21 days.')
            ->assertSee('Payments within 24 hours after approval')
            ->assertSee('Once approved, payouts are processed within 24 hours.');

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Commissions are paid upon request and are subject to review and approval by the Wolforix Partner Success Team.')
            ->assertSee('minimum withdrawal threshold of $100')
            ->assertSee('support@wolforix.com');
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
            ->assertSee('What platform does Wolforix use?')
            ->assertSee('Wolforix uses MetaTrader 5 (MT5).')
            ->assertSee('MetaQuotes-Demo')
            ->assertSee('What can I trade?')
            ->assertSee('SPX500, NDX100, US30')
            ->assertSee('BTCUSD, ETHUSD, XRPUSD')
            ->assertSee('What payment methods are accepted?')
            ->assertSee('Credit and debit cards (Visa, Mastercard, American Express)')
            ->assertSee('All payments are processed securely via Stripe and PayPal')
            ->assertSee('What payout methods are available?')
            ->assertSee('Bank Transfer (via Stripe infrastructure, depending on region)')
            ->assertSee('Requests are typically reviewed within 1–3 business days.')
            ->assertSee('mailto:support@wolforix.com', false)
            ->assertSee('How does the account scaling plan work?')
            ->assertSee('Scaling updates are applied at the end of each trading day.')
            ->assertSee('Any attempt to bypass or manipulate the scaling system is strictly prohibited.')
            ->assertSee('What trading times are allowed?')
            ->assertSee('Trading is available 24 hours, 5 days a week (Monday to Friday)')
            ->assertSee('Trading restrictions related to news events (±5 minutes) still apply.')
            ->assertSee('Which countries are restricted?')
            ->assertSee('Iran, North Korea, Syria, Sudan, Cuba, Russia, and Venezuela')
            ->assertSee('anti-money laundering (AML) and counter-terrorism financing (CTF) policies')
            ->assertSee('Which trading strategies are allowed?')
            ->assertSee('Are hedging across accounts and copy trading allowed?')
            ->assertSee('Hedging across multiple accounts and unauthorized copy trading are strictly prohibited')
            ->assertSee('Long EURUSD in one account and short EURUSD in another.')
            ->assertSee('Wolforix monitors copy trading activity.')
            ->assertSee('Wolforix may restrict or permanently ban the user.')
            ->assertSee('Hedging and copy trading threaten platform integrity.')
            ->assertSee('Is high-frequency trading (HFT) allowed?')
            ->assertSee('High-frequency trading (HFT) is strictly prohibited in Wolforix.')
            ->assertSee('Trading behavior that places abnormal load on the platform.')
            ->assertSee('Repeated violations may result in a permanent ban.')
            ->assertSee('Are duration abuse, grid trading, and martingale strategies allowed?')
            ->assertSee('Duration abuse refers to systematically opening and closing trades')
            ->assertSee('Grid systems without proper risk control are not allowed.')
            ->assertSee('Strategies that create exponential risk exposure are not allowed.')
            ->assertSee('Trades closed in less than 60 seconds are strictly prohibited if they result in profit.');
    }

    public function test_translated_faq_locales_include_the_new_policy_content(): void
    {
        $expectedByLocale = [
            'de' => ['Welche Plattform verwendet Wolforix?', 'Wie oft werden Auszahlungen verarbeitet?', 'Ich habe erfolgreich bestanden. Was soll ich jetzt tun?', 'Wie funktioniert es?', 'Wie sehe ich meinen Gewinn und Kontostand?'],
            'es' => ['¿Qué plataforma utiliza Wolforix?', '¿Con qué frecuencia se procesan los payouts?', 'He aprobado correctamente, ¿qué debo hacer ahora?', '¿Cómo funciona?', '¿Cómo veo mi beneficio y balance?'],
            'fr' => ['Quelle plateforme Wolforix utilise-t-il ?', 'À quelle fréquence les payouts sont-ils traités ?', 'J’ai réussi, que dois-je faire maintenant ?', 'Comment ça fonctionne ?', 'Comment voir mon profit et mon solde ?'],
            'hi' => ['Wolforix कौन सा platform use करता है?', 'पेआउट कितनी बार प्रोसेस होते हैं?', 'सफलतापूर्वक पास होने के बाद मुझे क्या करना चाहिए?', 'यह कैसे काम करता है?', 'मैं अपना profit और balance कैसे देखूँ?'],
            'it' => ['Quale piattaforma usa Wolforix?', 'Ogni quanto vengono elaborati i payout?', 'Ho superato con successo, cosa devo fare ora?', 'Come funziona?', 'Come vedo profitto e saldo?'],
            'pt' => ['Que plataforma usa a Wolforix?', 'Com que frequência são processados os payouts?', 'Passei com sucesso, o que devo fazer agora?', 'Como funciona?', 'Como vejo o meu lucro e saldo?'],
        ];

        foreach ($expectedByLocale as $locale => $expectedStrings) {
            $translations = require base_path("lang/{$locale}/site.php");
            $faqText = json_encode($translations['faq']['sections'], JSON_UNESCAPED_UNICODE);

            $this->assertCount(10, $translations['faq']['sections'], "FAQ section count mismatch for {$locale}.");
            $this->assertStringContainsString('MetaQuotes-Demo', $faqText, "MT5 login details missing for {$locale}.");
            $this->assertStringNotContainsString('I have successfully passed, what should I do now?', $faqText, "English passed FAQ leaked into {$locale}.");
            $this->assertStringNotContainsString('Commissions are paid upon request', $faqText, "English payout FAQ leaked into {$locale}.");
            $this->assertStringNotContainsString('How do I start my Wolforix Trial Account?', $faqText, "English trial FAQ leaked into {$locale}.");

            foreach ($expectedStrings as $expectedString) {
                $this->assertStringContainsString($expectedString, $faqText, "Missing translated FAQ text for {$locale}: {$expectedString}");
            }
        }
    }

    public function test_translated_faq_locales_have_matching_structure_without_english_fallback_leaks(): void
    {
        $shape = function (mixed $value) use (&$shape): mixed {
            if (! is_array($value)) {
                return 'string';
            }

            return array_map($shape, $value);
        };
        $flatten = function (mixed $value, string $path = '') use (&$flatten): array {
            if (! is_array($value)) {
                return [$path => (string) $value];
            }

            $flattened = [];

            foreach ($value as $key => $child) {
                $flattened = [...$flattened, ...$flatten($child, "{$path}/{$key}")];
            }

            return $flattened;
        };
        $englishFaq = (require base_path('lang/en/site.php'))['faq'];
        $englishShape = $shape($englishFaq);
        $englishFlat = $flatten($englishFaq);
        $allowedExactMatches = [
            'FAQ',
            'MT5',
            'MetaTrader 5',
            'Wolforix',
            'Wolforix Ltd.',
            'PayPal',
            'Stripe',
            'Forex',
            'EURUSD, GBPUSD, USDJPY, USDCHF, USDCAD',
            'SPX500, NDX100, US30',
            'GER30, UK100, FRA40',
            'XAUUSD (Gold)',
            'BTCUSD, ETHUSD, XRPUSD',
            'ADAUSD, LTCUSD, XLMUSD',
            'UKOUSD (Brent)',
            'USOUSD (Crude Oil)',
            'Wolforix Step-1 Instant',
            'Wolforix Step-Pro',
            'support@wolforix.com',
            'https://www.icmarkets.eu/de/open-trading-account/demo',
        ];

        foreach (['de', 'es', 'fr', 'hi', 'it', 'pt'] as $locale) {
            $faq = (require base_path("lang/{$locale}/site.php"))['faq'];
            $this->assertSame($englishShape, $shape($faq), "FAQ structure mismatch for {$locale}.");

            $leaks = collect($flatten($faq))
                ->filter(fn (string $text, string $path): bool => ($englishFlat[$path] ?? null) === $text
                    && ! in_array($text, $allowedExactMatches, true)
                    && trim($text) !== '')
                ->all();

            $this->assertSame([], $leaks, "English FAQ fallback text leaked into {$locale}.");
        }
    }

    public function test_faq_display_search_and_wolfi_use_selected_locale_faq_content(): void
    {
        $contentIndex = app(PublicContentIndex::class);
        $expectations = [
            'de' => ['passed' => 'Ich habe erfolgreich bestanden. Was soll ich jetzt tun?', 'trial' => 'Wie funktioniert es?', 'payout' => 'Wie oft werden Auszahlungen verarbeitet?'],
            'es' => ['passed' => 'He aprobado correctamente, ¿qué debo hacer ahora?', 'trial' => '¿Cómo funciona?', 'payout' => '¿Con qué frecuencia se procesan los payouts?'],
            'fr' => ['passed' => 'J’ai réussi, que dois-je faire maintenant ?', 'trial' => 'Comment ça fonctionne ?', 'payout' => 'À quelle fréquence les payouts sont-ils traités ?'],
            'hi' => ['passed' => 'सफलतापूर्वक पास होने के बाद मुझे क्या करना चाहिए?', 'trial' => 'यह कैसे काम करता है?', 'payout' => 'पेआउट कितनी बार प्रोसेस होते हैं?'],
            'it' => ['passed' => 'Ho superato con successo, cosa devo fare ora?', 'trial' => 'Come funziona?', 'payout' => 'Ogni quanto vengono elaborati i payout?'],
            'pt' => ['passed' => 'Passei com sucesso, o que devo fazer agora?', 'trial' => 'Como funciona?', 'payout' => 'Com que frequência são processados os payouts?'],
        ];

        foreach ($expectations as $locale => $strings) {
            $this->withSession(['locale' => $locale])
                ->get(route('faq'))
                ->assertOk()
                ->assertSee($strings['passed'])
                ->assertSee($strings['trial'])
                ->assertSee($strings['payout'])
                ->assertDontSee('I have successfully passed, what should I do now?')
                ->assertDontSee('How do I start my Wolforix Trial Account?')
                ->assertDontSee('Commissions are paid upon request');

            $siteSearchText = json_encode($contentIndex->siteSearchIndex($locale), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $wolfiIndexText = json_encode($contentIndex->voiceAssistantIndex([$locale], [$locale => $locale]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            foreach ($strings as $expectedString) {
                $this->assertStringContainsString($expectedString, $siteSearchText, "Site search index missing {$locale} FAQ text: {$expectedString}");
                $this->assertStringContainsString($expectedString, $wolfiIndexText, "Wolfi index missing {$locale} FAQ text: {$expectedString}");
            }

            $this->assertStringNotContainsString('I have successfully passed, what should I do now?', $siteSearchText);
            $this->assertStringNotContainsString('I have successfully passed, what should I do now?', $wolfiIndexText);
        }
    }

    public function test_wolfi_and_site_search_indexes_include_updated_faq_content(): void
    {
        $contentIndex = app(PublicContentIndex::class);
        $siteSearchText = json_encode($contentIndex->siteSearchIndex('en'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $wolfiIndexText = json_encode($contentIndex->voiceAssistantIndex(['en'], ['en' => 'en-US']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expectedFaqContent = [
            'MetaQuotes-Demo',
            'Bank Transfer (via Stripe infrastructure, depending on region)',
            'Trading is available 24 hours, 5 days a week',
            'Hedging across multiple accounts and unauthorized copy trading',
            'High-frequency trading (HFT) is strictly prohibited in Wolforix.',
            'Grid systems without proper risk control',
            'Commissions are paid upon request and are subject to review and approval by the Wolforix Partner Success Team.',
            'I have successfully passed, what should I do now?',
            'Complete your identity verification (KYC/KYB) in your client area',
            'How is working?',
            'Open your MT5 demo account with IC Markets using the button in the trial setup screen.',
            'Download the Wolforix MT5 connector',
            'Base URL, Account Reference, and Secret Token',
        ];

        $this->assertStringContainsString('#platform-what-platform-does-wolforix-use', $siteSearchText);
        $this->assertStringContainsString('#platform-what-platform-does-wolforix-use', $wolfiIndexText);

        foreach ($expectedFaqContent as $expectedString) {
            $this->assertStringContainsString($expectedString, $siteSearchText, "Site search index is missing FAQ content: {$expectedString}");
            $this->assertStringContainsString($expectedString, $wolfiIndexText, "Wolfi index is missing FAQ content: {$expectedString}");
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('MetaQuotes-Demo')
            ->assertSee('High-frequency trading (HFT) is strictly prohibited in Wolforix.');

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('MetaQuotes-Demo')
            ->assertSee('High-frequency trading (HFT) is strictly prohibited in Wolforix.');
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

    public function test_site_search_index_includes_legal_rule_sections_and_deep_links(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('News Trading Rule')
            ->assertSee('Scalping Rule')
            ->assertSee('It is prohibited to open or close trades 5 minutes before and 5 minutes after a high-impact news event.')
            ->assertSee('Trades closed in less than 60 seconds are strictly prohibited if they result in profit.')
            ->assertSee('#news-trading-rule')
            ->assertSee('#scalping-rule');
    }

    public function test_wolfi_voice_index_includes_terms_rule_content(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('News Trading Rule')
            ->assertSee('Scalping Rule')
            ->assertSee('It is prohibited to open or close trades 5 minutes before and 5 minutes after a high-impact news event.')
            ->assertSee('Trades closed in less than 60 seconds are strictly prohibited if they result in profit.')
            ->assertSee('#news-trading-rule')
            ->assertSee('#scalping-rule');
    }

    public function test_faq_contains_the_high_impact_news_rule(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Can I trade during high-impact news events?')
            ->assertSee('It is prohibited to open or close trades 5 minutes before and 5 minutes after a high-impact news event.');
    }

    public function test_admin_clients_redirects_to_the_admin_login_form(): void
    {
        $this->get(route('admin.clients.index'))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.reviews.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_log_in_via_form_and_view_client_list_and_metrics(): void
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

        $this->post(route('admin.login.store'), [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect(route('admin.clients.index'));

        $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Admin Review Trader')
            ->assertSee('Spain')
            ->assertSee('1-Step Instant / 25K')
            ->assertSee('$159.00')
            ->assertSee('Stripe')
            ->assertSee('Paid')
            ->assertSee('View Metrics');

        $this->get(route('admin.clients.show', $user))
            ->assertOk()
            ->assertSee('Admin Review Trader')
            ->assertSee('Billing Summary')
            ->assertSee('Profit')
            ->assertSee('$3,350.00')
            ->assertSee('Current Status')
            ->assertSee('Active');
    }

    public function test_admin_review_panel_shows_trustpilot_status_and_sends_test_email(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-20 10:00:00'));

        try {
            config()->set('wolforix.admin_auth.username', 'admin');
            config()->set('wolforix.admin_auth.password', 'secret');
            config()->set('wolforix.review_requests.trustpilot.url', 'https://de.trustpilot.com/review/wolforix.com');
            config()->set('wolforix.review_requests.trustpilot.reminder_enabled', true);

            $user = User::factory()->create([
                'name' => 'Review Monitor Trader',
                'email' => 'profile-review@example.com',
            ]);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'email' => 'recipient-review@example.com',
                'full_name' => 'Review Recipient',
                'street_address' => '9 Review Street',
                'city' => 'Madrid',
                'postal_code' => '28001',
                'country' => 'ES',
                'challenge_type' => 'one_step',
                'account_size' => 10000,
                'currency' => 'USD',
                'payment_provider' => 'stripe',
                'base_price' => 99,
                'discount_amount' => 0,
                'final_price' => 99,
                'payment_status' => 'paid',
                'order_status' => 'completed',
            ]);

            TradingAccount::query()->create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'challenge_type' => 'one_step',
                'account_size' => 10000,
                'account_reference' => 'WFX-REVIEW-10001',
                'challenge_status' => 'passed',
                'account_status' => 'completed',
                'is_trial' => false,
                'passed_email_sent_at' => Carbon::parse('2026-04-18 09:00:00'),
                'meta' => [
                    'trustpilot_review' => [
                        'initial_requested_at' => '2026-04-18T09:01:00+00:00',
                        'reminder_due_at' => '2026-04-25T09:01:00+00:00',
                    ],
                ],
            ]);

            $this->post(route('admin.login.store'), [
                'username' => 'admin',
                'password' => 'secret',
            ])->assertRedirect(route('admin.clients.index'));

            $this->get(route('admin.reviews.index'))
                ->assertOk()
                ->assertSee('Trustpilot review emails')
                ->assertSee('recipient-review@example.com')
                ->assertSee('Review Recipient')
                ->assertSee('WFX-REVIEW-10001')
                ->assertSee('Challenge passed email')
                ->assertSee('2026-04-18 09:01')
                ->assertSee('Scheduled')
                ->assertSee('https://de.trustpilot.com/review/wolforix.com');

            $this->post(route('admin.reviews.test'), [
                'email' => 'qa-review@example.com',
                'trader_name' => 'QA Review',
            ])
                ->assertRedirect(route('admin.reviews.index'))
                ->assertSessionHas('status', 'Trustpilot test email sent to: qa-review@example.com');

            Mail::assertSent(TrustpilotReviewRequestMail::class, function (TrustpilotReviewRequestMail $mail): bool {
                return $mail->traderName === 'QA Review'
                    && $mail->reviewUrl === 'https://de.trustpilot.com/review/wolforix.com'
                    && $mail->reminder === false;
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_login_form_rejects_invalid_credentials(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'username' => 'admin',
                'password' => 'wrong-secret',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('username');
    }

    public function test_admin_client_show_can_switch_accounts_and_review_detailed_trade_history(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');
        Carbon::setTestNow(Carbon::parse('2026-04-14 12:30:00'));

        try {
            $user = User::factory()->create([
                'name' => 'Trade Review Trader',
                'email' => 'trade-review@example.com',
            ]);

            UserProfile::query()->create([
                'user_id' => $user->id,
                'country' => 'Spain',
                'city' => 'Madrid',
                'street_address' => '8 Trader Street',
                'postal_code' => '28001',
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

            $order = Order::query()->create([
                'user_id' => $user->id,
                'challenge_plan_id' => $plan->id,
                'email' => $user->email,
                'full_name' => $user->name,
                'street_address' => '8 Trader Street',
                'city' => 'Madrid',
                'postal_code' => '28001',
                'country' => 'ES',
                'challenge_type' => 'one_step',
                'account_size' => 25000,
                'currency' => 'USD',
                'payment_provider' => 'stripe',
                'base_price' => 199,
                'discount_percent' => 20,
                'discount_amount' => 40,
                'final_price' => 159,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'external_checkout_id' => 'cs_trade_review',
                'external_payment_id' => 'pi_trade_review',
            ]);

            $purchase = ChallengePurchase::query()->create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'challenge_plan_id' => $plan->id,
                'challenge_type' => 'one_step',
                'account_size' => 25000,
                'currency' => 'USD',
                'account_status' => 'active',
            ]);

            $olderAccount = TradingAccount::query()->create([
                'user_id' => $user->id,
                'challenge_plan_id' => $plan->id,
                'order_id' => $order->id,
                'challenge_purchase_id' => $purchase->id,
                'challenge_type' => 'one_step',
                'account_size' => 25000,
                'account_reference' => 'MT5-REVIEW-001',
                'platform' => 'MT5',
                'platform_slug' => 'mt5',
                'platform_account_id' => 'MT5-001',
                'platform_login' => '770001',
                'platform_environment' => 'demo',
                'platform_status' => 'connected',
                'stage' => 'Single Phase',
                'status' => 'Active',
                'account_type' => 'challenge',
                'account_phase' => 'single_phase',
                'phase_index' => 1,
                'account_status' => 'active',
                'challenge_status' => 'active',
                'is_funded' => false,
                'is_trial' => false,
                'starting_balance' => 25000,
                'phase_starting_balance' => 25000,
                'phase_reference_balance' => 25000,
                'balance' => 25420,
                'equity' => 25395,
                'highest_equity_today' => 25420,
                'daily_drawdown' => 0,
                'daily_loss_used' => 0,
                'max_drawdown' => 0,
                'max_drawdown_used' => 0,
                'profit_loss' => -25,
                'total_profit' => 420,
                'today_profit' => 120,
                'drawdown_percent' => 0.3,
                'profit_target_percent' => 10,
                'profit_target_amount' => 2500,
                'profit_target_progress_percent' => 16.8,
                'daily_drawdown_limit_percent' => 4,
                'daily_drawdown_limit_amount' => 1000,
                'max_drawdown_limit_percent' => 8,
                'max_drawdown_limit_amount' => 2000,
                'profit_split' => 80,
                'minimum_trading_days' => 3,
                'trading_days_completed' => 2,
                'sync_status' => 'success',
                'sync_source' => 'mt5_ea',
                'last_synced_at' => now(),
                'last_evaluated_at' => now(),
            ]);

            $olderAccount->balanceSnapshots()->create([
                'snapshot_at' => now(),
                'balance' => 25420,
                'equity' => 25395,
                'profit_loss' => -25,
                'total_profit' => 420,
                'today_profit' => 120,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0.3,
                'payload' => [
                    'trade_history' => [
                        [
                            'deal_id' => 'D-3101',
                            'symbol' => 'EURUSD',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-14 09:10:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-14 10:30:00')->timestamp,
                            'entry_price' => 1.10020,
                            'exit_price' => 1.10170,
                            'volume' => 1,
                            'profit' => 140,
                            'commission' => -2,
                            'swap' => -1,
                        ],
                    ],
                    'open_positions' => [
                        [
                            'position_id' => 'P-3102',
                            'symbol' => 'XAUUSD',
                            'trade_side' => 'sell',
                            'open_timestamp' => Carbon::parse('2026-04-14 11:10:00')->timestamp,
                            'entry_price' => 3240.20,
                            'volume' => 0.5,
                            'profit' => -25,
                            'commission' => -0.5,
                        ],
                    ],
                ],
            ]);

            $newerAccount = TradingAccount::query()->create([
                'user_id' => $user->id,
                'challenge_plan_id' => $plan->id,
                'order_id' => $order->id,
                'challenge_purchase_id' => $purchase->id,
                'challenge_type' => 'one_step',
                'account_size' => 25000,
                'account_reference' => 'MT5-REVIEW-002',
                'platform' => 'MT5',
                'platform_slug' => 'mt5',
                'platform_account_id' => 'MT5-002',
                'platform_login' => '770002',
                'platform_environment' => 'demo',
                'platform_status' => 'connected',
                'stage' => 'Single Phase',
                'status' => 'Active',
                'account_type' => 'challenge',
                'account_phase' => 'single_phase',
                'phase_index' => 1,
                'account_status' => 'active',
                'challenge_status' => 'active',
                'is_funded' => false,
                'is_trial' => false,
                'starting_balance' => 25000,
                'phase_starting_balance' => 25000,
                'phase_reference_balance' => 25000,
                'balance' => 25110,
                'equity' => 25180,
                'highest_equity_today' => 25180,
                'daily_drawdown' => 0,
                'daily_loss_used' => 0,
                'max_drawdown' => 0,
                'max_drawdown_used' => 0,
                'profit_loss' => 70,
                'total_profit' => 110,
                'today_profit' => 110,
                'drawdown_percent' => 0.1,
                'profit_target_percent' => 10,
                'profit_target_amount' => 2500,
                'profit_target_progress_percent' => 4.4,
                'daily_drawdown_limit_percent' => 4,
                'daily_drawdown_limit_amount' => 1000,
                'max_drawdown_limit_percent' => 8,
                'max_drawdown_limit_amount' => 2000,
                'profit_split' => 80,
                'minimum_trading_days' => 3,
                'trading_days_completed' => 1,
                'sync_status' => 'success',
                'sync_source' => 'mt5_ea',
                'last_synced_at' => now(),
                'last_evaluated_at' => now(),
            ]);

            $newerAccount->balanceSnapshots()->create([
                'snapshot_at' => now(),
                'balance' => 25110,
                'equity' => 25180,
                'profit_loss' => 70,
                'total_profit' => 110,
                'today_profit' => 110,
                'daily_drawdown' => 0,
                'max_drawdown' => 0,
                'drawdown_percent' => 0.1,
                'payload' => [
                    'trade_history' => [
                        [
                            'deal_id' => 'D-4101',
                            'symbol' => 'NAS100',
                            'trade_side' => 'buy',
                            'open_timestamp' => Carbon::parse('2026-04-14 08:00:00')->timestamp,
                            'execution_timestamp' => Carbon::parse('2026-04-14 08:20:00')->timestamp,
                            'entry_price' => 18200.5,
                            'exit_price' => 18230.5,
                            'volume' => 0.3,
                            'profit' => 110,
                        ],
                    ],
                ],
            ]);

            $this->post(route('admin.login.store'), [
                'username' => 'admin',
                'password' => 'secret',
            ])->assertRedirect(route('admin.clients.index'));

            $this->get(route('admin.clients.show', ['user' => $user, 'account' => $olderAccount->id]))
                ->assertOk()
                ->assertSee('Per-account review')
                ->assertSee('MT5-REVIEW-001')
                ->assertSee('MT5-REVIEW-002')
                ->assertSee('Detailed trade history')
                ->assertSee('EURUSD')
                ->assertSee('XAUUSD')
                ->assertSee('1.10020')
                ->assertSee('1.10170')
                ->assertSee('01h 20m')
                ->assertSee('Open')
                ->assertSee('Win')
                ->assertDontSee('NAS100');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_client_show_exposes_consistency_state_for_selected_account(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');

        $user = User::factory()->create([
            'name' => 'Consistency Review Trader',
            'email' => 'consistency-review@example.com',
        ]);

        $plan = ChallengePlan::query()->create([
            'slug' => 'one-step-10k',
            'name' => '1-Step Instant 10K',
            'account_size' => 10000,
            'currency' => 'USD',
            'entry_fee' => 99,
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
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'account_reference' => 'WFX-CONSISTENCY-001',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_account_id' => 'MT5-CONSISTENCY-001',
            'platform_login' => '105381073',
            'platform_environment' => 'demo',
            'platform_status' => 'connected',
            'stage' => 'Single Phase',
            'status' => 'active',
            'account_type' => 'challenge',
            'account_phase' => 'single_phase',
            'phase_index' => 1,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10330,
            'equity' => 10330,
            'profit_loss' => 0,
            'total_profit' => 330,
            'today_profit' => 100,
            'drawdown_percent' => 0,
            'consistency_limit_percent' => 40,
            'consistency_status' => 'approaching',
            'consistency_last_trigger_threshold' => 35,
            'consistency_triggered_at' => Carbon::parse('2026-04-03 12:00:00'),
            'minimum_trading_days' => 3,
            'trading_days_completed' => 3,
            'sync_status' => 'success',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => Carbon::parse('2026-04-03 12:00:00'),
            'last_evaluated_at' => Carbon::parse('2026-04-03 12:00:00'),
            'rule_state' => [
                'consistency' => [
                    'status' => 'approaching',
                    'warning_visible' => true,
                    'current_month_profit' => 330,
                    'highest_single_day_profit' => 130,
                    'highest_single_day_date' => '2026-04-01',
                    'ratio_percent' => 39.39,
                    'approach_threshold_percent' => 35,
                    'breach_threshold_percent' => 40,
                    'last_triggered_threshold_percent' => 35,
                ],
            ],
            'meta' => [],
        ]);

        $this->post(route('admin.login.store'), [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect(route('admin.clients.index'));

        $this->get(route('admin.clients.show', $user))
            ->assertOk()
            ->assertSee('Consistency Status')
            ->assertSee('Approaching')
            ->assertSee('Current Month Profit')
            ->assertSee('$330.00')
            ->assertSee('Highest Single-Day Profit')
            ->assertSee('$130.00')
            ->assertSee('Consistency Ratio')
            ->assertSee('39.39%')
            ->assertSee('Last Triggered Threshold')
            ->assertSee('35.00%');
    }

    public function test_admin_can_save_mt5_credentials_and_trigger_purchase_credential_email_once(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');

        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Credential Trader',
            'email' => 'credential-trader@example.com',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'street_address' => '1 Credential Way',
            'city' => 'Dubai',
            'postal_code' => '00000',
            'country' => 'AE',
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 99,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'final_price' => 99,
            'payment_status' => Order::PAYMENT_PAID,
            'order_status' => Order::STATUS_COMPLETED,
            'metadata' => [],
        ]);

        $purchase = ChallengePurchase::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'account_status' => 'active',
            'meta' => [],
        ]);

        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => $purchase->id,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'account_reference' => 'WFX-MT5-CREDS',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_environment' => 'demo',
            'platform_status' => 'awaiting_metrics',
            'stage' => 'Single Phase',
            'status' => 'Active',
            'account_type' => 'challenge',
            'account_phase' => 'single_phase',
            'phase_index' => 1,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'is_funded' => false,
            'is_trial' => false,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'highest_equity_today' => 10000,
            'daily_drawdown' => 0,
            'daily_loss_used' => 0,
            'max_drawdown' => 0,
            'max_drawdown_used' => 0,
            'profit_loss' => 0,
            'total_profit' => 0,
            'today_profit' => 0,
            'drawdown_percent' => 0,
            'profit_target_percent' => 10,
            'profit_target_amount' => 1000,
            'profit_target_progress_percent' => 0,
            'daily_drawdown_limit_percent' => 4,
            'daily_drawdown_limit_amount' => 400,
            'max_drawdown_limit_percent' => 8,
            'max_drawdown_limit_amount' => 800,
            'profit_split' => 80,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 0,
            'sync_status' => 'pending',
            'sync_source' => 'admin_activation',
            'meta' => [],
        ]);

        $this->post(route('admin.login.store'), [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect(route('admin.clients.index'));

        $this->post(route('admin.clients.credentials', $user), [
            'account_id' => $account->id,
            'platform_login' => '105381073',
            'platform_account_id' => '105381073',
            'server_name' => 'Wolforix-Demo',
            'trading_password' => 'secret-pass',
            'investor_password' => 'investor-secret-pass',
        ])->assertRedirect(route('admin.clients.show', ['user' => $user, 'account' => $account->id]));

        $account->refresh();

        $this->assertSame('105381073', $account->platform_login);
        $this->assertSame('105381073', $account->platform_account_id);
        $this->assertSame('Wolforix-Demo', data_get($account->meta, 'credentials.server'));
        $this->assertSame('secret-pass', data_get($account->meta, 'credentials.password'));
        $this->assertSame('investor-secret-pass', data_get($account->meta, 'credentials.investor_password'));
        $this->assertNotNull($account->challenge_purchase_email_sent_at);

        Mail::assertSent(ChallengeAccountDetailsMail::class, function (ChallengeAccountDetailsMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->details['login_id'] === '105381073'
                && $mail->details['server'] === 'Wolforix-Demo'
                && $mail->details['password'] === 'secret-pass'
                && $mail->details['investor_password'] === 'investor-secret-pass';
        });

        $this->post(route('admin.clients.credentials', $user), [
            'account_id' => $account->id,
            'platform_login' => '105381073',
            'platform_account_id' => '105381073',
            'server_name' => 'Wolforix-Demo',
            'trading_password' => '',
            'investor_password' => '',
        ])->assertRedirect(route('admin.clients.show', ['user' => $user, 'account' => $account->id]));

        Mail::assertSent(ChallengeAccountDetailsMail::class, 1);
    }

    public function test_admin_can_activate_a_pending_client_account_and_receive_mt5_metrics(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');

        $user = User::factory()->create([
            'name' => 'Pending Activation Trader',
            'email' => 'pending-activation@example.com',
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

        $order = Order::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'street_address' => 'Activation Street 25',
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
            'external_checkout_id' => 'cs_pending_25000',
            'external_payment_id' => 'pi_pending_25000',
        ]);

        $purchase = ChallengePurchase::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'currency' => 'USD',
            'account_status' => 'pending_activation',
        ]);

        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => $purchase->id,
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'account_reference' => 'WFX-CT-25000-PEND',
            'platform' => 'cTrader',
            'platform_slug' => 'ctrader',
            'platform_environment' => 'demo',
            'platform_status' => 'pending_link',
            'stage' => 'Challenge Step 1',
            'status' => 'Pending Activation',
            'account_type' => 'challenge',
            'account_phase' => 'challenge',
            'phase_index' => 1,
            'account_status' => 'pending_activation',
            'challenge_status' => 'pending_activation',
            'starting_balance' => 25000,
            'phase_starting_balance' => 25000,
            'phase_reference_balance' => 25000,
            'balance' => 25000,
            'equity' => 25000,
            'highest_equity_today' => 25000,
            'profit_target_percent' => 10,
            'profit_target_amount' => 2500,
            'daily_drawdown_limit_percent' => 4,
            'daily_drawdown_limit_amount' => 1000,
            'max_drawdown_limit_percent' => 8,
            'max_drawdown_limit_amount' => 2000,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 0,
            'sync_status' => 'pending',
        ]);

        $this->post(route('admin.login.store'), [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect(route('admin.clients.index'));

        $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Pending Activation Trader')
            ->assertSee('Activate Account');

        $this->post(route('admin.clients.activate', $user))
            ->assertRedirect(route('admin.clients.index'))
            ->assertSessionHas('status');

        $account->refresh();
        $purchase->refresh();

        $this->assertSame('active', $account->account_status);
        $this->assertSame('active', $account->challenge_status);
        $this->assertSame('MT5', $account->platform);
        $this->assertSame('mt5', $account->platform_slug);
        $this->assertSame('awaiting_metrics', $account->platform_status);
        $this->assertSame('single_phase', $account->account_phase);
        $this->assertSame('Active', $account->status);
        $this->assertNotNull($account->activated_at);
        $this->assertNotEmpty($account->account_reference);
        $this->assertSame('active', $purchase->account_status);

        $this->assertDatabaseHas('trading_account_status_histories', [
            'trading_account_id' => $account->id,
            'new_status' => 'active',
            'source' => 'admin_activation',
        ]);

        $this->postJson(
            route('api.integrations.mt5.metrics', ['accountIdentifier' => $account->account_reference]),
            [
                'balance' => 25220,
                'equity' => 25240,
                'open_profit' => 20,
                'timestamp' => now()->toIso8601String(),
                'server_day' => now()->toDateString(),
                'trade_count' => 1,
                'has_activity' => true,
            ],
            [
                'Authorization' => 'Bearer '.data_get($account->fresh()->meta, 'mt5_connector.secret_token'),
            ],
        )
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('account_reference', $account->account_reference)
            ->assertJsonPath('challenge_status', 'active');
    }

    public function test_admin_activation_creates_a_challenge_account_when_one_does_not_exist(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');

        $user = User::factory()->create([
            'name' => 'No Account Yet',
            'email' => 'no-account-yet@example.com',
        ]);

        $plan = ChallengePlan::query()->create([
            'slug' => 'two-step-10000',
            'name' => '2-Step 10K',
            'account_size' => 10000,
            'currency' => 'USD',
            'entry_fee' => 109,
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

        $order = Order::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'street_address' => '2 Step Street 10',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'payment_provider' => 'paypal',
            'base_price' => 129,
            'discount_percent' => 15,
            'discount_amount' => 20.00,
            'final_price' => 109.00,
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'external_checkout_id' => 'pp_two_step_10000',
            'external_payment_id' => 'pp_txn_two_step_10000',
        ]);

        $purchase = ChallengePurchase::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'account_status' => 'pending_activation',
        ]);

        $this->post(route('admin.login.store'), [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect(route('admin.clients.index'));

        $this->post(route('admin.clients.activate', $user))
            ->assertRedirect(route('admin.clients.index'))
            ->assertSessionHas('status');

        $account = TradingAccount::query()
            ->where('challenge_purchase_id', $purchase->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('active', $account->account_status);
        $this->assertSame('phase_1', $account->account_phase);
        $this->assertSame(1, (int) $account->phase_index);
        $this->assertSame(10000.0, (float) $account->starting_balance);
        $this->assertSame(10000.0, (float) $account->balance);
        $this->assertSame(10000.0, (float) $account->equity);
        $this->assertSame(0, (int) $account->trading_days_completed);
        $this->assertSame('admin_activation', $account->sync_source);
        $this->assertNotEmpty($account->account_reference);
    }

    public function test_admin_activation_does_not_create_a_duplicate_active_account(): void
    {
        config()->set('wolforix.admin_auth.username', 'admin');
        config()->set('wolforix.admin_auth.password', 'secret');

        $user = User::factory()->create([
            'name' => 'Already Active Trader',
            'email' => 'already-active@example.com',
        ]);

        $plan = ChallengePlan::query()->create([
            'slug' => 'one-step-5000',
            'name' => '1-Step 5K',
            'account_size' => 5000,
            'currency' => 'USD',
            'entry_fee' => 59,
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

        $order = Order::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'street_address' => '1 Active Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
            'challenge_type' => 'one_step',
            'account_size' => 5000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 59,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'final_price' => 59,
            'payment_status' => 'paid',
            'order_status' => 'completed',
        ]);

        $purchase = ChallengePurchase::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'one_step',
            'account_size' => 5000,
            'currency' => 'USD',
            'account_status' => 'active',
        ]);

        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => $purchase->id,
            'challenge_type' => 'one_step',
            'account_size' => 5000,
            'account_reference' => 'WFX-MT5-5000-ACT',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_environment' => 'demo',
            'platform_status' => 'awaiting_metrics',
            'stage' => 'Single Phase',
            'status' => 'Active',
            'account_type' => 'challenge',
            'account_phase' => 'single_phase',
            'phase_index' => 1,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'starting_balance' => 5000,
            'phase_starting_balance' => 5000,
            'phase_reference_balance' => 5000,
            'balance' => 5000,
            'equity' => 5000,
            'highest_equity_today' => 5000,
            'minimum_trading_days' => 3,
            'activated_at' => now(),
        ]);

        $this->post(route('admin.login.store'), [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect(route('admin.clients.index'));

        $this->post(route('admin.clients.activate', $user))
            ->assertRedirect(route('admin.clients.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, TradingAccount::query()->where('challenge_purchase_id', $purchase->id)->count());
        $this->assertSame('active', $account->fresh()->account_status);
    }

    public function test_trial_registration_creates_a_trial_account_and_dashboard(): void
    {
        Mail::fake();

        $response = $this->post(route('trial.store'), [
            'email' => 'trial@example.com',
            'password' => 'password123',
        ]);

        $user = User::query()->where('email', 'trial@example.com')->firstOrFail();
        $trialAccount = TradingAccount::query()
            ->where('user_id', $user->id)
            ->where('is_trial', true)
            ->first();

        $response->assertRedirect(route('trial.setup'));
        $this->assertNotNull($trialAccount);
        $this->assertSame('trial', $trialAccount->account_type);
        $this->assertSame('MT5 Demo', $trialAccount->platform);
        $this->assertSame('mt5', $trialAccount->platform_slug);
        $this->assertEquals(10000.0, (float) $trialAccount->balance);
        $this->assertEquals(8.0, (float) $trialAccount->profit_target_percent);
        $this->assertFileExists(public_path('mt5software/wolforix-mt5-connector.zip'));
        Mail::assertSent(TrialAccountInstructionsMail::class, function (TrialAccountInstructionsMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->envelope()->subject === 'Your Wolforix Trial Account – Get Started'
                && str_contains($mail->render(), 'https://www.icmarkets.eu/de/open-trading-account/demo')
                && str_contains($mail->render(), 'Install the MT5 connector')
                && str_contains($mail->render(), 'Base URL, Account Reference, and Secret Token')
                && str_contains($mail->render(), (string) config('wolforix.support.email'));
        });

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('Connect Your MT5 Demo Account')
            ->assertSee('Not Connected')
            ->assertSee('Base URL')
            ->assertSee('Account Reference')
            ->assertSee('Secret Token');

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.setup'))
            ->assertOk()
            ->assertSee('Connect Your MT5 Demo Account')
            ->assertSee('Open Demo Account')
            ->assertSee('Download MT5 Connector')
            ->assertSee('mt5software/wolforix-mt5-connector.zip')
            ->assertDontSee('downloads/mt5-connector.ex5')
            ->assertSee('Copy WolforixRuleEngineEA.mq5 into MQL5/Experts')
            ->assertSee('Copy the Include files into MQL5/Include')
            ->assertSee('Base URL')
            ->assertSee($trialAccount->account_reference)
            ->assertDontSee('Enter your MT5 Account Number to continue.')
            ->assertDontSee('I already have my Demo Account');

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->post(route('trial.confirm-demo'))
            ->assertRedirect(route('trial.dashboard'));

        $trialAccount->refresh();
        $this->assertNull($trialAccount->platform_account_id);
        $this->assertNull($trialAccount->platform_login);
        $this->assertSame('pending_connection', $trialAccount->platform_status);
        $this->assertNotEmpty($trialAccount->meta['trial_connector_acknowledged_at'] ?? null);
        $this->assertNotEmpty($trialAccount->meta['mt5_connector']['secret_token'] ?? null);

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('Not Connected')
            ->assertSee('This is a Trial Account.')
            ->assertSee('Take Profit')
            ->assertSeeInOrder([
                'Take Profit',
                'Daily Drawdown Limit',
                'Max Drawdown Limit',
            ])
            ->assertSee('Minimum Trading Days')
            ->assertSee('No withdrawals')
            ->assertDontSee('Starting Balance')
            ->assertDontSee('Available Markets')
            ->assertDontSee('XAU/USD')
            ->assertDontSee('$10,000');

        $this->postJson(
            route('api.mt5.metrics', ['accountIdentifier' => $trialAccount->account_reference]),
            [
                'balance' => 10040,
                'equity' => 10055,
                'open_profit' => 15,
                'platform_login' => '105381073',
                'platform_account_id' => '105381073',
                'platform_environment' => 'ICMarkets-Demo',
                'timestamp' => now()->toIso8601String(),
                'server_day' => now()->toDateString(),
                'trade_count' => 1,
                'has_activity' => true,
                'sync_trigger' => 'ea_timer',
            ],
            [
                'Authorization' => 'Bearer '.$trialAccount->fresh()->meta['mt5_connector']['secret_token'],
            ],
        )
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('account_reference', $trialAccount->account_reference);

        $trialAccount->refresh();
        $this->assertSame('105381073', $trialAccount->platform_account_id);
        $this->assertSame('105381073', $trialAccount->platform_login);
        $this->assertSame('connected', $trialAccount->platform_status);
        $this->assertSame('mt5_ea', $trialAccount->sync_source);

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('Connected');
    }

    public function test_existing_user_can_submit_the_trial_form_and_enter_the_free_demo_flow(): void
    {
        Mail::fake();

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
            ->assertRedirect(route('trial.setup'))
            ->assertSessionHas('trial_user_id', $user->id);

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('trading_accounts', [
            'user_id' => $user->id,
            'is_trial' => true,
            'trial_status' => 'active',
        ]);
        Mail::assertSent(TrialAccountInstructionsMail::class, 1);
    }

    public function test_authenticated_user_can_start_a_trial_from_the_trial_page(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'existing-trial-user@example.com',
            'password' => 'password123',
        ]);

        $this->actingAs($user)
            ->get(route('trial.register'))
            ->assertRedirect(route('trial.setup'))
            ->assertSessionHas('trial_user_id', $user->id);

        $trialAccount = TradingAccount::query()
            ->where('user_id', $user->id)
            ->where('is_trial', true)
            ->first();

        $this->assertNotNull($trialAccount);
        $this->assertSame('active', $trialAccount->trial_status);
        Mail::assertSent(TrialAccountInstructionsMail::class, 1);
    }

    public function test_existing_user_login_from_trial_page_returns_to_trial_access(): void
    {
        $user = User::factory()->create([
            'email' => 'trial-login@example.com',
            'password' => 'password123',
        ]);

        $this->get(route('trial.register'))
            ->assertOk()
            ->assertSee('Recover Password')
            ->assertSee(route('password.request'), false)
            ->assertDontSee('Demo balance:')
            ->assertDontSee('Available markets:');

        $this->post(route('login.store'), [
            'login_email' => $user->email,
            'login_password' => 'password123',
        ])->assertRedirect(route('trial.register'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('trial.register'))
            ->assertRedirect(route('trial.setup'))
            ->assertSessionHas('trial_user_id', $user->id);

        $this->assertDatabaseHas('trading_accounts', [
            'user_id' => $user->id,
            'is_trial' => true,
            'trial_status' => 'active',
        ]);
    }

    public function test_authenticated_user_with_ended_trial_is_sent_to_the_trial_dashboard(): void
    {
        Mail::fake();

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

    public function test_trial_pass_sends_a_completion_email_and_marks_the_trial_as_passed(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'trial-pass@example.com',
            'password' => 'password123',
        ]);

        $trialAccount = TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-TRIAL-PASS01',
            'platform' => 'cTrader Demo',
            'stage' => 'Trial (Demo)',
            'status' => 'Active',
            'account_status' => 'active',
            'account_type' => 'trial',
            'is_trial' => true,
            'starting_balance' => 10000,
            'balance' => 10800,
            'equity' => 10800,
            'daily_drawdown' => 0,
            'max_drawdown' => 0,
            'profit_loss' => 800,
            'total_profit' => 800,
            'today_profit' => 100,
            'drawdown_percent' => 0,
            'profit_target_percent' => 8,
            'profit_target_amount' => 800,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 3,
            'allowed_symbols' => ['XAU/USD', 'EUR/USD', 'USD/JPY'],
            'trial_status' => 'active',
            'trial_started_at' => now()->subDays(4),
            'last_activity_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('You completed the free trial model.');

        $trialAccount->refresh();

        $this->assertSame('passed', $trialAccount->trial_status);
        $this->assertSame('Passed', $trialAccount->status);
        $this->assertNotNull($trialAccount->passed_at);
        $this->assertNotNull($trialAccount->ended_at);

        Mail::assertSent(TrialPassedMail::class, function (TrialPassedMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
    }

    public function test_trial_breach_sends_a_failure_email_when_rules_are_breached(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'trial-breach@example.com',
            'password' => 'password123',
        ]);

        $trialAccount = TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-TRIAL-BREACH1',
            'platform' => 'cTrader Demo',
            'stage' => 'Trial (Demo)',
            'status' => 'Active',
            'account_status' => 'active',
            'account_type' => 'trial',
            'is_trial' => true,
            'starting_balance' => 10000,
            'balance' => 9800,
            'equity' => 9800,
            'daily_drawdown' => 500,
            'max_drawdown' => 300,
            'profit_loss' => -200,
            'total_profit' => -200,
            'today_profit' => -200,
            'drawdown_percent' => 3,
            'profit_target_percent' => 8,
            'profit_target_amount' => 800,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 1,
            'allowed_symbols' => ['XAU/USD', 'EUR/USD', 'USD/JPY'],
            'trial_status' => 'active',
            'trial_started_at' => now()->subDays(1),
            'last_activity_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.dashboard'))
            ->assertOk()
            ->assertSee('Your trial has ended.');

        $trialAccount->refresh();

        $this->assertSame('ended', $trialAccount->trial_status);
        $this->assertSame('Ended', $trialAccount->status);
        $this->assertNotNull($trialAccount->failed_at);
        $this->assertNotNull($trialAccount->ended_at);

        Mail::assertSent(TrialBreachedMail::class, function (TrialBreachedMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
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
            ->assertRedirect(route('trial.setup'));

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
        FakeStripePaymentGateway::$checkoutSessionsCreated = 0;
        config()->set('wolforix.payments.providers.stripe.class', FakeStripePaymentGateway::class);
    }

    private function useFakePayPalGateway(): void
    {
        config()->set('wolforix.payments.providers.paypal.class', FakePayPalPaymentGateway::class);
        config()->set('wolforix.payments.providers.paypal.enabled', true);
        config()->set('wolforix.payments.providers.paypal.coming_soon', false);
    }

    private function createGiveawayPromoCode(string $code, array $entryOverrides = []): Mt5PromoCode
    {
        $entry = Mt5AccountPoolEntry::factory()->create(array_merge([
            'login' => '335374',
            'password' => 'promo-trading-pass',
            'investor_password' => 'promo-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_promo' => true,
            'is_available' => false,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'promo_marker' => 'Promo',
            ],
        ], $entryOverrides));

        return Mt5PromoCode::query()->create([
            'code' => $code,
            'mt5_account_pool_entry_id' => $entry->id,
            'mt5_login' => (string) $entry->login,
        ]);
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
