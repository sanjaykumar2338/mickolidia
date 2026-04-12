<?php

namespace App\Mail;

use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhaseOnePassedMail extends Mailable
{
    use Queueable, SerializesModels;

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
        return new Envelope(
            subject: 'You’ve Passed Phase 1 — 2-Step Pro',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.phase-one-passed',
        );
    }
}
