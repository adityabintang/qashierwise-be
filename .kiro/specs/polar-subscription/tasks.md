# Implementation Plan

- [x] 1. Set up Polar.sh configuration and dependencies






  - [x] 1.1 Install Polar.sh PHP SDK via composer

    - Run `composer require polar-sh/sdk`
    - _Requirements: 1.1_
  - [x] 1.2 Create config/polar.php configuration file


    - Define api_token, webhook_secret, products mapping, URLs, trial_days
    - _Requirements: 1.1, 2.1_

  - [x] 1.3 Add Polar.sh environment variables to .env.example

    - Add POLAR_API_TOKEN, POLAR_WEBHOOK_SECRET, POLAR_PRODUCT_STANDARD, POLAR_PRODUCT_PRO, POLAR_SUCCESS_URL, POLAR_CANCEL_URL
    - _Requirements: 1.1_


  - [x] 1.4 Write unit tests for configuration loading





    - Test config values are loaded from environment
    - Test missing credentials handling
    - _Requirements: 1.1, 1.2_

- [x] 2. Create DTOs and data structures





  - [x] 2.1 Create PlanDetails DTO


    - Implement constructor, toArray(), fromArray() methods
    - Include id, name, priceMonthly, polarProductId, features, tier
    - _Requirements: 2.2_
  - [x] 2.2 Write property test for PlanDetails serialization round-trip


    - **Property 14: Subscription Serialization Round-Trip (adapted for PlanDetails)**
    - **Validates: Requirements 7.4**
  - [x] 2.3 Create SubscriptionStatus DTO


    - Implement constructor, toArray(), fromArray() methods
    - Include status, planName, trialDaysRemaining, periodEnd, cancelledAt
    - _Requirements: 5.1, 5.4_
  - [x] 2.4 Write property test for SubscriptionStatus serialization round-trip


    - **Property 14: Subscription Serialization Round-Trip**
    - **Validates: Requirements 7.4**
  - [x] 2.5 Create CheckoutSession DTO


    - Implement constructor with id, url, planId, userEmail, successUrl, cancelUrl
    - _Requirements: 3.4_

- [x] 3. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Create database migration and Subscription model



  - [x] 4.1 Create subscriptions table migration
    - Add user_id, polar_subscription_id, polar_customer_id, plan_name, status, period dates, cancelled_at
    - Add indexes for user_id, status, polar_subscription_id
    - _Requirements: 7.1, 7.3_

  - [x] 4.2 Create Subscription model
    - Define fillable, casts, relationships
    - Implement isActive(), isCancelled(), isExpired() methods

    - _Requirements: 7.1, 7.3_

  - [x] 4.3 Add subscription relationship to User model
    - Add hasOne relationship to Subscription
    - _Requirements: 5.1_

  - [x] 4.4 Write unit tests for Subscription model methods

    - Test isActive(), isCancelled(), isExpired() with various states
    - _Requirements: 5.4, 5.5_

- [x] 5. Implement PlanConfig service

  - [x] 5.1 Create PlanConfig service class
    - Implement getPlan(), getAllPlans(), getPolarProductId(), getFeaturesByTier()
    - Define plan configurations for free_trial, standard, pro
    - _Requirements: 2.1, 2.2, 2.3_
  - [x] 5.2 Write property test for plan configuration mapping
    - **Property 1: Plan Configuration Mapping Consistency**
    - **Validates: Requirements 2.1, 2.2**
  - [x] 5.3 Write property test for plan details completeness

    - **Property 2: Plan Details Completeness**
    - **Validates: Requirements 2.2**

- [x] 6. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Implement PolarService for API communication






  - [x] 7.1 Create PolarService class

    - Implement createCheckoutSession(), getCustomerPortalUrl(), validateWebhookSignature(), getSubscription()
    - Handle API errors gracefully
    - _Requirements: 3.1, 3.2, 4.1_

  - [x] 7.2 Write property test for webhook signature validation

    - **Property 4: Webhook Signature Validation Correctness**
    - **Validates: Requirements 4.1**

  - [x] 7.3 Write property test for checkout session data integrity

    - **Property 3: Checkout Session Data Integrity**
    - **Validates: Requirements 3.4**


