# Flow Plan — Penanganan Kelemahan Alur AI Agent

Dokumen ini adalah **tolak ukur rencana penerapan** untuk membersihkan kelemahan
di lapisan flow / state machine / routing pesan. Setelah penerapan selesai,
`flow.md` di folder induk akan diperbarui.

> **Sumber temuan**: lihat ringkasan "Kelemahan Paling Vatal — Flow".

---

## Daftar Masalah yang Akan Ditangani

| # | Masalah | Lokasi sekarang | Tingkat |
|---|---|---|---|
| F1 | Dual intent detection paralel (`UserIntent::detect()` vs `isOrderMenuIntent()` / `isNextMenuPageIntent()`) | `app/Enums/UserIntent.php` + `AiAgentService.php` baris 813, 841 | **Vatal** |
| F2 | `BUSINESS_INFO` intent dibiarkan butuh tools (workaround salah arah) | `AiAgentService::intentNeedsTools()` baris 777-789 | **Vatal** |
| F3 | Legacy `pending_order` paralel dengan state machine modern | `AiAgentService::processMessage` baris 222-243 | **Vatal** |
| F4 | `add_to_cart` tool description menyuruh "NO need to search first" → fuzzy match silent salah pilih | `AiAgentService::getToolDefinitions()` baris 681 | Tinggi |
| F5 | Catalog mode `UNKNOWN`/`OFF_TOPIC` short-circuit menelan order text yang valid | `AiAgentService::processMessage` baris 192-202 | Tinggi |
| F6 | Tidak ada idempotency untuk webhook `type=text` (sudah ada untuk `type=order`) | `WhatsAppWebhookController` baris ~330-447 | Tinggi |
| F7 | `set_delivery_type` tool deskripsi tabrak state machine (LLM disuruh tanya, sementara state machine pakai tombol) | `AiAgentService::getToolDefinitions()` baris 743 | Sedang |
| F8 | Drip prompt (`sendDripPrompt`) tidak punya cooldown — bisa flooding | `CatalogOrderFlowService::sendDripPrompt` baris 1178 | Sedang |

---

## Rencana Perubahan

### F1 — Intent Detection Tunggal

**Sekarang:** Dua mekanisme:
- `UserIntent::detect()` (regex enum) — dipakai untuk gating tools & short-circuit.
- `isOrderMenuIntent()` & `isNextMenuPageIntent()` (service methods) — dipakai untuk hard guard & deterministic pagination.

Keyword tumpang tindih → klasifikasi bisa berbeda untuk pesan yang sama. Contoh: pesan "lihat selanjutnya" → `UserIntent::NEXT_MENU_PAGE` (enum), tapi `isOrderMenuIntent()` juga bisa kena di kondisi lain. Inconsistency.

**Setelah:**
- **Satu sumber kebenaran**: `UserIntent::detect()`. Hapus `isOrderMenuIntent()` dan `isNextMenuPageIntent()`.
- Semua kode pengecekan intent panggil enum: `if ($intent === UserIntent::NEXT_MENU_PAGE)`.
- Detection dipanggil **sekali** di awal `processMessage()` dan disebar via variable, bukan dipanggil ulang.

**Risk:** Beberapa branch lama mengandalkan regex `isOrderMenuIntent()` yang lebih agresif daripada `UserIntent::detect()`. Mitigasi: gabungkan keyword `isOrderMenuIntent()` ke regex `UserIntent::detect()` sebelum hapus method service.

### F2 — Perbaiki Klasifikasi "alamat" (akar `BUSINESS_INFO` ambigu)

**Sekarang:** Komentar di baris 780 confess: "BUSINESS_INFO is intentionally excluded from this list: 'alamat' in a delivery address message gets misclassified". Workaround: biarkan BUSINESS_INFO intent dipanggil dengan tools. Akibat: semua pertanyaan jam buka / lokasi pun bawa daftar tools (boros token + naikkan risiko hallucinated tool call).

