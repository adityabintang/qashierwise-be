<?php

namespace App\Services;

use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Notifications\NewWithdrawalRequestNotification;
use App\Notifications\WithdrawalStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class WithdrawalNotificationService
{
    /**
     * Notify admins about a new withdrawal request.
     *
     * @param WithdrawalRequest $withdrawalRequest The new withdrawal request
     * @return void
     */
    public function notifyAdminsOfNewRequest(WithdrawalRequest $withdrawalRequest): void
    {
        $admins = $this->getAdminUsers();

        if ($admins->isEmpty()) {
            Log::warning('No admin users found to notify about withdrawal request', [
                'withdrawal_id' => $withdrawalRequest->id,
            ]);
            return;
        }

        Notification::send($admins, new NewWithdrawalRequestNotification($withdrawalRequest));

        Log::info('Admin notification sent for new withdrawal request', [
            'withdrawal_id' => $withdrawalRequest->id,
            'admin_count' => $admins->count(),
        ]);
    }

    /**
     * Notify the sub-merchant about withdrawal status change.
     *
     * @param WithdrawalRequest $withdrawalRequest The withdrawal request
     * @param string $previousStatus The previous status before the change
     * @return void
     */
    public function notifyMerchantOfStatusChange(WithdrawalRequest $withdrawalRequest, string $previousStatus): void
    {
        $merchant = $withdrawalRequest->subMerchant;
        
        if ($merchant === null || $merchant->user === null) {
            Log::warning('Cannot notify merchant - no user found', [
                'withdrawal_id' => $withdrawalRequest->id,
            ]);
            return;
        }

        $user = $merchant->user;
        $user->notify(new WithdrawalStatusChangedNotification($withdrawalRequest, $previousStatus));

        Log::info('Merchant notification sent for withdrawal status change', [
            'withdrawal_id' => $withdrawalRequest->id,
            'user_id' => $user->id,
            'previous_status' => $previousStatus,
            'new_status' => $withdrawalRequest->status,
        ]);
    }

    /**
     * Send notification when withdrawal is approved.
     *
     * @param WithdrawalRequest $withdrawalRequest The approved withdrawal request
     * @return void
     */
    public function notifyWithdrawalApproved(WithdrawalRequest $withdrawalRequest): void
    {
        $this->notifyMerchantOfStatusChange($withdrawalRequest, WithdrawalRequest::STATUS_PENDING);
    }

    /**
     * Send notification when withdrawal is rejected.
     *
     * @param WithdrawalRequest $withdrawalRequest The rejected withdrawal request
     * @return void
     */
    public function notifyWithdrawalRejected(WithdrawalRequest $withdrawalRequest): void
    {
        $this->notifyMerchantOfStatusChange($withdrawalRequest, WithdrawalRequest::STATUS_PENDING);
    }

    /**
     * Send notification when withdrawal is processed.
     *
     * @param WithdrawalRequest $withdrawalRequest The processed withdrawal request
     * @return void
     */
    public function notifyWithdrawalProcessed(WithdrawalRequest $withdrawalRequest): void
    {
        $this->notifyMerchantOfStatusChange($withdrawalRequest, WithdrawalRequest::STATUS_APPROVED);
    }

    /**
     * Get all admin users who should receive withdrawal notifications.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getAdminUsers(): \Illuminate\Database\Eloquent\Collection
    {
        // Get users who are admins - this can be customized based on your admin logic
        // For now, we'll check for users with 'admin' in their email or a specific role
        // You may want to add an 'is_admin' column or use a roles system
        return User::where(function ($query) {
            $query->where('email', 'like', '%admin%')
                  ->orWhere('email', config('app.admin_email'));
        })->get();
    }

    /**
     * Send all notifications for a newly created withdrawal request.
     *
     * @param WithdrawalRequest $withdrawalRequest The new withdrawal request
     * @return void
     */
    public function sendNewRequestNotifications(WithdrawalRequest $withdrawalRequest): void
    {
        $this->notifyAdminsOfNewRequest($withdrawalRequest);
    }

    /**
     * Send all notifications for an approved withdrawal.
     *
     * @param WithdrawalRequest $withdrawalRequest The approved withdrawal request
     * @return void
     */
    public function sendApprovalNotifications(WithdrawalRequest $withdrawalRequest): void
    {
        $this->notifyWithdrawalApproved($withdrawalRequest);
    }

    /**
     * Send all notifications for a rejected withdrawal.
     *
     * @param WithdrawalRequest $withdrawalRequest The rejected withdrawal request
     * @return void
     */
    public function sendRejectionNotifications(WithdrawalRequest $withdrawalRequest): void
    {
        $this->notifyWithdrawalRejected($withdrawalRequest);
    }

    /**
     * Send all notifications for a processed withdrawal.
     *
     * @param WithdrawalRequest $withdrawalRequest The processed withdrawal request
     * @return void
     */
    public function sendProcessedNotifications(WithdrawalRequest $withdrawalRequest): void
    {
        $this->notifyWithdrawalProcessed($withdrawalRequest);
    }
}
