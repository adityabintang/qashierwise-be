# Fix QRIS Display in AI Agent

## Masalah
AI Agent menampilkan URL image QR Code langsung dari provider (Midtrans/Xendit) yang tidak bisa dibuka di WhatsApp atau aplikasi chat lainnya.

## Solusi
Mengubah AI Agent untuk mengirimkan shareable link ke halaman payment QRIS yang menampilkan QR Code image.

## Perubahan

### 1. Format URL yang Dikirim
**Sebelum:**
```
{$qrisTransaction->qr_code_url}
```
Contoh: `https://api.midtrans.com/v2/qris/...` (URL image langsung)

**Sesudah:**
```
{$qrisTransaction->getShareableLink()}
```
Contoh Development: `http://127.0.0.1:8000/pay/qris/QRIS-20251225175944-1YTHQTDB`
Contoh Production: `https://qashierwise.com/pay/qris/QRIS-xxxxxxxxxxxxxxxx-xxxxxx`

### 2. File yang Diubah

#### app/Http/Controllers/AiAgentController.php
- **Method:** `handleQrisPayment()` (line ~724)
  - Menambahkan `$shareableLink = $qrisTransaction->getShareableLink();`
  - Mengubah response dari menampilkan `qr_code_url` menjadi `shareableLink`
  - Mengubah instruksi pembayaran

- **Method:** `processOrderCreation()` (line ~1127)
  - Menambahkan `$shareableLink = $qrisTransaction->getShareableLink();`
  - Mengubah response dari menampilkan `qr_code_url` menjadi `shareableLink`
  - Mengubah instruksi pembayaran

#### app/Services/AiAgentService.php
- **Method:** `handleQrisPayment()` (line ~782)
  - Menambahkan `$shareableLink = $qrisTransaction->getShareableLink();`
  - Mengubah response dari menampilkan `qr_code_url` menjadi `shareableLink`
  - Mengubah instruksi pembayaran

- **Method:** `processOrderCreation()` (line ~974)
  - Menambahkan `$shareableLink = $qrisTransaction->getShareableLink();`
  - Mengubah response dari menampilkan `qr_code_url` menjadi `shareableLink`
  - Mengubah instruksi pembayaran

### 3. Instruksi Pembayaran Baru
```
Cara pembayaran:
1. Klik link di atas
2. Scan QR Code yang muncul
3. Buka aplikasi e-wallet/mobile banking
4. Konfirmasi pembayaran
```

## Keuntungan
1. ✅ Link bisa dibuka di WhatsApp dan aplikasi chat lainnya
2. ✅ Halaman payment menampilkan QR Code dengan UI yang lebih baik
3. ✅ Halaman payment sudah ada fitur:
   - Copy link
   - Share ke WhatsApp
   - Download QR Code
   - Auto-refresh status pembayaran
4. ✅ URL lebih user-friendly dan branded (qashierwise.com)

## Testing
1. Test di development: `http://127.0.0.1:8000/pay/qris/{orderId}`
2. Test di production: `https://qashierwise.com/pay/qris/{orderId}` atau `https://api.qashierwise.com/pay/qris/{orderId}`

## Catatan
- Method `getShareableLink()` sudah ada di model `QrisTransaction`
- Route `/pay/qris/{orderId}` sudah ada di `routes/web.php`
- Controller `QrisPaymentPageController` sudah menangani halaman payment
- Halaman payment sudah responsive dan mobile-friendly
