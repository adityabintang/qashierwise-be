# Fix Midtrans Subscription - Redirect & Plan Update

## Masalah yang Ditemukan

### 1. Redirect ke example.com setelah pembayaran
**Penyebab:** Midtrans Snap tidak dikonfigurasi dengan callback URL yang benar.

**Solusi:** Menambahkan parameter `callbacks` ke payload Snap API dengan URL yang benar:
- `finish`: `/subscription/success` - Redirect setelah pembayaran selesai (sukses/gagal/pending)
- `error`: `/subscription/error` - Redirect jika terjadi error
- `pending`: `/subscription/manage` - Redirect jika pembayaran pending

### 2. Plan tidak berubah di dashboard setelah pembayaran sukses
**Penyebab:** Webhook tidak membuat subscription record di database setelah pembayaran Snap berhasil.

**Solusi:** Menambahkan logika untuk:
1. Mendeteksi pembayaran subscription dari `custom_field2 = 'subscription'`
2. Mengekstrak `user_id` dan `plan_id` dari custom fields
3. Membuat atau update subscription record di database dengan status `active`

## Perubahan yang Dilakukan

### 1. MidtransSnapService.php
Menambahkan callbacks ke payload Snap:
```php
'callbacks' => [
    'finish' => route('subscription.success'),
    'error' => route('subscription.error'),
    'pending' => route('subscription.manage'),
],
```

### 2. MidtransWebhookController.php

#### a. Update `processSubscriptionWebhook()`
Menambahkan deteksi pembayaran Snap subscription:
```php
$paymentType = $payload['custom_field2'] ?? null;

// Check if this is a Snap subscription payment
if ($paymentType === 'subscription' && $transactionStatus !== null) {
    $this->handlePaymentNotification($payload);
}
```

#### b. Update `processSubscriptionPaymentSuccess()`
Menambahkan logika untuk membuat subscription:
```php
// Extract user ID and plan ID from custom fields
$userId = $payload['custom_field3'] ?? null;
$planId = $payload['custom_field1'] ?? null;
$paymentType = $payload['custom_field2'] ?? null;

// Create or update subscription
if ($paymentType === 'subscription' && $userId && $planId) {
    // Create subscription record
}
```

## Konfigurasi Webhook di Midtrans Dashboard

### Langkah-langkah:

1. **Login ke Midtrans Dashboard**
   - Sandbox: https://dashboard.sandbox.midtrans.com
   - Production: https://dashboard.midtrans.com

2. **Buka Settings → Configuration**

3. **Set Payment Notification URL**
   ```
   Development: http://127.0.0.1:8000/api/webhooks/midtrans/subscription
   Production: https://yourdomain.com/api/webhooks/midtrans/subscription
   ```

4. **Set HTTP Notification Method**
   - Pilih: `POST`

5. **Enable Notifications untuk:**
   - ✅ Payment Success
   - ✅ Payment Pending
   - ✅ Payment Failure
   - ✅ Payment Expire

6. **Save Configuration**

## Testing

### 1. Test Redirect URL
1. Buat checkout subscription baru
2. Setelah pembayaran, pastikan redirect ke:
   - Sukses: `/subscription/success`
   - Gagal: `/subscription/error`
   - Pending: `/subscription/manage`

### 2. Test Webhook & Plan Update
1. Lakukan pembayaran test di Midtrans
2. Cek log Laravel untuk memastikan webhook diterima:
   ```bash
   tail -f storage/logs/laravel.log | grep "subscription"
   ```
3. Cek database untuk memastikan subscription dibuat:
   ```sql
   SELECT * FROM subscriptions WHERE user_id = YOUR_USER_ID ORDER BY created_at DESC LIMIT 1;
   ```
4. Refresh dashboard dan pastikan plan sudah berubah

### 3. Test dengan Midtrans Simulator
Gunakan Midtrans Payment Simulator untuk test berbagai skenario:
- Success: Gunakan kartu test `4811 1111 1111 1114`
- Failure: Gunakan kartu test `4911 1111 1111 1113`

## Troubleshooting

### Webhook tidak diterima
1. Pastikan webhook URL sudah dikonfigurasi di Midtrans Dashboard
2. Cek firewall/security group untuk memastikan port terbuka
3. Untuk development lokal, gunakan ngrok atau expose.dev:
   ```bash
   ngrok http 8000
   ```
   Kemudian set webhook URL ke: `https://your-ngrok-url.ngrok.io/api/webhooks/midtrans/subscription`

### Plan tidak update setelah webhook
1. Cek log untuk error:
   ```bash
   tail -f storage/logs/laravel.log | grep "ERROR"
   ```
2. Pastikan custom fields terkirim dengan benar:
   - `custom_field1` = plan_id (standard/pro)
   - `custom_field2` = 'subscription'
   - `custom_field3` = user_id

### Signature validation failed
1. Pastikan `MIDTRANS_SERVER_KEY` di `.env` sama dengan di Midtrans Dashboard
2. Cek log untuk melihat signature yang diterima vs expected

## Environment Variables

Pastikan environment variables berikut sudah dikonfigurasi:

```env
# Midtrans Configuration
MIDTRANS_SERVER_KEY=Mid-server-xxxxx
MIDTRANS_CLIENT_KEY=Mid-client-xxxxx
MIDTRANS_MERCHANT_ID=Gxxxxxx
MIDTRANS_IS_PRODUCTION=false

# Subscription URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
```

## Monitoring

### Log Events yang Penting:
1. `webhook.received` - Webhook diterima dari Midtrans
2. `webhook.processed` - Webhook berhasil diproses
3. `subscription.created` - Subscription baru dibuat
4. `subscription.updated` - Subscription di-update

### Query untuk Monitoring:
```sql
-- Cek subscription terbaru
SELECT u.email, s.plan_name, s.status, s.created_at, s.metadata
FROM subscriptions s
JOIN users u ON s.user_id = u.id
ORDER BY s.created_at DESC
LIMIT 10;

-- Cek subscription berdasarkan order_id
SELECT s.*, u.email
FROM subscriptions s
JOIN users u ON s.user_id = u.id
WHERE s.metadata::text LIKE '%SUB-1-1769241485-fa9fcfa1%';
```

## Next Steps

1. ✅ Fix redirect URL - DONE
2. ✅ Fix subscription creation from webhook - DONE
3. ⏳ Configure webhook URL di Midtrans Dashboard - PENDING (Manual)
4. ⏳ Test end-to-end flow - PENDING
5. ⏳ Deploy to production - PENDING

## Notes

- Webhook harus bisa diakses dari internet (tidak bisa localhost tanpa tunnel)
- Midtrans akan retry webhook hingga 5x jika gagal
- Webhook signature harus divalidasi untuk keamanan
- Custom fields digunakan untuk menyimpan metadata subscription
