<?php

namespace Tests\Feature;

use App\Mail\ChallengeAccountDetailsMail;
use App\Mail\ChallengeFailedMail;
use App\Mail\ChallengePassedMail;
use App\Mail\ChallengePurchaseConfirmationMail;
use App\Mail\ChallengePurchaseSupportNotificationMail;
use App\Mail\ConsistencyAlertMail;
use App\Mail\PhaseOnePassedMail;
use App\Mail\PhaseTwoAccountDetailsMail;
use App\Mail\TrialBreachedMail;
use App\Mail\TrialPassedMail;
use App\Mail\TrustpilotReviewRequestMail;
use App\Mail\WelcomeMail;
use App\Models\ChallengePurchase;
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
        $user->id = 42;

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
                'investor_password' => 'investor-secret-pass',
                'server' => 'Wolforix-Demo',
                'account_type' => '1-Step Instant',
                'account_size' => '5K',
            ]),
            new ChallengePurchaseSupportNotificationMail($user, $order, $account, [
                'client_name' => 'Mail Trader',
                'client_email' => 'mail-trader@example.com',
                'purchased_plan' => 'Wolforix Challenge - 1-Step Instant $5,000.00',
                'account_size' => '$5,000.00',
                'order_number' => 'WFX-ORD-TEST-001',
                'payment_reference' => 'pi_test_123',
                'mt5_login' => '105381073',
                'broker' => 'FusionMarkets',
                'mt5_server' => 'FusionMarkets-Demo',
                'account_reference' => 'WFX-CT-TEST-001',
                'purchased_at' => '2026-04-27 12:00:00',
                'remaining_same_size' => '4',
                'remaining_total' => '9',
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
                'investor_password' => 'phase-two-investor-pass',
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
            new TrustpilotReviewRequestMail('Mail Trader', 'https://de.trustpilot.com/review/wolforix.com'),
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

    public function test_purchase_confirmation_mail_renders_account_login_details_when_available(): void
    {
        $order = new Order([
            'order_number' => 'WFX-ORD-TEST-LOGIN-001',
            'email' => 'mail-trader@example.com',
            'full_name' => 'Mail Trader',
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'usd',
            'final_price' => 99,
            'payment_provider' => 'stripe',
        ]);

        $purchase = new ChallengePurchase([
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'currency' => 'usd',
        ]);

        $account = new TradingAccount([
            'account_reference' => 'WFX-MT5-LOGIN-001',
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '105381073',
            'platform_account_id' => '105381073',
            'platform_environment' => 'demo',
            'meta' => [
                'credentials' => [
                    'server' => 'Wolforix-Demo',
                    'password' => 'secret-pass',
                    'investor_password' => 'investor-secret-pass',
                ],
            ],
        ]);

        $purchase->setRelation('tradingAccounts', collect([$account]));
        $order->setRelation('challengePurchase', $purchase);

        $mail = new ChallengePurchaseConfirmationMail($order);
        $html = $mail->render();

        $this->assertTrue($mail->credentialsReady);
        $this->assertSame('WFX-MT5-LOGIN-001', $mail->accountReference);
        $this->assertSame('105381073', $mail->accountAccessDetails['login_id']);
        $this->assertSame('investor-secret-pass', $mail->accountAccessDetails['investor_password']);
        $this->assertStringContainsString('Account Access Details', $html);
        $this->assertStringContainsString('105381073', $html);
        $this->assertStringContainsString('Wolforix-Demo', $html);
        $this->assertStringContainsString('secret-pass', $html);
        $this->assertStringContainsString('investor-secret-pass', $html);
    }
}
