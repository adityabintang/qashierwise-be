# Fix: AI Agent Tidak Meminta User Memberikan ID Produk

## Masalah
Sebelumnya, ketika user memesan makanan, AI Agent meminta user untuk memberikan ID produk:
- "Maaf, parameter tidak lengkap. Mohon berikan ID produk."
- User harus tahu ID produk untuk memesan

**Root Cause**: AI Agent tidak menggunakan function `search_products` sebelum `add_to_cart`, sehingga tidak memiliki ID produk yang diperlukan.

## Solusi
User sekarang **TIDAK PERLU** tahu ID produk. Mereka hanya perlu menyebutkan **NAMA produk**.

### Workflow Baru

1. **User bertanya menu**: AI menampilkan daftar produk TANPA ID
2. **User memesan**: AI WAJIB panggil `search_products` dulu untuk mendapatkan ID
3. **AI add to cart**: Gunakan ID dari hasil search untuk `add_to_cart`

### Perubahan yang Dilakukan

#### 1. Update System Prompt (`app/Models/AiAgent.php`)

**Perubahan utama:**
- Tambah instruksi WAJIB: "SELALU SEARCH DULU SEBELUM ADD TO CART"
- Workflow 3 langkah yang jelas:
  1. User bertanya menu → tampilkan tanpa ID
  2. User memesan → WAJIB search_products dulu
  3. Setelah dapat ID → baru add_to_cart

**Contoh workflow:**
```
User: "pesan dimsum keju 2 dan teh jumbo 1"
AI Step 1: search_products('dimsum keju') → dapat ID:1
AI Step 2: search_products('teh jumbo') → dapat ID:2
AI Step 3: add_to_cart(products=[{product_id:1, quantity:2}, {product_id:2, quantity:1}])
AI Response: "✅ Berhasil menambahkan ke keranjang!
📦 Dimsum Keju x2
📦 Teh Jumbo x1"
```

#### 2. Update Function `search_products` Description (`app/Services/AiAgentService.php`)

**Sebelum:**
```
'description' => 'Cari produk berdasarkan nama atau SKU'
```

**Sesudah:**
```
'description' => 'WAJIB DIPANGGIL PERTAMA sebelum add_to_cart. Cari produk berdasarkan nama atau SKU untuk mendapatkan ID produk. Gunakan ID dari hasil search ini untuk add_to_cart.'
```

#### 3. Update Function `searchProducts` Output

- ID produk dalam format `[ID:X]` untuk internal AI
- Tambah instruksi: "ID dalam [ID:X] adalah untuk internal AI saja. Saat menampilkan ke user, HAPUS bagian [ID:X]"
- User hanya melihat: nama produk, harga, stok, dan deskripsi

#### 4. Update Function `add_to_cart` Description

- Deskripsi diperjelas bahwa AI harus mencari ID produk dari nama terlebih dahulu
- Parameter `product_id` dijelaskan sebagai "ID produk yang sudah kamu cari dari nama produk yang disebutkan user"

## Hasil
✅ User sekarang bisa memesan dengan menyebutkan nama produk saja
✅ AI Agent WAJIB panggil search_products sebelum add_to_cart
✅ AI Agent otomatis mencari dan menggunakan ID produk
✅ Pengalaman user lebih natural dan mudah

## Testing
Coba test dengan pesan:
1. "menunya apa aja?" → Harus tampil tanpa ID
2. "pesan dimsum keju 2" → AI harus search dulu, baru add to cart
3. "pesan dimsum dan teh jumbo 1 yaa" → AI harus search 2x, baru add to cart

AI Agent seharusnya tidak lagi meminta ID produk.

## Catatan Penting
- AI Agent HARUS panggil `search_products` setiap kali user memesan, meskipun produk sudah ditampilkan sebelumnya
- Ini memastikan AI Agent selalu memiliki ID produk yang valid dan terbaru

