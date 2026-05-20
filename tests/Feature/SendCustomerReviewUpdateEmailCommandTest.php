<?php

namespace Tests\Feature;

use App\Mail\CustomerReviewUpdateMail;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCustomerReviewUpdateEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_review_update_email_dry_run_does_not_send(): void
    {
        Mail::fake();

        $account = $this->createCustomerAccount();
        $before = $this->statusSnapshot($account->fresh());

        $exitCode = Artisan::call('wolforix:send-customer-review-update-email', [
            'email' => 'josublen457@gmail.com',
            '--dry-run' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('mode', $output);
        $this->assertStringContainsString('dry-run', $output);
        $this->assertStringContainsString('Email preview', $output);
        $this->assertStringContainsString('DRY RUN ONLY', $output);
        $this->assertStringContainsString('WOLF50HQ', $output);
        Mail::assertNothingSent();
        $this->assertSame($before, $this->statusSnapshot($account->fresh()));
    }

    public function test_customer_review_update_email_send_sends_one_email_and_preserves_account_status(): void
    {
        Mail::fake();

        config()->set('wolforix.support.email', 'support@wolforix.com');
        $account = $this->createCustomerAccount();
        $before = $this->statusSnapshot($account->fresh());

        $exitCode = Artisan::call('wolforix:send-customer-review-update-email', [
            'email' => 'josublen457@gmail.com',
            '--send' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Customer review/update email sent to: josublen457@gmail.com', $output);
        $this->assertStringContainsString('No account status, challenge status, platform status, pass/fail state, or trading data was changed.', $output);
        Mail::assertSent(CustomerReviewUpdateMail::class, 1);
        Mail::assertSent(CustomerReviewUpdateMail::class, function (CustomerReviewUpdateMail $mail): bool {
            return $mail->hasTo('josublen457@gmail.com')
                && $mail->hasCc('support@wolforix.com')
                && $mail->envelope()->subject === 'Update Regarding Your Wolforix Account Review';
        });

        $this->assertSame($before, $this->statusSnapshot($account->fresh()));
    }

    public function test_customer_review_update_email_uses_wolforix_template_and_required_copy(): void
    {
        $mail = new CustomerReviewUpdateMail(
            customerName: 'Josué Andrés Agüero Franco',
            accountReference: 'WFX-MT5-00057-8HN7',
            discountCode: 'WOLF50HQ',
        );

        $html = $mail->render();

        $this->assertStringContainsString('background-color:#050b13', $html);
        $this->assertStringContainsString('Trade Fearlessly. Win Real.', $html);
        $this->assertStringContainsString('Update Regarding Your Wolforix Account Review', $html);
        $this->assertStringContainsString('Josué Andrés Agüero Franco', $html);
        $this->assertStringContainsString('WFX-MT5-00057-8HN7', $html);
        $this->assertStringContainsString('MT5 synchronization and mapping issue was identified and corrected', $html);
        $this->assertStringContainsString('broker-side trading data', $html);
        $this->assertStringContainsString('trade-duration rule validation', $html);
        $this->assertStringContainsString('WOLF50HQ', $html);
        $this->assertStringContainsString('50% support discount', $html);
    }

    private function createCustomerAccount(): TradingAccount
    {
        $user = User::factory()->create([
            'name' => 'Josué Andrés Agüero Franco',
            'email' => 'josublen457@gmail.com',
        ]);

        return TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-MT5-00057-8HN7',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335374',
            'platform_account_id' => '335374',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'waiting_for_first_sync',
            'status' => 'Active',
            'account_status' => 'active',
            'challenge_status' => 'active',
            'account_type' => 'challenge',
            'is_trial' => false,
            'challenge_type' => 'one_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'failed_at' => null,
            'failure_reason' => null,
            'trading_blocked' => false,
            'final_state_locked' => false,
            'rule_state' => [
                'daily_drawdown_breached' => false,
            ],
            'meta' => [
                'mt5_sync' => [
                    'identifier' => '335374',
                    'status' => 'waiting_for_first_sync',
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusSnapshot(TradingAccount $account): array
    {
        return [
            'account_status' => $account->account_status,
            'status' => $account->status,
            'challenge_status' => $account->challenge_status,
            'platform_status' => $account->platform_status,
            'sync_status' => $account->sync_status,
            'failed_at' => $account->failed_at,
            'failure_reason' => $account->failure_reason,
            'trading_blocked' => (bool) $account->trading_blocked,
            'final_state_locked' => (bool) $account->final_state_locked,
            'rule_state' => $account->rule_state,
        ];
    }
}
