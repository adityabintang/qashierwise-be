# Fix: Multiple Products Order Issue

## Masalah
Ketika user memesan lebih dari satu produk sekaligus (contoh: "pesan es campur 1 dan es buah selasih 3"), hanya satu produk yang masuk ke keranjang, sedangkan produk lainnya tidak ditambahkan.

## Penyebab
1. **System prompt tidak memberikan instruksi eksplisit** kepada LLM untuk memanggil function `add_to_cart` multiple kali ketika user memesan beberapa produk sekaligus
2. LLM hanya memanggil `add_to_cart` sekali untuk satu produk pertama saja

## Solusi

### 1. Update System Prompt (app/Models/AiAgent.php)
Menambahkan instruksi eksplisit di system prompt:
- **PENTING: Jika user memesan LEBIH DARI SATU PRODUK, panggil 'add_to_cart' untuk SETIAP produk**
- Menambahkan contoh konkret untuk multiple products order

**Perubahan:**
```php
// Instruksi baru di system prompt
2. **Saat menambahkan produk ke keranjang:**
   - Gunakan function 'add_to_cart' dengan product_id dan quantity yang benar
   - **PENTING: Jika user memesan LEBIH DARI SATU PRODUK, panggil 'add_to_cart' untuk SETIAP produk**
   - Contoh: 'Es campur 1 dan es buah 3' → panggil add_to_cart 2 kali
```

### 2. Improve handleToolCalls (app/Services/AiAgentService.php)
Memperbaiki handling multiple `add_to_cart` calls:
- Mendeteksi ketika ada multiple `add_to_cart` calls
- Membuat summary message yang lebih ringkas untuk multiple products
- Tetap menampilkan detailed message untuk single product

**Perubahan:**
```php
protected function handleToolCalls(...): void
{
    $results = [];
    $addToCartResults = [];

    foreach ($toolCalls as $toolCall) {
        $result = $this->executeToolCall(...);
        
        // Track add_to_cart calls separately
        if ($functionName === 'add_to_cart' && !empty($result)) {
            $addToCartResults[] = $result;
        } else {
            $results[] = $result;
        }
    }

    // Handle add_to_cart results
    if (count($addToCartResults) > 1) {
        // Multiple products - create summary
        $summaryMessage = "✅ Berhasil menambahkan ke keranjang!\n\n";
        foreach ($addToCartResults as $cartResult) {
            // Extract and show each product
        }
        $results[] = $summaryMessage;
    } elseif (count($addToCartResults) === 1) {
        // Single product - use detailed message
        $results[] = $addToCartResults[0];
    }
    
    // Send combined result
    $this->sendReply(...);
}
```

## Hasil
Sekarang ketika user memesan beberapa produk sekaligus:
1. LLM akan memanggil `add_to_cart` untuk setiap produk
2. Semua produk akan masuk ke keranjang
3. User mendapat konfirmasi ringkas untuk semua produk yang ditambahkan

**Contoh Response:**
```
✅ Berhasil menambahkan ke keranjang!

📦 Es Campur x1
📦 Es Buah Selasih x3

Ketik 'lihat keranjang' untuk melihat ringkasan pesanan.
```

## Testing
Coba pesan dengan format:
- "pesan es campur 1 dan es buah selasih 3"
- "saya mau es campur 2, es buah 1, dan es teler 3"
- "order 1 es campur sama 2 es buah"

Semua produk seharusnya masuk ke keranjang.

## Files Changed
1. `app/Models/AiAgent.php` - Update system prompt dengan instruksi multiple products
2. `app/Services/AiAgentService.php` - Improve handleToolCalls untuk summary message
