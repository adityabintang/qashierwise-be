# Subscription Architecture Issue - Snap vs Subscription API

## Problem Discovery

Subscription yang dibuat tidak memiliki `midtrans_subscription_id` karena menggunakan **Midtrans Snap** (one-time payment) bukan **Midtrans Subscription API** (recurring subscription).

## Evidence

### Subscription Data
```
Subscription ID: 7
Provider: midtrans
Midtrans Subscription ID: NULL  ← Problem!
Midtrans Customer ID: NULL
```

### Metadata Analysis
```json
{
  "order_id": "SUB-1-1769245466-test",
  "transaction_id": "TXN-1769245466",  ← Snap transaction
  "amount": "99000",
  "currency": "IDR",
  "payment_type": "credit_card"
}
```

**Key Finding**: Metadata contains `transaction_id` (Snap) but NOT `subscription_id` (Subscription API)

## Root Cause

### Current Flow (WRONG)
```
User clicks subscribe
    ↓
MidtransSnapService::createSubscriptionSnapToken()
    ↓
Creates ONE-TIME payment via Snap API
    ↓
User pays
    ↓
Webhook receives transaction notification
    ↓
Creates subscription record WITHOUT midtrans_subscription_id
    ↓
❌ Cannot cancel via Midtrans API
❌ No automatic recurring billing
❌ Need manual renewal
```

### Expected Flow (CORRECT)
```
User clicks subscribe
    ↓
MidtransSubscriptionService::createSubscription()
    ↓
Creates RECURRING subscription via Subscription API
    ↓
User pays first installment
    ↓
Webhook receives subscription.created notification
    ↓
Creates subscription record WITH midtrans_subscription_id
    ↓
✅ Can cancel via Midtrans API
✅ Automatic recurring billing
✅ Midtrans handles renewals
```

## Midtrans Products Comparison

### Snap (One-Time Payment)
- **Endpoint**: `/snap/v1/transactions`
- **Use Case**: Single purchases, one-time payments
- **Returns**: `transaction_id`
- **Recurring**: Manual (you handle it)
- **Cancellation**: N/A (already paid)
- **Example**: Buy a product, pay invoice

### Subscription API (Recurring)
- **Endpoint**: `/v1/subscriptions`
- **Use Case**: Recurring subscriptions
- **Returns**: `subscription_id`
- **Recurring**: Automatic (Midtrans handles it)
- **Cancellation**: Via API (`/subscriptions/{id}/disable`)
- **Example**: Monthly SaaS subscription

## Current Implementation Analysis

### Files Involved

1. **MidtransSnapService.php** ✅ Exists
   - Creates Snap tokens for one-time payments
   - Used in current flow
   - Wrong for subscriptions

2. **MidtransSubscriptionService.php** ✅ Exists
   - Has `createSubscription()` method
   - Has `cancelSubscription()` method
   - NOT used in current flow
   - This is what should be used!

3. **SubscriptionController.php**
   - `createCheckout()` uses `MidtransSnapService`
   - Should use `MidtransSubscriptionService` instead

## Why This Happened

Looking at the code, it seems:

1. **Initial Implementation**: Used Snap because it's simpler (no recurring setup needed)
2. **Later Addition**: Added `MidtransSubscriptionService` but never integrated it
3. **Result**: Two services exist but wrong one is being used

## Impact

### Current Issues
- ❌ No `midtrans_subscription_id` in database
- ❌ Cannot cancel via Midtrans API
- ❌ No automatic recurring billing
- ❌ Users need to manually renew each month
- ❌ No subscription management in Midtrans dashboard

### What Works
- ✅ Initial payment collection
- ✅ Local subscription tracking
- ✅ Access control based on subscription status

## Solutions

### Option 1: Switch to Subscription API (RECOMMENDED)

**Pros**:
- ✅ True recurring subscriptions
- ✅ Automatic billing by Midtrans
- ✅ Can cancel via API
- ✅ Proper subscription management
- ✅ Midtrans handles payment retries

**Cons**:
- ⚠️ Requires code changes
- ⚠️ Need to migrate existing subscriptions
- ⚠️ More complex setup

**Implementation**:
```php
// In SubscriptionController::createCheckout()

// OLD (current):
$snapData = app(\App\Services\MidtransSnapService::class)
    ->createSubscriptionSnapToken($user, $planId, $duration);

// NEW (correct):
$subscriptionData = app(\App\Services\MidtransSubscriptionService::class)
    ->createSubscription($user, $planId, $paymentToken);
```

### Option 2: Continue with Snap (NOT RECOMMENDED)

**Pros**:
- ✅ No code changes needed
- ✅ Simpler implementation

**Cons**:
- ❌ Not true subscriptions
- ❌ Manual renewal required
- ❌ Cannot cancel via API
- ❌ More work for you to handle recurring

**What to do**:
- Accept that subscriptions won't have `midtrans_subscription_id`
- Handle cancellation locally only (already implemented)
- Manually send payment reminders for renewal
- Create new Snap payment for each renewal

## Recommendation

**Switch to Midtrans Subscription API** for proper recurring subscription functionality.

### Migration Steps

1. **Update Checkout Flow**
   - Modify `SubscriptionController::createCheckout()`
   - Use `MidtransSubscriptionService` instead of `MidtransSnapService`
   - Handle payment token collection

2. **Update Webhook Handler**
   - Handle `subscription.created` event
   - Handle `subscription.updated` event
   - Handle `subscription.cancelled` event
   - Store `midtrans_subscription_id`

3. **Migrate Existing Subscriptions**
   - For active Snap-based subscriptions:
     - Option A: Let them expire naturally, new subscriptions use Subscription API
     - Option B: Create new subscriptions in Midtrans, migrate users
     - Option C: Keep dual system (Snap for old, Subscription API for new)

4. **Update Documentation**
   - Document the new flow
   - Update API documentation
   - Update user guides

## Immediate Fix (Already Implemented)

For now, the cancellation has been fixed to work with both:
- Subscriptions WITH `midtrans_subscription_id` → Cancel via Midtrans API
- Subscriptions WITHOUT `midtrans_subscription_id` → Cancel locally only

This allows the system to work while you decide on the long-term solution.

## Testing

To verify which flow is being used:

```bash
# Check subscription metadata
php analyze_subscription_metadata.php

# Look for:
# - transaction_id = Snap (one-time)
# - subscription_id = Subscription API (recurring)
```

## Next Steps

1. **Decide**: Snap or Subscription API?
2. **If Subscription API**:
   - Implement checkout flow changes
   - Test with Midtrans sandbox
   - Migrate existing subscriptions
   - Deploy to production
3. **If Snap**:
   - Accept limitations
   - Document manual renewal process
   - Keep current cancellation fix

## References

- [Midtrans Snap Documentation](https://docs.midtrans.com/en/snap/overview)
- [Midtrans Subscription API Documentation](https://docs.midtrans.com/en/subscription/overview)
- Current implementation: `app/Services/MidtransSnapService.php`
- Correct implementation: `app/Services/MidtransSubscriptionService.php`
