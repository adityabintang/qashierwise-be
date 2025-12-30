# Panduan: Contextual Fallback untuk AI Agent

## Overview

Sistem AI Agent sekarang dilengkapi dengan **Contextual Fallback Generator** yang cerdas. Ketika LLM gagal menghasilkan response (empty content), sistem akan otomatis generate fallback message yang **relevan dengan konteks percakapan user**.

## Keunggulan

### ❌ Sebelum (Generic Fallback)
```
User: "pesan dimsum"
AI: "Maaf, saya tidak mengerti. Bisa diulang?"
```
**Masalah:** User bingung, tidak tahu apa yang salah

### ✅ Setelah (Contextual Fallback)
```
User: "pesan dimsum"
AI: "Saya menemukan produk yang Anda cari: Dimsum Ayam, Dimsum Udang. 
     Berapa jumlah yang ingin Anda pesan? Contoh: 'pesan Dimsum Ayam 2 porsi'"
```
**Keuntungan:** User mendapat info produk + panduan cara pesan

## Cara Kerja

### 1. Intent Detection
Sistem mendeteksi intent dari pesan user:
- **Greeting**: Halo, Hi, Hai, Assalamualaikum
- **Menu Inquiry**: menu, ada apa, daftar produk
- **Order Intent**: pesan, beli, order, mau
- **Cart Inquiry**: keranjang, cart, pesanan
- **Payment**: bayar, qris, transfer
- **Help**: bantuan, help, cara
- **Thank You**: terima kasih, thanks
- **Cancel**: batal, cancel, tidak jadi

### 2. Context Analysis
Sistem menganalisis konteks percakapan:
- **Search Results**: Produk apa yang ditemukan?
- **Cart State**: Ada berapa item di keranjang?
- **Order State**: Ada order pending?
- **Last Message**: Apa yang user tanyakan terakhir?

### 3. Response Generation
Berdasarkan intent + context, sistem generate response yang:
- **Relevant**: Sesuai dengan pertanyaan user
- **Helpful**: Memberikan panduan konkret
- **Actionable**: User tahu apa yang harus dilakukan

## Contoh Response Berdasarkan Intent

### Greeting
```
User: "Halo"
Fallback: "Halo! Ada yang bisa saya bantu? Ketik 'menu' untuk lihat produk kami."
```

### Menu Inquiry
```
User: "ada menu apa?"
Fallback (no search): "Untuk melihat menu lengkap, ketik 'lihat menu' atau 'daftar produk'."
Fallback (with search): "Kami punya: Nasi Goreng, Mie Goreng, Dimsum. Mau pesan yang mana?"
```

### Order Intent
```
User: "mau pesan"
Fallback (empty cart): "Silakan sebutkan produk yang ingin Anda pesan. 
                        Contoh: 'pesan nasi goreng 2 porsi'"
Fallback (with cart): "Anda sudah punya 2 item di keranjang. 
                       Mau tambah lagi atau langsung checkout?"
```

### Order with Product Name
```
User: "pesan dimsum"
Fallback (found): "Saya menemukan: Dimsum Ayam, Dimsum Udang. 
                   Berapa jumlah yang ingin Anda pesan? 
                   Contoh: 'pesan Dimsum Ayam 2 porsi'"
Fallback (not found): "Maaf, produk yang Anda cari tidak tersedia. 
                       Ketik 'menu' untuk melihat daftar produk kami."
```

### Cart Inquiry
```
User: "lihat keranjang"
Fallback (empty): "Keranjang Anda masih kosong. Ketik 'menu' untuk lihat produk."
Fallback (with items): "Anda punya 3 item di keranjang. 
                        Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout."
```

### Payment Inquiry
```
User: "cara bayar"
Fallback (no order): "Belum ada pesanan yang perlu dibayar. 
                      Silakan buat pesanan terlebih dahulu."
Fallback (with order): "Untuk melakukan pembayaran, silakan konfirmasi pesanan Anda terlebih dahulu."
```

### Help Request
```
User: "bantuan"
Fallback: "Saya bisa bantu Anda:
           • Lihat menu: ketik 'menu' atau 'daftar produk'
           • Pesan: ketik 'pesan [nama produk] [jumlah]'
           • Lihat keranjang: ketik 'lihat keranjang'
           • Checkout: ketik 'konfirmasi pesanan'
           
           Ada yang bisa saya bantu?"
```

