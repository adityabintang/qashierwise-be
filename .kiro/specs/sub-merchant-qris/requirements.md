# Requirements Document

## Introduction

A comprehensive sub-merchant QRIS payment system that enables users to become sub-merchants, generate dynamic QRIS codes for their customers, manage their earnings balance, and request withdrawals. The system integrates with Midtrans payment gateway and includes platform fee management with admin oversight.

## Glossary

- **Sub_Merchant**: A registered user who can generate QRIS codes and receive payments
- **QRIS**: Quick Response Code Indonesian Standard for payments
- **Dynamic_QRIS**: QR code generated per transaction with unique order_id
- **Platform_Fee**: 2.5% commission deducted from each successful transaction
- **Merchant_Balance**: Available funds for a sub-merchant after platform fees
- **Withdrawal_Request**: Sub-merchant request to transfer balance to their bank account
- **Admin_Panel**: Interface for administrators to manage withdrawal approvals
- **Midtrans_Gateway**: Payment processing service provider
- **Bank_Account**: Sub-merchant's registered bank account for withdrawals

## Requirements

### Requirement 1: Sub-Merchant Registration and Management

**User Story:** As a user, I want to register as a sub-merchant with my bank account details, so that I can start accepting payments and receive withdrawals.

#### Acceptance Criteria

1. WHEN a user registers as a sub-merchant, THE System SHALL collect and validate bank account information (bank name, account number, account holder name)
2. WHEN existing users want to become sub-merchants, THE System SHALL allow them to add bank account details through settings
3. WHEN bank account information is provided, THE System SHALL validate the account format and required fields
4. THE System SHALL store sub-merchant status and bank account details securely
5. WHEN sub-merchant registration is complete, THE System SHALL initialize their balance to zero

### Requirement 2: Dynamic QRIS Generation

**User Story:** As a sub-merchant, I want to generate dynamic QRIS codes for each transaction, so that my customers can pay me directly with unique tracking.

#### Acceptance Criteria

1. WHEN a sub-merchant requests a QRIS, THE System SHALL generate a unique order_id for the transaction
2. WHEN generating QRIS, THE System SHALL create the QR code through Midtrans API with sub-merchant details
3. WHEN QRIS is generated, THE System SHALL provide both image and shareable link formats
4. THE System SHALL associate each QRIS with the specific sub-merchant and order details
5. WHEN QRIS expires or is used, THE System SHALL prevent reuse of the same code

### Requirement 3: Payment Processing and Fee Management

**User Story:** As the platform, I want to automatically deduct platform fees from successful transactions, so that revenue is properly managed.

#### Acceptance Criteria

1. WHEN a payment is successful via QRIS, THE System SHALL receive webhook notification from Midtrans
2. WHEN processing successful payment, THE System SHALL calculate platform fee as 2.5% of transaction amount
3. WHEN calculating merchant earnings, THE System SHALL deduct platform fee from gross amount
4. THE System SHALL update sub-merchant balance with net amount (gross - platform fee)
5. WHEN balance is updated, THE System SHALL log the transaction with fee breakdown

### Requirement 4: Balance Management and Tracking

**User Story:** As a sub-merchant, I want to view my current balance and transaction history, so that I can track my earnings.

#### Acceptance Criteria

1. THE System SHALL display current available balance for each sub-merchant
2. WHEN displaying balance, THE System SHALL show pending and available amounts separately
3. THE System SHALL provide transaction history with fee breakdowns
4. WHEN transactions occur, THE System SHALL update balance in real-time
5. THE System SHALL prevent negative balances and handle edge cases

### Requirement 5: Withdrawal Request System

**User Story:** As a sub-merchant, I want to request withdrawal of my balance to my bank account, so that I can access my earnings.

#### Acceptance Criteria

1. WHEN requesting withdrawal, THE System SHALL enforce minimum withdrawal amount of Rp 10,000
2. WHEN withdrawal is requested, THE System SHALL validate sufficient available balance
3. THE System SHALL create withdrawal request with pending status
4. WHEN withdrawal is submitted, THE System SHALL deduct requested amount from available balance
5. THE System SHALL send notification to admins for withdrawal approval

### Requirement 6: Admin Withdrawal Approval

**User Story:** As an administrator, I want to review and approve withdrawal requests, so that I can ensure proper fund management.

#### Acceptance Criteria

1. THE Admin_Panel SHALL display all pending withdrawal requests
2. WHEN reviewing requests, THE Admin_Panel SHALL show sub-merchant details and bank account information
3. WHEN approving withdrawal, THE System SHALL process bank transfer and update request status
4. WHEN rejecting withdrawal, THE System SHALL return amount to sub-merchant balance and notify them
5. THE Admin_Panel SHALL maintain audit trail of all approval actions

### Requirement 7: Frontend Balance and Withdrawal Management

**User Story:** As a sub-merchant, I want an intuitive interface to manage my balance and withdrawals, so that I can easily access my earnings.

#### Acceptance Criteria

1. THE Frontend SHALL display current balance prominently on dashboard
2. WHEN viewing balance, THE Frontend SHALL show earnings breakdown and transaction history
3. THE Frontend SHALL provide withdrawal request form with validation
4. WHEN submitting withdrawal, THE Frontend SHALL show confirmation and status updates
5. THE Frontend SHALL display withdrawal history with status tracking

### Requirement 8: QRIS Sharing and Distribution

**User Story:** As a sub-merchant, I want to easily share QRIS codes with my customers, so that they can make payments conveniently.

#### Acceptance Criteria

1. WHEN QRIS is generated, THE System SHALL provide downloadable QR code image
2. THE System SHALL generate shareable links for QRIS codes
3. WHEN sharing QRIS, THE System SHALL ensure proper expiration handling
4. THE System SHALL provide multiple sharing options (WhatsApp, email, direct link)
5. WHEN QRIS is accessed via link, THE System SHALL display payment interface

### Requirement 9: Security and Validation

**User Story:** As the platform, I want to ensure secure handling of financial data and transactions, so that user funds and information are protected.

#### Acceptance Criteria

1. THE System SHALL encrypt all bank account information at rest
2. WHEN processing payments, THE System SHALL validate webhook signatures from Midtrans
3. THE System SHALL implement rate limiting for QRIS generation
4. WHEN handling withdrawals, THE System SHALL require additional authentication
5. THE System SHALL log all financial transactions for audit purposes

### Requirement 10: Integration with Existing User System

**User Story:** As an existing user, I want to seamlessly upgrade to sub-merchant status, so that I can start accepting payments without creating a new account.

#### Acceptance Criteria

1. WHEN existing users access sub-merchant features, THE System SHALL prompt for bank account setup
2. THE System SHALL maintain existing user data while adding sub-merchant capabilities
3. WHEN upgrading to sub-merchant, THE System SHALL preserve user preferences and settings
4. THE System SHALL provide clear navigation between regular user and sub-merchant features
5. WHEN sub-merchant features are disabled, THE System SHALL gracefully handle feature access