# Drip Message Plan — Schedule Panjang Lintas-State

Dokumen ini adalah **tolak ukur rencana penerapan** untuk fitur **drip message**.

> **Definisi:**
> Drip = pesan **terjadwal lintas-state**, dengan timeline berskala
> menit-jam-hari. Tujuannya memulihkan customer dari **abandonment** (cart
> ditinggal, pembayaran tidak selesai, percakapan tidak dilanjut) — bukan
> mendorong customer melewati satu state seperti follow-up
> ([follow-up-plan.md](./follow-up-plan.md)).

| Aspek | Follow-up | Drip |
|---|---|---|
| Trigger | State stuck (menit) | Inaktivitas / abandonment (jam-hari) |
| Konten | Re-prompt state saat ini | Reminder / recovery / nudge |
| Frekuensi | 1× per state | Sequence (drip 1, drip 2, drip 3) |
| Lintas-state | Tidak | Iya |
| Di luar 24h session | Tidak | Iya (pakai template Meta) |

---

## Tujuan

1. Recover **abandoned carts** (POS mode atau catalog mode — cart terisi, tidak checkout).
2. Recover **abandoned payments** (QRIS dibuat, customer tidak bayar).
3. (Opsional) **Re-engagement** untuk customer yang sudah pernah order tapi tidak balik.

---

## Drip Sequences

### Sequence A — Abandoned Cart (POS mode)

**Kondisi masuk:**
- `conversation.cart` not empty,
- `flow_state IS NULL` (belum masuk state machine),
- `last_activity_at > 15 menit yang lalu`.

| Drip # | Setelah | Konten | CTA |
|---|---|---|---|
| 1 | 30 menit | "🛒 Cart kamu masih ada (N item, Rp xx.xxx). Lanjutkan?" | [Checkout] [Batal] |
| 2 | 4 jam | "Pesanan kamu menunggu. Tap untuk lanjut atau hapus cart." | [Checkout] [Hapus Cart] |
| 3 | 24 jam | (template Meta — kalau di luar 24h session) "Halo, cart kamu di Kafe X masih ada. Mau lanjut?" | [Lanjutkan] |

Setelah drip 3: cart auto-cleared, sequence ditutup.

### Sequence B — Abandoned Payment (QRIS expired window)

**Kondisi masuk:**
- `flow_state = STATE_AWAITING_PAYMENT`,
- `qris_transaction.expires_at > now()` (link masih valid).

| Drip # | Setelah | Konten | CTA |
|---|---|---|---|
| 1 | 5 menit | "💳 Link QRIS aktif, sisa N menit. Bayar sekarang?" | [Lihat Link] [Batal] |
| 2 | 15 menit | "⏰ Sisa N menit. Pesanan auto-batal jika tidak dibayar." | [Lihat Link] [Batal] |
| 3 | saat expire | "❌ Link kadaluarsa. Pesanan dibatalkan. Mau pesan ulang?" | [Lihat Menu] |

Drip 3 = transactional, dikirim **persis** saat expiry (tidak perlu nunggu inactivity).

### Sequence C — Re-engagement (opsional, fase 2)

**Kondisi masuk:**
- Customer punya >=1 order completed di masa lalu,
- `last_message_at > 14 hari yang lalu`,
- `contact.ai_active = true`,
- merchant punya **template Meta** untuk re-engagement.

| Drip # | Setelah | Konten (template Meta) | CTA |
|---|---|---|---|
| 1 | 14 hari sejak last activity | "Hai {name}, sudah lama tidak mampir 👋 Ada menu baru di Kafe X." | [Lihat Menu] |

Maks 1× per 30 hari per customer.

**Catatan:** Sequence C butuh template Meta yang sudah di-approve + opt-in checkbox dari customer (di Privacy Policy merchant). Implementasi di fase 2 — jangan masuk MVP.

---

## Aturan Umum

### Anti-spam global

- **Maks 3 drip per customer per 24 jam** (hard cap, lintas sequence).
- **Cooldown 60 detik** antara dua message keluar (lintas mekanisme: follow-up + drip + reply LLM).
- **Skip sequence kalau `ai_active = false`** atau `is_active = false`.
- **Reset sequence saat customer balas**:
  - Sequence A reset: customer balas → cart abandonment timer reset ke 0.
  - Sequence B reset: customer bayar → sequence selesai.

### Quiet hours

Sama dengan follow-up plan: 22:00 – 08:00 (configurable via `AiAgentSettings`).
**Pengecualian:** Sequence B drip 3 (notifikasi expiry) dikirim apapun jam — ini transactional, bukan marketing.

### 24-hour session window

