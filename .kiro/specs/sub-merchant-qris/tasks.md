# Implementation Plan: Sub-Merchant QRIS System

## Overview

This implementation plan breaks down the sub-merchant QRIS system into discrete coding tasks that build incrementally. Each task focuses on specific components while ensuring proper integration with existing Laravel application structure. The plan includes comprehensive testing with both unit tests and property-based tests using PHPUnit and Eris.

## Tasks

- [x] 1. Set up database schema and migrations
  - Create migration for sub_merchants table with proper foreign keys and indexes
  - Create migration for merchant_balances table with balance tracking fields
  - Create migration for qris_transactions table with Midtrans integration fields
  - Create migration for withdrawal_requests table with admin approval workflow
  - Create migration for platform_fees table for fee tracking
  - _Requirements: 1.4, 3.5, 6.5_

- [x] 2. Create core Eloquent models and relationships
  - [x] 2.1 Implement SubMerchant model with encrypted bank account fields
    - Define fillable fields and relationships to User and MerchantBalance
    - Implement bank account encryption/decryption accessors and mutators
    - Add validation methods for bank account data
    - _Requirements: 1.1, 1.3, 9.1_

  - [ ]* 2.2 Write property test for SubMerchant model
    - **Property 1: Bank Account Validation Consistency**
    - **Property 14: Data Encryption Consistency**
    - **Validates: Requirements 1.1, 1.3, 9.1**

  - [x] 2.3 Implement MerchantBalance model with balance management
    - Define balance fields (available, pending, total_earned, total_withdrawn)
    - Add methods for balance validation and withdrawal eligibility
    - Implement balance update tracking with timestamps
    - _Requirements: 4.1, 4.2, 4.5_

  - [ ]* 2.4 Write property test for MerchantBalance model
    - **Property 8: Balance Non-Negativity**
    - **Validates: Requirements 4.5**

  - [x] 2.5 Implement QrisTransaction model with Midtrans integration
    - Define transaction fields including order_id, amount, platform_fee, status
    - Add methods for expiration checking and shareable link generation
    - Implement relationship to SubMerchant and transaction status management
    - _Requirements: 2.1, 2.4, 2.5_

  - [ ]* 2.6 Write property test for QrisTransaction model
    - **Property 3: QRIS Order ID Uniqueness**
    - **Property 5: QRIS Expiration Prevention**
    - **Validates: Requirements 2.1, 2.5**

  - [x] 2.7 Implement WithdrawalRequest and PlatformFee models
    - Create WithdrawalRequest model with status management and admin approval fields
    - Create PlatformFee model for fee tracking and audit purposes
    - Define proper relationships and validation methods
    - _Requirements: 5.3, 5.4, 3.5_

- [x] 3. Implement core service layer
  - [x] 3.1 Create SubMerchantService for registration and management
    - Implement registerSubMerchant method with bank account validation
    - Add updateBankAccount method with proper validation
    - Create methods for activating/deactivating sub-merchants
    - _Requirements: 1.1, 1.2, 1.5_

  - [ ]* 3.2 Write property test for SubMerchantService
    - **Property 2: Sub-Merchant Registration Initialization**
    - **Property 17: User Upgrade Data Preservation**
    - **Validates: Requirements 1.5, 10.2, 10.3**

  - [x] 3.3 Create QrisService for QRIS generation and management
    - Implement generateQris method with Midtrans API integration
    - Add methods for QR code image generation and shareable links
    - Create QRIS validation and expiration handling
    - _Requirements: 2.1, 2.2, 2.3, 8.1, 8.2_

  - [ ]* 3.4 Write property test for QrisService
    - **Property 4: QRIS Generation Completeness**
    - **Validates: Requirements 2.3, 8.1, 8.2**

  - [x] 3.5 Create BalanceService for balance management
    - Implement updateBalance method with transaction logging
    - Add calculatePlatformFee method with 2.5% fee calculation
    - Create processPaymentSuccess method for webhook handling
    - _Requirements: 3.2, 3.3, 3.4, 3.5_

  - [ ]* 3.6 Write property test for BalanceService
    - **Property 6: Platform Fee Calculation Accuracy**
    - **Property 7: Balance Update Consistency**
    - **Validates: Requirements 3.2, 3.3, 3.4, 3.5**

- [x] 4. Checkpoint - Ensure core models and services work correctly
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement withdrawal system
  - [x] 5.1 Create WithdrawalService for withdrawal management
    - Implement createWithdrawalRequest with validation and balance deduction
    - Add validateWithdrawal method for minimum amount and balance checks
    - Create approveWithdrawal and rejectWithdrawal methods for admin actions
    - _Requirements: 5.1, 5.2, 5.4, 6.3, 6.4_

  - [ ]* 5.2 Write property test for WithdrawalService
    - **Property 9: Withdrawal Validation Rules**
    - **Property 10: Withdrawal Status Consistency**
    - **Property 11: Withdrawal Rejection Recovery**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 6.4**

  - [x] 5.3 Implement withdrawal notification system
    - Create notification classes for admin alerts on new withdrawal requests
    - Add notification for sub-merchants on withdrawal status changes
    - Implement email and in-app notification delivery
    - _Requirements: 5.5, 6.4_