**Setelah:**
- Perkenalkan **context-aware intent**: detection mempertimbangkan `flow_state` + `cart not empty`.
  - Saat `STATE_AWAITING_DELIVERY_INFO` atau `cart non-empty + checkout signal` → "alamat" diklasifikasikan `ORDER` (delivery address), bukan BUSINESS_INFO.
  - Selain itu → "alamat" = BUSINESS_INFO (tanya lokasi kafe).
- Rapihkan: BUSINESS_INFO **tidak butuh tools** lagi (`intentNeedsTools()` exclude BUSINESS_INFO).

**Manfaat:** Hemat token + LLM tidak tergoda call tool saat customer cuma tanya alamat.

**Signature:** `UserIntent::detect(string $message, ?array $context = null)` — context optional, isi `['flow_state' => ..., 'has_cart' => bool]`.

### F3 — Hapus Legacy `pending_order` Path

**Sekarang:** `processMessage` baris 222-243 cek `$conversation->getPendingOrder()` dan `isConfirmation($messageText)` untuk konfirmasi. Ini paralel dengan `STATE_CONFIRMING_ORDER_SUMMARY` (state machine modern).

Investigasi awal: kemungkinan `pending_order` adalah jalur sebelum state machine ada. Belum semua call-site di-migrasi.

**Setelah:**
1. **Audit** semua tempat `setPendingOrder()` & `getPendingOrder()` dipanggil. Petakan apakah ada flow yang masih bergantung.
2. Kalau dead path: hapus seluruh field + method + branch di `processMessage`.
3. Kalau masih ada use case (mis. konfirmasi non-state-machine): migrasikan ke state machine resmi atau pisahkan jadi mekanisme bernama jelas (bukan "pending order" yang generic).

**Risk:** Tinggi — hapus jalur yang masih hidup → customer stuck. Mitigasi: feature flag eksperimen + monitoring 1 minggu sebelum hapus permanen.

### F4 — `add_to_cart` Tool: Enforce Match Confidence

**Sekarang:** Tool description: `"DIRECTLY add items to cart by product name. Auto-searches product. Use this IMMEDIATELY when user wants to order - NO need to search first!"`. Fuzzy match silent + LLM didorong agresif → salah pilih produk.

**Setelah:**
- Tool description: ganti jadi netral — `"Add items to cart by product name. Returns error with alternatives if product not found or ambiguous."`
- Handler `addToCart()`:
  - Match confidence < threshold (mis. similarity < 0.8) → return error ke LLM dengan list 3 produk paling mirip.
  - Match ambiguous (multiple candidates similar) → return error: pilih satu.
  - LLM lalu balas customer minta klarifikasi (atau coba ulang dengan nama lebih spesifik).
- Tambah log: `Log::info('add_to_cart resolved', ['input' => ..., 'matched' => ..., 'confidence' => ...])`.

**Manfaat:** Hallucination "produk tidak ada → ditambahkan ke cart pakai produk acak" hilang.

### F5 — Pisahkan Fallback Catalog vs POS Mode

**Sekarang:** Baris 192-202 — di **catalog mode**, intent `UNKNOWN`/`OFF_TOPIC` di-short-circuit ke fallback. Aman untuk catalog (tap-driven). Tapi di POS mode, pesan order valid yang ter-misclassify `UNKNOWN` (mis. nama produk yang tidak match keyword regex) **harus** mencapai LLM untuk dievaluasi.

**Setelah:**
- Short-circuit `UNKNOWN`/`OFF_TOPIC` **hanya** dieksekusi di catalog mode (sudah benar di kode sekarang — tapi audit ulang setelah F1).
- Di POS mode, `UNKNOWN` selalu lanjut ke LLM (dengan tools aktif).
- Di **catalog mode**, sediakan opsi: customer ketik nama produk → fallback (kalau ada match di catalog) sarankan tap menu, bukan auto-add. UX teks-order tetap tidak ada di catalog mode (intentional), tapi customer dapat hint yang lebih helpful.

### F6 — Idempotency untuk Webhook Text

**Sekarang:** `routeToCatalogFlow` punya dedup untuk `type=order` (baris ~479-487). Tapi `type=text` tidak — Meta retry → AI agent reply 2x.

