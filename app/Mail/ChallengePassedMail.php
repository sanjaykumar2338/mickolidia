<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChallengePassedMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    /**
     * @param  array<string, string>  $details
     * @param  array{disk:string,path:string,name:string,absolute_path:string}|null  $certificate
     */
    public function __construct(
        public User $user,
        public TradingAccount $tradingAccount,
        public array $details,
        public ?array $certificate = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Congratulations — You’ve Passed the Evaluation');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.challenge-passed',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->certificate === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk($this->certificate['disk'], $this->certificate['path'])
                ->as($this->certificate['name'])
                ->withMime('image/png'),
        ];
    }
}
