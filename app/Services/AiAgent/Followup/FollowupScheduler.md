# FollowupScheduler

## Tanggung jawab
Schedule + invalidate `SendFollowupJob` untuk satu conversation. Bukan pengirim
pesan — itu tanggung jawab job + `CatalogOrderFlowService::sendStateReminder`.

## Input → Output
- `schedule(conversation)`: dispatch SendFollowupJob ke queue `ai-agent` dengan
  delay = merchant's `followup_interval_minutes` × 60s. Return `bool` (true kalau
  job dispatched, false kalau short-circuited oleh guard).
- `cancel(conversation)`: reset followup tracking. Pending job di queue tidak
  di-cancel aktif; saat job jalan, ia akan deteksi mismatched cycle-id dan exit.
- `intervalSeconds(conversation)`: seconds sampai tick berikutnya.

## Dependencies
- SendFollowupJob (queue: ai-agent)
- AiAgentConversation helpers: `startFollowupCycle`, `resetFollowup`

## State
Yang ditulis:
- `ai_agent_conversations.followup_count` (reset ke 0 saat startFollowupCycle)
- `ai_agent_conversations.followup_state_started_at` (set saat schedule, cleared di reset)
- `ai_agent_conversations.pending_followup_job_id` (cleared di reset)

## Edge cases
- **Idempotency via cycle-id**: pending Redis jobs **tidak** di-cancel aktif.
  Setiap job menyimpan `(state, cycle_started_at)` di constructor; saat eksekusi
  membanding dengan nilai DB. Mismatch → exit. Strategi ini menghindari Redis
  cancellation complexity tanpa correctness loss.
- **Guard `shouldSchedule`**: skip kalau `flow_state IS NULL`, agent inactive,
  `followup_enabled = false`, atau contact ai_active false.
- **markActivity** (di AiAgentConversation) clear cycle-id + counter saat customer
  balas; pending job otomatis stale di tick berikutnya.
- Dispatched dari `AiAgentConversation::setFlowState()` saat transisi state baru.
  Single integration point — semua state setter trigger reschedule otomatis.
