<?php

namespace App\Mail;

use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsistencyAlertMail extends Mailable
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
        $subject = $this->details['status'] === 'breach'
            ? 'Consistency Rule Alert - Threshold Reached'
            : 'Consistency Rule Alert - Approaching Threshold';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consistency-alert',
        );
    }
}
