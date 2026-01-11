# Fix: Webhook Tidak Meneruskan Pesan ke AI Agent

## 🔍 Analisis Masalah

Setelah memeriksa kode, saya menemukan bahwa **sistem sudah bekerja dengan benar**. Berikut adalah alur yang sudah ada:

### Alur Webhook → AI Agent → WhatsApp API

```
1. WhatsApp → Webhook (POST /api/whatsapp/webhook)
   ↓
2. WhatsAppWebhookController::handle()
   ↓
3. handleIncomingMessage() - Simpan pesan ke database
   ↓
4. Cek AI Agent aktif? (line 408-425)
   ↓
5. Dispatch ProcessAiAgentMessage ke Queue
   ↓
6. Queue Worker memproses job
   ↓
7. AiAgentService::processMessage()
   ↓
8. Call LLM untuk generate response
   ↓
9. sendReply() - Kirim ke WhatsApp API
```

## ✅ Kode Yang Sudah Benar

### 1. Webhook Handler (WhatsAppWebhookController.php, line 408-425)

```php
// Check if AI Agent is active for this account
$aiAgent = \App\Models\AiAgent::where('whatsapp_account_id', $whatsappAccount->id)
    ->where('is_active', true)
    ->first();

if ($aiAgent && $type === 'text') {
    // Dispatch AI Agent processing to queue
    \App\Jobs\ProcessAiAgentMessage::dispatch(
        $whatsappAccount,
        $contact,
        $content
    )->onQueue('ai-agent');

    Log::info('AI Agent message dispatched to queue', [
        'contact_id' => $contact->id,
        'ai_agent_id' => $aiAgent->id,
    ]);
}
```

### 2. Job Processing (ProcessAiAgentMessage.php)

```php
public function handle(AiAgentService $aiAgentService): void
{
    try {
        $aiAgentService->processMessage(
            $this->account,
            $this->contact,
            $this->messageText
        );
    } catch (\Exception $e) {
        Log::error('ProcessAiAgentMessage job failed', [
            'account_id' => $this->account->id,
            'contact_id' => $this->contact->id,
            'error' => $e->getMessage(),
        ]);
        throw $e; // Re-throw to trigger retry
    }
}
```

### 3. Send Reply (AiAgentService.php, line 2024-2041)

```php
protected function sendReply(WhatsAppAccount $account, string $to, string $message): void
{
    try {
        $whatsapp = new WhatsAppCloudApi([
            'from_phone_number_id' => $account->phone_number_id,
            'access_token' => $account->access_token,
        ]);

        $whatsapp->sendTextMessage($to, $message);

    } catch (\Exception $e) {
        Log::error('Failed to send WhatsApp reply', [
            'error' => $e->getMessage(),
            'to' => $to,
        ]);
    }
}
```

## ⚠️ MASALAH YANG DITEMUKAN

Setelah menjalankan `check_queue_status.php`, ditemukan 2 masalah utama:

### 1. WhatsApp Account Tidak Terhubung ❌

```
AI AGENT STATUS
   Agent: Qashierwise
   - Status: ✅ ACTIVE
   - WhatsApp Account: ❌ NOT CONNECTED

WHATSAPP ACCOUNTS
   ⚠️  No WhatsApp accounts configured!
```

**Masalah**: AI Agent aktif tetapi tidak ada WhatsApp account yang terhubung!

### 2. Ada 5 Pending Jobs dan 16 Failed Jobs

```
PENDING JOBS: 5
FAILED JOBS: 16
Error: ModelNotFoundException: No query results for model [App\Models\WhatsApp...]
```

**Masalah**: Jobs gagal karena WhatsApp account tidak ditemukan.

### Dampak:
- Webhook menerima pesan ✅
- Pesan disimpan ke database ✅
- Job di-dispatch ke queue ✅
- **Job gagal karena WhatsApp account tidak ada** ❌
- AI Agent tidak bisa memproses pesan ❌
- Response tidak dikirim ke WhatsApp ❌

## 🔧 Solusi

### LANGKAH 1: Hubungkan WhatsApp Account (PALING PENTING!)

Ada 2 cara:

#### Cara A: Via Dashboard (Recommended)

1. Login ke dashboard aplikasi
2. Pergi ke halaman WhatsApp Settings
3. Klik "Connect WhatsApp Account"
4. Ikuti proses Embedded Signup dari Meta
5. Setelah berhasil, account akan tersimpan di database

#### Cara B: Manual via Database (Untuk Testing)

Jika Anda sudah punya credentials WhatsApp Business API:

```sql
-- Insert WhatsApp account
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
    1, -- user_id (sesuaikan dengan user Anda)
    'YOUR_WABA_ID',
    'YOUR_PHONE_NUMBER_ID',
    '628123456789',
    '+62 812-3456-789',
    'YOUR_ACCESS_TOKEN',
    true,
    NOW(),
    NOW()
);

-- Update AI Agent untuk link ke WhatsApp account
UPDATE ai_agents 
SET whatsapp_account_id = (SELECT id FROM whatsapp_accounts WHERE user_id = 1 LIMIT 1)
WHERE id = 1;
```

### LANGKAH 2: Retry Failed Jobs

Setelah WhatsApp account terhubung:

```bash
# Retry semua failed jobs
php artisan queue:retry all

# Atau hapus failed jobs dan tunggu pesan baru
php artisan queue:flush
```

