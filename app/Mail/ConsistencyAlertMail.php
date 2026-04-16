<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsistencyAlertMail extends Mailable
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
        $subject = $this->details['status'] === 'breach'
            ? 'Consistency Rule Alert - Threshold Reached'
            : 'Consistency Rule Alert - Approaching Threshold';

        return $this->automatedEnvelope($subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consistency-alert',
        );
    }
}
