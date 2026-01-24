# Subscription & Alpine.js Fixes

## Issues Fixed

### 1. Alpine.js Reference Errors
**Problem**: Multiple `ReferenceError` messages in browser console:
- `isMobile is not defined`
- `sidebarOpen is not defined`
- `user is not defined`
- `notifications is not defined`

**Root Cause**: The `subscription/manage.blade.php` page uses `x-data="manageSubscription()"` which doesn't include the required Alpine.js variables that the `dashboard-sidebar` and `dashboard-header` components need.

**Solution**: Updated `resources/views/subscription/manage.blade.php` to merge the `manageSubscription()` data with the required dashboard variables:

```javascript
x-data="{
    ...manageSubscription(),
    isMobile: window.innerWidth < 1024,
    sidebarOpen: window.innerWidth >= 1024,
    user: null,
    notifications: [],
    // ... other methods
}"
```

### 2. Subscription Not Activating After Payment
**Problem**: Payment was successful but subscription plan wasn't being activated in the dashboard.

**Root Cause**: The `SubscriptionController::createCheckout()` method was not actually creating a Midtrans Snap transaction. It was just redirecting to the manage page without generating a payment token.

**Solution**: 

#### A. Updated `SubscriptionController::createCheckout()`
Modified the method to actually call `MidtransSnapService` to create a Snap token:

```php
// Create Midtrans Snap token
$snapData = app(\App\Services\MidtransSnapService::class)->createSubscriptionSnapToken($user, $planId);

if ($snapData === null || empty($snapData['snap_token'])) {
    return redirect()->back()->with('error', 'Failed to create payment session.');
}

// Store snap token in session and redirect to payment page
session([
    'selected_plan_id' => $planId,
    'snap_token' => $snapData['snap_token'],
]);

return redirect()->route('subscription.payment');
```

#### B. Added Payment Page Route
Added new route in `routes/web.php`:

```php
Route::get('/payment', [App\Http\Controllers\SubscriptionController::class, 'payment'])
    ->name('payment');
```

#### C. Created Payment Method
Added `payment()` method to `SubscriptionController`:

```php
public function payment(): View|RedirectResponse
{
    // Validates snap token and plan from session
    // Returns payment view with Midtrans Snap integration
}
```

#### D. Created Payment View
Created `resources/views/subscription/payment.blade.php` with:
- Midtrans Snap JS integration
- Payment button that triggers Snap popup
- Plan details display
- Success/error/pending callbacks

## How It Works Now

### Payment Flow:
1. User selects a plan on pricing page
2. Clicks "Subscribe" button
3. `SubscriptionController::createCheckout()` creates Midtrans Snap token
4. User is redirected to `/subscription/payment` page
5. User clicks "Proceed to Payment" button
6. Midtrans Snap popup opens with payment options
7. User completes payment
8. Midtrans sends webhook to `/api/webhooks/midtrans/subscription`
9. `MidtransWebhookController::handleSubscriptionWebhook()` processes the webhook
10. `processSubscriptionPaymentSuccess()` creates/updates subscription in database
11. User is redirected to success page
12. Subscription is now active in dashboard

### Webhook Processing:
The webhook controller (`MidtransWebhookController`) properly handles subscription creation:

```php
private function processSubscriptionPaymentSuccess(array $payload): void
{
    // Extract user ID and plan ID from custom fields
    $userId = $payload['custom_field3'] ?? null;
    $planId = $payload['custom_field1'] ?? null;
    $paymentType = $payload['custom_field2'] ?? null;

    if ($paymentType === 'subscription' && $userId && $planId) {
        $user = \App\Models\User::find($userId);
        
        if ($user) {
            // Create or update subscription
            $subscriptionData = [
                'user_id' => $user->id,
                'provider' => 'midtrans',
                'plan_name' => $planId,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                // ... metadata
            ];

            $subscription = $user->subscription;
            
            if ($subscription) {
                $subscription->update($subscriptionData);
            } else {
                \App\Models\Subscription::create($subscriptionData);
            }
        }
    }
}
```

## Testing

### To Test the Fix:

1. **Clear browser console** to see if Alpine.js errors are gone
2. **Navigate to subscription manage page** (`/subscription/manage`)
3. **Verify no console errors** for `isMobile`, `sidebarOpen`, `user`, or `notifications`
4. **Test subscription flow**:
   - Go to `/subscription/pricing`
   - Click "Subscribe" on a plan
   - Should redirect to `/subscription/payment`
   - Click "Proceed to Payment"
   - Complete payment in Midtrans Snap popup
   - After success, check `/subscription/manage` to see active subscription

### Test Webhook Manually:

Run the test script to simulate a webhook:

```bash
php test_subscription_snap_webhook.php
```

This will:
- Send a test webhook notification to your local server
- Create a subscription for user ID 1 (change in script if needed)
- Verify the subscription was created in the database
- Show detailed logs

### Expected Results:
- ✅ No Alpine.js console errors
- ✅ Sidebar and header work properly
- ✅ Payment page displays correctly
- ✅ Midtrans Snap popup opens
- ✅ After successful payment, subscription is created
- ✅ Dashboard shows active subscription plan
- ✅ Test webhook script shows "Subscription found!"

## Files Modified

1. `resources/views/subscription/manage.blade.php` - Fixed Alpine.js data
2. `app/Http/Controllers/SubscriptionController.php` - Added Snap token creation and payment method
3. `routes/web.php` - Added payment route
4. `resources/views/subscription/payment.blade.php` - Created payment page (NEW)
5. `test_subscription_snap_webhook.php` - Test script for webhook (NEW)
6. `SUBSCRIPTION_ALPINE_FIX.md` - This documentation (NEW)

## Notes

- The Midtrans Snap JS URL automatically switches between sandbox and production based on `MIDTRANS_IS_PRODUCTION` in `.env`

- Make sure these environment variables are set in `.env`:
  ```
  MIDTRANS_SERVER_KEY=your_server_key
  MIDTRANS_CLIENT_KEY=your_client_key
  MIDTRANS_MERCHANT_ID=your_merchant_id
  MIDTRANS_IS_PRODUCTION=false  # Set to true for production
  ```

- The webhook URL must be configured in Midtrans dashboard:
  - Webhook URL: `https://your-domain.com/api/webhooks/midtrans/subscription`
  - Make sure it's accessible from the internet (not localhost)
  - For local testing, use ngrok or similar tunneling service

## Troubleshooting

If subscription still doesn't activate:

1. **Check logs**: `storage/logs/laravel.log` for webhook processing
2. **Verify webhook is being called**: Look for "Midtrans subscription webhook received" log entries
3. **Check custom fields**: Ensure `custom_field1`, `custom_field2`, `custom_field3` are being sent correctly
4. **Verify plan config**: Check `config/subscription.php` has the plan defined
5. **Database check**: Query `subscriptions` table to see if record was created

```sql
SELECT * FROM subscriptions WHERE user_id = YOUR_USER_ID ORDER BY created_at DESC LIMIT 1;
```