**Setelah:**
- Pindah dedup ke awal `handleMessage()`, sebelum routing:
  ```
  if ($messageId && WhatsAppMessage::where('message_id', $messageId)->exists()) {
      Log::info('Duplicate webhook ignored', [...]);
      return $messageData;
  }
  ```
- Konstrain UNIQUE di kolom `whatsapp_messages.message_id` kalau belum ada (cek migrasi).

**Manfaat:** Tidak ada double reply karena retry Meta.

### F7 — `set_delivery_type` Tool: Hapus Konflik dengan State Machine

**Sekarang:** Tool deskripsi: `"Ask user Pickup or Delivery first. If delivery, also ask address."` → LLM disuruh menanyakan via teks. Sementara state machine pakai tombol (Flow 5).

**Setelah:**
- Saat `isOrderEnabled() = true`, tool `set_delivery_type` **dihilangkan** dari tools yang dipasang ke LLM. Cart confirmation → state machine ambil alih → tombol fulfillment.
- LLM hanya bertanggung jawab untuk fase **pre-cart**: menambah/menghapus item, jawab pertanyaan. Setelah `cart not empty + checkout signal`, kontrol diserahkan ke state machine.

**Konsekuensi:** Sederhanakan tool set LLM. Mengurangi conflict mental model.

### F8 — Drip Cooldown

Drip handling didetailkan di `follow-up-plan.md`. Tapi cooldown minimal **wajib** ditambahkan sebagai bagian dari flow:
- `sendDripPrompt()` cek `conversation.last_drip_at` — kalau < 60 detik lalu, skip.
- Reset `last_drip_at` setiap incoming customer message.

---

## Validasi (Kriteria Selesai)

- [ ] Grep clean: tidak ada method `isOrderMenuIntent()` / `isNextMenuPageIntent()` lagi.
- [ ] Test: pesan "alamat kafe dimana" di percakapan kosong → BUSINESS_INFO + LLM tanpa tools.
- [ ] Test: pesan "alamat" di state `AWAITING_DELIVERY_INFO` → ditangani state machine, tidak ke LLM.
- [ ] Test: `pending_order` field tidak lagi di-set di mana pun (atau di-rename + di-isolasi).
- [ ] Test: `add_to_cart` dengan produk yang tidak ada → LLM dapat error + list alternatif.
- [ ] Test: 2× POST webhook dengan `message_id` sama → hanya 1 reply ke customer.
- [ ] Audit chat.js: 10 kasus halusinasi sebelumnya — verifikasi semuanya tidak terjadi lagi.

---

## Risk & Rollback

| Risk | Mitigasi | Rollback |
|---|---|---|
| Hapus legacy `pending_order` putus jalur konfirmasi | Feature flag + 1 minggu monitoring | Revert + restore field |
| `add_to_cart` lebih strict → "false negative" untuk produk dengan typo customer | Threshold di-tune via metric (false-positive vs false-negative). Tambah suggester | Turunkan threshold |
| Idempotency salah dedup pesan unik | Konstrain UNIQUE di DB cegah salah dedup. Jangan dedup berdasarkan content | Drop dedup |

---

## Dependensi Antar Plan

- **Mengandalkan `config-plan.md`** untuk:
  - `getFeatureStatus()` (C1) — pengganti `isQrisEnabled()` dll yang lebih bermakna.
  - `AiAgentSettings` (C5) — schema settings.
- **Memblokir `follow-up-plan.md`** dan `drip-message-plan.md` — keduanya bergantung pada state machine yang sudah konsisten.

**Urutan implementasi:** config-plan dulu (sebagian) → flow-plan → follow-up & drip.

---

## Files yang Akan Disentuh

```
app/Enums/UserIntent.php                            # signature + context-aware
app/Services/AiAgentService.php                     # hapus duplikat method, sederhanakan branch
app/Services/CatalogOrderFlowService.php            # drip cooldown
app/Http/Controllers/Api/WhatsAppWebhookController.php  # global idempotency dedup
app/Models/AiAgentConversation.php                  # last_drip_at field
database/migrations/*_add_last_drip_at_to_conversations.php
database/migrations/*_unique_message_id_on_whatsapp_messages.php
```

Tidak menyentuh: drip scheduling, follow-up scheduler, prompt builder.
