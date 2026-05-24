# Follow-up Plan — Re-engagement Singkat di Tengah Flow

Dokumen ini adalah **tolak ukur rencana penerapan** untuk fitur **follow-up message**.

> **Definisi:**
> Follow-up = pesan singkat yang dikirim **otomatis** ke customer **saat customer
> diam di tengah suatu `flow_state`**, untuk menarik mereka kembali ke flow.
>
> Berbeda dengan **drip** ([drip-message-plan.md](./drip-message-plan.md)) yang
> bersifat jadwal panjang (jam-an / hari-an, lintas-state). Follow-up bersifat
> **state-bound** dan **berdurasi pendek** (menit-an, di dalam state yang sama).

---

## Tujuan

1. Mengurangi **abandoned mid-flow** — customer yang stuck di tengah state karena
   tidak tahu harus apa, ragu, atau lupa.
2. Memberi **petunjuk yang relevan dengan posisi mereka** di flow, bukan greeting umum.
3. Menjadi pengganti yang lebih ramah untuk drip prompt lama (`sendDripPrompt`) yang
   sekarang langsung dikirim **setiap** pesan teks acak saat ada `flow_state`.
4. **Memberikan kontrol penuh ke merchant** untuk mengatur urgensi pengingat
   (interval & jumlah) lewat dashboard, dengan **auto-cancel** sebagai aksi terminal
   yang deterministic.

---

## Konfigurasi Per-Merchant (REVISED)

