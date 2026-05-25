# AI Agent — Config Merchant

Dokumen ini mendeskripsikan **semua konfigurasi yang merchant atur** lewat dashboard
`/dashboard/ai-agent`, dan **bagaimana setiap konfigurasi mengubah sikap AI agent**.

Konfigurasi disimpan di tabel `ai_agents` (model `App\Models\AiAgent`). Method evaluasi
ada di model itu sendiri (`isOrderEnabled()`, `isQrisEnabled()`, dll) — itulah satu-satunya
sumber kebenaran. **Jangan** baca field mentah langsung di service; selalu pakai method
model supaya cek dependensi (sub-merchant aktif, reservation config aktif, dll) ikut tervalidasi.

---

## 1. Daftar Field Konfigurasi

### 1.1 Status Agent

| Field | Tipe | Default | Fungsi |
|---|---|---|---|
| `is_active` | bool | `true` | Master switch. Kalau `false`, webhook tidak akan dispatch ke job AI agent sama sekali (lihat `WhatsAppWebhookController::handleMessage` baris 396). |

### 1.2 Bot Identity

| Field | Tipe | Wajib | Fungsi |
|---|---|---|---|
| `bot_name` | string | ya | Nama yang AI gunakan untuk memperkenalkan diri. Disisipkan ke system prompt + dipakai `buildGreeting()`. |
| `system_prompt` | text | ya | Instruksi dasar perilaku bot. Diolah lagi oleh `AiAgentPromptBuilder` ditambah business info, intent context, dan tool hints. |

### 1.3 Business Information

Disimpan di kolom JSON `business_info` (cast `array`). Diinjeksi ke system prompt
hanya saat intent membutuhkannya (`UserIntent::needsBusinessInfo()` = `GREETING` atau `BUSINESS_INFO`).

| Key | Fungsi |
|---|---|
| `operating_hours` | Jam operasional, dijawab saat customer tanya jam. |
| `phone` | Nomor kontak. |
| `address` | Alamat fisik. |
| `description` | Deskripsi bisnis. |

### 1.4 Order Feature

| Field | Tipe | Default | Fungsi |
|---|---|---|---|
| `order_enabled` | bool | `false` | Mengaktifkan tool order (`add_to_cart`, `get_cart_summary`, `confirm_order`, dll). |
| `default_store_id` | int | null | **Wajib** kalau `order_enabled = true`. Tanpa store, `isOrderEnabled()` return `false` walau toggle ON. |
| `catalog_id` | string | null | Meta Catalog ID. Kalau di-set, agent berjalan dalam mode **CATALOG** (UI MPM tap-driven). Kalau null/empty, mode **POS-TEXT** (LLM + tool calls). |

> `isOrderEnabled() = order_enabled && default_store_id !== null`
> `isCatalogActive() = !empty(catalog_id) && config('catalog.meta_enabled')`

### 1.5 QRIS Payment

| Field | Tipe | Default | Fungsi |
|---|---|---|---|
| `qris_enabled` | bool | `false` | Mengaktifkan QRIS otomatis di flow pembayaran. |

> `isQrisEnabled() = qris_enabled && hasActiveSubMerchant()`
> Sub-merchant aktif (tabel `sub_merchants`, `is_active = true`) wajib. Tanpa itu, walau toggle ON, QRIS dianggap nonaktif.

### 1.6 Reservation

| Field | Tipe | Default | Fungsi |
|---|---|---|---|
| `reservation_enabled` | bool | `false` | Mengaktifkan tombol "Reservasi" di pilihan fulfillment. |

> `isReservationEnabled() = reservation_enabled && hasReservationConfig()`
> `ReservationConfig` aktif (tabel `reservation_configs`, `is_active = true`) wajib.

### 1.7 Delivery

| Field | Tipe | Default | Fungsi |
|---|---|---|---|
| `delivery_enabled` | bool | `false` | Mengaktifkan tombol "Delivery" di pilihan fulfillment. |
| `default_ongkir` | decimal | `0` | Ongkir flat yang ditambahkan saat customer pilih Delivery. |

### 1.8 Optimisasi (advanced)

