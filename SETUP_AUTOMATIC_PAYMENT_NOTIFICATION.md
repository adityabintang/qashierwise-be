# Setup: Automatic Payment Notification via WhatsApp

## Fitur
Ketika customer melakukan pembayaran QRIS dan status berubah menjadi **settlement/paid**, sistem akan **otomatis mengirim notifikasi WhatsApp** ke customer tanpa perlu customer mengetik "cek status" atau command apapun.

## Prerequisites

### 1. Queue Worker Harus Berjalan
Notifikasi dikirim melalui background job, jadi queue worker harus aktif.

**Development:**
```bash
php artisan queue:work --queue=payments
```

**Production (menggunakan Supervisor):**
```ini
[program:laravel-worker-payments]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=payments --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker-payments.log
stopwaitsecs=3600
```

Reload supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker-payments:*
```

### 2. Webhook Midtrans Terkonfigurasi
Pastikan webhook URL sudah terdaftar di Midtrans Dashboard:

**Webhook URL:**
```
https://yourdomain.com/api/webhooks/midtrans
```

**Settings di Midtrans:**
1. Login ke Midtrans Dashboard
2. Settings → Configuration → Notification URL
3. Masukkan webhook URL di atas
4. Save

### 3. WhatsApp Account Aktif
- WhatsApp account harus connected dan aktif
- AI Agent harus enabled untuk account tersebut

## Cara Kerja

### Flow Otomatis
```
Customer bayar QRIS
    ↓
Midtrans kirim webhook (settlement)
    ↓
Sistem update status transaction
    ↓
Dispatch job ke queue 'payments'
    ↓
Job proses payment (balance, fee, order)
    ↓
Job kirim WhatsApp notification OTOMATIS ✅
    ↓
Customer terima notifikasi tanpa perlu chat
```

### Pesan yang Dikirim
```
✅ Pembayaran Berhasil!

Jumlah: Rp 50.000
No. Transaksi: QRIS-20251226-ABC123
No. Pesanan: ORD-20251226-001

Terima kasih atas pembayaran Anda! Pesanan sedang diproses. 🙏
```

## Testing

### 1. Test End-to-End (Recommended)
1. Buat order via AI Agent WhatsApp
2. Generate QRIS untuk pembayaran
3. Bayar menggunakan aplikasi e-wallet/mobile banking
4. **Tunggu beberapa detik**
5. Customer akan otomatis menerima notifikasi WhatsApp ✅

### 2. Test dengan Webhook Simulator
Jika ingin test tanpa bayar real:

```bash
# Simulate webhook dari Midtrans
curl -X POST https://yourdomain.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "QRIS-20251226-ABC123",
    "transaction_status": "settlement",
    "status_code": "200",
    "gross_amount": "50000.00",
    "transaction_id": "test-txn-123",
    "payment_type": "qris",
    "signature_key": "..."
  }'
```

**Note:** Signature validation akan skip jika `MIDTRANS_SERVER_KEY` tidak di-set di `.env` (development mode).

### 3. Check Logs
Monitor logs untuk memastikan flow berjalan:

```bash
# Tail logs
tail -f storage/logs/laravel.log

# Cari webhook received
grep "Midtrans webhook received" storage/logs/laravel.log

# Cari settlement processed
grep "Settlement processed" storage/logs/laravel.log

# Cari job dispatched
grep "payment job dispatched" storage/logs/laravel.log

# Cari notification sent
grep "WhatsApp payment notification sent" storage/logs/laravel.log

# Cari payment confirmation sent
grep "Payment confirmation sent via WhatsApp" storage/logs/laravel.log
```

## Troubleshooting

### Notifikasi Tidak Terkirim

#### 1. Check Queue Worker
```bash
# Pastikan queue worker berjalan
ps aux | grep "queue:work"

# Check failed jobs
php artisan queue:failed
```

#### 2. Check Logs
```bash
# Cari error di logs
grep "ERROR" storage/logs/laravel.log | tail -20

# Cari warning tentang notification
grep "Failed to send payment notification" storage/logs/laravel.log
```

#### 3. Check Conversation Context
Pastikan `current_qris_transaction_id` ter-set di conversation:

```sql
SELECT 
    c.id,
    c.current_qris_transaction_id,
    q.order_id,
    q.status
FROM ai_agent_conversations c
LEFT JOIN qris_transactions q ON q.id = c.current_qris_transaction_id
WHERE c.whatsapp_contact_id = [CONTACT_ID]
ORDER BY c.created_at DESC
LIMIT 1;
```

Jika `current_qris_transaction_id` NULL, berarti QRIS tidak di-link ke conversation dengan benar.

#### 4. Check WhatsApp Account
```sql
SELECT 
    wa.id,
    wa.is_active,
    wa.connection_status,
    aa.is_active as ai_agent_active
FROM whatsapp_accounts wa
LEFT JOIN ai_agents aa ON aa.whatsapp_account_id = wa.id
WHERE wa.user_id = [USER_ID];
```

Pastikan:
- `wa.is_active` = 1
- `wa.connection_status` = 'connected'
- `aa.is_active` = 1

### Webhook Tidak Diterima

#### 1. Check Webhook URL
```bash
# Test webhook endpoint
curl -X POST https://yourdomain.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

Harus return 200 OK (meskipun data invalid).

#### 2. Check Midtrans Configuration
- Login ke Midtrans Dashboard
- Settings → Configuration → Notification URL
- Pastikan URL benar dan accessible dari internet

#### 3. Check Firewall
Pastikan server bisa menerima request dari Midtrans IP:
- Allow incoming HTTPS (port 443)
- Whitelist Midtrans IPs jika perlu

### Job Gagal Terus

#### 1. Check Failed Jobs
```bash
php artisan queue:failed
```

#### 2. Retry Failed Jobs
```bash
# Retry specific job
php artisan queue:retry [JOB_ID]

# Retry all failed jobs
php artisan queue:retry all
```

#### 3. Check Job Logs
```bash
grep "ProcessQrisPayment job failed" storage/logs/laravel.log
```

## Environment Variables

Pastikan environment variables sudah di-set:

```env
# Queue
QUEUE_CONNECTION=database

# Midtrans
MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key
MIDTRANS_IS_PRODUCTION=false

# WhatsApp (Meta/Cloud API)
WHATSAPP_CLOUD_API_TOKEN=your-token
WHATSAPP_CLOUD_API_PHONE_NUMBER_ID=your-phone-id
```

## Monitoring

### Success Indicators
✅ Webhook received log
✅ Settlement processed log
✅ Job dispatched log
✅ WhatsApp notification sent log
✅ Payment confirmation sent log
✅ Customer menerima pesan WhatsApp

### Metrics to Monitor
- Webhook response time
- Job processing time
- Notification success rate
- Failed jobs count

## Summary

Sistem sudah **fully automated**:
1. ✅ Webhook handler sudah ada
2. ✅ Job processing sudah ada
3. ✅ WhatsApp notification sudah terintegrasi
4. ✅ Error handling sudah proper
5. ✅ Logging sudah lengkap

**Customer tidak perlu melakukan apapun** - notifikasi akan dikirim otomatis saat pembayaran berhasil! 🎉
