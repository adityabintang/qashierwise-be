# Fix: Struktur Messages di AI Agent Conversations

## Masalah

Struktur `messages` di tabel `ai_agent_conversations` menggunakan field `role` dengan nilai "user" dan "assistant", padahal seharusnya menggunakan field `type` dengan nilai "human" dan "ai".

**Struktur Lama (Salah)**:
```json
{
  "role": "user",
  "content": "Pesan 2 Es Buah",
  "timestamp": "2025-12-25T17:52:43+00:00"
}
```

**Struktur Baru (Benar)**:
```json
{
  "type": "human",
  "content": "Pesan 2 Es Buah",
  "timestamp": "2025-12-25T17:52:43+00:00"
}
```

## Alasan Perubahan

1. **Konsistensi Terminologi**: "human" dan "ai" lebih jelas dan konsisten dengan domain aplikasi
2. **Pemisahan Concern**: Field `role` biasanya digunakan untuk authorization/permission, sedangkan `type` lebih tepat untuk message type
3. **Standar Industri**: Banyak chat/messaging system menggunakan "human" dan "ai" atau "bot" untuk membedakan sender type

## Solusi

### 1. Update Model AiAgentConversation

**File**: `app/Models/AiAgentConversation.php`

Mengubah method `addMessage()` untuk menggunakan `type` instead of `role`:

```php
public function addMessage(string $type, string $content): void
{
    $messages = $this->messages ?? [];

    // Add new message with type (human/ai) instead of role
    $messages[] = [
        'type' => $type, // 'human' or 'ai'
        'content' => $content,
        'timestamp' => now()->toIso8601String(),
    ];

    // Keep only last 10 messages (sliding window)
    if (count($messages) > 10) {
        $messages = array_slice($messages, -10);
    }

    $this->messages = $messages;
    $this->expires_at = now()->addHours(24);
    $this->save();
}
```

### 2. Update Semua Pemanggilan addMessage()

**Files Modified**:
- `app/Services/AiAgentService.php`
- `app/Http/Controllers/AiAgentController.php`

**Perubahan**:
```php
// Sebelum
$conversation->addMessage('user', $messageText);
$conversation->addMessage('assistant', $response);

// Sesudah
$conversation->addMessage('human', $messageText);
$conversation->addMessage('ai', $response);
```

### 3. Mapping untuk LLM API

**File**: `app/Services/AiAgentService.php` - Method `callLLM()`

LLM API (BytePlus ARK) masih menggunakan "role" dengan nilai "user" dan "assistant", jadi kita perlu mapping:

```php
foreach ($messages as $msg) {
    // Map type (human/ai) to role (user/assistant) for LLM API
    $role = match($msg['type'] ?? $msg['role'] ?? 'user') {
        'human' => 'user',
        'ai' => 'assistant',
        default => $msg['type'] ?? $msg['role'] ?? 'user'
    };
    
    $llmMessages[] = [
        'role' => $role,
        'content' => $msg['content'],
    ];
}
```

**Alasan**: 
- Internal storage menggunakan "type" dengan "human"/"ai"
- External API menggunakan "role" dengan "user"/"assistant"
- Mapping dilakukan saat memanggil API
- Backward compatible dengan data lama yang masih menggunakan "role"

### 4. Data Migration

**File**: `database/migrations/2025_12_25_180550_update_messages_structure_in_ai_agent_conversations.php`

Migration untuk update data yang sudah ada:

```php
public function up(): void
{
    $conversations = DB::table('ai_agent_conversations')
        ->whereNotNull('messages')
        ->get();

    foreach ($conversations as $conversation) {
        $messages = json_decode($conversation->messages, true);
        
        if (!is_array($messages)) {
            continue;
        }

        $updatedMessages = [];
        foreach ($messages as $message) {
            // Skip if already using 'type'
            if (isset($message['type'])) {
                $updatedMessages[] = $message;
                continue;
            }

            // Map 'role' to 'type'
            $type = match($message['role'] ?? 'user') {
                'user' => 'human',
                'assistant' => 'ai',
                default => 'human'
            };

            $updatedMessages[] = [
                'type' => $type,
                'content' => $message['content'] ?? '',
                'timestamp' => $message['timestamp'] ?? now()->toIso8601String(),
            ];
        }

        DB::table('ai_agent_conversations')
            ->where('id', $conversation->id)
            ->update(['messages' => json_encode($updatedMessages)]);
    }
}
```

