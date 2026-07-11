<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecaptchaProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        config()->set('services.recaptcha.enabled', true);
        config()->set('services.recaptcha.type', 'v3');
        config()->set('services.recaptcha.site_key', 'test-site-key');
        config()->set('services.recaptcha.secret_key', 'test-secret-key');
        config()->set('services.recaptcha.score_threshold', 0.5);
        config()->set('services.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');
        config()->set('services.recaptcha.timeout', 5);
    }

    public function test_registration_requires_a_successful_recaptcha_response(): void
    {
        Mail::fake();
        Http::fake();

        $this->post(route('register.store'), [
            'register_name' => 'Captcha Trader',
            'register_email' => 'captcha@example.com',
            'register_password' => 'password123',
            'register_password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['g-recaptcha-response'], null, 'register');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'captcha@example.com',
        ]);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_recaptcha_widget_renders_on_protected_public_forms(): void
    {
        foreach ([route('login'), route('password.request'), route('trial.register')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('https://www.google.com/recaptcha/api.js?render=test-site-key', false)
                ->assertSee('data-recaptcha-token', false);
        }
    }

    public function test_recaptcha_checkbox_widget_can_be_enabled_for_v2_keys(): void
    {
        config()->set('services.recaptcha.type', 'checkbox');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('https://www.google.com/recaptcha/api.js', false)
            ->assertSee('class="g-recaptcha"', false)
            ->assertSee('data-sitekey="test-site-key"', false);
    }

    public function test_registration_continues_after_recaptcha_passes(): void
    {
        Mail::fake();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'register',
            ]),
        ]);

        $this->post(route('register.store'), [
            'register_name' => 'Captcha Trader',
            'register_email' => 'captcha-pass@example.com',
            'register_password' => 'password123',
            'register_password_confirmation' => 'password123',
            'g-recaptcha-response' => 'valid-token',
        ])
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'captcha-pass@example.com',
        ]);
        Mail::assertSent(WelcomeMail::class);
    }

    public function test_trial_registration_requires_recaptcha(): void
    {
        Mail::fake();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
            ]),
        ]);

        $this->post(route('trial.store'), [
            'email' => 'trial-captcha@example.com',
            'password' => 'password123',
            'g-recaptcha-response' => 'invalid-token',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['g-recaptcha-response']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'trial-captcha@example.com',
        ]);
        Mail::assertNothingSent();
    }

    public function test_password_reset_email_requires_recaptcha(): void
    {
        Notification::fake();
        Http::fake();

        $user = User::factory()->create([
            'email' => 'reset-captcha@example.com',
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['g-recaptcha-response']);

        Notification::assertNothingSent();
        Http::assertNothingSent();
    }
}
