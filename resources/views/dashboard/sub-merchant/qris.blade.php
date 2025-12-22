@extends('layouts.app')

@section('title', 'Generate QRIS - QashierWise')

@section('content')
<div x-data="qrisApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant-qris'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Generate QRIS', 'description' => 'Create dynamic QR codes for payments'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Not Registered State -->
                <template x-if="!loading && !isSubMerchant">
                    <div class="card p-8 text-center">
                        <div class="h-16 w-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-amber-500"></i>
                        </div>
                        <h2 class="text-xl font-bold mb-2">Not Registered</h2>
                        <p class="text-[hsl(var(--muted-foreground))] mb-6">You need to register as a sub-merchant to generate QRIS codes.</p>
                        <a href="/dashboard/sub-merchant/register" class="btn btn-primary">
                            <i class="fas fa-user-plus mr-2"></i>
                            Register Now
                        </a>
                    </div>
                </template>

                <!-- Main Content -->
                <template x-if="!loading && isSubMerchant">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Generate QRIS Form -->
                        <div class="lg:col-span-1">
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title">Generate New QRIS</h2>
                                    <p class="card-description">Create a payment QR code</p>
                                </div>
                                <form @submit.prevent="generateQris()" class="card-content space-y-4">
                                    <!-- Amount -->
                                    <div>
                                        <label class="text-sm font-medium mb-1.5 block">Amount (Rp) <span class="text-red-500">*</span></label>
                                        <input 
                                            type="text" 
                                            x-model="form.amount" 
                                            @input="formatAmount()"
                                            required 
                                            class="input w-full min-h-[44px]" 
                                            placeholder="Enter amount"
                                        >
                                        <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Min: Rp 1,000 - Max: Rp 100,000,000</p>
                                    </div>

                                    <!-- Description -->
                                    <div>
                                        <label class="text-sm font-medium mb-1.5 block">Description</label>
                                        <input 
                                            type="text" 
                                            x-model="form.description" 
                                            class="input w-full min-h-[44px]" 
                                            placeholder="e.g., Payment for order #123"
                                            maxlength="255"
                                        >
                                    </div>

                                    <!-- Customer Name -->
                                    <div>
                                        <label class="text-sm font-medium mb-1.5 block">Customer Name</label>
                                        <input 
                                            type="text" 
                                            x-model="form.customer_name" 
                                            class="input w-full min-h-[44px]" 
                                            placeholder="Optional"
                                            maxlength="100"
                                        >
                                    </div>

                                    <!-- Fee Preview -->
                                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-[hsl(var(--muted-foreground))]">Amount</span>
                                            <span class="font-medium" x-text="formatCurrency(getNumericAmount())">Rp 0</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-[hsl(var(--muted-foreground))]">Platform Fee (2.5%)</span>
                                            <span class="font-medium text-red-500" x-text="'-' + formatCurrency(calculateFee())">-Rp 0</span>
                                        </div>
                                        <div class="flex justify-between text-sm pt-2 border-t border-[hsl(var(--border))]">
                                            <span class="font-medium">You Receive</span>
                                            <span class="font-bold text-emerald-600" x-text="formatCurrency(getNumericAmount() - calculateFee())">Rp 0</span>
                                        </div>
                                    </div>

                                    <!-- Error Message -->
                                    <template x-if="errorMessage">
                                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                            <div class="flex items-center gap-2 text-red-700">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <span class="text-sm" x-text="errorMessage"></span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Submit Button -->
                                    <button 
                                        type="submit" 
                                        :disabled="generating || !subMerchant.is_active" 
                                        class="btn btn-primary btn-md w-full"
                                    >
                                        <i class="fas" :class="generating ? 'fa-spinner animate-spin' : 'fa-qrcode'"></i>
                                        <span x-text="generating ? 'Generating...' : 'Generate QRIS'"></span>
                                    </button>

                                    <template x-if="!subMerchant.is_active">
                                        <p class="text-sm text-amber-600 text-center">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Your account is inactive
                                        </p>
                                    </template>
                                </form>
                            </div>
                        </div>

                        <!-- Generated QRIS Display & History -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Generated QRIS -->
                            <template x-if="generatedQris">
                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="card-title">Generated QRIS</h2>
                                        <p class="card-description">Share this QR code with your customer</p>
                                    </div>
                                    <div class="card-content">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <!-- QR Code -->
                                            <div class="text-center">
                                                <div class="bg-white p-4 rounded-lg inline-block shadow-sm border">
                                                    <img :src="generatedQris.qr_code_url" alt="QRIS Code" class="w-48 h-48 mx-auto">
                                                </div>
                                                <p class="text-2xl font-bold mt-4" x-text="formatCurrency(generatedQris.amount)"></p>
                                                <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="generatedQris.order_id"></p>
                                                
                                                <!-- Expiry Timer -->
                                                <div class="mt-3" x-show="!generatedQris.is_expired">
                                                    <span class="badge bg-amber-100 text-amber-700">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Expires in <span x-text="formatRemainingTime(generatedQris.remaining_time_seconds)"></span>
                                                    </span>
                                                </div>
                                                <div class="mt-3" x-show="generatedQris.is_expired">
                                                    <span class="badge bg-red-100 text-red-700">
                                                        <i class="fas fa-times-circle mr-1"></i>
                                                        Expired
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Share Options -->
                                            <div class="space-y-4">
                                                <h3 class="font-semibold">Share Options</h3>
                                                
                                                <!-- Copy Link -->
                                                <div class="flex gap-2">
                                                    <input 
                                                        type="text" 
                                                        :value="generatedQris.shareable_link" 
                                                        readonly 
                                                        class="input flex-1 text-sm"
                                                    >
                                                    <button @click="copyLink()" class="btn btn-outline btn-icon">
                                                        <i class="fas" :class="copied ? 'fa-check text-emerald-500' : 'fa-copy'"></i>
                                                    </button>
                                                </div>

                                                <!-- Share Buttons -->
                                                <div class="grid grid-cols-2 gap-2">
                                                    <button @click="shareWhatsApp()" class="btn btn-outline btn-sm">
                                                        <i class="fab fa-whatsapp text-green-500"></i>
                                                        WhatsApp
                                                    </button>
                                                    <button @click="downloadQr()" class="btn btn-outline btn-sm">
                                                        <i class="fas fa-download"></i>
                                                        Download
                                                    </button>
                                                </div>

                                                <!-- Transaction Details -->
                                                <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-2 text-sm">
                                                    <div class="flex justify-between">
                                                        <span class="text-[hsl(var(--muted-foreground))]">Status</span>
                                                        <span class="badge" :class="{
                                                            'bg-amber-100 text-amber-700': generatedQris.status === 'pending',
                                                            'bg-emerald-100 text-emerald-700': generatedQris.status === 'settlement',
                                                            'bg-red-100 text-red-700': generatedQris.status === 'expire' || generatedQris.status === 'cancel'
                                                        }" x-text="generatedQris.status"></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-[hsl(var(--muted-foreground))]">Platform Fee</span>
                                                        <span x-text="formatCurrency(generatedQris.platform_fee)"></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-[hsl(var(--muted-foreground))]">You Receive</span>
                                                        <span class="font-medium text-emerald-600" x-text="formatCurrency(generatedQris.net_amount)"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- QRIS History -->
                            <div class="card">
                                <div class="card-header !flex-row items-center justify-between">
                                    <div>
                                        <h2 class="card-title">QRIS History</h2>
                                        <p class="card-description">Recent transactions</p>
                                    </div>
                                    <select x-model="historyFilter" @change="fetchHistory()" class="input w-auto text-sm">
                                        <option value="">All Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="settlement">Settled</option>
                                        <option value="expire">Expired</option>
                                        <option value="cancel">Cancelled</option>
                                    </select>
                                </div>
                                <div class="card-content">
                                    <!-- Loading -->
                                    <template x-if="loadingHistory">
                                        <div class="space-y-3">
                                            <template x-for="i in 3" :key="'skeleton-'+i">
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
                                        <div class="space-y-2">
                                            <template x-for="tx in history" :key="tx.order_id">
                                                <div @click="viewTransaction(tx)" class="flex items-center gap-4 p-3 rounded-lg hover:bg-[hsl(var(--muted)/0.5)] transition-colors cursor-pointer">
                                                    <div class="h-10 w-10 rounded-lg flex items-center justify-center" :class="{
                                                        'bg-amber-100': tx.status === 'pending',
                                                        'bg-emerald-100': tx.status === 'settlement',
                                                        'bg-red-100': tx.status === 'expire' || tx.status === 'cancel'
                                                    }">
                                                        <i class="fas" :class="{
                                                            'fa-clock text-amber-500': tx.status === 'pending',
                                                            'fa-check text-emerald-500': tx.status === 'settlement',
                                                            'fa-times text-red-500': tx.status === 'expire' || tx.status === 'cancel'
                                                        }"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-medium" x-text="formatCurrency(tx.amount)"></p>
                                                        <p class="text-sm text-[hsl(var(--muted-foreground))] truncate" x-text="tx.order_id"></p>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="badge text-xs" :class="{
                                                            'bg-amber-100 text-amber-700': tx.status === 'pending',
                                                            'bg-emerald-100 text-emerald-700': tx.status === 'settlement',
                                                            'bg-red-100 text-red-700': tx.status === 'expire' || tx.status === 'cancel'
                                                        }" x-text="tx.status"></span>
                                                        <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1" x-text="formatDate(tx.created_at)"></p>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Empty State -->
                                            <template x-if="history.length === 0">
                                                <div class="empty-state py-8">
                                                    <div class="empty-state-icon"><i class="fas fa-qrcode text-xl"></i></div>
                                                    <p class="text-sm font-medium mt-2">No transactions yet</p>
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">Generate your first QRIS to get started</p>
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

    <!-- Transaction Detail Modal -->
    <div x-show="showDetailModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDetailModal" x-transition class="fixed inset-0 bg-black/50" @click="showDetailModal = false"></div>
        <div x-show="showDetailModal" x-transition class="card relative w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                <h3 class="text-lg font-semibold">Transaction Details</h3>
                <button @click="showDetailModal = false" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4 overflow-y-auto space-y-4" x-show="selectedTransaction">
                <!-- QR Code -->
                <div class="text-center" x-show="selectedTransaction.can_be_used">
                    <div class="bg-white p-3 rounded-lg inline-block shadow-sm border">
                        <img :src="selectedTransaction.qr_code_url" alt="QRIS Code" class="w-32 h-32 mx-auto">
                    </div>
                </div>

                <!-- Details -->
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-[hsl(var(--border))]">
                        <span class="text-sm text-[hsl(var(--muted-foreground))]">Order ID</span>
                        <span class="text-sm font-medium" x-text="selectedTransaction.order_id"></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[hsl(var(--border))]">
                        <span class="text-sm text-[hsl(var(--muted-foreground))]">Amount</span>
                        <span class="text-sm font-medium" x-text="formatCurrency(selectedTransaction.amount)"></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[hsl(var(--border))]">
                        <span class="text-sm text-[hsl(var(--muted-foreground))]">Platform Fee</span>
                        <span class="text-sm font-medium text-red-500" x-text="'-' + formatCurrency(selectedTransaction.platform_fee)"></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[hsl(var(--border))]">
                        <span class="text-sm text-[hsl(var(--muted-foreground))]">Net Amount</span>
                        <span class="text-sm font-bold text-emerald-600" x-text="formatCurrency(selectedTransaction.net_amount)"></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[hsl(var(--border))]">
                        <span class="text-sm text-[hsl(var(--muted-foreground))]">Status</span>
                        <span class="badge" :class="{
                            'bg-amber-100 text-amber-700': selectedTransaction.status === 'pending',
                            'bg-emerald-100 text-emerald-700': selectedTransaction.status === 'settlement',
                            'bg-red-100 text-red-700': selectedTransaction.status === 'expire' || selectedTransaction.status === 'cancel'
                        }" x-text="selectedTransaction.status"></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-sm text-[hsl(var(--muted-foreground))]">Created</span>
                        <span class="text-sm font-medium" x-text="formatDateTime(selectedTransaction.created_at)"></span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-4" x-show="selectedTransaction.can_be_used">
                    <button @click="copyLinkFromModal()" class="btn btn-outline btn-sm flex-1">
                        <i class="fas fa-copy"></i> Copy Link
                    </button>
                    <button @click="cancelTransaction()" :disabled="cancelling" class="btn btn-outline btn-sm text-red-600 hover:bg-red-50">
                        <i class="fas" :class="cancelling ? 'fa-spinner animate-spin' : 'fa-times'"></i>
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function qrisApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/sub-merchant',
        loading: true,
        generating: false,
        loadingHistory: true,
        cancelling: false,
        isSubMerchant: false,
        subMerchant: {},
        form: { amount: '', description: '', customer_name: '' },
        generatedQris: null,
        history: [],
        historyFilter: '',
        errorMessage: '',
        copied: false,
        showDetailModal: false,
        selectedTransaction: null,
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await this.checkStatus();
            if (this.isSubMerchant) {
                await this.fetchHistory();
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

        async generateQris() {
            this.generating = true;
            this.errorMessage = '';

            const amount = this.getNumericAmount();
            if (amount < 1000 || amount > 100000000) {
                this.errorMessage = 'Amount must be between Rp 1,000 and Rp 100,000,000';
                this.generating = false;
                return;
            }

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/qris/generate`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        amount: amount,
                        description: this.form.description,
                        customer_name: this.form.customer_name
                    })
                });
                const data = await res.json();

                if (data.success) {
                    this.generatedQris = data.data.transaction;
                    this.form = { amount: '', description: '', customer_name: '' };
                    await this.fetchHistory();
                } else {
                    this.errorMessage = data.error?.message || 'Failed to generate QRIS';
                }
            } catch (e) {
                console.error('Error:', e);
                this.errorMessage = 'An error occurred. Please try again.';
            } finally {
                this.generating = false;
            }
        },

        async fetchHistory() {
            this.loadingHistory = true;
            try {
                const token = localStorage.getItem('token');
                let url = `${this.API_BASE_URL}/qris/history?limit=20`;
                if (this.historyFilter) {
                    url += `&status=${this.historyFilter}`;
                }
                const res = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.history = data.data.transactions;
                }
            } catch (e) {
                console.error('Error:', e);
            } finally {
                this.loadingHistory = false;
            }
        },

        viewTransaction(tx) {
            this.selectedTransaction = tx;
            this.showDetailModal = true;
        },

        async cancelTransaction() {
            if (!confirm('Are you sure you want to cancel this QRIS?')) return;
            
            this.cancelling = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/qris/${this.selectedTransaction.order_id}/cancel`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.showDetailModal = false;
                    await this.fetchHistory();
                    if (this.generatedQris?.order_id === this.selectedTransaction.order_id) {
                        this.generatedQris = null;
                    }
                } else {
                    alert(data.error?.message || 'Failed to cancel');
                }
            } catch (e) {
                console.error('Error:', e);
                alert('An error occurred');
            } finally {
                this.cancelling = false;
            }
        },

        formatAmount() {
            let value = this.form.amount.replace(/[^0-9]/g, '');
            if (value) {
                this.form.amount = new Intl.NumberFormat('id-ID').format(parseInt(value));
            }
        },

        getNumericAmount() {
            return parseInt(this.form.amount.replace(/[^0-9]/g, '') || '0');
        },

        calculateFee() {
            return Math.round(this.getNumericAmount() * 0.025);
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount || 0);
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        formatDateTime(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleString('id-ID');
        },

        formatRemainingTime(seconds) {
            if (!seconds || seconds <= 0) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },

        copyLink() {
            navigator.clipboard.writeText(this.generatedQris.shareable_link);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },

        copyLinkFromModal() {
            navigator.clipboard.writeText(this.selectedTransaction.shareable_link);
            alert('Link copied!');
        },

        shareWhatsApp() {
            const text = `Please pay ${this.formatCurrency(this.generatedQris.amount)} using this QRIS link: ${this.generatedQris.shareable_link}`;
            window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
        },

        downloadQr() {
            const link = document.createElement('a');
            link.href = this.generatedQris.qr_code_url;
            link.download = `qris-${this.generatedQris.order_id}.png`;
            link.click();
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; }
    }
}
</script>
@endsection
