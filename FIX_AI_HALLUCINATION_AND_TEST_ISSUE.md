# Fix: AI Halusinasi Menu & Test AI Agent Error

## Tanggal: 11 Januari 2026

## Masalah 1: AI Agent Memberikan Menu Halusinasi

### Gejala:
- AI Agent menampilkan menu yang salah/tidak lengkap
- Menu yang ditampilkan tidak sesuai dengan database produk
- Contoh: Menampilkan "Nasi Goreng Spesial", "Mie Goreng Jawa", dll yang tidak ada di database

### Screenshot Evidence:
User bertanya "saya ingin pesan makanan, ada menunya?" dan AI menjawab dengan menu yang salah:
- *Nasi Goreng Spesial* - Rp35,000
- *Mie Goreng Jawa* - Rp30,000
- *Ayam Goreng Kremes* - Rp45,000
- dll (menu yang tidak ada di database)

### Root Cause:

**System prompt tidak cukup eksplisit** dalam mencegah halusinasi. Prompt yang ada terlalu singkat dan tidak jelas:

```php
// SEBELUM (terlalu singkat, tidak jelas)
'core_rules' => "RULES:fn_data_only|search_first|empty=skip|HIDE_ID|scope:menu,order
Format:'Nama-RpHarga'|Off-topic:'Maaf,saya :business_name untuk pemesanan.'",

'anti_hallucination_reminder' => 'empty=N/A|fn=truth|HIDE_ID',
```

LLM tidak mendapat instruksi yang jelas untuk:
1. **TIDAK boleh membuat menu sendiri**
2. **HARUS memanggil get_all_products() terlebih dahulu**
3. **HANYA menampilkan data dari tool results**

### Solusi:

Update `config/ai_agent_prompts.php` dengan instruksi yang lebih eksplisit:

```php
// SESUDAH (lebih eksplisit dan jelas)
'core_rules' => "RULES:
1. NEVER invent/hallucinate menu items or prices
2. ALWAYS call get_all_products() before showing menu
3. Show ONLY data from tool results
4. Format: 'Nama - RpHarga'
5. HIDE product IDs from user
6. If empty result: say 'Tidak ada'
7. Off-topic: 'Maaf, saya :business_name untuk pemesanan.'",

'anti_hallucination_reminder' => '⚠️ NEVER invent menu! ALWAYS use get_all_products() first. Show ONLY real data from tools. empty=N/A|HIDE_ID',
```

### Perubahan File:
- `config/ai_agent_prompts.php`

### Testing:
1. Clear config cache: `php artisan config:clear`
2. Test dengan pesan: "saya ingin pesan makanan, ada menunya?"
3. Verifikasi AI memanggil `get_all_products()` tool
4. Verifikasi menu yang ditampilkan sesuai database

---

## Masalah 2: Test AI Agent Error - "Failed to process test message"

### Gejala:
- Ketika klik tombol "Test AI Agent" di dashboard
- Muncul error: "Error: Failed to process test message"
- Test tidak berjalan sama sekali

### Possible Root Causes:

1. **Frontend Issue**: Error handling di frontend tidak menampilkan detail error
2. **Backend Issue**: Ada exception yang tidak ter-catch di controller
3. **Authentication Issue**: Token atau session expired
4. **Database Issue**: Test contact atau conversation tidak bisa dibuat

### Investigasi:

Dari kode `AiAgentController@test`:
- Method sudah ada dan route terdaftar: `POST /api/ai-agent/test`
- Ada try-catch yang seharusnya menangkap error
- Return error message jika ada exception

### Debugging Steps:

1. **Cek Laravel Log**:
```bash
Get-Content storage/logs/laravel.log -Tail 100 | Select-String -Pattern "test|Test AI|Failed"
```

2. **Test Manual via API**:
```bash
# Dengan authentication token
curl -X POST http://localhost:8000/api/ai-agent/test \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message": "halo"}'
```

3. **Cek Browser Console**:
- Buka Developer Tools (F12)
- Lihat tab Console untuk error JavaScript
- Lihat tab Network untuk response dari API

### Kemungkinan Solusi:

#### Solusi 1: Frontend Error Handling
Jika error dari frontend, perlu update error handling untuk menampilkan detail:

```javascript
// Di frontend component
try {
  const response = await fetch('/api/ai-agent/test', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({ message: testMessage })
  });
  
  const data = await response.json();
  
  if (!response.ok) {
    // Tampilkan detail error
    console.error('API Error:', data);
    throw new Error(data.message || data.error || 'Failed to process test message');
  }
  
  // Success handling
} catch (error) {
  console.error('Test failed:', error);
  // Tampilkan error ke user dengan detail
}
```

#### Solusi 2: Backend Validation
Tambahkan logging lebih detail di controller:

```php
public function test(Request $request): JsonResponse
{
    Log::info('Test AI Agent called', [
        'user_id' => auth()->id(),
        'message' => $request->message,
    ]);
    
    try {
        // ... existing code ...
    } catch (\Exception $e) {
        Log::error('Test AI Agent failed', [
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
 