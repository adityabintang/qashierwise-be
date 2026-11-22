# Perbaikan Perhitungan Period Subscription

## Masalah
Ketika user subscribe di tanggal 31 Januari untuk durasi 1 bulan, `period_end` menjadi 3 Maret (bukan 28 Februari). Ini terjadi karena Carbon `addMonth()` akan overflow ke bulan berikutnya jika tanggal tidak ada di bulan target.

### Contoh Masalah:
- Subscribe: 31 Januari 2026
- Expected: 28 Februari 2026 (akhir bulan Februari)
- Actual (sebelum fix): 3 Maret 2026 (overflow karena Februari tidak punya tanggal 31)

## Solusi
Menggunakan `addMonthNoOverflow()` dan `addMonthsNoOverflow()` dari Carbon yang akan:
- Jika tanggal tidak ada di bulan target, gunakan hari terakhir bulan tersebut
- 31 Januari + 1 bulan = 28 Februari (atau 29 di tahun kabisat)
- 31 Maret + 1 bulan = 30 April
- 30 Januari + 1 bulan = 28 Februari (atau 29 di tahun kabisat)

## File yang Diperbaiki

### 1. `app/Services/SubscriptionService.php`
- Line 281-282: Mengubah `addMonth()` menjadi `addMonthNoOverflow()`
- Line 513-517: Mengubah `addMonths()` menjadi `addMonthsNoOverflow()` di method `calculatePeriodEnd()`

### 2. `app/Http/Controllers/Api/MidtransWebhookController.php`
- Line 570: Mengubah `addMonths($months)` menjadi `addMonthsNoOverflow($months)`

## Testing
Untuk memverifikasi fix ini bekerja dengan benar:

```php
// Test case 1: Subscribe di tanggal 31 Januari
$start = Carbon::parse('2026-01-31');
$end = $start->copy()->addMonthNoOverflow();
// Expected: 2026-02-28
// Actual: 2026-02-28 ✓

// Test case 2: Subscribe di tanggal 31 Maret
$start = Carbon::parse('2026-03-31');
$end = $start->copy()->addMonthNoOverflow();
// Expected: 2026-04-30
// Actual: 2026-04-30 ✓

// Test case 3: Subscribe di tanggal 15 Januari (normal case)
$start = Carbon::parse('2026-01-15');
$end = $start->copy()->addMonthNoOverflow();
// Expected: 2026-02-15
// Actual: 2026-02-15 ✓
```

## Memperbaiki Data yang Sudah Ada

Untuk memperbaiki subscription yang sudah ada dengan `period_end` yang salah, jalankan command:

```bash
# Cek dulu tanpa mengubah data (dry-run)
php artisan subscription:fix-periods --dry-run

# Perbaiki data
php artisan subscription:fix-periods
```

Command ini akan:
1. Mencari semua subscription dengan status `active` atau `cancelled`
2. Menghitung ulang `current_period_end` yang benar menggunakan `addMonthsNoOverflow()`
3. Update database jika ada perbedaan

### Hasil Eksekusi
```
=== Fixing Subscription Period Dates ===
Found 1 subscriptions to check

Subscription ID: 1
  User ID: 1
  Plan: pro
  Start: 2026-01-30
  End (incorrect): 2026-03-02
  End (correct): 2026-02-28
  ✓ Fixed!

=== Summary ===
Fixed: 1 subscriptions
Already correct: 0 subscriptions
```

## Impact
- Subscription yang dibuat setelah fix ini akan memiliki `period_end` yang benar
- Subscription yang sudah ada telah diperbaiki menggunakan command `subscription:fix-periods`
- Tidak ada breaking changes, hanya perbaikan logic perhitungan tanggal
- UI sekarang akan menampilkan tanggal yang benar (28 Februari, bukan 3 Maret)
