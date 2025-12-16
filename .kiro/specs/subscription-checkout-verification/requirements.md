# Requirements Document

## Introduction

Fitur ini menambahkan mekanisme verifikasi subscription setelah user berhasil melakukan checkout di Polar.sh. Saat ini, sistem hanya bergantung pada webhook dari Polar untuk membuat/update subscription di database. Ketika webhook gagal terkirim atau tidak terproses, subscription tidak ter-record meskipun user sudah berhasil bayar.

Fitur ini menyediakan fallback mechanism dengan memanfaatkan `customer_session_token` yang dikirim Polar pada success URL redirect untuk memverifikasi dan sync subscription secara langsung.

## Glossary

- **Polar.sh**: Payment provider yang digunakan untuk mengelola subscription
- **Customer Session Token**: Token yang diberikan Polar pada success URL setelah checkout berhasil (format: `polar_cst_xxx`)
- **Checkout Session**: Sesi pembayaran yang dibuat saat user memulai proses subscribe
- **Subscription Verification System**: Sistem yang memverifikasi dan sync subscription dari Polar ke database lokal
- **Webhook**: HTTP callback dari Polar yang mengirim event subscription (created, updated, cancelled)

## Requirements

### Requirement 1

**User Story:** As a user, I want my subscription to be automatically verified and activated after successful checkout, so that I can immediately access premium features without waiting for webhook processing.

#### Acceptance Criteria

1. WHEN a user is redirected to success URL with customer_session_token parameter THEN the Subscription Verification System SHALL provide an API endpoint to verify the subscription
2. WHEN the frontend calls verify endpoint with valid customer_session_token THEN the Subscription Verification System SHALL fetch subscription data from Polar API
3. WHEN subscription data is successfully fetched from Polar THEN the Subscription Verification System SHALL create or update the subscription record in database
4. WHEN subscription is verified and saved THEN the Subscription Verification System SHALL return the updated subscription status to frontend
5. WHEN verification is successful THEN the Subscription Verification System SHALL log the verification event for audit purposes

### Requirement 2

**User Story:** As a system administrator, I want the verification process to handle errors gracefully, so that users receive clear feedback when something goes wrong.

#### Acceptance Criteria

1. WHEN customer_session_token is missing or empty THEN the Subscription Verification System SHALL return validation error with code INVALID_TOKEN
2. WHEN customer_session_token format is invalid (not starting with polar_cst_) THEN the Subscription Verification System SHALL return validation error with code INVALID_TOKEN_FORMAT
3. WHEN Polar API returns error or subscription not found THEN the Subscription Verification System SHALL return error with code VERIFICATION_FAILED
4. WHEN Polar service is not configured THEN the Subscription Verification System SHALL return error with code POLAR_CONFIG_MISSING
5. WHEN any error occurs during verification THEN the Subscription Verification System SHALL log the error details for debugging

### Requirement 3

**User Story:** As a developer, I want the verification endpoint to be secure, so that only authenticated users can verify their own subscriptions.

#### Acceptance Criteria

1. WHEN unauthenticated request is made to verify endpoint THEN the Subscription Verification System SHALL return 401 Unauthorized response
2. WHEN verification is successful THEN the Subscription Verification System SHALL associate subscription with the authenticated user only
3. WHEN subscription metadata contains different user_id than authenticated user THEN the Subscription Verification System SHALL reject the verification with UNAUTHORIZED_USER error

### Requirement 4

**User Story:** As a developer, I want the PolarService to support fetching checkout session details, so that subscription can be verified using customer_session_token.

#### Acceptance Criteria

1. WHEN getCheckoutByToken method is called with valid token THEN the PolarService SHALL return checkout session data including subscription_id
2. WHEN getCheckoutByToken method is called with invalid token THEN the PolarService SHALL return null
3. WHEN Polar API call fails THEN the PolarService SHALL log the error and return null

### Requirement 5

**User Story:** As a user, I want the verification to be idempotent, so that multiple verification attempts do not create duplicate subscriptions.

#### Acceptance Criteria

1. WHEN verification is called multiple times with same token THEN the Subscription Verification System SHALL update existing subscription instead of creating duplicate
2. WHEN subscription already exists for user THEN the Subscription Verification System SHALL update the subscription with latest data from Polar
3. WHEN verification completes THEN the Subscription Verification System SHALL return consistent subscription status regardless of number of calls
