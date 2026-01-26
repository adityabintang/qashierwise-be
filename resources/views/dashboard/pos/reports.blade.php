@extends('layouts.app')

@section('title', __('pos.reports.title') . ' - QashierWise POS')

@section('content')
<div x-data="reportsApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
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
                                    <template x-for="(product, index) in topProducts" :key="product.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)]">
                                            <td class="p-4 text-sm" x-text="index + 1"></td>
                                            <td class="p-4 font-medium" x-text="product.name"></td>
                                            <td class="p-4 text-right" x-text="product.quantity_sold"></td>
                                            <td class="p-4 text-right font-medium text-[hsl(var(--primary))]" x-text="formatCurrency(product.revenue)"></td>
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
                                            <td class="p-4 text-right" x-text="day.orders"></td>
                                            <td class="p-4 text-right font-medium text-[hsl(var(--primary))]" x-text="formatCurrency(day.sales)"></td>
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

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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

        async init() { this.initSidebar(); await this.fetchStores(); await this.fetchReport(); },

        initSidebar() {
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
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                let url = `${this.API_BASE_URL}/reports/`;
                const params = new URLSearchParams();
                if (this.storeFilter) params.append('store_id', this.storeFilter);

                if (this.reportType === 'daily') {
                    url += `daily?date=${this.selectedDate}&${params}`;
                } else if (this.reportType === 'range') {
                    url += `range?start=${this.startDate}&end=${this.endDate}&${params}`;
                } else {
                    url += `top-products?start=${this.startDate}&end=${this.endDate}&${params}`;
                }

                const res = await fetch(url, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.summary = data.data.summary || { total_sales: data.data.total_sales || 0, total_orders: data.data.total_orders || 0, average_order: data.data.average_order || 0, items_sold: data.data.items_sold || 0 };
                    this.topProducts = data.data.products || [];
                    this.dailyBreakdown = data.data.daily || [];
                    this.$nextTick(() => this.renderChart(data.data));
                }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        renderChart(data) {
            if (this.chart) { this.chart.destroy(); }
            const el = document.querySelector('#reportChart');
            if (!el) return;

            let options = { chart: { type: 'bar', height: 300, toolbar: { show: false } }, colors: ['hsl(262.1, 83.3%, 57.8%)'] };

            if (this.reportType === 'products') {
                const products = (data.products || []).slice(0, 10);
                options.series = [{ name: 'Revenue', data: products.map(p => p.revenue || 0) }];
                options.xaxis = { categories: products.map(p => p.name || 'Unknown') };
            } else if (this.reportType === 'range') {
                const daily = data.daily || [];
                options.series = [{ name: 'Sales', data: daily.map(d => d.sales || 0) }];
                options.xaxis = { categories: daily.map(d => this.formatDateShort(d.date)) };
            } else {
                options.series = [{ name: 'Sales', data: [data.total_sales || 0] }];
                options.xaxis = { categories: [this.selectedDate] };
            }

            this.chart = new ApexCharts(el, options);
            this.chart.render();
        },

        formatCurrency(a) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(a || 0); },
        formatDateShort(d) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '-'; },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
