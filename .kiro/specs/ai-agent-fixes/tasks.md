# Implementation Plan: AI Agent Order & Conversation Fixes

## Overview

This plan addresses three critical bugs: tool calling data isolation, test endpoint tool implementations, and conversation message storage. Tasks are organized to fix the most critical issues first (data isolation), then add missing functionality (test endpoint tools), and finally verify message storage.

## Tasks

- [x] 1. Fix data isolation in AiAgentService tool functions
  - Add user_id filtering to all product queries
  - Validate product ownership before operations
  - Ensure no cross-user data access
  - _Requirements: 1.1, 1.2, 1.3, 1.8_

- [x] 1.1 Fix searchProducts() to filter by user_id
  - Add `where('user_id', $userId)` to product query
  - Ensure search only returns user's products
  - _Requirements: 1.1_

- [x] 1.2 Fix getProductDetails() to validate ownership
  - Add `where('user_id', $userId)` to product query
  - Return error if product not found or doesn't belong to user
  - _Requirements: 1.2_

- [x] 1.3 Fix addToCart() to validate product ownership
  - Add `where('user_id', $userId)` when fetching product
  - Validate product exists and belongs to user before adding to cart
  - _Requirements: 1.3_

- [ ]* 1.4 Write property test for data isolation
  - **Property 1: Data Isolation in Tool Calls**
  - **Validates: Requirements 1.1, 1.2, 1.3, 1.8**
  - Generate random user_ids and products
  - Verify tool calls only return data for authenticated user
  - Test with multiple users to ensure no cross-user access

- [x] 2. Implement missing tool functions in test endpoint
  - Add get_product_details tool
  - Add add_to_cart tool
  - Add get_cart_summary tool
  - Add confirm_order tool with order creation
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

- [x] 2.1 Implement getProductDetails() in test endpoint
  - Add case in executeTestToolCall() for 'get_product_details'
  - Call existing searchProducts logic but for single product
  - Return formatted product details
  - _Requirements: 1.2_

- [x] 2.2 Implement addToCart() in test endpoint
  - Add case in executeTestToolCall() for 'add_to_cart'
  - Validate product exists and belongs to user
  - Check stock availability
  - Add to conversation's cart context
  - Return success message in Indonesian
  - _Requirements: 4.1_

- [x] 2.3 Implement getCartSummary() in test endpoint
  - Add case in executeTestToolCall() for 'get_cart_summary'
  - Retrieve cart from conversation's order_context
  - Calculate subtotal, tax, and total
  - Return formatted cart summary
  - _Requirements: 4.1_

- [x] 2.4 Implement confirmOrder() in test endpoint
  - Add case in executeTestToolCall() for 'confirm_order'
  - Create new protected method createTestOrder()
  - Use OrderService to create Order record
  - Use OrderService to add OrderItem records for each cart item
  - Store order_id in conversation's current_order_id
  - Clear cart from order_context
  - Return success message with order number
  - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.6_

- [ ]* 2.5 Write property test for order creation
  - **Property 4: Order Creation Completeness**
  - **Validates: Requirements 4.2, 4.4, 4.5, 4.6**
  - Generate random cart contents
  - Create order via test endpoint
  - Verify Order record created
  - Verify OrderItem records created for all cart items
  - Verify order_id stored in conversation
  - Verify cart cleared

- [x] 3. Checkpoint - Test order flow end-to-end
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Fix QRIS generation in test endpoint
  - Ensure SubMerchant retrieval uses correct user
  - Link QRIS transaction to order when order exists
  - Store transaction_id in conversation context
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7_

- [x] 4.1 Verify generateTestQris() uses correct SubMerchant
  - Ensure aiAgent.getSubMerchant() is called
  - Verify SubMerchant belongs to authenticated user
  - Add validation error messages
  - _Requirements: 5.2, 1.6_

- [x] 4.2 Fix QRIS transaction linking to orders
  - Check if conversation has current_order_id
  - If order exists, set qrisTransaction.linked_order_id
  - Create Payment record linking order and QRIS transaction
  - _Requirements: 5.5_

- [x] 4.3 Store QRIS transaction in conversation context
  - Call conversation.setCurrentQrisTransaction() after generation
  - Ensure transaction_id stored in current_qris_transaction_id field
  - _Requirements: 5.4_

