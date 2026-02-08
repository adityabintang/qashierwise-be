# Requirements Document: AI Agent Order & Conversation Fixes

## Introduction

This specification addresses critical bugs in the AI Agent feature where:
1. Tool calling does not properly retrieve knowledge/data according to function requirements
2. Order creation and QRIS generation fail in test endpoint
3. Conversation messages are stored incorrectly (all messages in one row instead of separate rows)

## Glossary

- **AI_Agent**: The automated assistant that handles WhatsApp conversations
- **Conversation**: A chat session between a user and the AI Agent
- **Message**: A single communication bubble from either user or assistant
- **Order**: A purchase request created through the AI Agent
- **QRIS**: Quick Response Code Indonesian Standard for payments
- **Test_Endpoint**: The `/api/ai-agent/test` endpoint used for testing AI Agent functionality
- **Tool_Call**: A function invocation by the LLM to retrieve data or perform actions
- **Knowledge_Base**: The data sources (products, orders, etc.) that tools should access
- **System_Prompt**: Instructions given to the LLM that define its behavior and available knowledge
- **System**: The AI Agent system including all backend services and database operations

## Requirements

### Requirement 1: Fix Tool Calling Knowledge Retrieval

**User Story:** As a developer, I want tool calls to always retrieve the correct knowledge/data according to their function definition, so that the AI Agent provides accurate and relevant information to users.

#### Acceptance Criteria

1. WHEN search_products tool is called, THE System SHALL query the Products table filtered by the authenticated user_id
2. WHEN get_all_products tool is called, THE System SHALL return all active products belonging to the authenticated user
3. WHEN add_to_cart tool is called, THE System SHALL validate the product exists and belongs to the authenticated user before adding to cart
4. WHEN get_cart_summary tool is called, THE System SHALL retrieve cart data from the conversation's order_context
5. WHEN confirm_order tool is called, THE System SHALL validate all products in cart still exist and have sufficient stock
6. WHEN generate_qris tool is called, THE System SHALL retrieve the SubMerchant configuration associated with the user's AI Agent
7. WHEN check_payment_status tool is called, THE System SHALL retrieve the QrisTransaction from the conversation's current_qris_transaction_id
8. FOR ALL tool calls, THE System SHALL ensure data isolation by filtering all queries by user_id or related user ownership

### Requirement 2: Fix System Prompt Knowledge Context

**User Story:** As a developer, I want the system prompt to include relevant knowledge context, so that the LLM understands what data is available and how to use tools effectively.

#### Acceptance Criteria

1. WHEN building system prompt, THE System SHALL include a summary of available products with their IDs and names
2. WHEN building system prompt, THE System SHALL include business information from the AI Agent configuration
3. WHEN building system prompt, THE System SHALL include clear instructions on when and how to use each tool
4. WHEN building system prompt, THE System SHALL include examples of proper tool usage
5. WHEN QRIS is enabled, THE System SHALL include QRIS-specific instructions in the system prompt

### Requirement 3: Fix Conversation Message Storage

**User Story:** As a developer, I want each user and assistant message to be stored as a separate entry in the conversation history, so that the chat interface displays correctly with individual message bubbles.

#### Acceptance Criteria

1. WHEN a user sends a message, THE System SHALL store it as a single message entry with role "user"
2. WHEN the assistant responds, THE System SHALL store it as a single message entry with role "assistant"
3. WHEN displaying conversation history, THE System SHALL return an array where each element represents one message bubble
4. WHEN the test endpoint processes a message, THE System SHALL add both user and assistant messages separately to the conversation
5. WHEN messages are added to conversation, THE System SHALL maintain chronological order with timestamps

### Requirement 4: Fix Order Creation in Test Endpoint

**User Story:** As a developer testing the AI Agent, I want the test endpoint to successfully create orders when the AI Agent calls order-related tools, so that I can verify the complete order flow works correctly.

#### Acceptance Criteria

1. WHEN the test endpoint receives add_to_cart tool call, THE System SHALL add products to the conversation's cart context
2. WHEN the test endpoint receives confirm_order tool call, THE System SHALL create an Order record in the database
3. WHEN creating an order through test endpoint, THE System SHALL link the order to the correct store_id from AI Agent configuration
4. WHEN order items are added, THE System SHALL create OrderItem records for each product in the cart
5. WHEN an order is created, THE System SHALL store the order_id in the conversation's current_order_id field
6. WHEN order creation succeeds, THE System SHALL clear the cart from order_context
7. IF order creation fails, THEN THE System SHALL return a descriptive error message to the user

### Requirement 5: Fix QRIS Generation in Test Endpoint

**User Story:** As a developer testing the AI Agent, I want the test endpoint to successfully generate QRIS payment codes, so that I can verify the payment integration works correctly.

#### Acceptance Criteria

1. WHEN the test endpoint receives a generate_qris tool call, THE System SHALL validate that QRIS is enabled for the AI Agent
2. WHEN generating QRIS, THE System SHALL validate that a SubMerchant is configured for the user
3. WHEN generating QRIS, THE System SHALL create a QrisTransaction record with the specified amount
4. WHEN QRIS is generated, THE System SHALL store the transaction_id in the conversation's current_qris_transaction_id field
5. WHEN an order exists in conversation context, THE System SHALL link the QrisTransaction to that order
6. WHEN QRIS generation succeeds, THE System SHALL return the QR code URL and transaction details
7. IF QRIS generation fails, THEN THE System SHALL return a descriptive error message explaining the failure reason

### Requirement 6: Add Complete Tool Implementations

**User Story:** As a developer, I want all tool functions to be fully implemented with proper error handling and data validation, so that the AI Agent can reliably execute all advertised capabilities.

#### Acceptance Criteria

1. WHEN any tool is called, THE System SHALL validate all required parameters are present
2. WHEN any tool is called, THE System SHALL validate parameter types match the tool definition
3. WHEN a tool accesses database records, THE System SHALL verify the records belong to the authenticated user
4. WHEN a tool modifies data, THE System SHALL wrap the operation in a database transaction
5. IF any tool call fails, THEN THE System SHALL log the error with full context (user_id, tool_name, parameters, error message)
6. IF any tool call fails, THEN THE System SHALL return a user-friendly error message in Indonesian
7. WHEN a tool call succeeds, THE System SHALL return a formatted response message in Indonesian

### Requirement 7: Improve Test Endpoint Response Format

**User Story:** As a developer, I want the test endpoint to return complete conversation history in the correct format, so that I can debug and verify the AI Agent's behavior.

#### Acceptance Criteria

1. WHEN the test endpoint completes, THE System SHALL return the full conversation history as an array of message objects
2. WHEN returning conversation history, THE System SHALL include role, content, and timestamp fields for each message
3. IF an error occurs, THEN THE System SHALL return error details in a consistent JSON format with success: false
4. WHEN tool calls are executed, THE System SHALL include tool execution results in the assistant's response message
5. WHEN the response is returned, THE System SHALL include both the user's message and the AI's response as separate entries in conversation_history
