<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrustpilotReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public function __construct(
        public string $traderName,
        public string $reviewUrl,
        public bool $reminder = false,
    ) {
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope($this->reminder
            ? 'Reminder: Share your Wolforix experience'
            : 'Share your Wolforix experience');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trustpilot-review-request',
        );
    }
}
