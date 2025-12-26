# Design Document: AI Agent Order & Conversation Fixes

## Overview

This design addresses three critical bugs in the AI Agent system:

1. **Tool Calling Knowledge Retrieval**: Tools don't properly filter data by user_id, causing data leakage and incorrect results
2. **Order Creation in Test Endpoint**: The test endpoint doesn't implement order creation tools, making testing impossible
3. **Conversation Message Storage**: Messages are stored incorrectly (all in one row instead of separate entries), breaking the chat UI

The fixes will ensure proper data isolation, complete tool implementations, and correct message storage format.

## Architecture

### Current Architecture

```
WhatsApp Message → AiAgentController → AiAgentService → LLM API
                                           ↓
                                      Tool Execution
                                           ↓
                                    Database Operations
```

### Issues in Current Architecture

1. **Data Isolation**: Tool functions don't consistently filter by user_id
2. **Test Endpoint**: Missing tool implementations for order/QRIS operations
3. **Message Storage**: `addMessage()` appends to array but stores all messages in single conversation row

### Fixed Architecture

The architecture remains the same, but with:
- Consistent user_id filtering in all tool functions
- Complete tool implementations in test endpoint
- Proper message storage (one row per message bubble)

## Components and Interfaces

### 1. AiAgentService

**Responsibilities**:
- Process incoming WhatsApp messages
- Call LLM with system prompt and conversation history
- Execute tool calls with proper data isolation
- Manage conversation state

**Key Methods to Fix**:

```php
// Fix: Add user_id filtering
protected function searchProducts(int $userId, string $query): string
{
    // CURRENT: No user_id filter
    // FIX: Add where('user_id', $userId)
}

protected function getProductDetails(int $userId, int $productId): string
{
    // CURRENT: No user_id filter
    // FIX: Add where('user_id', $userId)
}

protected function addToCart(
    AiAgentConversation $conversation,
    int $userId,
    int $productId,
    int $quantity
): string {
    // CURRENT: No user_id validation
    // FIX: Validate product belongs to user before adding
}
```

### 2. AiAgentController (Test Endpoint)

**Responsibilities**:
- Provide test interface for AI Agent
- Execute tool calls in test mode
- Return formatted conversation history

**Methods to Implement**:

```php
// NEW: Implement order creation in test endpoint
protected function executeTestToolCall(
    string $functionName,
    array $arguments,
    int $userId,
    AiAgentConversation $conversation,
    AiAgent $aiAgent
): string {
    // Add cases for:
    // - add_to_cart
    // - get_cart_summary
    // - confirm_order
    // - get_product_details
}

// NEW: Create order from cart
protected function createTestOrder(
    AiAgentConversation $conversation,
    AiAgent $aiAgent,
    int $userId
): string {
    // Use OrderService to create Order record
    // Use OrderService to add OrderItem records
    // Store order_id in conversation
    // Clear cart
}
```

### 3. AiAgentConversation Model

**Current Issue**: `addMessage()` appends to messages array, storing all messages in one row

**Fix**: Change storage strategy to store each message as separate entry

**Options**:
1. Create new `AiAgentMessage` model (one row per message)
2. Keep array but ensure proper structure for UI rendering

**Chosen Approach**: Keep array structure but fix how messages are added and retrieved

```php
// CURRENT: Stores all messages in one conversation row
public function addMessage(string $role, string $content): void
{
    $messages = $this->messages ?? [];
    $messages[] = [
        'role' => $role,
        'content' => $content,
        'timestamp' => now()->toIso8601String(),
    ];
    $this->messages = $messages;
    $this->save();
}

// FIX: Ensure each message is properly structured
// The array structure is actually correct - the issue is in how
// the test endpoint returns conversation_history
```

### 4. System Prompt Builder

**Current Issue**: System prompt doesn't include enough context about available products

**Fix**: `buildSystemPrompt()` already includes top 20 products - ensure it's being called correctly

