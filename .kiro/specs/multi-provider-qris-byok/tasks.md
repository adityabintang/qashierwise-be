# Implementation Plan: Multi-Provider QRIS with BYOK

## Overview

This implementation plan transforms the existing Midtrans-only QRIS system into a multi-provider BYOK (Bring Your Own Key) system with end-to-end AES-256 encryption and Row Level Security. The plan builds incrementally, starting with the encryption and security foundation, then adding provider abstraction, and finally implementing the UI and migration path.

## Tasks

- [x] 1. Set up database schema and migrations for BYOK system
  - Create migration for payment_provider_credentials table with RLS support
  - Create migration for user_encryption_keys table with RLS policies
  - Create migration for credential_access_logs audit table
  - Update qris_transactions table to add provider and provider_transaction_id columns
  - Add PostgreSQL RLS policies for user isolation (or application-level filtering for MySQL)
  - _Requirements: 1.7, 2.7, 3.1, 6.1, 6.2, 6.5_

- [x] 2. Implement encryption and key management services
  - [x] 2.1 Create KeyManagementService for user encryption keys
    - Implement getUserEncryptionKey method with secure key derivation
    - Add generateUserKey method using Laravel's secure random bytes
    - Create storeUserKey method with master key encryption
    - Implement key rotation functionality
    - _Requirements: 2.2, 2.7_

  - [ ]* 2.2 Write property test for KeyManagementService
    - **Property 5: Per-User Encryption Key Uniqueness**
    - **Validates: Requirements 2.2**

  - [x] 2.3 Create EncryptionService for credential encryption
    - Implement encryptCredentials using AES-256-CBC with user-specific keys
    - Add decryptCredentials with proper IV handling
    - Create rotateUserKey method for key rotation scenarios
    - Implement secure IV generation and storage
    - _Requirements: 2.1, 2.3, 2.5, 2.6_

  - [ ]* 2.4 Write property test for EncryptionService
    - **Property 4: AES-256 Encryption Enforcement**
    - **Property 6: Encryption Failure Rejection**
    - **Property 7: Encryption Key Separation**
    - **Validates: Requirements 2.1, 2.3, 2.5, 2.6, 2.7, 6.2**

- [x] 3. Create Eloquent models with RLS support
  - [x] 3.1 Implement PaymentProviderCredential model
    - Define fillable fields and casts for provider credentials
    - Add provider constants (DOKU, XENDIT, MIDTRANS, DUITKU)
    - Implement relationship to User model
    - Add validation methods for connection status
    - _Requirements: 1.1, 1.6, 1.7_

  - [ ]* 3.2 Write property test for PaymentProviderCredential model
    - **Property 2: Multi-Provider Simultaneous Configuration**
    - **Property 3: Provider Type and Credential Persistence**
    - **Validates: Requirements 1.6, 1.7**

  - [x] 3.3 Implement UserEncryptionKey model
    - Define model for storing user-specific encryption keys
    - Add relationship to User model
    - Implement key version tracking
    - _Requirements: 2.2, 2.7_

  - [x] 3.4 Implement CredentialAccessLog model for audit trail
    - Define model for logging all credential access
    - Add action enum (create, read, update, delete, decrypt)
    - Implement relationships to User and PaymentProviderCredential
    - _Requirements: 6.5, 10.1, 10.2, 10.3, 10.4_

- [x] 4. Implement Row Level Security middleware and policies
  - [x] 4.1 Create SetPostgresUserContext middleware
    - Implement handle method to set app.current_user_id session variable
    - Add terminate method to reset session context after request
    - Register middleware in HTTP kernel for authenticated routes
    - _Requirements: 3.1, 3.2, 3.3_

  - [ ]* 4.2 Write property test for RLS enforcement
    - **Property 8: Row Level Security User Isolation**
    - **Property 9: Automatic User Association on Insert**
    - **Property 10: RLS Violation Rejection and Logging**
    - **Validates: Requirements 3.2, 3.3, 3.4, 3.5, 10.4**

