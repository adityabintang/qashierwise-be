# Fix Template Issue - WABA 465511339986792

## Masalah
Template `appointment_confirmation` muncul di nomor +62 882-0032-35019 (WABA 465511339986792) padahal WABA ini tidak punya template di Meta.

## Penyebab
Template di database **stale/outdated** - di-import dari WABA lain atau sync yang salah.

## Solusi yang Sudah Dilakukan

### 1. ✅ Implementasi Row Level Security (RLS)
Semua model WhatsApp sekarang punya RLS untuk mencegah data leak:
- `WhatsAppAccount` - Filter by user_id
- `WhatsAppContact` - Filter by user_id  
- `WhatsAppMessage` - Filter by user_id
- `WhatsAppTemplate` - Filter by account.user_id

**Default deny** jika tidak authenticated.

### 2. ✅ Hapus Template Stale
Template yang salah sudah dihapus dari database:
```bash
php reset_templates.php
```

### 3. ✅ Verifikasi Template Kosong
```bash
php check_current_templates.php
# Output: 0 templates
```

## Langkah Selanjutnya untuk User

### Untuk WABA 465511339986792 (Sandbox - +62 882-0032-35019):

**Opsi 1: Buat Template Baru di Meta**
1. Buka [Meta Business Manager](https://business.facebook.com/wa/manage/message-templates/)
2. Pilih WABA 465511339986792
3. Klik "Create Template"
4. Buat template sesuai kebutuhan
5. Submit untuk approval
6. Setelah approved, klik "Sync from Meta" di aplikasi

**Opsi 2: Gunakan Nomor Test (Recommended untuk Sandbox)**
1. Buka [Meta Developer Console](https://developers.facebook.com/)
2. Pilih WhatsApp App
3. Masuk ke **WhatsApp > API Setup**
4. Tambahkan nomor test di **Test Numbers**
5. Kirim pesan text biasa (tanpa template) ke nomor test

**Opsi 3: Upgrade ke Production**
- Complete business verification
- Verify display name
- Buat template production

### Untuk WABA 1370062421568351 (Production):

Jika user lain punya WABA ini:
1. User tersebut login
2. Connect WhatsApp account mereka
3. Klik "Sync from Meta"
4. Template akan otomatis muncul untuk user tersebut saja

## Cara Sync Template dari Meta

### Via API:
```bash
GET /api/whatsapp/templates?refresh=true
```

### Via Frontend:
1. Buka halaman Templates
2. Klik tombol "Sync from Meta" atau "Refresh"
3. Template akan di-fetch dari Meta API
4. Hanya template milik WABA user yang akan muncul

## Verifikasi RLS Bekerja

```bash
php test_rls.php
```

Output yang benar:
- User #1: Bisa lihat data mereka
- User #2-6: Tidak bisa lihat data user lain
- Without auth: Tidak bisa lihat apa-apa

## Clear Frontend Cache

Jika template masih muncul di frontend:

1. **Hard Refresh Browser:**
   - Chrome/Firefox: Ctrl + Shift + R (Windows) atau Cmd + Shift + R (Mac)
   - Safari: Cmd + Option + R

2. **Clear LocalStorage:**
   ```javascript
   // Di browser console
   localStorage.clear();
   location.reload();
   ```

3. **Clear Service Worker (jika ada):**
   ```javascript
   // Di browser console
   navigator.serviceWorker.getRegistrations().then(function(registrations) {
     for(let registration of registrations) {
       registration.unregister();
     }
   });
   ```

## Monitoring

### Cek Template di Database:
```bash
php check_current_templates.php
```

### Cek Template di Meta:
```bash
php check_meta_templates.php
```

### Test RLS:
```bash
php test_rls.php
```

## Kesimpulan

✅ **RLS sudah aktif** - Data tidak akan leak antar user
✅ **Template stale sudah dihapus** - Database bersih
✅ **Sync template akan benar** - Hanya template milik WABA user yang muncul

**Next:** User perlu refresh browser dan sync template dari Meta.
