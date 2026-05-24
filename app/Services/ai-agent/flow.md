# AI Agent — Flow & Percabangan

Dokumen ini memetakan **alur lengkap** dari pesan masuk WhatsApp sampai balasan keluar,
termasuk semua titik percabangan berdasarkan config merchant (lihat [config.md](./config.md))
dan state percakapan.

Tujuan: menjadi referensi tunggal "di mana flow X diputuskan, dan apa kondisi cabangnya".
Setiap perubahan logika alur **wajib** memperbarui dokumen ini.

---

## 0. Peta Tingkat Tinggi

```
┌─────────────────────────────────────────────────────────────┐
│ Meta WhatsApp Cloud API webhook                              │
│  POST /api/whatsapp/webhook                                  │
└────────────────────────┬────────────────────────────────────┘
                         ▼
        WhatsAppWebhookController::handle()
                         │
       ┌─────────────────┼────────────────────┐
       ▼                 ▼                    ▼
   message.type:    message.type:        message.type:
   "order"          "interactive"        "text"
   (Catalog MPM     (Button reply)       (Free text)
    submit)
       │                 │                    │
       └────────┬────────┘                    │
                ▼                             ▼
   CatalogOrderFlowService             Conversation has
   (state machine, sync)               flow_state ?
                                       ┌──────┴──────┐
                                       ▼             ▼
                                   YES = state    NO = open
                                   takeover       ┌─────────────────┐
                                                  ▼                 │
                                          UserIntent::detect()      │
                                                  │                 │
                                  ┌───────────────┼───────────┐     │
                                  ▼               ▼           ▼     │
                              GREETING      VIEW_MENU/      ORDER/ ◀┘
                              short-circuit  catalog mode?  CHECKOUT
                                  │           ┌─┴─┐         /others
                                  ▼           ▼   ▼            │
                              Flow 1     Flow 2a Flow 2b       ▼
                                         (Catalog)(POS)    Flow 3+
                                                           (LLM + tools)
```

---

## Daftar Flow

| # | Nama | Trigger | Output |
|---|---|---|---|
| 0 | Routing & Guard | Setiap pesan masuk | Memilih flow lanjutan |
| 1 | Greeting | Intent `GREETING` | Salam + tombol Lihat Menu |
| 2a | Lihat Produk (Catalog mode) | Intent `VIEW_MENU` & `isCatalogActive()` | Meta Catalog MPM |
| 2b | Lihat Produk (POS mode) | Intent `VIEW_MENU` & catalog inactive | LLM call → tool `get_all_products` |
| 2c | Order Disabled | Intent menu/order saat `isOrderEnabled() = false` | Reply "fitur belum aktif" |
| 3 | Order ke Cart | Intent `ORDER` (POS mode) | LLM tool `add_to_cart` |
| 4 | Konfirmasi Cart | Customer ketik "selesai/checkout" / klik Konfirmasi | `STATE_CONFIRMING_CART` |
| 5 | Pilih Fulfillment | Tombol Konfirmasi cart | `STATE_AWAITING_FULFILLMENT` |
| 6 | Delivery Info | Pilih tombol Delivery | `STATE_AWAITING_DELIVERY_INFO` → `STATE_CONFIRMING_DELIVERY_INFO` |
| 7 | Konfirmasi Order Summary | Pilih Pickup / konfirmasi delivery | `STATE_CONFIRMING_ORDER_SUMMARY` |
| 8 | Pembayaran (QRIS atau Manual) | Konfirmasi order summary | `STATE_AWAITING_PAYMENT` atau done |
| 9 | Reservasi | Pilih tombol Reservasi | Trigger reservation form/flow |
| X1 | Cancel (universal escape) | "batal/cancel/stop" / tombol Batal | Clear semua state |
| X2 | Resume (drip) | Tombol "Lanjutkan" saat ada flow tertunda | Re-prompt step saat ini |

---

## Flow 0 — Routing & Guard

**Lokasi:** `WhatsAppWebhookController::handleMessage()` (baris ~330-447) + `AiAgentService::processMessage()` (baris ~57-200).

**Urutan keputusan:**

