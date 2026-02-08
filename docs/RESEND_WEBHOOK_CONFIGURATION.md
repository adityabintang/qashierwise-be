# Resend Webhook Configuration

## Endpoint URL

Endpoint webhook Resend yang harus didaftarkan di dashboard Resend:

```
POST https://your-domain.com/api/webhooks/resend
```

## Setup di Dashboard Resend

1. Login ke [Resend Dashboard](https://resend.com/webhooks)
2. Buka halaman **Webhooks**
3. Klik **Add Endpoint**
4. Masukkan endpoint URL: `https://your-domain.com/api/webhooks/resend`
5. Pilih event yang ingin Anda monitor:
   - `email.sent` - Email berhasil dikirim
   - `email.delivered` - Email berhasil diterima
   - `email.delivery_delayed` - Pengiriman email tertunda
   - `email.bounced` - Email gagal terkirim (bounced)
   - `email.complained` - Email dilaporkan sebagai spam
   - `email.opened` - Email dibuka oleh penerima
   - `email.clicked` - Link dalam email diklik
6. Copy **Signing Secret** yang diberikan
7. Tambahkan ke file `.env`:

```env
RESEND_WEBHOOK_SECRET=whsec_your_signing_secret_here
```

## Keamanan

Webhook ini dilindungi dengan:

1. **Signature Verification** - Menggunakan Svix signature untuk memverifikasi request berasal dari Resend
2. **Rate Limiting** - Maksimal 60 request per menit
3. **Payload Validation** - Memvalidasi struktur payload yang diterima

## Event Types

### email.sent
Email telah dikirim ke server email penerima.

### email.delivered
Email berhasil diterima oleh server email penerima.

### email.delivery_delayed
Pengiriman email mengalami penundaan (akan dicoba ulang).

### email.bounced
Email gagal terkirim secara permanen (hard bounce) atau sementara (soft bounce).

**Fields:**
- `bounce_type`: `hard` atau `soft`

### email.complained
Email dilaporkan sebagai spam oleh penerima.

### email.opened
Email dibuka oleh penerima (memerlukan tracking pixel).

### email.clicked
Link dalam email diklik oleh penerima (memerlukan click tracking).

**Fields:**
- `link`: URL yang diklik

## Testing

Jalankan test untuk memastikan webhook berfungsi:

```bash
php artisan test tests/Feature/Api/ResendWebhookControllerTest.php
```

## Logging

Semua webhook event akan dicatat di log Laravel:

- `storage/logs/laravel.log` (local/development)
- Log channel yang dikonfigurasi (production)

**Event types logged:**
- `webhook.received` - Webhook diterima
- `webhook.validation_failed` - Validasi gagal
- Email events (delivered, bounced, complained, opened, clicked)

## Implementation Notes

Controller: [app/Http/Controllers/Api/ResendWebhookController.php](../app/Http/Controllers/Api/ResendWebhookController.php)

Route: [routes/api.php](../routes/api.php#L69)

Config: [config/services.php](../config/services.php#L21)

## Customization

Untuk menambahkan logic custom saat event tertentu terjadi, edit method di `ResendWebhookController`:

- `handleEmailSent()` - Saat email dikirim
- `handleEmailDelivered()` - Saat email diterima
- `handleEmailBounced()` - Saat email bounced
- `handleEmailComplained()` - Saat email dilaporkan spam
- `handleEmailOpened()` - Saat email dibuka
- `handleEmailClicked()` - Saat link diklik

Contoh:

```php
private function handleEmailBounced(array $data): void
{
    Log::error('Email bounced', [
        'email_id' => $data['email_id'] ?? null,
        'to' => $data['to'] ?? null,
        'bounce_type' => $data['bounce_type'] ?? null,
    ]);

    // Custom logic: Mark email as invalid in database
    if (isset($data['to']) && $data['bounce_type'] === 'hard') {
        // Disable future emails to this address
        EmailBlacklist::create([
            'email' => $data['to'],
            'reason' => 'hard_bounce',
        ]);
    }
}
```

## Troubleshooting

### Webhook tidak menerima event

1. Pastikan URL webhook benar dan bisa diakses dari internet
2. Cek firewall tidak memblokir request dari Resend
3. Pastikan `RESEND_WEBHOOK_SECRET` sudah diset di `.env`

### Signature validation gagal

1. Pastikan `RESEND_WEBHOOK_SECRET` sama dengan yang di dashboard Resend
2. Jangan tambahkan/hapus karakter dari secret (copy paste langsung)
3. Pastikan tidak ada middleware yang memodifikasi request body

### Rate limiting

Jika webhook sering terkena rate limit (429), tingkatkan limit di route:

```php
Route::post('/webhooks/resend', [ResendWebhookController::class, 'handleNotification'])
    ->middleware('throttle:120,1'); // 120 requests per minute
```