- [x] 6. Create API controllers for sub-merchant functionality
  - [x] 6.1 Implement SubMerchantController for registration and management
    - Create endpoints for sub-merchant registration with bank account setup
    - Add endpoints for updating bank account information
    - Implement sub-merchant status and profile management endpoints
    - _Requirements: 1.1, 1.2, 10.1_

  - [x] 6.2 Implement QrisController for QRIS generation and sharing
    - Create endpoint for generating new QRIS codes with amount and details
    - Add endpoints for retrieving QR code images and shareable links
    - Implement QRIS status checking and expiration handling
    - _Requirements: 2.1, 2.2, 2.3, 8.1, 8.2, 8.5_

  - [x] 6.3 Implement BalanceController for balance management
    - Create endpoints for retrieving current balance and transaction history
    - Add endpoints for balance breakdown (available, pending, total earned)
    - Implement transaction history with fee breakdown display
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 6.4 Implement WithdrawalController for withdrawal requests
    - Create endpoint for submitting withdrawal requests with validation
    - Add endpoints for retrieving withdrawal history and status
    - Implement withdrawal cancellation for pending requests
    - _Requirements: 5.1, 5.2, 7.3, 7.5_

- [x] 7. Implement Midtrans webhook processing
  - [x] 7.1 Create MidtransWebhookController for payment notifications
    - Implement webhook endpoint with signature validation
    - Add payment status processing for settlement, expire, and cancel
    - Create proper error handling and logging for webhook failures
    - _Requirements: 3.1, 9.2_

  - [ ]* 7.2 Write property test for webhook processing
    - **Property 13: Webhook Signature Validation**
    - **Validates: Requirements 9.2**

  - [x] 7.3 Implement payment processing workflow
    - Create job for processing successful payments asynchronously
    - Add balance update logic with platform fee calculation
    - Implement transaction logging and audit trail creation
    - _Requirements: 3.2, 3.3, 3.4, 3.5_

- [x] 8. Create admin panel for withdrawal management
  - [x] 8.1 Implement AdminWithdrawalController for approval workflow
    - Create endpoints for listing pending withdrawal requests
    - Add endpoints for approving and rejecting withdrawals with admin notes
    - Implement withdrawal processing status tracking
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 8.2 Create admin dashboard views for withdrawal management
    - Build Blade templates for withdrawal request listing and details
    - Add forms for approval/rejection with admin notes
    - Implement audit trail display for admin actions
    - _Requirements: 6.1, 6.2, 6.5_

- [x] 9. Implement security and rate limiting
  - [x] 9.1 Add rate limiting for QRIS generation
    - Implement rate limiting middleware for QRIS endpoints
    - Add user-specific rate limits to prevent abuse
    - Create proper error responses for rate limit violations
    - _Requirements: 9.3_

  - [ ]* 9.2 Write property test for rate limiting
    - **Property 15: Rate Limiting Enforcement**
    - **Validates: Requirements 9.3**

  - [x] 9.3 Implement additional authentication for withdrawals
    - Add two-factor authentication requirement for withdrawal requests
    - Implement password confirmation for sensitive operations
    - Create session validation for admin approval actions
    - _Requirements: 9.4_

  - [ ]* 9.4 Write property test for authentication requirements
    - **Property 16: Authentication Requirements**
    - **Validates: Requirements 9.4**

- [x] 10. Create frontend views for sub-merchant dashboard
  - [x] 10.1 Build sub-merchant registration and settings pages
    - Create registration form with bank account input and validation
    - Add settings page for updating bank account information
    - Implement user upgrade flow from regular user to sub-merchant
    - _Requirements: 1.1, 1.2, 10.1_

  - [x] 10.2 Create QRIS generation and management interface
    - Build form for generating new QRIS codes with amount input
    - Add display for QR code images and shareable links
    - Implement QRIS history and status tracking interface
    - _Requirements: 2.3, 8.1, 8.2, 8.4_

  - [x] 10.3 Implement balance and transaction dashboard
    - Create balance display with available, pending, and total amounts
    - Add transaction history table with fee breakdown
    - Implement real-time balance updates using Laravel Echo
    - _Requirements: 4.1, 4.2, 4.3, 7.2_

  - [x] 10.4 Build withdrawal management interface
    - Create withdrawal request form with validation and confirmation
    - Add withdrawal history display with status tracking
    - Implement withdrawal cancellation for pending requests
    - _Requirements: 5.1, 7.3, 7.5_

- [x] 11. Implement comprehensive audit logging
  - [x] 11.1 Create audit logging system for financial transactions
    - Implement logging for all balance updates and fee calculations
    - Add audit trail for withdrawal approvals and rejections
    - Create comprehensive transaction logging with user actions
    - _Requirements: 3.5, 6.5, 9.5_

  - [ ]* 11.2 Write property test for audit logging
    - **Property 12: Transaction History Completeness**
    - **Validates: Requirements 4.3, 6.5, 9.5**

- [x] 12. Integration testing and final validation
  - [x] 12.1 Create end-to-end integration tests
    - Test complete QRIS generation to payment settlement flow
    - Add integration tests for withdrawal request to approval workflow
    - Implement tests for admin panel functionality with real data
    - _Requirements: All requirements integration_

  - [ ]* 12.2 Write comprehensive property tests for system integration
    - Test cross-service property consistency
    - Validate data integrity across all operations
    - Ensure security properties hold under various scenarios

- [x] 13. Final checkpoint - Complete system validation
  - Ensure all tests pass, ask the user if questions arise.
  - Verify all requirements are implemented and tested
  - Confirm system is ready for deployment

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties using PHPUnit with Eris
- Unit tests validate specific examples and edge cases
- Integration tests ensure end-to-end functionality works correctly
- Checkpoints ensure incremental validation and user feedback