```php
// In AiAgent model
public function buildSystemPrompt(int $userId): string
{
    // Already includes:
    // - Business info
    // - Top 20 products with IDs
    // - Tool usage instructions
    
    // FIX: Ensure this is called in test endpoint
}
```

## Data Models

### AiAgentConversation

```php
Schema::table('ai_agent_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ai_agent_id');
    $table->foreignId('whatsapp_contact_id');
    $table->json('messages');  // Array of message objects
    $table->json('order_context');  // Cart, pending orders
    $table->foreignId('current_order_id')->nullable();
    $table->foreignId('current_qris_transaction_id')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();
});
```

**Message Structure**:
```json
{
  "messages": [
    {
      "role": "user",
      "content": "Saya mau pesan nasi goreng",
      "timestamp": "2025-12-25T10:30:00Z"
    },
    {
      "role": "assistant",
      "content": "Baik, saya carikan menu nasi goreng...",
      "timestamp": "2025-12-25T10:30:05Z"
    }
  ]
}
```

### Order Context Structure

```json
{
  "order_context": {
    "cart": [
      {
        "product_id": 123,
        "product_name": "Nasi Goreng",
        "price": 25000,
        "quantity": 2
      }
    ],
    "pending_order": {
      "items": [...],
      "created_at": "2025-12-25T10:35:00Z"
    }
  }
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Data Isolation in Tool Calls

*For any* tool call execution (search_products, get_product_details, add_to_cart, etc.), all database queries SHALL filter by the authenticated user_id, ensuring no user can access another user's data.

**Validates: Requirements 1.1, 1.2, 1.3, 1.8**

### Property 2: Product Validation Before Cart Addition

*For any* add_to_cart operation, the product SHALL be validated to exist, be active, belong to the authenticated user, and have sufficient stock before being added to the cart.

**Validates: Requirements 1.3**

### Property 3: Cart Data Persistence

*For any* cart operation (add, update, retrieve), the cart data SHALL be stored in and retrieved from the conversation's order_context field, maintaining consistency across operations.

**Validates: Requirements 1.4**

### Property 4: Order Creation Completeness

*For any* successful order creation, the system SHALL create an Order record, create OrderItem records for all cart items, store the order_id in conversation context, and clear the cart.

**Validates: Requirements 4.2, 4.4, 4.5, 4.6**

### Property 5: QRIS Transaction Linking

*For any* QRIS generation when an order exists in conversation context, the QrisTransaction SHALL be linked to that order via the linked_order_id field.

**Validates: Requirements 5.5**

### Property 6: Message Storage Format

*For any* message added to a conversation, it SHALL be stored as a separate object in the messages array with role, content, and timestamp fields.

**Validates: Requirements 3.1, 3.2, 3.5**

### Property 7: Conversation History Chronology

*For any* conversation, when messages are retrieved, they SHALL be returned in chronological order based on their timestamps.

**Validates: Requirements 3.5**

### Property 8: Test Endpoint Response Format

*For any* test endpoint execution, the response SHALL include both the user's message and the AI's response as separate entries in the conversation_history array.

**Validates: Requirements 7.1, 7.5**

### Property 9: Tool Parameter Validation

*For any* tool call, all required parameters SHALL be validated for presence and type before execution, returning user-friendly error messages in Indonesian for invalid inputs.

**Validates: Requirements 6.1, 6.2, 6.6**

### Property 10: SubMerchant Retrieval for QRIS

*For any* generate_qris tool call, the system SHALL retrieve the SubMerchant configuration associated with the user's AI Agent before generating the QRIS code.

**Validates: Requirements 1.6**

### Property 11: System Prompt Knowledge Context

*For any* system prompt generation, when order feature is enabled, the prompt SHALL include a summary of available products with their IDs, names, and prices.

**Validates: Requirements 2.1**

## Error Handling

### Tool Call Errors

**Strategy**: Catch all exceptions in tool execution, log with full context, return user-friendly Indonesian messages

```php
try {
    // Tool execution
} catch (\Exception $e) {
    Log::error("Tool call error: {$functionName}", [
        'user_id' => $userId,
        'tool_name' => $functionName,
        'arguments' => $arguments,
        'error' => $e->getMessage(),
    ]);
    
    return 'Maaf, terjadi kesalahan saat memproses permintaan Anda.';
}
```

### Order Creation Errors

**Scenarios**:
1. Product not found → "Maaf, produk tidak ditemukan"
2. Insufficient stock → "Maaf, stok tidak mencukupi. Stok tersedia: X"
3. Store not configured → "Maaf, toko default belum dikonfigurasi"
4. Database error → "Maaf, terjadi kesalahan saat membuat pesanan"

### QRIS Generation Errors

**Scenarios**:
1. QRIS not enabled → "Maaf, pembayaran QRIS belum tersedia"
2. SubMerchant not configured → "Maaf, pembayaran QRIS belum tersedia. Sub-merchant belum dikonfigurasi"
3. Invalid amount → "Maaf, jumlah pembayaran tidak valid"
4. Provider error → "Maaf, terjadi kesalahan saat membuat kode pembayaran"

### Data Validation Errors

**Strategy**: Validate early, fail fast with descriptive messages

```php
// Product ownership validation
if (!$product || $product->user_id !== $userId) {
    return "Maaf, produk tidak ditemukan.";
}

