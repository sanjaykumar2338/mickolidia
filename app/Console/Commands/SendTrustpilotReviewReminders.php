<?php

namespace App\Console\Commands;

use App\Services\Reviews\TrustpilotReviewRequestMailer;
use Illuminate\Console\Command;

class SendTrustpilotReviewReminders extends Command
{
    protected $signature = 'reviews:send-trustpilot-reminders {--limit=100 : Maximum accounts to inspect per run}';

    protected $description = 'Send due Trustpilot review request reminder emails.';

    public function handle(TrustpilotReviewRequestMailer $mailer): int
    {
        $sent = $mailer->sendDueReminders((int) $this->option('limit'));

        $this->info("Trustpilot review reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
