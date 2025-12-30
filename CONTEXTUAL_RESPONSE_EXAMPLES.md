# Contoh Contextual Response AI Agent

## Greeting & Introduction

| User Message | Contextual Response |
|--------------|---------------------|
| "Halo" | "Halo! Ada yang bisa saya bantu? Ketik 'menu' untuk lihat produk kami." |
| "Hi" | "Halo! Ada yang bisa saya bantu? Ketik 'menu' untuk lihat produk kami." |
| "Assalamualaikum" | "Halo! Ada yang bisa saya bantu? Ketik 'menu' untuk lihat produk kami." |

## Menu & Product Inquiry

| User Message | Contextual Response |
|--------------|---------------------|
| "menu" | "Untuk melihat menu lengkap, ketik 'lihat menu' atau 'daftar produk'. Atau sebutkan produk yang Anda cari." |
| "ada menu apa?" | "Untuk melihat menu lengkap, ketik 'lihat menu' atau 'daftar produk'. Atau sebutkan produk yang Anda cari." |
| "jual apa?" | "Untuk melihat menu lengkap, ketik 'lihat menu' atau 'daftar produk'. Atau sebutkan produk yang Anda cari." |

**Dengan Search Results:**
| User Message | Search Results | Contextual Response |
|--------------|----------------|---------------------|
| "ada menu apa?" | Found: Nasi Goreng, Mie Goreng, Dimsum | "Kami punya: Nasi Goreng, Mie Goreng, Dimsum dan lainnya. Mau pesan yang mana?" |

## Order Intent

| User Message | Cart State | Contextual Response |
|--------------|------------|---------------------|
| "mau pesan" | Empty | "Silakan sebutkan produk yang ingin Anda pesan. Contoh: 'pesan nasi goreng 2 porsi' atau ketik 'menu' untuk lihat daftar produk." |
| "mau pesan" | 2 items | "Anda sudah punya 2 item di keranjang. Mau tambah lagi atau langsung checkout? Ketik 'lihat keranjang' untuk detail." |
| "beli" | Empty | "Silakan sebutkan produk yang ingin Anda pesan. Contoh: 'pesan nasi goreng 2 porsi' atau ketik 'menu' untuk lihat daftar produk." |

**Dengan Product Name:**
| User Message | Search Results | Contextual Response |
|--------------|----------------|---------------------|
| "pesan dimsum" | Found: Dimsum Ayam, Dimsum Udang | "Saya menemukan produk yang Anda cari: Dimsum Ayam, Dimsum Udang. Berapa jumlah yang ingin Anda pesan? Contoh: 'pesan Dimsum Ayam 2 porsi'" |
| "pesan pizza" | Not found | "Maaf, produk yang Anda cari tidak tersedia. Ketik 'menu' untuk melihat daftar produk kami." |
| "beli nasi goreng" | Found: Nasi Goreng | "Saya menemukan produk yang Anda cari: Nasi Goreng. Berapa jumlah yang ingin Anda pesan? Contoh: 'pesan Nasi Goreng 2 porsi'" |

## Cart Inquiry

| User Message | Cart State | Contextual Response |
|--------------|------------|---------------------|
| "lihat keranjang" | Empty | "Keranjang Anda masih kosong. Silakan pesan produk terlebih dahulu. Ketik 'menu' untuk lihat produk." |
| "lihat keranjang" | 3 items | "Anda punya 3 item di keranjang. Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout." |
| "cek pesanan" | Empty | "Keranjang Anda masih kosong. Silakan pesan produk terlebih dahulu. Ketik 'menu' untuk lihat produk." |
| "cart saya" | 1 item | "Anda punya 1 item di keranjang. Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout." |

## Payment Inquiry