- Drip dalam window 24 jam: free-form message.
- Drip di luar window 24 jam: **hanya boleh template Meta** yang sudah approved + opt-in.
- Sequence A drip 3 dan Sequence C **wajib pakai template**.
- Implementasi: cek `last_customer_message_at` sebelum kirim. Kalau >24 jam → switch ke template path.

---

## Mekanisme Teknis

### Scheduling

Tabel baru `ai_agent_drip_schedules`:

| Kolom | Tipe | Fungsi |
|---|---|---|
| `id` | bigint | PK |
| `conversation_id` | fk → `ai_agent_conversations` | — |
| `sequence` | enum (`abandoned_cart`, `abandoned_payment`, `re_engagement`) | — |
| `step` | int | 1, 2, 3 |
| `fire_at` | timestamp | kapan harus dikirim |
| `status` | enum (`pending`, `sent`, `cancelled`, `skipped`) | — |
| `template_name` | string nullable | nama template Meta (sequence di luar 24h) |
| `payload` | json nullable | snapshot data yang dibutuhkan saat fire (mis. cart count) |
| `created_at`, `updated_at`, `fired_at` | timestamps | — |

**Lifecycle:**

1. **Schedule on event:**
   - Cart non-empty + `flow_state IS NULL` + 15 menit no activity → schedule Sequence A drip 1 di `fire_at = last_activity + 30m`.
   - `flow_state = AWAITING_PAYMENT` set → schedule Sequence B drip 1, 2, 3 sekaligus.
2. **Cancel on transition:**
   - Customer transisi state / checkout / bayar / cancel → semua row `status = pending` untuk conversation × sequence di-set ke `cancelled`.
3. **Fire:**
   - Scheduler tiap 1 menit query `where fire_at <= now() AND status = 'pending'` → dispatch `SendDripJob`.

### Job: `SendDripJob`

Tanggung jawab:
1. Re-validasi (kondisi masuk masih berlaku — cart masih ada, payment masih pending, dll).
2. Cek anti-spam: count drip 24 jam, quiet hours, session window.
3. Render konten (template literal in-window; template Meta out-of-window).
4. Kirim via `WhatsAppService`.
5. Update row → `status = sent`, `fired_at = now()`.
6. Log: `Log::info('Drip sent', ['conversation_id', 'sequence', 'step'])`.

### Anti-double-fire

- Lock per `conversation_id` saat job berjalan (Laravel `Cache::lock` atau DB transaction).
- `fired_at IS NULL` check sebelum kirim.

---

## Konten Drip — Aturan Penulisan

- **Maks 2 kalimat** + 1 baris CTA (tombol).
- **Tidak boleh** memicu LLM call. Konten dirender dari template literal + data conversation.
- **Selalu ada exit door** ([Batal] / [Stop Reminder] / [Lihat Menu]).
- **Tone**: hangat & spesifik, tidak generic.
  - ❌ "Halo, kamu belum membalas."
  - ✅ "🛒 Cart kamu masih ada (Nasi Goreng Spesial x1, Rp 35.000). Mau lanjutkan?"

### Tombol baru yang diperlukan

| Konstanta | ID | Fungsi |
|---|---|---|
| `BTN_RESUME_CHECKOUT` | `drip_resume_checkout` | Sequence A — masuk Flow 4 (POS checkout) |
| `BTN_CLEAR_CART` | `drip_clear_cart` | Sequence A drip 2 — kosongkan cart |
| `BTN_VIEW_QRIS` | `drip_view_qris` | Sequence B — kirim ulang QR link |
| `BTN_STOP_DRIP` | `drip_stop` | Universal — set `AiAgentConversation.drips_paused_until = +30 days` |

`BTN_STOP_DRIP` adalah **respect signal** — customer tap = jangan kirim drip apapun ke conversation ini selama 30 hari. Wajib ada di setiap drip (sebagai 2nd/3rd button).

---

## Opt-in & Compliance

### In-session (≤24 jam)

- Drip in-session **tidak butuh** opt-in eksplisit (masih sesi aktif).

### Out-of-session (>24 jam)

- **Wajib** template Meta yang sudah approved.
- **Wajib** opt-in: customer pernah klik "Subscribe to reminders" atau sejenisnya, atau merchant mendokumentasikan opt-in via Privacy Policy yang customer setujui saat onboarding.
- **Wajib** opt-out: tombol `BTN_STOP_DRIP` selalu tersedia, dan customer juga bisa balas "STOP" / "BERHENTI" untuk unsubscribe permanen.

### Database: opt-out

