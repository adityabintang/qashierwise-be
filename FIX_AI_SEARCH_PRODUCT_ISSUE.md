# Fix: AI Agent Tidak Menemukan Produk yang Ada di Menu

## Masalah
AI Agent tidak dapat menemukan produk "Dimsum Keju" padahal produk tersebut ada di menu dan ditampilkan di daftar produk.

### Contoh Error:
- Menu menampilkan: "Dimsum Keju - Rp 40.000 - Stok: 10"
- User mengetik: "pesan dimsum 2, teh jumbo 2"
- AI menjawab: "Maaf, tidak ada produk yang ditemukan dengan kata kunci 'dimsum keju'."

## Penyebab
1. **Pencarian terlalu strict**: Fungsi `searchProducts()` hanya melakukan exact phrase matching dengan LIKE query
2. **AI tidak memecah query dengan benar**: Ketika user mengetik "pesan dimsum 2", AI mungkin mencari dengan query yang terlalu spesifik atau tidak tepat
3. **Instruksi kurang jelas**: System prompt tidak memberikan panduan yang cukup jelas tentang cara menggunakan kata kunci pencarian
4. **AI tidak mengekstrak ID dengan benar**: AI mungkin tidak mengekstrak ID dari hasil search untuk digunakan di add_to_cart

## Solusi yang Diterapkan

### 1. Perbaikan Fungsi Search (`app/Services/AiAgentService.php`)

**Sebelum:**
```php
$products = Product::where('user_id', $userId)
    ->where('is_active', true)
    ->where(function ($q) use ($query) {
        $q->where('name', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%");
    })
    ->limit(10)
    ->get();
```

**Sesudah:**
```php
// Clean and normalize query for better matching
$cleanQuery = trim(strtolower($query));

// Split query into words for flexible matching
$keywords = explode(' ', $cleanQuery);

$products = Product::where('user_id', $userId)
    ->where('is_active', true)
    ->where(function ($q) use ($query, $keywords) {
        // Exact phrase match (highest priority)
        $q->where('name', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%");
        
        // Also match if ALL keywords are present (flexible word order)
        if (count($keywords) > 1) {
            $q->orWhere(function($subQ) use ($keywords) {
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 3) { // Only use keywords with 3+ chars
                        $subQ->where('name', 'like', "%{$keyword}%");
                    }
                }
            });
        }
    })
    ->limit(10)
    ->get();
```

**Keuntungan:**
- Pencarian lebih fleksibel dengan memecah query menjadi kata-kata kunci
- Mendukung pencarian dengan urutan kata yang berbeda
- Tetap memprioritaskan exact phrase match
- Filter kata kunci pendek (< 3 karakter) untuk menghindari false positive

### 2. Perbaikan Tool Definition (`app/Services/AiAgentService.php`)

**Perubahan:**
```php
'description' => 'WAJIB DIPANGGIL PERTAMA sebelum add_to_cart. Cari produk berdasarkan nama atau SKU untuk mendapatkan ID produk. Gunakan kata kunci PENDEK (contoh: "dimsum", "teh", "nasi"). Sistem akan otomatis mencocokkan dengan produk yang mengandung kata tersebut. Gunakan ID dari hasil search ini untuk add_to_cart.',
```

### 3. Perbaikan System Prompt (`app/Models/AiAgent.php`)

**Perubahan:**
1. Mengubah instruksi dari `search_products('dimsum keju')` menjadi `search_products('dimsum')`
2. Menambahkan tips untuk menggunakan kata kunci PENDEK dan UMUM
3. Menambahkan contoh yang lebih jelas tentang cara memproses multiple products
4. Menambahkan instruksi eksplisit untuk mengekstrak ID dari hasil search

**Instruksi Baru:**
```
**Langkah 2: User memesan produk**
- User: 'pesan dimsum keju 2'
- AI: WAJIB panggil search_products('dimsum') dulu untuk mendapatkan ID
- AI: Gunakan ID dari hasil search untuk add_to_cart
- TIPS: Gunakan kata kunci PENDEK untuk search (contoh: 'dimsum', 'teh', 'nasi')
- Sistem akan otomatis mencocokkan dengan produk yang mengandung kata tersebut

4. **PENTING: SELALU SEARCH DULU SEBELUM ADD TO CART**
   - Meskipun produk sudah ditampilkan sebelumnya
   - WAJIB panggil search_products untuk mendapatkan ID terbaru
   - Baru kemudian panggil add_to_cart dengan ID tersebut
   - EKSTRAK ID dari hasil search yang berbentuk [ID:X]
   - Contoh: "Dimsum Keju [ID:123]" → gunakan product_id: 123

**CONTOH LENGKAP:**
User: 'pesan dimsum keju 2 dan teh jumbo 1'

Step 1: Search produk pertama
AI: [panggil search_products('dimsum')]
Hasil: "🔹 Dimsum Keju [ID:1]
   Harga: Rp 40.000
   Stok: 10"

Step 2: Ekstrak ID dari hasil
AI: [ekstrak ID dari hasil] → product_id: 1

Step 3: Search produk kedua
AI: [panggil search_products('teh')]
Hasil: "🔹 Teh Jumbo [ID:2]
   Harga: Rp 5.000
   Stok: 20"

Step 4: Ekstrak ID dari hasil
AI: [ekstrak ID dari hasil] → product_id: 2

Step 5: Add semua produk ke cart dalam SATU panggilan
AI: [panggil add_to_cart(products=[{product_id:1, quantity:2}, {product_id:2, quantity:1}])]
```

