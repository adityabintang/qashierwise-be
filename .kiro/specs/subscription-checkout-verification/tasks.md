# Implementation Plan

- [x] 1. Add getCheckoutByToken method to PolarService






  - [x] 1.1 Implement getCheckoutByToken method in PolarService

    - Add method to fetch checkout session using customer_session_token
    - Handle Polar API response and extract subscription_id, customer_id, product_id, metadata
    - Return null on error with proper logging
    - _Requirements: 4.1, 4.2, 4.3_
  - [ ]* 1.2 Write property test for getCheckoutByToken
    - **Property 1: Token format validation rejects invalid formats**
    - **Validates: Requirements 2.2**






- [x] 2. Implement verifyCheckout endpoint in SubscriptionController


  - [x] 2.1 Add token validation logic


    - Validate customer_session_token is present and not empty
    - Validate token format matches polar_cst_[a-zA-Z0-9]+ pattern
    - Return appropriate error codes (INVALID_TOKEN, INVALID_TOKEN_FORMAT)
    - _Requirements: 2.1, 2.2_
  - [x] 2.2 Implement verification flow


    - Check Polar service is configured


    - Call PolarService::getCheckoutByToken to fetch checkout data
    - Validate user ownership (metadata.user_id matches authenticated user)
    - Call PolarService::getSubscription to fetch full subscription details
    - Call SubscriptionService::createOrUpdateSubscription to persist
    - Return subscription status in response
    - _Requirements: 1.2, 1.3, 1.4, 3.2, 3.3_
  - [x] 2.3 Implement error handling







    - Handle POLAR_CONFIG_MISSING (503)
    - Handle VERIFICATION_FAILED (500)
    - Handle UNAUTHORIZED_USER (403)
    - Log all errors for debugging
    - _Requirements: 2.3, 2.4, 2.5_
  - [ ]* 2.4 Write property test for user ownership validation
    - **Property 4: User ownership validation**
    - **Validates: Requirements 3.3**
  - [ ]* 2.5 Write property test for subscription creation
    - **Property 2: Successful verification creates subscription record**
    - **Validates: Requirements 1.3**


- [x] 3. Add API route for verify-checkout endpoint





  - [x] 3.1 Register route in routes/api.php

    - Add POST /api/subscription/verify-checkout route
    - Ensure route is within auth:sanctum middleware group
    - _Requirements: 1.1, 3.1_


- [x] 4. Checkpoint - Make sure all tests are passing




  - Ensure all tests pass, ask the user if questions arise.

- [ ]* 5. Write property tests for idempotency
  - [ ]* 5.1 Write property test for idempotent verification
    - **Property 5: Idempotent verification**
    - **Validates: Requirements 5.1, 5.2, 5.3**
  - [ ]* 5.2 Write property test for response consistency
    - **Property 3: Verification response contains subscription status**
    - **Validates: Requirements 1.4**
  - [ ]* 5.3 Write property test for subscription association
    - **Property 6: Subscription association with authenticated user**
    - **Validates: Requirements 3.2**

- [ ]* 6. Write unit tests for verification endpoint
  - [ ]* 6.1 Write unit tests for token validation
    - Test empty token returns INVALID_TOKEN
    - Test invalid format returns INVALID_TOKEN_FORMAT
    - Test valid format passes validation
    - _Requirements: 2.1, 2.2_
  - [ ]* 6.2 Write unit tests for error scenarios
    - Test unauthenticated request returns 401
    - Test Polar not configured returns 503
    - Test checkout not found returns VERIFICATION_FAILED
    - _Requirements: 2.3, 2.4, 3.1_
  - [ ]* 6.3 Write integration test for full verification flow
    - Test successful verification creates subscription
    - Test verification updates existing subscription
    - _Requirements: 1.3, 5.1, 5.2_



- [x] 7. Final Checkpoint - Make sure all tests are passing



  - Ensure all tests pass, ask the user if questions arise.