1. **Bukan pesan dari customer?** (echo / status update) → drop.
2. **AI Agent tidak aktif** (`is_active = false` atau tidak ada `AiAgent` untuk account) → simpan pesan, **tidak balas**.
3. **Kontak punya `ai_active = false`** (manual takeover) → simpan pesan, **tidak balas**. *(Override per-contact, mis. ketika CS manual ambil alih.)*
4. **`routeToCatalogFlow()`** — apakah pesan ini konsumsi state machine? Cek:
   - `type === "order"` → consumed (Flow 4 via `handleCatalogOrderReceived`).
   - `type === "interactive.button_reply"` & conversation punya `flow_state` → consumed (lihat Flow 5-8).
   - `type === "text"` & `flow_state === STATE_AWAITING_DELIVERY_INFO` → consumed (Flow 6).
5. **Catalog flow ambil alih (`flow_state !== null`)** di `AiAgentService::processMessage` baris 86:
   - Kalau pesan teks **bukan** "batal/cancel" → reply drip "Sedang di tahap X, tap Lanjutkan...".
   - "batal/cancel/stop" → cancel universal (Flow X1).
6. **Kalau lolos semua di atas** → dispatch `ProcessAiAgentMessage` job ke queue `ai-agent`. Eksekusi job memanggil `AiAgentService::processMessage` yang akan jalankan Flow 1+ berdasarkan intent.

---

## Flow 1 — Greeting

**Trigger:** `UserIntent::detect($msg) === GREETING`
  (regex: `^(hai|halo|hi|hello|hei|assalamualaikum)` tanpa keyword menu/order setelahnya).

**Cabang config:**

| Kondisi | Output |
|---|---|
| `isOrderEnabled() = true` | `buildGreeting()` + **tombol "Lihat Menu"** via `sendGreetingWithMenuButton()`. |
| `isOrderEnabled() = false` | Greeting teks saja (LLM dipanggil tanpa tools). |

**Side effect:**
- Tambahkan ke `conversation.messages` (`human` + `ai`).
- **Short-circuit** — LLM **tidak** dipanggil di branch ini (token saving).

**Lokasi:** `AiAgentService::processMessage` baris 179-186; `CatalogOrderFlowService::sendGreetingWithMenuButton` baris 159.

---

## Flow 2 — Lihat Produk

### 2a — Catalog Mode

**Trigger:** Intent salah satu dari `VIEW_MENU`, `ORDER`, `NEXT_MENU_PAGE`, `SEARCH_PRODUCT` **dan** `isCatalogActive() = true`.

**Output:** `sendCatalog($account, $contact, $aiAgent)` — kirim interactive message berisi Meta Catalog MPM. Customer memilih produk dengan tap, hasil pilihan kembali sebagai webhook `type=order` → Flow 4.

**Lokasi:** `AiAgentService::processMessage` baris 167-174.

### 2b — POS Mode

**Trigger:** Intent sama tapi `isCatalogActive() = false`.

**Output:** LLM dipanggil dengan tools. LLM akan call `get_all_products` (page=1) dan render daftar produk dari database POS (`stores.products`).

- Pagination: customer ketik "menu lainnya" / "selanjutnya" → intent `NEXT_MENU_PAGE` → **deterministic shortcut** `handleNextMenuPage()` tanpa LLM (baris 246-250).
- Search: customer sebut nama produk → LLM bisa call `get_all_products` dengan `search` param, atau langsung `add_to_cart` (Flow 3).

### 2c — Order Disabled

**Trigger:** `isOrderEnabled() = false` **dan** `isOrderMenuIntent($msg) = true`
  (keyword: menu, produk, daftar, pesan, beli, order, keranjang, cart, checkout, dll).

**Output:** Reply `getOrderDisabledMessage()`:
- Kalau `isReservationEnabled() = true`: "Saat ini hanya melayani reservasi. Tap untuk reservasi: <link>"
- Kalau tidak: "Maaf, pemesanan via chat belum aktif. Hubungi <phone>."

**LLM tidak dipanggil.** Hard guard di baris 256-269.

---

## Flow 3 — Order ke Cart (POS Mode)

**Trigger:** Intent `ORDER` (atau bahkan `UNKNOWN` yang ternyata sebutkan nama produk) di POS mode, dan `isOrderEnabled() = true`.

