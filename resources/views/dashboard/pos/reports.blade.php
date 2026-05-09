@extends('layouts.app')
@include('components.dashboard-scripts')

@push('head-scripts')
    @vite('resources/js/apexcharts.js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
@endpush

@section('title', __('pos.reports.title') . ' - QashierWise POS')

@section('content')
<div x-data="reportsApp()" x-init="initDashboard()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-reports'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('pos.reports.title'), 'description' => __('pos.reports.description')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Filters -->
                <div class="card p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Report Type</label>
                            <select x-model="reportType" @change="fetchReport()" class="input w-full min-h-[44px]">
                                <option value="daily">Daily Sales</option>
                                <option value="range">Date Range</option>
                                <option value="products">Top Products</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Store</label>
                            <select x-model="storeFilter" @change="fetchReport()" class="input w-full min-h-[44px]">
                                <option value="">All Stores</option>
                                <template x-for="store in stores" :key="store.id">
                                    <option :value="store.id" x-text="store.name"></option>
                                </template>
                            </select>
                        </div>
                        <div x-show="reportType === 'daily'">
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Date</label>
                            <input type="date" x-model="selectedDate" @change="fetchReport()" class="input w-full min-h-[44px]">
                        </div>
                        <template x-if="reportType === 'range' || reportType === 'products'">
                            <div class="sm:col-span-2 grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Start Date</label>
                                    <input type="date" x-model="startDate" @change="fetchReport()" class="input w-full min-h-[44px]">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">End Date</label>
                                    <input type="date" x-model="endDate" @change="fetchReport()" class="input w-full min-h-[44px]">
                                </div>
                            </div>
                        </template>
                    </div>
                    <!-- Export Buttons -->
                    <div class="flex justify-end gap-2 pt-3 mt-3 border-t border-[hsl(var(--border))]">
                        <button @click="exportExcel()" :disabled="loading"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border border-[hsl(var(--border))] rounded-lg hover:bg-[hsl(var(--muted)/0.5)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <i class="fas fa-file-excel text-green-600"></i>
                            <span>Excel</span>
                        </button>
                        <button @click="exportPDF()" :disabled="loading"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border border-[hsl(var(--border))] rounded-lg hover:bg-[hsl(var(--muted)/0.5)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <i class="fas fa-file-pdf text-red-600"></i>
                            <span>PDF</span>
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Total Sales</p>
                                <p class="text-lg font-bold" x-text="formatCurrency(summary.total_sales)"></p>
                            </div>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Total Orders</p>
                                <p class="text-lg font-bold" x-text="summary.total_orders"></p>
                            </div>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-receipt text-purple-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Avg Order</p>
                                <p class="text-lg font-bold" x-text="formatCurrency(summary.average_order)"></p>
                            </div>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                <i class="fas fa-box text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Items Sold</p>
                                <p class="text-lg font-bold" x-text="summary.items_sold"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="card p-4 sm:p-6">
                    <h3 class="font-semibold mb-4" x-text="reportType === 'products' ? 'Top Selling Products' : 'Sales Overview'"></h3>
                    <div class="relative" style="min-height: 300px;">
                        <template x-if="loading">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <i class="fas fa-spinner fa-spin text-2xl text-[hsl(var(--muted-foreground))]"></i>
                            </div>
                        </template>
                        <div id="reportChart" x-show="!loading"></div>
                    </div>
                </div>

                <!-- Top Products Table (for products report) -->
                <template x-if="reportType === 'products' && !loading">
                    <div class="card overflow-hidden">
                        <div class="p-4 border-b border-[hsl(var(--border))]">
                            <h3 class="font-semibold">Product Performance</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-[hsl(var(--muted)/0.5)]">
                                    <tr>
                                        <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">#</th>
                                        <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Product</th>
                                        <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Qty Sold</th>
                                        <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[hsl(var(--border))]">
                                    <template x-for="(product, index) in topProducts" :key="product.product_id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)]">
                                            <td class="p-4 text-sm" x-text="index + 1"></td>
                                            <td class="p-4 font-medium" x-text="product.product_name"></td>
                                            <td class="p-4 text-right" x-text="product.total_quantity"></td>
                                            <td class="p-4 text-right font-medium text-[hsl(var(--primary))]" x-text="formatCurrency(product.total_revenue)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <!-- Daily Breakdown (for range report) -->
                <template x-if="reportType === 'range' && !loading && dailyBreakdown.length > 0">
                    <div class="card overflow-hidden">
                        <div class="p-4 border-b border-[hsl(var(--border))]">
                            <h3 class="font-semibold">Daily Breakdown</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-[hsl(var(--muted)/0.5)]">
                                    <tr>
                                        <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Date</th>
                                        <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Orders</th>
                                        <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Sales</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[hsl(var(--border))]">
                                    <template x-for="day in dailyBreakdown" :key="day.date">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)]">
                                            <td class="p-4" x-text="formatDateShort(day.date)"></td>
                                            <td class="p-4 text-right" x-text="day.total_orders"></td>
                                            <td class="p-4 text-right font-medium text-[hsl(var(--primary))]" x-text="formatCurrency(day.total_sales)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</div>

<!-- ApexCharts is now loaded via Vite bundle (window.ApexCharts) -->
<script>
function reportsApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true,
        stores: [], topProducts: [], dailyBreakdown: [],
        reportType: 'daily', storeFilter: '',
        selectedDate: new Date().toISOString().split('T')[0],
        startDate: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        endDate: new Date().toISOString().split('T')[0],
        summary: { total_sales: 0, total_orders: 0, average_order: 0, items_sold: 0 },
        chart: null,
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],
        userPermissions: [], isAdmin: true,

        async init() { this.initDashboard(); await this.fetchUserPermissions(); await this.fetchStores(); await this.fetchReport(); },

        initDashboard() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) { this.sidebarOpen = false; }
            else { let s = localStorage.getItem('sidebarOpen'); if (s !== null) this.sidebarOpen = JSON.parse(s); }
            this.$watch('sidebarOpen', v => { if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v)); });
            window.addEventListener('resize', () => {
                const was = this.isMobile; this.isMobile = window.innerWidth < 768;
                if (was && !this.isMobile) { let s = localStorage.getItem('sidebarOpen'); this.sidebarOpen = s !== null ? JSON.parse(s) : true; }
                else if (!was && this.isMobile) { this.sidebarOpen = false; }
            });
            let u = localStorage.getItem('user'); if (u) { try { this.user = JSON.parse(u); } catch (e) { this.user = { name: 'User' }; } } else { this.user = { name: 'User' }; }
            this.fetchUserPermissions();
        },

        async fetchUserPermissions() {
            try {
                const token = localStorage.getItem('token');
                if (!token) { this.isAdmin = true; this.userPermissions = []; return; }
                const res = await fetch(`${window.location.origin}/api/user/permissions`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.success) { this.isAdmin = data.data.is_admin || false; this.userPermissions = data.data.permissions || []; }
                }
            } catch (e) { console.error('Failed to fetch permissions:', e); }
        },

        hasPermission(permission) {
            if (this.isAdmin) return true;
            if (this.userPermissions.includes('*')) return true;
            return this.userPermissions.includes(permission);
        },

        async fetchStores() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/stores`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.stores = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); }
        },

        async fetchReport() {
            // Check permission first
            if (!this.hasPermission('view_reports') && !this.hasPermission('manage_reports')) {
                this.loading = false; return;
            }
            this.loading = true;
            let reportData = null;
            try {
                const token = localStorage.getItem('token');
                let url = `${this.API_BASE_URL}/reports/`;
                const params = new URLSearchParams();
                if (this.storeFilter) params.append('store_id', this.storeFilter);

                if (this.reportType === 'daily') {
                    url += `daily?date=${this.selectedDate}&${params}`;
                } else if (this.reportType === 'range') {
                    url += `range?start_date=${this.startDate}&end_date=${this.endDate}&${params}`;
                } else {
                    url += `top-products?start_date=${this.startDate}&end_date=${this.endDate}&${params}`;
                }

                const res = await fetch(url, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.summary = { total_sales: data.data.total_sales || 0, total_orders: data.data.total_orders || 0, average_order: data.data.average_order_value || 0, items_sold: data.data.total_items || 0 };
                    this.topProducts = data.data.products || [];
                    this.dailyBreakdown = data.data.daily_breakdown || [];
                    reportData = data.data;
                }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
            if (reportData) { this.$nextTick(() => this.renderChart(reportData)); }
        },

        renderChart(data) {
            if (!window.ApexCharts) { console.error('ApexCharts not loaded'); return; }
            if (this.chart) { this.chart.destroy(); this.chart = null; }
            const el = document.querySelector('#reportChart');
            if (!el) return;

            let options = { chart: { type: 'bar', height: 300, toolbar: { show: false } }, colors: ['hsl(262.1, 83.3%, 57.8%)'] };

            if (this.reportType === 'products') {
                const products = (data.products || []).slice(0, 10);
                options.series = [{ name: 'Revenue', data: products.map(p => p.total_revenue || 0) }];
                options.xaxis = { categories: products.map(p => p.product_name || 'Unknown') };
            } else if (this.reportType === 'range') {
                const daily = data.daily_breakdown || [];
                options.series = [{ name: 'Sales', data: daily.map(d => d.total_sales || 0) }];
                options.xaxis = { categories: daily.map(d => this.formatDateShort(d.date)) };
            } else {
                options.series = [{ name: 'Sales', data: [data.total_sales || 0] }];
                options.xaxis = { categories: [this.selectedDate] };
            }

            this.chart = new window.ApexCharts(el, options);
            this.chart.render();
        },

        formatCurrency(a) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(a || 0); },
        formatDateShort(d) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '-'; },

        exportPDF() {
            if (!window.jspdf) { alert('PDF library belum siap, coba refresh halaman.'); return; }
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const purple = [88, 28, 220];
            const filename = `laporan-${this.reportType}-${new Date().toISOString().split('T')[0]}.pdf`;

            const typeLabel = { daily: 'Penjualan Harian', range: 'Rentang Tanggal', products: 'Produk Terlaris' };
            let periodLabel = '';
            if (this.reportType === 'daily') periodLabel = `Tanggal: ${this.selectedDate}`;
            else periodLabel = `Periode: ${this.startDate} s/d ${this.endDate}`;

            doc.setFontSize(20); doc.setTextColor(...purple);
            doc.text('Laporan Penjualan', 14, 20);
            doc.setFontSize(11); doc.setTextColor(80, 80, 80);
            doc.text(`Tipe: ${typeLabel[this.reportType]}`, 14, 29);
            doc.text(periodLabel, 14, 36);
            doc.text(`Dicetak: ${new Date().toLocaleString('id-ID')}`, 14, 43);

            doc.autoTable({
                startY: 50,
                head: [['Metrik', 'Nilai']],
                body: [
                    ['Total Penjualan', this.formatCurrency(this.summary.total_sales)],
                    ['Total Pesanan', String(this.summary.total_orders)],
                    ['Rata-rata Pesanan', this.formatCurrency(this.summary.average_order)],
                    ['Item Terjual', String(this.summary.items_sold)],
                ],
                theme: 'grid',
                headStyles: { fillColor: purple, textColor: 255, fontStyle: 'bold' },
                columnStyles: { 1: { halign: 'right' } },
            });

            let finalY = doc.lastAutoTable.finalY + 10;

            if (this.reportType === 'products' && this.topProducts.length > 0) {
                doc.setFontSize(13); doc.setTextColor(...purple);
                doc.text('Performa Produk', 14, finalY);
                doc.autoTable({
                    startY: finalY + 4,
                    head: [['#', 'Produk', 'Qty Terjual', 'Pendapatan']],
                    body: this.topProducts.map((p, i) => [i + 1, p.product_name, p.total_quantity, this.formatCurrency(p.total_revenue)]),
                    theme: 'striped',
                    headStyles: { fillColor: purple, textColor: 255, fontStyle: 'bold' },
                    columnStyles: { 0: { halign: 'center', cellWidth: 12 }, 2: { halign: 'right' }, 3: { halign: 'right' } },
                });
            } else if (this.reportType === 'range' && this.dailyBreakdown.length > 0) {
                doc.setFontSize(13); doc.setTextColor(...purple);
                doc.text('Rincian Harian', 14, finalY);
                doc.autoTable({
                    startY: finalY + 4,
                    head: [['Tanggal', 'Pesanan', 'Penjualan']],
                    body: this.dailyBreakdown.map(d => [this.formatDateShort(d.date), d.total_orders, this.formatCurrency(d.total_sales)]),
                    theme: 'striped',
                    headStyles: { fillColor: purple, textColor: 255, fontStyle: 'bold' },
                    columnStyles: { 1: { halign: 'right' }, 2: { halign: 'right' } },
                });
            }

            doc.save(filename);
        },

        exportExcel() {
            if (!window.XLSX) { alert('Excel library belum siap, coba refresh halaman.'); return; }
            const wb = XLSX.utils.book_new();
            const filename = `laporan-${this.reportType}-${new Date().toISOString().split('T')[0]}.xlsx`;
            const typeLabel = { daily: 'Penjualan Harian', range: 'Rentang Tanggal', products: 'Produk Terlaris' };

            const summaryRows = [
                ['Laporan Penjualan - QashierWise'],
                ['Tipe Laporan', typeLabel[this.reportType]],
                this.reportType === 'daily'
                    ? ['Tanggal', this.selectedDate]
                    : ['Periode', `${this.startDate} s/d ${this.endDate}`],
                ['Dicetak', new Date().toLocaleString('id-ID')],
                [],
                ['RINGKASAN'],
                ['Total Penjualan', this.summary.total_sales],
                ['Total Pesanan', this.summary.total_orders],
                ['Rata-rata Pesanan', this.summary.average_order],
                ['Item Terjual', this.summary.items_sold],
            ];
            const wsSummary = XLSX.utils.aoa_to_sheet(summaryRows);
            wsSummary['!cols'] = [{ wch: 22 }, { wch: 20 }];
            XLSX.utils.book_append_sheet(wb, wsSummary, 'Ringkasan');

            if (this.reportType === 'products' && this.topProducts.length > 0) {
                const rows = [
                    ['#', 'Produk', 'Qty Terjual', 'Pendapatan (IDR)'],
                    ...this.topProducts.map((p, i) => [i + 1, p.product_name, p.total_quantity, p.total_revenue]),
                ];
                const ws = XLSX.utils.aoa_to_sheet(rows);
                ws['!cols'] = [{ wch: 5 }, { wch: 35 }, { wch: 14 }, { wch: 20 }];
                XLSX.utils.book_append_sheet(wb, ws, 'Produk Terlaris');
            } else if (this.reportType === 'range' && this.dailyBreakdown.length > 0) {
                const rows = [
                    ['Tanggal', 'Total Pesanan', 'Total Penjualan (IDR)'],
                    ...this.dailyBreakdown.map(d => [d.date, d.total_orders, d.total_sales]),
                ];
                const ws = XLSX.utils.aoa_to_sheet(rows);
                ws['!cols'] = [{ wch: 14 }, { wch: 16 }, { wch: 22 }];
                XLSX.utils.book_append_sheet(wb, ws, 'Rincian Harian');
            } else if (this.reportType === 'daily') {
                const rows = [
                    ['Metrik', 'Nilai'],
                    ['Total Penjualan', this.summary.total_sales],
                    ['Total Pesanan', this.summary.total_orders],
                    ['Rata-rata Pesanan', this.summary.average_order],
                    ['Item Terjual', this.summary.items_sold],
                ];
                const ws = XLSX.utils.aoa_to_sheet(rows);
                ws['!cols'] = [{ wch: 22 }, { wch: 20 }];
                XLSX.utils.book_append_sheet(wb, ws, 'Penjualan Harian');
            }

            XLSX.writeFile(wb, filename);
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
