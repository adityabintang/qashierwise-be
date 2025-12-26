# Fix: Error Saat Membuat Pesanan di AI Agent

## Masalah

Ketika user mencoba membuat pesanan melalui AI Agent Test interface, muncul error:
```
Maaf, terjadi kesalahan saat membuat pesanan.
```

## Root Cause

Setelah investigasi mendalam, ditemukan bahwa error terjadi karena:

1. **Database Constraint Violation**: Kolom `pos_user_id` di tabel `orders` memiliki constraint NOT NULL
2. **WhatsApp AI Orders**: Order yang dibuat melalui WhatsApp AI Agent tidak memiliki POS user karena tidak melalui POS system
3. **OrderService**: Ketika `OrderService::create()` dipanggil dengan `pos_user_id => null`, database menolak insert karena constraint

Error SQL yang terjadi:
```
SQLSTATE[23502]: Not null violation: 7 ERROR: null value in column "pos_user_id" 
of relation "orders" violates not-null constraint
```

## Solusi

### 1. Database Migration

Membuat migration untuk mengubah kolom `pos_user_id` menjadi nullable:

**File**: `database/migrations/2025_12_25_173324_make_pos_user_id_nullable_in_orders_table.php`

```php
public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        // Make pos_user_id nullable to support orders from WhatsApp AI Agent
        $table->foreignId('pos_user_id')->nullable()->change();
    });
}
```

**Alasan**: Order dari WhatsApp AI Agent tidak memerlukan POS user karena tidak melalui POS system. Kolom ini hanya relevan untuk order yang dibuat melalui POS interface.

### 2. Improved Error Logging

Menambahkan logging yang lebih detail di `AiAgentController::confirmOrder()` untuk memudahkan debugging:

```php
Log::info('Creating order via test endpoint', [
    'user_id' => $userId,
    'store_id' => $aiAgent->default_store_id,
    'cart_items' => count($cart),
]);

Log::error('Order creation failed - unexpected error', [
    'user_id' => $userId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

### 3. Enhanced LLM Error Handling

Memperbaiki error handling di `AiAgentService::callLLM()` untuk memberikan informasi lebih detail saat LLM response tidak valid:

```php
if (! $choice) {
    Log::error('Invalid LLM response format - no choices', [
        'response_data' => $data,
    ]);
    throw new \Exception('Invalid LLM response format: no choices in response');
}

if (!isset($choice['message'])) {
    Log::error('Invalid LLM response format - no message', [
        'choice' => $choice,
    ]);
    throw new \Exception('Invalid LLM response format: no message in choice');
}
```

## Testing

### Test 1: Order Creation (Direct)
```bash
php test_order_creation.php
```

**Result**: ✅ PASSED
- Order created successfully
- Order items added correctly
- Totals calculated properly (subtotal, tax, total)

### Test 2: LLM Integration
```bash
php test_ai_agent_order.php
```

**Result**: ✅ PASSED
- LLM API responding correctly
- Tool calls working as expected
- System prompt includes product context

## Cara Menggunakan

1. **Jalankan Migration**:
   ```bash
   php artisan migrate
   ```

2. **Test AI Agent**:
   - Buka Test AI Agent interface
   - Ketik: "Tampilkan semua menu"
   - Ketik: "Pesan 1 Es Campur"
   - Ketik: "Lihat keranjang"
   - Ketik: "Konfirmasi pesanan"

3. **Verifikasi**:
   - Order harus berhasil dibuat
   - Order number ditampilkan
   - Total harga benar (termasuk pajak 11%)

## Files Modified

1. `database/migrations/2025_12_25_173324_make_pos_user_id_nullable_in_orders_table.php` - NEW
2. `app/Http/Controllers/AiAgentController.php` - Enhanced logging
3. `app/Services/AiAgentService.php` - Improved error handling

## Impact

- ✅ Order creation via WhatsApp AI Agent now works
- ✅ Better error messages for debugging
- ✅ No impact on existing POS orders
- ✅ Backward compatible with existing data

## Notes

- Kolom `pos_user_id` sekarang nullable, yang berarti order bisa dibuat tanpa POS user
- Order dari WhatsApp AI Agent akan memiliki `source = 'whatsapp_ai'` untuk membedakan dari POS orders
- Logging yang lebih baik memudahkan debugging issue di masa depan