### Thank You
```
User: "terima kasih"
Fallback: "Sama-sama! Ada lagi yang bisa saya bantu?"
```

### Cancel
```
User: "batal"
Fallback (with cart): "Mau batalkan pesanan? Ketik 'hapus keranjang' untuk mengosongkan keranjang."
Fallback (no cart): "Baik, tidak jadi. Ada yang bisa saya bantu lagi?"
```

## Implementasi Teknis

### Method 1: `generateContextualFallback()`
Digunakan ketika ada tool results (search, cart operations, dll)

**Input:**
- `$toolResults`: Array hasil eksekusi tool calls
- `$conversation`: Object conversation untuk context

**Process:**
1. Extract tool names yang dieksekusi
2. Parse search results untuk ambil product names
3. Get last user message
4. Detect user intent dari message
5. Generate response berdasarkan intent + results + context

**Output:** String fallback message yang kontekstual

### Method 2: `generateSimpleFallback()`
Digunakan ketika tidak ada tool results (main response empty)

**Input:**
- `$conversation`: Object conversation untuk context

**Process:**
1. Get last user message
2. Detect intent (greeting, menu, order, help, dll)
3. Check conversation state (cart, order)
4. Generate response berdasarkan intent + state

**Output:** String fallback message yang kontekstual

## Testing

### Manual Testing
```bash
# Test dengan berbagai skenario
php test_contextual_fallback.php
```

### Test Cases
1. ✅ Greeting messages (Halo, Hi, Hai)
2. ✅ Menu inquiry (menu, ada apa, daftar)
3. ✅ Order intent (pesan, beli, order)
4. ✅ Cart inquiry (empty vs with items)
5. ✅ Payment inquiry (no order vs with order)
6. ✅ Help request (bantuan, help, cara)
7. ✅ Thank you messages
8. ✅ Search results (found vs not found)

### Integration Testing
```bash
# Test dengan real AI Agent
php test_empty_response_fix.php
```

## Monitoring

### Check Fallback Usage
```bash
# Monitor ketika fallback digunakan
tail -f storage/logs/laravel.log | grep "empty content"
```

### Analyze Patterns
```bash
# Jalankan monitoring script
php monitor_empty_responses.php
```

### Metrics
- Frequency: Berapa sering fallback digunakan?
- Intent Distribution: Intent apa yang paling sering trigger fallback?
- User Satisfaction: Apakah user lanjut conversation atau berhenti?

## Best Practices

### 1. Keep Fallback Relevant
✅ DO: Sesuaikan dengan konteks user
❌ DON'T: Generic "Maaf, saya tidak mengerti"

### 2. Provide Guidance
✅ DO: Berikan contoh konkret
❌ DON'T: Hanya bilang error tanpa solusi

### 3. Be Helpful
✅ DO: Suggest next action
❌ DON'T: Biarkan user bingung

### 4. Maintain Tone
✅ DO: Ramah dan sopan
❌ DON'T: Terlalu formal atau kaku

## Troubleshooting

### Fallback Terlalu Sering Muncul
**Penyebab:** LLM sering return empty content
**Solusi:**
1. Check LLM API status
2. Review system prompt
3. Adjust temperature (turunkan ke 0.5)
4. Check token limits

### Fallback Tidak Relevan
**Penyebab:** Intent detection kurang akurat
**Solusi:**
1. Tambah keyword untuk intent detection
2. Improve regex patterns
3. Add more context analysis

### User Masih Bingung
**Penyebab:** Fallback kurang jelas
**Solusi:**
1. Tambah contoh konkret
2. Simplify language
3. Add more guidance

## Future Improvements

### 1. Machine Learning Intent Detection
Gunakan ML model untuk detect intent lebih akurat

### 2. Personalized Fallback
Sesuaikan fallback dengan user history dan preferences

### 3. Multi-language Support
Support fallback dalam bahasa lain (English, dll)

### 4. A/B Testing
Test berbagai variasi fallback untuk optimize conversion

### 5. Fallback Analytics
Track effectiveness dari setiap fallback message

## Kesimpulan

Contextual Fallback Generator memastikan:
- ✅ User **tidak pernah** mendapat response kosong
- ✅ Response **selalu relevan** dengan pertanyaan user
- ✅ User mendapat **guidance yang jelas** untuk next action
- ✅ Better UX dan **higher engagement**
- ✅ System **gracefully handles** LLM failures

**Result:** AI Agent yang lebih reliable, helpful, dan user-friendly! 🎉
