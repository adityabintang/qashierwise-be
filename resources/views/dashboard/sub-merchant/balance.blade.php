@extends('layouts.app')
@include('components.dashboard-scripts')

@push('head-scripts')
    @vite('resources/js/apexcharts.js')
@endpush

@section('title', __('submerchant.balance_title'))

@section('content')
<div x-data="balanceApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant-balance'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('submerchant.balance'), 'description' => __('submerchant.balance_overview')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Not Registered State -->
                <template x-if="!loading && !isSubMerchant">
                    <div class="card p-8 text-center">
                        <div class="h-16 w-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-amber-500"></i>
                        </div>
                        <h2 class="text-xl font-bold mb-2">{{ __('submerchant.not_registered') }}</h2>
                        <p class="text-[hsl(var(--muted-foreground))] mb-6">{{ __('submerchant.not_registered_message') }}</p>
                        <a href="/dashboard/sub-merchant/register" class="btn btn-primary">
                            <i class="fas fa-user-plus mr-2"></i>
                            {{ __('submerchant.register_now') }}
                        </a>
                    </div>
                </template>

                <!-- Main Content -->
                <template x-if="!loading && isSubMerchant">
                    <div class="space-y-6">
                        <!-- Balance Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="card p-6 hover:shadow-lg transition-all duration-200 border-l-4 border-emerald-500">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-2">{{ __('submerchant.available_balance') }}</p>
                                        <p class="text-3xl font-bold text-emerald-600" x-text="formatCurrency(balance.available)">Rp 0</p>
                                    </div>
                                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-lg">
                                        <i class="fas fa-wallet text-2xl text-white"></i>
                                    </div>
                                </div>
                                <a href="/dashboard/sub-merchant/withdrawals" class="flex items-center justify-center gap-2 w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-md hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
                                    <i class="fas fa-money-bill-wave text-lg"></i>
                                    <span>Tarik Saldo</span>
                                </a>
                            </div>

                            <div class="card p-6 hover:shadow-lg transition-all duration-200 border-l-4 border-amber-500">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-2">{{ __('submerchant.pending_balance') }}</p>
                                        <p class="text-3xl font-bold text-amber-600" x-text="formatCurrency(balance.pending)">Rp 0</p>
                                    </div>
                                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center shadow-lg">
                                        <i class="fas fa-clock text-2xl text-white"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-amber-600 font-medium mt-3 px-3 py-2 bg-amber-50 rounded-lg">{{ __('submerchant.being_processed') }}</p>
                            </div>

                            <div class="card p-6 hover:shadow-lg transition-all duration-200 border-l-4 border-blue-500">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-2">{{ __('submerchant.total_earned') }}</p>
                                        <p class="text-3xl font-bold text-blue-600" x-text="formatCurrency(balance.total_earned)">Rp 0</p>
                                    </div>
                                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-lg">
                                        <i class="fas fa-chart-line text-2xl text-white"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-blue-600 font-medium mt-3 px-3 py-2 bg-blue-50 rounded-lg">{{ __('submerchant.all_time_earnings') }}</p>
                            </div>

                            <div class="card p-6 hover:shadow-lg transition-all duration-200 border-l-4 border-purple-500">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-2">{{ __('submerchant.total_transactions') }}</p>
                                        <p class="text-3xl font-bold text-purple-600" x-text="balance.total_transactions || 0">0</p>
                                    </div>
                                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shadow-lg">
                                        <i class="fas fa-receipt text-2xl text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Earnings Summary -->
                        <div class="card">
                            <div class="card-header !flex-row items-center justify-between">
                                <div>
                                    <h2 class="card-title">{{ __('submerchant.earnings_summary') }}</h2>
                                    <p class="card-description">{{ __('submerchant.monthly_breakdown') }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <select x-model="selectedMonth" @change="fetchDailyEarnings()" class="input w-auto text-sm">
                                        <template x-for="m in months" :key="m.value">
                                            <option :value="m.value" x-text="m.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <div class="card-content">
                                <!-- Summary Stats -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                    <div class="text-center p-3 bg-[hsl(var(--muted)/0.5)] rounded-lg">
                                        <p class="text-2xl font-bold" x-text="earningsSummary.transaction_count">0</p>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ __('submerchant.transactions') }}</p>
                                    </div>
                                    <div class="text-center p-3 bg-[hsl(var(--muted)/0.5)] rounded-lg">
                                        <p class="text-2xl font-bold" x-text="formatCurrencyShort(earningsSummary.gross_earnings)">Rp 0</p>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ __('submerchant.gross') }}</p>
                                    </div>
                                    <div class="text-center p-3 bg-[hsl(var(--muted)/0.5)] rounded-lg">
                                        <p class="text-2xl font-bold text-red-500" x-text="formatCurrencyShort(earningsSummary.total_fees)">Rp 0</p>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ __('submerchant.fee') }} (2.5%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-emerald-50 rounded-lg">
                                        <p class="text-2xl font-bold text-emerald-600" x-text="formatCurrencyShort(earningsSummary.net_earnings)">Rp 0</p>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ __('submerchant.net_amount') }}</p>
                                    </div>
                                </div>

                                <!-- Daily Chart Placeholder -->
                                <div class="h-64 flex items-center justify-center bg-[hsl(var(--muted)/0.3)] rounded-lg">
                                    <template x-if="loadingDaily">
                                        <div class="text-center">
                                            <i class="fas fa-spinner animate-spin text-2xl text-[hsl(var(--muted-foreground))]"></i>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mt-2">{{ __('submerchant.loading') }}</p>
                                        </div>
                                    </template>
                                    <template x-if="!loadingDaily && dailyEarnings.length === 0">
                                        <div class="text-center">
                                            <i class="fas fa-chart-bar text-4xl text-[hsl(var(--muted-foreground))]"></i>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mt-2">{{ __('submerchant.no_data_for_period') }}</p>
                                        </div>
                                    </template>
                                    <template x-if="!loadingDaily && dailyEarnings.length > 0">
                                        <div class="w-full h-full p-4">
                                            <div id="earningsChart" class="w-full h-full"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction History -->
                        <div class="card">
                            <div class="card-header !flex-row items-center justify-between">
                                <div>
                                    <h2 class="card-title">{{ __('submerchant.transaction_history') }}</h2>
                                    <p class="card-description">{{ __('submerchant.all_settled_transactions') }}</p>
                                </div>
                            </div>
                            <div class="card-content">
                                <!-- Loading -->
                                <template x-if="loadingTransactions">
                                    <div class="space-y-3">
                                        <template x-for="i in 5" :key="'skeleton-'+i">
                                            <div class="flex items-center gap-4 p-3 rounded-lg bg-[hsl(var(--muted)/0.5)]">
                                                <div class="skeleton h-10 w-10 rounded-lg"></div>
                                                <div class="flex-1 space-y-2">
                                                    <div class="skeleton h-4 w-32"></div>
                                                    <div class="skeleton h-3 w-24"></div>
                                                </div>
                                                <div class="skeleton h-4 w-24"></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Transaction Table -->
                                <template x-if="!loadingTransactions">
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead>
                                                <tr class="border-b border-[hsl(var(--border))]">
                                                    <th class="text-left py-3 px-2 text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ __('submerchant.date') }}</th>
                                                    <th class="text-left py-3 px-2 text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ __('submerchant.order_id') }}</th>
                                                    <th class="text-right py-3 px-2 text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ __('submerchant.gross') }}</th>
                                                    <th class="text-right py-3 px-2 text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ __('submerchant.fee') }}</th>
                                                    <th class="text-right py-3 px-2 text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ __('submerchant.net') }}</th>
                                                    <th class="text-center py-3 px-2 text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ __('submerchant.status') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="tx in transactions" :key="tx.order_id">
                                                    <tr class="border-b border-[hsl(var(--border))] hover:bg-[hsl(var(--muted)/0.3)]">
                                                        <td class="py-3 px-2 text-sm" x-text="formatDate(tx.settled_at || tx.created_at)"></td>
                                                        <td class="py-3 px-2 text-sm font-mono text-xs" x-text="tx.order_id"></td>
                                                        <td class="py-3 px-2 text-sm text-right" x-text="formatCurrency(tx.gross_amount || tx.amount)"></td>
                                                        <td class="py-3 px-2 text-sm text-right text-red-500" x-text="'-' + formatCurrency(tx.platform_fee || tx.fee_amount)"></td>
                                                        <td class="py-3 px-2 text-sm text-right font-medium text-emerald-600" x-text="formatCurrency(tx.net_amount)"></td>
                                                        <td class="py-3 px-2 text-center">
                                                            <span class="badge bg-emerald-100 text-emerald-700 text-xs">{{ __('submerchant.settled') }}</span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>

                                        <!-- Empty State -->
                                        <template x-if="transactions.length === 0">
                                            <div class="empty-state py-12">
                                                <div class="empty-state-icon"><i class="fas fa-receipt text-xl"></i></div>
                                                <p class="text-sm font-medium mt-2">{{ __('submerchant.no_transactions') }}</p>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ __('submerchant.settled_transactions_appear_here') }}</p>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</div>

