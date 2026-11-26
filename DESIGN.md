# QashierWise UI Design System

Dokumen ini adalah acuan untuk **semua pekerjaan UI**, termasuk membuat fitur baru, memperbaiki tampilan, merapikan halaman yang sudah ada, dan menyempurnakan komponen. Sumber visualnya adalah antarmuka CMS di `/admin`. Saat membuat atau memperbarui halaman `/dashboard`, ikuti bahasa visual dan perilaku komponen di bawah ini. Pertahankan struktur dan mekanisme autentikasi masing-masing area.

Untuk perbaikan tampilan yang sudah ada, audit halaman terhadap token, tipografi, komponen, responsivitas, dan state interaksi di dokumen ini. Selaraskan bagian yang disentuh dengan pola `/admin` tanpa mengubah alur bisnis atau perilaku fitur yang tidak terkait. Jika pola lama dipakai di banyak halaman, perbaiki komponen atau token bersama agar hasilnya konsisten.

## Sumber implementasi

| Area | File rujukan | Peran |
| --- | --- | --- |
| Token, font, latar, animasi | `resources/js/next.js/app/admin/admin.css` | Sumber utama nilai visual `/admin` |
| Komponen dasar React | `resources/js/next.js/app/admin/ui.tsx` | Button, Card, Field, Input, Select, Toggle, Badge, EmptyState, Spinner |
| Kerangka halaman | `resources/js/next.js/app/admin/components/Layout.tsx` | Sidebar, topbar, lebar konten, PageHeader |
| Tabel dan pagination | `resources/js/next.js/app/admin/components/DataTable.tsx` | Kepadatan dan state tabel |
| Dialog dan notifikasi | `resources/js/next.js/app/admin/components/Modal.tsx`, `toast.tsx` | Konfirmasi, overlay, toast |
| Contoh halaman | `resources/js/next.js/app/admin/pages/Dashboard.tsx`, `pages/posts/PostList.tsx`, `pages/posts/PostForm.tsx` | Metrik, daftar, form |
| Stack dashboard saat ini | `resources/views/layouts/dashboard.blade.php`, `resources/css/app.css` | Blade/Alpine dan kelas CSS yang perlu diselaraskan |

Jika implementasi `/admin` berubah, perbarui dokumen ini bersama perubahan tersebut. Nilai persis tetap merujuk pada berkas sumber di atas.

## Prinsip visual

- Ringan, rapi, dan mudah dipindai. Permukaan putih berada di atas latar abu muda; ungu hanya untuk navigasi aktif, fokus, tautan penting, dan tindakan utama.
- Judul dan angka utama berkontras tinggi. Label sekunder lebih redup, tetapi teks penting tidak bergantung pada warna saja.
- Satu halaman memiliki satu judul utama, deskripsi singkat bila perlu, dan tindakan utama di sisi kanan pada layar lebar.
- Kelompokkan data dalam card dengan padding konsisten. Hindari card bersarang untuk setiap baris atau elemen kecil.
- Status dan aksi harus jelas dalam state normal, hover, fokus, memuat, kosong, berhasil, dan gagal.

## Fondasi visual

### Tipografi

Font `/admin` adalah **Manrope** dengan fallback system sans. Untuk halaman `/dashboard` yang ingin mengikuti sistem ini, muat Manrope pada layout dashboard dan gunakan pada area UI tersebut; `resources/css/app.css` saat ini memakai Inter. Jangan mengubah font seluruh aplikasi tanpa mengecek dampaknya pada halaman lain.

| Elemen | Pola `/admin` |
| --- | --- |
| Judul halaman | `text-2xl font-bold tracking-tight text-ink-900` |
| Judul card/bagian | `text-base` atau `text-sm font-semibold text-ink-700` |
| Isi dan kontrol | `text-sm` |
| Label tabel, bantuan, metadata | `text-xs`; label tabel `font-semibold uppercase tracking-wide` |
| Angka metrik | `text-2xl font-bold text-ink-900` atau `text-3xl` untuk laporan |

### Warna

Gunakan token semantik lebih dulu; tabel ini merangkum nilai yang saat ini didefinisikan di `admin.css`.

