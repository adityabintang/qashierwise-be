# Konfigurasi AI Agent

Atur kepribadian dan pengetahuan AI agar jawabannya sesuai dengan restoran Anda. Buka menu **AI Agent**.

## Informasi bisnis

Isi data berikut agar AI bisa menjawab pertanyaan umum pelanggan:

- **Nama Agent** — mis. *"Assistant Resto Saya"*. Ini nama yang "dikenakan" bot.
- **Jam Buka** — mis. *"Senin–Jumat 08:00–22:00"*.
- **Nomor Telepon** — mis. *"021-1234567"* untuk dibagikan saat ditanya.
- **Alamat** — mis. *"Jl. Sudirman No. 123, Jakarta Pusat"*.

Semakin lengkap data, semakin akurat jawaban AI.

## Katalog produk

AI dapat mengirim **katalog/menu** ke pelanggan saat mereka ingin memesan.

1. Pada bagian **Pilih Katalog**, pilih katalog Meta yang ingin dipakai bot.
2. Pastikan katalog sudah tersinkron — lihat **[Hubungkan Katalog](/docs/katalog/hubungkan-katalog)**.

Saat pelanggan minta menu, AI mengirim **multi-product message** dari katalog tersebut.

## Follow-up otomatis

AI bisa melakukan **follow-up** ke pelanggan yang berhenti membalas di tengah percakapan.

- **Interval follow-up** — atur jeda waktu (dalam menit) sebelum AI mengirim pesan susulan.

Gunakan interval yang wajar agar tidak terasa mengganggu.

## Delivery

Toggle **Delivery** pada AI Agent menentukan apakah bot boleh memproses pesanan antar (delivery).

> Toggle ini hanya bisa **ON** jika konfigurasi delivery Anda **aktif**. Ongkir & aturan delivery diatur terpisah di menu **[Delivery](/docs/delivery/konfigurasi)**, lalu disinkronkan ke AI Agent.

## Menyimpan perubahan

Setiap perubahan disimpan dan ditandai waktu **"last saved"**. Setelah menyimpan, sebaiknya lakukan **[uji coba](/docs/ai-agent/uji-coba)**.
