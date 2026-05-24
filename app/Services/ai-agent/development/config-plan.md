# Config Plan — Penanganan Kelemahan Konfigurasi AI Agent

Dokumen ini adalah **tolak ukur rencana penerapan** untuk membersihkan kelemahan
di lapisan konfigurasi merchant. Setelah penerapan selesai, `config.md` di
folder induk akan diperbarui untuk mencerminkan state akhir.

> **Sumber temuan**: lihat ringkasan "Kelemahan Paling Vatal — Config" yang dirangkum
> dari analisa `app/Models/AiAgent.php` + `config.md`.

---

## Daftar Masalah yang Akan Ditangani

| # | Masalah | Lokasi sekarang | Tingkat |
|---|---|---|---|
| C1 | Silent-degrade saat artefak dependensi hilang | `AiAgent::isQrisEnabled()`, `isReservationEnabled()` | Tinggi |
| C2 | Global flag `config('catalog.meta_enabled')` mematikan catalog mode tanpa visibility di dashboard | `AiAgent::isCatalogActive()` | Tinggi |
| C3 | `product_sample_limit` di prompt → halusinasi produk yang tidak ada | `AiAgentPromptBuilder` + `getAllProducts()` | Tinggi |
| C4 | Field mentah `qris_enabled`, `delivery_enabled`, dll di-read langsung oleh beberapa service | `AiAgentService` (multiple lines) | Sedang |
| C5 | `settings` JSON bebas-bentuk tanpa schema → drift antar merchant | `AiAgent::$settings` | Rendah |
| C6 | Tidak ada cache untuk `hasActiveSubMerchant()`, `hasReservationConfig()` → query DB per pesan | `AiAgent::*` | Rendah |
| C7 | UI Katalog: dropdown tanpa status nonaktif eksplisit + tidak ada gate env-level | Dashboard `/dashboard/ai-agent` | Tinggi |
| C8 | Tidak ada field merchant-config untuk interval & jumlah follow-up | (belum ada) | Tinggi |

---

## Rencana Perubahan

### C1 — Eksplisit Status Fitur (bukan boolean tunggal)

**Sekarang:** `isQrisEnabled()` return `bool`. Saat `false`, tidak ada info mengapa.

**Setelah:** Method baru `getFeatureStatus(): array` per fitur yang return:
```
['enabled' => bool, 'reason' => 'ok' | 'toggle_off' | 'missing_submerchant' | 'missing_reservation_config' | 'missing_store']
```

**Manfaat:**
- Service bisa branch berdasarkan **alasan**, bukan boolean buta.
- Logging lebih bermakna: `Log::info('QRIS unavailable', ['reason' => 'missing_submerchant'])`.
- Dashboard bisa render warning yang akurat (sudah ada — tinggal di-sinkron dengan reason).

**Risk:** Refactor call-site lama. Mitigasi: pertahankan method lama (`isQrisEnabled()`) sebagai shim — `return $this->getFeatureStatus()['qris']['enabled']` — supaya migrasi bertahap.

### C2 — Pisahkan Global Flag dari Per-Merchant Config

**Sekarang:** `isCatalogActive() = !empty(catalog_id) && config('catalog.meta_enabled')`. Global flag bisa silently mematikan catalog untuk semua merchant.

**Setelah:**
- `isCatalogConfigured(): bool` — hanya cek `catalog_id`.
- `isCatalogActive(): bool` — cek global flag + configured.
- `getCatalogUnavailableReason(): ?string` — return `null | 'no_catalog_id' | 'global_disabled'`.
- Dashboard panggil `getCatalogUnavailableReason()` dan tampilkan banner kalau `global_disabled`.

**Manfaat:** Merchant tidak bingung saat catalog "tiba-tiba mati" tanpa mereka ubah apapun.

### C3 — Hilangkan Sample Produk dari Prompt

**Sekarang:** `AiAgentPromptBuilder` inject N produk ke system prompt (default 10). LLM "tahu" 10 produk, sisanya hanya via tool.

**Setelah:**
- **Tidak inject daftar produk ke prompt.** Sepenuhnya delegasi ke tool `get_all_products`.
- Tambah anti-hallucination guard di `executeToolCall('add_to_cart')`: produk yang tidak ditemukan via fuzzy match harus return error tegas ke LLM dengan list produk valid (top 5 alternatif). LLM diharapkan ulangi dengan nama yang benar atau minta klarifikasi customer.
- Hapus field `product_sample_limit` setelah migrasi semua call-site.

