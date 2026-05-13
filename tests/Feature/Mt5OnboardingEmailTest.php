<?php

namespace Tests\Feature;

use App\Mail\Mt5OnboardingSetupMail;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Mt5OnboardingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mt5_onboarding_email_contains_required_setup_content_without_secrets(): void
    {
        config()->set('wolforix.support.email', 'support@wolforix.com');
        config()->set('app.url', 'https://www.wolforix.com');

        $mail = new Mt5OnboardingSetupMail();
        $html = $mail->render();
        $text = view('emails.mt5-onboarding-setup-text', [
            'dashboardUrl' => $mail->dashboardUrl,
            'setupUrl' => $mail->setupUrl,
            'setupVideoUrl' => $mail->setupVideoUrl,
            'connectorDownloadUrl' => $mail->connectorDownloadUrl,
            'connectorReleaseLabel' => $mail->connectorReleaseLabel,
            'supportEmail' => $mail->supportEmail,
        ])->render();

        $this->assertSame('Action Required: Install the Updated Wolforix MT5 Connector', $mail->envelope()->subject);
        $this->assertSame('emails.mt5-onboarding-setup-text', $mail->content()->text);

        foreach ([
            'https://www.wolforix.com/dashboard',
            'https://www.wolforix.com/mt5/setup',
            'https://www.wolforix.com/mt5software/wolforix-mt5-connector.zip',
            'https://www.wolforix.com/mt5_demo.mp4',
            'https://www.wolforix.com',
            'https://wolforix.com',
            'support@wolforix.com',
            '2026.05.13 recovery build',
            'WolforixRuleEngineEA.mq5',
            'MQL5/Experts',
            'MQL5/Include',
            'Enable Algo Trading',
            'Wait for the dashboard status to show Connected',
            'live synchronization, rule monitoring, and dashboard metrics work correctly',
            'earlier MT5 sync/connectivity issues',
            'completely free funded account opportunity',
        ] as $requiredContent) {
            $this->assertStringContainsString($requiredContent, $html);
            $this->assertStringContainsString($requiredContent, $text);
        }

        $this->assertStringContainsString(
            'Until the updated EA connector is installed, attached to a chart, and successfully synced',
            $html
        );

        foreach ([
            'secret_token',
            'Secret Token',
            'mt5_connector',
            'MT5 password',
            'investor_password',
            'password:',
        ] as $sensitiveContent) {
            $this->assertStringNotContainsString($sensitiveContent, $html);
            $this->assertStringNotContainsString($sensitiveContent, $text);
        }

        $this->assertStringNotContainsString('https://www.wolforix.com/trial/setup', $html);
        $this->assertStringNotContainsString('https://www.wolforix.com/trial/setup', $text);
    }

    public function test_wolforix_send_mt5_onboarding_email_command_sends_to_requested_address(): void
    {
        Mail::fake();

        $exitCode = Artisan::call('wolforix:send-mt5-onboarding-email', [
            'email' => 'sk963070@gmail.com',
        ]);

        $this->assertSame(0, $exitCode);

        Mail::assertSent(Mt5OnboardingSetupMail::class, function (Mt5OnboardingSetupMail $mail): bool {
            return $mail->hasTo('sk963070@gmail.com')
                && $mail->hasCc('ceo@wolforix.com')
                && $mail->envelope()->subject === 'Action Required: Install the Updated Wolforix MT5 Connector';
        });
    }

    public function test_wolforix_send_mt5_connector_recovery_emails_command_sends_to_mt5_and_trial_users_with_ceo_sender(): void
    {
        Mail::fake();

        $mt5User = User::factory()->create(['email' => 'mt5-client@example.com']);
        TradingAccount::query()->create([
            'user_id' => $mt5User->id,
            'account_reference' => 'WFX-MT5-CLIENT',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'account_type' => 'challenge',
            'is_trial' => false,
        ]);

        $trialUser = User::factory()->create(['email' => 'trial-client@example.com']);
        TradingAccount::query()->create([
            'user_id' => $trialUser->id,
            'account_reference' => 'WFX-TRIAL-CLIENT',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'account_type' => 'trial',
            'is_trial' => true,
        ]);

        $nonMt5User = User::factory()->create(['email' => 'ctrader-client@example.com']);
        TradingAccount::query()->create([
            'user_id' => $nonMt5User->id,
            'account_reference' => 'WFX-CTRADER-CLIENT',
            'platform' => 'cTrader',
            'platform_slug' => 'ctrader',
            'account_type' => 'challenge',
            'is_trial' => false,
        ]);

        $exitCode = Artisan::call('wolforix:send-mt5-connector-recovery-emails');

        $this->assertSame(0, $exitCode);

        Mail::assertSent(Mt5OnboardingSetupMail::class, 2);
        Mail::assertSent(Mt5OnboardingSetupMail::class, function (Mt5OnboardingSetupMail $mail): bool {
            $envelope = $mail->envelope();

            return $mail->hasTo('mt5-client@example.com')
                && $mail->hasCc('ceo@wolforix.com')
                && $mail->mailer === 'ceo'
                && $envelope->from?->address === 'ceo@wolforix.com';
        });
        Mail::assertSent(Mt5OnboardingSetupMail::class, fn (Mt5OnboardingSetupMail $mail): bool => $mail->hasTo('trial-client@example.com'));
        Mail::assertNotSent(Mt5OnboardingSetupMail::class, fn (Mt5OnboardingSetupMail $mail): bool => $mail->hasTo('ctrader-client@example.com'));
    }

    public function test_wolforix_send_mt5_onboarding_email_command_rejects_invalid_email(): void
    {
        Mail::fake();

        $exitCode = Artisan::call('wolforix:send-mt5-onboarding-email', [
            'email' => 'not-an-email',
        ]);

        $this->assertSame(1, $exitCode);
        Mail::assertNothingSent();
    }
}
