# Fix: Multiple Products Not Added to Cart Bug - FINAL SOLUTION

## Problem
Ketika user memesan lebih dari satu produk sekaligus (contoh: "es buah selasih 2 dan es campurnya 2"), hanya satu produk yang masuk ke keranjang. Produk kedua tidak ditambahkan.

## Root Cause
**LLM tidak mengembalikan multiple tool calls dalam satu response**. Kebanyakan LLM API (termasuk BytePlus ARK) hanya mengembalikan satu tool call per response, bukan multiple tool calls sekaligus.

## Solution: Batch Add to Cart ✅

Mengubah function `add_to_cart` untuk menerima **array of products** sehingga LLM hanya perlu memanggil function sekali untuk menambahkan multiple products.

### Changes

#### 1. Update Tool Definition (`app/Services/AiAgentService.php`)
```php
'add_to_cart' => [
    'description' => 'Tambahkan satu atau lebih produk ke keranjang belanja. Untuk multiple produk, gunakan array products.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'products' => [
                'type' => 'array',
                'description' => 'Array of products to add',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer'],
                        'quantity' => ['type' => 'integer'],
                    ],
                    'required' => ['product_id', 'quantity'],
                ],
            ],
        ],
        'required' => ['products'],
    ],
]
```

#### 2. Add `addMultipleToCart()` Method (`app/Services/AiAgentService.php`)
```php
protected function addMultipleToCart(
    AiAgentConversation $conversation,
    int $userId,
    array $products
): string {
    // Validate and add each product to cart
    // Return summary message with all added products
}
```

#### 3. Update `executeToolCall()` (`app/Services/AiAgentService.php`)
```php
case 'add_to_cart':
    // Support both array of products and single product
    if (isset($arguments['products']) && is_array($arguments['products'])) {
        // Batch add to cart
        return $this->addMultipleToCart($conversation, $userId, $arguments['products']);
    } elseif (isset($arguments['product_id']) && isset($arguments['quantity'])) {
        // Single product (backward compatibility)
        return $this->addToCart(...);
    }
```

#### 4. Update System Prompt (`app/Models/AiAgent.php`)
```php
2. **Saat menambahkan produk ke keranjang:**
   - Gunakan function 'add_to_cart' dengan parameter 'products' (array)
   - **PENTING: Untuk MULTIPLE produk, masukkan SEMUA produk dalam SATU array**
   - Format: products: [{product_id: X, quantity: Y}, {product_id: Z, quantity: W}]

CONTOH BENAR - MULTIPLE PRODUK:
User: 'Pesan es campur 1 dan es buah selasih 3'
AI: [panggil add_to_cart(products=[{product_id:1, quantity:1}, {product_id:2, quantity:3}])]
AI Response: '✅ Berhasil menambahkan ke keranjang!

📦 Es Campur x1
📦 Es Buah Selasih x3

Ketik "lihat keranjang" untuk melihat ringkasan pesanan.'
```

## How It Works

### Before (Broken)
```
User: "es buah 2 dan es campur 2"
  ↓
LLM: add_to_cart(product_id=2, quantity=2)  ← Only 1 call
  ↓
Result: Only Es Buah added ❌
```

### After (Fixed)
```
User: "es buah 2 dan es campur 2"
  ↓
LLM: add_to_cart(products=[
    {product_id: 2, quantity: 2},
    {product_id: 1, quantity: 2}
])  ← Single call with array
  ↓
Result: Both products added ✅
```

## Features

### Batch Processing
- Add multiple products in one function call
- Validates each product individually
- Continues processing even if one product fails

### Error Handling
- Invalid product IDs are skipped with error message
- Out of stock products are reported
- Partial success is supported (some products added, some failed)

### Response Format
```
✅ Berhasil menambahkan ke keranjang!

📦 Es Campur x1
📦 Es Buah Selasih x3

Ketik 'lihat keranjang' untuk melihat ringkasan pesanan.
```

With errors:
```
✅ Berhasil menambahkan ke keranjang!

📦 Es Campur x1

⚠️ Beberapa produk tidak dapat ditambahkan:
Es Teler: stok tidak mencukupi (tersedia: 0)
```

## Backward Compatibility

The function still supports single product format for backward compatibility:
```php
// Old format (still works)
add_to_cart(product_id=1, quantity=2)

// New format (recommended)
add_to_cart(products=[{product_id:1, quantity:2}])
```

## Testing

### Test Cases

1. **Single Product**
   ```
   Input: "pesan es buah 2"
   Expected: products=[{product_id:2, quantity:2}]
   Result: 1 produk masuk ✅
   ```

2. **Two Products**
   ```
   Input: "es buah selasih 2 dan es campurnya 2"
   Expected: products=[{product_id:2, quantity:2}, {product_id:1, quantity:2}]
   Result: 2 produk masuk ✅
   ```

3. **Three Products**
   ```
   Input: "pesan es campur 1, es buah 2, dan es teler 3"
   Expected: products=[{product_id:1, quantity:1}, {product_id:2, quantity:2}, {product_id:3, quantity:3}]
   Result: 3 produk masuk ✅
   ```

### Check Logs
```bash
# Monitor tool calls
tail -f storage/logs/laravel.log | grep "add_to_cart"

# Expected output for "es buah 2 dan es campur 2":
[timestamp] Executing tool call {"function":"add_to_cart","arguments":"{\"products\":[{\"product_id\":2,\"quantity\":2},{\"product_id\":1,\"quantity\":2}]}"}
```

### Check Cart
```sql
SELECT 
    c.id,
    JSON_EXTRACT(c.order_context, '$.cart') as cart
FROM ai_agent_conversations c
WHERE c.whatsapp_contact_id = [CONTACT_ID]
ORDER BY c.updated_at DESC
LIMIT 1;
```

Expected cart content:
```json
[
  {"product_id": 2, "product_name": "Es Buah Selasih", "price": 10000, "quantity": 2},
  {"product_id": 1, "product_name": "Es Campur", "price": 20000, "quantity": 2}
]
```

## Files Changed
1. `app/Services/AiAgentService.php`
   - Updated `add_to_cart` tool definition to accept array
   - Added `addMultipleToCart()` method
   - Updated `executeToolCall()` to handle both formats
   - Added enhanced logging

2. `app/Models/AiAgent.php`
   - Updated system prompt with array format instructions
   - Added clear examples for single and multiple products

## Benefits

✅ **Solves the root cause** - No need for multiple tool calls
✅ **Better UX** - Single response for multiple products
✅ **More efficient** - One API call instead of multiple
✅ **Backward compatible** - Old format still works
✅ **Better error handling** - Partial success supported
✅ **Clearer instructions** - LLM knows exactly what to do

## Monitoring
```bash
# Real-time monitoring
tail -f storage/logs/laravel.log | grep -E "(add_to_cart|addMultipleToCart)"
```

Expected for successful multiple products order:
```
[timestamp] Executing tool call {"function":"add_to_cart","arguments":"{\"products\":[...]}"}
[timestamp] Added 2 products to cart successfully
```
