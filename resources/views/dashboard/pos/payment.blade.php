@extends('layouts.app')

@section('title', __('pos.menu.payment') . ' - QashierWise POS')

@section('content')
<div x-data="paymentApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-payment'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('pos.menu.payment'), 'description' => __('dashboard.menu_payment')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Pending Orders List -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold">Pending Orders</h2>
                            <button @click="fetchOrders()" class="btn btn-outline btn-sm">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>

                        <!-- Loading -->
                        <template x-if="loading">
                            <div class="space-y-3">
                                <template x-for="i in 3" :key="'skeleton-'+i">
                                    <div class="card p-4">
                                        <div class="flex justify-between mb-3">
                                            <div class="skeleton h-5 w-24"></div>
                                            <div class="skeleton h-5 w-20"></div>
                                        </div>
                                        <div class="skeleton h-4 w-32"></div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Orders -->
                        <template x-if="!loading">
                            <div class="space-y-3">
                                <template x-for="order in pendingOrders" :key="order.id">
                                    <div class="card p-4 cursor-pointer hover:shadow-md transition-shadow"
                                         :class="selectedOrder?.id === order.id ? 'ring-2 ring-[hsl(var(--primary))]' : ''"
                                         @click="selectOrder(order)">
                                        <div class="flex justify-between items-start mb-2">
                                            <span class="font-semibold" x-text="'#' + order.order_number"></span>
                                            <span class="text-lg font-bold text-[hsl(var(--primary))]" x-text="formatCurrency(order.total)"></span>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm text-[hsl(var(--muted-foreground))]">
                                            <span x-text="order.store?.name || 'N/A'"></span>
                                            <span x-text="order.table?.number ? 'Table ' + order.table.number : ''"></span>
                                            <span x-text="(order.items?.length || 0) + ' items'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Empty State -->
                        <div x-show="!loading && pendingOrders.length === 0" class="card">
                            <div class="empty-state py-12">
                                <div class="empty-state-icon"><i class="fas fa-check-circle text-2xl"></i></div>
                                <h3 class="font-semibold mt-4">No pending orders</h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">All orders have been paid.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Panel -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold">Payment</h2>

                        <template x-if="!selectedOrder">
                            <div class="card p-8 text-center">
                                <div class="text-[hsl(var(--muted-foreground))]">
                                    <i class="fas fa-hand-pointer text-3xl mb-3"></i>
                                    <p>Select an order to process payment</p>
                                </div>
                            </div>
                        </template>

                        <template x-if="selectedOrder">
                            <div class="card p-4 space-y-4">
                                <!-- Order Summary -->
                                <div class="border-b border-[hsl(var(--border))] pb-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-semibold" x-text="'Order #' + selectedOrder.order_number"></span>
                                        <span class="badge bg-amber-100 text-amber-700">Pending</span>
                                    </div>
                                    <div class="space-y-1 text-sm">
                                        <template x-for="item in selectedOrder.items" :key="item.id">
                                            <div class="flex justify-between">
                                                <span><span x-text="item.quantity"></span>x <span x-text="item.product?.name"></span></span>
                                                <span x-text="formatCurrency(item.subtotal)"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Totals -->
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm"><span>Subtotal</span><span x-text="formatCurrency(selectedOrder.subtotal)"></span></div>
                                    <div class="flex justify-between text-sm"><span>Tax</span><span x-text="formatCurrency(selectedOrder.tax_amount)"></span></div>
                                    <div class="flex justify-between text-sm"><span>Discount</span><span x-text="'-' + formatCurrency(selectedOrder.discount_amount || 0)"></span></div>
                                    <div class="flex justify-between font-bold text-xl pt-2 border-t border-[hsl(var(--border))]">
                                        <span>Total</span>
                                        <span class="text-[hsl(var(--primary))]" x-text="formatCurrency(selectedOrder.total)"></span>
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div>
                                    <label class="text-sm font-medium mb-2 block">Payment Method</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button @click="paymentMethod = 'cash'" class="btn btn-md min-h-[60px] flex-col gap-1" :class="paymentMethod === 'cash' ? 'btn-primary' : 'btn-outline'">
                                            <i class="fas fa-money-bill-wave text-lg"></i>
                                            <span class="text-xs">Cash</span>
                                        </button>
                                        <button @click="paymentMethod = 'card'" class="btn btn-md min-h-[60px] flex-col gap-1" :class="paymentMethod === 'card' ? 'btn-primary' : 'btn-outline'">
                                            <i class="fas fa-credit-card text-lg"></i>
                                            <span class="text-xs">Card</span>
                                        </button>
                                        <button @click="paymentMethod = 'transfer'" class="btn btn-md min-h-[60px] flex-col gap-1" :class="paymentMethod === 'transfer' ? 'btn-primary' : 'btn-outline'">
                                            <i class="fas fa-university text-lg"></i>
                                            <span class="text-xs">Transfer</span>
                                        </button>
                                        <button @click="paymentMethod = 'qris'" class="btn btn-md min-h-[60px] flex-col gap-1" :class="paymentMethod === 'qris' ? 'btn-primary' : 'btn-outline'">
                                            <i class="fas fa-qrcode text-lg"></i>
                                            <span class="text-xs">QRIS</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Cash Payment -->
                                <template x-if="paymentMethod === 'cash'">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-sm font-medium mb-1.5 block">Amount Received</label>
                                            <input type="number" x-model="amountReceived" class="input w-full min-h-[44px] text-lg" placeholder="0">
                                        </div>
                                        <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm">Change</span>
                                                <span class="text-xl font-bold" :class="change >= 0 ? 'text-emerald-600' : 'text-red-600'" x-text="formatCurrency(change)"></span>
                                            </div>
                                        </div>
                                        <!-- Quick Amount Buttons -->
                                        <div class="grid grid-cols-3 gap-2">
                                            <template x-for="amount in quickAmounts" :key="amount">
                                                <button @click="amountReceived = amount" class="btn btn-outline btn-sm" x-text="formatCurrency(amount)"></button>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Card Payment -->
                                <template x-if="paymentMethod === 'card'">
                                    <div>
                                        <label class="text-sm font-medium mb-1.5 block">Card Last 4 Digits</label>
                                        <input type="text" x-model="cardLast4" maxlength="4" class="input w-full min-h-[44px]" placeholder="1234">
                                    </div>
                                </template>

                                <!-- Process Payment Button -->
                                <button @click="processPayment()" :disabled="processing || !canProcess" class="btn btn-primary btn-lg w-full min-h-[56px]">
                                    <i class="fas" :class="processing ? 'fa-spinner animate-spin' : 'fa-check'"></i>
                                    <span x-text="processing ? 'Processing...' : 'Process Payment'"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function paymentApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true, processing: false,
        orders: [], selectedOrder: null,
        paymentMethod: 'cash', amountReceived: '', cardLast4: '',
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],

        async init() {
            this.initSidebar();
            await this.fetchOrders();
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

        async fetchOrders() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/orders?status=pending`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.orders = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        get pendingOrders() { return this.orders.filter(o => o.status === 'pending'); },
        get quickAmounts() {
            if (!this.selectedOrder) return [];
            const total = parseFloat(this.selectedOrder.total);
            return [total, Math.ceil(total / 10000) * 10000, Math.ceil(total / 50000) * 50000, 50000, 100000, 200000].filter((v, i, a) => a.indexOf(v) === i).slice(0, 6);
        },
        get change() { return (parseFloat(this.amountReceived) || 0) - (this.selectedOrder?.total || 0); },
        get canProcess() {
            if (!this.selectedOrder || !this.paymentMethod) return false;
            if (this.paymentMethod === 'cash') return parseFloat(this.amountReceived) >= this.selectedOrder.total;
            if (this.paymentMethod === 'card') return this.cardLast4.length === 4;
            return true;
        },

        selectOrder(order) {
            this.selectedOrder = order;
            this.amountReceived = '';
            this.cardLast4 = '';
            this.paymentMethod = 'cash';
        },

        formatCurrency(a) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(a || 0); },

        async processPayment() {
            if (!this.canProcess) return;
            this.processing = true;
            try {
                const token = localStorage.getItem('token');
                const payload = {
                    order_id: this.selectedOrder.id,
                    method: this.paymentMethod,
                    amount: this.paymentMethod === 'cash' ? parseFloat(this.amountReceived) : this.selectedOrder.total
                };
                if (this.paymentMethod === 'card') { payload.metadata = { last_four: this.cardLast4 }; }
                const res = await fetch(`${this.API_BASE_URL}/payments`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.success) {
                    alert('Payment successful!');
                    this.selectedOrder = null;
                    await this.fetchOrders();
                } else { alert(data.message || 'Payment failed'); }
            } catch (e) { console.error('Error:', e); alert('Payment failed'); } finally { this.processing = false; }
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
