<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\PlanConfig;
use App\Services\SubscriptionService;
use App\Services\XenditSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for subscription-related API endpoints.
 *
 * Handles subscription status retrieval, checkout session creation,
 * and subscription management using Xendit Recurring.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private XenditSubscriptionService $xenditSubscriptionService,
        private PlanConfig $planConfig,
    ) {}

    /**
     * Reconcile a pending local subscription with Xendit's authoritative state.
     *
     * Webhook `recurring.plan.activated` is the canonical activator. This
     * fallback exists for two scenarios: (1) local/dev where Xendit cannot
     * reach the server, and (2) webhook delivery failures in production.
     *
     * Rate-limited to one Xendit API call per subscription per 60 seconds so
     * that a frontend polling the status endpoint rapidly does not flood the
     * Xendit API.
     */
    private function syncPendingSubscriptionWithProvider(\App\Models\User $user): void
    {
        $masterAdmin = $user->isMasterAdmin() ? $user : $user->getMasterAdmin();
        $subscription = $masterAdmin?->subscription;

        if ($subscription === null
            || $subscription->status !== 'pending'
            || empty($subscription->xendit_subscription_id)
            || ! $this->xenditSubscriptionService->isConfigured()
        ) {
            return;
        }

        // Throttle: at most one Xendit API call per subscription per 60 seconds.
        $cacheKey = 'xendit_sync_'.$subscription->id;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addSeconds(60));

        try {
            $xenditPlan = $this->xenditSubscriptionService->getRecurringPlan(
                $subscription->xendit_subscription_id
            );

            if ($xenditPlan === null) {
                return;
            }

            $xenditStatus = strtoupper((string) ($xenditPlan['status'] ?? ''));

            if ($xenditStatus === 'ACTIVE') {
                $subscription->update([
                    'status' => 'active',
                    'cancelled_at' => null,
                ]);

                // Record payment in billing history (idempotent — safe to call even
                // if webhook already recorded it).
                $this->subscriptionService->recordXenditPayment(
                    $subscription,
                    $subscription->xendit_subscription_id,
                    $xenditPlan['reference_id'] ?? null,
                );

                Log::info('Subscription auto-activated from Xendit sync', [
                    'subscription_id' => $subscription->id,
                    'xendit_subscription_id' => $subscription->xendit_subscription_id,
                    'user_id' => $masterAdmin->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync pending subscription with Xendit', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get current user's subscription status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // Auto-sync pending subscriptions with the payment provider before
        // computing status. The webhook (recurring.plan.activated) is the
        // canonical activator, but it can be delayed or — in local/dev
        // environments — never reach the server at all. Without this sync the
        // user would see "trial" forever after a successful payment.
        $this->syncPendingSubscriptionWithProvider($user);

        $status = $this->subscriptionService->getUserSubscriptionStatus($user);
        $subscription = $user->subscription;

        // Build subscription data
        $subscriptionData = [
            'status' => $status->status,
            'plan_name' => $status->planName,
            'trial_days_remaining' => $status->trialDaysRemaining,
            'period_start' => $status->periodEnd ? $subscription?->current_period_start?->toIso8601String() : null,
            'period_end' => $status->periodEnd?->toIso8601String(),
            'cancelled_at' => $status->cancelledAt?->toIso8601String(),
            'amount' => null,
        ];

        // Add amount if subscription exists
        if ($subscription) {
            // Try to get amount from subscription metadata first
            $metadata = is_string($subscription->metadata)
                ? json_decode($subscription->metadata, true)
                : $subscription->metadata;

            if (isset($metadata['price_per_month'])) {
                // Use price_per_month from metadata
                $subscriptionData['amount'] = $metadata['price_per_month'];
            } elseif (isset($metadata['amount'])) {
                // Fall back to amount from metadata
                $months = $metadata['months'] ?? 1;
                $subscriptionData['amount'] = $months > 0 ? ($metadata['amount'] / $months) : 0;
            } else {
                // Fall back to config
                $plan = config("subscription.plans.{$subscription->plan_name}");
                $subscriptionData['amount'] = $plan['price_monthly'] ?? 0;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscriptionData,
            ],
        ]);
    }

    /**
     * Get billing history for current user.
     *
     * POS users see their master admin's billing history since
     * all payments are made by the merchant owner.
     */
    public function billingHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        // CRITICAL: Use effective user ID to get master admin's billing history
        // POS users inherit billing history from their master admin
        $effectiveUserId = $user->getEffectiveUserId();

        // Get payment history from subscription_payments table
        $payments = SubscriptionPayment::where('user_id', $effectiveUserId)
            ->orderBy('transaction_time', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'transaction_id' => $payment->transaction_id,
                    'plan_name' => $payment->plan_name,
                    'gross_amount' => (float) $payment->gross_amount,
                    'currency' => $payment->currency,
                    'payment_type' => $payment->payment_type,
                    'status' => $payment->status,
                    'transaction_time' => $payment->transaction_time?->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
            ],
        ]);
    }

    /**
     * Create a checkout session for a subscription plan using Xendit.
     */
    public function createCheckout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|string|in:pro',
            'duration' => 'required|string|in:1_month,3_months,1_year',
            'promo_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLAN',
                    'message' => 'Invalid plan or duration selected',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $planId = $request->input('plan_id');
        $duration = $request->input('duration');
        $promoCode = $request->input('promo_code');

        Log::info('Xendit checkout attempt', [
            'userId' => $user->id,
            'planId' => $planId,
            'duration' => $duration,
            'hasPromoCode' => ! empty($promoCode),
        ]);

        // Check if Xendit service is configured
        if (! $this->xenditSubscriptionService->isConfigured()) {
            Log::error('Xendit not configured', [
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'XENDIT_CONFIG_MISSING',
                    'message' => 'Subscription service is not configured',
                ],
            ], 503);
        }

        // Get plan price
        $plan = config("subscription.plans.{$planId}");
        if (! $plan || ! isset($plan['durations'][$duration])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLAN',
                    'message' => 'Invalid plan or duration',
                ],
            ], 422);
        }

        $originalAmount = $plan['durations'][$duration]['price'];
        $finalAmount = $originalAmount;
        $discountAmount = 0;
        $promoCodeData = null;

        // Validate and apply promo code if provided
        if ($promoCode) {
            $promoService = app(\App\Services\PromoCodeService::class);
            $promoResult = $promoService->validateAndApply($promoCode, $planId, $originalAmount, $user);

            if (! $promoResult['valid']) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_PROMO_CODE',
                        'message' => $promoResult['message'],
                    ],
                ], 422);
            }

            $finalAmount = $promoResult['final_amount'];
            $discountAmount = $promoResult['discount'];
            $promoCodeData = $promoResult['promo_code'];

            Log::info('Promo code applied to checkout', [
                'userId' => $user->id,
                'promoCode' => $promoCode,
                'originalAmount' => $originalAmount,
                'discount' => $discountAmount,
                'finalAmount' => $finalAmount,
            ]);
        }

        try {
            // Stop any existing Xendit plan before creating a new one.
            // Without this, the old plan remains in REQUIRES_ACTION or AUTHORIZING
            // state in Xendit. When the user tries to authorize the new plan,
            // Xendit returns INTENT_UNPROCESSABLE_ERROR because a previous intent
            // for the same customer is still in AUTHORIZING state.
            $masterAdminForCleanup = $user->isMasterAdmin() ? $user : $user->getMasterAdmin();
            $existingForCleanup = $masterAdminForCleanup?->subscription;
            if ($existingForCleanup && ! empty($existingForCleanup->xendit_subscription_id)) {
                try {
                    $this->xenditSubscriptionService->stopRecurringPlan(
                        $existingForCleanup->xendit_subscription_id
                    );
                    Log::info('Stopped previous Xendit plan before new checkout', [
                        'userId' => $user->id,
                        'old_xendit_subscription_id' => $existingForCleanup->xendit_subscription_id,
                    ]);
                } catch (\Exception $e) {
                    // Non-fatal: log and continue. stopRecurringPlan already handles 404
                    // (plan already gone), so this only fires on unexpected errors.
                    Log::warning('Could not stop previous Xendit plan, proceeding anyway', [
                        'userId' => $user->id,
                        'old_xendit_subscription_id' => $existingForCleanup->xendit_subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create Xendit recurring plan, passing the actual request origin so
            // the success/cancel return URLs use the same host the user is on.
            // This prevents localStorage mismatch when APP_URL differs from the
            // host in the browser (e.g. localhost vs 127.0.0.1 in local dev).
            $result = $this->xenditSubscriptionService->createRecurringPlan(
                $user,
                $planId,
                $duration,
                $request->getSchemeAndHttpHost()
            );

            Log::info('Xendit checkout session created successfully', [
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
                'subscription_id' => $result['subscription_id'] ?? null,
                'status' => $result['status'] ?? null,
            ]);

            $durationDetails = $plan['durations'][$duration];
            $months = (int) ($durationDetails['months'] ?? 1);
            $masterAdmin = $user->isMasterAdmin() ? $user : $user->getMasterAdmin();

            if ($masterAdmin === null) {
                Log::warning('Master admin not found, using current user for subscription ownership', [
                    'userId' => $user->id,
                    'planId' => $planId,
                    'duration' => $duration,
                ]);

                $masterAdmin = $user;
            }

            $subscriptionPayload = [
                'user_id' => $masterAdmin->id,
                'xendit_subscription_id' => $result['subscription_id'] ?? null,
                'plan_name' => $planId,
                'status' => 'pending',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonthsNoOverflow($months),
                // Reset cancelled_at: starting a new checkout means the user is no
                // longer cancelled. Without this reset, a previously cancelled
                // subscription would be re-evaluated as STATUS_CANCELLED (not
                // expired) and incorrectly grant pro access before payment.
                'cancelled_at' => null,
                'metadata' => json_encode([
                    'reference_id' => $result['reference_id'] ?? null,
                    'amount' => $finalAmount,
                    'original_amount' => $originalAmount,
                    'discount' => $discountAmount,
                    'currency' => $plan['currency'] ?? 'IDR',
                    'plan_id' => $planId,
                    'duration' => $duration,
                    'duration_name' => $durationDetails['name'] ?? null,
                    'months' => $months,
                    'price_per_month' => $durationDetails['price_per_month'] ?? null,
                    'xendit_status' => $result['status'] ?? null,
                    'initiated_by_user_id' => $user->id,
                    'initiated_by_email' => $user->email,
                ]),
            ];

            $existingSubscription = $masterAdmin->subscription;

            if ($existingSubscription !== null) {
                $existingSubscription->update($subscriptionPayload);
            } else {
                Subscription::create($subscriptionPayload);
            }

            // Return checkout URL for user to complete payment
            return response()->json([
                'success' => true,
                'data' => [
                    'checkout_url' => $result['action_url'],
                    'redirect_url' => $result['action_url'],
                    'subscription_id' => $result['subscription_id'],
                    'status' => $result['status'],
                    'original_amount' => $originalAmount,
                    'final_amount' => $finalAmount,
                    'discount' => $discountAmount,
                ],
            ]);
        } catch (\RuntimeException $e) {
            Log::error('Failed to create Xendit checkout', [
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHECKOUT_FAILED',
                    'message' => 'Failed to create checkout session: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Cancel current subscription.
     *
     * Cancels the user's active subscription via Xendit.
     * Access remains until the end of the current billing period.
     *
     * CRITICAL: Only master admins can cancel subscriptions.
     * POS users cannot cancel - they inherit from master admin.
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        // CRITICAL: Only master admins can cancel subscriptions
        if (! $user->isMasterAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Only merchant owners can cancel subscriptions',
                ],
            ], 403);
        }

        // Get the master admin's subscription directly
        $subscription = $user->subscription;

        if ($subscription === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_NOT_FOUND',
                    'message' => 'No active subscription found',
                ],
            ], 404);
        }

        // Check if Xendit subscription ID exists
        if (empty($subscription->xendit_subscription_id)) {
            // Try Midtrans if Xendit is not set
            if (empty($subscription->midtrans_subscription_id)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_SUBSCRIPTION',
                        'message' => 'Subscription does not have a valid subscription ID',
                    ],
                ], 400);
            }

            // Use Midtrans to cancel
            $midtransService = app(\App\Services\MidtransSubscriptionService::class);
            if (! $midtransService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CONFIG_MISSING',
                        'message' => 'Subscription service is not configured',
                    ],
                ], 503);
            }

            $midtransService->cancelSubscription($subscription->midtrans_subscription_id);
        } else {
            // Use Xendit to cancel
            if (! $this->xenditSubscriptionService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'XENDIT_CONFIG_MISSING',
                        'message' => 'Subscription service is not configured',
                    ],
                ], 503);
            }

            try {
                $this->xenditSubscriptionService->stopRecurringPlan($subscription->xendit_subscription_id);
            } catch (\RuntimeException $e) {
                Log::error('Failed to cancel Xendit subscription', [
                    'subscription_id' => $subscription->xendit_subscription_id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CANCELLATION_FAILED',
                        'message' => 'Failed to cancel subscription: '.$e->getMessage(),
                    ],
                ], 500);
            }
        }

        $this->subscriptionService->cancelSubscription($subscription, now());

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully',
            'data' => [
                'subscription' => [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->plan_name,
                    'status' => $subscription->status,
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                    'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
