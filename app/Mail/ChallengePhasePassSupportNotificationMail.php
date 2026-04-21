<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChallengePhasePassSupportNotificationMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

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
        return $this->automatedEnvelope('Support Alert — Challenge Final State');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.challenge-phase-pass-support-notification',
        );
    }
}
