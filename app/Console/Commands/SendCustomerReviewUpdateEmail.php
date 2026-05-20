<?php

namespace App\Console\Commands;

use App\Mail\CustomerReviewUpdateMail;
use App\Models\TradingAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCustomerReviewUpdateEmail extends Command
{
    private const CUSTOMER_EMAIL = 'josublen457@gmail.com';

    private const CUSTOMER_NAME = 'Josué Andrés Agüero Franco';

    private const ACCOUNT_REFERENCE = 'WFX-MT5-00057-8HN7';

    private const DISCOUNT_CODE = 'WOLF50HQ';

    protected $signature = 'wolforix:send-customer-review-update-email
        {email : Customer email address}
        {--dry-run : Preview the email without sending}
        {--send : Send the email}';

    protected $description = 'Dry-run first customer account review update email for the verified MT5 mapping repair case.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $send = (bool) $this->option('send');
        $dryRun = (bool) $this->option('dry-run') || ! $send;

        if ($send && (bool) $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --send, not both.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        if ($email !== self::CUSTOMER_EMAIL) {
            $this->error('Refusing to continue: this email command is locked to '.self::CUSTOMER_EMAIL.'.');

            return self::FAILURE;
        }

        $account = TradingAccount::query()
            ->with('user')
            ->where('account_reference', self::ACCOUNT_REFERENCE)
            ->first();

        if (! $account instanceof TradingAccount) {
            $this->error('Refusing to continue: account '.self::ACCOUNT_REFERENCE.' was not found.');

            return self::FAILURE;
        }

        $supportEmail = trim((string) config('wolforix.support.email', 'support@wolforix.com'));
        $cc = $this->ccRecipients($email, $supportEmail);
        $mail = new CustomerReviewUpdateMail(
            customerName: self::CUSTOMER_NAME,
            accountReference: self::ACCOUNT_REFERENCE,
            discountCode: self::DISCOUNT_CODE,
        );

        $this->table(['Field', 'Value'], [
            ['mode', $dryRun ? 'dry-run' : 'send'],
            ['to', $email],
            ['cc', $cc === [] ? '-' : implode(', ', $cc)],
            ['subject', 'Update Regarding Your Wolforix Account Review'],
            ['customer_name', self::CUSTOMER_NAME],
            ['account_reference', self::ACCOUNT_REFERENCE],
            ['trading_account_id', (string) $account->id],
            ['account_status', (string) ($account->account_status ?: $account->status ?: '-')],
            ['challenge_status', (string) ($account->challenge_status ?: '-')],
            ['platform_status', (string) ($account->platform_status ?: '-')],
        ]);

        $this->newLine();
        $this->info('Email preview');
        foreach ($this->previewLines() as $line) {
            $this->line('- '.$line);
        }

        if ($dryRun) {
            $html = $mail->render();
            $this->newLine();
            $this->warn('DRY RUN ONLY — no email was sent.');
            $this->line('Rendered Wolforix template preview length: '.strlen($html).' bytes');

            return self::SUCCESS;
        }

        $pendingMail = Mail::to($email);
        if ($cc !== []) {
            $pendingMail->cc($cc);
        }
        $pendingMail->send($mail);

        Log::info('Wolforix customer account review update email sent.', [
            'email' => $email,
            'cc' => $cc,
            'account_reference' => self::ACCOUNT_REFERENCE,
            'trading_account_id' => $account->id,
            'template' => CustomerReviewUpdateMail::class,
        ]);

        $this->info('Customer review/update email sent to: '.$email.($cc === [] ? '' : '; CC: '.implode(', ', $cc)));
        $this->line('No account status, challenge status, platform status, pass/fail state, or trading data was changed.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function ccRecipients(string $recipientEmail, string $supportEmail): array
    {
        if (! filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        if (strtolower($supportEmail) === strtolower($recipientEmail)) {
            return [];
        }

        return [$supportEmail];
    }

    /**
     * @return list<string>
     */
    private function previewLines(): array
    {
        return [
            'Thank the customer for their patience.',
            'Confirm that an internal technical review was completed.',
            'Explain that an MT5 synchronization/mapping issue was identified and corrected.',
            'Clarify that broker-side trading data was not affected.',
            'State that the case remains under internal review for trade-duration/scalping-rule validation.',
            'Acknowledge the frustration caused by technical issues in a calm, non-blaming tone.',
            'Offer goodwill support discount code '.self::DISCOUNT_CODE.' for 50% if the client decides to continue in the future.',
        ];
    }
}