<script>
function balanceApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/sub-merchant',
        loading: true,
        loadingTransactions: true,
        loadingDaily: true,
        isSubMerchant: false,
        subMerchant: {},
        balance: { available: 0, pending: 0, total_earned: 0, total_withdrawn: 0 },
        transactions: [],
        dailyEarnings: [],
        earningsSummary: { transaction_count: 0, gross_earnings: 0, total_fees: 0, net_earnings: 0 },
        selectedMonth: new Date().getMonth() + 1,
        months: [],
        chart: null,
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            this.initMonths();
            await this.checkStatus();
            if (this.isSubMerchant) {
                await Promise.all([
                    this.fetchBalance(),
                    this.fetchTransactions(),
                    this.fetchDailyEarnings()
                ]);
            }
        },

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

        initMonths() {
            const monthNames = [
                '{{ __("common.january") }}',
                '{{ __("common.february") }}',
                '{{ __("common.march") }}',
                '{{ __("common.april") }}',
                '{{ __("common.may") }}',
                '{{ __("common.june") }}',
                '{{ __("common.july") }}',
                '{{ __("common.august") }}',
                '{{ __("common.september") }}',
                '{{ __("common.october") }}',
                '{{ __("common.november") }}',
                '{{ __("common.december") }}'
            ];
            const currentMonth = new Date().getMonth();
            this.months = [];
            for (let i = 0; i <= currentMonth; i++) {
                this.months.push({ value: i + 1, label: monthNames[i] });
            }
            this.months.reverse();
        },

        async checkStatus() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/status`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.data.is_sub_merchant) {
                    this.isSubMerchant = true;
                    this.subMerchant = data.data.sub_merchant;
                }
            } catch (e) {
                console.error('Error:', e);
            } finally {
                this.loading = false;
            }
        },

        async fetchBalance() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/balance/breakdown`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.balance = {
                        available: data.data.balance.available.amount,
                        pending: data.data.balance.pending.amount,
                        total_earned: data.data.balance.total_earned.amount,
                        total_withdrawn: data.data.balance.total_withdrawn.amount
                    };
                }
            } catch (e) {
                console.error('Error:', e);
            }
        },

        async fetchTransactions() {
            this.loadingTransactions = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/balance/transactions?limit=50`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.transactions = data.data.transactions;
                }
            } catch (e) {
                console.error('Error:', e);
            } finally {
                this.loadingTransactions = false;
            }
        },

        async fetchDailyEarnings() {
            this.loadingDaily = true;
            try {
                const token = localStorage.getItem('token');
                const year = new Date().getFullYear();
                const res = await fetch(`${this.API_BASE_URL}/balance/daily-earnings?month=${this.selectedMonth}&year=${year}`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.dailyEarnings = data.data.daily;
                    this.earningsSummary = data.data.totals;
                    this.$nextTick(() => this.renderChart());
                }
            } catch (e) {
                console.error('Error:', e);
            } finally {
                this.loadingDaily = false;
            }
        },

        renderChart() {
            if (this.dailyEarnings.length === 0) return;

            const chartEl = document.getElementById('earningsChart');
            if (!chartEl) return;

            if (this.chart) {
                this.chart.destroy();
            }

            const options = {
                series: [{
                    name: 'Net Earnings',
                    data: this.dailyEarnings.map(d => d.net_earnings)
                }],
                chart: {
                    type: 'bar',
                    height: '100%',
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: { borderRadius: 4, columnWidth: '60%' }
                },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: this.dailyEarnings.map(d => {
                        const date = new Date(d.date);
                        return date.getDate();
                    }),
                    labels: { style: { fontSize: '10px' } }
                },
                yaxis: {
                    labels: {
                        formatter: (val) => this.formatCurrencyShort(val),
                        style: { fontSize: '10px' }
                    }
                },
                colors: ['#10b981'],
                tooltip: {
                    y: { formatter: (val) => this.formatCurrency(val) }
                }
            };

            this.chart = new ApexCharts(chartEl, options);
            this.chart.render();
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
        },

        formatCurrencyShort(amount) {
            if (amount >= 1000000) {
                return 'Rp ' + (amount / 1000000).toFixed(1) + 'M';
            } else if (amount >= 1000) {
                return 'Rp ' + (amount / 1000).toFixed(0) + 'K';
            }
            return 'Rp ' + amount;
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; }
    }
}
</script>
@endsection