| Field | Tipe | Default | Fungsi |
|---|---|---|---|
| `use_optimized_prompt` | bool | `true` | Pakai builder optimasi (intent-based prompt) vs prompt monolitik. |
| `enable_prompt_caching` | bool | `false` | Cache bagian statis prompt selama 1 jam (Laravel Cache). |
| `use_toon_format` | bool | `false` | Pakai format TOON (token-optimized) untuk daftar produk. |
| `product_sample_limit` | int | 10 | Jumlah produk yang disertakan di prompt context (bukan di tool result). |
| `settings` | json | `{}` | Bebas, untuk pengaturan eksperimen. |

---

## 2. Matriks Sikap AI Agent berdasarkan Config

Tiap baris adalah **kombinasi config** yang menentukan apa yang customer lihat.

### 2.1 Saat customer membuka chat (greeting)

| `order_enabled` | `catalog_id` | Yang ditampilkan |
|---|---|---|
| OFF | — | Greeting teks saja, **tanpa** tombol "Lihat Menu". |
| ON | empty | Greeting + tombol "Lihat Menu" → tap = trigger LLM dengan "menu" → render daftar produk POS. |
| ON | filled | Greeting + tombol "Lihat Menu" → tap = `sendCatalog()` (Meta Catalog MPM). |

### 2.2 Saat customer minta lihat menu / order

| Mode | Hasil |
|---|---|
| **Order OFF** | Reply teks: "Maaf, fitur pemesanan belum aktif." (lihat `getOrderDisabledMessage()`). |
| **Order ON, Catalog active** | Kirim katalog Meta (MPM). LLM tidak dilibatkan. |
| **Order ON, Catalog inactive** | LLM dipanggil dengan tools (`get_all_products`, `add_to_cart`, dll). |

### 2.3 Setelah cart terisi → pilih fulfillment

Tombol yang muncul di `sendFulfillmentButtons()`:

| `delivery_enabled` | `reservation_enabled` | Tombol yang dirender |
|---|---|---|
| OFF | OFF | `[ Pickup ]` |
| ON  | OFF | `[ Pickup ][ Delivery ]` |
| OFF | ON  | `[ Pickup ][ Reservasi ]` |
| ON  | ON  | `[ Pickup ][ Delivery ][ Reservasi ]` |

> Pickup selalu ada karena dianggap default fulfillment yang tidak butuh konfigurasi tambahan.
> Max 3 tombol (batasan WhatsApp Interactive Button). Kalau ada >3 di masa depan, perlu pakai list reply.

### 2.4 Setelah customer pilih fulfillment

| Pilihan | `qris_enabled` | Hasil |
|---|---|---|
| Pickup | OFF | Order dibuat → diarahkan ke kasir (pembayaran manual). |
| Pickup | ON  | Order dibuat → QRIS dibuatkan → link pembayaran dikirim. |
| Delivery | OFF | Minta info delivery → konfirmasi → order dibuat (pembayaran manual). |
| Delivery | ON  | Minta info delivery → konfirmasi → order + QRIS. |
| Reservasi | — | Trigger reservation flow (link form / WhatsApp Flow). QRIS tidak relevan di sini. |

### 2.5 Tool definitions yang dipasang ke LLM

`getToolDefinitionsForAgent($aiAgent)` (line ~794):

| Kondisi | Tools yang aktif |
|---|---|
| `isOrderEnabled() = false` | **null** (LLM dipanggil tanpa tools — hanya bisa balas teks). |
| `isOrderEnabled() = true`  | `get_all_products`, `add_to_cart`, `remove_from_cart`, `clear_cart`, `get_cart_summary`, `confirm_order`, `set_delivery_type`, `set_order_notes`. |
| ditambah `isQrisEnabled() = true` | Plus tool QRIS (`generate_qris`, `check_payment_status`). |

Lebih lanjut, **intent-based tool gating** (`intentNeedsTools()`):

| Intent | LLM dipanggil dengan tools? |
|---|---|
| GREETING | tidak (short-circuit sebelum LLM) |
| OFF_TOPIC | tidak |
| BUSINESS_INFO | **iya** (karena pesan "alamat" untuk delivery ada di intent ini juga) |
| VIEW_MENU / SEARCH_PRODUCT / NEXT_MENU_PAGE | iya |
| ORDER / VIEW_CART / CHECKOUT | iya |
| UNKNOWN | iya |

---

## 3. Dependensi Eksternal (yang merchant juga harus setup)

Toggle di dashboard tidak cukup — beberapa fitur butuh artefak lain:

