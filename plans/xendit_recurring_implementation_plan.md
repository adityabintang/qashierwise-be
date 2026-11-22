# Xendit Recurring Payment Implementation Plan

## Overview

This document outlines the implementation plan for **replacing Midtrans** with **Xendit Recurring/Subscriptions API** for the pricing page feature. The existing Midtrans subscription system will be migrated entirely to Xendit.

## Current System Analysis

### Existing Architecture
- **Provider**: Xendit Recurring (replacing Midtrans)
- **Subscription Model**: `app/Models/Subscription.php` - update to use Xendit IDs
- **New Payment Flow**: Checkout → Xendit Recurring → Payment Linking → Webhook → Update subscription
- **Config**: `config/subscription.php` - change provider from 'midtrans' to 'xendit'

### Xendit API Flow (from documentation)
1. **Create Customer**: POST `/customers` - Create a customer object in Xendit
2. **Create Recurring Plan**: POST `/recurring/plans` - Create subscription plan
3. **Redirect to Xendit**: Use `actions[].url` from response for payment method linking
4. **Handle Webhooks**: Listen for:
   - `recurring.plan.activated` - Plan activated
   - `recurring.plan.inactivated` - Plan deactivated
   - `recurring.cycle.succeeded` - Payment successful
   - `recurring.cycle.failed` - Payment failed

---

## Implementation Steps

### Phase 1: Configuration Updates

#### 1.1 Update `config/subscription.php`
- Add Xendit configuration section
- Add Xendit-specific URLs
- Update provider options to include 'xendit'

```php
// New config section
'xendit' => [
    'api_key' => env('XENDIT_API_KEY'),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
    'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
],
'urls' => [
    'success' => env('XENDIT_SUBSCRIPTION_SUCCESS_URL', env('APP_URL').'/subscription/success'),
    'cancel' => env('XENDIT_SUBSCRIPTION_CANCEL_URL', env('APP_URL').'/subscription/cancel'),
    'error' => env('XENDIT_SUBSCRIPTION_ERROR_URL', env('APP_URL').'/subscription/error'),
]
```

#### 1.2 Update `.env.example`
- Add Xendit subscription-related environment variables

---

### Phase 2: Database Migration

#### 2.1 Update Subscriptions Table
Replace Midtrans fields with Xendit fields:

```php
// Rename columns to Xendit (or create new and drop old)
$table->renameColumn('midtrans_subscription_id', 'old_midtrans_subscription_id');
$table->renameColumn('midtrans_customer_id', 'old_midtrans_customer_id');

// Add new Xendit columns
$table->string('xendit_subscription_id')->nullable();
$table->string('xendit_customer_id')->nullable();
```

**Note**: Keep old Midtrans columns temporarily for reference during migration, then drop after migration is complete.

---

### Phase 3: Create Xendit Subscription Service

#### 3.1 Create `app/Services/XenditSubscriptionService.php`

**Key Methods:**

1. **`createCustomer(User $user): string`**
   - Creates Xendit customer object
   - Returns `customer_id`
   - Uses: `POST /customers`

2. **`createRecurringPlan(User $user, string $planId, string $duration): array`**
   - Creates subscription plan in Xendit
   - Returns: `['subscription_id' => string, 'action_url' => string, 'status' => string]`
   - Uses: `POST /recurring/plans`

3. **`getRecurringPlan(string $planId): array`**
   - Gets subscription plan details
   - Uses: `GET /recurring/plans/{id}`

4. **`pauseRecurringPlan(string $planId): array`**
   - Pauses subscription
   - Uses: `POST /recurring/plans/{id}/pause`

5. **`resumeRecurringPlan(string $planId): array`**
   - Resumes subscription
   - Uses: `POST /recurring/plans/{id}/resume`

6. **`stopRecurringPlan(string $planId): array`**
   - Stops/cancels subscription
   - Uses: `POST /recurring/plans/{id}/stop`

---

### Phase 4: Update Subscription Model

#### 4.1 Update `app/Models/Subscription.php`

Replace Midtrans fields with Xendit fields:

```php
// Update fillable - replace midtrans_ with xendit_
protected $fillable = [
    // existing...
    'xendit_subscription_id',  // replaces midtrans_subscription_id
    'xendit_customer_id',      // replaces midtrans_customer_id
    // Remove: midtrans_subscription_id, midtrans_customer_id
];

// Add helper method
public function getProviderSubscriptionId(): ?string
{
    return $this->xendit_subscription_id;
}
```

