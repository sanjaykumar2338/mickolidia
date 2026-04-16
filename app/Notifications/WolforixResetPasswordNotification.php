<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WolforixResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $passwordBroker = (string) config('auth.defaults.passwords', 'users');
        $expireMinutes = (int) config("auth.passwords.{$passwordBroker}.expire", 60);

        return (new MailMessage)
            ->from(
                (string) config('mail.automated_from.address'),
                (string) config('mail.automated_from.name'),
            )
            ->subject('Reset your Wolforix password')
            ->view('emails.password-reset', [
                'user' => $notifiable,
                'actionUrl' => route('password.reset', [
                    'token' => $this->token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]),
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
