<?php

namespace App\Services;

use App\DTOs\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing subscription business logic.
 *
 * Handles subscription status detection, webhook event processing,
 * trial period management, and feature access control.
 */
class SubscriptionService
{
    /**
     * Trial period duration in days.
     */
    private const TRIAL_DAYS = 14;

    /**
     * Subscription status constants.
     */
    public const STATUS_TRIAL = 'trial';

    public const STATUS_TRIAL_EXPIRED = 'trial_expired';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    private PlanConfig $planConfig;

    public function __construct(PlanConfig $planConfig)
    {
        $this->planConfig = $planConfig;
    }

    /**
     * Get the subscription status for a user.
     *
     * CRITICAL: Uses getEffectiveSubscription() to ensure POS users inherit
     * subscription from their master admin. This enforces that all users
     * under a merchant share the same subscription tier.
     *
     * @param  User  $user  The user to check
     */
    public function getUserSubscriptionStatus(User $user): SubscriptionStatus
    {
        // CRITICAL: Use effective subscription to support inheritance
        // POS users inherit subscription from their master admin
        $subscription = $user->getEffectiveSubscription();

        Log::info('Getting subscription status', [
            'user_id' => $user->id,
            'email' => $user->email,
            'is_master_admin' => $user->isMasterAdmin(),
            'subscription_id' => $subscription?->id,
            'plan_name' => $subscription?->plan_name,
            'inherited' => $user->subscription === null && $subscription !== null,
        ]);

        // If user has no subscription (even inherited), check trial status
        if ($subscription === null) {
            return $this->getTrialStatus($user);
        }

        // If subscription is cancelled but still within period
        if ($subscription->isCancelled() && ! $subscription->isExpired()) {
            return new SubscriptionStatus(
                status: self::STATUS_CANCELLED,
                planName: $subscription->plan_name,
                trialDaysRemaining: null,
                periodEnd: $subscription->current_period_end,
                cancelledAt: $subscription->cancelled_at,
            );
        }

        // If subscription is expired
        if ($subscription->isExpired()) {
            return new SubscriptionStatus(
                status: self::STATUS_EXPIRED,
                planName: $subscription->plan_name,
                trialDaysRemaining: null,
                periodEnd: $subscription->current_period_end,
                cancelledAt: $subscription->cancelled_at,
            );
        }

        // Active subscription
        if ($subscription->isActive()) {
            return new SubscriptionStatus(
                status: self::STATUS_ACTIVE,
                planName: $subscription->plan_name,
                trialDaysRemaining: null,
                periodEnd: $subscription->current_period_end,
                cancelledAt: null,
            );
        }

        // Fallback to expired status
        return new SubscriptionStatus(
            status: self::STATUS_EXPIRED,
            planName: $subscription->plan_name,
            trialDaysRemaining: null,
            periodEnd: $subscription->current_period_end,
            cancelledAt: $subscription->cancelled_at,
        );
    }

    /**
     * Get trial status for a user without a subscription.
     *
     * @param  User  $user  The user to check
     */
    private function getTrialStatus(User $user): SubscriptionStatus
    {
        $trialDaysRemaining = $this->calculateTrialDaysRemaining($user);

        if ($trialDaysRemaining <= 0) {
            return new SubscriptionStatus(
                status: self::STATUS_TRIAL_EXPIRED,
                planName: PlanConfig::PLAN_FREE_TRIAL,
                trialDaysRemaining: 0,
                periodEnd: null,
                cancelledAt: null,
            );
        }

        return new SubscriptionStatus(
            status: self::STATUS_TRIAL,
            planName: PlanConfig::PLAN_FREE_TRIAL,
            trialDaysRemaining: $trialDaysRemaining,
            periodEnd: null,
            cancelledAt: null,
        );
    }

