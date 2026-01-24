<?php

namespace App\Http\Controllers;

use App\Services\MidtransSubscriptionService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controller for Midtrans subscription management (web routes).
 * 
 * Handles subscription pricing page, checkout flow, and subscription management.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private MidtransSubscriptionService $midtransService,
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Show pricing page with available subscription plans.
     * 
     * @return View
     */
    public function index(): View
    {
        $plans = config('subscription.plans', []);
        
        return view('subscription.pricing', [
            'plans' => $plans,
        ]);
    }

    /**
     * Create checkout session and redirect to Midtrans payment page.
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function createCheckout(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|string|in:standard,pro',
        ]);

        $user = $request->user();
        $planId = $request->input('plan_id');

        // Check if Midtrans service is configured
        if (!$this->midtransService->isConfigured()) {
            return redirect()->back()->with('error', 'Subscription service is not configured. Please contact support.');
        }

        // Check if user already has an active subscription
        $existingSubscription = $user->subscription;
        if ($existingSubscription && $existingSubscription->status === 'active') {
            return redirect()->route('subscription.manage')->with('info', 'You already have an active subscription.');
        }

        try {
            Log::info('Checkout session initiated', [
                'event' => 'checkout.initiated',
                'userId' => $user->id,
                'planId' => $planId,
                'userEmail' => $user->email,
            ]);
            
            // For now, we'll redirect to a payment token collection page
            // In a real implementation, this would integrate with Midtrans Snap
            // to get a payment token first, then create the subscription
            
            // Store the plan selection in session for later use
            session(['selected_plan_id' => $planId]);
            
            return redirect()->route('subscription.manage')->with('info', 'Please complete payment setup to activate your subscription.');
        } catch (\Exception $e) {
            Log::error('Failed to create checkout session', [
                'event' => 'checkout.failed',
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'planId' => $planId,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->back()->with('error', 'Failed to create checkout session. Please try again.');
        }
    }

    /**
     * Handle successful payment callback from Midtrans.
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function success(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        Log::info('Subscription payment success callback', [
            'userId' => $user->id,
            'queryParams' => $request->query(),
        ]);

        // The actual subscription creation/update will be handled by webhook
        // This is just a user-facing success page
        
        return redirect()->route('subscription.manage')->with('success', 'Payment successful! Your subscription will be activated shortly.');
    }

    /**
     * Handle cancelled payment callback from Midtrans.
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        Log::info('Subscription payment cancelled', [
            'userId' => $user->id,
            'queryParams' => $request->query(),
        ]);

        return redirect()->route('subscription.pricing')->with('info', 'Payment was cancelled. You can try again when ready.');
    }

    /**
     * Handle payment error callback from Midtrans.
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function error(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        Log::error('Subscription payment error', [
            'userId' => $user->id,
            'queryParams' => $request->query(),
        ]);

        return redirect()->route('subscription.pricing')->with('error', 'Payment failed. Please try again or contact support if the issue persists.');
    }

    /**
     * Show subscription management page.
     * 
     * @return View
     */
    public function manage(): View
    {
        $user = auth()->user();
        $subscription = $user->subscription;
        
        // Get subscription status
        $subscriptionStatus = $this->subscriptionService->getUserSubscriptionStatus($user);
        
        // Get available plans
        $plans = config('subscription.plans', []);
        
        return view('subscription.manage', [
            'subscription' => $subscription,
            'subscriptionStatus' => $subscriptionStatus,
            'plans' => $plans,
        ]);
    }

    /**
     * Cancel user's subscription.
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function cancelSubscription(Request $request): RedirectResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription) {
            return redirect()->back()->with('error', 'No active subscription found.');
        }

        // Check if subscription is from Midtrans
        if ($subscription->provider !== 'midtrans' || empty($subscription->midtrans_subscription_id)) {
            return redirect()->back()->with('error', 'This subscription cannot be cancelled through this interface.');
        }

        // Check if already cancelled
        if ($subscription->status === 'cancelled') {
            return redirect()->back()->with('info', 'Subscription is already cancelled.');
        }

        try {
            // Cancel subscription in Midtrans
            $success = $this->midtransService->cancelSubscription($subscription->midtrans_subscription_id);

            if (!$success) {
                return redirect()->back()->with('error', 'Failed to cancel subscription. Please try again or contact support.');
            }

            // Update local subscription status
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Log::info('Subscription cancelled successfully', [
                'event' => 'subscription.user_cancelled',
                'userId' => $user->id,
                'subscriptionId' => $subscription->id,
                'midtransSubscriptionId' => $subscription->midtrans_subscription_id,
                'planName' => $subscription->plan_name,
                'periodEnd' => $subscription->current_period_end?->toIso8601String(),
            ]);

            return redirect()->route('subscription.manage')->with('success', 'Subscription cancelled successfully. You can continue using the service until the end of your billing period.');
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'subscriptionId' => $subscription->id,
            ]);

            return redirect()->back()->with('error', 'Failed to cancel subscription. Please try again or contact support.');
        }
    }
}
