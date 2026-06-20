# Cara Test Summary AI Agent di UI

## Apa itu Summary?

Summary adalah fitur yang meringkas conversation panjang agar AI Agent tidak perlu memproses semua pesan lama. Ini menghemat token dan mempercepat response.

## Threshold Saat Ini

Berdasarkan konfigurasi di `.env` dan `config/conversation.php`:

- ✅ **Summarization ENABLED**: `true`
- 📊 **Message Count Threshold**: 6 pesan (default)
- 🔢 **Token Threshold**: 800 tokens (default)
- 📝 **Min Substantive Messages**: 3 pesan bermakna (lebih dari 5 karakter)

**Artinya:** Summary akan trigger jika conversation memiliki:
- Minimal 6 pesan total
- Minimal 3 pesan yang bermakna (bukan cuma "ok", "ya", dll)
- Atau total token mencapai 800

## Cara Test di UI Dashboard

### Step 1: Buka AI Agent Test Chat

1. Login ke dashboard
2. Buka menu **AI Agent**
3. Scroll ke bagian **Test Chat** di bawah

### Step 2: Kirim Pesan Panjang Berurutan

Kirim minimal **6-7 pesan** dengan konten yang cukup panjang. Contoh:

```
Pesan 1: "Halo, nama saya Budi. Saya ingin tanya tentang menu yang tersedia di restoran ini."

Pesan 2: "Saya suka makanan pedas dan seafood. Apa ada rekomendasi menu yang cocok untuk saya?"

Pesan 3: "Berapa harga untuk paket seafood pedas? Dan apakah ada promo hari ini?"

Pesan 4: "Saya juga ingin tahu jam operasional restoran. Apakah buka setiap hari?"

Pesan 5: "Kalau saya pesan sekarang, berapa lama waktu tunggu untuk makanan siap?"

Pesan 6: "Oke, saya mau pesan 2 porsi nasi goreng seafood pedas dan 1 es teh manis."

Pesan 7: "Tolong konfirmasi pesanan saya ya."
```

### Step 3: Cek Apakah Summary Terjadi

Setelah pesan ke-6 atau ke-7, summary akan di-trigger. Untuk memastikan:

#### A. Cek di Browser Console (F12)

Buka Developer Tools (F12) dan lihat Console. Cari log seperti:

```
Summarization triggered
conversation_id: 123
message_count: 7
substantive_count: 6
estimated_tokens: 850
```

#### B. Cek di Laravel Log

```bash
tail -f storage/logs/laravel.log
```

Cari log:
```
[INFO] Summarization triggered
[INFO] Summary generated successfully
```

#### C. Cek di Database

Jalankan script test:

```bash
php test_ai_memory.php
```

Atau manual di tinker:

```bash
php artisan tinker
```

```php
// Ambil conversation terakhir
$conv = App\Models\AiAgentConversation::latest()->first();

// Cek apakah ada summary
$conv->summary;  // Jika ada summary, akan tampil text ringkasan

// Cek jumlah pesan
count($conv->messages);  // Harus >= 6

// Cek token
$estimator = new App\Services\TokenEstimator();
$estimator->estimateConversationTokens($conv->messages);
```

### Step 4: Test Apakah AI Masih Ingat Context

Setelah summary terjadi, test apakah AI masih ingat informasi dari awal:

```
Pesan 8: "Apa nama saya tadi?"
```

AI harus menjawab: **"Budi"** (dari pesan pertama)

```
Pesan 9: "Apa yang saya pesan tadi?"
```

AI harus menjawab: **"2 porsi nasi goreng seafood pedas dan 1 es teh manis"**

Jika AI masih ingat, berarti **summary berfungsi dengan baik**! 🎉

## Cara Mempercepat Test (Turunkan Threshold)

Jika mau test lebih cepat tanpa kirim banyak pesan, edit `.env`:

```env
# Turunkan threshold untuk test
CONVERSATION_MESSAGE_COUNT_THRESHOLD=3
CONVERSATION_TOKEN_THRESHOLD=200
```

Lalu restart server:

```bash
php artisan config:clear
```

Sekarang summary akan trigger setelah **3 pesan** saja.

## Troubleshooting

### Summary Tidak Trigger

1. **Cek apakah enabled:**
   ```bash
   php artisan tinker
   config('conversation.summarization.enabled')  // Harus true
   ```

2. **Cek threshold:**
   ```bash
   config('conversation.summarization.message_count_threshold')  // Default: 6
   config('conversation.summarization.token_threshold')  // Default: 800
   ```

3. **Cek pesan substantive:**
   - Pesan harus lebih dari 5 karakter
   - Minimal 3 pesan substantive
   - Jangan kirim pesan pendek seperti "ok", "ya", "oke"

4. **Cek API Key BytePlus:**
   ```env
   BYTEPLUS_ARK_API_KEY=...  # Harus valid
   ```

### Summary Error (SUDAH DIPERBAIKI ✅)

**Update 2025-12-30:** Summary sekarang **tidak akan gagal** lagi!

Jika LLM tidak return JSON, sistem akan otomatis:
- ✅ Gunakan plain text sebagai summary
- ✅ Deteksi intent dari keyword
- ✅ Extract data penting (harga, produk, dll)
- ✅ Tetap hemat token (70-85%)

Cek log untuk melihat apakah menggunakan fallback:

```bash
tail -f storage/logs/laravel.log | grep -i "plain_text_fallback"
```

Jika muncul log `plain_text_fallback`, berarti:
- ✅ LLM tidak return JSON
- ✅ Sistem gunakan fallback
- ✅ Summary tetap tersimpan
- ✅ AI tetap ingat context

## Indikator Summary Berhasil

✅ **Di Console/Log:** Muncul "Summarization triggered" dan "Summary generated"
✅ **Di Database:** Field `summary` terisi dengan text ringkasan
✅ **AI Behavior:** AI masih ingat context dari pesan lama meskipun conversation panjang
✅ **Performance:** Response time lebih cepat karena tidak perlu proses semua pesan

## Tips

- **Jangan reset conversation** saat test summary, biarkan conversation terus berlanjut
- **Kirim pesan yang bermakna**, bukan cuma "ok" atau "ya"
- **Tunggu response AI** sebelum kirim pesan berikutnya
- **Cek log** untuk memastikan summary benar-benar terjadi

## Setelah Test

Jika mau kembalikan ke setting normal (agar tidak terlalu sering summarize):

```env
CONVERSATION_SUMMARIZATION_ENABLED=true
CONVERSATION_MESSAGE_COUNT_THRESHOLD=6
CONVERSATION_TOKEN_THRESHOLD=800
```

Atau matikan jika tidak perlu:

```env
CONVERSATION_SUMMARIZATION_ENABLED=false
```

---

**Happy Testing!** 🚀
