# Syarat Catalog Tampil di WhatsApp (MPM)

Dokumen ini merangkum syarat teknis agar pesan Multi-Product Message (MPM) berhasil dikirim via WhatsApp Cloud API. Divalidasi dari dokumentasi resmi Meta dan hasil eksperimen langsung pada akun developer.

---

## 1. Syarat Catalog

| Syarat | Detail |
|--------|--------|
| Tipe catalog | Harus `commerce` / `e-commerce` (vertical = "commerce"). Catalog travel, automotive, dll. tidak bisa dipakai untuk MPM. |
| Linked ke WABA | Catalog wajib di-link ke WhatsApp Business Account (WABA) via `POST /{waba_id}/product_catalogs`. |
| Satu WABA = satu catalog | Meta hanya mengizinkan 1 catalog aktif per WABA. Switching catalog harus unlink dulu. |
| Satu catalog = satu WABA | Sebaliknya, 1 catalog hanya bisa di-link ke 1 WABA. Jika sudah linked ke WABA lain → error subcode `2388099`. |

---

## 2. Syarat Produk (paling kritis)

### 2a. Field Wajib Per Produk

| Field | Keterangan |
|-------|-----------|
| `name` | Nama produk — harus jelas dan nyata, bukan placeholder (e.g. "svsfvsfv" akan gagal review). |
| `description` | Deskripsi produk. |
| `price` | Harga dalam unit mata uang (IDR: nominal langsung tanpa desimal). |
| `currency` | Kode mata uang ISO (e.g. `IDR`, `USD`). |
| `image_url` | URL gambar HTTPS yang bisa diakses oleh crawler Meta. Bukan localhost. |
| `url` | URL halaman produk HTTPS yang valid dan dapat diakses publik. |
| `availability` | `in stock` atau `out of stock`. |
| `retailer_id` | Unique Content ID / SKU. Harus unik dalam satu catalog. |

### 2b. `review_status` — Field Paling Kritis untuk MPM

