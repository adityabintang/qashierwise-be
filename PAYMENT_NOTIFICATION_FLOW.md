# Payment Notification Flow - Automatic WhatsApp Confirmation

## Overview
Sistem sudah dikonfigurasi untuk **otomatis mengirim notifikasi WhatsApp** ke customer ketika pembayaran QRIS berhasil (status settlement/paid), tanpa perlu customer mengetik "cek status" atau command lainnya.

## Flow Diagram

```
1. Customer melakukan pembayaran QRIS
   ↓
2. Midtrans mengirim webhook notification (settlement)
   ↓
3. MidtransWebhookController::handleNotification()
   - Validasi signature
   - Cari QrisTransaction berdasarkan order_id
   ↓
4. MidtransWebhookController::handleSettlement()
   - Update status transaction ke 'settlement'
   - Dispatch ProcessQrisPayment job ke queue
   ↓
5. ProcessQrisPayment::handle()
   - Update balance merchant
   - Create platform fee record
   - Update Payment & Order status
   - Call sendPaymentNotification()
   ↓
6. AiAgentService::sendPaymentConfirmation()
   - Cari conversation berdasarkan current_qris_transaction_id
   - Format pesan konfirmasi pembayaran
   - Kirim WhatsApp message otomatis ke customer
   - Clear payment context (cart, pending order, qris transaction)
   ↓
7. Customer menerima notifikasi WhatsApp otomatis ✅
```

## Komponen yang Terlibat

### 1. Webhook Handler
**File:** `app/Http/Controllers/Api/MidtransWebhookController.php`

**Method:** `handleSettlement()`
- Menerima webhook dari Midtrans saat payment settlement
- Validasi signature untuk keamanan
- Update status QrisTransaction
- Dispatch job untuk processing asynchronous

### 2. Payment Processing Job
**File:** `app/Jobs/ProcessQrisPayment.php`

**Method:** `handle()`
- Proses dalam database transaction untuk data consistency
- Update balance merchant dengan platform fee
- Update status Payment dan Order
- **Panggil `sendPaymentNotification()` untuk kirim WhatsApp**

**Method:** `sendPaymentNotification()`
- Memanggil `AiAgentService::sendPaymentConfirmation()`
- Tidak akan fail job jika notifikasi gagal (optional)

### 3. WhatsApp Notification Service
**File:** `app/Services/AiAgentService.php`

**Method:** `sendPaymentConfirmation(QrisTransaction $qrisTransaction)`
- Cari conversation berdasarkan `current_qris_transaction_id`
- Ambil WhatsApp contact dan account
- Format pesan konfirmasi:
  ```
  ✅ Pembayaran Berhasil!
  
  Jumlah: Rp 50.000
  No. Transaksi: QRIS-xxx
  No. Pesanan: ORD-xxx
  
  Terima kasih atas pembayaran Anda! Pesanan sedang diproses. 🙏
  ```
- Kirim via WhatsApp menggunakan `sendReply()`
- Simpan ke conversation history
- Clear payment context (cart, pending order, qris)

### 4. Conversation Context
**File:** `app/Models/AiAgentConversation.php`

**Field:** `current_qris_transaction_id`
- Foreign key ke `qris_transactions` table
- Di-set saat QRIS di-generate
- Digunakan untuk link conversation dengan QRIS transaction
- Memungkinkan sistem menemukan conversation yang tepat untuk notifikasi

## Kapan QRIS Transaction ID Di-set?

QRIS transaction ID di-set di conversation pada saat:

1. **AI Agent generate QRIS** (via tool call `generate_qris`)
   - File: `app/Services/AiAgentService.php`
   - Method: `generateQrisForOrder()`
   - Line: `$conversation->setCurrentQrisTransaction($qrisTransaction->id);`

2. **Manual QRIS generation** (via API/UI)
   - File: `app/Http/Controllers/AiAgentController.php`
   - Method: `generateQris()`
   - Line: `$conversation->setCurrentQrisTransaction($qrisTransaction->id);`

## Error Handling

### Jika Conversation Tidak Ditemukan
```php
if (!$conversation) {
    Log::info('No conversation found for QRIS transaction, skipping notification');
    return; // Skip silently, tidak error
}
```

### Jika WhatsApp Account Tidak Aktif
```php
if (!$account || !$account->is_active) {
    Log::warning('WhatsApp account not available for payment confirmation');
    return; // Skip silently
}
```

### Jika Notifikasi Gagal
```php
// Di ProcessQrisPayment::sendPaymentNotification()
try {
    $aiAgentService->sendPaymentConfirmation($this->transaction);
} catch (\Exception $e) {
    Log::warning('Failed to send payment notification, continuing');
    // Tidak throw exception, job tetap sukses
}
```

## Testing

### Manual Testing
1. Buat order via AI Agent
2. Generate QRIS untuk pembayaran
3. Bayar menggunakan QRIS (atau simulate webhook)
4. **Customer akan otomatis menerima notifikasi WhatsApp**

### Simulate Webhook (Development)
```bash
POST /api/webhooks/midtrans
Content-Type: application/json

{
  "order_id": "QRIS-xxx",
  "transaction_status": "settlement",
  "status_code": "200",
  "gross_amount": "50000.00",
  "signature_key": "..."
}
```

### Check Logs
```bash
# Webhook received
grep "Midtrans webhook received" storage/logs/laravel.log

# Settlement processed
grep "Settlement processed" storage/logs/laravel.log

# Payment notification sent
grep "Payment confirmation sent via WhatsApp" storage/logs/laravel.log
```

## Queue Configuration

Pastikan queue worker berjalan untuk memproses job:

```bash
# Development
php artisan queue:work --queue=payments

# Production (Supervisor)
[program:laravel-worker]
command=php /path/to/artisan queue:work --queue=payments --tries=3
```

## Kesimpulan

✅ **Sistem sudah lengkap dan otomatis**
- Customer tidak perlu mengetik "cek status"
- Notifikasi dikirim otomatis saat payment settlement
- Error handling yang baik (tidak fail job jika notifikasi gagal)
- Logging lengkap untuk debugging

✅ **Flow sudah terintegrasi**
- Webhook → Job → Notification → WhatsApp
- Conversation context tersimpan dengan baik
- Payment context di-clear setelah sukses
