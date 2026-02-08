@extends('layouts.app')

@section('title', 'Withdrawal')

@section('content')
<div x-data="withdrawalApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant-withdrawals'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => 'Withdrawal', 'description' => 'Tarik saldo ke rekening bank Anda'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-4xl mx-auto space-y-6">
                <!-- Loading State -->
                <template x-if="loading">
                    <div class="card p-8 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-purple-500 mb-4"></i>
                        <p>Loading...</p>
                    </div>
                </template>

                <!-- Main Content -->
                <template x-if="!loading">
                    <div class="space-y-6">
                        <!-- Balance Card -->
                        <div class="card p-8 bg-gradient-to-br from-emerald-50 to-teal-50 border-l-4 border-emerald-500 shadow-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-emerald-700 uppercase tracking-wide mb-2">Saldo Tersedia</p>
                                    <p class="text-4xl font-bold text-emerald-600" x-text="formatCurrency(availableBalance)"></p>
                                    <p class="text-xs text-emerald-600 mt-2">Siap untuk ditarik ke rekening bank</p>
                                </div>
                                <div class="h-20 w-20 rounded-3xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-xl">
                                    <i class="fas fa-wallet text-3xl text-white"></i>
                                </div>
                            </div>
                        </div>

                        <!-- No Bank Account Warning -->
                        <template x-if="!hasBankAccount">
                            <div class="card p-8 border-2 border-amber-300 bg-gradient-to-br from-amber-50 to-orange-50 shadow-lg">
                                <div class="flex items-start gap-4">
                                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-lg flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-bold text-lg text-amber-900 mb-2">Rekening Bank Belum Dikonfigurasi</p>
                                        <p class="text-sm text-amber-700 mb-4">Silakan tambahkan rekening bank terlebih dahulu sebelum melakukan withdrawal.</p>
                                        <a href="/dashboard/sub-merchant/bank-account" class="inline-flex items-center justify-center gap-3 px-6 py-3 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
                                            <i class="fas fa-university text-lg"></i>
                                            <span>Tambah Rekening Bank</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Withdrawal Form -->
                        <template x-if="hasBankAccount">
                            <div class="card p-8">
                                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[hsl(var(--border))]">
                                    <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center shadow-lg">
                                        <i class="fas fa-paper-plane text-xl text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-[hsl(var(--foreground))]">Request Withdrawal</h3>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]">Tarik saldo ke rekening bank Anda</p>
                                    </div>
                                </div>

                                <!-- Bank Account Info -->
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-6 border-2 border-blue-100 mb-6">
                                    <div class="flex items-center gap-3 mb-3">
                                        <i class="fas fa-university text-blue-600 text-lg"></i>
                                        <p class="text-sm font-semibold text-blue-700 uppercase tracking-wide">Rekening Tujuan</p>
                                    </div>
                                    <p class="font-bold text-lg text-blue-900 mb-1" x-text="bankAccount.bank_code"></p>
                                    <p class="text-blue-800 font-medium tracking-wide" x-text="bankAccount.bank_account_number + ' - ' + bankAccount.bank_account_name"></p>
                                </div>

                                <form @submit.prevent="submitWithdrawal" class="space-y-5">
                                    <div>
                                        <label class="block text-sm font-bold text-[hsl(var(--foreground))] mb-3">
                                            <i class="fas fa-coins mr-2 text-purple-600"></i>Jumlah Withdrawal (Rp)
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-bold text-gray-400">Rp</span>
                                            <input type="number" x-model="withdrawalAmount" min="10000" :max="availableBalance"
                                                class="input w-full pl-16 pr-4 py-4 text-xl font-bold border-2 focus:border-purple-500 rounded-xl" placeholder="10.000">
                                        </div>
                                        <div class="flex items-center gap-2 mt-2 px-3 py-2 bg-purple-50 rounded-lg">
                                            <i class="fas fa-info-circle text-purple-600 text-sm"></i>
                                            <p class="text-xs text-purple-700 font-medium">Minimum withdrawal: Rp 10.000</p>
                                        </div>
                                    </div>

                                    <!-- Error Message -->
                                    <template x-if="errorMessage">
                                        <div class="p-3 rounded-lg bg-red-50 text-red-700 text-sm" x-text="errorMessage"></div>
                                    </template>

                                    <!-- Success Message -->
                                    <template x-if="successMessage">
                                        <div class="p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm" x-text="successMessage"></div>
                                    </template>

                                    <button type="submit" class="flex items-center justify-center gap-3 w-full py-5 px-6 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-bold text-lg rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none" :disabled="submitting || !withdrawalAmount || withdrawalAmount < 10000 || withdrawalAmount > availableBalance">
                                        <template x-if="!submitting">
                                            <i class="fas fa-paper-plane text-2xl"></i>
                                        </template>
                                        <template x-if="submitting">
                                            <i class="fas fa-spinner fa-spin text-2xl"></i>
                                        </template>
                                        <span x-text="submitting ? 'Processing...' : 'Request Withdrawal'"></span>
                                    </button>
                                </form>
                            </div>
                        </template>

                        <!-- Withdrawal History -->
                        <div class="card p-8">
                            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[hsl(var(--border))]">
                                <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-history text-xl text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-[hsl(var(--foreground))]">Riwayat Withdrawal</h3>
                                    <p class="text-sm text-[hsl(var(--muted-foreground))]">Daftar permintaan penarikan saldo</p>
                                </div>
                            </div>

                            <template x-if="withdrawals.length === 0">
                                <div class="text-center py-12 bg-gradient-to-br from-gray-50 to-slate-50 rounded-2xl border-2 border-dashed border-gray-300">
                                    <div class="h-20 w-20 rounded-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <i class="fas fa-receipt text-3xl text-white"></i>
                                    </div>
                                    <p class="text-lg font-semibold text-gray-600">Belum ada riwayat withdrawal</p>
                                    <p class="text-sm text-gray-500 mt-2">Permintaan withdrawal Anda akan muncul di sini</p>
                                </div>
                            </template>

                            <template x-if="withdrawals.length > 0">
                                <div class="space-y-4">
                                    <template x-for="w in withdrawals" :key="w.id">
                                        <div class="group flex items-center justify-between p-6 rounded-2xl bg-gradient-to-br from-white to-gray-50 border-2 border-gray-200 hover:border-indigo-300 hover:shadow-lg transition-all duration-200">
                                            <div class="flex items-center gap-4 flex-1">
                                                <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center shadow-md group-hover:shadow-lg transition-shadow">
                                                    <i class="fas fa-money-bill-wave text-2xl text-indigo-600"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="font-bold text-xl text-gray-900" x-text="formatCurrency(w.amount)"></p>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <i class="fas fa-university text-sm text-gray-500"></i>
                                                        <p class="text-sm font-medium text-gray-600" x-text="w.bank_code + ' - ' + w.bank_account_number"></p>
                                                    </div>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <i class="fas fa-calendar text-sm text-gray-400"></i>
                                                        <p class="text-xs text-gray-500" x-text="formatDate(w.created_at)"></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="px-4 py-2 rounded-xl text-sm font-bold shadow-md"
                                                    :class="{
                                                        'bg-gradient-to-r from-amber-400 to-orange-500 text-white': w.status === 'pending',
                                                        'bg-gradient-to-r from-blue-400 to-cyan-500 text-white': w.status === 'processing',
                                                        'bg-gradient-to-r from-emerald-400 to-teal-500 text-white': w.status === 'completed',
                                                        'bg-gradient-to-r from-red-400 to-rose-500 text-white': w.status === 'failed'
                                                    }"
                                                    x-text="w.status.charAt(0).toUpperCase() + w.status.slice(1)">
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</div>

