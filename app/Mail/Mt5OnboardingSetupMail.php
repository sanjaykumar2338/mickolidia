<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAutomatedSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Mt5OnboardingSetupMail extends Mailable
{
    use Queueable, SerializesModels;
    use UsesAutomatedSender;

    public string $dashboardUrl = 'https://www.wolforix.com/dashboard';

    public string $setupUrl = 'https://www.wolforix.com/mt5/setup';

    public string $setupVideoUrl = 'https://www.wolforix.com/mt5_demo.mp4';

    public string $connectorDownloadUrl;

    public string $connectorReleaseLabel;

    public string $supportEmail;

    public function __construct()
    {
        $this->supportEmail = (string) config('wolforix.support.email', 'support@wolforix.com');
        $this->connectorDownloadUrl = 'https://www.wolforix.com/'.ltrim((string) config('wolforix.mt5_connector.download_path', 'mt5software/wolforix-mt5-connector.zip'), '/');
        $this->connectorReleaseLabel = (string) config('wolforix.mt5_connector.release_label', 'latest recovery build');
    }

    public function envelope(): Envelope
    {
        return $this->automatedEnvelope('Action Required: Install the Updated Wolforix MT5 Connector');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mt5-onboarding-setup',
            text: 'emails.mt5-onboarding-setup-text',
        );
    }
}
