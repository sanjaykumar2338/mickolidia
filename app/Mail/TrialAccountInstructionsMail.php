<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TrialAccountInstructionsMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public string $demoRegistrationUrl;

    public function __construct(public User $user)
    {
        $this->demoRegistrationUrl = (string) config('wolforix.trial.demo_registration_url', 'https://www.icmarkets.eu/de/open-trading-account/demo');
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Your Wolforix Trial Account – Get Started');
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Priority' => '1 (Highest)',
                'X-MSMail-Priority' => 'High',
                'Importance' => 'High',
                'Priority' => 'urgent',
                'X-Wolforix-Send-Mode' => 'delayed-trial-setup',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-account-instructions',
        );
    }
}