    /**
     * Process a webhook event from Midtrans.
     *
     * @param  array  $payload  The webhook payload data
     */
    public function processMidtransWebhook(array $payload): void
    {
        $transactionStatus = $payload['transaction_status'] ?? null;
        $subscriptionId = $payload['subscription_id'] ?? null;
        $orderId = $payload['order_id'] ?? null;

        if ($transactionStatus === null) {
            Log::warning('Midtrans webhook missing transaction_status', ['payload' => $payload]);

            return;
        }

        Log::info('Processing Midtrans webhook', [
            'transaction_status' => $transactionStatus,
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
        ]);

        // Handle different transaction statuses
        match ($transactionStatus) {
            'capture', 'settlement' => $this->handleMidtransPaymentSuccess($payload),
            'pending' => $this->handleMidtransPaymentPending($payload),
            'deny', 'cancel', 'expire' => $this->handleMidtransPaymentFailed($payload),
            default => Log::info('Unhandled Midtrans transaction status', ['status' => $transactionStatus]),
        };
    }

    /**
     * Handle successful Midtrans payment.
     *
     * @param  array  $payload  The webhook payload
     */
    private function handleMidtransPaymentSuccess(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? null;
        $orderId = $payload['order_id'] ?? null;

        // If this is a subscription payment
        if ($subscriptionId !== null) {
            $this->updateFromMidtransWebhook($subscriptionId, [
                'status' => 'active',
                'transaction_status' => $payload['transaction_status'],
                'transaction_id' => $payload['transaction_id'] ?? null,
                'transaction_time' => $payload['transaction_time'] ?? null,
            ]);
        } else {
            Log::info('Midtrans payment success without subscription_id', ['order_id' => $orderId]);
        }
    }

    /**
     * Handle pending Midtrans payment.
     *
     * @param  array  $payload  The webhook payload
     */
    private function handleMidtransPaymentPending(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? null;

        if ($subscriptionId !== null) {
            Log::info('Midtrans payment pending', [
                'subscription_id' => $subscriptionId,
                'order_id' => $payload['order_id'] ?? null,
            ]);
        }
    }

    /**
     * Handle failed Midtrans payment.
     *
     * @param  array  $payload  The webhook payload
     */
    private function handleMidtransPaymentFailed(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? 'failed';

        if ($subscriptionId !== null) {
            Log::warning('Midtrans payment failed', [
                'subscription_id' => $subscriptionId,
                'transaction_status' => $transactionStatus,
                'order_id' => $payload['order_id'] ?? null,
            ]);

            // Update subscription status if payment failed
            $this->updateFromMidtransWebhook($subscriptionId, [
                'status' => 'expired',
                'transaction_status' => $transactionStatus,
            ]);
        }
    }

