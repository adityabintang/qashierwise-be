# BytePlus Context Caching Implementation

## Overview

Implementasi context caching menggunakan BytePlus Responses API untuk mengoptimalkan biaya dan performa AI Agent. Caching dapat mengurangi biaya hingga 86% untuk request yang berulang.

## Cara Kerja

### Session Caching (Multi-turn Conversation)

1. **Request Pertama**: System prompt di-cache dengan `prefix: true`
2. **Request Selanjutnya**: Menggunakan `previous_response_id` untuk melanjutkan session
3. **Cache Expiry**: Maksimal 72 jam (otomatis di-refresh setiap request)

### Flow Diagram

```
Request 1 (First Message):
┌─────────────────────────────────────────────────────────┐
│ POST /api/v3/responses                                  │
│ {                                                       │
│   "model": "...",                                       │
│   "input": [system_prompt, user_message],               │
│   "caching": {"type": "enabled", "prefix": true}        │
│ }                                                       │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
              Response: {id: "resp_xxx", ...}
                          │
                          ▼
         Store response_id in conversation.cache_response_id

Request 2+ (Subsequent Messages):
┌─────────────────────────────────────────────────────────┐
│ POST /api/v3/responses                                  │
│ {                                                       │
│   "model": "...",                                       │
│   "input": [user_message],                              │
│   "previous_response_id": "resp_xxx",                   │
│   "caching": {"type": "enabled"}                        │
│ }                                                       │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
              Response includes cached_tokens in usage
```

## Konfigurasi

### Enable Caching untuk AI Agent

```php
// Enable untuk satu agent
$agent = AiAgent::find(1);
$agent->enable_prompt_caching = true;
$agent->save();

// Enable untuk semua agent
AiAgent::query()->update(['enable_prompt_caching' => true]);
```

### Database Schema

```sql
-- ai_agent_conversations table
ALTER TABLE ai_agent_conversations 
ADD COLUMN cache_response_id VARCHAR(255) NULL,
ADD COLUMN cache_expires_at TIMESTAMP NULL;
```

## Monitoring

### Log Messages

```
# Cache creation
BytePlus Responses API: Creating new cache with prefix

# Cache usage
BytePlus Responses API: Using existing cache

# Cache stats
BytePlus Responses API cache stats:
- input_tokens: 2500
- cached_tokens: 2000
- cache_hit: true
- cache_savings_percent: 80%
```

### Analytics

Data caching disimpan di tabel `ai_prompt_analytics`:
- `prompt_type`: 'cached'
- `cache_hit`: true/false
- `prompt_tokens`: jumlah token input
- `completion_tokens`: jumlah token output

## Fallback Behavior

Jika Responses API gagal:
1. Cache invalid → Clear cache dan retry
2. API error → Fallback ke Chat Completions API
3. Semua retry gagal → Throw exception

## Cost Savings

Berdasarkan dokumentasi BytePlus:
- Cache hit dapat menghemat hingga 86% biaya
- Semakin panjang system prompt, semakin besar penghematan
- Cocok untuk multi-turn conversation dengan system prompt yang sama

## Referensi

- [BytePlus Context Caching Overview](https://docs.byteplus.com/en/docs/ModelArk/1398933)
- [Responses API Documentation](https://docs.byteplus.com/en/docs/ModelArk/1398935)