`whatsapp_contacts` tambah kolom:
- `drips_paused_until` (timestamp nullable) — paused sampai tanggal ini.
- `drips_unsubscribed_at` (timestamp nullable) — permanen unsubscribe (customer ketik STOP).

Drip job **wajib** cek dua kolom ini sebelum kirim.

---

## Validasi (Kriteria Selesai)

- [ ] Sequence A: cart abandon → drip 1 di 30m, drip 2 di 4h, drip 3 di 24h (template).
- [ ] Sequence A: customer balas di antara → semua sisa drip cancelled.
- [ ] Sequence B: QRIS dibuat → 3 row drip scheduled (5m, 15m, expire).
- [ ] Sequence B: customer bayar → semua drip cancelled.
- [ ] Anti-spam: customer punya 3 drip 24 jam → drip ke-4 di-skip + logged.
- [ ] Quiet hours: drip Sequence A jatuh tempo jam 23:00 → defer ke jam 08:00 keesokan.
- [ ] Out-of-session: drip 24h+ pakai template (bukan free-form).
- [ ] `BTN_STOP_DRIP` → tap → `drips_paused_until = +30 days`, semua future drip cancelled.
- [ ] Customer balas "STOP" → `drips_unsubscribed_at = now()`, future drip skipped permanen.

---

## Metrics yang Di-track

| Metric | Tujuan |
|---|---|
| `drip_scheduled_total` per sequence | Volume |
| `drip_sent_total` per sequence × step | Eksekusi |
| `drip_cancelled_total` per sequence (with reason) | Kapan transition / opt-out terjadi |
| `drip_skipped_quiet_hours_total` | Sanity check |
| `drip_skipped_session_expired_total` | Visibility template gap |
| `drip_to_reply_rate` per sequence × step | Efektivitas |
| `drip_to_recovered_order_rate` (Seq A) | Business outcome |
| `drip_to_paid_rate` (Seq B) | Business outcome |
| `drip_opted_out_total` | Kepuasan |

---

## Risk & Rollback

| Risk | Mitigasi | Rollback |
|---|---|---|
| Customer kesal & block bisnis | Maks 3/24h, quiet hours, opt-out 1-tap | Toggle off `AiAgentSettings.drip_enabled` global |
| Sequence B drip kirim setelah customer bayar (race) | Re-validasi di `SendDripJob`, lock | — (idempotent) |
| Template Meta belum approved → drip out-of-session gagal | Fallback ke skip + log, jangan crash | Implementasi template-fetch validator |
| Scheduler table membengkak (jutaan row pending) | Cleanup job harian: hapus row `status != pending` >30 hari | Truncate scheduler table |

---

## Dependensi

- **`config-plan.md` C5**: `AiAgentSettings.drip_enabled`, quiet hours.
- **`flow-plan.md`**: state machine konsisten + intent yang clean.
- **`follow-up-plan.md`**: shared `last_drip_at` cooldown + scheduler infrastructure (reuse Laravel Scheduler tick).
- **Meta template approval**: untuk Sequence A drip 3 & Sequence C — kerja terpisah dengan tim ops.

---

## Roadmap Implementasi (fase)

### Fase 1 — MVP

- Sequence A (drip 1 & 2, **dalam** 24h window).
- Sequence B (drip 1 & 2, in-session — drip 3 pakai existing expire-notif).
- Anti-spam dasar (maks 3/24h, quiet hours).
- Opt-out via tombol.

### Fase 2 — Out-of-session

- Sequence A drip 3 dengan template Meta.
- Setup template approval workflow.

### Fase 3 — Re-engagement

- Sequence C dengan template Meta.
- Opt-in mechanism + Privacy Policy update.

---

## Files yang Akan Disentuh

```
app/Jobs/SendDripJob.php                                       # baru
app/Console/Commands/DispatchAiAgentDrips.php                  # baru
app/Console/Kernel.php                                         # daftarkan schedule
app/Models/AiAgentDripSchedule.php                             # baru
app/Models/AiAgentConversation.php                             # event: schedule on cart-non-empty, on payment-state-enter
app/Models/WhatsAppContact.php                                 # drips_paused_until, drips_unsubscribed_at
app/Services/Drip/DripScheduler.php                            # baru — buat & cancel row schedule
app/Services/Drip/DripContentRenderer.php                      # baru — render konten per sequence
app/Services/CatalogOrderFlowService.php                       # handle BTN_RESUME_CHECKOUT, BTN_VIEW_QRIS, BTN_STOP_DRIP
database/migrations/*_create_ai_agent_drip_schedules.php
database/migrations/*_add_drip_columns_to_contacts.php
```

Tidak menyentuh: follow-up logic (terpisah), prompt builder, tool dispatching.
