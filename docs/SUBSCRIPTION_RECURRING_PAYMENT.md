# Subscription Recurring Payment Guide

## Overview

QashierWise menggunakan Midtrans Subscription untuk mengelola pembayaran berlangganan bulanan. Sistem ini secara otomatis menagih pelanggan setiap bulan sesuai dengan plan yang dipilih.

## Cara Kerja Recurring Payment

### 1. Pembayaran Pertama (Initial Payment)
- User memilih plan (Standard Rp 99,000 atau Pro Rp 199,000)
- User melakukan pembayaran pertama melalui Midtrans Snap
- Setelah pembayaran berhasil, subscription dibuat dengan status `active`
- Period start dan period end dicatat (contoh: 24 Jan 2026 - 24 Feb 2026)

### 2. Pembayaran Berulang (Recurring Payment)
- Midtrans secara otomatis menagih pelanggan setiap bulan pada tanggal yang sama
- Jika pembayaran berhasil:
  - Status subscription tetap `active`
  - Period end diperbarui ke bulan berikutnya
  - Webhook notification dikirim ke aplikasi
- Jika pembayaran gagal:
  - Midtrans akan mencoba beberapa kali (retry mechanism)
  - Jika tetap gagal, status subscription menjadi `expired`
  - User akan menerima notifikasi

### 3. Reminder Pembayaran
Midtrans mengirimkan reminder otomatis:
- **7 hari sebelum** tanggal pembayaran
- **3 hari sebelum** tanggal pembayaran
- **1 hari sebelum** tanggal pembayaran
- **Pada hari** pembayaran

Reminder dikirim melalui:
- Email (jika dikonfigurasi)
- SMS (jika dikonfigurasi)
- WhatsApp (jika dikonfigurasi)

## Contoh Skenario

### Skenario 1: Pembayaran Lancar
```
24 Jan 2026: User subscribe Standard Plan (Rp 99,000)
- Status: active
- Period: 24 Jan - 24 Feb 2026

17 Feb 2026: Reminder 7 hari sebelum pembayaran
21 Feb 2026: Reminder 3 hari sebelum pembayaran
23 Feb 2026: Reminder 1 hari sebelum pembayaran

24 Feb 2026: Midtrans auto-charge Rp 99,000
- Status: active (tetap)
- Period: 24 Feb - 24 Mar 2026

24 Mar 2026: Midtrans auto-charge Rp 99,000
- Status: active (tetap)
- Period: 24 Mar - 24 Apr 2026
```

### Skenario 2: Pembayaran Gagal
```
24 Jan 2026: User subscribe Standard Plan (Rp 99,000)
- Status: active
- Period: 24 Jan - 24 Feb 2026

24 Feb 2026: Midtrans auto-charge gagal (saldo tidak cukup)
- Retry 1: Gagal
- Retry 2: Gagal
- Retry 3: Gagal

26 Feb 2026: Setelah semua retry gagal
- Status: expired
- User menerima notifikasi
- Akses ke fitur premium dihentikan
```

### Skenario 3: User Cancel Subscription
```
24 Jan 2026: User subscribe Standard Plan (Rp 99,000)
- Status: active
- Period: 24 Jan - 24 Feb 2026

10 Feb 2026: User cancel subscription
- Status: cancelled
- Period: 24 Jan - 24 Feb 2026 (tetap)
- Akses tetap aktif sampai 24 Feb 2026

24 Feb 2026: Period berakhir
- Status: expired
- Tidak ada auto-charge
- Akses ke fitur premium dihentikan
```

## Konfigurasi Midtrans

### Environment Variables
```env
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true
```

### Webhook Configuration
Webhook URL harus dikonfigurasi di Midtrans Dashboard:
```
https://yourdomain.com/api/webhooks/midtrans/subscription
```

## Fitur Subscription Management

### 1. View Subscription Status
User dapat melihat status subscription di halaman "Manage Subscription":
- Plan name (Standard/Pro)
- Status (active/cancelled/expired)
- Current period (start - end date)
- Next billing date
- Amount per month

