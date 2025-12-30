# Implementation Plan: Xendit QRIS Integration

## Overview

This implementation plan breaks down the Xendit QRIS integration into discrete coding tasks. The approach follows a provider factory pattern, starting with database setup, then implementing the XenditProvider, updating the QrisService, and finally adding webhook handling. Each task builds on previous work with integrated testing.

## Tasks

- [x] 1. Database Setup and Migrations
  - Create `payment_provider_credentials` table with encrypted credential storage
  - Add `provider`, `reference_id`, and `paid_at` columns to `qris_transactions` table
  - Create migration to set existing users' default provider to 'midtrans'
  - _Requirements: 2.1, 2.2, 2.3_

- [x] 2. Create PaymentProviderInterface and DTOs
  - Define `PaymentProviderInterface` with generateQris(), verifyWebhook(), parseWebhookPayload(), getProviderName() methods
  - Create `QrisRequest`, `QrisResponse`, and `WebhookTransaction` DTOs
  - Create custom exceptions: `ProviderException`, `UnsupportedProviderException`, `NoActiveProviderException`, `InvalidWebhookException`
  - _Requirements: 1.4, 6.1, 6.2_

- [ ]* 2.1 Write unit tests for PaymentProviderInterface
  - Test interface contract and method signatures
  - _Requirements: 1.4_

- [x] 3. Implement XenditProvider Class
  - Create `XenditProvider` implementing `PaymentProviderInterface`
  - Implement `generateQris()` method calling Xendit API endpoint
  - Implement `verifyWebhook()` using HMAC-SHA256 signature verification
  - Implement `parseWebhookPayload()` mapping Xendit status to standard format
  - Implement `getProviderName()` returning 'xendit'
  - _Requirements: 1.1, 1.2, 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ]* 3.1 Write property test for XenditProvider credential handling
  - **Property 2: Credential Encryption Round Trip**
  - **Validates: Requirements 2.1, 2.2, 2.3**

- [ ]* 3.2 Write unit tests for XenditProvider
  - Test QRIS generation with mocked API responses
  - Test webhook signature verification
  - Test status mapping (ACTIVE→pending, PAID→success, EXPIRED→expired, FAILED→failed)
  - Test error handling for invalid credentials and API failures
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 4. Implement ProviderFactory
  - Create `ProviderFactory` class with static `create()` method
  - Support instantiation of both MidtransProvider and XenditProvider
  - Handle credential decryption and passing to providers
  - Throw `UnsupportedProviderException` for unknown providers
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ]* 4.1 Write property test for ProviderFactory
  - **Property 1: Provider Factory Creates Correct Instance**
  - **Validates: Requirements 6.1, 6.2, 6.3**

- [ ]* 4.2 Write unit tests for ProviderFactory
  - Test factory creates correct provider for 'midtrans' type
  - Test factory creates correct provider for 'xendit' type
  - Test factory throws exception for unsupported provider type
  - Test factory passes credentials correctly to providers
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 5. Create PaymentProviderCredential Model
  - Create model with encrypted attribute accessors for api_key and secret_key
  - Implement encryption/decryption using Laravel's Crypt facade
  - Add relationship to User model
  - Add scope for active credentials
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [ ]* 5.1 Write property test for credential encryption
  - **Property 2: Credential Encryption Round Trip**
  - **Validates: Requirements 2.1, 2.2, 2.3**

- [ ]* 5.2 Write unit tests for PaymentProviderCredential model
  - Test credential encryption on save
  - Test credential decryption on retrieval
  - Test active credential scope
  - Test validation of required fields
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 6. Update QrisService for Multi-Provider Support
  - Refactor `generateQris()` to use ProviderFactory
  - Implement `getActiveProviderCredential()` method
  - Add provider selection logic (throw `NoActiveProviderException` if none configured)
  - Implement `handleWebhook()` method for webhook processing
  - Add webhook signature verification
  - Implement `updateTransactionStatus()` for webhook payload processing
  - _Requirements: 1.1, 1.2, 3.2, 3.3, 3.4, 5.1, 5.2, 5.3, 5.4_

