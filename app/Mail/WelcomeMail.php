<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Welcome to Wolforix | Your account is active');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }
}
