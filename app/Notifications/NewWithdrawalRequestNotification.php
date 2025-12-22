<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewWithdrawalRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected WithdrawalRequest $withdrawalRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        $this->withdrawalRequest = $withdrawalRequest;
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
        $merchant = $this->withdrawalRequest->subMerchant;
        $amount = number_format($this->withdrawalRequest->amount, 0, ',', '.');

        return (new MailMessage)
            ->subject('New Withdrawal Request - Action Required')
            ->greeting('Hello Admin,')
            ->line('A new withdrawal request has been submitted and requires your review.')
            ->line("**Merchant:** {$merchant->user->name}")
            ->line("**Amount:** Rp {$amount}")
            ->line("**Bank:** {$this->withdrawalRequest->getBankName()}")
            ->line("**Account:** {$this->withdrawalRequest->getAccountNumber()}")
            ->line("**Account Holder:** {$this->withdrawalRequest->getAccountHolderName()}")
            ->action('Review Request', url('/admin/withdrawals/' . $this->withdrawalRequest->id))
            ->line('Please review and process this request at your earliest convenience.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_withdrawal_request',
            'withdrawal_id' => $this->withdrawalRequest->id,
            'sub_merchant_id' => $this->withdrawalRequest->sub_merchant_id,
            'merchant_name' => $this->withdrawalRequest->subMerchant->user->name ?? 'Unknown',
            'amount' => (float) $this->withdrawalRequest->amount,
            'bank_name' => $this->withdrawalRequest->getBankName(),
            'created_at' => $this->withdrawalRequest->created_at->toIso8601String(),
            'message' => 'New withdrawal request of Rp ' . number_format($this->withdrawalRequest->amount, 0, ',', '.'),
        ];
    }
}
