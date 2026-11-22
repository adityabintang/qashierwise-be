# Fix: Reservation Calendar Timezone Issue

## Problem
Waktu reservasi yang di-mark di kalender tidak sesuai. Contoh: reservasi dibuat untuk jam 17:00 WIB, tetapi ditampilkan di kalender pada jam yang berbeda karena masalah timezone.

## Root Cause
1. **Timezone tidak ditentukan saat parsing waktu**: Pada `ReservationController::calendar()`, waktu reservasi di-parse tanpa menentukan timezone, sehingga menggunakan default timezone aplikasi (UTC).
2. **Field `reservation_time` tidak ada**: Tabel reservasi tidak memiliki kolom `reservation_time`, namun kode mencoba mengaksesnya.

## Solution

### 1. Menambahkan Kolom `reservation_time`
**File**: `database/migrations/2026_02_15_131320_add_reservation_datetime_to_reservations_table.php`
- Menambahkan kolom `reservation_time` (nullable) ke tabel reservations

### 2. Update Model Reservation
**File**: `app/Models/Reservation.php`
- Menambahkan `reservation_time` ke array `$fillable`

### 3. Update Factory
**File**: `database/factories/ReservationFactory.php`
- Menambahkan `reservation_time` dengan nilai default random time dalam factory definition

### 4. Fix Timezone Parsing
**File**: `app/Http/Controllers/Api/ReservationController.php`

**Perubahan utama**:
```php
// SEBELUM (UTC timezone):
$startDateTime = Carbon::parse($reservation->reservation_date.' '.$reservationTime);

// SESUDAH (WIB timezone):
$dateString = $reservation->reservation_date instanceof \Carbon\Carbon
    ? $reservation->reservation_date->toDateString()
    : $reservation->reservation_date;
$startDateTime = Carbon::parse($dateString.' '.$reservationTime, 'Asia/Jakarta');
```

**Penjelasan**:
- Parse waktu menggunakan timezone `Asia/Jakarta` (WIB)
- Convert `reservation_date` ke string dulu sebelum digabungkan dengan time (karena sudah di-cast sebagai Carbon object)
- ISO8601 string yang dikirim ke frontend sudah include timezone offset yang benar

### 5. Test Coverage
**Files**: 
- `tests/Feature/ReservationCalendarTimezoneTest.php` (NEW)
- `tests/Feature/ReservationCalendarTest.php` (UPDATED)

**Test yang dibuat**:
1. `test_reservation_at_17_00_shows_correct_time_in_calendar`: Memastikan reservasi jam 17:00 WIB ditampilkan dengan benar
2. `test_multiple_reservations_at_different_times_show_correctly`: Memastikan multiple reservasi di waktu berbeda (07:00, 12:00, 17:00, 20:00) semua ditampilkan dengan benar

## Impact
- ✅ Reservasi dengan waktu tertentu sekarang ditampilkan di kalender pada jam yang benar sesuai WIB
- ✅ Format ISO8601 yang dikirim ke frontend sudah include timezone offset
- ✅ Frontend dapat menampilkan waktu dengan benar menggunakan `extendedProps.reservation_time`
- ✅ All tests passing

## How to Apply
1. Run migration:
   ```bash
   php artisan migrate
   ```

2. Test yang sudah dibuat akan otomatis run saat test suite:
   ```bash
   php artisan test --filter=ReservationCalendar
   ```

## Notes
- Waktu di database disimpan sebagai string format "HH:mm" (e.g., "17:00")
- Backend parse dengan timezone Asia/Jakarta saat membaca
- Frontend menerima ISO8601 string dengan timezone offset yang benar
- Jika `reservation_time` null, akan menampilkan "Waktu belum ditentukan"
