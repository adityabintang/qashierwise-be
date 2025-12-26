# Requirements Document

## Introduction

A multi-provider QRIS generation system with Bring Your Own Key (BYOK) functionality that enables users to securely configure their own payment provider credentials (Doku, Xendit, Midtrans, Duitku) with end-to-end AES-256 encryption and Row Level Security (RLS) to ensure each user can only access their own data.

## Glossary

- **BYOK**: Bring Your Own Key - users provide their own payment provider API credentials
- **Payment_Provider**: Third-party service for QRIS generation (Doku, Xendit, Midtrans, Duitku)
- **API_Credentials**: Provider-specific authentication keys and secrets
- **AES_256**: Advanced Encryption Standard with 256-bit key for data encryption
- **End_to_End_Encryption**: Data encrypted on client side before transmission and storage
- **Row_Level_Security**: Database security ensuring users can only access their own records
- **Active_Provider**: The currently selected payment provider for QRIS generation
- **Connection_Status**: Validation state of API credentials (valid/invalid)
- **Encryption_Key**: User-specific key for encrypting/decrypting API credentials
- **QRIS_Code**: Quick Response Code Indonesian Standard for payments

## Requirements

### Requirement 1: Multi-Provider Configuration Management

**User Story:** As a user, I want to configure multiple payment provider credentials, so that I can choose which provider to use for QRIS generation.

#### Acceptance Criteria

1. THE System SHALL support four payment providers: Doku, Xendit, Midtrans, and Duitku
2. WHEN configuring Doku, THE System SHALL collect Client ID and Secret Key
3. WHEN configuring Xendit, THE System SHALL collect API Key and Callback Token
4. WHEN configuring Midtrans, THE System SHALL collect Server Key and Client Key
5. WHEN configuring Duitku, THE System SHALL collect Merchant Code and API Key
6. THE System SHALL allow users to configure credentials for multiple providers simultaneously
7. WHEN credentials are configured, THE System SHALL store provider type and credential fields

### Requirement 2: End-to-End Encryption with AES-256

**User Story:** As a user, I want my API credentials encrypted end-to-end with AES-256, so that my sensitive data is protected from unauthorized access.

#### Acceptance Criteria

1. WHEN a user submits API credentials, THE System SHALL encrypt them using AES-256 before storage
2. THE System SHALL generate a unique encryption key for each user
3. WHEN encrypting credentials, THE System SHALL use AES-256-CBC or AES-256-GCM mode
4. WHEN retrieving credentials, THE System SHALL decrypt them only when needed for API calls
5. THE System SHALL never store or transmit credentials in plaintext
6. WHEN encryption fails, THE System SHALL reject the credential submission and notify the user
7. THE System SHALL securely manage encryption keys separate from encrypted data

### Requirement 3: Row Level Security Implementation

**User Story:** As a user, I want Row Level Security enforced on my data, so that other users cannot access my payment provider credentials or transactions.

#### Acceptance Criteria

1. THE System SHALL implement Row Level Security policies on all provider credential tables
2. WHEN querying credentials, THE System SHALL automatically filter results to current user only
3. WHEN inserting credentials, THE System SHALL automatically associate them with current user
4. THE System SHALL prevent users from accessing other users' encrypted credentials
5. WHEN RLS policies are violated, THE System SHALL reject the query and log the attempt
6. THE System SHALL enforce RLS at the database level, not just application level

### Requirement 4: Active Provider Selection

**User Story:** As a user, I want to select one active payment provider, so that I can control which provider is used for QRIS generation.

#### Acceptance Criteria

1. THE System SHALL allow users to designate one provider as active at a time
2. WHEN selecting an active provider, THE System SHALL validate that credentials exist for that provider
3. WHEN changing active provider, THE System SHALL deactivate the previous provider
4. THE System SHALL use the active provider for all new QRIS generation requests
5. WHEN no provider is active, THE System SHALL prevent QRIS generation and prompt configuration

### Requirement 5: Connection Status Validation

**User Story:** As a user, I want to see the connection status of my configured providers, so that I know if my credentials are valid.

#### Acceptance Criteria

