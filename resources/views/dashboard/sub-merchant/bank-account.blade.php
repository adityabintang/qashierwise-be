@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Rekening Bank')

@section('content')
<div x-data="bankAccountApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant-bank-account'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => 'Rekening Bank', 'description' => 'Kelola rekening bank untuk withdrawal'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-2xl mx-auto space-y-6">
                <!-- Loading State -->
                <template x-if="loading">
                    <div class="card p-8 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-purple-500 mb-4"></i>
                        <p>Loading...</p>
                    </div>
                </template>

                <!-- Main Content -->
                <template x-if="!loading">
                    <div class="card p-8">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[hsl(var(--border))]">
                            <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-lg">
                                <i class="fas fa-university text-xl text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-[hsl(var(--foreground))]" x-text="hasBankAccount ? 'Rekening Bank Anda' : 'Tambah Rekening Bank'"></h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">Kelola informasi rekening untuk withdrawal</p>
                            </div>
                        </div>

                        <!-- Current Bank Account Display -->
                        <template x-if="hasBankAccount && !editing">
                            <div class="space-y-4">
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-6 border-2 border-blue-100 space-y-4">
                                    <div class="flex items-center gap-3 pb-3 border-b border-blue-200">
                                        <i class="fas fa-building text-blue-600 text-lg"></i>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Bank</p>
                                            <p class="font-bold text-lg text-blue-900" x-text="getBankName(form.bank_code)"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 pb-3 border-b border-blue-200">
                                        <i class="fas fa-credit-card text-blue-600 text-lg"></i>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Nomor Rekening</p>
                                            <p class="font-bold text-lg text-blue-900 tracking-wider" x-text="form.bank_account_number"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-user text-blue-600 text-lg"></i>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Nama Pemilik</p>
                                            <p class="font-bold text-lg text-blue-900" x-text="form.bank_account_name"></p>
                                        </div>
                                    </div>
                                </div>
                                <button @click="editing = true" class="flex items-center justify-center gap-3 w-full py-4 px-6 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
                                    <i class="fas fa-edit text-xl"></i>
                                    <span class="text-lg">Ubah Rekening</span>
                                </button>
                            </div>
                        </template>

                        <!-- Bank Account Form -->
                        <template x-if="!hasBankAccount || editing">
                            <form @submit.prevent="saveBankAccount" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Bank</label>
                                    <select x-model="form.bank_code" class="input w-full" required>
                                        <option value="">-- Pilih Bank --</option>
                                        <template x-for="bank in banks" :key="bank.code">
                                            <option :value="bank.code" x-text="bank.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Nomor Rekening</label>
                                    <input type="text" x-model="form.bank_account_number" class="input w-full"
                                        placeholder="Contoh: 1234567890" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Nama Pemilik Rekening</label>
                                    <input type="text" x-model="form.bank_account_name" class="input w-full"
                                        placeholder="Sesuai buku tabungan" required>
                                </div>

                                <!-- Error Message -->
                                <template x-if="errorMessage">
                                    <div class="p-3 rounded-lg bg-red-50 text-red-700 text-sm" x-text="errorMessage"></div>
                                </template>

                                <!-- Success Message -->
                                <template x-if="successMessage">
                                    <div class="p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm" x-text="successMessage"></div>
                                </template>

                                <div class="flex gap-4 mt-6">
                                    <template x-if="editing">
                                        <button type="button" @click="editing = false; resetForm()" class="flex items-center justify-center gap-2 flex-1 py-4 px-6 bg-white hover:bg-gray-50 text-gray-700 font-semibold rounded-xl border-2 border-gray-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200" :disabled="saving">
                                            <i class="fas fa-times text-lg"></i>
                                            <span class="text-lg">Batal</span>
                                        </button>
                                    </template>
                                    <button type="submit" class="flex items-center justify-center gap-3 py-4 px-6 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none" :class="editing ? 'flex-1' : 'w-full'" :disabled="saving">
                                        <template x-if="!saving">
                                            <i class="fas fa-save text-xl"></i>
                                        </template>
                                        <template x-if="saving">
                                            <i class="fas fa-spinner fa-spin text-xl"></i>
                                        </template>
                                        <span class="text-lg" x-text="saving ? 'Menyimpan...' : 'Simpan Rekening'"></span>
                                    </button>
                                </div>
                            </form>
                        </template>
                    </div>
                </template>
            </div>
        </main>
    </div>
</div>

<script>
function bankAccountApp() {
    return {
        loading: true,
        saving: false,
        editing: false,
        hasBankAccount: false,
        banks: [],
        form: {
            bank_code: '',
            bank_account_number: '',
            bank_account_name: ''
        },
        originalForm: {},
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

            await Promise.all([this.fetchBanks(), this.fetchBankAccount()]);
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

        async fetchBanks() {
            try {
                const res = await fetch('/api/sub-merchant/bank-account/banks', {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.banks = data.data?.banks || [];
                }
            } catch (e) { console.error('Failed to fetch banks', e); }
        },

        async fetchBankAccount() {
            try {
                const res = await fetch('/api/sub-merchant/bank-account', {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.data?.bank_account) {
                    this.form = { ...data.data.bank_account };
                    this.originalForm = { ...data.data.bank_account };
                    this.hasBankAccount = true;
                }
            } catch (e) { console.error('Failed to fetch bank account', e); }
        },

        async saveBankAccount() {
            this.errorMessage = '';
            this.successMessage = '';
            this.saving = true;

            try {
                const res = await fetch('/api/sub-merchant/bank-account', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();

                if (data.success) {
                    this.successMessage = 'Rekening bank berhasil disimpan!';
                    this.hasBankAccount = true;
                    this.editing = false;
                    this.originalForm = { ...this.form };
                } else {
                    this.errorMessage = data.message || 'Gagal menyimpan rekening bank.';
                }
            } catch (e) {
                this.errorMessage = 'Terjadi kesalahan. Silakan coba lagi.';
            }

            this.saving = false;
        },

        resetForm() {
            this.form = { ...this.originalForm };
            this.errorMessage = '';
            this.successMessage = '';
        },

        getBankName(code) {
            const bank = this.banks.find(b => b.code === code);
            return bank ? bank.name : code;
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