<script>
function withdrawalApp() {
    return {
        loading: true,
        submitting: false,
        availableBalance: 0,
        hasBankAccount: false,
        bankAccount: {},
        withdrawalAmount: '',
        withdrawals: [],
        errorMessage: '',
        successMessage: '',
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            const token = localStorage.getItem('token');
            if (!token) { window.location.href = '/login'; return; }

            await Promise.all([this.fetchBalance(), this.fetchBankAccount(), this.fetchWithdrawals()]);
            this.loading = false;
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

        async fetchBalance() {
            try {
                const res = await fetch('/api/sub-merchant/balance', {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.availableBalance = data.data?.balance?.available || 0;
                }
            } catch (e) { console.error('Failed to fetch balance', e); }
        },

        async fetchBankAccount() {
            try {
                const res = await fetch('/api/sub-merchant/bank-account', {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.data?.bank_account) {
                    this.bankAccount = data.data.bank_account;
                    this.hasBankAccount = true;
                }
            } catch (e) { console.error('Failed to fetch bank account', e); }
        },

        async fetchWithdrawals() {
            try {
                const res = await fetch('/api/sub-merchant/withdrawals', {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.withdrawals = data.data?.withdrawals || [];
                }
            } catch (e) { console.error('Failed to fetch withdrawals', e); }
        },

        async submitWithdrawal() {
            this.errorMessage = '';
            this.successMessage = '';
            this.submitting = true;

            try {
                const res = await fetch('/api/sub-merchant/withdrawals', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ amount: parseFloat(this.withdrawalAmount) })
                });
                const data = await res.json();

                if (data.success) {
                    this.successMessage = 'Withdrawal request berhasil disubmit!';
                    this.withdrawalAmount = '';
                    await Promise.all([this.fetchBalance(), this.fetchWithdrawals()]);
                } else {
                    this.errorMessage = data.message || 'Gagal melakukan withdrawal.';
                }
            } catch (e) {
                this.errorMessage = 'Terjadi kesalahan. Silakan coba lagi.';
            }

            this.submitting = false;
        },

        formatCurrency(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount || 0);
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        logout() {
            const token = localStorage.getItem('token');
            if (token) {
                fetch('/api/logout', { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } })
                    .finally(() => this.clearAndRedirect());
            } else { this.clearAndRedirect(); }
        },

        clearAndRedirect() {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            localStorage.removeItem('sidebarOpen');
            localStorage.removeItem('notifications');
            window.location.href = '/login';
        }
    };
}
</script>
@endsection