// Stock validation
if ($product->stock_quantity < $quantity) {
    return "Maaf, stok tidak mencukupi. Stok tersedia: {$product->stock_quantity}";
}
```

## Testing Strategy

### Unit Tests

**Focus**: Individual tool functions, data validation, error handling

**Test Cases**:
1. `searchProducts()` filters by user_id
2. `getProductDetails()` validates product ownership
3. `addToCart()` validates stock and ownership
4. `getCartSummary()` retrieves from order_context
5. `confirmOrder()` validates cart items still exist
6. `generateQris()` retrieves correct SubMerchant
7. `checkPaymentStatus()` retrieves correct QrisTransaction
8. `addMessage()` stores message with correct structure
9. Test endpoint returns properly formatted conversation_history

### Property-Based Tests

**Configuration**: Minimum 100 iterations per test using PHPUnit with property testing library

**Property Tests**:

1. **Data Isolation Property**
   - Generate random user_ids and product data
   - Call tool functions with different user_ids
   - Verify no cross-user data access

2. **Cart Consistency Property**
   - Generate random cart operations
   - Verify cart state matches order_context after each operation

3. **Message Storage Property**
   - Generate random message sequences
   - Verify each message is stored as separate array entry
   - Verify chronological order maintained

4. **Order Creation Property**
   - Generate random cart contents
   - Create order
   - Verify Order and OrderItem records created
   - Verify cart cleared

5. **QRIS Linking Property**
   - Generate random order and QRIS data
   - Verify QrisTransaction linked to order when order exists

### Integration Tests

**Focus**: End-to-end flows through test endpoint

**Test Scenarios**:
1. Complete order flow: search → add to cart → confirm → create order
2. QRIS flow: create order → generate QRIS → check status
3. Multi-user isolation: verify users can't access each other's data
4. Conversation history: verify messages stored and retrieved correctly

### Manual Testing via Test Endpoint

**Test Cases**:
1. Search products: "Cari menu nasi goreng"
2. Add to cart: "Pesan 2 nasi goreng"
3. View cart: "Lihat keranjang"
4. Confirm order: "Konfirmasi pesanan"
5. Generate QRIS: "Buat QRIS 50000"
6. Check payment: "Cek status"

**Verification**:
- Each message appears as separate entry in conversation_history
- Order created in database with correct items
- QRIS transaction linked to order
- No data leakage between users

## Implementation Notes

### Critical Fixes Required

1. **AiAgentService.php**:
   - Add `where('user_id', $userId)` to all product queries
   - Validate product ownership before cart operations
   - Ensure SubMerchant retrieval uses correct user_id
   - Use existing QrisService for QRIS generation (already implemented correctly)

2. **AiAgentController.php** (test endpoint):
   - Implement `add_to_cart` tool
   - Implement `get_cart_summary` tool
   - Implement `confirm_order` tool with order creation using OrderService
   - Implement `get_product_details` tool
   - Fix conversation_history response format
   - Use QrisService for QRIS generation (already available via dependency injection)

3. **Message Storage**:
   - Verify `addMessage()` creates proper structure
   - Ensure test endpoint returns messages array correctly
   - No changes needed to storage strategy (array is correct)

### Service Usage

**Order Creation**: Use existing `OrderService` methods:
```php
// Create order
$order = $this->orderService->create([
    'store_id' => $aiAgent->default_store_id,
    'table_id' => null,
    'pos_user_id' => null,
    'source' => Order::SOURCE_WHATSAPP_AI,
    'customer_name' => $contact->name ?? 'Test Customer',
    'customer_phone' => $contact->wa_id ?? 'test',
]);