| Peran | Token / nilai | Penggunaan |
| --- | --- | --- |
| Aksi utama | `purple-600` `#7c3aed` | Tombol utama, ikon aktif, fokus |
| Aksi hover | `purple-700` `#6d28d9` | Hover aksi utama |
| Latar aktif lembut | `purple-50` `#f6f3ff`, `purple-100` `#ede7ff` | Navigasi aktif, sorotan |
| Teks utama | `ink-900` `#0b0a0f` | Judul dan angka |
| Teks isi | `ink-700` `#2a2733` | Isi dan label kontrol |
| Teks sekunder | `ink-500` `#5b5667`, `ink-400` `#8a8696` | Deskripsi dan metadata |
| Garis | `ink-100` `#f1eff5`, `ink-200` `#e5e2ec` | Pemisah dan ring kontrol |
| Latar halaman | gradien `#f8f9fa` ke `#f3f4f6` | Area kerja `/admin` |
| Permukaan | putih `#ffffff` | Card, tabel, modal, topbar |
| Sukses | `ios-green` `#34c759` | Status sukses |
| Informasi | `ios-blue` `#007aff` | Informasi |
| Peringatan | `ios-orange` `#ff9500` | Peringatan |
| Bahaya | `ios-red` `#ff3b30` | Hapus, kesalahan |

Jangan mencampur palet `ink-*` dengan abu netral acak pada satu halaman. Gunakan warna status hanya ketika maknanya relevan. Status harus punya teks, bukan ikon atau warna saja.

### Radius, bayangan, dan ruang

| Elemen | Pola |
| --- | --- |
| Card, tabel, empty state, modal | `rounded-2xl` (sekitar 16 px) |
| Button dan input | `rounded-xl` (sekitar 12 px) |
| Badge | `rounded-full` |
| Card | putih + `ring-1 ring-ink-100` + `--shadow-card` |
| Modal/toast | `--shadow-elevated` |
| Card standar | padding `p-5`; card metrik ringkas `p-4` |
| Jarak antarkelompok | `gap-6` / `mt-6`; elemen dalam kelompok `gap-2` sampai `gap-4` |
| Konten halaman | `max-w-6xl`, `px-4 py-6` di mobile, `lg:px-8 lg:py-8` |

Token bayangan dan animasi berasal dari `admin.css`. Jangan menyalin angka bayangan ke banyak file; buat padanan token dashboard bila belum tersedia.

## Pola komponen

| Komponen | Bentuk dan perilaku yang diikuti |
| --- | --- |
| PageHeader | Judul kiri, deskripsi satu kalimat, aksi utama kanan; membungkus pada layar sempit. |
| Button | `primary` ungu, `secondary` putih dengan ring, `ghost` untuk aksi ringan, `danger` merah. Tinggi lewat padding `px-4 py-2.5`, teks 14 px semibold. Tampilkan spinner dan nonaktifkan saat proses berlangsung. |
| Card | Permukaan putih, radius 16 px, ring halus, bayangan lembut. Gunakan padding 16–20 px. |
| Field/Input/Select/Textarea | Label di atas kontrol; bantuan atau error di bawah. Kontrol putih, radius 12 px, ring `ink-200`, fokus ring `purple-400`. Tanda wajib berwarna merah. |
| Toggle | Switch 40×24 px, hijau ketika aktif, teks label di samping, atribut `role="switch"` dan `aria-checked`. |
| Badge | Pill kecil, 12 px medium. Gunakan varian gray/success/warning/info/primary sesuai status. |
| DataTable | Satu card berisi tabel; header 12 px uppercase, baris 14 px, pemisah `ink-100`, scroll horizontal pada layar kecil. Sort ditandai ikon. Pagination di bawah tabel. |
| EmptyState | Area terpusat dengan border putus `ink-200`, ikon redup, judul, deskripsi, dan satu aksi pemulihan bila ada. |
| Modal/ConfirmDialog | Overlay gelap transparan dengan blur ringan, dialog putih radius 16 px, judul dan tombol tutup, aksi Batal + tindakan utama/bahaya. Escape menutup dialog. |
| Toast | Kanan atas, card putih dengan ikon status, pesan singkat, tombol tutup; di `/admin` hilang otomatis setelah 4 detik. |
| Loading | Spinner ungu pada area yang menunggu; jangan tampilkan data palsu saat API belum selesai. |

### Tata letak dan responsif