- [ ]* 6.1 Write property test for active provider selection
  - **Property 6: Active Provider Selection**
  - **Validates: Requirements 3.2, 3.3, 3.4**

- [ ]* 6.2 Write property test for QRIS response structure
  - **Property 3: QRIS Response Contains Required Fields**
  - **Validates: Requirements 1.4, 4.2, 4.3**

- [ ]* 6.3 Write unit tests for QrisService
  - Test QRIS generation with active provider
  - Test exception when no provider configured
  - Test webhook handling with signature verification
  - Test transaction status update from webhook
  - Test backward compatibility with Midtrans
  - _Requirements: 1.1, 1.2, 3.2, 3.3, 3.4, 5.1, 5.2, 5.3, 5.4_

- [x] 7. Create Provider Selection UI
  - Create settings page/component for provider selection
  - Display available providers (Midtrans, Xendit)
  - Show configured providers with credential status
  - Allow users to set active provider
  - Add form for entering Xendit credentials (API key, secret key)
  - Implement credential validation before saving
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ]* 7.1 Write unit tests for provider selection UI
  - Test form validation for credentials
  - Test credential storage
  - Test provider activation
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 8. Update Webhook Routes and Handlers
  - Create webhook route for Xendit: `POST /webhooks/xendit`
  - Create webhook controller method to handle Xendit webhooks
  - Implement signature verification
  - Call QrisService::handleWebhook()
  - Return appropriate HTTP responses
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ]* 8.1 Write property test for webhook signature verification
  - **Property 4: Webhook Signature Verification Consistency**
  - **Validates: Requirements 5.1, 5.2**

- [ ]* 8.2 Write unit tests for webhook handling
  - Test webhook signature verification (valid and invalid)
  - Test webhook payload parsing
  - Test transaction status update
  - Test error responses for invalid webhooks
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 9. Implement Error Handling and Logging
  - Add comprehensive error handling in XenditProvider
  - Implement error message sanitization (no API keys in responses)
  - Add audit logging for credential access
  - Add logging for all provider API calls
  - Create error response DTOs
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ]* 9.1 Write property test for error message sanitization
  - **Property 8: Error Message Sanitization**
  - **Validates: Requirements 8.5**

- [ ]* 9.2 Write unit tests for error handling
  - Test invalid credential errors
  - Test provider unavailable errors
  - Test validation errors
  - Test error message sanitization
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 10. Implement Backward Compatibility
  - Ensure existing Midtrans transactions continue to work
  - Set default provider to 'midtrans' for existing users
  - Maintain existing API endpoints and response formats
  - Test migration of existing data
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ]* 10.1 Write property test for backward compatibility
  - **Property 7: Backward Compatibility**
  - **Validates: Requirements 7.1, 7.2, 7.3**

- [ ]* 10.2 Write integration tests for backward compatibility
  - Test existing Midtrans flow still works
  - Test data migration
  - Test provider switching for existing users
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 11. Checkpoint - Ensure all tests pass
  - Run all unit tests for Xendit integration
  - Run all property-based tests
  - Run integration tests
  - Verify no regressions in existing functionality
  - _Requirements: All_

- [ ]* 11.1 Write integration tests for full QRIS flow
  - Test complete QRIS generation with Xendit
  - Test webhook handling end-to-end
  - Test provider switching
  - _Requirements: 1.1, 1.2, 4.1, 4.2, 4.3, 5.1, 5.2, 5.3_

- [x] 12. Documentation and Configuration
  - Document Xendit API integration setup
  - Create configuration guide for merchants
  - Document webhook setup and testing
  - Add code comments and docstrings
  - Update README with multi-provider support
  - _Requirements: All_

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- Integration tests validate end-to-end flows
- All tests should run with minimum 100 iterations for property-based tests
