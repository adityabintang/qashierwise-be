# WhatsApp Sandbox vs Production

## Sandbox Account (Testing)

### Karakteristik:
- ✅ **Gratis** untuk testing
- ✅ **Tidak perlu Business Verification**
- ❌ **Hanya bisa kirim ke nomor test yang di-whitelist**
- ❌ **Dibatasi per negara** (Error 130497)
- ❌ **Rate limit rendah**
- ✅ **Cocok untuk development**

### Cara Menambahkan Nomor Test:
1. Buka [Meta Developer Console](https://developers.facebook.com/)
2. Pilih WhatsApp App Anda
3. Masuk ke **WhatsApp > API Setup**
4. Klik **"Add Phone Number"** di bagian **Test Numbers**
5. Masukkan nomor WhatsApp yang akan digunakan untuk testing
6. Verifikasi dengan OTP yang dikirim ke nomor tersebut

### Batasan Sandbox:
```
Error 130497: Business account is restricted from messaging users in this country
```
Ini normal untuk sandbox - hanya bisa kirim ke nomor yang sudah di-whitelist.

---

## Production Account (Live)

### Karakteristik:
- ✅ **Bisa kirim ke semua nomor**
- ✅ **Tidak ada batasan negara**
- ✅ **Rate limit tinggi**
- ✅ **Cocok untuk production**
- ❌ **Perlu Business Verification**
- ❌ **Berbayar** (conversation-based pricing)

### Cara Upgrade ke Production:

#### 1. Business Verification
1. Buka [Meta Business Manager](https://business.facebook.com/)
2. Pilih Business Account Anda
3. Masuk ke **Business Settings > Security Center**
4. Klik **"Start Verification"**
5. Upload dokumen:
   - KTP/Passport pemilik bisnis
   - NPWP/NIB (untuk Indonesia)
   - Dokumen legalitas bisnis
6. Tunggu approval (1-5 hari kerja)

#### 2. WhatsApp Business Account Verification
1. Setelah business verified, buka WhatsApp Manager
2. Pilih WhatsApp Business Account
3. Klik **"Verify Business"**
4. Isi informasi bisnis:
   - Nama bisnis
   - Alamat
   - Website
   - Kategori bisnis
5. Submit untuk review

#### 3. Display Name Verification
1. Di WhatsApp Manager, pilih Phone Number
2. Klik **"Display Name"**
3. Request verification untuk display name
4. Tunggu approval (1-3 hari)

#### 4. Upgrade Tier
WhatsApp memiliki tier messaging:
- **Tier 1**: 1,000 conversations/day (default)
- **Tier 2**: 10,000 conversations/day
- **Tier 3**: 100,000 conversations/day
- **Tier 4**: Unlimited

Tier akan otomatis naik setelah:
- Phone number verified
- Quality rating bagus
- Tidak ada violation

---

## Perbedaan Template

### Sandbox:
- Template hanya untuk testing
- Tidak perlu approval ketat
- Bisa pakai template default (hello_world)

### Production:
- Template harus di-approve Meta
- Review time: 1-24 jam
- Harus follow template guidelines
- Quality score mempengaruhi delivery

---

## Pricing

### Sandbox:
- **Gratis** untuk testing
- Unlimited messages ke nomor test

### Production:
- **Conversation-based pricing**
- Biaya per conversation (24 jam window)
- Harga berbeda per negara
- Indonesia: ~$0.05 - $0.10 per conversation

**Conversation Types:**
1. **User-initiated**: Customer mengirim pesan pertama (lebih murah)
2. **Business-initiated**: Bisnis mengirim template message (lebih mahal)

---

## Rekomendasi

### Untuk Development:
✅ Gunakan **Sandbox**
- Tambahkan nomor developer ke test numbers
- Test semua fitur tanpa biaya
- Tidak perlu verifikasi

### Untuk Production:
✅ Upgrade ke **Production**
- Complete business verification
- Verify display name
- Monitor quality rating
- Setup billing

---

## Troubleshooting

### Error 130497 di Sandbox:
**Normal** - tambahkan nomor ke test numbers

### Error 130497 di Production:
**Tidak normal** - cek:
1. Business verification status
2. Phone number verification
3. Quality rating
4. Compliance issues

### Template Not Found (132001):
1. Cek template di Meta Business Manager
2. Pastikan template approved
3. Sync template ke database
4. Pastikan language code benar (en_US, id, dll)

---

## Security: Row Level Security (RLS)

**Implementasi RLS** untuk mencegah data leak:

```php
// WhatsAppTemplate.php
protected static function booted(): void
{
    static::addGlobalScope('userTemplates', function (Builder $builder) {
        if (auth()->check()) {
            $userId = auth()->id();
            $builder->whereHas('account', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            });
        }
    });
}
```

**Models dengan RLS:**
- ✅ WhatsAppAccount
- ✅ WhatsAppContact
- ✅ WhatsAppMessage
- ✅ WhatsAppTemplate

**Benefit:**
- User hanya bisa lihat data mereka sendiri
- Mencegah data leak antar user
- Automatic filtering di semua query
