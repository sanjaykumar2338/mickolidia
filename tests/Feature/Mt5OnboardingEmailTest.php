<?php

namespace Tests\Feature;

use App\Mail\Mt5OnboardingSetupMail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Mt5OnboardingEmailTest extends TestCase
{
    public function test_mt5_onboarding_email_contains_required_setup_content_without_secrets(): void
    {
        config()->set('wolforix.support.email', 'support@wolforix.com');

        $mail = new Mt5OnboardingSetupMail();
        $html = $mail->render();
        $text = view('emails.mt5-onboarding-setup-text', [
            'dashboardUrl' => $mail->dashboardUrl,
            'setupUrl' => $mail->setupUrl,
            'setupVideoUrl' => $mail->setupVideoUrl,
            'supportEmail' => $mail->supportEmail,
        ])->render();

        $this->assertSame('Connect Your MT5 Account to Wolforix Dashboard', $mail->envelope()->subject);
        $this->assertSame('emails.mt5-onboarding-setup-text', $mail->content()->text);

        foreach ([
            'https://www.wolforix.com/dashboard',
            'https://www.wolforix.com/trial/setup',
            'https://www.wolforix.com/mt5_demo.mp4',
            'https://www.wolforix.com',
            'https://wolforix.com',
            'support@wolforix.com',
            'WolforixRuleEngineEA.mq5',
            'MQL5/Experts',
            'MQL5/Include',
            'Enable Algo Trading',
            'Wait for the dashboard status to show Connected',
        ] as $requiredContent) {
            $this->assertStringContainsString($requiredContent, $html);
            $this->assertStringContainsString($requiredContent, $text);
        }

        $this->assertStringContainsString(
            'Until the EA connector is installed, attached to a chart, and successfully synced',
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
                && $mail->hasCc('Support@wolforix.com')
                && $mail->envelope()->subject === 'Connect Your MT5 Account to Wolforix Dashboard';
        });
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
