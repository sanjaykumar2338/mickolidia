<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Mt5OnboardingSetupMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public string $dashboardUrl = 'https://www.wolforix.com/dashboard';

    public string $setupUrl = 'https://www.wolforix.com/mt5/setup';

    public string $setupVideoUrl = 'https://www.wolforix.com/mt5_demo.mp4';

    public string $supportEmail;

    public function __construct()
    {
        $this->supportEmail = (string) config('wolforix.support.email', 'support@wolforix.com');
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Connect Your MT5 Account to Wolforix Dashboard');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mt5-onboarding-setup',
            text: 'emails.mt5-onboarding-setup-text',
        );
    }
}