- [ ]* 4.4 Write property test for QRIS linking
  - **Property 5: QRIS Transaction Linking**
  - **Validates: Requirements 5.5**
  - Generate random order and QRIS data
  - Create order then generate QRIS
  - Verify QrisTransaction.linked_order_id set correctly
  - Verify Payment record created

- [x] 5. Fix conversation message storage and response format
  - Verify addMessage() stores correct structure
  - Fix test endpoint response to return proper conversation_history
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 7.1, 7.2, 7.5_

- [x] 5.1 Verify AiAgentConversation.addMessage() structure
  - Review current implementation
  - Ensure each message has role, content, timestamp
  - Verify messages stored as array of objects
  - No code changes needed (already correct)
  - _Requirements: 3.1, 3.2, 3.5_

- [x] 5.2 Fix test endpoint conversation_history response
  - Ensure test() method returns conversation.messages array
  - Verify both user and assistant messages included
  - Ensure messages in chronological order
  - _Requirements: 7.1, 7.5_

- [ ]* 5.3 Write property test for message storage
  - **Property 6: Message Storage Format**
  - **Validates: Requirements 3.1, 3.2, 3.5**
  - Generate random message sequences
  - Add messages to conversation
  - Verify each stored as separate array entry
  - Verify structure includes role, content, timestamp

- [ ]* 5.4 Write property test for conversation chronology
  - **Property 7: Conversation History Chronology**
  - **Validates: Requirements 3.5**
  - Generate random messages with timestamps
  - Retrieve conversation history
  - Verify messages returned in chronological order

- [x] 6. Add comprehensive error handling and logging
  - Add try-catch blocks to all tool functions
  - Log errors with full context
  - Return user-friendly Indonesian error messages
  - _Requirements: 6.5, 6.6_

- [x] 6.1 Add error handling to all tool functions
  - Wrap each tool execution in try-catch
  - Log error with user_id, tool_name, parameters, error message
  - Return "Maaf, terjadi kesalahan..." message
  - _Requirements: 6.5, 6.6_

- [x] 6.2 Add parameter validation to tool functions
  - Validate required parameters present
  - Validate parameter types match definitions
  - Return descriptive error messages for invalid inputs
  - _Requirements: 6.1, 6.2_

- [ ]* 6.3 Write unit tests for error handling
  - Test missing parameters
  - Test invalid parameter types
  - Test product not found scenarios
  - Test insufficient stock scenarios
  - Verify error messages in Indonesian

- [x] 7. Improve system prompt with knowledge context
  - Verify buildSystemPrompt() includes product list
  - Ensure test endpoint calls buildSystemPrompt()
  - Add tool usage instructions
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 7.1 Verify system prompt includes product context
  - Review AiAgent.buildSystemPrompt() implementation
  - Ensure top 20 products included with IDs and prices
  - Ensure business info included
  - No code changes needed (already correct)
  - _Requirements: 2.1, 2.2_

- [x] 7.2 Ensure test endpoint uses buildSystemPrompt()
  - Verify test() method calls aiAgent.buildSystemPrompt(userId)
  - Ensure system prompt passed to LLM
  - _Requirements: 2.1_

- [ ]* 7.3 Write property test for system prompt context
  - **Property 11: System Prompt Knowledge Context**
  - **Validates: Requirements 2.1**
  - Generate random products for user
  - Build system prompt
  - Verify prompt includes product IDs, names, prices
  - Verify prompt includes business info

- [x] 8. Final checkpoint and integration testing
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8.1 Test complete order flow via test endpoint
  - Test: "Cari menu nasi goreng"
  - Test: "Pesan 2 nasi goreng"
  - Test: "Lihat keranjang"
  - Test: "Konfirmasi pesanan"
  - Verify order created in database
  - Verify conversation_history formatted correctly
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

- [x] 8.2 Test QRIS flow via test endpoint
  - Create order first
  - Test: "Buat QRIS 50000"
  - Verify QRIS transaction created
  - Verify linked to order
  - Test: "Cek status"
  - Verify status check works
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

- [ ]* 8.3 Write integration test for multi-user isolation
  - Create two test users with products
  - Execute tool calls for each user
  - Verify no cross-user data access
  - Verify each user only sees their own data

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- Use existing OrderService and QrisService for order/QRIS operations
- All error messages should be in Indonesian
- All database queries must filter by user_id for data isolation
