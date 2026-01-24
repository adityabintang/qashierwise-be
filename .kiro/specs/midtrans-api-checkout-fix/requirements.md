# Midtrans API Checkout Fix - Requirements

## 1. Overview

The subscription checkout flow is currently broken because the API endpoint (`/subscription/checkout`) is configured for Polar integration, but the frontend expects Midtrans integration with a `redirect_url` response. This causes a JSON parsing error when users attempt to checkout.

## 2. Problem Statement

**Current Behavior:**
- Frontend calls `POST /subscription/checkout` with Bearer token authentication
- API route uses `Api\SubscriptionController::createCheckout()` which is configured for Polar
- Polar integration returns a different response format without `redirect_url`
- Frontend expects `{ redirect_url: "..." }` for Midtrans Snap payment page
- This mismatch causes: `SyntaxError: JSON.parse: unexpected character at line 1 column 1`

**Root Cause:**
The API subscription controller is hardcoded to use Polar service, but the application has migrated to Midtrans for subscription payments.

## 3. User Stories

### 3.1 As a user, I want to successfully checkout for a subscription plan
**Acceptance Criteria:**
- When I click a subscription plan checkout button on the welcome page
- The system creates a Midtrans Snap payment session
- I receive a valid `redirect_url` in the response
- I am redirected to the Midtrans payment page
- No JSON parsing errors occur

### 3.2 As a user, I want clear error messages when checkout fails
**Acceptance Criteria:**
- When checkout fails due to configuration issues, I see: "Subscription service is not configured"
- When checkout fails due to API errors, I see: "Failed to create checkout session. Please try again."
- Error messages are displayed in the UI, not as console errors

### 3.3 As a developer, I want the API to support Midtrans checkout
**Acceptance Criteria:**
- The API endpoint `/subscription/checkout` creates Midtrans Snap tokens
- The response includes `redirect_url` pointing to Midtrans Snap payment page
- The endpoint validates plan_id (standard, pro)
- The endpoint requires authentication via Bearer token
- The endpoint returns proper HTTP status codes (200, 422, 500, 503)

## 4. Technical Requirements

### 4.1 API Endpoint Modification
- Update `Api\SubscriptionController::createCheckout()` to use Midtrans instead of Polar
- Integrate with Midtrans Snap API to generate payment tokens
- Return response format: `{ success: true, data: { redirect_url: string, snap_token: string } }`

### 4.2 Midtrans Snap Integration
- Use Midtrans Snap API to create payment tokens for subscriptions
- Configure Snap with proper redirect URLs (success, error, cancel)
- Pass subscription metadata (user_id, plan_id) to Midtrans
- Handle Snap API errors gracefully

### 4.3 Error Handling
- Return 422 for invalid plan_id
- Return 503 when Midtrans is not configured
- Return 500 for Snap API failures
- Log all errors with context for debugging

### 4.4 Configuration
- Use existing Midtrans configuration from `config/midtrans.php`
- Support both sandbox and production environments
- Validate required configuration keys (server_key, client_key)

## 5. Out of Scope

- Webhook handling (already implemented)
- Subscription management UI changes
- Payment method selection (Snap handles this)
- Subscription cancellation flow
- Migration from Polar to Midtrans (already completed)

## 6. Dependencies

- Midtrans PHP SDK (already installed)
- Existing Midtrans configuration
- Existing subscription models and database schema

## 7. Success Metrics

- Zero JSON parsing errors on checkout
- Successful redirect to Midtrans Snap payment page
- Proper error messages displayed to users
- All checkout attempts logged for monitoring

## 8. Constraints

- Must maintain backward compatibility with existing subscription records
- Must not break webhook handling
- Must work with existing frontend code (minimal changes)
- Must support both sandbox and production Midtrans environments
