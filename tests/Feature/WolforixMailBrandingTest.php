<?php

namespace Tests\Feature;

use App\Mail\ChallengeAccountDetailsMail;
use App\Mail\ChallengeFailedMail;
use App\Mail\ChallengePassedMail;
use App\Mail\ChallengePurchaseConfirmationMail;
use App\Mail\ConsistencyAlertMail;
use App\Mail\PhaseOnePassedMail;
use App\Mail\PhaseTwoAccountDetailsMail;
use App\Mail\TrialBreachedMail;
use App\Mail\TrialPassedMail;
use App\Mail\WelcomeMail;
use App\Notifications\WolforixResetPasswordNotification;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use Tests\TestCase;

class WolforixMailBrandingTest extends TestCase
{
    public function test_transactional_mailables_share_the_welcome_brand_shell(): void
    {
        config()->set('mail.automated_from.address', 'noreply@wolforix.com');
        config()->set('mail.automated_from.name', 'Wolforix Notifications');

        $user = User::factory()->make([
            'name' => 'Mail Trader',
            'email' => 'mail-trader@example.com',
        ]);

        $order = new Order([
            'order_number' => 'WFX-ORD-TEST-001',
            'email' => 'mail-trader@example.com',
            'full_name' => 'Mail Trader',
            'challenge_type' => 'one_step',
            'account_size' => 5000,
            'currency' => 'usd',
            'final_price' => 49,
            'payment_provider' => 'stripe',
        ]);

        $account = new TradingAccount([
            'account_reference' => 'WFX-CT-TEST-001',
            'platform' => 'MT5',
        ]);

        $supportEmail = (string) config('wolforix.support.email');

        $mailables = [
            new WelcomeMail($user),
            new ChallengePurchaseConfirmationMail($order),
            new ChallengeAccountDetailsMail('Mail Trader', $account, $order, [
                'platform' => 'MT5',
                'login_id' => '105381073',
                'password' => 'secret-pass',
                'server' => 'Wolforix-Demo',
                'account_type' => '1-Step Instant',
                'account_size' => '5K',
            ]),
            new PhaseOnePassedMail('Mail Trader', $account, [
                'plan' => '2-Step Pro / 5K',
                'completed_phase' => 'Phase 1',
                'next_phase' => 'Phase 2',
            ]),
            new PhaseTwoAccountDetailsMail('Mail Trader', $account, [
                'platform' => 'MT5',
                'login_id' => '205381073',
                'password' => 'phase-two-pass',
                'server' => 'Wolforix-Demo',
            ]),
            new ChallengeFailedMail($user, $account, [
                'plan' => '1-Step Instant / 5K',
                'rule' => 'Daily Loss Limit',
                'threshold' => '$200.00',
                'recorded_value' => '$245.00',
                'support_email' => $supportEmail,
            ]),
            new ChallengePassedMail($user, $account, [
                'plan' => '1-Step Instant / 5K',
                'profit_target' => '$500.00',
                'profit_target_percent' => '10%',
                'support_email' => $supportEmail,
            ]),
            new TrialPassedMail($user, $account),
            new TrialBreachedMail($user, $account, 'Daily loss limit reached'),
            new ConsistencyAlertMail('Mail Trader', $account, [
                'status' => 'approaching',
                'rule_label' => 'Approaching threshold',
                'account_reference' => 'WFX-CT-TEST-001',
                'current_month_profit' => '$330.00',
                'highest_single_day_profit' => '$130.00',
                'ratio_percent' => '39.39%',
                'threshold_percent' => '35.00%',
                'highest_single_day_date' => '2026-04-01',
                'rule_explanation' => 'Profits should be distributed across multiple trading days.',
                'support_email' => $supportEmail,
            ]),
        ];

        foreach ($mailables as $mailable) {
            $envelope = $mailable->envelope();
            $html = $mailable->render();

            $this->assertSame('noreply@wolforix.com', $envelope->from?->address, $mailable::class);
            $this->assertSame('Wolforix Notifications', $envelope->from?->name, $mailable::class);
            $this->assertEmpty($envelope->replyTo, $mailable::class);
            $this->assertStringContainsString('background-color:#050b13', $html, $mailable::class);
            $this->assertStringContainsString('Trade Fearlessly. Win Real.', $html, $mailable::class);
            $this->assertStringContainsString('Wolforix', $html, $mailable::class);
            $this->assertStringContainsString($supportEmail, $html, $mailable::class);
        }

        $passwordResetMail = (new WolforixResetPasswordNotification('reset-token-123'))->toMail($user);

        $passwordResetHtml = view('emails.password-reset', [
            'user' => $user,
            'actionUrl' => route('password.reset', [
                'token' => 'reset-token-123',
                'email' => $user->email,
            ]),
            'expireMinutes' => 60,
        ])->render();

        $this->assertStringContainsString('background-color:#050b13', $passwordResetHtml, WolforixResetPasswordNotification::class);
        $this->assertStringContainsString('Trade Fearlessly. Win Real.', $passwordResetHtml, WolforixResetPasswordNotification::class);
        $this->assertStringContainsString('Reset your Wolforix password', $passwordResetHtml, WolforixResetPasswordNotification::class);
        $this->assertStringContainsString($supportEmail, $passwordResetHtml, WolforixResetPasswordNotification::class);
        $this->assertSame(['noreply@wolforix.com', 'Wolforix Notifications'], $passwordResetMail->from);
        $this->assertEmpty($passwordResetMail->replyTo);
    }
}
