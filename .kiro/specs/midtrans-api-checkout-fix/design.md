# Midtrans API Checkout Fix - Design

## 1. Architecture Overview

The fix involves updating the API subscription controller to integrate with Midtrans Snap instead of Polar. The flow will be:

```
Frontend (Alpine.js) → API Controller → Midtrans Snap Service → Midtrans Snap API
                                ↓
                          Return redirect_url
                                ↓
                    Frontend redirects to Midtrans
```

## 2. Component Design

### 2.1 MidtransSnapService

Create a new service class to handle Midtrans Snap integration for subscriptions.

**Location:** `app/Services/MidtransSnapService.php`

**Responsibilities:**
- Create Snap payment tokens for subscription checkout
- Configure transaction details with subscription metadata
- Handle Snap API responses and errors
- Provide configuration validation

**Key Methods:**

```php
class MidtransSnapService
{
    /**
     * Create a Snap token for subscription checkout.
     * 
     * @param User $user
     * @param string $planId
     * @return array|null ['snap_token' => string, 'redirect_url' => string] or null on failure
     */
    public function createSubscriptionSnapToken(User $user, string $planId): ?array;
    
    /**
     * Check if Midtrans Snap is properly configured.
     * 
     * @return bool
     */
    public function isConfigured(): bool;
}
```

**Configuration:**
- Use `config('midtrans.server_key')` for API authentication
- Use `config('midtrans.client_key')` for frontend integration
- Use `config('midtrans.is_production')` for environment selection
- Snap URL: `https://app.midtrans.com/snap/v1/transactions` (production)
- Snap URL: `https://app.sandbox.midtrans.com/snap/v1/transactions` (sandbox)

**Transaction Payload:**

```php
[
    'transaction_details' => [
        'order_id' => 'SUB-{user_id}-{timestamp}-{random}',
        'gross_amount' => (int) $plan['price'],
    ],
    'item_details' => [
        [
            'id' => $planId,
            'price' => (int) $plan['price'],
            'quantity' => 1,
            'name' => "{$plan['name']} Subscription",
        ],
    ],
    'customer_details' => [
        'first_name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone ?? '',
    ],
    'custom_field1' => $planId,
    'custom_field2' => 'subscription',
    'custom_field3' => (string) $user->id,
]
```

### 2.2 API Controller Update

Update `Api\SubscriptionController::createCheckout()` to use Midtrans Snap.

**Changes:**
1. Inject `MidtransSnapService` instead of `PolarService`
2. Call `createSubscriptionSnapToken()` to get Snap token
3. Return response with `redirect_url` and `snap_token`
4. Handle errors with proper status codes

**Response Format:**

Success (200):
```json
{
    "success": true,
    "data": {
        "redirect_url": "https://app.midtrans.com/snap/v3/redirection/{snap_token}",
        "snap_token": "abc123..."
    }
}
```

Invalid Plan (422):
```json
{
    "success": false,
    "error": {
        "code": "INVALID_PLAN",
        "message": "Invalid plan selected"
    },
    "errors": {...}
}
```

Not Configured (503):
```json
{
    "success": false,
    "error": {
        "code": "MIDTRANS_CONFIG_MISSING",
        "message": "Subscription service is not configured"
    }
}
```

Checkout Failed (500):
```json
{
    "success": false,
    "error": {
        "code": "CHECKOUT_FAILED",
        "message": "Failed to create checkout session"
    }
}
```

### 2.3 Frontend Compatibility

The existing frontend code expects:
```javascript
const data = await response.json();
if (response.ok && data.redirect_url) {
    window.location.href = data.redirect_url;
}
```

This will work with the new response format since `data.redirect_url` will be present in `data.data.redirect_url`. We need to adjust the frontend to access the nested property.

**Frontend Update:**
```javascript
const data = await response.json();
if (response.ok && data.data?.redirect_url) {
    window.location.href = data.data.redirect_url;
} else {
    this.error = data.error?.message || 'Gagal membuat sesi checkout. Silakan coba lagi.';
}
```

## 3. Data Flow

### 3.1 Successful Checkout Flow

1. User clicks "Subscribe" button on welcome page
2. Frontend calls `POST /subscription/checkout` with Bearer token and `{ plan_id: "standard" }`
3. API controller validates plan_id
4. API controller checks Midtrans configuration
5. API controller calls `MidtransSnapService::createSubscriptionSnapToken()`
6. Snap service creates transaction payload
7. Snap service calls Midtrans Snap API
8. Midtrans returns snap_token and redirect_url
9. API controller returns response with redirect_url
10. Frontend redirects user to Midtrans payment page
11. User completes payment on Midtrans
12. Midtrans sends webhook to `/midtrans/webhook`
13. Webhook handler creates/updates subscription record

### 3.2 Error Flow

1. User clicks "Subscribe" button
2. Frontend calls API endpoint
3. API controller detects missing configuration
4. API controller returns 503 error
5. Frontend displays error message to user
6. User contacts support or admin fixes configuration

## 4. Security Considerations

### 4.1 Authentication
- Require Bearer token authentication for API endpoint
- Validate user ownership of subscription

### 4.2 Data Validation
- Validate plan_id against allowed values
- Sanitize user input before sending to Midtrans
- Validate Midtrans response format

### 4.3 Configuration Security
- Store server_key in environment variables
- Never expose server_key to frontend
- Use HTTPS for all Midtrans API calls

## 5. Error Handling

### 5.1 Configuration Errors
- Check for missing server_key, client_key
- Return 503 with clear error message
- Log configuration issues

### 5.2 API Errors
- Handle Midtrans API timeouts (30s timeout)
- Handle invalid response formats
- Handle network errors
- Log all errors with context

### 5.3 Validation Errors
- Validate plan_id before API call
- Return 422 with validation errors
- Provide clear error messages

## 6. Testing Strategy

### 6.1 Unit Tests
- Test `MidtransSnapService::createSubscriptionSnapToken()` with mocked HTTP responses
- Test configuration validation
- Test error handling for various failure scenarios

### 6.2 Integration Tests
- Test full checkout flow with sandbox Midtrans
- Test error responses
- Test authentication requirements

### 6.3 Manual Testing
- Test checkout with sandbox credentials
- Verify redirect to Midtrans Snap page
- Verify webhook handling after payment
- Test error scenarios (invalid plan, missing config)

## 7. Deployment Considerations

### 7.1 Configuration
- Ensure `MIDTRANS_SERVER_KEY` is set in production
- Ensure `MIDTRANS_CLIENT_KEY` is set in production
- Set `MIDTRANS_IS_PRODUCTION=true` in production

### 7.2 Rollback Plan
- If issues occur, revert API controller changes
- Frontend will show error messages
- Users can retry checkout after fix

### 7.3 Monitoring
- Monitor checkout success rate
- Monitor Midtrans API response times
- Alert on configuration errors
- Track error rates by error code

## 8. Future Enhancements

- Support for multiple payment methods (not just credit card)
- Support for promo codes/discounts
- Support for annual billing
- Retry logic for failed Snap API calls
- Caching of Snap tokens (if applicable)
