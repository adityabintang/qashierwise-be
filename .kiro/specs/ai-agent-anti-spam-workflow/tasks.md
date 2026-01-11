# Implementation Plan: AI Agent Anti-Spam Workflow

## Overview

This implementation plan breaks down the anti-spam workflow feature into discrete coding tasks. The workflow will be integrated into the existing WhatsApp message processing pipeline to provide rate limiting, message deduplication, and seen status functionality.

## Tasks

- [x] 1. Create configuration file for anti-spam settings
  - Create `config/ai_agent.php` with anti-spam configuration structure
  - Define rate limit settings (max_messages, ttl_seconds)
  - Define deduplication settings (ttl_seconds)
  - Define seen status settings (enabled flag)
  - Set default values matching requirements
  - _Requirements: 5.1, 5.2, 5.3_

- [x] 2. Implement RateLimiter service
  - [x] 2.1 Create RateLimiter service class
    - Create `app/Services/RateLimiter.php`
    - Implement `checkLimit()` method to verify if user is under limit
    - Implement `incrementCounter()` method with Redis INCR
    - Implement `getCount()` method to retrieve current count
    - Use Redis key format: `rateLimit#{phoneNumber}`
    - Set TTL of 86400 seconds on first increment
    - Handle Redis connection failures gracefully
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 6.1, 6.2_

  - [ ]* 2.2 Write property test for rate limiter
    - **Property 1-3: Rate Limit Counter, TTL, and Enforcement**
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4**
    - Generate random phone numbers
    - Verify counter increments correctly
    - Verify TTL is set to 86400 seconds
    - Verify messages are rejected when count reaches 20
    - Verify messages are allowed when count is below 20

  - [ ]* 2.3 Write unit tests for rate limiter edge cases
    - Test boundary condition at exactly 20 messages
    - Test Redis connection failure handling
    - Test TTL setting on first vs subsequent increments
    - _Requirements: 1.3, 6.1, 6.2_

- [x] 3. Implement MessageDeduplicator service
  - [x] 3.1 Create MessageDeduplicator service class
    - Create `app/Services/MessageDeduplicator.php`
    - Implement `isDuplicate()` method to check for existing key
    - Implement `markAsSeen()` method to store deduplication key
    - Use Redis key format: `dedup#{phoneNumber}#{contentHash}`
    - Generate MD5 hash of message content
    - Use configurable TTL from config
    - Handle Redis connection failures gracefully
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 6.1, 6.2_

  - [ ]* 3.2 Write property test for message deduplicator
    - **Property 6-8: Deduplication Key Storage, Query, and Handling**
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**
    - Generate random phone numbers and message content
    - Verify deduplication keys are stored with correct format
    - Verify TTL is set from configuration
    - Verify duplicate messages are detected
    - Verify unique messages are allowed

  - [ ]* 3.3 Write unit tests for deduplicator edge cases
    - Test identical message detection
    - Test key format with special characters in phone number
    - Test Redis connection failure handling
    - _Requirements: 3.4, 6.1, 6.2_

- [x] 4. Implement SeenStatusSender service
  - [x] 4.1 Create SeenStatusSender service class
    - Create `app/Services/SeenStatusSender.php`
    - Implement `sendSeenStatus()` method
    - Use WhatsApp Cloud API endpoint for marking messages as read
    - Use user-specific access token from WhatsAppAccount
    - Format request body correctly: `{"messaging_product": "whatsapp", "status": "read", "message_id": "..."}`
    - Handle API failures gracefully (log and continue)
    - Return boolean indicating success/failure
    - _Requirements: 2.1, 2.2, 6.3_

  - [ ]* 4.2 Write property test for seen status sender
    - **Property 4-5: Seen Status Sent and HTTP Request Format**
    - **Validates: Requirements 2.1, 2.2**
    - Generate random WhatsApp accounts and message IDs
    - Verify HTTP POST request is made to correct endpoint
    - Verify request includes correct authentication header
    - Verify request body has correct format
    - Mock WhatsApp API responses

  - [ ]* 4.3 Write unit tests for seen status sender error handling
    - Test API failure handling (4xx, 5xx responses)
    - Test network timeout handling
    - Test with invalid access tokens
    - Verify errors are logged but don't throw exceptions
    - _Requirements: 6.3_

