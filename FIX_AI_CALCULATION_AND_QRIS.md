# Fix: AI Perhitungan Salah & QRIS Tidak Auto-Generate

## Masalah

### 1. Perhitungan Total Salah
AI menghitung total pembelian yang salah:
- **Seharusnya**: 2 x Rp 10.000 = Rp 20.000 (+ pajak 11% = Rp 22.200)
- **Yang terjadi**: AI menghitung Rp 40.000 atau Rp 66.000

### 2. QRIS Tidak Auto-Generate
Setelah konfirmasi pesanan, QRIS tidak di-generate otomatis padahal:
- QRIS Payment sudah di-enable di menu AI Agent
- Payment provider sudah dikonfigurasi
- SubMerchant sudah terdaftar

## Root Cause

### Masalah 1: AI Menghitung Ulang Sendiri
AI tidak menggunakan hasil dari tool functions dengan benar. AI menghitung ulang harga berdasarkan informasi di conversation history, yang menyebabkan perhitungan salah.

**Penyebab**:
- System prompt tidak memberikan instruksi yang cukup jelas
- AI tidak diperintahkan untuk HANYA menggunakan hasil dari function tools
- Tidak ada contoh yang jelas tentang cara yang benar vs salah

### Masalah 2: Test Endpoint Tidak Auto-Generate QRIS
Method `confirmOrder()` di `AiAgentController` (test endpoint) tidak memiliki logika untuk auto-generate QRIS seperti yang ada di `AiAgentService::confirmAndCreateOrder()`.

**Penyebab**:
- Test endpoint hanya membuat order tanpa cek QRIS enabled
- Tidak ada kode untuk generate QRIS transaction
- Tidak ada kode untuk create Payment record

### Masalah 3: SubMerchant Tidak Aktif
SubMerchant `is_active` bernilai NULL, bukan TRUE, sehingga `isQrisEnabled()` mengembalikan false.

## Solusi

### 1. Enhanced System Prompt dengan Instruksi Ketat

**File**: `app/Models/AiAgent.php` - Method `buildSystemPrompt()`

Menambahkan instruksi yang sangat jelas dan tegas:

```php
$prompt .= "\n\n## INSTRUKSI PEMESANAN - WAJIB DIPATUHI:

1. **JANGAN PERNAH menghitung harga sendiri**
   - SELALU gunakan hasil dari function tools
   - JANGAN tambahkan atau kurangi angka sendiri
   - JANGAN hitung pajak atau total sendiri

2. **Saat menambahkan produk ke keranjang:**
   - Gunakan function 'add_to_cart' dengan product_id dan quantity yang benar
   - Tampilkan PERSIS hasil yang dikembalikan function
   - JANGAN ubah atau hitung ulang harga

3. **Saat menampilkan keranjang:**
   - Gunakan function 'get_cart_summary'
   - Tampilkan PERSIS hasil yang dikembalikan function
   - JANGAN hitung ulang subtotal, pajak, atau total

4. **Saat konfirmasi pesanan:**
   - Gunakan function 'confirm_order'
   - Tampilkan PERSIS hasil yang dikembalikan function
   - Function akan otomatis generate QRIS jika enabled

5. **Format response:**
   - Salin PERSIS output dari function
   - Boleh tambahkan kalimat pembuka/penutup yang ramah
   - JANGAN ubah angka atau perhitungan apapun

CONTOH BENAR:
User: 'Pesan 2 Es Buah'
AI: [panggil add_to_cart(product_id=2, quantity=2)]
AI: 'Baik! ✅ Berhasil menambahkan ke keranjang!

📦 Es Buah Selasih
💰 Rp 10.000 x 2 = Rp 20.000

Ketik "lihat keranjang" untuk melihat ringkasan pesanan.'

CONTOH SALAH:
User: 'Pesan 2 Es Buah'
AI: 'Saya tambahkan 2 Es Buah seharga Rp 40.000' ❌ SALAH! Jangan hitung sendiri!
```

**Alasan**: 
- Instruksi yang sangat eksplisit dengan contoh konkret
- Menggunakan kata-kata tegas: "JANGAN PERNAH", "WAJIB", "PERSIS"
- Memberikan contoh yang benar vs salah
- Menjelaskan konsekuensi jika tidak mengikuti instruksi

### 2. Auto-Generate QRIS di Test Endpoint

**File**: `app/Http/Controllers/AiAgentController.php` - Method `confirmOrder()`

Menambahkan logika untuk auto-generate QRIS setelah order dibuat:

