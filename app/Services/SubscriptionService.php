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
     * @param User $user The user to check
     * @return SubscriptionStatus
     */
    public function getUserSubscriptionStatus(User $user): SubscriptionStatus
    {
        $subscription = $user->subscription;

        // If user has no subscription, check trial status
        if ($subscription === null) {
            return $this->getTrialStatus($user);
        }

        // If subscription is cancelled but still within period
        if ($subscription->isCancelled() && !$subscription->isExpired()) {
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
     * @param User $user The user to check
     * @return SubscriptionStatus
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
     * Process a webhook event from Polar.sh.
     * 
     * @param array $event The webhook event data
     * @return void
     */
    public function processWebhookEvent(array $event): void
    {
        $eventType = $event['type'] ?? null;

        if ($eventType === null) {
            Log::warning('Webhook event missing type', ['event' => $event]);
            return;
        }

        Log::info('Processing webhook event', ['type' => $eventType]);

        match ($eventType) {
            'subscription.created' => $this->handleSubscriptionCreated($event),
            'subscription.updated' => $this->handleSubscriptionUpdated($event),
            'subscription.cancelled', 'subscription.canceled' => $this->handleSubscriptionCancelled($event),
            default => Log::info('Unhandled webhook event type', ['type' => $eventType]),
        };
    }

    /**
     * Process a webhook event from Midtrans.
     * 
     * @param array $payload The webhook payload data
     * @return void
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
     * Handle subscription.created webhook event.
     * 
     * @param array $event The webhook event data
     * @return void
     */
    private function handleSubscriptionCreated(array $event): void
    {
        $data = $event['data'] ?? [];
        $this->createOrUpdateSubscriptionFromWebhook($data, 'active');
    }

    /**
     * Handle subscription.updated webhook event.
     * 
     * @param array $event The webhook event data
     * @return void
     */
    private function handleSubscriptionUpdated(array $event): void
    {
        $data = $event['data'] ?? [];
        $status = $data['status'] ?? 'active';
        $this->createOrUpdateSubscriptionFromWebhook($data, $status);
    }

    /**
     * Handle subscription.cancelled webhook event.
     * 
     * @param array $event The webhook event data
     * @return void
     */
    private function handleSubscriptionCancelled(array $event): void
    {
        $data = $event['data'] ?? [];
        $subscriptionId = $data['id'] ?? null;

        if ($subscriptionId === null) {
            Log::warning('Subscription cancelled event missing subscription ID');
            return;
        }

        $subscription = Subscription::where('polar_subscription_id', $subscriptionId)->first();

        if ($subscription === null) {
            Log::warning('Subscription not found for cancellation', ['subscriptionId' => $subscriptionId]);
            return;
        }

        $cancelledAt = isset($data['canceled_at']) 
            ? Carbon::parse($data['canceled_at']) 
            : Carbon::now();

        $this->cancelSubscription($subscription, $cancelledAt);
    }

    /**
     * Handle successful Midtrans payment.
     * 
     * @param array $payload The webhook payload
     * @return void
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
     * @param array $payload The webhook payload
     * @return void
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
     * @param array $payload The webhook payload
     * @return void
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
     * Create or update subscription from webhook data.
     * 
     * @param array $data The subscription data from webhook
     * @param string $status The subscription status
     * @return Subscription|null
     */
    private function createOrUpdateSubscriptionFromWebhook(array $data, string $status): ?Subscription
    {
        $subscriptionId = $data['id'] ?? null;
        $customerId = $data['customer_id'] ?? null;
        $metadata = $data['metadata'] ?? [];
        $userId = $metadata['user_id'] ?? null;

        if ($subscriptionId === null || $userId === null) {
            Log::warning('Webhook data missing required fields', [
                'subscriptionId' => $subscriptionId,
                'userId' => $userId,
            ]);
            return null;
        }

        $user = User::find($userId);

        if ($user === null) {
            Log::warning('User not found for subscription', ['userId' => $userId]);
            return null;
        }

        // Determine plan name from product ID
        $productId = $data['product_id'] ?? null;
        $planName = $this->getPlanNameFromProductId($productId) ?? ($metadata['plan_id'] ?? 'standard');

        return $this->createOrUpdateSubscription($user, [
            'polar_subscription_id' => $subscriptionId,
            'polar_customer_id' => $customerId,
            'plan_name' => $planName,
            'status' => $status,
            'current_period_start' => $data['current_period_start'] ?? null,
            'current_period_end' => $data['current_period_end'] ?? null,
        ]);
    }


    /**
     * Get plan name from Polar product ID.
     * 
     * @param string|null $productId The Polar product ID
     * @return string|null
     */
    private function getPlanNameFromProductId(?string $productId): ?string
    {
        if ($productId === null) {
            return null;
        }

        $standardProductId = config('polar.products.standard');
        $proProductId = config('polar.products.pro');

        if ($productId === $standardProductId) {
            return PlanConfig::PLAN_STANDARD;
        }

        if ($productId === $proProductId) {
            return PlanConfig::PLAN_PRO;
        }

        return null;
    }

    /**
     * Create or update a subscription for a user.
     * 
     * @param User $user The user
     * @param array $polarData The subscription data
     * @return Subscription
     */
    public function createOrUpdateSubscription(User $user, array $polarData): Subscription
    {
        $subscription = $user->subscription;

        $data = [
            'user_id' => $user->id,
            'polar_subscription_id' => $polarData['polar_subscription_id'],
            'polar_customer_id' => $polarData['polar_customer_id'],
            'plan_name' => $polarData['plan_name'],
            'status' => $polarData['status'],
            'current_period_start' => isset($polarData['current_period_start']) 
                ? Carbon::parse($polarData['current_period_start']) 
                : Carbon::now(),
            'current_period_end' => isset($polarData['current_period_end']) 
                ? Carbon::parse($polarData['current_period_end']) 
                : Carbon::now()->addMonth(),
        ];

        if ($subscription !== null) {
            $subscription->update($data);
            Log::info('Subscription updated', [
                'event' => 'subscription.updated',
                'subscriptionId' => $subscription->id,
                'userId' => $user->id,
                'planName' => $data['plan_name'],
                'status' => $data['status'],
                'provider' => 'polar',
            ]);
            return $subscription->fresh();
        }

        $subscription = Subscription::create($data);
        Log::info('Subscription created', [
            'event' => 'subscription.created',
            'subscriptionId' => $subscription->id,
            'userId' => $user->id,
            'planName' => $data['plan_name'],
            'status' => $data['status'],
            'provider' => 'polar',
        ]);

        return $subscription;
    }

    /**
     * Create a subscription from Midtrans data.
     * 
     * @param User $user The user
     * @param array $midtransData The Midtrans subscription data
     * @return Subscription
     */
    public function createMidtransSubscription(User $user, array $midtransData): Subscription
    {
        $subscription = $user->subscription;

        $metadata = $midtransData['metadata'] ?? [];
        $schedule = $midtransData['schedule'] ?? [];
        
        $data = [
            'user_id' => $user->id,
            'midtrans_subscription_id' => $midtransData['id'],
            'midtrans_customer_id' => $midtransData['customer_id'] ?? null,
            'provider' => 'midtrans',
            'plan_name' => $metadata['plan_id'] ?? 'standard',
            'status' => $this->mapMidtransStatus($midtransData['status'] ?? 'active'),
            'current_period_start' => isset($schedule['start_time']) 
                ? Carbon::parse($schedule['start_time']) 
                : Carbon::now(),
            'current_period_end' => isset($schedule['start_time']) 
                ? Carbon::parse($schedule['start_time'])->addMonth() 
                : Carbon::now()->addMonth(),
            'metadata' => json_encode([
                'midtrans_name' => $midtransData['name'] ?? null,
                'amount' => $midtransData['amount'] ?? null,
                'currency' => $midtransData['currency'] ?? 'IDR',
                'payment_type' => $midtransData['payment_type'] ?? null,
            ]),
        ];

        if ($subscription !== null) {
            $subscription->update($data);
            Log::info('Midtrans subscription updated', [
                'event' => 'subscription.midtrans.updated',
                'subscriptionId' => $subscription->id,
                'midtransSubscriptionId' => $midtransData['id'],
                'userId' => $user->id,
                'planName' => $data['plan_name'],
                'status' => $data['status'],
                'provider' => 'midtrans',
            ]);
            return $subscription->fresh();
        }

        $subscription = Subscription::create($data);
        Log::info('Midtrans subscription created', [
            'event' => 'subscription.midtrans.created',
            'subscriptionId' => $subscription->id,
            'midtransSubscriptionId' => $midtransData['id'],
            'userId' => $user->id,
            'planName' => $data['plan_name'],
            'status' => $data['status'],
            'provider' => 'midtrans',
        ]);

        return $subscription;
    }

    /**
     * Cancel a subscription.
     * 
     * @param Subscription $subscription The subscription to cancel
     * @param Carbon $cancelledAt The cancellation timestamp
     * @return Subscription
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
            'provider' => $subscription->provider,
            'cancelledAt' => $cancelledAt->toIso8601String(),
            'periodEnd' => $subscription->current_period_end?->toIso8601String(),
        ]);

        return $subscription->fresh();
    }

    /**
     * Update subscription from Midtrans webhook data.
     * 
     * @param string $midtransSubscriptionId The Midtrans subscription ID
     * @param array $data The webhook data
     * @return void
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

        if (!empty($updateData)) {
            $subscription->update($updateData);
            Log::info('Subscription updated from Midtrans webhook', [
                'subscriptionId' => $subscription->id,
                'midtransSubscriptionId' => $midtransSubscriptionId,
                'updates' => array_keys($updateData),
            ]);
        }
    }

    /**
     * Calculate the remaining trial days for a user.
     * 
     * @param User $user The user to check
     * @return int Days remaining (0 if trial expired)
     */
    public function calculateTrialDaysRemaining(User $user): int
    {
        $trialDays = (int) config('polar.trial_days', self::TRIAL_DAYS);
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
     * @param User $user The user to check
     * @param string $featureTier The required tier ('basic', 'standard', 'pro')
     * @return bool
     */
    public function canAccessFeature(User $user, string $featureTier): bool
    {
        $status = $this->getUserSubscriptionStatus($user);

        // Get the user's current tier
        $userTier = $this->getTierFromPlanName($status->planName);

        // Define tier hierarchy (higher index = higher tier)
        $tierHierarchy = [
            PlanConfig::TIER_BASIC => 0,
            PlanConfig::TIER_STANDARD => 1,
            PlanConfig::TIER_PRO => 2,
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
     * Get the tier from a plan name.
     * 
     * @param string $planName The plan name
     * @return string The tier
     */
    private function getTierFromPlanName(string $planName): string
    {
        return match ($planName) {
            PlanConfig::PLAN_FREE_TRIAL => PlanConfig::TIER_BASIC,
            PlanConfig::PLAN_STANDARD => PlanConfig::TIER_STANDARD,
            PlanConfig::PLAN_PRO => PlanConfig::TIER_PRO,
            default => PlanConfig::TIER_BASIC,
        };
    }

    /**
     * Map Midtrans subscription status to internal status.
     * 
     * @param string $midtransStatus The Midtrans status
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
     * @param array $metadata The Midtrans metadata
     * @return string The plan ID
     */
    private function getPlanIdFromMetadata(array $metadata): string
    {
        return $metadata['plan_id'] ?? 'standard';
    }

    /**
     * Calculate next period end date based on interval.
     * 
     * @param Carbon $startDate The start date
     * @param string $interval The interval unit (month, year)
     * @param int $intervalCount The interval count
     * @return Carbon The end date
     */
    private function calculatePeriodEnd(Carbon $startDate, string $interval = 'month', int $intervalCount = 1): Carbon
    {
        return match ($interval) {
            'month' => $startDate->copy()->addMonths($intervalCount),
            'year' => $startDate->copy()->addYears($intervalCount),
            'day' => $startDate->copy()->addDays($intervalCount),
            default => $startDate->copy()->addMonth(),
        };
    }
}