- [x] 5. Checkpoint - Ensure all service tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implement AntiSpamWorkflow orchestration service
  - [x] 6.1 Create AntiSpamWorkflow service class
    - Create `app/Services/AntiSpamWorkflow.php`
    - Inject RateLimiter, MessageDeduplicator, and SeenStatusSender dependencies
    - Implement `validate()` method that orchestrates all steps
    - Execute steps in order: rate limiting → deduplication → seen status
    - Return false if rate limit exceeded
    - Return false if duplicate detected
    - Send seen status (non-blocking, log errors)
    - Return true if all validations pass
    - Log each step for monitoring
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [ ]* 6.2 Write property test for workflow orchestration
    - **Property 9-11: Workflow Step Ordering, Halt on Failure, Success Path**
    - **Validates: Requirements 4.2, 4.3, 4.4**
    - Generate random messages with various validation states
    - Verify steps execute in correct order
    - Verify workflow halts when rate limit exceeded
    - Verify workflow halts when duplicate detected
    - Verify workflow continues when all validations pass
    - Verify seen status is sent for valid messages

  - [ ]* 6.3 Write unit tests for workflow integration
    - Test workflow with rate limit exceeded
    - Test workflow with duplicate message
    - Test workflow with all validations passing
    - Test seen status failure doesn't block workflow
    - _Requirements: 4.2, 4.3, 4.4_

- [x] 7. Integrate workflow into WhatsAppWebhookController
  - [x] 7.1 Update webhook controller to use anti-spam workflow
    - Inject AntiSpamWorkflow service into WhatsAppWebhookController
    - In `handleIncomingMessage()` method, call workflow validation before dispatching AI agent job
    - Only dispatch `ProcessAiAgentMessage` job if workflow returns true
    - Log rejection reasons (rate limit exceeded, duplicate detected)
    - Ensure seen status is sent even if message is rejected
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [ ]* 7.2 Write integration test for webhook with anti-spam workflow
    - Test webhook receives message and applies workflow
    - Test message is dispatched to queue when validations pass
    - Test message is not dispatched when rate limit exceeded
    - Test message is not dispatched when duplicate detected
    - Test seen status is sent in all cases
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [x] 8. Implement configuration validation and fallback
  - [x] 8.1 Add configuration validation methods
    - Create helper methods in each service to validate configuration
    - Validate rate limit max_messages is positive integer
    - Validate TTL values are positive integers
    - Fall back to default values if validation fails
    - Log warnings when invalid configuration is detected
    - _Requirements: 5.4, 5.5_

  - [ ]* 8.2 Write property test for configuration handling
    - **Property 12-13: Configuration Value Application and Fallback**
    - **Validates: Requirements 5.4, 5.5**
    - Generate random configuration values (valid and invalid)
    - Verify valid configurations are applied correctly
    - Verify invalid configurations fall back to defaults
    - Verify configuration changes apply to subsequent messages

  - [ ]* 8.3 Write unit tests for configuration edge cases
    - Test with negative values
    - Test with non-numeric values
    - Test with null values
    - Test with missing configuration keys
    - _Requirements: 5.5_

- [x] 9. Checkpoint - Ensure integration tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Implement comprehensive error handling and logging
  - [x] 10.1 Add error handling to all services
    - Wrap Redis operations in try-catch blocks
    - Wrap HTTP requests in try-catch blocks
    - Log errors with appropriate levels (ERROR for critical, WARNING for degraded)
    - Include context in logs: timestamp, error type, operation attempted
    - Ensure graceful degradation (continue processing on errors)
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [ ]* 10.2 Write property test for error handling
    - **Property 14-15: Graceful Degradation and Error Logging**
    - **Validates: Requirements 6.2, 6.4**
    - Generate random failure scenarios (Redis down, API errors)
    - Verify processing continues despite failures
    - Verify errors are logged with required information
    - Verify log entries include timestamp and error type

  - [ ]* 10.3 Write unit tests for specific error scenarios
    - Test Redis connection failure in rate limiter
    - Test Redis connection failure in deduplicator
    - Test WhatsApp API failure in seen status sender
    - Verify all errors are logged appropriately
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [x] 11. Add environment variables and documentation
  - [x] 11.1 Update .env.example with anti-spam configuration
    - Add `AI_AGENT_RATE_LIMIT_ENABLED=true`
    - Add `AI_AGENT_RATE_LIMIT_MAX=20`
    - Add `AI_AGENT_RATE_LIMIT_TTL=86400`
    - Add `AI_AGENT_DEDUP_ENABLED=true`
    - Add `AI_AGENT_DEDUP_TTL=300`
    - Add `AI_AGENT_SEEN_STATUS_ENABLED=true`
    - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 11.2 Create documentation for anti-spam workflow
    - Document configuration options
    - Document how to adjust rate limits
    - Document how to disable features
    - Document monitoring and logging
    - _Requirements: 5.1, 5.2, 5.3_

- [x] 12. Final checkpoint - End-to-end testing
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties using Eris library
- Unit tests validate specific examples and edge cases
- Integration tests verify the complete workflow from webhook to AI agent dispatch
- All services should handle failures gracefully and log appropriately