Sumber: [Meta Marketing API — Product Item Reference](https://developers.facebook.com/docs/marketing-api/reference/product-item/)

```
enum { "", pending, rejected, approved, outdated }
```

| Nilai | Arti | Bisa dikirim via MPM? |
|-------|------|----------------------|
| `"approved"` | Produk lolos review Meta | **YA** |
| `""` (kosong) | Belum disubmit untuk review ATAU review tidak berlaku | Bergantung konfigurasi akun — pada akun developer/test sering **TIDAK BISA** |
| `"pending"` | Sedang dalam proses review | **TIDAK** |
| `"rejected"` | Ditolak Meta (melanggar Commerce Policy) | **TIDAK** |
| `"outdated"` | Data produk sudah berubah, perlu re-review | **TIDAK** |

> **Root cause error kita**: Semua produk di catalog `makanan sehat` (3203965079786789) memiliki `review_status: ""` (kosong). Meta menolak MPM dengan error:
> ```
> (#131009) None of the products provided could be sent. Please check your catalog.
> ```

### 2c. Penyebab `review_status` kosong atau ditolak

- Nama produk tidak wajar / placeholder (e.g. "svsfvsfv", "ergergerg")
- `image_url` tidak bisa diakses oleh Meta crawler (localhost, IP private, expired)
- `url` tidak valid atau tidak bisa diakses publik
- Produk melanggar [Meta Commerce Policy](https://www.facebook.com/policies/commerce/) (alkohol, senjata, konten dewasa, dll.)
- Produk belum pernah disubmit untuk review di Commerce Manager

---

## 3. Syarat WABA & Phone Number

| Syarat | Detail |
|--------|--------|
| Catalog linked ke WABA | `POST /{waba_id}/product_catalogs` dengan `catalog_id` yang benar. |
| Commerce settings aktif | `POST /{phone_number_id}/whatsapp_commerce_settings` dengan `is_cart_enabled: true` dan `is_catalog_visible: true`. |
| Phone number terdaftar di WABA | Phone number ID harus ada di bawah WABA yang sama. |

---

## 4. Keterbatasan Test Phone Number

Berdasarkan eksperimen langsung (phone number: `15551873092`):

| Kemampuan | Test Number |
|-----------|------------|
| Link catalog ke WABA | **BISA** |
| Baca produk dari catalog | **BISA** |
| Kirim MPM jika produk `review_status: approved` | Belum diverifikasi |
| Kirim MPM jika produk `review_status: ""` | **TIDAK BISA** (error 131009) |

> Meta tidak mendokumentasikan secara eksplisit batasan test phone number untuk MPM. Namun berdasarkan eksperimen, test number dapat link ke WABA, namun pengiriman MPM memerlukan produk yang benar-benar approved.

---

## 5. Alur Lengkap agar MPM Bekerja

```
1. Buat catalog di Commerce Manager
   └── Pilih tipe: E-Commerce
   └── Pastikan vertical = "commerce"

2. Tambahkan produk dengan data lengkap:
   └── name, description, price, currency
   └── image_url (HTTPS, publik, min 500×500px)
   └── url (HTTPS, halaman produk valid)
   └── availability = "in stock"
   └── retailer_id (unik)

3. Submit produk untuk review di Commerce Manager
   └── Tunggu review_status = "approved"
   └── Estimasi: beberapa menit hingga 24 jam
   └── Jika ditolak: perbaiki data, resubmit

4. Link catalog ke WABA
   POST /{waba_id}/product_catalogs
   └── catalog_id = ID catalog yang sudah diapprove
   └── Pastikan catalog belum linked ke WABA lain

5. Aktifkan commerce settings di phone number
   POST /{phone_number_id}/whatsapp_commerce_settings
   └── is_cart_enabled: true
   └── is_catalog_visible: true

6. Kirim MPM (product_list message)
   └── catalog_id di message harus sama dengan yang linked ke WABA
   └── product_retailer_id harus ada di catalog tersebut
   └── Maks 30 produk, maks 10 section
```

---

## 6. Error Umum dan Penyebabnya

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `131009` — "None of the products provided could be sent" | Produk `review_status` bukan `approved`, atau data produk tidak valid | Review dan approve produk di Commerce Manager |
| `131009` — "Invalid catalog_id" | Catalog tidak ter-link ke WABA pengirim | Link catalog ke WABA via API atau Commerce Manager |
| Subcode `2388099` | Catalog sudah di-link ke WABA lain | Unlink dari WABA lain dulu via Commerce Manager |
| `200` — Permission denied | Token tidak memiliki `catalog_management` permission | Re-login dengan permission `catalog_management` |

---

## 7. Cara Cek Status Produk via API

```bash
GET /{catalog_id}/products?fields=retailer_id,name,availability,review_status,review_rejection_reasons
```

Response yang BAIK (siap kirim MPM):
```json
{
  "name": "Nasi Goreng Spesial",
  "availability": "in stock",
  "review_status": "approved",
  "review_rejection_reasons": []
}
```

Response yang BERMASALAH (tidak bisa kirim MPM):
```json
{
  "name": "svsfvsfv",
  "availability": "in stock",
  "review_status": "",
  "review_rejection_reasons": []
}
```

---

## 8. Cara Approve Produk di Commerce Manager

1. Buka [Meta Commerce Manager](https://business.facebook.com/commerce)
2. Pilih catalog → **Catalog Items**
3. Cek kolom **Status** per produk
4. Jika status bukan *Approved*: klik produk → edit → pastikan semua data lengkap → simpan
5. Meta akan otomatis re-review dalam beberapa menit
6. Atau: gunakan Data Feed (CSV/XML) untuk upload massal dengan data lengkap

---

## Referensi

- [Meta Developer — Multi-product messages](https://developers.facebook.com/documentation/business-messaging/whatsapp/catalogs/multi-product-messages/)
- [Meta Developer — Catalogs Overview](https://developers.facebook.com/documentation/business-messaging/whatsapp/catalogs/catalogs-overview/)
- [Meta Marketing API — Product Item Reference](https://developers.facebook.com/docs/marketing-api/reference/product-item/)
- [Vonage — WhatsApp Product Messages Guide](https://developer.vonage.com/en/messages/guides/whatsapp-product-messages)
- [Interakt — Enable Product Catalogs via WhatsApp API](https://www.interakt.shop/whatsapp-business-api/product-catalog-whatsapp-api/)
- [Yellow.ai — Setup WhatsApp Catalog](https://docs.yellow.ai/docs/platform_concepts/channelConfiguration/whatsapp-product-catalog)
