<?php

namespace App\Console\Commands;

use App\Mail\Mt5OnboardingSetupMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMt5OnboardingEmail extends Command
{
    protected $signature = 'wolforix:send-mt5-onboarding-email {email}';

    protected $description = 'Send the Wolforix MT5 connector onboarding/setup instructions email.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        Mail::to($email)->send(new Mt5OnboardingSetupMail());

        Log::info('Wolforix MT5 onboarding email sent.', [
            'email' => $email,
            'template' => Mt5OnboardingSetupMail::class,
        ]);

        $this->info('Wolforix MT5 onboarding email sent to: '.$email);

        return self::SUCCESS;
    }
}
