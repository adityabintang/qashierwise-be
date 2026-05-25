# Qashierwise — Project Orientation

- frontend = next.js (qashierwise-fe/)
- backend  = laravel (qashierwise-be/)

Backend sudah jalan di production; frontend Next.js sedang dimigrasikan dari Blade.

---

# Aturan Refactor AI Agent

Aturan-aturan berikut berlaku khusus untuk refactor service AI Agent + Catalog di `qashierwise-be/app/Services/`. Tujuan utamanya: menghilangkan halusinasi yang muncul dari sisa kode legacy dan kode yang tidak konsisten.

## 1. Struktur folder berdasarkan domain

Service dipisah ke sub-folder berdasarkan tanggung jawab domain, contoh:

```
app/Services/ai-agent/
  agent-catalog/
  agent-tools/
  agent-prompt/
  agent-reply/
```

Sub-folder dibuat hanya ketika ada >=2 service yang secara natural berkelompok pada domain yang sama. Jangan bikin sub-folder hanya berisi 1 file.

## 2. Pecah god class

`AiAgentService.php` (3094 LOC) dan `CatalogOrderFlowService.php` (1745 LOC) wajib dipecah berdasarkan tanggung jawab:

- Intent routing
- Prompt building
- LLM call + tool dispatching
- Tool handlers (cart, menu, payment, dll)
- Reply sending (WhatsApp API)
- Catalog state machine (delivery, payment, confirmation)

Setiap class hasil pecahan harus bisa dijelaskan tanggung jawabnya dalam satu kalimat.

## 3. Bebas LOC, tapi wajib clean code + log bersih

Tidak ada batas hardcap baris per file. Yang dijaga adalah **kohesi dan kebersihan**.

### Clean code (wajib)

- **Single responsibility per class**. Kalau susah dijelaskan dalam 1 kalimat, split.
- **Dilarang dead code & version markers**. Hapus penanda seperti `[v2-buffer]`, `// new flow`, `// old code`, komentar `// TODO: remove` berumur >1 bulan, dan branch `if ($legacyFlag)` yang tidak punya pemilik jelas. Kalau ragu kenapa kode itu ada, hapus — git history menyimpannya.
- **Tidak boleh ada method "swiss army"**. Method >100 baris dengan banyak cabang harus dipecah berdasarkan kasus.
- **Konsisten penamaan**. Tidak campur bahasa di nama method (`handleOrder` vs `prosesPesanan`).

### Log bersih (wajib)

Log levels:

| Level | Kapan |
|---|---|
| `info` | Milestone alur (pesan masuk, intent terdeteksi, LLM dipanggil, reply terkirim) |
| `warning` | Recoverable issue (fallback dipakai, retry, tool returned empty) |
| `error` | Gagal yang butuh perhatian (exception, API down) |
| `debug` | Detail troubleshoot — **HANYA** aktif di lokal, off di production |

Aturan tambahan:

- **Structured context wajib**: `Log::info('Message dispatched', ['contact_id' => $id, 'agent_id' => $aid])`. Bukan string-concat.
- **No PII** di pesan log production (no nomor WA full, no nama lengkap pelanggan, no isi pesan plain text di level info — gunakan preview/hash kalau perlu).
- **No log di hot loop**. Kalau ada `foreach` proses 100 item, jangan log per item.
- **No log instrumentation tertinggal**. Setelah debugging selesai, hapus `Log::info('here 1')`, `Log::info('reached point X')`, dll.

## 4. `<nama-service>.md` per service

Setiap service yang **flow-nya tidak obvious dari nama class** wajib punya `.md` companion di folder yang sama. Nama file = nama service (contoh: `CatalogOrderFlow.md` untuk `CatalogOrderFlowService.php`).

### Kontrak minimum `.md`

```
# <ServiceName>

## Tanggung jawab
Satu kalimat: apa yang service ini lakukan.

## Input → Output
Diagram pendek atau bullet list.

## Dependencies
Service lain yang dipanggil.

## State (kalau ada)
Field DB / cache yang dibaca/ditulis.

## Edge cases yang penting
Hal yang tidak obvious dari kode.
```

### Yang DILARANG masuk `.md`

- Contoh kode (akan rot, lebih cepat stale dari kode aslinya).
- Penjelasan algoritma per-method (itu pakai docblock di kode).
- Changelog (itu pakai git).

Service simple yang sudah jelas dari nama class & method-nya tidak perlu `.md`.

## 5. Flow diagram di root `ai-agent/`

Wajib ada **satu** `ai-agent.md` (atau `flow.md`) di `app/Services/ai-agent/` yang berisi diagram alur tingkat tinggi:

```
webhook → routing → service mana yang aktif kapan → reply
```

Diagram ini adalah "peta" supaya pembaca `.md` di sub-folder tidak kehilangan konteks alur besar. Update wajib setiap kali ada perubahan routing utama.

## 6. Setiap refactor wajib menghapus kode lama

PR yang refactor service AI Agent harus:

- **Menghapus** kode lama yang digantikan, bukan membiarkannya dengan flag/comment "siapa tahu dipakai lagi".
- **Tidak boleh menambah `@deprecated` tanpa tanggal hapus**. Kalau ditandai deprecated, harus ada tanggal kapan dihapus (max 2 minggu setelah PR refactor merged).
- **Tidak boleh menyimpan file `*-old.php` / `*-legacy.php` / `*-backup.php`** di repo. Git history adalah backup.

Kalau ragu, hapus. Restore dari git lebih murah daripada mengelola kode mati.
