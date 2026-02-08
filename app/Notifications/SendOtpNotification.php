<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    use Queueable;

    protected string $otp;

    protected string $type;

    protected int $expirationMinutes;

    public function __construct(string $otp, string $type, int $expirationMinutes = 10)
    {
        $this->otp = $otp;
        $this->type = $type;
        $this->expirationMinutes = $expirationMinutes;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = $this->type === 'email_verification'
            ? 'Verify Your Email Address'
            : 'Password Reset Code';

        $intro = $this->type === 'email_verification'
            ? 'Thank you for registering with QashierWise. Please use the verification code below to complete your email verification:'
            : 'We received a request to reset your password. Please use the verification code below:';

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.otp', [
                'otp' => $this->otp,
                'type' => $this->type,
                'expirationMinutes' => $this->expirationMinutes,
                'intro' => $intro,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'otp' => $this->otp,
            'type' => $this->type,
        ];
    }
}
