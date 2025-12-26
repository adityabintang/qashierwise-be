@extends('layouts.app')

@section('title', 'Sub-Merchant Dashboard - QashierWise')

@section('content')
<div x-data="subMerchantDashboard()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Sub-Merchant', 'description' => 'Manage your QRIS payments and earnings'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Loading State -->
                <template x-if="loading">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="card p-6">
                                <div class="skeleton h-8 w-48 mb-4"></div>
                                <div class="skeleton h-4 w-full mb-2"></div>
                                <div class="skeleton h-4 w-3/4"></div>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <div class="card p-6">
                                <div class="skeleton h-6 w-32 mb-4"></div>
                                <div class="skeleton h-10 w-full"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Not Registered State -->
                <template x-if="!loading && !isSubMerchant">
                    <div class="card">
                        <div class="p-8 text-center">
                            <div class="h-20 w-20 rounded-full bg-[hsl(var(--primary)/0.1)] flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-qrcode text-4xl text-[hsl(var(--primary))]"></i>
                            </div>
                            <h2 class="text-2xl font-bold mb-2">Become a Sub-Merchant</h2>
                            <p class="text-[hsl(var(--muted-foreground))] mb-6 max-w-md mx-auto">
                                Start accepting QRIS payments from your customers. Generate dynamic QR codes and manage your earnings easily.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-2xl mx-auto mb-8">
                                <div class="p-4 rounded-lg bg-[hsl(var(--muted)/0.5)]">
                                    <i class="fas fa-qrcode text-2xl text-[hsl(var(--primary))] mb-2"></i>
                                    <h3 class="font-semibold">Generate QRIS</h3>
                                    <p class="text-sm text-[hsl(var(--muted-foreground))]">Create dynamic QR codes for payments</p>
                                </div>
                                <div class="p-4 rounded-lg bg-[hsl(var(--muted)/0.5)]">
                                    <i class="fas fa-wallet text-2xl text-emerald-500 mb-2"></i>
                                    <h3 class="font-semibold">Track Earnings</h3>
                                    <p class="text-sm text-[hsl(var(--muted-foreground))]">Monitor your balance in real-time</p>
                                </div>
                                <div class="p-4 rounded-lg bg-[hsl(var(--muted)/0.5)]">
                                    <i class="fas fa-cogs text-2xl text-purple-500 mb-2"></i>
                                    <h3 class="font-semibold">Provider Settings</h3>
                                    <p class="text-sm text-[hsl(var(--muted-foreground))]">Configure your payment providers</p>
                                </div>
                            </div>
                            <a href="/dashboard/sub-merchant/register" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus mr-2"></i>
                                Register as Sub-Merchant
                            </a>
                        </div>
                    </div>
                </template>

                <!-- Registered State - Dashboard -->
                <template x-if="!loading && isSubMerchant">
                    <div class="space-y-6">
                        <!-- Balance Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Available Balance</p>
                                        <p class="text-2xl font-bold mt-1" x-text="formatCurrency(balance.available)">Rp 0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                                        <i class="fas fa-wallet text-xl text-emerald-500"></i>
                                    </div>
                                </div>
                                <a href="/dashboard/sub-merchant/balance" class="text-sm text-[hsl(var(--primary))] hover:underline mt-3 inline-block">
                                    View details →
                                </a>
                            </div>

                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Pending Balance</p>
                                        <p class="text-2xl font-bold mt-1" x-text="formatCurrency(balance.pending)">Rp 0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-amber-50 flex items-center justify-center">
                                        <i class="fas fa-clock text-xl text-amber-500"></i>
                                    </div>
                                </div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))] mt-3">Being processed</p>
                            </div>

                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Total Earned</p>
                                        <p class="text-2xl font-bold mt-1" x-text="formatCurrency(balance.total_earned)">Rp 0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-blue-50 flex items-center justify-center">
                                        <i class="fas fa-chart-line text-xl text-blue-500"></i>
                                    </div>
                                </div>
                                <a href="/dashboard/sub-merchant/balance" class="text-sm text-[hsl(var(--primary))] hover:underline mt-3 inline-block">
                                    View details →
                                </a>
                            </div>

                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Total Transactions</p>
                                        <p class="text-2xl font-bold mt-1" x-text="balance.total_transactions || 0">0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-purple-50 flex items-center justify-center">
                                        <i class="fas fa-receipt text-xl text-purple-500"></i>
                                    </div>
                                </div>
                                <a href="/dashboard/sub-merchant/balance" class="text-sm text-[hsl(var(--primary))] hover:underline mt-3 inline-block">
                                    View transactions →
                                </a>
                            </div>
                        </div>

                        <!-- Quick Actions & Status -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Quick Actions -->
                            <div class="lg:col-span-2 card">
                                <div class="card-header">
                                    <h2 class="card-title">Quick Actions</h2>
                                    <p class="card-description">Common tasks for your sub-merchant account</p>
                                </div>
                                <div class="card-content">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <a href="/dashboard/sub-merchant/provider-settings" class="flex items-center gap-4 p-4 rounded-lg bg-purple-50 hover:bg-purple-100 transition-colors">
                                            <div class="h-12 w-12 rounded-xl bg-purple-500 flex items-center justify-center">
                                                <i class="fas fa-cogs text-xl text-white"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold">Provider Settings</h3>
                                                <p class="text-sm text-[hsl(var(--muted-foreground))]">Configure payment providers</p>
                                            </div>
                                        </a>

                                        <a href="/dashboard/sub-merchant/qris" class="flex items-center gap-4 p-4 rounded-lg bg-[hsl(var(--primary)/0.1)] hover:bg-[hsl(var(--primary)/0.15)] transition-colors">
                                            <div class="h-12 w-12 rounded-xl bg-[hsl(var(--primary))] flex items-center justify-center">
                                                <i class="fas fa-qrcode text-xl text-white"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold">Generate QRIS</h3>
                                                <p class="text-sm text-[hsl(var(--muted-foreground))]">Create new payment QR code</p>
                                            </div>
                                        </a>

                                        <a href="/dashboard/sub-merchant/balance" class="flex items-center gap-4 p-4 rounded-lg bg-emerald-50 hover:bg-emerald-100 transition-colors">
                                            <div class="h-12 w-12 rounded-xl bg-emerald-500 flex items-center justify-center">
                                                <i class="fas fa-chart-pie text-xl text-white"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold">View Balance</h3>
                                                <p class="text-sm text-[hsl(var(--muted-foreground))]">Check earnings & transactions</p>
                                            </div>
                                        </a>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</div>

<script>
function subMerchantDashboard() {
    return {
        API_BASE_URL: window.location.origin + '/api/sub-merchant',
        loading: true,
        isSubMerchant: false,
        subMerchant: {},
        balance: { available: 0, pending: 0, total_earned: 0, total_transactions: 0 },
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await this.fetchStatus();
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

        async fetchStatus() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/status`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.isSubMerchant = data.data.is_sub_merchant;
                    if (this.isSubMerchant) {
                        this.subMerchant = data.data.sub_merchant;
                        this.balance = data.data.balance || { available: 0, pending: 0, total_earned: 0, total_transactions: 0 };
                    }
                }
            } catch (e) {
                console.error('Error fetching status:', e);
            } finally {
                this.loading = false;
            }
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; }
    }
}
</script>
@endsection
