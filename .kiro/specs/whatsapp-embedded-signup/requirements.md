# Requirements Document

## Introduction

This document specifies the requirements for implementing WhatsApp Embedded Signup v4 integration in the QashierWise application. The feature enables users to connect their own WhatsApp Business accounts through Meta's Embedded Signup flow, replacing the current single-account configuration that relies on hardcoded credentials (`WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_BUSINESS_ACCOUNT_ID`).

With Embedded Signup v4, users can onboard their WhatsApp Business accounts with coexistence support, allowing them to use their existing WhatsApp number while also connecting to the Cloud API. Each user will have their own WhatsApp credentials stored in the database, enabling multi-tenant WhatsApp messaging capabilities.

## Glossary

- **Embedded Signup (ES)**: Meta's OAuth-based flow that allows users to connect their WhatsApp Business accounts to third-party applications
- **ES v4**: Version 4 of Embedded Signup with coexistence feature support
- **Coexistence**: Feature allowing users to use their existing WhatsApp number with Cloud API without losing personal WhatsApp access
- **WABA**: WhatsApp Business Account - the business entity that owns phone numbers
- **Phone Number ID**: Unique identifier for a WhatsApp phone number in the Cloud API
- **Access Token**: OAuth token used to authenticate API requests to WhatsApp Cloud API
- **System User Access Token (SUAT)**: Long-lived token generated for API access
- **Config ID**: Unique identifier for Embedded Signup configuration in Meta Developer Console
- **WhatsApp Cloud API**: Meta's API for sending and receiving WhatsApp messages
- **Facebook SDK**: JavaScript library for Facebook/Meta authentication flows

## Requirements

### Requirement 1

**User Story:** As a user, I want to connect my WhatsApp Business account through an embedded signup flow, so that I can send and receive WhatsApp messages using my own business number.

#### Acceptance Criteria

1. WHEN a user clicks the "Connect WhatsApp" button THEN the System SHALL initialize the Facebook SDK and launch the Embedded Signup dialog with Config ID 828935343106633
2. WHEN the Embedded Signup dialog opens THEN the System SHALL request permissions for whatsapp_business_management and whatsapp_business_messaging
3. WHEN a user completes the Embedded Signup flow THEN the System SHALL receive a code parameter from the Facebook SDK callback
4. WHEN the System receives a valid code from Embedded Signup THEN the System SHALL exchange the code for an access token using the Facebook Graph API
5. WHEN the System obtains an access token THEN the System SHALL retrieve the user's WABA ID and Phone Number ID from the WhatsApp Business Management API

### Requirement 2

**User Story:** As a user, I want my WhatsApp account credentials to be securely stored, so that I can use WhatsApp features without re-authenticating.

#### Acceptance Criteria

1. WHEN the System successfully retrieves WhatsApp credentials THEN the System SHALL store the phone_number_id, waba_id, and access_token in the whatsapp_accounts table associated with the authenticated user
2. WHEN storing access tokens THEN the System SHALL encrypt the access_token field before database storage
3. WHEN a user already has a connected WhatsApp account THEN the System SHALL update the existing record instead of creating a duplicate
4. WHEN storing WhatsApp account data THEN the System SHALL also store display_name, verified_name, and quality_rating retrieved from the API

### Requirement 3

**User Story:** As a user, I want to use my connected WhatsApp account for messaging, so that messages are sent from my business number instead of a shared number.

#### Acceptance Criteria

1. WHEN a user sends a WhatsApp message THEN the System SHALL use the user's stored phone_number_id and access_token from the whatsapp_accounts table
2. WHEN a user has no connected WhatsApp account THEN the System SHALL return an error indicating WhatsApp account connection is required
3. WHEN making WhatsApp API calls THEN the System SHALL dynamically configure the WhatsApp client with the user's credentials
4. WHEN a user's access token expires or becomes invalid THEN the System SHALL return an appropriate error and prompt for re-authentication

### Requirement 4

**User Story:** As a user, I want to view and manage my connected WhatsApp account, so that I can see my connection status and disconnect if needed.

#### Acceptance Criteria

1. WHEN a user views their WhatsApp settings THEN the System SHALL display the connected phone number, business name, and connection status
2. WHEN a user requests to disconnect their WhatsApp account THEN the System SHALL remove the stored credentials and mark the account as inactive
3. WHEN displaying WhatsApp account status THEN the System SHALL show the quality_rating and verified_name from the stored data
4. WHEN a user has multiple WABA accounts THEN the System SHALL allow selection of which account to use

### Requirement 5

**User Story:** As a developer, I want the webhook to route incoming messages to the correct user, so that each user receives only their own WhatsApp messages.

#### Acceptance Criteria

1. WHEN the webhook receives an incoming message THEN the System SHALL identify the target user by matching the phone_number_id from the webhook payload with stored whatsapp_accounts records
2. WHEN the webhook cannot find a matching whatsapp_account THEN the System SHALL log the event and skip processing
3. WHEN routing webhook events THEN the System SHALL associate messages, status updates, and other events with the correct user's WhatsApp account
4. WHEN multiple users have connected accounts THEN the System SHALL ensure message isolation between users

### Requirement 6

**User Story:** As a system administrator, I want to configure the Embedded Signup integration, so that the application can authenticate with Meta's APIs.

#### Acceptance Criteria

1. WHEN the application starts THEN the System SHALL load WHATSAPP_APP_ID, WHATSAPP_APP_SECRET, and WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID from environment variables
2. WHEN any required configuration is missing THEN the System SHALL log a warning and disable the Embedded Signup feature
3. WHEN configuring the Facebook SDK THEN the System SHALL use API version v22.0 and ES version 4 for coexistence support

### Requirement 7

**User Story:** As a user, I want to receive real-time feedback during the signup process, so that I know the status of my WhatsApp connection.

#### Acceptance Criteria

1. WHEN the Embedded Signup flow is in progress THEN the System SHALL display a loading indicator
2. WHEN the connection succeeds THEN the System SHALL display a success message with the connected phone number
3. WHEN the connection fails THEN the System SHALL display an error message with actionable guidance
4. IF the user cancels the Embedded Signup dialog THEN the System SHALL handle the cancellation gracefully and return to the previous state

### Requirement 8

**User Story:** As a user, I want to use the coexistence feature, so that I can keep using my personal WhatsApp while also using the business API.

#### Acceptance Criteria

1. WHEN initiating Embedded Signup THEN the System SHALL enable coexistence mode by using ES v4 configuration
2. WHEN a user connects a number with coexistence THEN the System SHALL store a flag indicating coexistence is enabled
3. WHEN displaying account information THEN the System SHALL indicate whether the account is using coexistence mode
