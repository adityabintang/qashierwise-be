<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendPasswordResetLinkNotification extends Notification
{
    use Queueable;

    protected string $resetUrl;

    protected int $expirationMinutes;

    public function __construct(string $token, int $expirationMinutes = 60)
    {
        $this->resetUrl = config('app.url').'/reset-password?token='.$token;
        $this->expirationMinutes = $expirationMinutes;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password')
            ->markdown('emails.password-reset', [
                'resetUrl' => $this->resetUrl,
                'expirationMinutes' => $this->expirationMinutes,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'reset_url' => $this->resetUrl,
        ];
    }
}
