<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected WithdrawalRequest $withdrawalRequest;
    protected string $previousStatus;

    /**
     * Create a new notification instance.
     */
    public function __construct(WithdrawalRequest $withdrawalRequest, string $previousStatus)
    {
        $this->withdrawalRequest = $withdrawalRequest;
        $this->previousStatus = $previousStatus;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->withdrawalRequest->amount, 0, ',', '.');
        $status = ucfirst($this->withdrawalRequest->status);

        $mailMessage = (new MailMessage)
            ->subject("Withdrawal Request {$status}")
            ->greeting("Hello {$notifiable->name},");

        switch ($this->withdrawalRequest->status) {
            case WithdrawalRequest::STATUS_APPROVED:
                $mailMessage
                    ->line('Great news! Your withdrawal request has been approved.')
                    ->line("**Amount:** Rp {$amount}")
                    ->line('The funds will be transferred to your registered bank account shortly.')
                    ->line("**Bank:** {$this->withdrawalRequest->getBankName()}")
                    ->line("**Account:** {$this->withdrawalRequest->getAccountNumber()}");
                
                if ($this->withdrawalRequest->admin_notes) {
                    $mailMessage->line("**Notes:** {$this->withdrawalRequest->admin_notes}");
                }
                break;

            case WithdrawalRequest::STATUS_REJECTED:
                $mailMessage
                    ->line('Unfortunately, your withdrawal request has been rejected.')
                    ->line("**Amount:** Rp {$amount}")
                    ->line("**Reason:** {$this->withdrawalRequest->admin_notes}")
                    ->line('The amount has been returned to your available balance.')
                    ->line('Please review the rejection reason and submit a new request if needed.');
                break;

            case WithdrawalRequest::STATUS_PROCESSED:
                $mailMessage
                    ->line('Your withdrawal has been processed successfully!')
                    ->line("**Amount:** Rp {$amount}")
                    ->line('The funds have been transferred to your bank account.')
                    ->line("**Bank:** {$this->withdrawalRequest->getBankName()}")
                    ->line("**Account:** {$this->withdrawalRequest->getAccountNumber()}")
                    ->line('Please allow 1-3 business days for the transfer to reflect in your account.');
                break;

            default:
                $mailMessage
                    ->line("Your withdrawal request status has been updated to: {$status}")
                    ->line("**Amount:** Rp {$amount}");
        }

        return $mailMessage
            ->action('View Withdrawal History', url('/dashboard/withdrawals'))
            ->line('Thank you for using our platform!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $statusMessages = [
            WithdrawalRequest::STATUS_APPROVED => 'Your withdrawal request has been approved',
            WithdrawalRequest::STATUS_REJECTED => 'Your withdrawal request has been rejected',
            WithdrawalRequest::STATUS_PROCESSED => 'Your withdrawal has been processed',
        ];

        return [
            'type' => 'withdrawal_status_changed',
            'withdrawal_id' => $this->withdrawalRequest->id,
            'amount' => (float) $this->withdrawalRequest->amount,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->withdrawalRequest->status,
            'admin_notes' => $this->withdrawalRequest->admin_notes,
            'processed_at' => $this->withdrawalRequest->processed_at?->toIso8601String(),
            'message' => $statusMessages[$this->withdrawalRequest->status] ?? 'Withdrawal status updated',
        ];
    }
}