## Cara Testing

### Test Case 1: Pencarian dengan kata kunci pendek
```bash
php test_product_search.php
```

Atau test manual:
```
User: "pesan dimsum 2"
Expected: AI menemukan "Dimsum Keju" dan menambahkan ke keranjang
```

### Test Case 2: Multiple products
```
User: "pesan dimsum 2, teh jumbo 2"
Expected: AI menemukan kedua produk dan menambahkan ke keranjang dalam satu kali add_to_cart
```

### Test Case 3: Pencarian dengan urutan kata berbeda
```
User: "pesan keju dimsum 2"
Expected: AI tetap menemukan "Dimsum Keju"
```

### Test Case 4: Pencarian dengan kata kunci sebagian
```
User: "pesan dim 2"
Expected: AI menemukan produk yang mengandung "dim" (seperti "Dimsum Keju")
```

## Debugging

### 1. Cek Log AI Agent
```bash
php check_ai_logs.php
```

### 2. Cek Produk di Database
```bash
php test_product_search.php
```

### 3. Cek Laravel Log
```bash
tail -f storage/logs/laravel.log | grep -i "ai agent\|llm\|tool call\|search_products"
```

### 4. Pastikan Produk Ada di Database
```bash
php artisan tinker
>>> App\Models\Product::where('is_active', true)->get(['id', 'name', 'price', 'stock_quantity'])
```

### 5. Jika Produk Tidak Ada, Jalankan Seeder
```bash
php artisan db:seed --class=PosTestDataSeeder
```

## Troubleshooting

### Masalah: AI masih tidak menemukan produk

**Solusi 1: Pastikan produk ada di database**
```bash
php test_product_search.php
```

**Solusi 2: Cek log untuk melihat query yang digunakan AI**
```bash
php check_ai_logs.php
```

**Solusi 3: Reset conversation AI Agent**
- Klik tombol "Reset" di Test AI Agent
- Atau hapus conversation dari database:
```bash
php artisan tinker
>>> App\Models\AiAgentConversation::truncate()
```

**Solusi 4: Pastikan AI Agent sudah dikonfigurasi dengan benar**
- Buka Dashboard → AI Agent
- Pastikan "Enable Order" diaktifkan
- Pastikan "Default Store" sudah dipilih

**Solusi 5: Cek apakah LLM API berfungsi**
- Cek log untuk error dari BytePlus ARK API
- Pastikan API key valid di `.env`:
```
BYTEPLUS_ARK_API_KEY=your_api_key
BYTEPLUS_ARK_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
BYTEPLUS_ARK_MODEL=your_model_id
```

### Masalah: AI menemukan produk tapi tidak bisa add to cart

**Kemungkinan penyebab:**
1. AI tidak mengekstrak ID dengan benar dari hasil search
2. AI memanggil add_to_cart dengan format yang salah

**Solusi:**
- Cek log untuk melihat parameter yang dikirim ke add_to_cart
- Pastikan format: `products=[{product_id:1, quantity:2}]`

## Catatan Penting

1. **Kata kunci minimal 3 karakter**: Untuk menghindari false positive, sistem hanya menggunakan kata kunci dengan panjang minimal 3 karakter dalam flexible matching

2. **Prioritas pencarian**:
   - Exact phrase match (prioritas tertinggi)
   - Flexible keyword matching (jika query mengandung multiple words)

3. **AI harus selalu search dulu**: Meskipun produk sudah ditampilkan di menu, AI WAJIB memanggil `search_products()` terlebih dahulu sebelum `add_to_cart()`

4. **Multiple products**: Untuk multiple products, AI harus:
   - Search setiap produk satu per satu
   - Ekstrak ID dari setiap hasil search
   - Kumpulkan semua ID
   - Panggil `add_to_cart()` SATU KALI dengan array products

5. **Format ID di hasil search**: ID ditampilkan dalam format `[ID:X]` untuk memudahkan AI mengekstrak

## File yang Diubah
- `app/Services/AiAgentService.php` - Fungsi `searchProducts()` dan tool definition
- `app/Models/AiAgent.php` - Method `buildSystemPrompt()` - Instruksi pemesanan
- `test_product_search.php` - Script untuk test pencarian produk (NEW)
- `check_ai_logs.php` - Script untuk melihat log AI Agent (NEW)

## Status
✅ Perbaikan selesai diterapkan
⏳ Menunggu testing dari user

## Next Steps
1. Test dengan scenario real: "pesan dimsum 2, teh jumbo 2"
2. Jika masih error, jalankan `php check_ai_logs.php` dan share hasilnya
3. Jika perlu, jalankan `php test_product_search.php` untuk memastikan produk ada di database

