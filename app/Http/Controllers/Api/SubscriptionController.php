<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanConfig;
use App\Services\PolarService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for subscription-related API endpoints.
 * 
 * Handles subscription status retrieval, checkout session creation,
 * and customer portal access.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private PolarService $polarService,
        private PlanConfig $planConfig,
    ) {}

    /**
     * Get the current user's subscription status.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $status = $this->subscriptionService->getUserSubscriptionStatus($user);

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $status->toArray(),
            ],
        ]);
    }

    /**
     * Create a checkout session for a subscription plan.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createCheckout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|string|in:standard,pro',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLAN',
                    'message' => 'Invalid plan selected',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $planId = $request->input('plan_id');

        // Check if Polar service is configured
        if (!$this->polarService->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POLAR_CONFIG_MISSING',
                    'message' => 'Subscription service is not configured',
                ],
            ], 503);
        }

        $result = $this->polarService->createCheckoutSessionWithError($user, $planId);

        if ($result['session'] === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHECKOUT_FAILED',
                    'message' => 'Failed to create checkout session',
                    'detail' => config('app.debug') ? $result['error'] : null,
                ],
            ], 500);
        }

        $checkoutSession = $result['session'];

        return response()->json([
            'success' => true,
            'data' => [
                'checkout_url' => $checkoutSession->url,
                'session_id' => $checkoutSession->id,
            ],
        ]);
    }

    /**
     * Get the customer portal URL for managing subscription.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPortalUrl(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if ($subscription === null || $subscription->polar_customer_id === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_NOT_FOUND',
                    'message' => 'No active subscription found',
                ],
            ], 404);
        }

        // Check if Polar service is configured
        if (!$this->polarService->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POLAR_CONFIG_MISSING',
                    'message' => 'Subscription service is not configured',
                ],
            ], 503);
        }

        $portalUrl = $this->polarService->getCustomerPortalUrl($subscription->polar_customer_id);

        if ($portalUrl === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PORTAL_URL_FAILED',
                    'message' => 'Failed to retrieve customer portal URL',
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'portal_url' => $portalUrl,
            ],
        ]);
    }

    /**
     * Verify subscription after successful checkout.
     * 
     * This endpoint verifies a subscription using the customer_session_token
     * provided by Polar on the success URL redirect. It serves as a fallback
     * when webhook delivery fails.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyCheckout(Request $request): JsonResponse
    {
        // Task 2.1: Token validation logic
        $token = $request->input('customer_session_token');

        // Validate token is present and not empty
        if (empty($token)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Customer session token is required',
                ],
            ], 422);
        }

        // Validate token format matches polar_cst_[a-zA-Z0-9]+ pattern
        if (!preg_match('/^polar_cst_[a-zA-Z0-9]+$/', $token)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN_FORMAT',
                    'message' => 'Invalid token format',
                ],
            ], 422);
        }

        // Task 2.3: Check if Polar service is configured (503)
        if (!$this->polarService->isConfigured()) {
            Log::error('Subscription verification failed: Polar service not configured', [
                'userId' => $request->user()->id,
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POLAR_CONFIG_MISSING',
                    'message' => 'Subscription service is not configured',
                ],
            ], 503);
        }

        // Task 2.2: Verification flow
        $user = $request->user();

        // Fetch checkout data from Polar
        $checkoutData = $this->polarService->getCheckoutByToken($token);

        // Task 2.3: Handle VERIFICATION_FAILED (500) - Checkout not found
        if ($checkoutData === null) {
            Log::error('Subscription verification failed: Checkout not found', [
                'token' => substr($token, 0, 20) . '...',
                'userId' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VERIFICATION_FAILED',
                    'message' => 'Failed to verify checkout',
                ],
            ], 500);
        }

        // Task 2.3: Handle UNAUTHORIZED_USER (403) - User ownership validation
        $metadataUserId = $checkoutData['metadata']['user_id'] ?? null;
        if ($metadataUserId !== null && (string) $metadataUserId !== (string) $user->id) {
            Log::warning('Subscription verification failed: User mismatch', [
                'authenticatedUserId' => $user->id,
                'metadataUserId' => $metadataUserId,
                'checkoutId' => $checkoutData['id'] ?? 'unknown',
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED_USER',
                    'message' => 'Subscription does not belong to this user',
                ],
            ], 403);
        }

        // Fetch full subscription details from Polar
        $subscriptionId = $checkoutData['subscription_id'] ?? null;
        
        // Task 2.3: Handle VERIFICATION_FAILED (500) - No subscription_id in checkout
        if ($subscriptionId === null) {
            Log::error('Subscription verification failed: No subscription_id in checkout', [
                'checkoutId' => $checkoutData['id'] ?? 'unknown',
                'userId' => $user->id,
                'checkoutStatus' => $checkoutData['status'] ?? 'unknown',
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VERIFICATION_FAILED',
                    'message' => 'Failed to verify checkout',
                ],
            ], 500);
        }

        $polarSubscription = $this->polarService->getSubscription($subscriptionId);

        // Task 2.3: Handle VERIFICATION_FAILED (500) - Subscription not found in Polar
        if ($polarSubscription === null) {
            Log::error('Subscription verification failed: Subscription not found in Polar', [
                'subscriptionId' => $subscriptionId,
                'userId' => $user->id,
                'checkoutId' => $checkoutData['id'] ?? 'unknown',
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VERIFICATION_FAILED',
                    'message' => 'Failed to fetch subscription details',
                ],
            ], 500);
        }

        // Determine plan name from product ID
        $planName = $this->planConfig->getPlanNameFromProductId($polarSubscription['product_id']) 
            ?? ($checkoutData['metadata']['plan_id'] ?? 'standard');

        // Create or update subscription
        $subscription = $this->subscriptionService->createOrUpdateSubscription($user, [
            'polar_subscription_id' => $polarSubscription['id'],
            'polar_customer_id' => $polarSubscription['customer_id'],
            'plan_name' => $planName,
            'status' => $polarSubscription['status'],
            'current_period_start' => $polarSubscription['current_period_start'],
            'current_period_end' => $polarSubscription['current_period_end'],
        ]);

        Log::info('Subscription verified successfully', [
            'subscriptionId' => $subscription->id,
            'userId' => $user->id,
            'polarSubscriptionId' => $polarSubscription['id'],
            'planName' => $planName,
            'status' => $polarSubscription['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription verified successfully',
            'data' => [
                'subscription' => [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->plan_name,
                    'status' => $subscription->status,
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Manually sync subscription from Polar (for debugging/fallback).
     * 
     * This endpoint allows manually creating/updating a subscription
     * when webhook delivery fails.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function syncFromPolar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'polar_subscription_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Polar subscription ID is required',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $polarSubscriptionId = $request->input('polar_subscription_id');

        // Fetch subscription from Polar API
        $polarSubscription = $this->polarService->getSubscription($polarSubscriptionId);

        if ($polarSubscription === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_NOT_FOUND',
                    'message' => 'Subscription not found in Polar',
                ],
            ], 404);
        }

        // Determine plan name from product ID
        $planName = $this->planConfig->getPlanNameFromProductId($polarSubscription['product_id']) ?? 'standard';

        // Create or update subscription
        $subscription = $this->subscriptionService->createOrUpdateSubscription($user, [
            'polar_subscription_id' => $polarSubscription['id'],
            'polar_customer_id' => $polarSubscription['customer_id'],
            'plan_name' => $planName,
            'status' => $polarSubscription['status'],
            'current_period_start' => $polarSubscription['current_period_start'],
            'current_period_end' => $polarSubscription['current_period_end'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription synced successfully',
            'data' => [
                'subscription' => [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->plan_name,
                    'status' => $subscription->status,
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                ],
            ],
        ]);
    }
}
