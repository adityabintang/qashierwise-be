# Fix: AI Agent Looping Issue & Slow Response

## Masalah 1: Looping
Bot WhatsApp AI Agent terus menampilkan daftar produk yang sama berulang kali (looping) ketika user memesan produk. Contoh:
- User: "beli dimsum 1 sama teh jumbo 2"
- Bot: Menampilkan daftar produk
- Bot: Menampilkan daftar produk lagi
- Bot: Menampilkan daftar produk lagi (loop terus)

### Penyebab
1. **LLM terus memanggil search berulang kali** alih-alih langsung `add_to_cart` setelah mendapat hasil search
2. **Hasil search tidak cukup jelas** memberikan instruksi ke LLM untuk langsung add_to_cart
3. **Tidak ada mekanisme untuk mencegah loop** - sistem terus memanggil LLM dengan hasil search yang sama

### Solusi Looping

#### 1. Perbaikan Format Hasil Search
**File**: `app/Services/AiAgentService.php`

Mengubah format hasil search agar lebih tegas:

**Sebelum:**
```
Berikut produk yang saya temukan:

🔹 Dimsum Keju [ID:1]
   Harga: Rp 40.000
   Stok: 10

**INSTRUKSI UNTUK AI**: Gunakan ID di atas untuk add_to_cart...
```

**Sesudah:**
```
HASIL PENCARIAN PRODUK:

- Dimsum Keju [ID:1] - Rp 40.000 - Stok: 10

**INSTRUKSI WAJIB**: Sekarang LANGSUNG panggil add_to_cart dengan ID di atas. JANGAN search lagi! JANGAN tampilkan daftar produk ke user lagi!
```

#### 2. Mekanisme Pencegahan Loop
**File**: `app/Services/AiAgentService.php` - Method `handleToolCalls()`

Menambahkan logika untuk mendeteksi dan mencegah loop:

```php
// Check if we've already done a follow-up call in this conversation turn
$lastMessages = array_slice($conversation->messages ?? [], -5);
$recentSearchCount = 0;
foreach ($lastMessages as $msg) {
    if (isset($msg['type']) && $msg['type'] === 'ai' && 
        (stripos($msg['content'] ?? '', 'HASIL PENCARIAN') !== false || 
         stripos($msg['content'] ?? '', 'produk yang saya temukan') !== false)) {
        $recentSearchCount++;
    }
}

// If we've already shown search results recently, don't loop - just show to user
if ($recentSearchCount >= 2) {
    Log::warning('Preventing search loop - already searched multiple times recently');
    
    // Send a helpful message to user
    $responseMessage = "Maaf, saya mengalami kesulitan memproses pesanan Anda. Silakan coba lagi dengan format:\n\n";
    $responseMessage .= "Contoh: 'pesan dimsum 2 porsi'\n";
    $responseMessage .= "Atau: 'pesan dimsum 1 dan teh jumbo 2'";
    
    $conversation->addMessage('ai', $responseMessage);
    $this->sendReply($account, $contact->wa_id, $responseMessage);
    return;
}
```

---

## Masalah 2: Response Lambat
Response dari AI Agent menjadi sangat lambat setelah implementasi conversation summarization.

### Penyebab
Fitur **conversation summarization** menambahkan 2 panggilan LLM ekstra:

1. **Intent change detection**: Memanggil LLM untuk cek perubahan intent setiap pesan
2. **Summarization**: Memanggil LLM untuk membuat summary jika threshold tercapai

Jadi setiap pesan bisa memanggil LLM **hingga 3x**:
- 1x untuk cek intent change (TIDAK PERLU!)
- 1x untuk summarize (jika threshold tercapai)
- 1x untuk response utama

### Solusi Response Lambat

#### 1. Disable Intent Change Detection
**File**: `app/Services/AiAgentService.php` - Method `processMessage()`

Intent change detection di-comment karena menambah 1 panggilan LLM ekstra yang tidak perlu:

```php
// DISABLED: Intent change detection (too slow - adds extra LLM call)
// Intent changes will be detected naturally during regular summarization
```

#### 2. Disable Summarization (Recommended untuk Production)
**File**: `.env`

Tambahkan konfigurasi untuk menonaktifkan summarization:

```env
# Conversation Summarization Configuration
# Set to false to disable summarization and improve response speed
CONVERSATION_SUMMARIZATION_ENABLED=false
```

**Alternatif**: Jika ingin tetap menggunakan summarization, naikkan threshold agar tidak terlalu sering:

```env
CONVERSATION_SUMMARIZATION_ENABLED=true
CONVERSATION_MESSAGE_COUNT_THRESHOLD=10
CONVERSATION_TOKEN_THRESHOLD=1500
```

---

## Perubahan File
1. `app/Services/AiAgentService.php`:
   - Method `searchProducts()` - Format hasil search lebih tegas
   - Method `searchMultipleProducts()` - Format hasil search lebih tegas
   - Method `handleToolCalls()` - Tambah mekanisme pencegahan loop
   - Method `processMessage()` - Disable intent change detection
   
2. `.env`:
   - Tambah `CONVERSATION_SUMMARIZATION_ENABLED=false` untuk disable summarization

---

## Testing
Setelah fix ini, test dengan skenario:

### Test Looping Fix:
1. User: "pesan dimsum 2"
   - Expected: Bot langsung menambahkan ke cart tanpa loop
   
2. User: "beli dimsum 1 sama teh jumbo 2"
   - Expected: Bot langsung menambahkan kedua produk ke cart tanpa loop
   
3. User: "mau nasi goreng"
   - Expected: Bot langsung menambahkan ke cart tanpa loop

### Test Response Speed:
1. Kirim pesan sederhana: "halo"
   - Expected: Response dalam 1-2 detik
   
2. Kirim pesan order: "pesan dimsum 2"
   - Expected: Response dalam 2-3 detik (termasuk search + add to cart)

---

## Rekomendasi
- **Production**: Set `CONVERSATION_SUMMARIZATION_ENABLED=false` untuk response tercepat
- **Development**: Bisa enable summarization untuk testing, tapi naikkan threshold
- **Future**: Jika ingin enable summarization di production, pertimbangkan:
  - Jalankan summarization secara async (queue job)
  - Atau trigger summarization hanya saat idle (bukan saat user mengirim pesan)
