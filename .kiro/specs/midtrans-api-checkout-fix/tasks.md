# Midtrans API Checkout Fix - Tasks

## 1. Create MidtransSnapService

- [ ] 1.1 Create `app/Services/MidtransSnapService.php` class
  - Implement constructor to load Midtrans configuration
  - Set base URL based on environment (sandbox/production)
  - Store server_key and client_key from config

- [ ] 1.2 Implement `isConfigured()` method
  - Check if server_key is not empty
  - Check if client_key is not empty
  - Return boolean result

- [ ] 1.3 Implement `createSubscriptionSnapToken()` method
  - Validate plan_id exists in config
  - Generate unique order_id (SUB-{user_id}-{timestamp}-{random})
  - Build transaction payload with item_details, customer_details
  - Add custom fields for subscription metadata
  - Make HTTP POST request to Midtrans Snap API with Basic Auth
  - Handle successful response (extract token and redirect_url)
  - Handle error responses (log and return null)
  - Add comprehensive logging for debugging

## 2. Update API Controller

- [ ] 2.1 Update `Api\SubscriptionController` constructor
  - Inject `MidtransSnapService` dependency
  - Remove or keep `PolarService` for backward compatibility (if needed)

- [ ] 2.2 Update `createCheckout()` method
  - Replace Polar service calls with Midtrans Snap service
  - Check if Midtrans is configured using `isConfigured()`
  - Call `createSubscriptionSnapToken()` to get Snap token
  - Return success response with redirect_url and snap_token
  - Return 503 error if not configured
  - Return 500 error if Snap token creation fails
  - Add logging for checkout attempts

## 3. Update Frontend

- [ ] 3.1 Update `resources/views/welcome.blade.php` checkout function
  - Update response parsing to access nested `data.data.redirect_url`
  - Update error handling to access `data.error.message`
  - Ensure error messages are displayed to user
  - Test with both success and error scenarios

## 4. Testing

- [ ] 4.1 Create unit tests for `MidtransSnapService`
  - Test `isConfigured()` with various config states
  - Test `createSubscriptionSnapToken()` with mocked HTTP responses
  - Test error handling for API failures
  - Test payload generation

- [ ] 4.2 Create integration test for checkout flow
  - Test successful checkout with sandbox credentials
  - Test error handling for invalid plan_id
  - Test error handling for missing configuration
  - Test authentication requirements

- [ ] 4.3 Manual testing
  - Test checkout flow in sandbox environment
  - Verify redirect to Midtrans Snap page
  - Complete payment and verify webhook handling
  - Test error scenarios

## 5. Documentation

- [ ] 5.1 Update API documentation
  - Document new response format for `/subscription/checkout`
  - Document error codes and messages
  - Add example requests and responses

- [ ] 5.2 Update configuration documentation
  - Document required Midtrans environment variables
  - Add troubleshooting guide for common issues
  - Document testing with sandbox credentials

## 6. Deployment

- [ ] 6.1 Verify production configuration
  - Ensure MIDTRANS_SERVER_KEY is set
  - Ensure MIDTRANS_CLIENT_KEY is set
  - Ensure MIDTRANS_IS_PRODUCTION is set correctly
  - Test configuration validation

- [ ] 6.2 Deploy changes
  - Deploy code changes
  - Monitor error logs
  - Monitor checkout success rate
  - Be ready to rollback if issues occur

- [ ] 6.3 Post-deployment verification
  - Test checkout flow in production
  - Verify Snap redirect works
  - Verify webhook handling works
  - Monitor for any errors
