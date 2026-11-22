# Impor riwayat chat dan kontak WhatsApp Business

### Hasil yang diharapkan

Setelah pengguna menghubungkan nomor WhatsApp Business lewat Coexistence, QashierWise mengimpor kontak awal ke **Kontak**. Jika pengguna memilih **Share all chats**, Inbox menampilkan hingga 180 hari riwayat chat pribadi, termasuk pesan masuk dan keluar. Jika pengguna memilih **Don’t share chats**, kontak tetap diimpor dan pesan baru tetap berjalan. Chat grup berada di luar cakupan impor Meta. [Dokumentasi Meta](https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-business-app-users) · [Panduan Chatbase](https://www.chatbase.co/docs/user-guides/integrations/whatsapp-coexistence#importing-your-chat-history-and-contacts)

### Onboarding dan status impor

- Teruskan `FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING` beserta ID aset pada callback Embedded Signup v4. Gabungkan event Meta dengan kode OAuth meski keduanya tiba dalam urutan berbeda. Verifikasi nomor dan WABA dengan token hasil pertukaran kode sebelum membuat sesi impor. Alur Cloud API biasa dan koneksi lama tanpa sesi impor tetap bisa digunakan.
- Simpan sesi impor per onboarding. Setelah langganan webhook WABA berhasil, minta `smb_app_state_sync` lalu `history` lewat `POST /{phone_number_id}/smb_app_data` dalam 24 jam. Simpan `request_id` masing-masing. Cegah permintaan ganda dari callback atau job yang berulang. Jika hasil permintaan tidak pasti, tunggu webhook dan tampilkan status yang sesuai tanpa mengulang permintaan secara otomatis.
- Tambahkan `coexistence_import` ke respons `GET /api/whatsapp/account`. Kartu akun menampilkan status kontak dan riwayat, progres yang dilaporkan Meta, serta petunjuk untuk tetap membuka aplikasi WhatsApp Business. Kode `2593109` menampilkan bahwa riwayat tidak dibagikan. Jika Meta belum memberi konfirmasi, tampilkan status menunggu, lalu belum terkonfirmasi setelah 24 jam. Tetap proses batch sah yang tiba belakangan.

### Pemrosesan data dan Inbox

- Verifikasi tanda tangan webhook dan cocokkan WABA serta ID nomor dengan sesi impor aktif. Simpan batch secara terenkripsi sebelum merespons `200`, lalu proses melalui antrean Laravel. Hindari pencatatan isi chat dan data kontak. Bedakan webhook `history` yang membawa lampiran dari webhook pesan masuk biasa. [Verifikasi webhook Meta](https://developers.facebook.com/docs/graph-api/webhooks/getting-started/)
- Gunakan nomor WhatsApp untuk mencocokkan kontak dalam akun yang sama. Impor kontak awal berdasarkan waktu perubahan yang diberikan Meta. Isi nama kontak baru atau nama yang masih berupa nomor. Pertahankan nama yang sudah tersimpan, tag, dan percakapan. Perubahan buku alamat setelah onboarding berada di luar cakupan. Catat jumlah entri yang hanya memiliki BSUID karena Kontak dan Inbox saat ini memakai nomor telepon sebagai identitas.
- Simpan pesan berdasarkan ID Meta, arah, dan waktu aslinya. Abaikan duplikat, utamakan data pesan live saat ID bertabrakan, dan perbarui cuplikan percakapan hanya jika pesan yang diproses lebih baru. Gunakan waktu pesan dan ID sebagai pengurut Inbox sambil mempertahankan parameter `before_id`. Pesan impor tampil sebagai sudah dibaca dan dikecualikan dari pemicu AI, otomasi, serta perhitungan jendela layanan 24 jam.
- Cocokkan lampiran yang datang terpisah dengan ID pesan, termasuk saat lampiran tiba lebih dulu. Unduh file yang disediakan Meta untuk pesan dalam 14 hari sebelum onboarding, sesuai batas ukuran aplikasi. Simpan file di penyimpanan privat dan sajikan lewat akses yang memverifikasi akun. Pertahankan caption dan tampilkan placeholder bila file tidak tersedia. [Format riwayat dan lampiran Meta](https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-business-app-users#history)

### Verifikasi dan peluncuran

- Uji kedua pilihan berbagi chat, onboarding Cloud API biasa, webhook bertanda tangan salah, dan nomor dari tenant lain. Uji batch duplikat atau terbalik, lampiran yang tiba lebih dulu, pagination untuk chat campuran, serta pesan live yang masuk saat impor. Pastikan pesan impor tidak menambah unread atau membuka jendela layanan.
- Jalankan migrasi dan worker antrean sebelum mengaktifkan `history` dan `smb_app_state_sync` di Meta App Dashboard. Uji dari ponsel dengan nomor WhatsApp Business yang memenuhi syarat, lalu pastikan hasil muncul di kartu akun, Inbox, dan Kontak. Fitur berlaku untuk onboarding Coexistence baru, tanpa menarik ulang data koneksi lama secara otomatis.
