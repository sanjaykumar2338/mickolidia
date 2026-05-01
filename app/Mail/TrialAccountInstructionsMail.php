<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialAccountInstructionsMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public string $demoRegistrationUrl = 'https://www.icmarkets.eu/de/open-trading-account/demo';

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Your Wolforix Trial Account – Get Started');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-account-instructions',
        );
    }
}