- [x] 5. Checkpoint - Ensure encryption and security foundation works
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Create provider abstraction layer
  - [x] 6.1 Define PaymentProviderInterface
    - Create interface with validateCredentials method
    - Add generateQris method signature
    - Define checkTransactionStatus method
    - Add getProviderName and getRequiredCredentialFields methods
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 5.1, 7.1_

  - [x] 6.2 Implement DokuProvider
    - Implement getRequiredCredentialFields returning client_id and secret_key
    - Create validateCredentials using Doku SNAP API token endpoint
    - Implement generateQris using Doku QRIS API
    - Add checkTransactionStatus for transaction inquiry
    - _Requirements: 1.2, 5.1, 7.3, 7.4_

  - [x] 6.3 Implement XenditProvider
    - Implement getRequiredCredentialFields returning api_key and callback_token
    - Create validateCredentials using Xendit balance endpoint
    - Implement generateQris using Xendit QR Codes API
    - Add checkTransactionStatus for QR code status
    - _Requirements: 1.3, 5.1, 7.3, 7.4_

  - [x] 6.4 Implement MidtransProvider
    - Implement getRequiredCredentialFields returning server_key and client_key
    - Create validateCredentials using Midtrans status endpoint
    - Implement generateQris using Midtrans charge API
    - Add checkTransactionStatus for transaction status
    - _Requirements: 1.4, 5.1, 7.3, 7.4_

  - [x] 6.5 Implement DuitkuProvider
    - Implement getRequiredCredentialFields returning merchant_code and api_key
    - Create validateCredentials using Duitku inquiry endpoint
    - Implement generateQris using Duitku invoice creation API
    - Add checkTransactionStatus for payment status
    - _Requirements: 1.5, 5.1, 7.3, 7.4_

  - [ ]* 6.6 Write property test for provider implementations
    - **Property 1: Provider-Specific Required Fields**
    - **Property 20: Provider-Specific Request Formatting**
    - **Property 21: Provider Response Parsing**
    - **Validates: Requirements 1.2, 1.3, 1.4, 1.5, 7.3, 7.4**

  - [x] 6.7 Create ProviderFactory for provider instantiation
    - Implement make method with provider name matching
    - Add support for all four providers
    - Throw UnsupportedProviderException for invalid providers
    - _Requirements: 1.1, 7.1_

- [x] 7. Implement credential management services
  - [x] 7.1 Create ProviderCredentialService
    - Implement storeCredentials with encryption and validation
    - Add updateCredentials with re-encryption
    - Create deleteCredentials with secure wipe
    - Implement setActiveProvider with constraint enforcement
    - Add getActiveProvider method
    - _Requirements: 1.6, 1.7, 4.1, 4.2, 4.3, 8.1, 8.2, 8.3, 8.5_

  - [ ]* 7.2 Write property test for ProviderCredentialService
    - **Property 11: Single Active Provider Constraint**
    - **Property 12: Active Provider Validation Requirement**
    - **Property 22: Credential Update with Consistent Key**
    - **Property 23: Credential Deletion and Secure Wipe**
    - **Validates: Requirements 4.1, 4.2, 4.3, 8.1, 8.2, 8.3, 8.5**

  - [x] 7.3 Create ProviderValidationService
    - Implement validateCredentials method using provider interface
    - Add updateConnectionStatus method
    - Create captureProviderError method for error storage
    - Implement manual revalidation trigger
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [ ]* 7.4 Write property test for ProviderValidationService
    - **Property 15: Credential Validation on Save**
    - **Property 16: Connection Status Update After Validation**
    - **Property 17: Provider Error Message Capture**
    - **Property 18: Manual Revalidation Capability**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.6**

- [x] 8. Implement audit logging service
  - [x] 8.1 Create CredentialAuditService
    - Implement logAccess method for all credential operations
    - Add logDecryption method with timestamp and user
    - Create logValidation method for validation attempts
    - Implement logRLSViolation method for security events
    - _Requirements: 6.5, 10.1, 10.2, 10.3, 10.4_

  - [ ]* 8.2 Write property test for CredentialAuditService
    - **Property 19: Comprehensive Audit Logging**
    - **Validates: Requirements 6.5, 10.1, 10.2, 10.3**

- [x] 9. Checkpoint - Ensure provider abstraction and services work
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Update QRIS generation to use multi-provider system
  - [x] 10.1 Refactor QrisGenerationService
    - Update generateQris to use active provider from credentials
    - Add provider credential decryption before API calls
    - Implement provider factory usage for dynamic provider selection
    - Update transaction storage to include provider information
    - _Requirements: 4.4, 7.1, 7.2_

  - [ ]* 10.2 Write property test for multi-provider QRIS generation
    - **Property 13: Active Provider Usage for QRIS**
    - **Property 14: No Active Provider Prevention**
    - **Validates: Requirements 4.4, 4.5, 7.1**

  - [x] 10.3 Update QrisTransaction model
    - Add provider field to track which provider was used
    - Add provider_transaction_id for provider-specific transaction IDs
    - Update relationships and queries to include provider filtering
    - _Requirements: 7.1_