// Add items
foreach ($cart as $item) {
    $product = Product::find($item['product_id']);
    if ($product) {
        $this->orderService->addItem($order, $product, $item['quantity']);
    }
}
```

**QRIS Generation**: Use existing `QrisService` methods:
```php
// Get SubMerchant
$subMerchant = $aiAgent->getSubMerchant();

// Generate QRIS
$qrisTransaction = $this->qrisService->generateQris($subMerchant, $amount, [
    'description' => $description ?? 'Pembayaran via AI Agent',
]);

// Link to order if exists
if ($order) {
    $qrisTransaction->linked_order_id = $order->id;
    $qrisTransaction->save();
}
```

### Database Transactions

Wrap order creation in transaction:

```php
DB::transaction(function () use ($conversation, $aiAgent, $userId) {
    // Create order using OrderService
    $order = $this->orderService->create([...]);
    
    // Add order items using OrderService
    foreach ($cart as $item) {
        $product = Product::find($item['product_id']);
        if ($product) {
            $this->orderService->addItem($order, $product, $item['quantity']);
        }
    }
    
    // Update conversation
    $conversation->setCurrentOrder($order->id);
    $conversation->clearCart();
});
```

### Logging

Add comprehensive logging for debugging:

```php
Log::info('Tool call executed', [
    'user_id' => $userId,
    'tool_name' => $functionName,
    'arguments' => $arguments,
    'result_length' => strlen($result),
]);
```

### Performance Considerations

- Product queries already limited to 10-20 results
- Conversation messages limited to last 10 (sliding window)
- No N+1 queries in order creation
- Eager load relationships where needed

## Security Considerations

### Data Isolation

**Critical**: Every database query MUST filter by user_id or verify ownership

```php
// WRONG
$product = Product::find($productId);

// CORRECT
$product = Product::where('id', $productId)
    ->where('user_id', $userId)
    ->first();
```

### Input Validation

- Validate all tool parameters before execution
- Sanitize user input in search queries
- Validate numeric inputs (product_id, quantity, amount)

### Authorization

- Verify user owns AI Agent before processing
- Verify user owns products before adding to cart
- Verify user owns SubMerchant before generating QRIS

## Migration Path

### Phase 1: Fix Data Isolation (Critical)
1. Update all tool functions in AiAgentService
2. Add user_id filtering to all queries
3. Add ownership validation

### Phase 2: Implement Test Endpoint Tools
1. Add missing tool implementations
2. Implement order creation logic
3. Fix conversation_history response format

### Phase 3: Verify Message Storage
1. Test message storage format
2. Verify UI displays correctly
3. No code changes needed (already correct)

### Phase 4: Testing
1. Write unit tests for all tool functions
2. Write property tests for data isolation
3. Test via test endpoint
4. Manual QA with multiple users

## Deployment Considerations

- No database migrations required
- No breaking changes to API
- Backward compatible with existing conversations
- Can deploy incrementally (fix by fix)
- Monitor logs for tool call errors after deployment
