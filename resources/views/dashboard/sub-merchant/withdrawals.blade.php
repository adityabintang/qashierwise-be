@extends('layouts.app')

@section('title', 'Withdrawals - QashierWise')

@section('content')
<div x-data="withdrawalsApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant-withdrawals'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Withdrawals', 'description' => 'Request and track your withdrawals'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Not Registered State -->
                <template x-if="!loading && !isSubMerchant">
                    <div class="card p-8 text-center">
                        <div class="h-16 w-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-amber-500"></i>
                        </div>
                        <h2 class="text-xl font-bold mb-2">Not Registered</h2>
                        <p class="text-[hsl(var(--muted-foreground))] mb-6">You need to register as a sub-merchant to request withdrawals.</p>
                        <a href="/dashboard/sub-merchant/register" class="btn btn-primary">
                            <i class="fas fa-user-plus mr-2"></i>
                            Register Now
                        </a>
                    </div>
                </template>

                <!-- Main Content -->
                <template x-if="!loading && isSubMerchant">
                    <div class="space-y-6">
                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Available Balance</p>
                                        <p class="text-2xl font-bold mt-1 text-emerald-600" x-text="formatCurrency(balance.available)">Rp 0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                                        <i class="fas fa-wallet text-xl text-emerald-500"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Pending Withdrawals</p>
                                        <p class="text-2xl font-bold mt-1 text-amber-600" x-text="stats.pending_count">0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-amber-50 flex items-center justify-center">
                                        <i class="fas fa-clock text-xl text-amber-500"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Total Withdrawn</p>
                                        <p class="text-2xl font-bold mt-1" x-text="formatCurrency(stats.total_withdrawn)">Rp 0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-blue-50 flex items-center justify-center">
                                        <i class="fas fa-check-circle text-xl text-blue-500"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="card p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Total Requests</p>
                                        <p class="text-2xl font-bold mt-1" x-text="stats.total_requests">0</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-purple-50 flex items-center justify-center">
                                        <i class="fas fa-list text-xl text-purple-500"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Request Withdrawal Form -->
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title">Request Withdrawal</h2>
                                    <p class="card-description">Transfer funds to your bank account</p>
                                </div>
                                <div class="card-content space-y-4">
                                    <!-- Bank Account Info -->
                                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-2">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-lg bg-[hsl(var(--primary)/0.1)] flex items-center justify-center">
                                                <i class="fas fa-university text-[hsl(var(--primary))]"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium" x-text="subMerchant.bank_name"></p>
                                                <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="subMerchant.account_number"></p>
                                            </div>
                                        </div>
                                        <p class="text-sm" x-text="subMerchant.account_holder_name"></p>
                                    </div>

                                    <!-- Amount Input -->
                                    <div>
                                        <label class="text-sm font-medium mb-1.5 block">Amount (Rp)</label>
                                        <input 
                                            type="text" 
                                            x-model="withdrawForm.amount" 
                                            @input="formatWithdrawAmount()"
                                            class="input w-full min-h-[44px]" 
                                            placeholder="Enter amount"
                                        >
                                        <div class="flex justify-between mt-1">
                                            <p class="text-xs text-[hsl(var(--muted-foreground))]">Min: Rp 10,000</p>
                                            <button @click="setMaxAmount()" class="text-xs text-[hsl(var(--primary))] hover:underline">Use Max</button>
                                        </div>
                                    </div>

                                    <!-- Quick Amount Buttons -->
                                    <div class="grid grid-cols-3 gap-2">
                                        <button @click="setQuickAmount(50000)" class="btn btn-outline btn-sm">Rp 50K</button>
                                        <button @click="setQuickAmount(100000)" class="btn btn-outline btn-sm">Rp 100K</button>
                                        <button @click="setQuickAmount(500000)" class="btn btn-outline btn-sm">Rp 500K</button>
                                    </div>

                                    <!-- Error Message -->
                                    <template x-if="withdrawError">
                                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                            <div class="flex items-center gap-2 text-red-700">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <span class="text-sm" x-text="withdrawError"></span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Success Message -->
                                    <template x-if="withdrawSuccess">
                                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                                            <div class="flex items-center gap-2 text-emerald-700">
                                                <i class="fas fa-check-circle"></i>
                                                <span class="text-sm" x-text="withdrawSuccess"></span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Submit Button -->
                                    <button 
                                        @click="requestWithdrawal()" 
                                        :disabled="submitting || getNumericWithdrawAmount() < 10000 || getNumericWithdrawAmount() > balance.available" 
                                        class="btn btn-primary btn-md w-full"
                                    >
                                        <i class="fas" :class="submitting ? 'fa-spinner animate-spin' : 'fa-money-bill-transfer'"></i>
                                        <span x-text="submitting ? 'Processing...' : 'Request Withdrawal'"></span>
                                    </button>

                                    <p class="text-xs text-[hsl(var(--muted-foreground))] text-center">
                                        Withdrawals are processed within 1-3 business days
                                    </p>
                                </div>
                            </div>

                            <!-- Withdrawal History -->
                            <div class="lg:col-span-2 card">
                                <div class="card-header !flex-row items-center justify-between">
                                    <div>
                                        <h2 class="card-title">Withdrawal History</h2>
                                        <p class="card-description">Track your withdrawal requests</p>
                                    </div>
                                    <select x-model="historyFilter" @change="fetchHistory()" class="input w-auto text-sm">
                                        <option value="">All Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="processed">Processed</option>
                                    </select>
                                </div>
                                <div class="card-content">
                                    <!-- Loading -->
                                    <template x-if="loadingHistory">
                                        <div class="space-y-3">
                                            <template x-for="i in 5" :key="'skeleton-'+i">
                                                <div class="flex items-center gap-4 p-3 rounded-lg bg-[hsl(var(--muted)/0.5)]">
                                                    <div class="skeleton h-10 w-10 rounded-lg"></div>
                                                    <div class="flex-1 space-y-2">
                                                        <div class="skeleton h-4 w-32"></div>
                                                        <div class="skeleton h-3 w-24"></div>
                                                    </div>
                                                    <div class="skeleton h-6 w-20 rounded-full"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- History List -->
                                    <template x-if="!loadingHistory">
                                        <div class="space-y-3">
                                            <template x-for="w in withdrawals" :key="w.id">
                                                <div class="flex items-center gap-4 p-4 rounded-lg border border-[hsl(var(--border))] hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                                    <div class="h-10 w-10 rounded-lg flex items-center justify-center" :class="{
                                                        'bg-amber-100': w.status === 'pending',
                                                        'bg-blue-100': w.status === 'approved',
                                                        'bg-red-100': w.status === 'rejected',
                                                        'bg-emerald-100': w.status === 'processed'
                                                    }">
                                                        <i class="fas" :class="{
                                                            'fa-clock text-amber-500': w.status === 'pending',
                                                            'fa-check text-blue-500': w.status === 'approved',
                                                            'fa-times text-red-500': w.status === 'rejected',
                                                            'fa-check-double text-emerald-500': w.status === 'processed'
                                                        }"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-semibold" x-text="formatCurrency(w.amount)"></p>
                                                        <p class="text-sm text-[hsl(var(--muted-foreground))]">
                                                            <span x-text="w.bank_details.bank_name"></span> • 
                                                            <span x-text="w.bank_details.account_number"></span>
                                                        </p>
                                                        <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1" x-text="formatDate(w.created_at)"></p>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="badge text-xs" :class="{
                                                            'bg-amber-100 text-amber-700': w.status === 'pending',
                                                            'bg-blue-100 text-blue-700': w.status === 'approved',
                                                            'bg-red-100 text-red-700': w.status === 'rejected',
                                                            'bg-emerald-100 text-emerald-700': w.status === 'processed'
                                                        }" x-text="w.status"></span>
                                                        
                                                        <!-- Cancel Button for Pending -->
                                                        <template x-if="w.can_be_cancelled">
                                                            <button 
                                                                @click="cancelWithdrawal(w.id)" 
                                                                class="btn btn-ghost btn-sm text-red-500 hover:bg-red-50 mt-2"
                                                            >
                                                                <i class="fas fa-times"></i> Cancel
                                                            </button>
                                                        </template>

                                                        <!-- Admin Notes for Rejected -->
                                                        <template x-if="w.status === 'rejected' && w.admin_notes">
                                                            <p class="text-xs text-red-500 mt-1 max-w-[150px] truncate" :title="w.admin_notes" x-text="w.admin_notes"></p>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Empty State -->
                                            <template x-if="withdrawals.length === 0">
                                                <div class="empty-state py-12">
                                                    <div class="empty-state-icon"><i class="fas fa-money-bill-transfer text-xl"></i></div>
                                                    <p class="text-sm font-medium mt-2">No withdrawals yet</p>
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">Your withdrawal requests will appear here</p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>

    <!-- Password Confirmation Modal -->
    <div x-show="showPasswordModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showPasswordModal" x-transition class="fixed inset-0 bg-black/50" @click="showPasswordModal = false"></div>
        <div x-show="showPasswordModal" x-transition class="card relative w-full max-w-sm">
            <div class="p-4 border-b border-[hsl(var(--border))]">
                <h3 class="text-lg font-semibold">Confirm Password</h3>
                <p class="text-sm text-[hsl(var(--muted-foreground))]">Enter your password to continue</p>
            </div>
            <form @submit.prevent="confirmPassword()" class="p-4 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Password</label>
                    <input 
                        type="password" 
                        x-model="passwordForm.password" 
                        required 
                        class="input w-full min-h-[44px]" 
                        placeholder="Enter your password"
                    >
                </div>

                <template x-if="passwordError">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="flex items-center gap-2 text-red-700">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="text-sm" x-text="passwordError"></span>
                        </div>
                    </div>
                </template>

                <div class="flex gap-3">
                    <button type="button" @click="showPasswordModal = false" class="btn btn-outline btn-md flex-1">Cancel</button>
                    <button type="submit" :disabled="confirmingPassword" class="btn btn-primary btn-md flex-1">
                        <i class="fas" :class="confirmingPassword ? 'fa-spinner animate-spin' : 'fa-lock'"></i>
                        <span x-text="confirmingPassword ? 'Verifying...' : 'Confirm'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function withdrawalsApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/sub-merchant',
        loading: true,
        loadingHistory: true,
        submitting: false,
        confirmingPassword: false,
        isSubMerchant: false,
        subMerchant: {},
        balance: { available: 0 },
        stats: { pending_count: 0, total_withdrawn: 0, total_requests: 0 },
        withdrawals: [],
        historyFilter: '',
        withdrawForm: { amount: '' },
        withdrawError: '',
        withdrawSuccess: '',
        showPasswordModal: false,
        passwordForm: { password: '' },
        passwordError: '',
        passwordConfirmed: false,
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await this.checkStatus();
            if (this.isSubMerchant) {
                await Promise.all([
                    this.fetchBalance(),
                    this.fetchStats(),
                    this.fetchHistory(),
                    this.checkPasswordStatus()
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
                const res = await fetch(`${this.API_BASE_URL}/balance`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.balance = data.data.balance;
                }
            } catch (e) {
                console.error('Error:', e);
            }
        },

        async fetchStats() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/stats`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.stats = data.data.stats;
                }
            } catch (e) {
                console.error('Error:', e);
            }
        },

        async fetchHistory() {
            this.loadingHistory = true;
            try {
                const token = localStorage.getItem('token');
                let url = `${this.API_BASE_URL}/withdrawals/history?limit=20`;
                if (this.historyFilter) {
                    url += `&status=${this.historyFilter}`;
                }
                const res = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.withdrawals = data.data.withdrawals;
                }
            } catch (e) {
                console.error('Error:', e);
            } finally {
                this.loadingHistory = false;
            }
        },

        async checkPasswordStatus() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/password-status`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.passwordConfirmed = data.data.is_confirmed;
                }
            } catch (e) {
                console.error('Error:', e);
            }
        },

        async requestWithdrawal() {
            const amount = this.getNumericWithdrawAmount();
            
            if (amount < 10000) {
                this.withdrawError = 'Minimum withdrawal amount is Rp 10,000';
                return;
            }

            if (amount > this.balance.available) {
                this.withdrawError = 'Insufficient balance';
                return;
            }

            // Check if password confirmation is needed
            if (!this.passwordConfirmed) {
                this.showPasswordModal = true;
                return;
            }

            await this.submitWithdrawal();
        },

        async confirmPassword() {
            this.confirmingPassword = true;
            this.passwordError = '';

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/confirm-password`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.passwordForm)
                });
                const data = await res.json();

                if (data.success) {
                    this.passwordConfirmed = true;
                    this.showPasswordModal = false;
                    this.passwordForm.password = '';
                    await this.submitWithdrawal();
                } else {
                    this.passwordError = data.error?.message || 'Invalid password';
                }
            } catch (e) {
                console.error('Error:', e);
                this.passwordError = 'An error occurred';
            } finally {
                this.confirmingPassword = false;
            }
        },

        async submitWithdrawal() {
            this.submitting = true;
            this.withdrawError = '';
            this.withdrawSuccess = '';

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ amount: this.getNumericWithdrawAmount() })
                });
                const data = await res.json();

                if (data.success) {
                    this.withdrawSuccess = 'Withdrawal request submitted successfully!';
                    this.withdrawForm.amount = '';
                    await Promise.all([
                        this.fetchBalance(),
                        this.fetchStats(),
                        this.fetchHistory()
                    ]);
                } else {
                    this.withdrawError = data.error?.message || 'Failed to submit withdrawal request';
                }
            } catch (e) {
                console.error('Error:', e);
                this.withdrawError = 'An error occurred. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        async cancelWithdrawal(id) {
            if (!confirm('Are you sure you want to cancel this withdrawal request?')) return;

            // Check password confirmation
            if (!this.passwordConfirmed) {
                this.showPasswordModal = true;
                return;
            }

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/withdrawals/${id}/cancel`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (data.success) {
                    await Promise.all([
                        this.fetchBalance(),
                        this.fetchStats(),
                        this.fetchHistory()
                    ]);
                } else {
                    alert(data.error?.message || 'Failed to cancel withdrawal');
                }
            } catch (e) {
                console.error('Error:', e);
                alert('An error occurred');
            }
        },

        formatWithdrawAmount() {
            let value = this.withdrawForm.amount.replace(/[^0-9]/g, '');
            if (value) {
                this.withdrawForm.amount = new Intl.NumberFormat('id-ID').format(parseInt(value));
            }
        },

        getNumericWithdrawAmount() {
            return parseInt(this.withdrawForm.amount.replace(/[^0-9]/g, '') || '0');
        },

        setQuickAmount(amount) {
            this.withdrawForm.amount = new Intl.NumberFormat('id-ID').format(amount);
        },

        setMaxAmount() {
            this.withdrawForm.amount = new Intl.NumberFormat('id-ID').format(this.balance.available);
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; }
    }
}
</script>
@endsection
