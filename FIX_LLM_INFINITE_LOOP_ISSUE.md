# Fix: LLM Infinite Loop Issue - Tidak Merespons Saat Pesan Makanan

## Masalah

Ketika user memesan makanan dengan format seperti "craffe 2 sama red velvet 2", LLM tidak memberikan respons dan terjebak dalam infinite loop.

### Gejala:
- User mengirim pesan pemesanan makanan
- LLM tidak merespons sama sekali
- Log menunjukkan tool calls berulang-ulang (Craffle → Red Velvet → Craffle → Red Velvet...)
- Loop berlangsung sampai timeout (2+ menit)

### Root Cause:

1. **Loop Prevention Tidak Efektif**: Kode mencoba mencegah loop dengan menghitung pesan AI yang mengandung "HASIL PENCARIAN", tapi LLM tidak pernah mengirim pesan tersebut karena terus memanggil tool calls.

2. **Recursive Tool Calls Tanpa Batas**: Ketika LLM mengembalikan tool_calls lagi, kode memanggil `handleToolCalls()` secara rekursif tanpa ada batasan iterasi.

3. **LLM Behavior**: LLM terus mencari produk secara bergantian tanpa pernah memanggil `add_to_cart` untuk menyelesaikan pesanan.

### Log Evidence:

```
[2026-01-11 03:59:00] Handling tool calls: get_all_products (search: "Craffle")
[2026-01-11 03:59:02] Handling tool calls: get_all_products (search: "Red Velvet")
[2026-01-11 03:59:06] Handling tool calls: get_all_products (search: "Craffle")
[2026-01-11 03:59:08] Handling tool calls: get_all_products (search: "Red Velvet")
... (loop continues for 2+ minutes)
```

## Solusi

Menambahkan **iteration counter** pada method `handleToolCalls()` untuk membatasi maksimal 3 iterasi tool calls dalam satu turn conversation.

### Perubahan Kode:

**File**: `app/Services/AiAgentService.php`

#### 1. Tambah Parameter Iteration pada Method Signature

```php
protected function handleToolCalls(
    array $toolCalls,
    AiAgentConversation $conversation,
    WhatsAppAccount $account,
    WhatsAppContact $contact,
    AiAgent $ai