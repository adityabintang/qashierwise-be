<?php

namespace App\Http\Controllers;

use App\Services\MidtransSubscriptionService;
use App\Services\SubscriptionService;
use App\Services\XenditSubscriptionService;
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
        private SubscriptionService $subscriptionService,
        private XenditSubscriptionService $xenditSubscriptionService,
    ) {}

    /**
     * Show payment page with Midtrans Snap.
     */
    public function payment(): View|RedirectResponse
    {
        $user = auth()->user();

        // Explicit authentication check
        if ($user === null) {
            return redirect()->route('login')->with('error', 'Please login to continue.');
        }

        // Check if snap token exists in session
        $snapToken = session('snap_token');
        $planId = session('selected_plan_id');
        $duration = session('selected_duration');

        if (empty($snapToken) || empty($planId) || empty($duration)) {
            Log::warning('Payment page accessed without snap token', [
                'userId' => $user->id,
                'hasSnapToken' => ! empty($snapToken),
                'hasPlanId' => ! empty($planId),
                'hasDuration' => ! empty($duration),
            ]);

            return redirect()->route('subscription.pricing')->with('error', 'Please select a plan first.');
        }

        $plan = config("subscription.plans.{$planId}");

        if ($plan === null || ! isset($plan['durations'][$duration])) {
            Log::error('Invalid plan or duration in session', [
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
            ]);

            return redirect()->route('subscription.pricing')->with('error', 'Invalid plan selected.');
        }

        $durationDetails = $plan['durations'][$duration];

        return view('subscription.payment', [
            'snapToken' => $snapToken,
            'plan' => $plan,
            'planId' => $planId,
            'duration' => $duration,
            'durationDetails' => $durationDetails,
            'clientKey' => config('midtrans.client_key'),
        ]);
    }

    /**
     * Show pricing page with available subscription plans.
     */
    public function index(): View
    {
        $plans = config('subscription.plans', []);

        return view('subscription.pricing', [
            'plans' => $plans,
        ]);
    }

    /**
     * Create checkout session and redirect to Midtrans Subscription payment page.
     *
     * Uses Midtrans Subscription API for recurring payments instead of Snap.
     */
    public function createCheckout(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|string|in:standard,pro',
            'duration' => 'required|string|in:1_month,3_months,1_year',
        ]);

        $user = $request->user();

        // Explicit authentication check (defense in depth)
        if ($user === null) {
            Log::warning('Unauthenticated checkout attempt', [
                'event' => 'checkout.unauthenticated',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with('error', 'Please login to subscribe.');
        }

        $planId = $request->input('plan_id');
        $duration = $request->input('duration');

        // Check if Midtrans service is configured
        if (! $this->midtransService->isConfigured()) {
            Log::warning('Midtrans not configured', [
                'event' => 'checkout.config_missing',
                'userId' => $user->id,
            ]);

            return redirect()->back()->with('error', 'Subscription service is not configured. Please contact support.');
        }

        // Check if user already has an active subscription (with null safety)
        $existingSubscription = $user->subscription;
        if ($existingSubscription !== null && $existingSubscription->status === 'active') {
            Log::info('User already has active subscription', [
                'event' => 'checkout.already_subscribed',
                'userId' => $user->id,
                'subscriptionId' => $existingSubscription->id,
            ]);

            return redirect()->route('subscription.manage')->with('info', 'You already have an active subscription.');
        }

        try {
            Log::info('Checkout session initiated', [
                'event' => 'checkout.initiated',
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
                'userEmail' => $user->email,
                'hasExistingSubscription' => $existingSubscription !== null,
                'existingStatus' => $existingSubscription?->status,
            ]);

            // Store plan info in session and redirect to card tokenization page
            session([
                'selected_plan_id' => $planId,
                'selected_duration' => $duration,
            ]);

            Log::info('Redirecting to card tokenization page', [
                'event' => 'checkout.redirect_to_tokenization',
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
            ]);

            // Redirect to card tokenization page
            return redirect()->route('subscription.tokenization');
        } catch (\Exception $e) {
            Log::error('Failed to create checkout session', [
                'event' => 'checkout.failed',
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to create checkout session. Please try again.');
        }
    }

    /**
     * Show card tokenization page for Midtrans Subscription.
     */
    public function tokenization(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return redirect()->route('login')->with('error', 'Please login to continue.');
        }

        $planId = session('selected_plan_id');
        $duration = session('selected_duration');

        if (empty($planId) || empty($duration)) {
            return redirect()->route('subscription.pricing')->with('error', 'Please select a plan first.');
        }

        $plan = config("subscription.plans.{$planId}");

        if ($plan === null || ! isset($plan['durations'][$duration])) {
            return redirect()->route('subscription.pricing')->with('error', 'Invalid plan selected.');
        }

        $durationDetails = $plan['durations'][$duration];

        return view('subscription.tokenization', [
            'plan' => $plan,
            'planId' => $planId,
            'duration' => $duration,
            'durationDetails' => $durationDetails,
            'clientKey' => config('subscription.midtrans.client_key'),
        ]);
    }

    /**
     * Process card token and create subscription.
     */
    public function createSubscription(Request $request): RedirectResponse
    {
        $request->validate([
            'card_token' => 'required|string',
        ]);

        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login')->with('error', 'Please login to continue.');
        }

        $planId = session('selected_plan_id');
        $duration = session('selected_duration');

        if (empty($planId) || empty($duration)) {
            return redirect()->route('subscription.pricing')->with('error', 'Please select a plan first.');
        }

        $cardToken = $request->input('card_token');

        try {
            Log::info('Creating Midtrans subscription', [
                'event' => 'subscription.create_start',
                'userId' => $user->id,
                'planId' => $planId,
                'duration' => $duration,
            ]);

            // Create subscription using Midtrans Subscription API
            $subscriptionData = $this->midtransService->createSubscription($user, $planId, $cardToken);

            if ($subscriptionData === null || empty($subscriptionData['id'])) {
                Log::error('Failed to create Midtrans subscription', [
                    'event' => 'subscription.create_failed',
                    'userId' => $user->id,
                    'planId' => $planId,
                ]);

                return redirect()->back()->with('error', 'Failed to create subscription. Please try again.');
            }

            // Store subscription info in database
            $plan = config("subscription.plans.{$planId}");
            $durationDetails = $plan['durations'][$duration] ?? null;
            $months = $durationDetails['months'] ?? 1;

            // Create or update local subscription
            $subscription = $user->subscription;
            $subscriptionData = [
                'user_id' => $user->id,
                'midtrans_subscription_id' => $subscriptionData['id'],
                'midtrans_customer_id' => $subscriptionData['customer_id'] ?? null,
                'plan_name' => $planId,
                'status' => $subscriptionData['status'] === 'active' ? 'active' : 'pending',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonthsNoOverflow($months),
                'metadata' => json_encode([
                    'midtrans_name' => $subscriptionData['name'] ?? null,
                    'amount' => $subscriptionData['amount'] ?? null,
                    'currency' => $subscriptionData['currency'] ?? 'IDR',
                    'payment_type' => 'credit_card',
                    'interval' => $subscriptionData['schedule']['interval_unit'] ?? 'month',
                    'interval_count' => $subscriptionData['schedule']['interval'] ?? 1,
                    'duration' => $duration,
                    'duration_name' => $durationDetails['name'] ?? null,
                    'months' => $months,
                ]),
            ];

            if ($subscription) {
                $subscription->update($subscriptionData);
            } else {
                $subscription = \App\Models\Subscription::create($subscriptionData);
            }

            // Clear session
            session()->forget(['selected_plan_id', 'selected_duration']);

            Log::info('Subscription created successfully', [
                'event' => 'subscription.created',
                'subscription_id' => $subscription->id,
                'midtrans_subscription_id' => $subscriptionData['id'],
                'user_id' => $user->id,
            ]);

            return redirect()->route('subscription.success')
                ->with('success', 'Subscription created successfully! Your subscription is now active.');
        } catch (\Exception $e) {
            Log::error('Exception creating subscription', [
                'event' => 'subscription.exception',
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'planId' => $planId,
            ]);

            return redirect()->back()->with('error', 'Failed to create subscription. Please try again.');
        }
    }

    /**
     * Handle successful payment callback from Midtrans.
     *
     * No subscription property access - safe from null pointer errors.
     */
    public function success(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Explicit authentication check
        if ($user === null) {
            Log::warning('Unauthenticated success callback', [
                'event' => 'checkout.success_unauthenticated',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with('info', 'Please login to view your subscription.');
        }

        Log::info('Subscription payment success callback', [
            'event' => 'checkout.success_callback',
            'userId' => $user->id,
            'queryParams' => $request->query(),
        ]);

        $subscription = $user->getEffectiveSubscription();

        if ($subscription !== null && ! empty($subscription->xendit_subscription_id)) {
            $xenditPlan = $this->xenditSubscriptionService->getRecurringPlan($subscription->xendit_subscription_id);
            $xenditStatus = strtoupper((string) ($xenditPlan['status'] ?? ''));

            Log::info('Xendit status sync from success callback', [
                'event' => 'checkout.success_xendit_sync',
                'userId' => $user->id,
                'subscriptionId' => $subscription->id,
                'xenditSubscriptionId' => $subscription->xendit_subscription_id,
                'xenditStatus' => $xenditStatus,
            ]);

            if ($xenditStatus === 'ACTIVE') {
                $subscription->update([
                    'status' => 'active',
                ]);

                return redirect()->route('dashboard')->with('success', 'Payment successful! Your subscription is now active.');
            }

            if ($xenditStatus === 'REQUIRES_ACTION' || $subscription->status === 'pending') {
                return redirect()->route('dashboard')->with('info', 'Pembayaran sedang diproses. Status subscription akan aktif otomatis setelah dikonfirmasi oleh Xendit.');
            }
        }

        // The actual subscription creation/update will be handled by webhook
        // Redirect to dashboard with refresh parameter to force subscription reload

        return redirect()->route('dashboard')->with('success', 'Payment successful! Your subscription is now active.');
    }

    /**
     * Handle cancelled payment callback from Midtrans.
     *
     * No subscription property access - safe from null pointer errors.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Explicit authentication check
        if ($user === null) {
            Log::warning('Unauthenticated cancel callback', [
                'event' => 'checkout.cancel_unauthenticated',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with('info', 'Please login to try again.');
        }

        Log::info('Subscription payment cancelled', [
            'event' => 'checkout.cancel_callback',
            'userId' => $user->id,
            'queryParams' => $request->query(),
        ]);

        return redirect()->route('subscription.pricing')->with('info', 'Payment was cancelled. You can try again when ready.');
    }

    /**
     * Handle payment error callback from Midtrans.
     *
     * No subscription property access - safe from null pointer errors.
     */
    public function error(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Explicit authentication check
        if ($user === null) {
            Log::warning('Unauthenticated error callback', [
                'event' => 'checkout.error_unauthenticated',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with('error', 'Please login to try again.');
        }

        Log::error('Subscription payment error', [
            'event' => 'checkout.error_callback',
            'userId' => $user->id,
            'queryParams' => $request->query(),
        ]);

        return redirect()->route('subscription.pricing')->with('error', 'Payment failed. Please try again or contact support if the issue persists.');
    }

    /**
     * Show subscription management page.
     *
     * Handles null subscriptions safely by using null-safe operator.
     *
     * @return View
     */
    public function manage(): View|RedirectResponse
    {
        $user = auth()->user();

        // Explicit authentication check (defense in depth)
        if ($user === null) {
            return redirect()->route('login')->with('error', 'Please login to view your subscription.');
        }

        // Use null-safe operator to prevent errors
        $subscription = $user->subscription;

        // Get subscription status (handles null subscriptions)
        $subscriptionStatus = $this->subscriptionService->getUserSubscriptionStatus($user);

        // Get available plans
        $plans = config('subscription.plans', []);

        Log::debug('Subscription management page accessed', [
            'userId' => $user->id,
            'hasSubscription' => $subscription !== null,
            'subscriptionStatus' => $subscriptionStatus->status,
        ]);

        return view('subscription.manage', [
            'subscription' => $subscription,
            'subscriptionStatus' => $subscriptionStatus,
            'plans' => $plans,
        ]);
    }

    /**
     * Cancel user's subscription.
     *
     * Handles null subscriptions with explicit checks and clear error messages.
     */
    public function cancelSubscription(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Explicit authentication check (defense in depth)
        if ($user === null) {
            Log::warning('Unauthenticated cancel attempt', [
                'event' => 'subscription.cancel_unauthenticated',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with('error', 'Please login to manage your subscription.');
        }

        $subscription = $user->subscription;

        // Explicit null check with clear error message
        if ($subscription === null) {
            Log::warning('Cancel attempt with no subscription', [
                'event' => 'subscription.cancel_no_subscription',
                'userId' => $user->id,
            ]);

            return redirect()->back()->with('error', 'No active subscription found.');
        }

        // Check if already cancelled
        if ($subscription->status === 'cancelled') {
            Log::info('Cancel attempt for already cancelled subscription', [
                'event' => 'subscription.already_cancelled',
                'userId' => $user->id,
                'subscriptionId' => $subscription->id,
            ]);

            return redirect()->back()->with('info', 'Subscription is already cancelled.');
        }

        try {
            // If subscription has Midtrans ID, cancel through Midtrans API
            if (! empty($subscription->midtrans_subscription_id)) {
                Log::info('Cancelling Midtrans subscription via API', [
                    'event' => 'subscription.cancel_via_api',
                    'userId' => $user->id,
                    'subscriptionId' => $subscription->id,
                    'midtransSubscriptionId' => $subscription->midtrans_subscription_id,
                ]);

                // Cancel subscription in Midtrans
                $success = $this->midtransService->cancelSubscription($subscription->midtrans_subscription_id);

                if (! $success) {
                    Log::error('Midtrans cancellation failed', [
                        'event' => 'subscription.midtrans_cancel_failed',
                        'userId' => $user->id,
                        'subscriptionId' => $subscription->id,
                    ]);

                    return redirect()->back()->with('error', 'Failed to cancel subscription. Please try again or contact support.');
                }
            } else {
                // For subscriptions without Midtrans ID (manual/legacy), just cancel locally
                Log::info('Cancelling subscription locally (no Midtrans ID)', [
                    'event' => 'subscription.cancel_local_only',
                    'userId' => $user->id,
                    'subscriptionId' => $subscription->id,
                    'hasMidtransId' => ! empty($subscription->midtrans_subscription_id),
                ]);
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
                'midtransSubscriptionId' => $subscription->midtrans_subscription_id ?? 'none',
                'planName' => $subscription->plan_name,
                'periodEnd' => $subscription->current_period_end?->toIso8601String(),
            ]);

            return redirect()->route('subscription.manage')->with('success', 'Subscription cancelled successfully. You can continue using the service until the end of your billing period.');
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'event' => 'subscription.cancel_exception',
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'subscriptionId' => $subscription->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to cancel subscription. Please try again or contact support.');
        }
    }
}
