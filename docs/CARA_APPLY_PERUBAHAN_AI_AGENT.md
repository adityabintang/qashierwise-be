# Cara Apply Perubahan AI Agent

## Perubahan yang Sudah Dilakukan
✅ System prompt sudah diupdate di `app/Models/AiAgent.php`
✅ Function tools sudah diupdate di `app/Services/AiAgentService.php`

## Cara Mengaktifkan Perubahan

### Opsi 1: Save Ulang Konfigurasi AI Agent (RECOMMENDED)
1. Buka halaman **AI Agent** di dashboard
2. Klik tombol **"Simpan Konfigurasi"** (tidak perlu mengubah apapun)
3. Perubahan akan langsung aktif

### Opsi 2: Restart Aplikasi
Jika menggunakan development server:
```bash
# Stop server (Ctrl+C)
# Start ulang
php artisan serve
```

Jika menggunakan production:
```bash
# Restart PHP-FPM atau web server
sudo systemctl restart php-fpm
# atau
sudo systemctl restart nginx
```

## Cara Testing

### Test 1: Tampilkan Menu
**Input user:**
```
menunya apa aja?
```

**Expected output:**
```
📋 Berikut daftar menu/produk kami:

1. Dimsum Keju
   💰 Rp 40.000
   📦 Stok: 10

2. Teh Jumbo
   💰 Rp 5.000
   📦 Stok: 50

Silakan pilih produk yang Anda inginkan! 😊
```

**TIDAK BOLEH menampilkan ID produk!**

### Test 2: Pesan Produk
**Input user:**
```
pesan dimsum keju 2 dan teh jumbo 1
```

**Expected behavior:**
1. AI panggil `search_products('dimsum keju')` → dapat ID
2. AI panggil `search_products('teh jumbo')` → dapat ID
3. AI panggil `add_to_cart(products=[...])` dengan ID yang didapat
4. AI tampilkan hasil

**Expected output:**
```
Baik! ✅ Berhasil menambahkan ke keranjang!

📦 Dimsum Keju x2
📦 Teh Jumbo x1

Ketik "lihat keranjang" untuk melihat ringkasan pesanan.
```

**TIDAK BOLEH muncul error "Maaf, parameter tidak lengkap. Mohon berikan ID produk."**

### Test 3: Pesan Produk Tidak Ada
**Input user:**
```
pesan pizza
```

**Expected output:**
```
Maaf, kami tidak memiliki pizza di menu. Apakah Anda ingin melihat menu lain yang tersedia?
```

## Troubleshooting

### Masalah: Masih muncul "Mohon berikan ID produk"
**Solusi:**
1. Pastikan sudah save ulang konfigurasi AI Agent
2. Clear cache Laravel: `php artisan cache:clear`
3. Restart aplikasi

### Masalah: AI Agent tidak memanggil search_products
**Solusi:**
1. Periksa log di `storage/logs/laravel.log`
2. Pastikan LLM API berfungsi dengan baik
3. Coba test ulang dengan kalimat yang lebih jelas

### Masalah: ID produk masih muncul ke user
**Solusi:**
1. Pastikan perubahan di `app/Models/AiAgent.php` sudah tersimpan
2. Save ulang konfigurasi AI Agent di dashboard
3. Test ulang

## Monitoring
Untuk melihat log AI Agent:
```bash
tail -f storage/logs/laravel.log | grep "AI Agent"
```

Untuk melihat tool calls:
```bash
tail -f storage/logs/laravel.log | grep "tool_calls"
```