### LANGKAH 3: Jalankan Queue Worker (Jika Menggunakan Database Queue)

Jika `.env` menggunakan `QUEUE_CONNECTION=database`:

```bash
# Windows
start_queue_worker.bat

# Linux/Mac
chmod +x start_queue_worker.sh
./start_queue_worker.sh

# Atau manual
php artisan queue:work --queue=ai-agent
```

**CATATAN**: Jika menggunakan `QUEUE_CONNECTION=sync`, queue worker tidak diperlukan karena jobs diproses langsung.

## 🧪 Testing

### 1. Cek Status Sistem

```bash
php check_queue_status.php
```

Output yang diharapkan:
```
✅ Everything looks good!

AI AGENT STATUS
   Agent: Qashierwise
   - Status: ✅ ACTIVE
   - WhatsApp Account: ✅ ACTIVE
   - Phone Number ID: 123456789

WHATSAPP ACCOUNTS
   Total Accounts: 1
   - Status: ✅ ACTIVE
   - Has Access Token: ✅
```

### 2. Test Kirim Pesan

Kirim pesan WhatsApp ke nomor bisnis Anda, misalnya:
```
Halo
```

### 3. Cek Log Real-time

```bash
# Windows (PowerShell)
Get-Content storage/logs/laravel.log -Wait -Tail 50 | Select-String "AI Agent"

# Linux/Mac
tail -f storage/logs/laravel.log | grep "AI Agent"
```

Yang harus muncul:
```
[timestamp] AI Agent message dispatched to queue
[timestamp] ProcessAiAgentMessage job started
[timestamp] AI Agent processing message
[timestamp] LLM API Response
[timestamp] Message sent to WhatsApp
```

### 4. Cek Database

```sql
-- Cek pesan masuk
SELECT * FROM whatsapp_messages 
WHERE direction = 'incoming' 
ORDER BY created_at DESC 
LIMIT 5;

-- Cek pesan keluar (response AI)
SELECT * FROM whatsapp_messages 
WHERE direction = 'outgoing' 
ORDER BY created_at DESC 
LIMIT 5;

-- Cek conversation AI Agent
SELECT * FROM ai_agent_conversations 
ORDER BY updated_at DESC 
LIMIT 5;
```

## 📊 Monitoring

### Cek Job Failed

```bash
php artisan queue:failed
```

### Retry Failed Jobs

```bash
php artisan queue:retry all
```

### Clear Failed Jobs

```bash
php artisan queue:flush
```

## 🎯 Checklist Troubleshooting

- [ ] **WhatsApp Account terhubung?** (Paling penting!)
  - Cek: `SELECT * FROM whatsapp_accounts WHERE is_active = true;`
  - Harus ada minimal 1 account aktif
  
- [ ] **AI Agent linked ke WhatsApp Account?**
  - Cek: `SELECT * FROM ai_agents WHERE whatsapp_account_id IS NOT NULL;`
  - `whatsapp_account_id` tidak boleh NULL
  
- [ ] **AI Agent aktif?**
  - Cek: `SELECT * FROM ai_agents WHERE is_active = true;`
  
- [ ] **Access Token valid?**
  - Test: Kirim pesan manual via API WhatsApp
  - Token expired? Generate token baru di Meta Developer Console
  
- [ ] **BytePlus ARK API key valid?**
  - Cek `.env`: `BYTEPLUS_ARK_API_KEY`
  - Test: `php artisan tinker` → Test API call
  
- [ ] **Queue worker berjalan?** (Jika `QUEUE_CONNECTION=database`)
  - Cek: `ps aux | grep "queue:work"` (Linux/Mac)
  - Cek: Task Manager → php.exe (Windows)
  
- [ ] **Log error?**
  - Cek: `storage/logs/laravel.log`
  - Cari: "AI Agent", "ProcessAiAgentMessage", "Failed"
  
- [ ] **Job masuk ke queue?**
  - Cek: `SELECT * FROM jobs WHERE queue = 'ai-agent';`
  
- [ ] **Job gagal?**
  - Cek: `SELECT * FROM failed_jobs ORDER BY failed_at DESC;`
  - Lihat error message di kolom `exception`

## 📝 Kesimpulan

**Masalah utama yang ditemukan:**

1. ❌ **WhatsApp Account tidak terhubung** - AI Agent tidak bisa mengirim pesan tanpa WhatsApp account
2. ⚠️ **16 Failed jobs** - Karena WhatsApp account tidak ditemukan
3. ⚠️ **5 Pending jobs** - Menunggu diproses

**Solusi:**

1. **Hubungkan WhatsApp Account** via dashboard atau manual insert ke database
2. **Retry failed jobs**: `php artisan queue:retry all`
3. **Jalankan queue worker** (jika menggunakan database queue): `php artisan queue:work --queue=ai-agent`

**Setelah fix:**

Alur akan bekerja sempurna:
```
Webhook terima pesan 
  → Dispatch ke queue 
  → Worker proses 
  → AI generate response 
  → Kirim ke WhatsApp API 
  → Customer terima response ✅
```

**Tools untuk monitoring:**

- `php check_queue_status.php` - Cek status sistem
- `start_queue_worker.bat` / `.sh` - Jalankan queue worker
- `tail -f storage/logs/laravel.log` - Monitor log real-time