**Manfaat:** Source of truth tunggal (DB via tool). Halusinasi nama produk turun drastis.

**Risk:** Lebih banyak tool call → latency naik untuk pesan pertama. Mitigasi: `get_all_products` di-cache di Laravel Cache per `store_id` 5 menit.

### C4 — Banned: Akses Field Mentah di Service

**Aturan baru** (ditambahkan ke claude.md kalau perlu):
> Service **dilarang** membaca `$aiAgent->qris_enabled`, `$aiAgent->delivery_enabled`, dll. Selalu pakai method (`isQrisEnabled()`, `isDeliveryEnabled()`).

**Action:**
- Grep & ganti semua `\$aiAgent->qris_enabled` → `$aiAgent->isQrisEnabled()`.
- Grep & ganti semua `\$aiAgent->delivery_enabled` → `$aiAgent->isDeliveryEnabled()`.
- Tambah PHPStan rule (kalau ada) atau pre-commit grep guard.

### C5 — Schema untuk `settings` JSON

**Sekarang:** Bebas-bentuk, sulit di-track.

**Setelah:**
- Class `AiAgentSettings` (DTO atau Castable) dengan field tipe-aman:
  ```
  drip_enabled: bool                  // default true
  followup_enabled: bool              // default true
  followup_interval_minutes: int      // default 10, valid: 5–60
  followup_max_count: int             // default 3,  valid: 1–5
  followup_auto_cancel: bool          // default true (auto-cancel order setelah max tercapai)
  quiet_hours_start: string|null      // "22:00"
  quiet_hours_end: string|null        // "08:00"
  max_drips_per_24h: int              // default 3
  ```
- Cast di model: `'settings' => AiAgentSettings::class`.
- Migrasi data: existing `settings` JSON di-merge dengan default.

**Catatan:** Field-field di atas sebagian akan dipakai oleh follow-up & drip plan. Skema di sini = pondasi untuk plan lain. Detail `followup_interval_minutes` & `followup_max_count` lihat **C8**.

### C6 — Cache Dependency Check

**Sekarang:** Tiap pesan masuk → `hasActiveSubMerchant()` → query `SubMerchant` table.

**Setelah:**
- Cache hasil `hasActiveSubMerchant()`, `hasReservationConfig()` di Laravel Cache per `ai_agent_id` selama 5 menit.
- Invalidate cache di model events `SubMerchant::saved`, `ReservationConfig::saved`.

**Manfaat:** Drop ±2 query DB per pesan masuk.

### C7 — Catalog UI: Dropdown → Toggle + Env Gate

**Sekarang:**
- Dashboard `/dashboard/ai-agent` menampilkan **dropdown** "Katalog Meta" dengan opsi "-- Tidak menggunakan katalog (mode AI) --" sebagai cara mematikan catalog.
- Catalog mode dikendalikan oleh kombinasi `catalog_id` (per-merchant) + `config('catalog.meta_enabled')` yang membaca env var **`META_CATALOG`**.
- Env var `META_CATALOG` berfungsi sebagai **feature flag platform-level** untuk mengunci fitur catalog di production (karena masih beta).

**Setelah:**

1. **UI berubah jadi 2 komponen terpisah:**
   - **Toggle ON/OFF** "Enable Catalog Mode" (state per-merchant, field baru `ai_agents.catalog_enabled`).
   - **Dropdown "Pilih Catalog"** yang HANYA muncul saat toggle ON. Saat OFF, dropdown disembunyikan + `catalog_id` di-clear (NULL).
   - Saat toggle OFF, status di-render: **"Catalog Non-Aktif — AI Agent berjalan dalam mode AI text."**

2. **Env gate (platform-level kill-switch — REUSE existing):**
   - Env var: **`META_CATALOG`** (sudah ada di `.env`, sudah terkoneksi ke `config('catalog.meta_enabled')` via `config/catalog.php`). Tidak perlu env var baru.
   - `META_CATALOG=true` → toggle catalog di dashboard **aktif** (merchant bebas atur on/off + pilih `catalog_id`).
   - `META_CATALOG=false` → toggle di dashboard **disabled & locked OFF** + banner: *"Catalog mode dikunci oleh administrator (fitur beta).”* Dashboard tetap menampilkan toggle (read-only) supaya merchant tahu fitur ada tapi belum dibuka.

