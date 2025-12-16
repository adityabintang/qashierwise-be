@extends('layouts.app')

@section('title', 'Transactions - QashierWise POS')

@section('content')
<div x-data="transactionsApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-transactions'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Transactions', 'description' => 'View transaction history'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Filters -->
                <div class="card p-3 sm:p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Search</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm"></i>
                                <input type="text" x-model="search" @input.debounce.300ms="fetchTransactions()" placeholder="Search order number..." class="input pl-10 w-full min-h-[44px]">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Start Date</label>
                            <input type="date" x-model="startDate" @change="fetchTransactions()" class="input w-full min-h-[44px]">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">End Date</label>
                            <input type="date" x-model="endDate" @change="fetchTransactions()" class="input w-full min-h-[44px]">
                        </div>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="block lg:hidden space-y-3">
                    <template x-if="loading">
                        <template x-for="i in 4" :key="'skeleton-'+i">
                            <div class="card p-4">
                                <div class="flex justify-between mb-2">
                                    <div class="skeleton h-4 w-24"></div>
                                    <div class="skeleton h-5 w-20"></div>
                                </div>
                                <div class="skeleton h-3 w-32 mb-1"></div>
                                <div class="skeleton h-3 w-24"></div>
                            </div>
                        </template>
                    </template>

                    <template x-if="!loading">
                        <template x-for="tx in transactions" :key="tx.id">
                            <div class="card p-4 hover:shadow-md transition-shadow" @click="viewTransaction(tx)">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-semibold" x-text="'#' + tx.order_number"></span>
                                    <span class="text-lg font-bold text-[hsl(var(--primary))]" x-text="formatCurrency(tx.total)"></span>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-[hsl(var(--muted-foreground))]">
                                    <span x-text="tx.store?.name || 'N/A'"></span>
                                    <span>•</span>
                                    <span x-text="formatDate(tx.created_at)"></span>
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="badge text-xs" :class="getStatusClass(tx.status)" x-text="tx.status"></span>
                                    <template x-if="tx.payments?.length > 0">
                                        <span class="badge badge-secondary text-xs" x-text="tx.payments[0].method"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden lg:block card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[hsl(var(--muted)/0.5)]">
                                <tr>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Order #</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Store</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Date</th>
                                    <th class="text-center p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Status</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Payment</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Total</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <template x-if="loading">
                                    <template x-for="i in 5" :key="'table-skeleton-'+i">
                                        <tr>
                                            <td class="p-4"><div class="skeleton h-4 w-20"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-28"></div></td>
                                            <td class="p-4"><div class="skeleton h-6 w-16 mx-auto rounded-full"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-16"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-20 ml-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-8 w-8 ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!loading">
                                    <template x-for="tx in transactions" :key="tx.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                            <td class="p-4 font-medium" x-text="'#' + tx.order_number"></td>
                                            <td class="p-4 text-sm" x-text="tx.store?.name || '-'"></td>
                                            <td class="p-4 text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(tx.created_at)"></td>
                                            <td class="p-4 text-center">
                                                <span class="badge text-xs" :class="getStatusClass(tx.status)" x-text="tx.status"></span>
                                            </td>
                                            <td class="p-4 text-sm" x-text="tx.payments?.length > 0 ? tx.payments[0].method : '-'"></td>
                                            <td class="p-4 text-right font-medium" x-text="formatCurrency(tx.total)"></td>
                                            <td class="p-4 text-right">
                                                <button @click="viewTransaction(tx)" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i></button>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && transactions.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-receipt text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No transactions found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Transactions will appear here after orders are completed.</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div x-show="!loading && pagination.lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-[hsl(var(--muted-foreground))]">
                        Showing <span x-text="pagination.from"></span> to <span x-text="pagination.to"></span> of <span x-text="pagination.total"></span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button @click="goToPage(pagination.currentPage - 1)" :disabled="pagination.currentPage === 1" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i></button>
                        <template x-for="page in paginationPages" :key="page">
                            <button @click="goToPage(page)" class="btn btn-sm" :class="page === pagination.currentPage ? 'btn-primary' : 'btn-outline'" x-text="page"></button>
                        </template>
                        <button @click="goToPage(pagination.currentPage + 1)" :disabled="pagination.currentPage === pagination.lastPage" class="btn btn-outline btn-sm"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Transaction Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showModal" x-transition class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div x-show="showModal" x-transition class="card relative w-full h-full sm:h-auto sm:max-w-lg sm:max-h-[90vh] overflow-hidden flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold">Transaction Details</h3>
                <button @click="closeModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6" x-show="selectedTx">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="text-xl font-bold" x-text="'#' + selectedTx?.order_number"></h4>
                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(selectedTx?.created_at)"></p>
                    </div>
                    <span class="badge" :class="getStatusClass(selectedTx?.status)" x-text="selectedTx?.status"></span>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="text-[hsl(var(--muted-foreground))]">Store:</span><p class="font-medium" x-text="selectedTx?.store?.name || '-'"></p></div>
                        <div><span class="text-[hsl(var(--muted-foreground))]">Table:</span><p class="font-medium" x-text="selectedTx?.table?.number ? 'Table ' + selectedTx.table.number : '-'"></p></div>
                    </div>
                    <div class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-3">Items</h5>
                        <div class="space-y-2">
                            <template x-for="item in selectedTx?.items" :key="item.id">
                                <div class="flex justify-between text-sm">
                                    <span><span x-text="item.quantity"></span>x <span x-text="item.product?.name || 'Product'"></span></span>
                                    <span x-text="formatCurrency(item.subtotal)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-2">
                        <div class="flex justify-between text-sm"><span>Subtotal</span><span x-text="formatCurrency(selectedTx?.subtotal)"></span></div>
                        <div class="flex justify-between text-sm"><span>Tax</span><span x-text="formatCurrency(selectedTx?.tax_amount)"></span></div>
                        <div class="flex justify-between text-sm"><span>Discount</span><span x-text="'-' + formatCurrency(selectedTx?.discount_amount || 0)"></span></div>
                        <div class="flex justify-between font-bold text-lg pt-2 border-t border-[hsl(var(--border))]"><span>Total</span><span x-text="formatCurrency(selectedTx?.total)"></span></div>
                    </div>
                    <template x-if="selectedTx?.payments?.length > 0">
                        <div class="border-t border-[hsl(var(--border))] pt-4">
                            <h5 class="font-medium mb-3">Payments</h5>
                            <div class="space-y-2">
                                <template x-for="payment in selectedTx?.payments" :key="payment.id">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="badge badge-secondary" x-text="payment.method"></span>
                                        <span class="font-medium" x-text="formatCurrency(payment.amount)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function transactionsApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true,
        transactions: [], showModal: false, selectedTx: null,
        search: '', startDate: '', endDate: '',
        pagination: { currentPage: 1, lastPage: 1, from: 0, to: 0, total: 0 },
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],

        async init() { this.initSidebar(); await this.fetchTransactions(); },

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

        async fetchTransactions() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const params = new URLSearchParams({ page: this.pagination.currentPage });
                if (this.search) params.append('search', this.search);
                if (this.startDate) params.append('start_date', this.startDate);
                if (this.endDate) params.append('end_date', this.endDate);
                const res = await fetch(`${this.API_BASE_URL}/transactions?${params}`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.transactions = data.data.data || data.data;
                    if (data.data.current_page) { this.pagination = { currentPage: data.data.current_page, lastPage: data.data.last_page, from: data.data.from || 0, to: data.data.to || 0, total: data.data.total }; }
                }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        get paginationPages() { const p = [], c = this.pagination.currentPage, l = this.pagination.lastPage; for (let i = Math.max(1, c - 2); i <= Math.min(l, c + 2); i++) p.push(i); return p; },
        goToPage(page) { if (page >= 1 && page <= this.pagination.lastPage) { this.pagination.currentPage = page; this.fetchTransactions(); } },
        formatCurrency(a) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(a || 0); },
        formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'; },
        getStatusClass(s) { return { 'pending': 'bg-amber-100 text-amber-700', 'completed': 'bg-blue-100 text-blue-700', 'paid': 'bg-emerald-100 text-emerald-700', 'cancelled': 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-700'; },

        viewTransaction(tx) { this.selectedTx = tx; this.showModal = true; },
        closeModal() { this.showModal = false; this.selectedTx = null; },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
