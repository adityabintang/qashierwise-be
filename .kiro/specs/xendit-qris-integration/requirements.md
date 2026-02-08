# Requirements Document

## Introduction

This document specifies the requirements for integrating Xendit as an additional QRIS payment provider alongside the existing Midtrans integration. The system will allow users to choose between Midtrans and Xendit for generating dynamic QRIS codes, provided they have configured the necessary API credentials.

## Glossary

- **QRIS**: Quick Response Code Indonesian Standard - Indonesia's national QR code payment standard
- **Payment_Provider**: A third-party service that processes QRIS payments (Midtrans or Xendit)
- **Dynamic_QRIS**: A unique QR code generated for each transaction with specific amount and reference
- **API_Credentials**: Authentication keys required to access provider APIs (API key, secret key, etc.)
- **Provider_Selection**: User's choice of which payment provider to use for QRIS generation
- **QrisService**: The service layer that handles QRIS transaction logic
- **ProviderFactory**: Component that instantiates the appropriate payment provider based on user selection

## Requirements

### Requirement 1: Xendit Provider Implementation

**User Story:** As a merchant, I want to use Xendit as my QRIS provider, so that I have flexibility in choosing payment processors.

#### Acceptance Criteria

1. THE System SHALL support Xendit as a payment provider option alongside Midtrans
2. WHEN a user configures Xendit API credentials, THE System SHALL validate and store them securely
3. WHEN generating a QRIS code with Xendit selected, THE System SHALL use Xendit's QRIS API
4. THE XenditProvider SHALL implement the PaymentProviderInterface consistently with other providers
5. WHEN Xendit API returns a response, THE System SHALL parse it into the standard QrisResponse DTO

### Requirement 2: Provider Credential Management

**User Story:** As a merchant, I want to securely configure my Xendit API credentials, so that the system can generate QRIS codes on my behalf.

#### Acceptance Criteria

1. WHEN a user submits Xendit credentials, THE System SHALL encrypt them before storage
2. THE System SHALL store Xendit API key and secret key in the payment_provider_credentials table
3. WHEN retrieving Xendit credentials, THE System SHALL decrypt them for API usage
4. IF Xendit credentials are invalid or missing, THEN THE System SHALL return a descriptive error message
5. THE System SHALL log all credential access attempts for audit purposes

### Requirement 3: Provider Selection Interface

**User Story:** As a merchant, I want to select which QRIS provider to use, so that I can control which service processes my payments.

#### Acceptance Criteria

1. WHEN a user accesses provider settings, THE System SHALL display available providers (Midtrans and Xendit)
2. WHEN a user has configured credentials for multiple providers, THE System SHALL allow selection of active provider
3. THE System SHALL persist the user's provider selection preference
4. WHEN generating QRIS, THE System SHALL use the currently selected active provider
5. IF no provider is selected or configured, THEN THE System SHALL return a NoActiveProviderException

### Requirement 4: Xendit QRIS API Integration

**User Story:** As a developer, I want the system to correctly integrate with Xendit's QRIS API, so that dynamic QRIS codes are generated successfully.

#### Acceptance Criteria

1. WHEN creating a QRIS transaction, THE XenditProvider SHALL call Xendit's Create QR Code API endpoint
2. THE XenditProvider SHALL include required parameters: external_id, type, callback_url, and amount
3. WHEN Xendit returns a successful response, THE System SHALL extract the QR string and reference ID
4. WHEN Xendit returns an error response, THE System SHALL throw a ProviderException with the error details
5. THE XenditProvider SHALL set appropriate HTTP headers including API key authentication

### Requirement 5: Webhook Handling for Xendit

**User Story:** As a merchant, I want to receive payment notifications from Xendit, so that orders are automatically updated when customers pay.

#### Acceptance Criteria

1. WHEN Xendit sends a webhook notification, THE System SHALL verify the webhook signature
2. THE System SHALL parse Xendit webhook payload into standard transaction status format
3. WHEN a payment is successful, THE System SHALL update the corresponding QrisTransaction status
4. WHEN a payment fails or expires, THE System SHALL update the transaction status accordingly
5. THE System SHALL respond to Xendit webhooks with appropriate HTTP status codes

### Requirement 6: Provider Factory Enhancement

**User Story:** As a developer, I want the ProviderFactory to support Xendit, so that the correct provider instance is created based on user configuration.

#### Acceptance Criteria

1. WHEN ProviderFactory receives 'xendit' as provider type, THE Factory SHALL instantiate XenditProvider
2. THE ProviderFactory SHALL pass decrypted credentials to the XenditProvider constructor
3. IF an unsupported provider type is requested, THEN THE Factory SHALL throw UnsupportedProviderException
4. THE ProviderFactory SHALL maintain consistent interface across all provider types
5. WHEN credentials are missing for requested provider, THE Factory SHALL throw appropriate exception

### Requirement 7: Backward Compatibility

**User Story:** As an existing merchant using Midtrans, I want the system to continue working without changes, so that my current operations are not disrupted.

#### Acceptance Criteria

1. WHEN a user has only Midtrans configured, THE System SHALL continue using Midtrans as default
2. THE System SHALL maintain existing Midtrans functionality without regression
3. WHEN migrating to multi-provider support, THE System SHALL preserve existing transaction data
4. THE System SHALL support existing API endpoints and response formats
5. IF no provider preference is set, THE System SHALL default to the first available configured provider

### Requirement 8: Error Handling and Validation

**User Story:** As a merchant, I want clear error messages when QRIS generation fails, so that I can troubleshoot configuration issues.

#### Acceptance Criteria

1. WHEN Xendit API credentials are invalid, THE System SHALL return "Invalid Xendit credentials" error
2. WHEN Xendit API is unreachable, THE System SHALL return a timeout error with retry suggestion
3. WHEN required parameters are missing, THE System SHALL return validation errors listing missing fields
4. THE System SHALL log all provider errors with sufficient context for debugging
5. WHEN a provider exception occurs, THE System SHALL sanitize error messages before displaying to users
