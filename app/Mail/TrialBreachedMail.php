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

class TrialBreachedMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public function __construct(
        public User $user,
        public TradingAccount $tradingAccount,
        public string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Wolforix Free Trial Update | Trial Rules Breached');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-breached',
        );
    }
}
