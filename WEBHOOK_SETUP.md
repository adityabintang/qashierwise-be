# Webhook Setup untuk Development

## Masalah
Polar.sh tidak bisa kirim webhook ke `http://127.0.0.1:8000` karena itu local machine.

## Solusi: Gunakan Ngrok

### 1. Install Ngrok
Download dari: https://ngrok.com/download

### 2. Jalankan Laravel Server
```bash
php artisan serve
```

### 3. Expose dengan Ngrok
Di terminal baru:
```bash
ngrok http 8000
```

Ngrok akan memberikan public URL seperti:
```
https://abc123.ngrok.io -> http://localhost:8000
```

### 4. Update Webhook URL di Polar.sh Sandbox
1. Buka https://sandbox.polar.sh
2. Pergi ke **Settings** → **Webhooks**
3. Edit webhook URL menjadi: `https://abc123.ngrok.io/api/webhooks/polar`
4. Save

### 5. Test Checkout
1. Buka aplikasi di browser: `http://127.0.0.1:8000`
2. Login dan pilih plan
3. Selesaikan checkout di Polar.sh
4. Webhook akan dikirim ke ngrok URL
5. Subscription status akan update otomatis

## Alternatif: Test Manual

Jika tidak mau setup ngrok, test webhook secara manual:

```bash
php test_webhook.php
```

Edit `test_webhook.php` untuk ganti:
- `user_id` dengan ID user yang mau di-test
- `plan_id` dengan 'standard' atau 'pro'

## Catatan untuk Production

Di production, webhook URL sudah benar:
```
https://api.qashierwise.com/api/webhooks/polar
```

Pastikan di production `.env`:
```env
APP_URL=https://qashierwise.com
API_URL=https://api.qashierwise.com
POLAR_SANDBOX=false
```
