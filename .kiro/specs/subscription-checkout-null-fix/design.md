# Subscription Checkout Null Pointer Fix - Design

## 1. Overview

This design addresses the null pointer error in `SubscriptionController::createCheckout()` by implementing proper null checks and safe property access patterns throughout the subscription flow.

## 2. Root Cause Analysis

### 2.1 Current Code (Line 66)
```php
$user = $request->user();
$planId = $request->input('plan_id');

// ... config check ...

// Check if user already has an active subscription (with null safety)
$existingSubscription = $user->subscription;
```

**Problem:** The code assumes `$user` is always authenticated. When `$request->user()` returns null (unauthenticated user), accessing `$user->subscription` throws a null pointer error.

### 2.2 Actual Issue
The error "Attempt to read property 'subscription' on null" occurs because:
1. The route may not have proper authentication middleware
2. The controller doesn't check if `$user` is null before accessing properties
3. Unauthenticated users can reach the checkout endpoint

## 3. Solution Design

### 3.1 Fix Strategy

**Approach:** Implement defensive programming with authentication checks, null-safe operators, and explicit null checks throughout the controller.

**Key Changes:**
1. Add authentication middleware to subscription routes
2. Add explicit null check for `$user` before accessing properties
3. Use null-safe operator (`?->`) when accessing subscription properties
4. Add explicit null checks before property access
5. Ensure views handle null subscriptions gracefully
6. Add comprehensive logging for debugging

### 3.2 Code Changes

#### 3.2.0 Update Routes (Add Authentication Middleware)

Ensure subscription routes require authentication:

```php
// In routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::get('/subscription/pricing', [SubscriptionController::class, 'index'])->name('subscription.pricing');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'createCheckout'])->name('subscription.checkout');
    Route::get('/subscription/manage', [SubscriptionController::class, 'manage'])->name('subscription.manage');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('subscription.cancel');
    
    // Callback routes (may need to be outside auth middleware depending on implementation)
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
    Route::get('/subscription/cancel-payment', [SubscriptionController::class, 'cancel'])->name('subscription.cancel-payment');
    Route::get('/subscription/error', [SubscriptionController::class, 'error'])->name('subscription.error');
});
```

#### 3.2.1 Update `createCheckout()` Method

```php
public function createCheckout(Request $request): RedirectResponse
{
    $request->validate([
        'plan_id' => 'required|string|in:standard,pro',
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

    // Check if Midtrans service is configured
    if (!$this->midtransService->isConfigured()) {
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
            'userEmail' => $user->email,
            'hasExistingSubscription' => $existingSubscription !== null,
            'existingStatus' => $existingSubscription?->status,
        ]);
        
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
```

#### 3.2.2 Update `manage()` Method

```php
public function manage(): View
{
    $user = auth()->user();
    
    // Explicit authentication check (defense in depth)
    if ($user === null) {
        abort(401, 'Unauthenticated');
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
```

#### 3.2.3 Update `cancelSubscription()` Method

```php
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

    // Check if subscription is from Midtrans
    if ($subscription->provider !== 'midtrans' || empty($subscription->midtrans_subscription_id)) {
        Log::warning('Cancel attempt for non-Midtrans subscription', [
            'event' => 'subscription.cancel_wrong_provider',
            'userId' => $user->id,
            'provider' => $subscription->provider,
        ]);
        return redirect()->back()->with('error', 'This subscription cannot be cancelled through this interface.');
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
        // Cancel subscription in Midtrans
        $success = $this->midtransService->cancelSubscription($subscription->midtrans_subscription_id);

        if (!$success) {
            Log::error('Midtrans cancellation failed', [
                'event' => 'subscription.midtrans_cancel_failed',
                'userId' => $user->id,
                'subscriptionId' => $subscription->id,
            ]);
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
            'event' => 'subscription.cancel_exception',
            'error' => $e->getMessage(),
            'userId' => $user->id,
            'subscriptionId' => $subscription->id,
            'trace' => $e->getTraceAsString(),
        ]);

        return redirect()->back()->with('error', 'Failed to cancel subscription. Please try again or contact support.');
    }
}
```

### 3.3 View Updates

#### 3.3.1 Update `subscription/manage.blade.php`

Ensure the view handles null subscriptions:

```blade
@if($subscription === null)
    <div class="alert alert-info">
        <p>You don't have an active subscription yet.</p>
        <a href="{{ route('subscription.pricing') }}" class="btn btn-primary">View Plans</a>
    </div>
@else
    {{-- Existing subscription display code --}}
    <div class="subscription-details">
        <h3>Current Subscription</h3>
        <p>Plan: {{ $subscription->plan_name }}</p>
        <p>Status: {{ $subscription->status }}</p>
        {{-- More subscription details --}}
    </div>
@endif
```

## 4. Testing Strategy

### 4.1 Unit Tests

