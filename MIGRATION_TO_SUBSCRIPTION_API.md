# Migration to Midtrans Subscription API

## Overview

Migrasi dari Midtrans Snap (one-time) ke Subscription API (recurring) untuk mendapatkan:
- ✅ `midtrans_subscription_id` 
- ✅ Auto-renewal setiap bulan
- ✅ Cancellation via API
- ✅ Proper subscription management

## Architecture Changes

### Current Flow (Snap)
```
User → Select Plan → Snap Token → Snap Payment Page → Webhook → Create Subscription
```

### New Flow (Subscription API)
```
User → Select Plan → Snap Token (for first payment) → Get Payment Token → 
Create Subscription → Webhook → Store subscription_id
```

## Key Difference

**Subscription API requires 2 steps:**
1. **First Payment**: User pays first installment (via Snap or saved card)
2. **Create Subscription**: Use payment token to create recurring subscription

## Implementation Plan

### Phase 1: Hybrid Approach (Recommended)
Keep Snap for first payment, then create subscription with the token.

**Advantages:**
- ✅ User-friendly (familiar Snap UI)
- ✅ Gets subscription_id for recurring
- ✅ Minimal code changes
- ✅ Backward compatible

**Flow:**
1. User selects plan
2. Create Snap transaction (first payment)
3. User completes payment via Snap
4. Webhook receives payment success
5. Extract payment token from transaction
6. Create subscription using token
7. Store `midtrans_subscription_id`

### Phase 2: Pure Subscription API
Use Subscription API directly without Snap.

**Advantages:**
- ✅ Cleaner architecture
- ✅ Direct subscription creation

**Disadvantages:**
- ⚠️ Need custom payment form
- ⚠️ More complex UI
- ⚠️ Requires card tokenization

## Recommended: Phase 1 Implementation

Let's implement the hybrid approach as it's the most practical.

### Step 1: Update Webhook Handler

The webhook needs to:
1. Receive Snap payment success
2. Extract payment token
3. Create subscription via Subscription API
4. Store subscription_id

### Step 2: Update Subscription Service

Add method to create subscription from Snap transaction.

### Step 3: Test Flow

1. User subscribes
2. Pays via Snap
3. Webhook creates subscription
4. Verify subscription_id is stored

## Alternative: Midtrans Subscription with Snap Integration

Midtrans actually supports creating subscriptions that use Snap for the first payment!

**API Endpoint:** `POST /v1/subscriptions`

**Payload:**
```json
{
  "name": "Standard Plan Monthly",
  "amount": "99000",
  "currency": "IDR",
  "payment_type": "credit_card",
  "token": "payment_token_from_snap",
  "schedule": {
    "interval": 1,
    "interval_unit": "month",
    "max_interval": 12,
    "start_time": "2026-02-24 00:00:00 +0700"
  },
  "metadata": {
    "user_id": "1",
    "plan_id": "standard"
  },
  "customer_details": {
    "first_name": "User Name",
    "email": "user@example.com"
  }
}
```

**Response:**
```json
{
  "id": "sub_abc123",  ← This is what we need!
  "name": "Standard Plan Monthly",
  "amount": "99000",
  "status": "active",
  "customer_id": "cus_xyz789",
  ...
}
```

## Implementation Code

I'll create the implementation files in the next steps.

### Files to Modify:
1. `app/Services/MidtransSubscriptionService.php` - Add Snap integration
2. `app/Http/Controllers/Api/MidtransWebhookController.php` - Handle subscription creation
3. `app/Http/Controllers/SubscriptionController.php` - Update checkout flow
4. `resources/views/subscription/payment.blade.php` - Update payment page

### Files to Create:
1. Migration script for existing subscriptions
2. Test scripts for new flow

## Testing Strategy

### Sandbox Testing:
1. Create test subscription
2. Verify subscription_id is stored
3. Test auto-renewal (wait for next billing)
4. Test cancellation via API
5. Verify webhook handling

### Production Rollout:
1. Deploy to staging
2. Test with real Midtrans sandbox
3. Migrate existing subscriptions
4. Deploy to production
5. Monitor for issues

## Rollback Plan

If issues occur:
1. Revert to Snap-only flow
2. Keep cancellation fix (already works)
3. Manual renewal process

## Next Steps

Ready to implement? I'll create:
1. Updated webhook handler
2. Modified subscription service
3. Test scripts
4. Migration guide

Shall I proceed with the implementation?
