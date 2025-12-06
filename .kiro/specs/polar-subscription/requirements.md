# Requirements Document

## Introduction

This document specifies the requirements for integrating Polar.sh subscription management into QashierWise. The integration will enable subscription-based billing for the existing pricing tiers (Basic/Free Trial, Standard, Pro) displayed on the welcome page, and provide subscription status detection in the dashboard to differentiate user access levels. The implementation follows Test-Driven Development (TDD) principles.

## Glossary

- **Polar.sh**: A subscription and payment platform that provides APIs for managing subscriptions, customers, and billing
- **Subscription**: A recurring payment arrangement where users pay periodically for access to features
- **Plan**: A subscription tier (Free Trial, Standard, Pro) with specific features and pricing
- **Free Trial**: A 14-day trial period with limited features before requiring a paid subscription
- **Subscription Status**: The current state of a user's subscription (trial, active, cancelled, expired)
- **Checkout Session**: A Polar.sh session that handles the payment flow for subscription purchases
- **Webhook**: An HTTP callback that Polar.sh sends to notify the application of subscription events
- **Customer Portal**: A Polar.sh-hosted page where users can manage their subscription

## Requirements

### Requirement 1: Polar.sh SDK Integration

**User Story:** As a developer, I want to integrate the Polar.sh Laravel SDK, so that the application can communicate with Polar.sh APIs for subscription management.

#### Acceptance Criteria

1. WHEN the application initializes THEN the Subscription_System SHALL load Polar.sh SDK configuration from environment variables
2. WHEN Polar.sh API credentials are missing THEN the Subscription_System SHALL log an error and disable subscription features gracefully
3. WHEN the Polar.sh SDK is configured THEN the Subscription_System SHALL validate the API token connectivity on application boot

### Requirement 2: Subscription Plan Configuration

**User Story:** As a business owner, I want to define subscription plans that match my pricing tiers, so that customers can subscribe to the appropriate plan.

#### Acceptance Criteria

1. WHEN the Subscription_System initializes THEN the Subscription_System SHALL map internal plan identifiers (free_trial, standard, pro) to Polar.sh product IDs
2. WHEN a plan configuration is requested THEN the Subscription_System SHALL return plan details including name, price, features, and Polar.sh product ID
3. WHEN a plan identifier is invalid THEN the Subscription_System SHALL return a null value without throwing an exception

### Requirement 3: Checkout Session Creation

**User Story:** As a visitor on the pricing page, I want to click a subscription button and be redirected to a secure checkout, so that I can subscribe to a plan.

#### Acceptance Criteria

1. WHEN a user clicks a subscription button on the pricing section THEN the Subscription_System SHALL create a Polar.sh checkout session with the selected plan
2. WHEN a checkout session is created successfully THEN the Subscription_System SHALL redirect the user to the Polar.sh checkout URL
3. WHEN checkout session creation fails THEN the Subscription_System SHALL display an error message and remain on the pricing page
4. WHEN a checkout session is created THEN the Subscription_System SHALL include the user's email and a success/cancel callback URL

### Requirement 4: Webhook Event Processing

**User Story:** As a system administrator, I want the application to receive and process Polar.sh webhook events, so that subscription statuses are updated in real-time.

#### Acceptance Criteria

1. WHEN Polar.sh sends a webhook event THEN the Subscription_System SHALL validate the webhook signature before processing
2. WHEN a subscription.created event is received THEN the Subscription_System SHALL create or update the user's subscription record with active status
3. WHEN a subscription.updated event is received THEN the Subscription_System SHALL update the subscription record with the new status and plan
4. WHEN a subscription.cancelled event is received THEN the Subscription_System SHALL mark the subscription as cancelled and record the cancellation date
5. WHEN a webhook signature is invalid THEN the Subscription_System SHALL reject the request with a 401 status code
6. WHEN a webhook event is processed successfully THEN the Subscription_System SHALL return a 200 status code

### Requirement 5: User Subscription Status Detection

**User Story:** As a dashboard user, I want the system to detect my subscription status, so that I can see my current plan and access appropriate features.

#### Acceptance Criteria

1. WHEN a user accesses the dashboard THEN the Subscription_System SHALL retrieve the user's current subscription status
2. WHEN a user has no subscription record THEN the Subscription_System SHALL treat the user as being on the free trial plan
3. WHEN a user's free trial has expired (more than 14 days since registration) THEN the Subscription_System SHALL indicate trial_expired status
4. WHEN a user has an active subscription THEN the Subscription_System SHALL return the plan name (standard or pro) and expiration date
5. WHEN a user's subscription is cancelled but not yet expired THEN the Subscription_System SHALL indicate the subscription remains active until the period end date

### Requirement 6: Dashboard Subscription Display

**User Story:** As a dashboard user, I want to see my current subscription status prominently displayed, so that I know my plan and can upgrade if needed.

#### Acceptance Criteria

1. WHEN a user views the dashboard THEN the Dashboard_UI SHALL display the current subscription plan name and status
2. WHEN a user is on free trial THEN the Dashboard_UI SHALL display the remaining trial days and an upgrade prompt
3. WHEN a user's trial has expired THEN the Dashboard_UI SHALL display a prominent upgrade banner with limited functionality notice
4. WHEN a user has an active paid subscription THEN the Dashboard_UI SHALL display the plan name and next billing date
5. WHEN a user clicks "Manage Subscription" THEN the Dashboard_UI SHALL redirect to the Polar.sh customer portal

### Requirement 7: Subscription Data Persistence

**User Story:** As a system administrator, I want subscription data stored in the database, so that subscription status can be queried efficiently without external API calls.

#### Acceptance Criteria

1. WHEN a subscription is created or updated THEN the Subscription_System SHALL store the subscription ID, user ID, plan, status, and dates in the database
2. WHEN querying subscription status THEN the Subscription_System SHALL read from the local database for performance
3. WHEN subscription data is persisted THEN the Subscription_System SHALL include polar_subscription_id, polar_customer_id, plan_name, status, current_period_start, current_period_end, and cancelled_at fields
4. WHEN serializing subscription data THEN the Subscription_System SHALL produce valid JSON that can be deserialized back to an equivalent subscription object

### Requirement 8: Welcome Page Pricing Integration

**User Story:** As a visitor, I want to see pricing plans with working subscription buttons, so that I can choose and subscribe to a plan directly from the landing page.

#### Acceptance Criteria

1. WHEN a visitor views the pricing section THEN the Welcome_Page SHALL display all three plans (Basic, Standard, Pro) with their features and prices
2. WHEN a visitor clicks "Pilih Standard" or "Pilih Pro" THEN the Welcome_Page SHALL initiate the checkout flow for the selected plan
3. WHEN a visitor clicks "Mulai Gratis" THEN the Welcome_Page SHALL redirect to the registration page for free trial signup
4. WHEN a visitor is already logged in THEN the Welcome_Page SHALL show "Current Plan" badge on their active subscription tier

### Requirement 9: Feature Access Control

**User Story:** As a product owner, I want to restrict certain features based on subscription tier, so that users are incentivized to upgrade.

#### Acceptance Criteria

1. WHEN a user attempts to access a Standard-tier feature THEN the Access_Control_System SHALL verify the user has an active Standard or Pro subscription
2. WHEN a user attempts to access a Pro-tier feature THEN the Access_Control_System SHALL verify the user has an active Pro subscription
3. WHEN a user lacks the required subscription tier THEN the Access_Control_System SHALL display an upgrade prompt instead of the feature
4. WHEN checking feature access THEN the Access_Control_System SHALL consider trial users as having Basic-tier access only