```php
// Test unauthenticated checkout attempt
public function test_checkout_redirects_unauthenticated_users_to_login()
{
    $response = $this->post(route('subscription.checkout'), ['plan_id' => 'standard']);
    
    $response->assertRedirect(route('login'));
}

// Test null subscription handling
public function test_checkout_allows_authenticated_users_without_subscription()
{
    $user = User::factory()->create();
    // Don't create a subscription
    
    $response = $this->actingAs($user)
        ->post(route('subscription.checkout'), ['plan_id' => 'standard']);
    
    $response->assertRedirect(route('subscription.manage'));
    $response->assertSessionHas('info');
}

// Test active subscription prevention
public function test_checkout_prevents_duplicate_active_subscription()
{
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);
    
    $response = $this->actingAs($user)
        ->post(route('subscription.checkout'), ['plan_id' => 'standard']);
    
    $response->assertRedirect(route('subscription.manage'));
    $response->assertSessionHas('info', 'You already have an active subscription.');
}

// Test cancelled subscription allows resubscribe
public function test_checkout_allows_resubscribe_after_cancellation()
{
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'cancelled',
    ]);
    
    $response = $this->actingAs($user)
        ->post(route('subscription.checkout'), ['plan_id' => 'standard']);
    
    $response->assertRedirect(route('subscription.manage'));
    $response->assertSessionHas('info');
}

// Test manage page with null subscription
public function test_manage_page_handles_null_subscription()
{
    $user = User::factory()->create();
    // Don't create a subscription
    
    $response = $this->actingAs($user)
        ->get(route('subscription.manage'));
    
    $response->assertOk();
    $response->assertViewHas('subscription', null);
}

// Test unauthenticated manage page access
public function test_manage_page_redirects_unauthenticated_users()
{
    $response = $this->get(route('subscription.manage'));
    
    $response->assertRedirect(route('login'));
}

// Test cancel with null subscription
public function test_cancel_fails_gracefully_without_subscription()
{
    $user = User::factory()->create();
    // Don't create a subscription
    
    $response = $this->actingAs($user)
        ->post(route('subscription.cancel'));
    
    $response->assertRedirect();
    $response->assertSessionHas('error', 'No active subscription found.');
}

// Test unauthenticated cancel attempt
public function test_cancel_redirects_unauthenticated_users()
{
    $response = $this->post(route('subscription.cancel'));
    
    $response->assertRedirect(route('login'));
}
```

### 4.2 Integration Tests

- Test full checkout flow for new users
- Test checkout prevention for active subscribers
- Test resubscription after cancellation
- Test management page rendering with and without subscriptions

### 4.3 Manual Testing Checklist

- [ ] Unauthenticated user is redirected to login when accessing checkout
- [ ] Unauthenticated user is redirected to login when accessing manage page
- [ ] Unauthenticated user is redirected to login when attempting to cancel
- [ ] New authenticated user can access pricing page
- [ ] New authenticated user can click subscribe button
- [ ] New authenticated user doesn't see null pointer errors
- [ ] User with active subscription sees appropriate message
- [ ] User with cancelled subscription can resubscribe
- [ ] Management page displays correctly for users without subscriptions
- [ ] Cancel button only shows for users with active subscriptions

## 5. Deployment Plan

### 5.1 Pre-Deployment
1. Run all unit tests
2. Run integration tests
3. Review error logs for similar issues
4. Backup database

### 5.2 Deployment
1. Deploy controller changes
2. Deploy view changes
3. Monitor error logs
4. Monitor checkout success rate

### 5.3 Rollback Plan
If issues occur:
1. Revert controller changes
2. Revert view changes
3. Investigate root cause
4. Fix and redeploy

### 5.4 Post-Deployment Monitoring
- Monitor error logs for null pointer errors (should be zero)
- Monitor checkout success rate (should increase)
- Monitor user feedback
- Check Sentry/error tracking for new issues

## 6. Risk Assessment

### 6.1 Low Risk
- Adding null checks (defensive programming)
- Improving logging
- View updates for null handling

### 6.2 Medium Risk
- None identified

### 6.3 High Risk
- None identified

## 7. Performance Considerations

- Null checks add negligible overhead (<1ms)
- No additional database queries required
- Logging adds minimal overhead
- Overall performance impact: negligible

## 8. Security Considerations

- No security implications
- Maintains existing authentication requirements
- No new attack vectors introduced

## 9. Correctness Properties

### Property 1: Authentication Required
**Statement:** For all requests R to subscription endpoints, the user MUST be authenticated before processing.

**Validation:** Unit tests verify that unauthenticated requests are redirected to login.

### Property 2: Null Safety
**Statement:** For all authenticated users U, accessing subscription properties MUST NOT throw null pointer errors.

**Validation:** Unit tests verify that null subscriptions are handled gracefully.

### Property 3: Active Subscription Prevention
**Statement:** For all authenticated users U with active subscription S, attempting checkout MUST redirect to management page.

**Validation:** Integration tests verify duplicate subscription prevention.

### Property 4: Resubscription Allowance
**Statement:** For all authenticated users U with cancelled/expired subscription S, attempting checkout MUST succeed.

**Validation:** Integration tests verify resubscription flow works.
