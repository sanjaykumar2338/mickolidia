<?php

namespace App\Mail;

use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChallengeFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public User $user,
        public TradingAccount $tradingAccount,
        public array $details,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Status Update – Challenge Failed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.challenge-failed',
        );
    }
}
