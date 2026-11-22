# Cara Menghubungkan WhatsApp Account ke AI Agent

## 🎯 Masalah

AI Agent tidak bisa mengirim pesan karena tidak ada WhatsApp account yang terhubung.

```
AI AGENT STATUS
   Agent: Qashierwise
   - Status: ✅ ACTIVE
   - WhatsApp Account: ❌ NOT CONNECTED  ← MASALAH INI!
```

## 📋 Prasyarat

Anda harus memiliki:

1. **WhatsApp Business Account** yang sudah disetup di Meta Developer Console
2. **Phone Number ID** dari Meta
3. **Access Token** yang valid
4. **WABA ID** (WhatsApp Business Account ID)

## 🔧 Cara 1: Via Dashboard (Recommended)

### Langkah-langkah:

1. **Login ke Dashboard**
   - Buka: `http://localhost:8000` atau URL production Anda
   - Login dengan akun Anda

2. **Pergi ke WhatsApp Settings**
   - Klik menu "WhatsApp" atau "Settings"
   - Cari opsi "Connect WhatsApp Account"

3. **Klik "Connect WhatsApp Account"**
   - Akan muncul popup dari Meta (Embedded Signup)
   - Login dengan akun Facebook/Meta Anda
   - Pilih WhatsApp Business Account yang ingin dihubungkan
   - Berikan permission yang diminta

4. **Selesai!**
   - Account akan otomatis tersimpan di database
   - AI Agent akan otomatis terhubung ke account ini

## 🔧 Cara 2: Manual via Database (Untuk Testing/Development)

Jika Anda sudah punya credentials dari Meta Developer Console:

### Langkah 1: Dapatkan Credentials dari Meta

1. Buka [Meta Developer Console](https://developers.facebook.com/)
2. Pilih App Anda
3. Pergi ke **WhatsApp → Getting Started**
4. Catat:
   - **Phone Number ID**: Contoh `531033526760565`
   - **Access Token**: Klik "Generate Token" atau gunakan System User Token
   - **WABA ID**: Business Account ID

### Langkah 2: Insert ke Database

```sql
-- Ganti nilai-nilai berikut dengan credentials Anda
INSERT INTO whatsapp_accounts (
    user_id,
    waba_id,
    phone_number_id,
    phone_number,
    display_phone_number,
    access_token,
    is_active,
    created_at,
    updated_at
) VALUES (
    1,                              -- user_id (ID user Anda, cek di tabel users)
    'YOUR_WABA_ID',                 -- Contoh: '123456789012345'
    'YOUR_PHONE_NUMBER_ID',         -- Contoh: '531033526760565'
    '62882003235019',               -- Nomor WhatsApp tanpa + atau spasi
    '+62 882-0032-35019',           -- Format display
    'YOUR_ACCESS_TOKEN',            -- Token dari Meta (panjang, mulai dengan EAA...)
    true,                           -- is_active
    NOW(),
    NOW()
);
```

### Langkah 3: Hubungkan ke AI Agent

```sql
-- Update AI Agent untuk menggunakan WhatsApp account yang baru dibuat
UPDATE ai_agents 
SET whatsapp_account_id = (
    SELECT id 
    FROM whatsapp_accounts 
    WHERE user_id = 1 
    ORDER BY created_at DESC 
    LIMIT 1
)
WHERE id = 1;  -- ID AI Agent Anda
```

### Langkah 4: Verifikasi

```sql
-- Cek apakah sudah terhubung
SELECT 
    a.id as agent_id,
    a.bot_name,
    a.is_active as agent_active,
    w.id as account_id,
    w.phone_number_id,
    w.display_phone_number,
    w.is_active as account_active
FROM ai_agents a
LEFT JOIN whatsapp_accounts w ON a.whatsapp_account_id = w.id;
```

Output yang diharapkan:
```
agent_id | bot_name     | agent_active | account_id | phone_number_id | display_phone_number | account_active
---------|--------------|--------------|------------|-----------------|---------------------|---------------
1        | Qashierwise  | true         | 1          | 531033526760565 | +62 882-0032-35019  | true
```

## 🧪 Testing

### 1. Cek Status

```bash
php check_queue_status.php
```

Harus muncul:
```
✅ AI AGENT STATUS
   Agent: Qashierwise
   - Status: ✅ ACTIVE
   - WhatsApp Account: ✅ ACTIVE  ← HARUS ACTIVE!
   - Phone Number ID: 531033526760565
```

### 2. Test Kirim Pesan

Kirim pesan WhatsApp ke nomor bisnis Anda:
```
Halo
```

### 3. Cek Log

```bash
# Windows PowerShell
Get-Content storage/logs/laravel.log -Wait -Tail 50

# Linux/Mac
tail -f storage/logs/laravel.log
```

Harus muncul:
```
[timestamp] Webhook received
[timestamp] AI Agent message dispatched to queue
[timestamp] ProcessAiAgentMessage job started
[timestamp] AI Agent processing message
[timestamp] LLM API Response
[timestamp] Message sent to WhatsApp
```

### 4. Retry Failed Jobs

Jika sebelumnya ada failed jobs:

```bash
php artisan queue:retry all
```

## ⚠️ Troubleshooting

### Error: "Access token is invalid"

**Penyebab**: Token expired atau salah

**Solusi**:
1. Generate token baru di Meta Developer Console
2. Update di database:
```sql
UPDATE whatsapp_accounts 
SET access_token = 'NEW_TOKEN_HERE'
WHERE id = 1;
```

### Error: "Phone number not found"

**Penyebab**: Phone Number ID salah

**Solusi**:
1. Cek Phone Number ID di Meta Developer Console
2. Update di database:
```sql
UPDATE whatsapp_accounts 
SET phone_number_id = 'CORRECT_PHONE_NUMBER_ID'
WHERE id = 1;
```

### AI Agent masih tidak merespon

**Cek**:
1. WhatsApp account aktif?
   ```sql
   SELECT * FROM whatsapp_accounts WHERE is_active = true;
   ```

2. AI Agent terhubung ke account?
   ```sql
   SELECT * FROM ai_agents WHERE whatsapp_account_id IS NOT NULL;
   ```

3. Queue worker berjalan? (jika `QUEUE_CONNECTION=database`)
   ```bash
   php artisan queue:work --queue=ai-agent
   ```

4. Webhook URL sudah dikonfigurasi di Meta?
   - URL: `https://your-domain.com/api/whatsapp/webhook`
   - Verify Token: Cek di `.env` → `WHATSAPP_WEBHOOK_VERIFY_TOKEN`

## 📚 Referensi

- [Meta WhatsApp Business API Documentation](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Embedded Signup Guide](https://developers.facebook.com/docs/whatsapp/embedded-signup)
- File: `FIX_WEBHOOK_AI_AGENT_FLOW.md` - Penjelasan lengkap alur webhook
- File: `check_queue_status.php` - Script untuk cek status sistem

## 🎉 Selesai!

Setelah WhatsApp account terhubung, sistem akan bekerja:

```
Customer kirim pesan WhatsApp
  ↓
Webhook terima pesan
  ↓
Simpan ke database
  ↓
Dispatch ke queue
  ↓
AI Agent proses pesan
  ↓
Generate response dengan LLM
  ↓
Kirim response ke WhatsApp API
  ↓
Customer terima response ✅
```
