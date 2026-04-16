<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhaseTwoAccountDetailsMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $traderName,
        public TradingAccount $tradingAccount,
        public array $details,
    ) {
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Your Phase 2 Account Details — Wolforix 2-Step Pro');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.phase-two-account-details',
        );
    }
}