| Fitur | Artefak yang harus ada | Tabel |
|---|---|---|
| QRIS | `SubMerchant` aktif | `sub_merchants` |
| Reservasi | `ReservationConfig` aktif | `reservation_configs` |
| Order | Store dipilih di `default_store_id` | `stores` |
| Delivery | tidak ada — cukup toggle | — |
| Catalog | `catalog_id` di-set + `WhatsAppAccount` linked ke Meta Catalog | `whatsapp_accounts.waba_id` |

Kalau toggle ON tapi artefak tidak ada, method model akan return `false` (silent degrade).
Dashboard sudah memperlihatkan warning ("Sub Merchant belum dibuat" / "Konfigurasi reservasi belum dibuat") untuk kasus ini.

---

## 4. Aturan Penambahan Config Baru

Sebelum menambah field baru di `ai_agents`, periksa dulu:

1. **Apakah cukup pakai `settings` json?** Field bebas-bentuk untuk eksperimen sebaiknya masuk ke sana, bukan kolom baru.
2. **Apakah butuh validator dependensi?** Kalau ya, tambahkan method `isXEnabled()` di model — jangan biarkan service membaca field mentah.
3. **Apakah mempengaruhi tool definitions?** Update `getToolDefinitionsForAgent()` dan dokumentasikan di tabel 2.5.
4. **Apakah mempengaruhi tombol/UX flow?** Update [flow.md](./flow.md) di flow yang sesuai.
5. **Apakah punya intent baru?** Tambah case di `UserIntent` enum dan update `intentNeedsTools()`.

---

## 5. Sumber Kebenaran (jangan duplikasi)

| Pertanyaan | Sumber |
|---|---|
| "Apakah QRIS aktif?" | `$aiAgent->isQrisEnabled()` |
| "Apakah delivery aktif?" | `$aiAgent->isDeliveryEnabled()` |
| "Apakah reservasi aktif?" | `$aiAgent->isReservationEnabled()` |
| "Apakah catalog mode atau POS mode?" | `$aiAgent->isCatalogActive()` |
| "Apakah order aktif?" | `$aiAgent->isOrderEnabled()` |
| "Status feature secara umum + reason?" | `$aiAgent->getFeatureStatus()` (returns `['order'=>['enabled'=>bool, 'reason'=>string], ...]`) |
| "Catalog locked oleh platform admin?" | `$aiAgent->isCatalogPlatformLocked()` (= !`config('catalog.meta_enabled')`) |
| "Kenapa catalog tidak aktif?" | `$aiAgent->getCatalogUnavailableReason()` (`platform_locked`/`toggle_off`/`no_catalog_id`/`null`) |

**Dilarang** mengulangi logic ini di service (mis. `if ($aiAgent->qris_enabled && $hasSubMerchant)`). Selalu panggil method model — kalau ada dependensi baru di masa depan, perubahan ke method otomatis berlaku di semua call site.

`hasActiveSubMerchant()` dan `hasReservationConfig()` di-cache 5 menit dengan
`SubMerchantObserver` + `ReservationConfigObserver` untuk invalidate saat row
saved/deleted — bukan stale-able, refresh otomatis saat merchant ubah artefak.

---

## 6. Struktur Service AI Agent

Setelah refactor Phase 1-6, service AI agent dipecah jadi sub-folder berdasarkan
domain (sesuai aturan 1 & 2 di `development/aturan.md`):

```
app/Services/AiAgent/                       ← root namespace, lihat ai-agent.md
├── Reply/         ReplySender              (kirim WhatsApp text)
├── LLM/           LlmClient                (BytePlus ARK + retry)
├── Intent/        IntentRouter             (5 short-circuit pre-LLM)
├── Tools/         ProductResolver, CatalogTools, CartTools, ToolDispatcher
├── Checkout/      CheckoutTools            (confirm_order, set_notes)
├── Payment/       PaymentTools             (generate_qris, check_status)
├── Catalog/       DeliveryInfoParser, OrderCreator, CatalogMessageRenderer
├── Followup/      FollowupScheduler        (state-aware ping)
└── Drip/          DripScheduler, DripContentRenderer  (longer-term sequence)
```

`AiAgentService.php` (sekarang 596 LOC dari 2920 awal) hanya orchestrator —
tidak ada lagi tool dispatch, LLM HTTP, atau render logic di dalamnya.
`CatalogOrderFlowService.php` (964 LOC dari 1880 awal) hanya state machine
orchestrator + `sendCatalog` Meta API.