- [x] 11. Create API controllers for provider management
  - [x] 11.1 Implement ProviderCredentialController
    - Create endpoint for listing user's configured providers
    - Add endpoint for storing new provider credentials
    - Implement endpoint for updating existing credentials
    - Create endpoint for deleting provider credentials
    - Add endpoint for setting active provider
    - _Requirements: 1.6, 1.7, 4.1, 8.1, 8.3_

  - [x] 11.2 Implement ProviderValidationController
    - Create endpoint for validating credentials
    - Add endpoint for manual revalidation
    - Implement endpoint for retrieving connection status
    - _Requirements: 5.1, 5.2, 5.4_

  - [x] 11.3 Update existing QrisController
    - Modify QRIS generation to use multi-provider system
    - Add error handling for no active provider scenario
    - Update response format to include provider information
    - _Requirements: 4.5, 7.5_

- [x] 12. Implement error handling and sanitization
  - [x] 12.1 Create error handling middleware
    - Implement encryption error sanitization
    - Add RLS violation error handling with generic messages
    - Create provider error classification (network vs credential)
    - Implement user-friendly error message mapping
    - _Requirements: 12.1, 12.2, 12.3, 12.5_

  - [ ]* 12.2 Write property test for error handling
    - **Property 24: Encryption Error Sanitization**
    - **Property 25: Error Type Classification**
    - **Property 26: RLS Violation Error Sanitization**
    - **Validates: Requirements 12.2, 12.3, 12.5**

- [x] 13. Create frontend UI for provider management
  - [x] 13.1 Build provider settings page
    - Create Blade template for provider configuration
    - Add forms for each provider with provider-specific fields
    - Implement connection status indicators (valid/invalid)
    - Add active provider selection UI
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [x] 13.2 Implement provider credential forms
    - Create Doku credential form (Client ID, Secret Key)
    - Add Xendit credential form (API Key, Callback Token)
    - Create Midtrans credential form (Server Key, Client Key)
    - Add Duitku credential form (Merchant Code, API Key)
    - Implement field masking for sensitive data
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 9.6, 9.7_

  - [x] 13.3 Add validation and feedback UI
    - Implement real-time validation feedback
    - Add loading states during credential validation
    - Create success/error message displays
    - Implement provider switching confirmation dialogs
    - _Requirements: 9.6, 12.1_

  - [x] 13.4 Update sub-merchant dashboard navigation
    - Replace old Midtrans-only QRIS menu with new multi-provider menu
    - Add provider settings link to sub-merchant menu
    - Update dashboard to show active provider
    - _Requirements: 9.1, 9.3_

- [x] 14. Implement migration from old system
  - [x] 14.1 Create migration command for existing users
    - Implement command to identify existing Midtrans users
    - Add prompt for users to enter their own Midtrans credentials
    - Create migration workflow preserving transaction history
    - Implement rollback capability for failed migrations
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

  - [x] 14.2 Add migration UI for existing users
    - Create migration prompt page for users without BYOK credentials
    - Add step-by-step migration wizard
    - Implement credential verification during migration
    - Add migration status tracking
    - _Requirements: 11.1, 11.2, 11.5_

- [-] 15. Integration testing and validation
  - [x] 15.1 Create end-to-end integration tests
    - Test complete flow: configure provider → validate → generate QRIS
    - Add tests for provider switching scenarios
    - Implement tests for multi-user isolation
    - Create tests for error scenarios (invalid credentials, network errors)
    - _Requirements: All requirements integration_

  - [ ]* 15.2 Write comprehensive property tests for system integration
    - Test cross-service property consistency
    - Validate data integrity across all operations
    - Ensure security properties hold under various scenarios
    - Test concurrent access and race conditions

- [x] 16. Final checkpoint - Complete system validation
  - Ensure all tests pass, ask the user if questions arise.
  - Verify all requirements are implemented and tested
  - Confirm RLS policies are working correctly
  - Validate encryption/decryption performance
  - Test with all four providers in staging environment
  - Confirm system is ready for deployment

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties using PHPUnit with Eris
- Unit tests validate specific examples and edge cases
- Integration tests ensure end-to-end functionality works correctly
- Checkpoints ensure incremental validation and user feedback
- RLS implementation may differ between PostgreSQL (native) and MySQL (application-level)
- Provider API credentials should be obtained from each provider's developer portal for testing
