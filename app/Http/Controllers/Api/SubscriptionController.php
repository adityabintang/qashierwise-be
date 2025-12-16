<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanConfig;
use App\Services\PolarService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
