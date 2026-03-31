# 📊 Dashboard Analytics - QashierWise

## ✅ Widgets yang Sudah Ditambahkan

### 1. **Blog Stats Widget** 📈
Menampilkan statistik utama blog:
- **Total Blog Posts** - Jumlah semua artikel
- **Published Posts** - Artikel yang sudah dipublikasi
- **Draft Posts** - Artikel dalam draft
- **Categories** - Total kategori blog
- **Tags** - Total tag blog

Setiap stat dilengkapi dengan:
- Mini chart untuk visualisasi trend
- Icon yang relevan
- Color coding (success, warning, info, dll)

### 2. **Blog Posts Chart** 📉
Line chart yang menampilkan:
- Jumlah blog posts per bulan
- Data 6 bulan terakhir
- Gradient purple QashierWise
- Interactive hover tooltips

### 3. **Popular Categories** 🥧
Doughnut chart yang menampilkan:
- Top 5 kategori berdasarkan jumlah posts
- Color gradient purple
- Percentage distribution

### 4. **Recent Blog Posts** 📝
Table widget yang menampilkan:
- 5 artikel blog terbaru
- Kolom: Judul, Kategori, Status, Tanggal Publish
- Badge untuk kategori dan status
- Sortable columns

## 🎨 Fitur

- ✅ **Real-time data** - Data langsung dari database
- ✅ **Responsive** - Tampil bagus di semua device
- ✅ **Interactive charts** - Hover untuk detail
- ✅ **Color coded** - Purple accent QashierWise
- ✅ **Sortable** - Widget bisa diurutkan
- ✅ **Glass effect** - Mengikuti theme glassmorphism

## 📍 Lokasi File

```
app/Filament/Widgets/
├── BlogStatsWidget.php          # Stats overview
├── BlogPostsChartWidget.php     # Line chart
├── PopularCategoriesWidget.php  # Doughnut chart
└── RecentBlogPostsWidget.php    # Recent posts table
```

## 🔧 Kustomisasi

### Ubah Jumlah Data

**Recent Posts:**
```php
// RecentBlogPostsWidget.php
->limit(5) // Ubah angka ini
```

**Popular Categories:**
```php
// PopularCategoriesWidget.php
->limit(5) // Ubah angka ini
```

**Chart Period:**
```php
// BlogPostsChartWidget.php
->where('created_at', '>=', now()->subMonths(6)) // Ubah periode
```

### Ubah Warna Chart

**Line Chart:**
```php
'backgroundColor' => 'rgba(139, 92, 246, 0.2)',
'borderColor' => 'rgba(139, 92, 246, 1)',
```

**Doughnut Chart:**
```php
'backgroundColor' => [
    'rgba(139, 92, 246, 0.8)',  // Purple
    'rgba(167, 139, 250, 0.8)',  // Light purple
    // ... tambah warna lain
],
```

### Ubah Urutan Widget

```php
// Di setiap widget
protected static ?int $sort = 1; // Ubah angka ini
```

Semakin kecil angka, semakin atas posisinya.

## 📊 Menambah Widget Baru

### 1. Stats Widget
```bash
php artisan make:filament-widget NewStatsWidget --stats-overview
```

### 2. Chart Widget
```bash
php artisan make:filament-widget NewChartWidget --chart
```

### 3. Table Widget
```bash
php artisan make:filament-widget NewTableWidget --table
```

### 4. Register Widget
```php
// AdminPanelProvider.php
->widgets([
    BlogStatsWidget::class,
    NewWidget::class, // Tambahkan di sini
])
```

## 🎯 Widget Ideas untuk Future

### Blog Analytics
- **Views per Post** - Most viewed articles
- **Comments Count** - Most commented posts
- **Author Stats** - Posts per author
- **Publishing Schedule** - Scheduled posts calendar

### User Analytics
- **Active Users** - Daily/Monthly active users
- **New Registrations** - User growth chart
- **User Roles** - Distribution pie chart

### System Analytics
- **Storage Usage** - Media storage stats
- **Database Size** - Database growth
- **API Calls** - API usage statistics

### E-commerce (jika ada)
- **Revenue** - Total revenue
- **Orders** - Order statistics
- **Products** - Product performance
- **Customers** - Customer analytics

## 💡 Tips

1. **Performance** - Gunakan caching untuk data yang jarang berubah
2. **Lazy Loading** - Load widget on demand untuk performa
3. **Permissions** - Batasi widget berdasarkan role user
4. **Refresh** - Tambahkan auto-refresh untuk real-time data

## 🔄 Refresh Data

Dashboard akan refresh otomatis saat:
- Page reload
- Navigate ke dashboard
- Data berubah (create/update/delete)

Untuk manual refresh:
```php
// Tambahkan di widget
protected static ?string $pollingInterval = '10s'; // Auto refresh tiap 10 detik
```

## 📱 Responsive

Widgets otomatis responsive:
- **Mobile** - 1 column
- **Tablet** - 2 columns
- **Desktop** - 3-4 columns

Untuk custom layout:
```php
protected int | string | array $columnSpan = 'full'; // Full width
protected int | string | array $columnSpan = 2; // 2 columns
```

---

**Dashboard sekarang lebih informatif dan berguna! 🎉**

Akses: `http://127.0.0.1:8000/admin`
