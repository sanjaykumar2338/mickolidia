<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

trait UsesAutomatedSender
{
    protected function automatedEnvelope(string $subject): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.automated_from.address'),
                (string) config('mail.automated_from.name'),
            ),
            subject: $subject,
        );
    }
}