**Mekanisme:**
1. Pesan teruskan ke LLM (`callLLM`) dengan tools aktif.
2. LLM diharapkan call tool `add_to_cart` dengan `items: [{product_name, quantity}, ...]`.
3. `executeToolCall()` dispatch ke `addToCart()` — auto-search produk by name (fuzzy), tambahkan ke `conversation.cart`.
4. LLM lanjut generate reply text yang konfirmasi penambahan + subtotal.

**Catatan:** Customer **tidak masuk** ke `flow_state` apapun di sini — cart masih bisa diedit bebas via teks.

---

## Flow 4 — Konfirmasi Cart

**Trigger ada dua cabang:**

### 4a — Catalog Mode (webhook `type=order`)

`handleCatalogOrderReceived` → konversi `message.order.product_items` menjadi cart catalog → `setCatalogItems()` → `sendCartConfirmation()` (tombol Konfirmasi / Batal).

State setelah: `STATE_CONFIRMING_CART`.

### 4b — POS Mode (customer minta checkout)

Trigger di `AiAgentService::processMessage` baris 210-220:
- Intent `CHECKOUT`, **atau**
- Regex match: `selesai|sudah|udah|cukup|itu saja|itu aja|lanjut(kan)?`.

→ `startPosOrderFlow()` → konversi `conversation.cart` → `sendCartConfirmation()`.

State setelah: `STATE_CONFIRMING_CART`.

> **Convergence point**: catalog dan POS bertemu di state `STATE_CONFIRMING_CART`. Mulai dari sini, alur identik.

**Aksi pada state ini:**
- Tombol **✅ Konfirmasi** → set state `STATE_AWAITING_FULFILLMENT` → Flow 5.
- Tombol **❌ Batal** → Flow X1.

---

## Flow 5 — Pilih Fulfillment

**State:** `STATE_AWAITING_FULFILLMENT`

**Output:** `sendFulfillmentButtons()` — tombol berbeda tergantung config:

| `delivery_enabled` | `reservation_enabled` | Tombol |
|---|---|---|
| OFF | OFF | `[ 🏪 Pickup ]` |
| ON  | OFF | `[ 🏪 Pickup ][ 🚚 Delivery ]` |
| OFF | ON  | `[ 🏪 Pickup ][ 📅 Reservasi ]` |
| ON  | ON  | `[ 🏪 Pickup ][ 🚚 Delivery ][ 📅 Reservasi ]` |

**Handler:** `handleFulfillmentChoice()` (baris 923).

| Tombol | Aksi | State setelah |
|---|---|---|
| Pickup | `setDeliveryType(PICKUP)`, `setOngkir(0)` | `STATE_CONFIRMING_ORDER_SUMMARY` → Flow 7 |
| Delivery | Cek `isDeliveryEnabled()` lagi (race guard); `setOngkir(default_ongkir)` | `STATE_AWAITING_DELIVERY_INFO` → Flow 6 |
| Reservasi | `triggerReservation()` | Flow 9 (state di-clear) |

---

## Flow 6 — Delivery Info

**State:** `STATE_AWAITING_DELIVERY_INFO` → `STATE_CONFIRMING_DELIVERY_INFO`

**Prompt:** `sendDeliveryInfoPrompt()` — "Kirim dalam satu pesan: Nama, Phone, Alamat, Catatan."

**Input customer:**
- Free-text (mis. `"Budi, 08123, Jl. Mawar 12, jangan pedas"`) → `parseDeliveryInfo()` heuristic.
- Labeled (`nama = Budi, alamat = ...`) → `extractLabeledFields()` — bisa partial update.
- `batal/cancel` → Flow X1.

**Output:** `sendDeliveryInfoSummary()` — tampilkan parsed fields + tombol ✏️ Edit / ✅ Konfirmasi.

State setelah parse: `STATE_CONFIRMING_DELIVERY_INFO`.

**Handler tombol:** `handleDeliveryInfoConfirmation()` baris 967.

| Tombol | Aksi | State setelah |
|---|---|---|
| ✏️ Edit | Re-prompt | `STATE_AWAITING_DELIVERY_INFO` (loop balik) |
| ✅ Konfirmasi | Simpan address & note | `STATE_CONFIRMING_ORDER_SUMMARY` → Flow 7 |

