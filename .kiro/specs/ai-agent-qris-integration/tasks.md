# Implementation Plan: AI Agent QRIS Integration

## Overview

Implementasi integrasi AI Agent dengan sistem QRIS yang sudah ada. Tasks disusun secara incremental, dimulai dari database migrations, kemudian model updates, service updates, dan terakhir controller/test endpoint updates.

## Tasks

- [x] 1. Database Migrations
  - [x] 1.1 Create migration to add order_id to qris_transactions table
    - Add nullable foreign key order_id referencing orders table
    - Add index for order_id
    - _Requirements: 4.1, 4.2_
  - [x] 1.2 Create migration to add source and customer fields to orders table
    - Add source column with default 'pos'
    - Add customer_name and customer_phone nullable columns
    - Add index on source column
    - _Requirements: 1.2, 1.3_
  - [x] 1.3 Create migration to add payment context to ai_agent_conversations table
    - Add current_order_id nullable foreign key
    - Add current_qris_transaction_id nullable foreign key
    - _Requirements: 6.3_
  - [x] 1.4 Create payments table migration (if not exists)
    - Create table with order_id, qris_transaction_id, method, amount, status, paid_at
    - Add indexes for order_id and status
    - _Requirements: 2.1, 2.2_

- [x] 2. Model Updates
  - [x] 2.1 Update QrisTransaction model
    - Add order_id to fillable
    - Add order() BelongsTo relationship
    - _Requirements: 4.1, 4.2_
  - [x] 2.2 Update Order model
    - Add SOURCE_POS and SOURCE_WHATSAPP_AI constants
    - Add source, customer_name, customer_phone to fillable
    - Add scopeFromWhatsAppAi() and scopeFromPos() query scopes
    - Add payments() HasMany relationship
    - _Requirements: 1.2, 1.3, 1.5_
  - [x] 2.3 Create or update Payment model
    - Define METHOD and STATUS constants
    - Add fillable fields
    - Add order() and qrisTransaction() relationships
    - Add markAsPaid() method with cascading Order update
    - _Requirements: 2.1, 2.2, 2.3, 2.4_
  - [x] 2.4 Update AiAgentConversation model
    - Add current_order_id and current_qris_transaction_id to fillable
    - Add setCurrentOrder(), getCurrentOrder() methods
    - Add setCurrentQrisTransaction(), getCurrentQrisTransaction() methods
    - Add clearPaymentContext() method
    - _Requirements: 6.3_
  - [x] 2.5 Update AiAgent model
    - Add isQrisEnabled() method with full validation
    - Add getSubMerchant() method
    - Add hasActiveSubMerchant() method
    - Add hasActivePaymentProvider() method
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 3. AiAgentService QRIS Integration
  - [x] 3.1 Add QrisService dependency injection
    - Inject QrisService in constructor
    - _Requirements: 3.2_
  - [x] 3.2 Implement getToolDefinitions() with QRIS tools
    - Add generate_qris tool definition when qris_enabled
    - Add check_payment_status tool definition when qris_enabled
    - _Requirements: 3.1, 7.1_
  - [x] 3.3 Implement  generateQrisForOrder() method
    - Get SubMerchant from AI Agent
    - Call QrisService::generateQris() with order details
    - Create Payment record linked to Order
    - Store transaction IDs in conversation context
    - Format and return QRIS message with QR URL, amount, expiry
    - Handle errors gracefully with user-friendly messages
    - _Requirements: 3.2, 3.3, 3.4, 3.5, 6.1, 6.2, 9.1, 9.2, 9.3_
  - [x] 3.4 Implement checkPaymentStatus() method
    - Get current QrisTransaction from conversation context
    - Query transaction status
    - Return appropriate message based on status (paid/pending/expired)
    - Offer regeneration for expired transactions
    - _Requirements: 7.2, 7.3, 7.4, 7.5, 9.4_
  - [x] 3.5 Update executeToolCall() to handle QRIS tools
    - Add case for 'generate_qris' calling generateQrisForOrder()
    - Add case for 'check_payment_status' calling checkPaymentStatus()
    - _Requirements: 3.1, 7.1_
  - [x] 3.6 Update confirmAndCreateOrder() for QRIS flow
    - Create Order with source='whatsapp_ai' and customer info
    - If qris_enabled, automatically generate QRIS after order creation
    - Store order and transaction IDs in conversation
    - _Requirements: 1.1, 1.2, 1.3, 6.1_