3. **`isCatalogActive()` dievaluasi sebagai:**
   ```php
   $envEnabled        = (bool) config('catalog.meta_enabled');   // dari META_CATALOG
   $merchantToggleOn  = (bool) $this->catalog_enabled;            // field bool baru
   $hasCatalogId      = ! empty($this->catalog_id);
   return $envEnabled && $merchantToggleOn && $hasCatalogId;
   ```

4. **Field DB baru:** `ai_agents.catalog_enabled` (bool, default `false`).
   - Migrasi: untuk row existing dengan `catalog_id IS NOT NULL`, set `catalog_enabled = true` (preserve current behavior).

5. **Method tambahan di model `AiAgent`:**
   - `isCatalogPlatformLocked(): bool` → `! config('catalog.meta_enabled')`. Dipakai dashboard untuk render banner & disable toggle.
   - `getCatalogUnavailableReason(): ?string` → `'platform_locked' | 'toggle_off' | 'no_catalog_id' | null`. Lebih informatif daripada boolean tunggal.

**Manfaat:**
- UX dashboard lebih jelas — toggle vs. dropdown punya intent berbeda (on/off vs. picker).
- Disconnect lama (UI dropdown menampilkan "tidak menggunakan" sebagai item dropdown) hilang.
- Platform admin tetap memegang kill-switch via `META_CATALOG` (untuk lock saat fitur masih beta).
- Tidak ada env var baru — leverage yang sudah ada.

**Catatan implementasi:**
- `.env.example` perlu di-update agar komentar `# Meta Catalog feature flag (true = enabled, false = locked in frontend)` ada — supaya operator masa depan paham.
- Frontend dashboard (Blade view) perlu render kondisional 3 state: `platform_locked`, `toggle_off`, `toggle_on_with_picker`.

### C8 — Merchant-Configurable Follow-up

**Sekarang:** Belum ada mekanisme follow-up sama sekali. Drip prompt lama (`sendDripPrompt`) dipanggil reaktif tanpa interval / jumlah maksimum.

**Setelah:**

Tambah 3 field konfigurasi merchant di `AiAgentSettings` (lihat C5):

| Field | Tipe | Default | Range | Fungsi |
|---|---|---|---|---|
| `followup_interval_minutes` | int | 10 | 5–60 | Jeda antar follow-up (semua state pakai interval yang sama). |
| `followup_max_count` | int | 3 | 1–5 | Maksimum follow-up sebelum aksi terminal. |
| `followup_auto_cancel` | bool | true | — | Setelah `max_count` tercapai: order/pesanan di-cancel & state di-clear. |

**Dashboard UI** (di `/dashboard/ai-agent`) — section baru "Follow-up Settings":

```
[ Enable Follow-up   (✓) ]

Interval Follow-up
  ( ) 5 menit
  ( ) 10 menit  ← default
  ( ) 15 menit
  ( ) 20 menit
  ( ) 30 menit
  ( ) 45 menit
  ( ) 60 menit

Jumlah Maksimum Follow-up Sebelum Pembatalan
  [ 3 ]  (1–5)

[ ✓ ] Auto-cancel pesanan setelah follow-up maksimum tercapai

Penjelasan: Customer akan menerima pesan pengingat setiap [N] menit jika tidak
membalas di tengah proses pemesanan. Setelah [M] pengingat tanpa respons,
pesanan akan dibatalkan otomatis.
```

**Side effect untuk plan lain:**
- `follow-up-plan.md` akan menggunakan field-field ini sebagai sumber timing (bukan hardcode 3 menit / 5 menit per state). Detail timing & auto-cancel di follow-up-plan.
- `drip-message-plan.md` Sequence B (Payment) yang sebelumnya hardcoded 5m & 15m akan **konsolidasi** ke `followup_interval_minutes` agar konsisten dengan state lain.

**Manfaat:**
- Merchant bisa tune urgensi pengingat sesuai karakter bisnis (kafe makan-di-tempat butuh follow-up lebih cepat; reservasi malam butuh lebih lama).
- Aturan "3× lalu cancel" eksplisit, deterministic, dan bisa di-audit per merchant.

---

## Validasi (Kriteria Selesai)