- Di desktop, sidebar `/admin` memiliki lebar `w-64` (256 px); konten bergeser `lg:pl-64`. Di mobile, sidebar menjadi drawer dengan backdrop dan tombol menu di topbar.
- Topbar menempel di atas (`sticky top-0`), putih transparan dengan blur dan garis bawah.
- Area konten dibatasi `max-w-6xl`. Grid metrik dapat dimulai dari 2 kolom di mobile dan bertambah di breakpoint `md`/`lg`; card besar ditata ulang menjadi satu kolom di mobile.
- Tombol dan filter dapat membungkus (`flex-wrap`), tabel dapat di-scroll horizontal, dan aksi penting tetap dapat dijangkau tanpa hover.
- Ikon `/admin` memakai `lucide-react`. Di Blade, pilih ikon yang bentuk dan ukurannya sepadan; jangan mencampur beberapa gaya ikon dalam satu area UI.

### Motion dan aksesibilitas

- Animasi masuk halaman menggunakan `admin-fade-in`: opacity + perpindahan vertikal 6 px selama 0,28 detik. Spinner memakai `admin-spin`. Gunakan animasi hanya untuk memberi konteks perubahan state.
- Semua kontrol harus memiliki label yang terbaca, state fokus terlihat, dan teks tombol yang menjelaskan tindakannya. Aksi hapus memerlukan konfirmasi.
- Warna sukses/gagal/aktif harus disertai teks. Loading dan empty state harus menggambarkan keadaan yang benar.
- UI `/admin` saat ini mendukung tema terang. Jangan merancang fitur dashboard baru yang bergantung pada dark mode tanpa token dan pemeriksaan kontras tambahan.

## Cara menerapkan pada `/dashboard`

`/admin` adalah React + Tailwind dengan token `@theme`; `/dashboard` adalah Blade + Alpine dengan `resources/css/app.css` dan komponen `.card`, `.btn`, serta token HSL yang berbeda. **Komponen React di `ui.tsx` tidak bisa langsung di-import ke Blade.** Ikuti kontrak visualnya, lalu buat padanan yang dapat digunakan ulang di stack dashboard.

1. Baik untuk fitur baru maupun perbaikan tampilan, periksa apakah komponen dashboard sudah ada. Perluas komponen atau kelas bersama itu sebelum menambah gaya halaman khusus.
2. Selaraskan token dashboard untuk font, permukaan, `ink`, radius, bayangan, dan state fokus. Gunakan namespace/token dashboard yang jelas agar perubahan tidak merusak halaman publik.
3. Buat padanan Blade/Alpine untuk pola PageHeader, Button, Card, Field, Badge, EmptyState, tabel, modal, dan toast sesuai kebutuhan fitur. Pertahankan API properti/varian yang konsisten di seluruh dashboard.
4. Gunakan urutan halaman: PageHeader → ringkasan/metrik → filter/tindakan → konten utama → empty/error/loading state. Contoh konkret ada di `Dashboard.tsx` dan `PostList.tsx`.
5. Cek tampilan mobile dan desktop, keyboard/fokus, state loading, serta teks Indonesia/English yang tersedia pada area tersebut. Gunakan istilah produk yang sama di menu, judul, tombol, dan pesan. Untuk perbaikan tampilan, bandingkan keadaan sebelum dan sesudah pada halaman yang diubah.

Sebagai pemetaan awal: `purple-600` sejalan dengan `--primary` dashboard saat ini; `.card` dan `.btn` sudah ada, tetapi radius, bayangan, font, dan beberapa state berbeda dari `/admin`. Kesamaan warna primer saja belum cukup untuk menyamakan tampilannya.

## Contoh komposisi halaman

```text
PageHeader: "Pesanan"                         [Tindakan utama]
Deskripsi singkat konteks halaman

[Card metrik] [Card metrik] [Card metrik]

[Pencarian] [Filter status] [Filter tanggal]

[DataTable: status, data utama, aksi per baris]
[Pagination]

Jika kosong → EmptyState + satu aksi yang relevan
Jika gagal → pesan dan aksi coba lagi
Jika memuat → spinner/skeleton pada area konten
```

Gunakan contoh ini sebagai susunan awal, bukan kewajiban untuk semua halaman. Pilih komponen sesuai jenis tugas dan kepadatan data.