    /**
     * Create or update a subscription for a user from Midtrans data.
     *
     * CRITICAL: Subscriptions are ALWAYS created for the master admin.
     * POS users (kasir) cannot have their own subscription - they inherit
     * from their master admin. This enforces the single subscription
     * per merchant model.
     *
     * @param  User  $user  The user (could be master admin or POS user)
     * @param  array  $midtransData  The Midtrans subscription data
     */
    public function createOrUpdateSubscription(User $user, array $midtransData): Subscription
    {
        // CRITICAL: Get the master admin who owns the subscription
        // Subscriptions are always linked to master admin, never to POS users
        $masterAdmin = $user->isMasterAdmin() ? $user : $user->getMasterAdmin();

        if ($masterAdmin === null) {
            Log::error('Cannot create subscription - no master admin found', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            throw new \Exception('Cannot create subscription: User is not associated with any merchant');
        }

        $subscription = $masterAdmin->subscription;

        $metadata = $midtransData['metadata'] ?? [];
        $schedule = $midtransData['schedule'] ?? [];

        $data = [
            'user_id' => $masterAdmin->id,  // ALWAYS linked to master admin
            'midtrans_subscription_id' => $midtransData['id'],
            'midtrans_customer_id' => $midtransData['customer_id'] ?? null,
            'plan_name' => $metadata['plan_id'] ?? 'pro',
            'status' => $this->mapMidtransStatus($midtransData['status'] ?? 'active'),
            'current_period_start' => $periodStart = isset($schedule['start_time'])
                ? Carbon::parse($schedule['start_time'])
                : Carbon::now(),
            'current_period_end' => $this->calculatePeriodEnd(
                $periodStart,
                $schedule['interval_unit'] ?? 'month',
                $schedule['interval'] ?? 1
            ),
            'metadata' => json_encode([
                'midtrans_name' => $midtransData['name'] ?? null,
                'amount' => $midtransData['amount'] ?? null,
                'currency' => $midtransData['currency'] ?? 'IDR',
                'payment_type' => $midtransData['payment_type'] ?? null,
                'interval' => $schedule['interval_unit'] ?? 'month',
                'interval_count' => $schedule['interval'] ?? 1,
                'initiated_by_user_id' => $user->id,  // Track who initiated
                'initiated_by_email' => $user->email,
            ]),
        ];

        if ($subscription !== null) {
            $subscription->update($data);
            Log::info('Subscription updated', [
                'event' => 'subscription.updated',
                'subscriptionId' => $subscription->id,
                'midtransSubscriptionId' => $midtransData['id'],
                'userId' => $user->id,
                'planName' => $data['plan_name'],
                'status' => $data['status'],
            ]);

            return $subscription->fresh();
        }

        $subscription = Subscription::create($data);
        Log::info('Subscription created', [
            'event' => 'subscription.created',
            'subscriptionId' => $subscription->id,
            'midtransSubscriptionId' => $midtransData['id'],
            'userId' => $user->id,
            'planName' => $data['plan_name'],
            'status' => $data['status'],
        ]);

        return $subscription;
    }

    /**
     * Cancel a subscription.
     *
     * @param  Subscription  $subscription  The subscription to cancel
     * @param  Carbon  $cancelledAt  The cancellation timestamp
     */
    public function cancelSubscription(Subscription $subscription, Carbon $cancelledAt): Subscription
    {
        $subscription->update([
            'cancelled_at' => $cancelledAt,
        ]);

        Log::info('Subscription cancelled', [
            'event' => 'subscription.cancelled',
            'subscriptionId' => $subscription->id,
            'userId' => $subscription->user_id,
            'planName' => $subscription->plan_name,
            'cancelledAt' => $cancelledAt->toIso8601String(),
            'periodEnd' => $subscription->current_period_end?->toIso8601String(),
        ]);

        return $subscription->fresh();
    }

    /**
     * Update subscription from Midtrans webhook data.
     *
     * @param  string  $midtransSubscriptionId  The Midtrans subscription ID
     * @param  array  $data  The webhook data
     */
    public function updateFromMidtransWebhook(string $midtransSubscriptionId, array $data): void
    {
        $subscription = Subscription::where('midtrans_subscription_id', $midtransSubscriptionId)->first();

        if ($subscription === null) {
            Log::warning('Subscription not found for Midtrans webhook update', [
                'midtransSubscriptionId' => $midtransSubscriptionId,
            ]);

            return;
        }

        $updateData = [];

        // Update status if provided
        if (isset($data['status'])) {
            $updateData['status'] = $this->mapMidtransStatus($data['status']);
        }

        // Update period dates if provided
        if (isset($data['current_period_start'])) {
            $updateData['current_period_start'] = Carbon::parse($data['current_period_start']);
        }

        if (isset($data['current_period_end'])) {
            $updateData['current_period_end'] = Carbon::parse($data['current_period_end']);
        }

        // Update metadata with transaction info
        if (isset($data['transaction_id']) || isset($data['transaction_status'])) {
            $existingMetadata = $subscription->metadata ? json_decode($subscription->metadata, true) : [];
            $existingMetadata['last_transaction_id'] = $data['transaction_id'] ?? null;
            $existingMetadata['last_transaction_status'] = $data['transaction_status'] ?? null;
            $existingMetadata['last_transaction_time'] = $data['transaction_time'] ?? null;
            $updateData['metadata'] = json_encode($existingMetadata);
        }

        if (! empty($updateData)) {
            $subscription->update($updateData);
            Log::info('Subscription updated from Midtrans webhook', [
                'subscriptionId' => $subscription->id,
                'midtransSubscriptionId' => $midtransSubscriptionId,
                'updates' => array_keys($updateData),
            ]);
        }
    }

    /**
     * Calculate remaining trial days for a user.
     *
     * @param  User  $user  The user to check
     * @return int Days remaining (0 if trial expired)
     */
    public function calculateTrialDaysRemaining(User $user): int
    {
        $trialDays = (int) config('subscription.trial_days', self::TRIAL_DAYS);
        $registrationDate = $user->created_at;

        if ($registrationDate === null) {
            return 0;
        }

        // Use startOfDay to ensure consistent day calculation
        $registrationDay = $registrationDate->copy()->startOfDay();
        $today = Carbon::now()->startOfDay();

        $daysSinceRegistration = (int) $registrationDay->diffInDays($today);
        $daysRemaining = $trialDays - $daysSinceRegistration;

        return max(0, $daysRemaining);
    }

    /**
     * Check if a user can access a feature based on their subscription tier.
     *
     * @param  User  $user  The user to check
     * @param  string  $featureTier  The required tier ('basic', 'standard', 'pro')
     */
    public function canAccessFeature(User $user, string $featureTier): bool
    {
        $status = $this->getUserSubscriptionStatus($user);

        // Get the user's current tier
        $userTier = $this->getTierFromPlanName($status->planName);

        // Define tier hierarchy (higher index = higher tier)
        $tierHierarchy = [
            PlanConfig::TIER_BASIC => 0,
            PlanConfig::TIER_PRO => 1,
        ];

        $userTierLevel = $tierHierarchy[$userTier] ?? 0;
        $requiredTierLevel = $tierHierarchy[$featureTier] ?? 0;

        // Trial users only have basic access
        if ($status->status === self::STATUS_TRIAL || $status->status === self::STATUS_TRIAL_EXPIRED) {
            return $featureTier === PlanConfig::TIER_BASIC;
        }

        // Expired subscriptions have no access beyond basic
        if ($status->status === self::STATUS_EXPIRED) {
            return $featureTier === PlanConfig::TIER_BASIC;
        }

        // Active or cancelled (but not expired) subscriptions
        return $userTierLevel >= $requiredTierLevel;
    }

    /**
     * Get tier from a plan name.
     *
     * @param  string  $planName  The plan name
     * @return string The tier
     */
    private function getTierFromPlanName(string $planName): string
    {
        return match ($planName) {
            PlanConfig::PLAN_FREE_TRIAL => PlanConfig::TIER_BASIC,
            PlanConfig::PLAN_PRO => PlanConfig::TIER_PRO,
            default => PlanConfig::TIER_BASIC,
        };
    }

    /**
     * Map Midtrans subscription status to internal status.
     *
     * @param  string  $midtransStatus  The Midtrans status
     * @return string The internal status
     */
    private function mapMidtransStatus(string $midtransStatus): string
    {
        return match (strtolower($midtransStatus)) {
            'active' => 'active',
            'inactive', 'disabled' => 'cancelled',
            'expired' => 'expired',
            default => 'active',
        };
    }

    /**
     * Get plan ID from Midtrans metadata.
     *
     * @param  array  $metadata  The Midtrans metadata
     * @return string The plan ID
     */
    private function getPlanIdFromMetadata(array $metadata): string
    {
        return $metadata['plan_id'] ?? 'standard';
    }

    /**
     * Calculate next period end date based on interval.
     *
     * @param  Carbon  $startDate  The start date
     * @param  string  $interval  The interval unit (month, year)
     * @param  int  $intervalCount  The interval count
     * @return Carbon The end date
     */
    private function calculatePeriodEnd(Carbon $startDate, string $interval = 'month', int $intervalCount = 1): Carbon
    {
        return match ($interval) {
            'month' => $startDate->copy()->addMonthsNoOverflow($intervalCount),
            'year' => $startDate->copy()->addYears($intervalCount),
            'day' => $startDate->copy()->addDays($intervalCount),
            default => $startDate->copy()->addMonthsNoOverflow(1),
        };
    }
}
