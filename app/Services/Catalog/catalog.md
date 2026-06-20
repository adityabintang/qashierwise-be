# Dokumentasi API Meta Product Catalog

[cite_start]Dokumentasi ini merangkum fungsionalitas pengelolaan Meta Product Catalog melalui antarmuka Graph API[cite: 2]. [cite_start]Katalog produk mewakili daftar item yang dapat Anda gunakan untuk mengirimkan iklan dinamis (Dynamic Ads)[cite: 12].

---

## 1. Create (Membuat Data)

### 1.1 Membuat Product Catalog Baru
[cite_start]**Endpoint:** `POST /{business_id}/owned_product_catalogs` [cite: 155-158]

**Parameter:**
* `name` (UTF-8 encoded string): Nama katalog. [cite_start]**(Wajib)** [cite: 165]
* [cite_start]`additional_vertical_option` (enum): Konfigurasi tambahan (`LOCAL_DA_CATALOG`, `LOCAL_PRODUCTS`). [cite: 160]
* [cite_start]`business_metadata` (JSON object): Metadata bisnis. [cite: 165]
* [cite_start]`catalog_segment_filter` (JSON-encoded rule): Filter untuk membuat segmen katalog. [cite: 165]
* [cite_start]`da_display_settings` (Object): Pengaturan tampilan Dynamic Ads. [cite: 165]
* [cite_start]`destination_catalog_settings` (JSON object): Pengaturan katalog tujuan. [cite: 165]
* [cite_start]`flight_catalog_settings` (JSON object): Pengaturan katalog penerbangan. [cite: 165]
* [cite_start]`parent_catalog_id` (numeric string / integer): ID katalog induk. [cite: 165]
* [cite_start]`partner_integration` (JSON object): Pengaturan integrasi mitra. [cite: 165]
* [cite_start]`store_catalog_settings` (JSON object): Pengaturan katalog toko fisik. [cite: 171-172]
* `vertical` (enum): Industri/vertikal katalog. Default: `commerce`. [cite_start]Nilai: `adoptable_pets`, `apps_and_software`, `articles_and_publications`, `commerce`, `destinations`, `flights`, `generic`, `home_listings`, `hotels`, `local_service_businesses`, `media_titles`, `offer_items`, `services`, `offline_commerce`, `transactable_items`, `vehicles`. [cite: 175-184]

**Return Type (Berhasil):**
    {
      "id": "numeric string"
    }
*(Catatan: Endpoint ini mendukung read-after-write dan akan membaca node yang diwakili oleh id)* [cite: 186-189]

### 1.2 Menambahkan Pengguna (Assigned Users) ke Katalog
[cite_start]**Endpoint:** `POST /{product_catalog_id}/assigned_users` [cite: 96-98]

**Parameter:**
* `user` (UID): ID pengguna bisnis atau ID pengguna sistem. [cite_start]**(Wajib)** [cite: 101]
* `tasks` (array<enum>): Tugas izin katalog (`MANAGE`, `ADVERTISE`, `MANAGE_AR`, `AA_ANALYZE`). [cite_start]**(Wajib)** [cite: 101]

**Return Type:**
    {
      "success": true
    }
[cite: 109-110]

### 1.3 Membuat Data Kendaraan (Vehicles) ke Katalog
[cite_start]**Endpoint:** `POST /{product_catalog_id}/vehicles` [cite: 113-115]

**Parameter Utama (Banyak parameter wajib):**
* [cite_start]`address` (JSON object) **(Wajib)** [cite: 123]
* [cite_start]`body_style` (enum) **(Wajib)** [cite: 123]
* [cite_start]`currency` (ISO 4217 Currency Code) **(Wajib)** [cite: 123]
* [cite_start]`description` (string) **(Wajib)** [cite: 129]
* [cite_start]`exterior_color` (string) **(Wajib)** [cite: 129]
* [cite_start]`images` (list<Object>) **(Wajib)** [cite: 136]
* [cite_start]`make` (string) **(Wajib)** [cite: 136]
* [cite_start]`mileage` (JSON object) **(Wajib)** [cite: 136]
* [cite_start]`model` (string) **(Wajib)** [cite: 136]
* [cite_start]`price` (int64) **(Wajib)** [cite: 136]
* [cite_start]`state_of_vehicle` (enum) **(Wajib)** [cite: 136]
* [cite_start]`title` (string) **(Wajib)** [cite: 136]
* [cite_start]`url` (URI) **(Wajib)** [cite: 142]
* [cite_start]`vehicle_id` (string) **(Wajib)** [cite: 142]
* [cite_start]`vin` (string) **(Wajib)** [cite: 142]
* [cite_start]`year` (int64) **(Wajib)** [cite: 142]

---

## 2. Read (Membaca Data)

### 2.1 Membaca Katalog Produk yang Dimiliki Bisnis
[cite_start]**Endpoint:** `GET /{business_id}/owned_product_catalogs` [cite: 14-16]

**Fields (Atribut) yang Tersedia:**
* [cite_start]`id` (numeric string): ID katalog. [cite: 42]
* [cite_start]`business` (Business): Bisnis pemilik katalog. [cite: 42]
* [cite_start]`catalog_store` (StoreCatalogSettings): Halaman utama toko. [cite: 42]
* [cite_start]`commerce_merchant_settings` (CommerceMerchantSettings): Pengaturan merchant. [cite: 42]
* [cite_start]`da_display_settings` (ProductCatalogImageSettings): Pengaturan gambar untuk Dynamic Ads. [cite: 51]
* [cite_start]`default_image_url` (string): URL gambar default. [cite: 51]
* [cite_start]`fallback_image_url` (list<string>): URL gambar cadangan. [cite: 51]
* [cite_start]`feed_count` (int32): Total feed yang digunakan. [cite: 51]
* [cite_start]`is_catalog_segment` (bool): Apakah ini segmen katalog. [cite: 51]
* [cite_start]`is_local_catalog` (bool): Apakah ini katalog lokal. [cite: 51]
* [cite_start]`name` (string): Nama katalog. [cite: 54]
* [cite_start]`product_count` (int32): Total produk dalam katalog. [cite: 54]
* [cite_start]`store_catalog_settings` (StoreCatalogSettings): Pengaturan toko fisik. [cite: 54]
* [cite_start]`vertical` (enum): Tipe katalog. [cite: 54]

