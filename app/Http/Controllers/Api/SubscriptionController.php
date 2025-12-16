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
}