---

### Phase 5: Update Subscription Controller

#### 5.1 Update `app/Http/Controllers/Api/SubscriptionController.php`

**New Endpoint: Create Xendit Checkout**
```php
public function createXenditCheckout(Request $request): JsonResponse
{
    // Similar to createCheckout but uses XenditSubscriptionService
    // Returns: { checkout_url, subscription_id }
}
```

**New Endpoint: Xendit Return**
```php
public function xenditReturn(Request $request): JsonResponse
{
    // Handle redirect from Xendit after payment linking
}
```

---

### Phase 6: Handle Xendit Webhooks

#### 6.1 Update `app/Http/Controllers/Api/XenditWebhookController.php`

**Add handlers for:**

1. **`recurring.plan.activated`**
   - Update subscription status to 'active'
   - Record payment in SubscriptionPayment

2. **`recurring.plan.inactivated`**
   - Update subscription status to 'inactive'/'cancelled'

3. **`recurring.cycle.succeeded`**
   - Record successful payment
   - Extend subscription period

4. **`recurring.cycle.failed`**
   - Handle failed payment
   - Update subscription status if max retries reached

---

### Phase 7: Update Subscription Service

#### 7.1 Update `app/Services/SubscriptionService.php`

Replace Midtrans webhook processing with Xendit:

```php
// Remove: processMidtransWebhook()
// Replace with: processXenditWebhook()

public function processXenditWebhook(array $payload): void
{
    $event = $payload['event'] ?? '';
    $data = $payload['data'] ?? [];
    
    match($event) {
        'recurring.plan.activated' => $this->handleXenditActivation($data),
        'recurring.plan.inactivated' => $this->handleXenditDeactivation($data),
        'recurring.cycle.succeeded' => $this->handleXenditCycleSuccess($data),
        'recurring.cycle.failed' => $this->handleXenditCycleFailed($data),
    };
}
```

---

### Phase 8: Frontend Integration

#### 8.1 Update Pricing Page
- Add Xendit as payment option
- Create checkout flow for Xendit

---

## API Reference Summary

### Endpoints Used

| Xendit API | Method | Purpose |
|------------|--------|---------|
| `/customers` | POST | Create customer |
| `/recurring/plans` | POST | Create subscription |
| `/recurring/plans/{id}` | GET | Get plan details |
| `/recurring/plans/{id}/pause` | POST | Pause subscription |
| `/recurring/plans/{id}/resume` | POST | Resume subscription |
| `/recurring/plans/{id}/stop` | POST | Stop subscription |

### Webhook Events

| Event | Description |
|-------|-------------|
| `recurring.plan.activated` | Subscription activated |
| `recurring.plan.inactivated` | Subscription deactivated |
| `recurring.cycle.succeeded` | Payment succeeded |
| `recurring.cycle.failed` | Payment failed |
| `recurring.cycle.created` | New cycle created |
| `recurring.cycle.retrying` | Retry scheduled |

---

## File Changes Summary

### New Files
1. `app/Services/XenditSubscriptionService.php` - Main Xendit recurring service

### Modified Files
1. `config/subscription.php` - Add Xendit config
2. `app/Models/Subscription.php` - Add Xendit fields
3. `app/Http/Controllers/Api/SubscriptionController.php` - Add Xendit endpoints
4. `app/Http/Controllers/Api/XenditWebhookController.php` - Add recurring handlers
5. `app/Services/SubscriptionService.php` - Add Xendit webhook processing
6. `.env.example` - Add Xendit env vars
7. Database migration file

---

## Implementation Priority

1. **Phase 1-2**: Configuration & Database (Foundation)
2. **Phase 3-4**: Core Service & Model (Business Logic)
3. **Phase 5-6**: Controllers & Webhooks (API Integration)
4. **Phase 7-8**: Testing & Frontend (Completion)

---

## Notes

- Xendit recurring requires customer creation first
- Payment method linking happens on Xendit-hosted page
- Webhooks are critical for subscription lifecycle management
- **Replacing Midtrans entirely** - remove Midtrans subscription code after migration
- Migrate existing Midtrans subscriptions to Xendit (optional based on business need)
