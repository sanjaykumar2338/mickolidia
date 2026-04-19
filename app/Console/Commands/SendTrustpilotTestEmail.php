<?php

namespace App\Console\Commands;

use App\Mail\TrustpilotReviewRequestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTrustpilotTestEmail extends Command
{
    protected $signature = 'reviews:test {email}';

    protected $description = 'Send Trustpilot review email for testing';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        Mail::to($email)->send(new TrustpilotReviewRequestMail(
            traderName: 'Test Trader',
            reviewUrl: (string) config('wolforix.review_requests.trustpilot.url'),
        ));

        $this->info('Trustpilot test email sent to: '.$email);

        return self::SUCCESS;
    }
}