> Section ini memperbarui aturan default yang ada di bagian "Trigger Matrix" &
> "Anti-spam" di bawah. Field-field konfigurasi didefinisikan di
> [config-plan.md C8](./config-plan.md#c8--merchant-configurable-follow-up).

Merchant mengatur 3 hal di `/dashboard/ai-agent`:

| Field | Default | Range | Disimpan di |
|---|---|---|---|
| `followup_interval_minutes` | 10 | 5–60 menit | `AiAgentSettings.followup_interval_minutes` |
| `followup_max_count` | 3 | 1–5 | `AiAgentSettings.followup_max_count` |
| `followup_auto_cancel` | true | bool | `AiAgentSettings.followup_auto_cancel` |

### Bagaimana ini mengubah timing

- **Tidak ada lagi threshold per-state yang berbeda** (3 menit untuk `CONFIRMING_CART`, 5 menit untuk `AWAITING_DELIVERY_INFO`, dst).
  Semua state pakai `followup_interval_minutes` yang sama, ditentukan merchant.
- Trigger matrix di bawah sekarang **hanya menjelaskan konten per state**, bukan timing.

### Bagaimana ini mengubah jumlah follow-up

- **Tidak ada lagi aturan "Maks 1 follow-up per state"**.
- Per **flow cycle** (dari masuk state machine sampai keluar), follow-up dikirim hingga `followup_max_count` kali, dengan jeda `followup_interval_minutes`.
- Setiap incoming customer message **reset counter** kembali ke 0 — karena customer aktif lagi.

### Auto-cancel (aksi terminal)

Setelah follow-up ke-`followup_max_count` dikirim **dan** customer tetap tidak merespon dalam `followup_interval_minutes` berikutnya:

1. Kirim 1 pesan terakhir: *"Pesanan dibatalkan karena tidak ada respons. Tap *Lihat Menu* untuk pesan ulang."* + tombol `[Lihat Menu]`.
2. Jalankan cleanup terminal:
   - `clearFlowState()`, `clearCatalogItems()`, `clearDeliveryContext()`, `clearPendingOrder()`.
   - Kalau ada `Order` yang sudah dibuat (mis. state `AWAITING_PAYMENT`):
     - Set `order.status = 'cancelled'`, `cancellation_reason = 'auto_cancelled_no_response'`.
     - Kalau ada QRIS transaction pending → `QrisTransaction::markAsExpired()` + revoke link.
   - Reset `followup_count = 0`, `followup_sent_for_state = NULL`.
3. Log: `Log::info('Follow-up auto-cancel triggered', ['conversation_id', 'state', 'order_id', 'reason'])`.

**Behavior `followup_auto_cancel = false`:** setelah max count tercapai, stop kirim follow-up tapi **jangan** cancel order. State tetap "menggantung" sampai customer kembali. (Cocok untuk merchant yang fulfillment-nya manual / longgar.)

### Contoh skenario (default settings: 10 menit × 3)

```
T=0       customer di STATE_AWAITING_PAYMENT, link QRIS terkirim
T+10m     follow-up #1: "💳 Link QRIS aktif, sisa N menit. Bayar sekarang?"
T+20m     follow-up #2: "⏰ Sisa N menit. Pesanan auto-batal jika tidak dibayar."
T+30m     follow-up #3: "Reminder terakhir — bayar sekarang atau pesanan dibatalkan."
T+40m     AUTO-CANCEL: order.status = cancelled, kirim pesan terminal
```

Kalau customer balas/bayar kapan saja di antara → semua sisa follow-up cancelled, counter reset.

---

## Trigger Matrix (REVISED)

> Timing semua state dikendalikan oleh `followup_interval_minutes` (lihat section
> "Konfigurasi Per-Merchant" di atas). Tabel ini hanya menjelaskan **konten per state**.

| State / Kondisi | Konten | Tombol |
|---|---|---|
| `STATE_CONFIRMING_CART` | Re-send ringkasan cart singkat | [✅ Konfirmasi] [❌ Batal] |
| `STATE_AWAITING_FULFILLMENT` | Pilih metode pengiriman | [🏪 Pickup] [🚚 Delivery] [📅 Reservasi] (sesuai config) |
| `STATE_AWAITING_DELIVERY_INFO` | Reminder format delivery info | — (tunggu teks) |
| `STATE_CONFIRMING_DELIVERY_INFO` | Re-send summary delivery | [✏️ Edit] [✅ Konfirmasi] |
| `STATE_CONFIRMING_ORDER_SUMMARY` | Re-send order summary | [✅ Konfirmasi] [❌ Batal] |
| `STATE_AWAITING_PAYMENT` | Reminder bayar QRIS, sisa waktu link | [💳 Lihat Link] [❌ Batal] |

**Catatan:** `STATE_AWAITING_PAYMENT` sekarang **termasuk** di follow-up matrix (revisi
dari rancangan awal). Sebelumnya state ini ditangani drip-plan dengan timeline
hardcoded (5m, 15m). Karena merchant minta kontrol uniform, payment ikut pakai
`followup_interval_minutes` yang sama.

Drip-plan tetap menangani kasus **non-state-based** (cart abandoned setelah keluar
state machine, re-engagement >24 jam, dll). Lihat
[drip-message-plan.md](./drip-message-plan.md) — Sequence B akan dikonsolidasikan
ke follow-up agar tidak overlap.

---

## Aturan Eksekusi

### Anti-spam (REVISED)

- **Maks `followup_max_count` per flow cycle** (default 3, merchant-configurable 1–5). Setelah max tercapai → auto-cancel (atau stop diam-diam kalau `followup_auto_cancel = false`).
- **Reset counter setiap incoming customer message**. Customer aktif lagi → counter kembali ke 0, timer ulang dari sekarang.
- **Reset counter setiap state transition**. Pindah state = fresh start.
- **Cooldown global**: 60 detik antara dua outbound message berturut-turut (lintas mekanisme: follow-up + drip + reply LLM).
- **Skip kalau `contact.ai_active = false`** (manual takeover).
- **Skip kalau `WhatsAppAccount.is_active = false`** atau `AiAgent.is_active = false`.
- **Skip kalau `AiAgentSettings.followup_enabled = false`** (merchant matikan fitur).

### Quiet hours

- Default: **22:00 – 08:00 WIB** (jam lokal merchant).
- Override per merchant via `AiAgentSettings.quiet_hours_start` / `quiet_hours_end` (lihat config-plan C5).
- Saat follow-up jatuh tempo di quiet hours → **defer** ke awal jam aktif berikutnya.
  - Catatan: kalau defer melewati 24-jam WhatsApp session window → skip (template message tidak relevan untuk follow-up singkat).

### 24-hour session window

- Follow-up **hanya** boleh dikirim dalam 24 jam sejak pesan terakhir dari customer.
- Di luar itu: skip. (Drip-plan menangani re-engagement di luar window dengan template.)

---

## Konten Follow-up (template ringkas)

Aturan konten:
- **1 pesan**, **≤ 2 kalimat**.
- Selalu sertakan **CTA jelas** (tombol existing atau prompt).
- **Jangan re-render context panjang** (mis. jangan tampilkan ulang seluruh cart). Cukup ringkasan.
- **Bahasa hangat, tidak menuduh**. Hindari "kamu belum membalas".

Contoh per state:

```
STATE_CONFIRMING_CART:
  "🛒 Cart kamu masih disimpan ya. Mau lanjutkan?"
  [✅ Konfirmasi] [❌ Batal]

STATE_AWAITING_FULFILLMENT:
  "Pilih metode pengiriman:"
  [🏪 Pickup] [🚚 Delivery] [📅 Reservasi]
  (tombol tergantung config — sama dengan Flow 5)

STATE_AWAITING_DELIVERY_INFO:
  "🚚 Kirim info delivery dalam satu pesan ya:
   Nama, HP, Alamat, Catatan (opsional)"
  (tanpa tombol — tunggu teks)

STATE_CONFIRMING_DELIVERY_INFO:
  "Info delivery sudah benar?"
  [✏️ Edit] [✅ Konfirmasi]

STATE_CONFIRMING_ORDER_SUMMARY:
  "Lanjut konfirmasi pesanan?"
  [✅ Konfirmasi] [❌ Batal]
```

---

## Mekanisme Teknis

### Scheduling

**Pendekatan:** Laravel Scheduler menjalankan command `ai-agent:dispatch-followup` **tiap 1 menit**.

Command tersebut:
1. Query `AiAgentConversation` yang:
   - `flow_state IS NOT NULL`,
   - `flow_state IN ({list state yang punya follow-up})`,
   - `last_activity_at < now() - threshold_per_state`,
   - `followup_sent_for_state IS NULL` (belum pernah follow-up di state ini),
   - kontak `ai_active = true`,
   - within 24h session window.
2. Dispatch `SendFollowupJob` per conversation ke queue `ai-agent` (bukan queue utama webhook).

### Field DB tambahan (REVISED)

`ai_agent_conversations` table:
- `last_activity_at` (timestamp) — di-update setiap incoming customer message ATAU outgoing bot message yang state-transition.
- `followup_count` (int, default 0) — counter berapa kali follow-up sudah dikirim di flow cycle saat ini. Di-reset ke 0 saat: incoming customer message, state transition, atau auto-cancel terminal.
- `followup_state_started_at` (timestamp, nullable) — kapan state cycle dimulai (untuk menghitung "T+10m, T+20m, T+30m"). Di-set saat masuk state baru; di-reset saat customer balas.
- `last_drip_at` (timestamp) — global cooldown lintas state (juga dipakai drip-plan).

Field `followup_sent_for_state` (dari rancangan awal) **dihilangkan** — counter-based
(`followup_count`) lebih sederhana & cocok untuk N> 1.

### Job: `SendFollowupJob` (REVISED)

Tanggung jawab:
1. Re-validasi:
   - State belum berubah.
   - `followup_count < followup_max_count` (kalau >= → trigger auto-cancel).
   - Masih dalam quiet hours / session window.
   - `followup_enabled = true` di settings.
2. **Branch: belum max** — render konten + kirim:
   - Render konten berdasarkan state (delegasi ke `CatalogOrderFlowService` — reuse `sendCartConfirmation()`, `sendFulfillmentButtons()`, `sendOrderSummary()`, dll).
   - Increment `followup_count`, set `last_drip_at = now()`.
   - Schedule job berikutnya di `now() + followup_interval_minutes`.
   - Log: `Log::info('Follow-up sent', ['conversation_id', 'state', 'count', 'max'])`.
3. **Branch: sudah max + `followup_auto_cancel = true`** — jalankan auto-cancel terminal (lihat section "Auto-cancel" di atas).
4. **Branch: sudah max + `followup_auto_cancel = false`** — stop, log "max reached, auto_cancel off".

### Scheduling Strategy (REVISED)

Pendekatan: **self-rescheduling job** (bukan polling scheduler tiap menit).

- Saat state machine memasuki state target (mis. `STATE_CONFIRMING_CART` di-set) → dispatch `SendFollowupJob::dispatch(...)` dengan `delay($interval_minutes * 60)`.
- Saat job berjalan & memutuskan kirim follow-up berikutnya → schedule diri sendiri ulang dengan delay yang sama.
- Saat reset trigger terjadi (customer balas, state transisi) → batalkan job pending (Laravel job has `job_id`, simpan di conversation untuk cancel).

Field tambahan: `pending_followup_job_id` (string, nullable) di `ai_agent_conversations`.

Alternatif scheduler-based 1-menit-poll masih oke kalau lebih sederhana — tinggal query `where followup_state_started_at + (interval * (count+1)) <= now()`. Pilih saat implementasi berdasarkan beban.

### Reset triggers (REVISED)

Reset `last_activity_at`, `followup_count = 0`, `followup_state_started_at = NULL`, dan **cancel pending job** saat:
- Webhook menerima pesan dari customer.
- State machine transisi (state berubah — termasuk masuk state baru: set `followup_state_started_at = now()` lalu schedule job baru).
- Auto-cancel terminal dijalankan.

---

## Validasi (Kriteria Selesai) — REVISED

- [ ] **Config merchant**: dashboard menampilkan radio interval (5, 10, 15, 20, 30, 45, 60 menit) + input max count (1–5) + toggle auto-cancel. Tersimpan ke `AiAgentSettings`.
- [ ] **Validasi server-side**: interval di luar 5–60 atau max count di luar 1–5 → reject.
- [ ] **Timing default 10/3**: customer masuk `STATE_CONFIRMING_CART` → follow-up #1 di T+10m, #2 di T+20m, #3 di T+30m.
- [ ] **Counter reset incoming**: customer balas di T+15m → counter reset 0, follow-up berikutnya di T+25m (T+15m + 10m).
- [ ] **Counter reset transition**: state pindah ke `AWAITING_FULFILLMENT` → counter 0, schedule baru.
- [ ] **Auto-cancel terminal**: follow-up #3 dikirim, customer tetap diam → di T+40m, order auto-cancelled, pesan terminal terkirim, state cleared.
- [ ] **Auto-cancel di payment**: state `AWAITING_PAYMENT` dengan QRIS pending → mencapai max → QrisTransaction marked expired + order cancelled.
- [ ] **`followup_auto_cancel = false`**: max tercapai → stop follow-up, **tidak** cancel.
- [ ] **`followup_enabled = false`**: tidak ada follow-up dikirim sama sekali.
- [ ] **Per-merchant interval berbeda**: merchant A pakai 5 menit, merchant B pakai 30 menit → keduanya ter-honor independen.
- [ ] **Quiet hours**: trigger jatuh di 23:30 → defer ke 08:00 keesokan.
- [ ] **`ai_active = false`**: tidak ada follow-up.
- [ ] **>24h session**: tidak ada follow-up free-form (lihat drip-plan untuk template).
- [ ] **Metric**: rate follow-up → reply per state ter-tracked di `ai_agent_followup_events`.

---

## Metrics yang Di-track

| Metric | Tujuan |
|---|---|
| `followup_sent_total` per state | Volume |
| `followup_to_reply_rate` per state | Efektivitas |
| `followup_to_completed_order_rate` | Business outcome |
| `followup_skipped_quiet_hours` | Sanity check quiet hours logic |
| `followup_skipped_session_expired` | Visibility 24h window cut-off |

Disimpan di tabel `ai_agent_followup_events` atau Telescope (TBD).

---

## Risk & Rollback

| Risk | Mitigasi | Rollback |
|---|---|---|
| Customer kesal karena dianggap "spam" | Maks 1/state, quiet hours, cooldown 60 detik | Toggle off via `AiAgentSettings.followup_enabled = false` |
| Race condition (state berubah saat job berjalan) | Re-validasi state di awal `SendFollowupJob` | — (idempotent) |
| Scheduler beban tinggi | Index `(flow_state, last_activity_at, followup_sent_for_state)` | Throttle scheduler ke 5 menit |

---

## Dependensi (REVISED)

- **`config-plan.md` C5 + C8**: `AiAgentSettings` schema (`followup_interval_minutes`, `followup_max_count`, `followup_auto_cancel`, `followup_enabled`, quiet hours).
- **`flow-plan.md`**: state machine yang konsisten + `last_drip_at` cooldown (F8).
- **`drip-message-plan.md` Sequence B (Payment)**: dikonsolidasikan ke follow-up — drip-plan akan di-update untuk hapus Sequence B atau redirect ke follow-up.
- **Hapus `sendDripPrompt`** lama (di `CatalogOrderFlowService`) → digantikan oleh follow-up mechanism ini. Drip-plan punya mekanisme terpisah untuk kasus non-state-based (cart abandon out-of-state-machine, re-engagement >24 jam).

---

## Hubungan dengan Drip

| Aspek | Follow-up | Drip |
|---|---|---|
| Trigger | State stuck (menit-an) | Inaktivitas / abandoned (jam-an) |
| Konten | Re-send prompt state saat ini | Re-engagement / reminder / template |
| Frekuensi | 1× per state | Mengikuti schedule (1×, 2×, dst sesuai sequence) |
| Lintas-state | Tidak | Iya (cart-abandoned independent dari state) |
| Di luar 24h session | Tidak | Bisa (pakai template Meta) |

Lihat [drip-message-plan.md](./drip-message-plan.md) untuk detail drip.

---

## Files yang Akan Disentuh (REVISED)

```
app/Jobs/SendFollowupJob.php                                   # baru — self-rescheduling
app/Jobs/AutoCancelOrderJob.php                                # baru — terminal aksi cancel
app/Console/Commands/DispatchAiAgentFollowups.php              # opsional (kalau pakai poll-based)
app/Console/Kernel.php                                         # daftarkan schedule (kalau pakai poll)
app/Models/AiAgentConversation.php                             # last_activity_at, followup_count, followup_state_started_at, pending_followup_job_id, last_drip_at
app/Services/CatalogOrderFlowService.php                       # expose render method per state (reuse)
app/Services/AiAgentService.php                                # update last_activity_at, schedule SendFollowupJob on state enter
app/Http/Controllers/Api/WhatsAppWebhookController.php         # reset counter on incoming + cancel pending job
database/migrations/*_add_followup_fields_to_conversations.php
database/migrations/*_create_ai_agent_followup_events.php (optional, untuk metrics)
```

Tidak menyentuh: dashboard UI (sudah di-handle config-plan C8), prompt builder, drip out-of-session.