---

## Flow 7 — Konfirmasi Order Summary

**State:** `STATE_CONFIRMING_ORDER_SUMMARY`

**Output:** `sendOrderSummary()` — tampilkan items + subtotal + (ongkir kalau delivery) + total + tombol ✅ Konfirmasi / ❌ Batal.

**Handler:** `handleOrderSummaryConfirmation()` baris 1016.

| Tombol | Aksi |
|---|---|
| ✅ Konfirmasi | `createOrderAndRespond()` — buat record `Order` di DB → Flow 8 (payment). |
| ❌ Batal | Flow X1. |

---

## Flow 8 — Pembayaran

**Cabang berdasarkan `isQrisEnabled()`:**

### 8a — QRIS Aktif

1. `createOrderAndRespond()` → `Order` dibuat dengan status `pending_payment`.
2. Trigger `QrisService::createTransaction()` → dapat QR + payment URL.
3. Kirim QR image + link ke customer.
4. State: `STATE_AWAITING_PAYMENT`.
5. Polling status / webhook callback dari Midtrans → `sendPaymentConfirmation()`.
6. Saat paid: state cleared, kirim notifikasi sukses.
7. Saat expired: state cleared, kirim notifikasi & arahkan ke menu (lihat `processMessage` baris 106-129).

### 8b — QRIS Nonaktif (Manual)

1. `createOrderAndRespond()` → `Order` dibuat dengan status sesuai pickup/delivery.
2. Kirim "Pesanan dibuat. Silakan bayar di kasir / saat barang diantar."
3. **Tidak masuk** ke `STATE_AWAITING_PAYMENT`. State di-clear.

---

## Flow 9 — Reservasi

**Trigger:** Tombol Reservasi di Flow 5.

**Syarat:** `isReservationEnabled() = true` (`reservation_enabled` + `ReservationConfig` aktif).

**Output:**
- Kirim link form reservasi (`getReservationFormUrl()`), **atau**
- Trigger WhatsApp Flow (tergantung implementasi `triggerReservation()` di `CatalogOrderFlowService` baris ~1448).

**State:** flow_state di-clear setelah trigger (reservasi dilanjut di luar percakapan chat).

---

## Flow X1 — Cancel (universal escape)

**Trigger:**
- Tombol ❌ Batal di state manapun, **atau**
- Customer ketik `batal | cancel | stop | berhenti | gajadi | gak jadi | tidak jadi` saat `flow_state !== null`.

**Aksi:**
1. `clearFlowState()`
2. `clearCatalogItems()`
3. `clearDeliveryContext()`
4. `clearPendingOrder()`
5. `clearPaymentContext()` *(kalau di state payment)*

**Output:**
- Kalau cancel saat `STATE_AWAITING_PAYMENT`: pesan khusus — link QRIS tetap valid sampai expired.
- Selain itu: `sendCancelledReply()` → "Pesanan dibatalkan. Tap Lihat Menu untuk mulai lagi."

**Lokasi:**
- Cancel via teks: `AiAgentService::processMessage` baris 91-104.
- Cancel via tombol: `CatalogOrderFlowService::handleButtonReply` baris 598-617.

---

## Flow X2 — Resume / Drip

**Trigger:** Customer kirim pesan teks **bukan-cancel** saat `flow_state !== null` (state machine sedang menunggu tombol/input spesifik).

**Output:** `sendDripPrompt()` — "Sedang di tahap *<step label>*. Tap *Lanjutkan* untuk meneruskan, atau *Batal*."

**Tombol Lanjutkan:** Memanggil `resumeFlow()` (baris 1222) yang me-render ulang prompt step saat ini.

**Tujuan:** Mencegah LLM "ambil alih" saat customer di tengah state machine — menghindari hallucination berupa konfirmasi palsu / pesan duplikat.

---

## Lampiran A — Tabel State Machine