- [x] 4. Checkpoint - Run migrations and verify models
  - Run migrations
  - Verify model relationships work correctly
  - Ensure all tests pass, ask the user if questions arise

- [x] 5. AiAgentController Updates
  - [x] 5.1 Add QrisService dependency injection
    - Inject QrisService in constructor
    - _Requirements: 11.5_
  - [x] 5.2 Update test() method with QRIS tools
    - Include generate_qris and check_payment_status in test tool definitions
    - Pass conversation to executeTestToolCall for context tracking
    - _Requirements: 11.1, 11.2_
  - [x] 5.3 Implement executeTestToolCall() for QRIS
    - Add generate_qris case that calls QrisService
    - Add check_payment_status case that queries transaction
    - Create real QrisTransaction and Payment records
    - Return QR code URL in response
    - _Requirements: 11.3, 11.4, 11.6_
  - [x] 5.4 Implement toggleQris() endpoint with validation
    - Check for active SubMerchant
    - Check for active payment provider credentials
    - Return descriptive error messages if validation fails
    - Enable qris_enabled only if all validations pass
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [x] 6. Webhook Integration
  - [x] 6.1 Update webhook controllers to cascade status updates
    - When QrisTransaction is marked paid, update linked Payment
    - When Payment is marked paid, update linked Order
    - _Requirements: 10.1, 10.2, 10.3_
  - [x] 6.2 Add optional notification to AI Agent on payment completion
    - Check if QrisTransaction has linked conversation
    - Send confirmation message to customer via WhatsApp
    - _Requirements: 10.4_

- [x] 7. Checkpoint - Test QRIS flow end-to-end
  - Test QRIS generation via test endpoint
  - Verify QrisTransaction and Payment records are created
  - Ensure all tests pass, ask the user if questions arise

- [x] 8. API Routes
  - [x] 8.1 Add toggleQris route
    - POST /api/ai-agent/toggle-qris
    - _Requirements: 8.4_

- [ ]* 9. Unit Tests
  - [ ]* 9.1 Write tests for AiAgent::isQrisEnabled()
    - Test returns true when all conditions met
    - Test returns false when qris_enabled is false
    - Test returns false when no SubMerchant
    - Test returns false when no active provider
    - _Requirements: 5.4_
  - [ ]* 9.2 Write tests for AiAgentService::generateQrisForOrder()
    - Test successful QRIS generation
    - Test error handling when no SubMerchant
    - Test error handling when provider fails
    - _Requirements: 3.2, 3.3, 3.4, 3.5_
  - [ ]* 9.3 Write tests for AiAgentService::checkPaymentStatus()
    - Test response for paid status
    - Test response for pending status
    - Test response for expired status
    - _Requirements: 7.3, 7.4, 7.5_
  - [ ]* 9.4 Write tests for Payment::markAsPaid()
    - Test Payment status updated
    - Test Order status cascaded
    - _Requirements: 2.3, 2.4_

- [ ]* 10. Property-Based Tests
  - [ ]* 10.1 Write property test for Order Creation Integrity
    - **Property 1: Order Creation Integrity**
    - **Validates: Requirements 1.1, 1.2, 1.3**
  - [ ]* 10.2 Write property test for Payment-Order Linkage
    - **Property 2: Payment-Order Linkage**
    - **Validates: Requirements 2.1, 2.2**
  - [ ]* 10.3 Write property test for QRIS Tool Availability
    - **Property 4: QRIS Tool Availability**
    - **Validates: Requirements 3.1, 7.1**
  - [ ]* 10.4 Write property test for QRIS Enable Validation
    - **Property 7: QRIS Enable Validation**
    - **Validates: Requirements 8.1, 8.2, 8.3, 8.4**

- [ ]* 11. Integration Tests
  - [ ]* 11.1 Write integration test for full QRIS flow
    - Test order creation → QRIS generation → payment → webhook
    - Verify all records created and linked correctly
    - _Requirements: 3.2, 6.1, 10.1, 10.2_
  - [ ]* 11.2 Write integration test for test endpoint QRIS
    - Test QRIS generation via /api/ai-agent/test
    - Verify real QrisTransaction created
    - Verify QR code URL in response
    - _Requirements: 11.1, 11.2, 11.3, 11.6_

- [ ] 12. Final Checkpoint
  - Run all tests
  - Verify QRIS flow works in test endpoint
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