### 2. Cancel Subscription
User dapat cancel subscription kapan saja:
- Akses tetap aktif sampai akhir periode
- Tidak ada auto-charge di periode berikutnya
- User dapat re-subscribe kapan saja

### 3. Billing History
User dapat melihat riwayat pembayaran:
- Tanggal pembayaran
- Amount
- Status (settlement/pending/failed)
- Payment method

## API Endpoints

### Get Subscription Status
```http
GET /api/subscription/status
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "subscription": {
      "status": "active",
      "plan_name": "standard",
      "amount": 99000,
      "period_start": "2026-01-24T00:00:00Z",
      "period_end": "2026-02-24T00:00:00Z",
      "cancelled_at": null
    }
  }
}
```

### Get Billing History
```http
GET /api/subscription/billing-history
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": "order-123",
        "plan_name": "Standard Plan",
        "gross_amount": 99000,
        "status": "settlement",
        "transaction_time": "2026-01-24T10:30:00Z"
      }
    ]
  }
}
```

### Cancel Subscription
```http
POST /subscription/cancel
Authorization: Bearer {token}

Response:
{
  "success": true,
  "message": "Subscription cancelled successfully"
}
```

## Webhook Events

### Payment Success
```json
{
  "transaction_status": "settlement",
  "subscription_id": "sub_123",
  "order_id": "order_123",
  "gross_amount": "99000",
  "transaction_time": "2026-01-24 10:30:00"
}
```

### Payment Failed
```json
{
  "transaction_status": "deny",
  "subscription_id": "sub_123",
  "order_id": "order_123",
  "gross_amount": "99000",
  "transaction_time": "2026-02-24 10:30:00"
}
```

## Testing

### Test Subscription Flow
1. Login ke aplikasi
2. Pilih plan di halaman pricing
3. Lakukan pembayaran dengan test card:
   - Card Number: 4811 1111 1111 1114
   - CVV: 123
   - Expiry: 01/27
4. Verifikasi subscription status di "Manage Subscription"
5. Test cancel subscription

### Test Webhook
```bash
# Simulate payment success webhook
curl -X POST https://yourdomain.com/api/webhooks/midtrans/subscription \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_status": "settlement",
    "subscription_id": "sub_123",
    "order_id": "order_123",
    "gross_amount": "99000"
  }'
```

## Troubleshooting

### Subscription tidak aktif setelah pembayaran
1. Cek webhook logs di Midtrans Dashboard
2. Cek application logs: `storage/logs/laravel.log`
3. Verifikasi webhook URL sudah dikonfigurasi dengan benar
4. Test webhook manually

### Pembayaran berulang tidak berjalan
1. Cek status subscription di Midtrans Dashboard
2. Verifikasi payment method masih valid
3. Cek saldo/limit kartu kredit
4. Review retry logs di Midtrans

### User tidak menerima reminder
1. Verifikasi email/phone number di Midtrans
2. Cek notification settings di Midtrans Dashboard
3. Review notification logs

## Best Practices

1. **Selalu handle webhook dengan idempotent**
   - Cek apakah payment sudah diproses sebelumnya
   - Gunakan transaction_id sebagai unique identifier

2. **Berikan grace period**
   - Jangan langsung disable akses saat pembayaran gagal
   - Berikan waktu 3-7 hari untuk retry

3. **Komunikasi yang jelas**
   - Kirim email reminder sebelum pembayaran
   - Notifikasi saat pembayaran berhasil/gagal
   - Jelaskan proses cancel subscription

4. **Monitoring**
   - Monitor failed payments
   - Track subscription churn rate
   - Analyze cancellation reasons

## Support

Untuk pertanyaan lebih lanjut:
- Email: support@qashierwise.com
- Documentation: https://docs.qashierwise.com
- Midtrans Docs: https://docs.midtrans.com