```php
// Check if QRIS is enabled - auto generate QRIS
if ($aiAgent->isQrisEnabled()) {
    $subMerchant = $aiAgent->getSubMerchant();
    
    if ($subMerchant) {
        try {
            // Generate QRIS for the order
            $qrisTransaction = $this->qrisService->generateQris(
                $subMerchant, 
                (float) $order->total, 
                ['description' => "Pesanan #{$order->order_number}"]
            );

            // Link QRIS to order
            $qrisTransaction->linked_order_id = $order->id;
            $qrisTransaction->save();

            // Create Payment record
            \App\Models\Payment::create([
                'order_id' => $order->id,
                'qris_transaction_id' => $qrisTransaction->id,
                'method' => \App\Models\Payment::METHOD_QRIS,
                'amount' => $order->total,
                'status' => \App\Models\Payment::STATUS_PENDING,
            ]);

            // Store QRIS transaction in conversation
            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

            // Return response with QRIS
            $expiryTime = $qrisTransaction->expires_at->format('H:i');
            $formattedTotal = 'Rp ' . number_format($order->total, 0, ',', '.');

            $response = "✅ Pesanan Berhasil Dibuat!\n\n";
            $response .= "📋 No. Pesanan: {$order->order_number}\n";
            $response .= "💰 Total: {$formattedTotal}\n\n";
            $response .= "💳 Silakan bayar dengan QRIS:\n";
            $response .= "{$qrisTransaction->qr_code_url}\n\n";
            $response .= "⏰ Berlaku hingga: {$expiryTime}\n\n";
            $response .= "Cara pembayaran:\n";
            $response .= "1. Buka aplikasi e-wallet/mobile banking\n";
            $response .= "2. Scan QR Code di atas\n";
            $response .= "3. Konfirmasi pembayaran\n\n";
            $response .= "Ketik 'cek status' setelah membayar. 🙏";

            return $response;
        } catch (\Exception $e) {
            Log::error('QRIS generation failed', ['error' => $e->getMessage()]);
            // Fall through to response without QRIS
        }
    }
}
```

**Alasan**:
- Konsisten dengan implementasi di `AiAgentService`
- Auto-generate QRIS jika enabled
- Link QRIS ke order dengan Payment record
- Memberikan QR code URL langsung ke user

### 3. Aktivasi SubMerchant

**Command**:
```php
$subMerchant = SubMerchant::find(1);
$subMerchant->is_active = true;
$subMerchant->save();
```

**Alasan**: SubMerchant harus aktif agar `isQrisEnabled()` mengembalikan true.

## Testing

### Test 1: Perhitungan yang Benar
```
User: "Pesan 2 Es Buah Selasih"
Expected: 
- AI memanggil add_to_cart(product_id=2, quantity=2)
- AI menampilkan: "Rp 10.000 x 2 = Rp 20.000"
- TIDAK menampilkan angka lain

User: "Lihat keranjang"
Expected:
- AI memanggil get_cart_summary()
- AI menampilkan:
  * Subtotal: Rp 20.000
  * Pajak (11%): Rp 2.200
  * Total: Rp 22.200
```

### Test 2: Auto-Generate QRIS
```
User: "Konfirmasi pesanan"
Expected:
- Order dibuat dengan benar
- QRIS transaction di-generate otomatis
- Payment record dibuat
- Response berisi:
  * Order number
  * Total yang benar
  * QR code URL
  * Instruksi pembayaran
  * Waktu kadaluarsa
```

### Test 3: Verifikasi Database
```sql
-- Check order
SELECT id, order_number, total, source FROM orders ORDER BY id DESC LIMIT 1;

-- Check QRIS transaction
SELECT id, order_id, amount, status, qr_code_url 
FROM qris_transactions 
WHERE linked_order_id = [order_id];

-- Check payment
SELECT id, order_id, qris_transaction_id, amount, status 
FROM payments 
WHERE order_id = [order_id];
```

## Cara Menggunakan

1. **Pastikan QRIS Payment Enabled**:
   - Buka menu AI Agent
   - Toggle "Enable QRIS Payment" ke ON
   - Pastikan payment provider sudah dikonfigurasi

2. **Test Order Flow**:
   - Buka Test AI Agent
   - Ketik: "Tampilkan menu"
   - Ketik: "Pesan 2 Es Buah Selasih"
   - Ketik: "Lihat keranjang"
   - Ketik: "Konfirmasi pesanan"

3. **Verifikasi**:
   - Cek perhitungan total benar
   - Cek QRIS QR code muncul
   - Cek order di database
   - Cek QRIS transaction di database

## Files Modified

1. `app/Models/AiAgent.php` - Enhanced system prompt dengan instruksi ketat
2. `app/Http/Controllers/AiAgentController.php` - Auto-generate QRIS di test endpoint
3. Database - Aktivasi SubMerchant

## Impact

- ✅ AI tidak lagi menghitung ulang harga sendiri
- ✅ Perhitungan total selalu benar
- ✅ QRIS auto-generate setelah konfirmasi order
- ✅ User langsung mendapat QR code untuk pembayaran
- ✅ Payment flow lengkap dari order sampai QRIS

## Notes

- System prompt sekarang sangat eksplisit tentang apa yang boleh dan tidak boleh dilakukan AI
- Test endpoint sekarang memiliki feature parity dengan production endpoint
- QRIS hanya di-generate jika:
  1. `qris_enabled` = true di AI Agent
  2. SubMerchant aktif
  3. Payment provider aktif
- Jika QRIS generation gagal, order tetap dibuat tapi tanpa QRIS (fallback)
