<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionPaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $orderId,
        private string $planName,
        private string $durationName,
        private float $amount,
        private string $currency,
        private string $transactionStatus,
        private ?string $transactionId,
        private ?Carbon $transactionTime,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $formattedAmount = number_format($this->amount, 0, ',', '.');
        $statusLabel = $this->formatStatus($this->transactionStatus);
        $transactionTime = $this->transactionTime?->toIso8601String() ?? '-';

        return (new MailMessage)
            ->subject("Receipt - {$this->planName}")
            ->greeting("Hello {$notifiable->name},")
            ->line('Your subscription payment has been received.')
            ->line("**Status:** {$statusLabel}")
            ->line("**Amount:** {$this->currency} {$formattedAmount}")
            ->line("**Order ID:** {$this->orderId}")
            ->line("**Plan:** {$this->planName}")
            ->line("**Duration:** {$this->durationName}")
            ->line("**Transaction ID:** {$this->transactionId}")
            ->line("**Transaction Time:** {$transactionTime}")
            ->line('Thank you for subscribing to QashierWise.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_payment_receipt',
            'order_id' => $this->orderId,
            'plan_name' => $this->planName,
            'duration_name' => $this->durationName,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_status' => $this->transactionStatus,
            'transaction_id' => $this->transactionId,
            'transaction_time' => $this->transactionTime?->toIso8601String(),
        ];
    }

    private function formatStatus(string $transactionStatus): string
    {
        $status = strtolower($transactionStatus);

        return match ($status) {
            'capture' => 'Capture',
            'settlement' => 'Settlement',
            default => ucfirst($status),
        };
    }
}
