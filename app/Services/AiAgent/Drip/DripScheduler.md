# DripScheduler

## Tanggung jawab
Owns lifecycle of `ai_agent_drip_schedules` rows. Caller minta event ("cart non-empty",
"conversation finished") → service tambah/cancel rows. Bukan sender — firing
adalah tanggung jawab `DispatchDueDrips` poll job + `SendDripJob`.

## Input → Output
- `onCartUpdated(conversation)`: schedule Sequence A (cart abandoned) saat cart
  non-empty + tidak di state machine. Cancel kalau cart kosong atau state machine aktif.
- `onConversationResumed(conversation)`: cancel semua pending row (customer aktif lagi).
- `cancelSequence(conversation, sequence)`: cancel rows satu sequence saja.

## Dependencies
- `AiAgentDripSchedule` model
- Settings di `aiAgent->settings`: `drip_enabled`
- WhatsAppContact: `drips_paused_until`, `drips_unsubscribed_at` (opt-out fields)

## State
Yang ditulis:
- `ai_agent_drip_schedules`: insert pending rows + update status pending→cancelled
- Tidak menulis ke conversation atau contact

## Edge cases
- **Sequence A timing hardcoded** (30m + 4h). Tidak merchant-configurable —
  drip adalah operator-driven feature, bukan merchant.
- **Sequence A drip 3 (24h template)** skipped di MVP — butuh Meta template
  approval. Comment di code menandai TBD.
- **State machine + cart non-empty**: skip drip karena follow-up system sudah
  menangani in-state reminders. Drip hanya untuk **out-of-state** cart abandon.
- **Opt-out enforced** di shouldSchedule:
  - `drips_unsubscribed_at` (permanent via `BTN_STOP_DRIP` button)
  - `drips_paused_until` (30-day pause via `BTN_STOP_DRIP`)
  - `aiAgentSettings.drip_enabled = false` (merchant kill switch)
- **Idempotent**: calling `onCartUpdated` 2× berturut-turut cancel rows lama
  lalu insert rows baru. Aman buat cart-mutation hooks yang fire multiple kali.