1. WHEN credentials are saved, THE System SHALL validate them by making a test API call to the provider
2. THE System SHALL display connection status as "valid" or "invalid" for each configured provider
3. WHEN credentials are invalid, THE System SHALL display specific error messages from the provider
4. THE System SHALL allow users to manually re-validate credentials at any time
5. WHEN validation succeeds, THE System SHALL update the connection status to "valid"
6. WHEN validation fails, THE System SHALL update the connection status to "invalid" and store error details

### Requirement 6: Secure Credential Storage

**User Story:** As a platform administrator, I want API credentials stored securely, so that data breaches do not expose sensitive information.

#### Acceptance Criteria

1. THE System SHALL store encrypted credentials in a dedicated secure table
2. WHEN storing credentials, THE System SHALL separate encryption keys from encrypted data
3. THE System SHALL use database-level encryption for the credentials table
4. THE System SHALL implement access controls limiting who can query credential tables
5. THE System SHALL log all access attempts to credential data for audit purposes

### Requirement 7: Provider-Specific QRIS Generation

**User Story:** As a user, I want to generate QRIS codes using my active provider, so that I can accept payments through my chosen service.

#### Acceptance Criteria

1. WHEN generating QRIS, THE System SHALL use the active provider's API
2. THE System SHALL decrypt credentials only at the moment of API call
3. WHEN calling provider APIs, THE System SHALL use provider-specific request formats
4. THE System SHALL handle provider-specific response formats and extract QR code data
5. WHEN QRIS generation fails, THE System SHALL provide provider-specific error messages

### Requirement 8: Credential Update and Deletion

**User Story:** As a user, I want to update or delete my provider credentials, so that I can maintain control over my API keys.

#### Acceptance Criteria

1. THE System SHALL allow users to update existing provider credentials
2. WHEN updating credentials, THE System SHALL re-encrypt with the same encryption key
3. THE System SHALL allow users to delete provider credentials
4. WHEN deleting active provider credentials, THE System SHALL prompt user to select a new active provider
5. WHEN credentials are deleted, THE System SHALL securely wipe the encrypted data

### Requirement 9: User Interface for Provider Management

**User Story:** As a user, I want an intuitive interface to manage my payment providers, so that I can easily configure and monitor my credentials.

#### Acceptance Criteria

1. THE System SHALL provide a settings page for managing payment provider credentials
2. WHEN viewing settings, THE System SHALL display all four providers with their configuration status
3. THE System SHALL show which provider is currently active
4. THE System SHALL display connection status indicators (valid/invalid) for each provider
5. WHEN configuring a provider, THE System SHALL show provider-specific input fields
6. THE System SHALL provide clear visual feedback during credential validation
7. THE System SHALL mask sensitive credential fields in the UI (show as asterisks)

### Requirement 10: Security Audit and Logging

**User Story:** As a platform administrator, I want comprehensive audit logs for credential access, so that I can monitor security and detect unauthorized access attempts.

#### Acceptance Criteria

1. THE System SHALL log all credential creation, update, and deletion events
2. THE System SHALL log all credential decryption events with timestamp and user
3. THE System SHALL log all failed validation attempts with error details
4. THE System SHALL log all RLS policy violations with user and query details
5. THE System SHALL provide audit log viewing for administrators only

### Requirement 11: Migration from Existing System

**User Story:** As an existing sub-merchant user, I want to migrate from the old Midtrans-only system to the new BYOK system, so that I can continue using the service with my own credentials.

#### Acceptance Criteria

1. THE System SHALL provide a migration path for existing Midtrans users
2. WHEN migrating, THE System SHALL prompt users to enter their own Midtrans credentials
3. THE System SHALL preserve existing transaction history during migration
4. THE System SHALL maintain backward compatibility with existing QRIS transactions
5. WHEN migration is incomplete, THE System SHALL prevent QRIS generation and show migration prompt

### Requirement 12: Error Handling and User Feedback

**User Story:** As a user, I want clear error messages when credential configuration fails, so that I can troubleshoot and fix issues.

#### Acceptance Criteria

1. WHEN API credentials are invalid, THE System SHALL display provider-specific error messages
2. WHEN encryption fails, THE System SHALL notify the user without exposing technical details
3. WHEN network errors occur during validation, THE System SHALL distinguish them from credential errors
4. THE System SHALL provide actionable guidance for common error scenarios
5. WHEN RLS violations occur, THE System SHALL display a generic security error without details
