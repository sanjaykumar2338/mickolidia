<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChallengeAccountDetailsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $traderName,
        public TradingAccount $tradingAccount,
        public ?Order $order,
        public array $details,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Challenge Account Details — Wolforix',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.challenge-account-details',
        );
    }
}