- [x] 8. Implement SubscriptionService business logic




  - [x] 8.1 Create SubscriptionService class


    - Implement getUserSubscriptionStatus(), processWebhookEvent(), createOrUpdateSubscription()
    - Implement cancelSubscription(), calculateTrialDaysRemaining(), canAccessFeature()
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 9.1, 9.2, 9.4_

  - [x] 8.2 Write property test for default trial status

    - **Property 8: Default Trial Status for New Users**
    - **Validates: Requirements 5.2**
  - [x] 8.3 Write property test for trial expiration detection

    - **Property 9: Trial Expiration Detection**
    - **Validates: Requirements 5.3**

  - [x] 8.4 Write property test for trial days calculation
    - **Property 12: Trial Days Calculation**
    - **Validates: Requirements 6.2**

  - [x] 8.5 Write property test for active subscription status
    - **Property 10: Active Subscription Status**
    - **Validates: Requirements 5.4**
  - [x] 8.6 Write property test for cancelled but active subscription

    - **Property 11: Cancelled But Active Subscription**
    - **Validates: Requirements 5.5**

- [x] 9. Checkpoint - Ensure all tests pass














  - Ensure all tests pass, ask the user if questions arise.


- [x] 10. Implement webhook event processing






  - [x] 10.1 Implement subscription.created event handler


    - Create or update subscription with active status
    - _Requirements: 4.2_
  - [x] 10.2 Write property test for subscription created event


    - **Property 5: Subscription Created Event Processing**
    - **Validates: Requirements 4.2**
  - [x] 10.3 Implement subscription.updated event handler


    - Update subscription status and plan
    - _Requirements: 4.3_
  - [x] 10.4 Write property test for subscription updated event


    - **Property 6: Subscription Updated Event Processing**
    - **Validates: Requirements 4.3**
  - [x] 10.5 Implement subscription.cancelled event handler


    - Mark subscription as cancelled with timestamp
    - _Requirements: 4.4_
  - [x] 10.6 Write property test for subscription cancelled event


    - **Property 7: Subscription Cancelled Event Processing**
    - **Validates: Requirements 4.4**

- [x] 11. Implement feature access control
  - [x] 11.1 Implement canAccessFeature() method in SubscriptionService
    - Check user subscription tier against required feature tier
    - _Requirements: 9.1, 9.2, 9.4_
  - [x] 11.2 Write property test for standard tier access
    - **Property 15: Standard Tier Feature Access**
    - **Validates: Requirements 9.1**
  - [x] 11.3 Write property test for pro tier access
    - **Property 16: Pro Tier Feature Access**
    - **Validates: Requirements 9.2**
  - [x] 11.4 Write property test for trial user restrictions

    - **Property 17: Trial User Feature Access Restriction**
    - **Validates: Requirements 9.4**

- [x] 12. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. Create API controllers






  - [x] 13.1 Create SubscriptionController

    - Implement status(), createCheckout(), getPortalUrl() endpoints
    - Add request validation
    - _Requirements: 3.1, 5.1, 6.5_
  - [x] 13.2 Create PolarWebhookController


    - Implement handle() method with signature validation
    - Route events to appropriate handlers
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_


  - [x] 13.3 Register API routes
    - Add routes for /api/subscription/status, /api/subscription/checkout, /api/subscription/portal
    - Add webhook route /api/webhooks/polar

    - _Requirements: 3.1, 4.1_
  - [x] 13.4 Write feature tests for subscription endpoints

    - Test status endpoint returns correct subscription info
    - Test checkout endpoint creates session
    - Test webhook endpoint validates signature
    - _Requirements: 3.1, 4.1, 5.1_

- [x] 14. Checkpoint - Ensure all tests pass




  - Ensure all tests pass, ask the user if questions arise.

- [x] 15. Update welcome page pricing section






  - [x] 15.1 Update pricing buttons to trigger checkout

    - Connect "Pilih Standard" and "Pilih Pro" buttons to checkout API
    - Keep "Mulai Gratis" button linking to registration
    - _Requirements: 8.2, 8.3_

  - [x] 15.2 Add current plan badge for logged-in users

    - Display "Current Plan" badge on active subscription tier
    - _Requirements: 8.4_

- [x] 16. Update dashboard with subscription status




  - [x] 16.1 Create subscription status API endpoint integration

    - Fetch subscription status on dashboard load
    - _Requirements: 6.1_

  - [x] 16.2 Add subscription status card to dashboard
    - Display plan name, status, and relevant dates

    - _Requirements: 6.1, 6.4_
  - [x] 16.3 Add trial countdown for trial users

    - Display remaining trial days with upgrade prompt
    - _Requirements: 6.2_
  - [x] 16.4 Add trial expired banner
    - Display prominent upgrade banner when trial expired

    - _Requirements: 6.3_
  - [x] 16.5 Add "Manage Subscription" button

    - Link to Polar.sh customer portal
    - _Requirements: 6.5_



- [x] 17. Final Checkpoint - Ensure all tests pass



  - Ensure all tests pass, ask the user if questions arise.
