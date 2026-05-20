<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerReviewUpdateMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public function __construct(
        public string $customerName,
        public string $accountReference,
        public string $discountCode = 'WOLF50HQ',
    ) {
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Update Regarding Your Wolforix Account Review');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-review-update',
        );
    }
}