### 2.2 Melihat Semua Pixel & Apps Eksternal
[cite_start]**Endpoint:** `GET /{product_catalog_id}/external_event_sources` [cite: 289-292]

---

## 3. Update (Memperbarui Data)

### 3.1 Memperbarui Pengaturan Catalog Utama
[cite_start]**Endpoint:** `POST /{product_catalog_id}` [cite: 199-200]

**Parameter:**
* [cite_start]`additional_vertical_option` (enum) [cite: 202]
* [cite_start]`da_display_settings` (Object) [cite: 202]
* [cite_start]`default_image_url` (URI) [cite: 208]
* [cite_start]`destination_catalog_settings` (JSON object) [cite: 208]
* [cite_start]`fallback_image_url` (URI) [cite: 208]
* [cite_start]`flight_catalog_settings` (JSON object) [cite: 208]
* [cite_start]`name` (string) [cite: 208]
* [cite_start]`partner_integration` (JSON object) [cite: 208]
* [cite_start]`store_catalog_settings` (JSON object) [cite: 208]

**Return Type:**
    {
      "success": true
    }
[cite_start][cite: 214-215]

### 3.2 Memperbarui Generated Image Config
[cite_start]**Endpoint:** `POST /{product_catalog_id}/update_generated_image_config` [cite: 220-221]

**Parameter:**
* [cite_start]`data` (List<GeneratedImageConfigData>): **(Wajib)** Berisi `product_item_id` dan `generated_background_images_ad_usage`. [cite: 223]

**Return Type (Mendukung read-after-write):**
    [
      {
        "product_item_id": "numeric string",
        "success": true,
        "error_message": "string"
      }
    ]
[cite: 231-234]

### 3.3 Marketplace Partner Signals
[cite_start]**Endpoint:** `POST /{product_catalog_id}/marketplace_partner_signals` [cite: 237-238]

**Parameter Wajib:**
* [cite_start]`event_name` (enum): `PURCHASE`, `ADD_TO_CART`, `VIEW_ITEM`, `OFFER_SUBMITTED`, `PURCHASE_VIA_OFFER`, `TEST`. [cite: 247]
* [cite_start]`event_time` (datetime/timestamp) [cite: 247]
* [cite_start]`user_data` (JSON object) [cite: 247]

---

## 4. Delete (Menghapus Data)

[cite_start]*(Catatan: Anda tidak dapat menghapus katalog dari Business Manager, atau memindahkannya antar Business Manager)* [cite: 269-270]

### 4.1 Menghapus Product Catalog
[cite_start]**Endpoint:** `DELETE /{product_catalog_id}` [cite: 293-294]

**Parameter Tambahan Opsional:**
* [cite_start]`allow_delete_catalog_with_live_product_set` (boolean, default: `false`): Atur ke `true` jika ingin memaksa penghapusan meskipun katalog masih memiliki set produk yang aktif. [cite: 296]

**Return Type:**
    {
      "success": true
    }
[cite_start][cite: 302]

### 4.2 Menghapus (Mendisosasiasi) Pengguna dari Katalog
[cite_start]**Endpoint:** `DELETE /{product_catalog_id}/assigned_users` [cite: 305-306]

**Parameter:**
* `user` (UID): ID pengguna bisnis atau sistem. [cite_start]**(Wajib)** [cite: 308]

### 4.3 Menghapus Sumber Peristiwa Eksternal (External Event Sources)
[cite_start]**Endpoint:** `DELETE /{product_catalog_id}/external_event_sources` [cite: 285-288]

**Parameter:**
* [cite_start]`external_event_sources` (Array): Format `[<APP_ID>, <PIXEL_ID>]`. [cite: 287]

---

## 5. Referensi Kode Kesalahan (Error Codes)

* [cite_start]**100**: Parameter tidak valid (Invalid parameter)[cite: 82, 112, 154, 191, 219, 236, 265, 304].
* [cite_start]**102**: Session key invalid or no longer valid[cite: 197].
* [cite_start]**190**: Invalid OAuth 2.0 Access Token[cite: 82, 191].
* [cite_start]**200**: Kesalahan izin (Permissions error)[cite: 82, 112, 154, 197, 219].
* [cite_start]**368**: The action attempted has been deemed abusive or is otherwise disallowed[cite: 82].
* [cite_start]**415**: Two factor authentication required (via SMS atau kode TOTP generator)[cite: 112].
* [cite_start]**801**: Invalid operation[cite: 304].
* [cite_start]**804**: Specified object already exists[cite: 197].
* [cite_start]**2500**: Error parsing graph query [cite: 88-89].
* [cite_start]**3970**: Anda harus ditugaskan sebagai admin dari katalog produk ini sebelum Anda dapat menghapusnya[cite: 304].
* [cite_start]**10800**: Duplicate retailer_id ketika mencoba membuat store collection[cite: 154].
* [cite_start]**2310019**: Bisnis dari katalog ini belum di-onboard ke Collaborative Ads[cite: 197].