| User Message | Order State | Contextual Response |
|--------------|-------------|---------------------|
| "cara bayar" | No order | "Belum ada pesanan yang perlu dibayar. Silakan buat pesanan terlebih dahulu." |
| "cara bayar" | Has order | "Untuk melakukan pembayaran, silakan konfirmasi pesanan Anda terlebih dahulu." |
| "bayar pakai qris" | No order | "Belum ada pesanan yang perlu dibayar. Silakan buat pesanan terlebih dahulu." |
| "transfer" | Has order | "Untuk melakukan pembayaran, silakan konfirmasi pesanan Anda terlebih dahulu." |

## Help Request

| User Message | Contextual Response |
|--------------|---------------------|
| "bantuan" | "Saya bisa bantu Anda:<br>• Lihat menu: ketik 'menu' atau 'daftar produk'<br>• Pesan: ketik 'pesan [nama produk] [jumlah]'<br>• Lihat keranjang: ketik 'lihat keranjang'<br>• Checkout: ketik 'konfirmasi pesanan'<br><br>Ada yang bisa saya bantu?" |
| "help" | [Same as above] |
| "cara pesan" | [Same as above] |
| "gimana caranya" | [Same as above] |

## Thank You

| User Message | Contextual Response |
|--------------|---------------------|
| "terima kasih" | "Sama-sama! Ada lagi yang bisa saya bantu?" |
| "thanks" | "Sama-sama! Ada lagi yang bisa saya bantu?" |
| "thank you" | "Sama-sama! Ada lagi yang bisa saya bantu?" |
| "makasih" | "Sama-sama! Ada lagi yang bisa saya bantu?" |

## Cancel Intent

| User Message | Cart State | Contextual Response |
|--------------|------------|---------------------|
| "batal" | Has items | "Mau batalkan pesanan? Ketik 'hapus keranjang' untuk mengosongkan keranjang." |
| "batal" | Empty | "Baik, tidak jadi. Ada yang bisa saya bantu lagi?" |
| "cancel" | Has items | "Mau batalkan pesanan? Ketik 'hapus keranjang' untuk mengosongkan keranjang." |
| "tidak jadi" | Empty | "Baik, tidak jadi. Ada yang bisa saya bantu lagi?" |

## General Questions

| User Message | Contextual Response |
|--------------|---------------------|
| "jam buka" | "Untuk informasi jam operasional, silakan hubungi kami langsung. Ada yang bisa saya bantu untuk pemesanan?" |
| "lokasi dimana" | "Untuk informasi lokasi, silakan hubungi kami langsung. Mau pesan sesuatu?" |
| "alamat" | "Untuk informasi lokasi, silakan hubungi kami langsung. Mau pesan sesuatu?" |

## Unknown/Unclear Intent

| User Message | Cart State | Contextual Response |
|--------------|------------|---------------------|
| "abc xyz" | Empty | "Maaf, saya kurang mengerti. Bisa dijelaskan lebih detail atau coba:<br>• Ketik 'menu' untuk lihat produk<br>• Ketik 'pesan [produk] [jumlah]' untuk memesan<br>• Ketik 'bantuan' untuk info lebih lanjut" |
| "abc xyz" | Has items | "Anda punya 2 item di keranjang. Ketik 'lihat keranjang' untuk detail atau 'konfirmasi' untuk checkout. Atau mau pesan yang lain?" |

## Key Principles

### 1. Always Contextual
Response disesuaikan dengan:
- User intent (apa yang user inginkan)
- Conversation state (cart, order status)
- Search results (produk yang ditemukan)

### 2. Always Helpful
Setiap response memberikan:
- Info yang relevan
- Panduan konkret
- Contoh penggunaan
- Next action yang jelas

### 3. Always Friendly
Tone yang digunakan:
- Ramah dan sopan
- Tidak terlalu formal
- Mudah dipahami
- Encouraging

### 4. Always Actionable
User selalu tahu:
- Apa yang bisa dilakukan
- Bagaimana cara melakukannya
- Contoh command yang bisa digunakan

## Notes

- Semua response di atas adalah **fallback** ketika LLM gagal generate response
- Response akan lebih baik lagi jika LLM berfungsi normal
- Fallback memastikan user **tidak pernah** mendapat response kosong
- System **gracefully handles** semua edge cases