| Konstanta | Nilai | Deskripsi |
|---|---|---|
| `STATE_CONFIRMING_CART` | `confirming_cart` | Menunggu klik Konfirmasi cart. |
| `STATE_AWAITING_FULFILLMENT` | `awaiting_fulfillment_choice` | Menunggu pilih Pickup/Delivery/Reservasi. |
| `STATE_AWAITING_DELIVERY_INFO` | `awaiting_delivery_info` | Menunggu teks delivery info. |
| `STATE_CONFIRMING_DELIVERY_INFO` | `confirming_delivery_info` | Menunggu Edit/Konfirmasi delivery info. |
| `STATE_CONFIRMING_ORDER_SUMMARY` | `confirming_order_summary` | Menunggu Konfirmasi order summary. |
| `STATE_AWAITING_PAYMENT` | `awaiting_payment` | Menunggu pembayaran QRIS. |

## Lampiran B — Tabel Button IDs

| Konstanta | ID | Konteks |
|---|---|---|
| `BTN_SHOW_MENU` | `show_menu` | Greeting + fallback CTA. |
| `BTN_PICKUP` | `fulfill_pickup` | Pilih fulfillment. |
| `BTN_DELIVERY` | `fulfill_delivery` | Pilih fulfillment. |
| `BTN_RESERVASI` | `fulfill_reservasi` | Pilih fulfillment. |
| `BTN_EDIT_DELIVERY` | `delivery_edit` | Edit info delivery. |
| `BTN_CONFIRM_DELIVERY` | `delivery_confirm` | Konfirmasi info delivery. |
| `BTN_CONFIRM_ORDER` | `order_confirm` | Konfirmasi order summary. |
| `BTN_CANCEL_ORDER` | `order_cancel` | Cancel universal. |
| `BTN_CONFIRM_CART` | `cart_confirm` | Konfirmasi cart. |
| `BTN_CONTINUE_FLOW` | `flow_continue` | Drip resume. |

## Lampiran C — Tabel Intent

| Intent | Regex / Trigger | needsTools? |
|---|---|---|
| GREETING | `^(hai\|halo\|hi\|hello\|hei\|assalamualaikum)` tanpa keyword order | tidak |
| NEXT_MENU_PAGE | `menu (lainnya\|selanjutnya\|berikutnya\|lagi)`, `selanjutnya`, `next`, dll | iya |
| VIEW_MENU | `menu\|daftar\|list\|produk\|jual apa\|ada apa` | iya |
| VIEW_CART | `keranjang\|cart\|pesanan saya\|lihat pesanan` | iya |
| CHECKOUT | `checkout\|bayar\|konfirmasi\|lanjut\|proses` | iya |
| ORDER | `pesan\|beli\|order\|mau\|ambil` | iya |
| BUSINESS_INFO | `jam\|buka\|tutup\|alamat\|lokasi\|dimana\|kontak\|telepon` | **iya** (krn "alamat" delivery juga match) |
| OFF_TOPIC | keyword: `siapa presiden`, `chatgpt`, `claude`, `coding`, dll | tidak |
| UNKNOWN | fallback | iya |

---

## Lampiran D — Halangan Halusinasi yang Diketahui

Catatan kandidat-titik di mana legacy code masih menyebabkan halusinasi.
Akan dikonfirmasi via audit `chat.js`:

1. **Intent BUSINESS_INFO** dengan tools aktif — LLM bisa salah call `set_delivery_type` saat customer cuma tanya alamat kafe.
2. **POS mode setelah cart kosong** — kalau `cart` di-clear di tengah jalan, intent `CHECKOUT` masih bisa trigger `startPosOrderFlow` dengan cart kosong. Belum jelas siapa yang guard ini.
3. **Catalog mode + LLM short-circuit untuk UNKNOWN/OFF_TOPIC** — bagus untuk catalog, tapi belum diperiksa apakah pesan order valid di POS mode tidak terjebak di branch ini.
4. **Pending order legacy (`conversation.pendingOrder`)** — masih ada di baris 222-243 `processMessage`, kemungkinan jalur lama sebelum state machine. Perlu dicek apakah masih digunakan atau dead code.
5. **`isOrderMenuIntent()` vs `UserIntent::detect()` keyword overlap** — dua mekanisme deteksi intent yang berjalan paralel, rawan inkonsistensi.

Update lampiran ini setiap kali audit chat.js menemukan kasus halusinasi baru.