- [ ] Semua service tidak ada akses field mentah `$aiAgent->qris_enabled` dll (grep clean).
- [ ] `AiAgentPromptBuilder` tidak inject daftar produk lagi (test snapshot prompt).
- [ ] Test integrasi: ubah `catalog.meta_enabled=false` → `isCatalogActive()` return false + `getCatalogUnavailableReason()` return `'global_disabled'`.
- [ ] Test integrasi: hapus SubMerchant aktif → `getFeatureStatus()['qris']['reason']` return `'missing_submerchant'`.
- [ ] Performance: query count per pesan masuk turun (verifikasi via Laravel Telescope).
- [ ] Halusinasi nama produk hilang dalam audit 10 kasus chat.js.
- [ ] **C7**: Dashboard `/dashboard/ai-agent` — toggle Catalog ON → dropdown muncul, OFF → tersembunyi & status "Non-Aktif" tampil.
- [ ] **C7**: Set `META_CATALOG=false` di `.env` → toggle di dashboard disabled (read-only) + banner "Catalog mode dikunci oleh administrator (fitur beta)" tampil. `isCatalogActive()` return false meski merchant pernah toggle ON.
- [ ] **C7**: Set `META_CATALOG=true` → toggle bisa di-flip merchant; saat ON + `catalog_id` filled → `isCatalogActive() = true`.
- [ ] **C7**: Toggle merchant OFF → `catalog_id` di-clear; ON → dropdown muncul empty (kalau belum dipilih).
- [ ] **C7**: `getCatalogUnavailableReason()` return `'platform_locked'` saat `META_CATALOG=false`, `'toggle_off'` saat toggle OFF, `'no_catalog_id'` saat toggle ON tapi catalog belum dipilih, `null` saat semua valid.
- [ ] **C8**: Dashboard — pilihan interval 5/10/15/20/30/45/60 menit tersimpan ke `AiAgentSettings.followup_interval_minutes`.
- [ ] **C8**: Dashboard — input max count 1–5 tersimpan ke `AiAgentSettings.followup_max_count`.
- [ ] **C8**: Validasi server-side: interval di luar 5–60 ditolak, max count di luar 1–5 ditolak.

---

## Risk & Rollback

| Risk | Mitigasi | Rollback |
|---|---|---|
| Refactor call-site memutus alur lama | Shim method, migrasi bertahap | Revert per file |
| Drop sample produk → latency naik | Cache `get_all_products` per store 5 menit | Re-enable injection dengan limit lebih rendah (5) sementara |
| Schema `settings` break existing data | Migrasi script + default merge | Restore JSON lama dari backup DB |

---

## Dependensi Antar Plan

- **`flow-plan.md`** akan **mengandalkan** `getFeatureStatus()` baru untuk routing yang konsisten.
- **`follow-up-plan.md`** & **`drip-message-plan.md`** akan **mengandalkan** `AiAgentSettings` (C5) untuk on/off + quiet hours per merchant.

Karena itu, **urutan implementasi yang disarankan:**

1. **C1 + C2 + C4 + C6** dulu (low-risk, refactor murni) → buka jalan untuk flow-plan.
2. **C5** (schema settings) → buka jalan untuk follow-up & drip plan.
3. **C3** terakhir (perlu validasi panjang via chat.js).

---

## Files yang Akan Disentuh

```
app/Models/AiAgent.php                              # tambah getFeatureStatus(), catalog_enabled, shim method lama
app/Models/AiAgentSettings.php                      # baru — DTO/Castable (followup_*, drip_*, quiet_hours_*)
app/Services/AiAgentPromptBuilder.php               # hapus product injection
app/Services/AiAgentService.php                     # ganti akses field mentah
app/Services/CatalogOrderFlowService.php            # ganti akses field mentah
app/Observers/SubMerchantObserver.php               # baru — invalidate cache
app/Observers/ReservationConfigObserver.php         # baru — invalidate cache
app/Http/Controllers/AiAgentController.php          # C7 toggle + dropdown logic; C8 form fields
resources/views/dashboard/ai-agent.blade.php (?)    # C7 UI: toggle + dropdown conditional render; C8 section follow-up
config/ai_agent.php                                 # baru atau tambah — default settings
config/catalog.php                                  # C7: tidak berubah (sudah membaca META_CATALOG); cukup verifikasi
.env.example                                        # C7: pastikan komentar META_CATALOG ada + dokumentasi semantik
database/migrations/*_alter_ai_agents_settings.php  # migrasi settings JSON ke schema baru
database/migrations/*_add_catalog_enabled_to_ai_agents.php  # C7
```

Tidak menyentuh: state machine, drip system internals, follow-up scheduler logic (lihat plan lain — hanya field config-nya yang ada di sini).