**Fitur**:
- Update semua data existing dari "role" ke "type"
- Map "user" → "human" dan "assistant" → "ai"
- Skip jika sudah menggunakan "type" (idempotent)
- Reversible dengan method `down()`

## Hasil

### Struktur Messages Sekarang

```json
[
  {
    "type": "human",
    "content": "Pesan 2 Es Buah Selasih",
    "timestamp": "2025-12-25T17:52:43+00:00"
  },
  {
    "type": "ai",
    "content": "✅ Berhasil menambahkan ke keranjang!\n\n📦 Es Buah Selasih\n💰 Rp 10.000 x 2 = Rp 20.000",
    "timestamp": "2025-12-25T17:52:45+00:00"
  },
  {
    "type": "human",
    "content": "Lihat keranjang",
    "timestamp": "2025-12-25T17:53:15+00:00"
  },
  {
    "type": "ai",
    "content": "🛒 Keranjang Belanja\n\n1. Es Buah Selasih\n   💰 Rp 10.000 x 2 = Rp 20.000\n\nSubtotal: Rp 20.000\nPajak (11%): Rp 2.200\nTotal: Rp 22.200",
    "timestamp": "2025-12-25T17:53:16+00:00"
  }
]
```

### Keuntungan

1. **Clarity**: Lebih jelas bahwa "human" adalah user dan "ai" adalah bot
2. **Consistency**: Konsisten dengan terminologi domain aplikasi
3. **Separation**: Memisahkan message type dari authorization role
4. **Backward Compatible**: Mapping di `callLLM()` mendukung data lama
5. **Future Proof**: Mudah extend untuk type lain (e.g., "system", "notification")

## Testing

### Test 1: Verifikasi Struktur Baru
```bash
php artisan tinker --execute="print_r(App\Models\AiAgentConversation::latest()->first()->messages);"
```

Expected: Semua messages menggunakan `type` dengan nilai "human" atau "ai"

### Test 2: Test Conversation Flow
```
1. User: "Pesan 2 Es Buah"
   → Saved as: {"type": "human", "content": "Pesan 2 Es Buah", ...}

2. AI: "✅ Berhasil menambahkan..."
   → Saved as: {"type": "ai", "content": "✅ Berhasil...", ...}
```

### Test 3: LLM API Compatibility
```
Internal: {"type": "human", "content": "Hello"}
Sent to LLM: {"role": "user", "content": "Hello"}

Internal: {"type": "ai", "content": "Hi there"}
Sent to LLM: {"role": "assistant", "content": "Hi there"}
```

## Files Modified

1. `app/Models/AiAgentConversation.php` - Changed `role` to `type` in addMessage()
2. `app/Services/AiAgentService.php` - Updated all addMessage() calls and added mapping in callLLM()
3. `app/Http/Controllers/AiAgentController.php` - Updated all addMessage() calls
4. `database/migrations/2025_12_25_180550_update_messages_structure_in_ai_agent_conversations.php` - NEW

## Impact

- ✅ Messages structure now uses "type" with "human"/"ai"
- ✅ All existing data migrated automatically
- ✅ LLM API calls still work with proper mapping
- ✅ Backward compatible with old data
- ✅ Clearer and more consistent terminology
- ✅ Each message is a separate row in the JSON array

## Notes

- Setiap pesan (human dan ai) disimpan sebagai entry terpisah dalam array `messages`
- Field `type` menggantikan `role` untuk lebih jelas
- Mapping otomatis dilakukan saat memanggil LLM API
- Migration bersifat reversible jika perlu rollback
