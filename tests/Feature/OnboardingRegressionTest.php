<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_registration_page_is_available_and_protected_by_recaptcha_markup(): void
    {
        config()->set('services.recaptcha.enabled', true);
        config()->set('services.recaptcha.type', 'v3');
        config()->set('services.recaptcha.site_key', 'test-site-key');

        $this->get('/register')
            ->assertOk()
            ->assertSee('id="register"', false)
            ->assertSee('autofocus', false)
            ->assertSee('data-recaptcha-token', false)
            ->assertSee('data-recaptcha-status', false);
    }

    public function test_registration_normalizes_email_and_blocks_case_variant_duplicates(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'trader@example.com',
        ]);

        $this->post(route('register.store'), [
            'register_name' => '  Duplicate Trader  ',
            'register_email' => '  TRADER@EXAMPLE.COM  ',
            'register_password' => 'password123',
            'register_password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['register_email'], null, 'register');

        $this->assertGuest();
        $this->assertSame(1, User::query()->where('email', 'trader@example.com')->count());
    }

    public function test_trial_registration_requires_password_confirmation_before_creating_records(): void
    {
        Mail::fake();

        $this->post(route('trial.store'), [
            'email' => 'trial@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['password']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'trial@example.com']);
        $this->assertDatabaseCount('trading_accounts', 0);
    }

    public function test_demo_onboarding_uses_english_provider_url_and_never_claims_connection_before_sync(): void
    {
        Mail::fake();

        $this->post(route('trial.store'), [
            'email' => 'demo-flow@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('trial.setup'));

        $user = User::query()->where('email', 'demo-flow@example.com')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->get(route('trial.setup'))
            ->assertOk()
            ->assertSee('https://www.icmarkets.eu/en/open-trading-account/demo', false)
            ->assertSee('Wolforix has not confirmed the demo connection')
            ->assertDontSee('Your free trial account is ready.');

        $this->actingAs($user)
            ->withSession(['trial_user_id' => $user->id])
            ->post(route('trial.confirm-demo'))
            ->assertRedirect(route('trial.dashboard'))
            ->assertSessionHas('trial_success', __('site.trial.setup.continue_success'));

        $account = $user->trialAccounts()->latest('id')->firstOrFail();

        $this->assertSame('pending_connection', $account->platform_status);
        $this->assertNull($account->platform_login);
        $this->assertNull($account->platform_account_id);
    }

    public function test_public_pages_hide_whatsapp_and_telegram_community_links(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('https://t.me/wolforix', false)
            ->assertDontSee('chat.whatsapp.com', false)
            ->assertSee('https://youtube.com/@wolforix', false)
            ->assertSee(config('wolforix.social.instagram.url'), false);
    }
}
