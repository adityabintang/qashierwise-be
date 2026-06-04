@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', __('pos.orders.title') . ' - QashierWise POS')

@section('content')
<div x-data="ordersApp()" x-init="initDashboard()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-orders'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('pos.orders.title'), 'description' => __('pos.orders.description')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header with Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Sales Orders</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Create and manage customer orders</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Delivery / Pickup Toggle -->
                        <div class="flex items-center bg-[hsl(var(--muted)/0.6)] rounded-lg p-1 gap-1">
                            <button @click="setViewMode('all')" class="px-3 py-1.5 rounded-md text-sm font-medium transition-all" :class="viewMode === 'all' ? 'bg-white shadow text-[hsl(var(--foreground))]' : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]'">
                                Semua
                            </button>
                            <button @click="setViewMode('pickup')" class="px-3 py-1.5 rounded-md text-sm font-medium transition-all" :class="viewMode === 'pickup' ? 'bg-white shadow text-emerald-700' : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]'">
                                <i class="fas fa-store mr-1"></i>Pickup
                            </button>
                            <button @click="setViewMode('delivery')" class="px-3 py-1.5 rounded-md text-sm font-medium transition-all" :class="viewMode === 'delivery' ? 'bg-white shadow text-orange-600' : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]'">
                                <i class="fas fa-truck mr-1"></i>Delivery
                            </button>
                        </div>
                        <template x-if="hasPermission('create_orders') || hasPermission('manage_orders')">
                            <button @click="openCreateModal()" class="btn btn-primary btn-md">
                                <i class="fas fa-plus"></i>
                                <span>New Order</span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card p-3 sm:p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Status</label>
                            <select x-model="statusFilter" @change="fetchOrders()" class="input w-full min-h-[44px]">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Store</label>
                            <select x-model="storeFilter" @change="fetchOrders()" class="input w-full min-h-[44px]">
                                <option value="">All Stores</option>
                                <template x-for="store in stores" :key="store.id">
                                    <option :value="store.id" x-text="store.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-2">
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Search</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none z-10"></i>
                                <input type="text" x-model="search" @input="debounceSearch()" placeholder="Search order number..." class="input pl-10 w-full min-h-[44px]" style="padding-left: 2.5rem;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders List -->
                <!-- Mobile Card View -->
                <div class="block lg:hidden space-y-3">
                    <template x-if="loading">
                        <template x-for="i in 4" :key="'skeleton-'+i">
                            <div class="card p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="skeleton h-4 w-24"></div>
                                    <div class="skeleton h-6 w-16 rounded-full"></div>
                                </div>
                                <div class="skeleton h-3 w-32 mb-2"></div>
                                <div class="skeleton h-5 w-20"></div>
                            </div>
                        </template>
                    </template>

                    <template x-if="!loading">
                        <template x-for="order in orders" :key="order.id">
                            <div class="card p-4 hover:shadow-md transition-shadow" @click="viewOrder(order)">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-semibold" x-text="'#' + order.order_number"></span>
                                    <span class="badge text-xs" :class="getStatusClass(order.status)" x-text="order.status"></span>
                                </div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="order.store?.name || 'N/A'"></p>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(order.created_at)"></p>
                                <div class="flex justify-between items-center mt-3 pt-3 border-t border-[hsl(var(--border))]">
                                    <span class="text-lg font-bold text-[hsl(var(--primary))]" x-text="formatCurrency(order.total)"></span>
                                    <div class="flex gap-2">
                                        <button @click.stop="viewOrder(order)" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></button>
                                        <template x-if="order.status === 'pending'">
                                            <button @click.stop="cancelOrder(order)" class="btn btn-outline btn-sm text-red-600"><i class="fas fa-times"></i></button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden lg:block card overflow-hidden">
                    <div class="overflow-x-auto">
                        <!-- Pickup / All Table -->
                        <table x-show="viewMode !== 'delivery'" class="w-full">
                            <thead class="bg-[hsl(var(--muted)/0.5)]">
                                <tr>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Order #</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Store</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Table</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Items</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Total</th>
                                    <th class="text-center p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Status</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Date</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <template x-if="loading">
                                    <template x-for="i in 5" :key="'table-skeleton-'+i">
                                        <tr>
                                            <td class="p-4"><div class="skeleton h-4 w-20"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-12"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-8 ml-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-20 ml-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-6 w-16 mx-auto rounded-full"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-8 w-24 ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!loading">
                                    <template x-for="order in orders" :key="order.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                            <td class="p-4 font-medium" x-text="'#' + order.order_number"></td>
                                            <td class="p-4 text-sm" x-text="order.store?.name || '-'"></td>
                                            <td class="p-4 text-sm" x-text="order.table?.number ? 'Table ' + order.table.number : '-'"></td>
                                            <td class="p-4 text-right text-sm" x-text="order.items?.length || 0"></td>
                                            <td class="p-4 text-right font-medium" x-text="formatCurrency(order.total)"></td>
                                            <td class="p-4 text-center">
                                                <span class="badge text-xs" :class="getStatusClass(order.status)" x-text="order.status"></span>
                                            </td>
                                            <td class="p-4 text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(order.created_at)"></td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button @click="viewOrder(order)" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i></button>
                                                    <template x-if="order.status === 'pending'">
                                                        <button @click="cancelOrder(order)" class="btn btn-ghost btn-sm text-red-600"><i class="fas fa-times"></i></button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>

                        <!-- Delivery Table -->
                        <table x-show="viewMode === 'delivery'" class="w-full">
                            <thead class="bg-[hsl(var(--muted)/0.5)]">
                                <tr>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Order #</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Store</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Alamat</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Catatan</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Total</th>
                                    <th class="text-center p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Status</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Date</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <template x-if="loading">
                                    <template x-for="i in 5" :key="'del-skeleton-'+i">
                                        <tr>
                                            <td class="p-4"><div class="skeleton h-4 w-20"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-40"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-32"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-20 ml-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-6 w-16 mx-auto rounded-full"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-8 w-24 ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!loading">
                                    <template x-for="order in orders" :key="order.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                            <td class="p-4 font-medium" x-text="'#' + order.order_number"></td>
                                            <td class="p-4 text-sm" x-text="order.store?.name || '-'"></td>
                                            <td class="p-4 text-sm max-w-[200px]">
                                                <span x-text="order.alamat || '-'" class="block truncate cursor-default"
                                                    @mouseenter="showTooltip($el, order.alamat, 'Alamat')"
                                                    @mouseleave="hideTooltip()"></span>
                                            </td>
                                            <td class="p-4 text-sm max-w-[180px]">
                                                <span x-text="order.catatan || '-'"
                                                    :class="order.catatan ? 'block truncate text-amber-700 bg-amber-50 px-2 py-0.5 rounded cursor-default' : 'text-[hsl(var(--muted-foreground))]'"
                                                    @mouseenter="showTooltip($el, order.catatan, 'Catatan')"
                                                    @mouseleave="hideTooltip()"></span>
                                            </td>
                                            <td class="p-4 text-right font-medium" x-text="formatCurrency(order.total)"></td>
                                            <td class="p-4 text-center">
                                                <span class="badge text-xs" :class="getStatusClass(order.status)" x-text="order.status"></span>
                                            </td>
                                            <td class="p-4 text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(order.created_at)"></td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button @click="viewOrder(order)" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i></button>
                                                    <template x-if="order.status === 'pending'">
                                                        <button @click="cancelOrder(order)" class="btn btn-ghost btn-sm text-red-600"><i class="fas fa-times"></i></button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && orders.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-shopping-cart text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No orders found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Create your first order to get started.</p>
                        <template x-if="hasPermission('create_orders') || hasPermission('manage_orders')">
                            <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4"><i class="fas fa-plus"></i> New Order</button>
                        </template>
                    </div>
                </div>

                <!-- Pagination -->
                <div x-show="!loading && pagination.lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-[hsl(var(--muted-foreground))]">
                        Showing <span x-text="pagination.from"></span> to <span x-text="pagination.to"></span> of <span x-text="pagination.total"></span> orders
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

    <!-- Create Order Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showCreateModal" x-transition class="fixed inset-0 bg-black/50" @click="closeCreateModal()"></div>
        <div x-show="showCreateModal" x-transition class="card relative w-full h-full sm:h-auto sm:max-w-4xl sm:max-h-[90vh] overflow-hidden flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold">Create New Order</h3>
                <button @click="closeCreateModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto scroll-area">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 p-4 sm:p-6">
                    <!-- Order Details -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase">Order Details</h4>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Store <span class="text-red-500">*</span></label>
                            <select x-model="createForm.store_id" required class="input w-full min-h-[44px]">
                                <option value="">Select store...</option>
                                <template x-for="store in stores" :key="store.id">
                                    <option :value="store.id" x-text="store.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Table (Optional)</label>
                            <select x-model="createForm.table_id" class="input w-full min-h-[44px]">
                                <option value="">No table</option>
                                <template x-for="table in tables" :key="table.id">
                                    <option :value="table.id" x-text="'Table ' + table.number"></option>
                                </template>
                            </select>
                        </div>
                        <!-- Delivery Type -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Delivery Type</label>
                            <div class="flex gap-2">
                                <button type="button" @click="createForm.delivery_type = 'pickup'" class="flex-1 h-10 rounded-lg text-sm font-medium transition-all" :class="createForm.delivery_type === 'pickup' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    <i class="fas fa-store mr-1"></i> Pickup
                                </button>
                                <button type="button" @click="createForm.delivery_type = 'delivery'" class="flex-1 h-10 rounded-lg text-sm font-medium transition-all" :class="createForm.delivery_type === 'delivery' ? 'bg-orange-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    <i class="fas fa-truck mr-1"></i> Delivery
                                </button>
                            </div>
                        </div>
                        <div x-show="createForm.delivery_type === 'delivery'" x-transition class="space-y-3">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Alamat <span class="text-red-500">*</span></label>
                                <input type="text" x-model="createForm.alamat" placeholder="Alamat pengiriman..." class="input w-full min-h-[44px]">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Ongkir (Rp)</label>
                                <input type="number" x-model.number="createForm.ongkir" min="0" step="500" placeholder="0" class="input w-full min-h-[44px]">
                            </div>
                        </div>
                        <!-- Customer Info -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Nama Customer</label>
                                <input type="text" x-model="createForm.customer_name" placeholder="Nama pelanggan..." class="input w-full min-h-[44px]">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">No. WhatsApp</label>
                                <input type="text" x-model="createForm.customer_phone" placeholder="628xxx..." class="input w-full min-h-[44px]">
                            </div>
                        </div>
                        <!-- Payment Method -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Metode Pembayaran</label>
                            <div class="flex gap-2">
                                <button type="button" @click="createForm.payment_method = 'cash'" class="flex-1 h-10 rounded-lg text-sm font-medium transition-all" :class="createForm.payment_method === 'cash' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    <i class="fas fa-money-bill-wave mr-1"></i> Cash
                                </button>
                                <div class="flex-1 relative" :title="subMerchantActive === false ? 'QRIS tidak tersedia — daftarkan Sub-Merchant QRIS terlebih dahulu di menu Pengaturan QRIS' : ''">
                                    <button type="button"
                                        @click="subMerchantActive ? (createForm.payment_method = 'qris') : null"
                                        :disabled="!subMerchantActive"
                                        class="w-full h-10 rounded-lg text-sm font-medium transition-all"
                                        :class="!subMerchantActive ? 'bg-gray-100 text-gray-400 cursor-not-allowed opacity-60' : (createForm.payment_method === 'qris' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200')">
                                        <i class="fas fa-qrcode mr-1"></i> QRIS
                                        <template x-if="!subMerchantActive">
                                            <i class="fas fa-lock ml-1 text-xs"></i>
                                        </template>
                                    </button>
                                </div>
                            </div>
                            <p x-show="!subMerchantActive" class="text-xs text-amber-600 mt-1.5 flex items-start gap-1">
                                <i class="fas fa-exclamation-triangle mt-0.5 flex-shrink-0"></i>
                                <span>QRIS belum aktif. Daftarkan akun Sub-Merchant QRIS di <strong>menu Pengaturan QRIS</strong> untuk menggunakan fitur ini.</span>
                            </p>
                            <p x-show="subMerchantActive && createForm.payment_method === 'qris' && createForm.customer_phone" class="text-xs text-blue-600 mt-1">
                                <i class="fas fa-info-circle mr-1"></i> Link pembayaran akan dikirim ke WhatsApp customer
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Catatan (Optional)</label>
                            <input type="text" x-model="createForm.catatan" placeholder="Catatan pesanan..." class="input w-full min-h-[44px]">
                        </div>
                        <!-- Product Selection -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Add Products</label>
                            <div class="relative mb-2">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none z-10"></i>
                                <input type="text" x-model="productSearch" placeholder="Search products..." class="input pl-10 w-full min-h-[44px]" style="padding-left: 2.5rem;">
                            </div>
                            <div class="border border-[hsl(var(--border))] rounded-lg max-h-48 overflow-y-auto">
                                <template x-if="filteredProducts.length === 0">
                                    <div class="p-4 text-center text-[hsl(var(--muted-foreground))]">
                                        <p x-show="!products.length">No products found</p>
                                        <p x-show="products.length && productSearch">No products match your search</p>
                                    </div>
                                </template>
                                <template x-if="filteredProducts.length > 0">
                                    <div class="divide-y divide-[hsl(var(--border))]">
                                        <template x-for="product in filteredProducts" :key="product.id">
                                            <button @click="addToOrder(product)" class="w-full p-3 text-left hover:bg-[hsl(var(--muted)/0.5)] flex justify-between items-center">
                                                <span x-text="product.name"></span>
                                                <span class="text-sm text-[hsl(var(--primary))]" x-text="formatCurrency(product.price)"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <!-- Order Items -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-sm text-[hsl(var(--muted-foreground))] uppercase">Order Items</h4>
                        <div class="border border-[hsl(var(--border))] rounded-lg">
                            <template x-if="orderItems.length === 0">
                                <div class="p-8 text-center text-[hsl(var(--muted-foreground))]">
                                    <i class="fas fa-shopping-basket text-2xl mb-2"></i>
                                    <p class="text-sm">No items added yet</p>
                                </div>
                            </template>
                            <template x-if="orderItems.length > 0">
                                <div class="divide-y divide-[hsl(var(--border))]">
                                    <template x-for="(item, index) in orderItems" :key="index">
                                        <div class="p-3 flex items-center gap-3">
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium truncate" x-text="item.name"></p>
                                                <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="formatCurrency(item.price)"></p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button @click="decreaseQty(index)" class="btn btn-outline btn-sm btn-icon"><i class="fas fa-minus"></i></button>
                                                <span class="w-8 text-center" x-text="item.quantity"></span>
                                                <button @click="increaseQty(index)" class="btn btn-outline btn-sm btn-icon"><i class="fas fa-plus"></i></button>
                                            </div>
                                            <button @click="removeItem(index)" class="btn btn-ghost btn-sm text-red-600"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <!-- Order Summary -->
                        <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-2">
                            <div class="flex justify-between text-sm"><span>Subtotal</span><span x-text="formatCurrency(orderSubtotal)"></span></div>
                            <div class="flex justify-between text-sm"><span>Tax (10%)</span><span x-text="formatCurrency(orderTax)"></span></div>
                            <div x-show="createForm.delivery_type === 'delivery' && createForm.ongkir" class="flex justify-between text-sm"><span>Ongkir</span><span x-text="formatCurrency(parseFloat(createForm.ongkir) || 0)"></span></div>
                            <div class="flex justify-between font-bold text-lg pt-2 border-t border-[hsl(var(--border))]"><span>Total</span><span x-text="formatCurrency(orderTotal)"></span></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button @click="closeCreateModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="createOrder()" :disabled="creating || orderItems.length === 0" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="creating ? 'fa-spinner animate-spin' : 'fa-check'"></i>
                    <span x-text="creating ? 'Creating...' : 'Create Order'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Global Tooltip -->
    <div x-show="tooltip.show" x-cloak
        :style="`position:fixed;left:${tooltip.x}px;top:${tooltip.y}px;transform:translateY(-100%);z-index:9999;pointer-events:auto`"
        @mouseenter="tooltip.show=true" @mouseleave="tooltip.show=false"
        class="max-w-xs bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-xl"
        style="white-space:pre-wrap;word-break:break-word;">
        <div class="font-medium text-gray-400 mb-1" x-text="tooltip.label"></div>
        <span x-text="tooltip.text"></span>
        <div class="absolute top-full left-4 border-4 border-transparent border-t-gray-900"></div>
    </div>

    <!-- View Order Modal -->
    <div x-show="showViewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showViewModal" x-transition class="fixed inset-0 bg-black/50" @click="closeViewModal()"></div>
        <div x-show="showViewModal" x-transition class="card relative w-full h-full sm:h-auto sm:max-w-lg sm:max-h-[90vh] overflow-hidden flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold">Order Details</h3>
                <button @click="closeViewModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6" x-show="selectedOrder">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="text-xl font-bold" x-text="'#' + selectedOrder?.order_number"></h4>
                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="formatDate(selectedOrder?.created_at)"></p>
                    </div>
                    <span class="badge" :class="getStatusClass(selectedOrder?.status)" x-text="selectedOrder?.status"></span>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="text-[hsl(var(--muted-foreground))]">Store:</span><p class="font-medium" x-text="selectedOrder?.store?.name || '-'"></p></div>
                        <div><span class="text-[hsl(var(--muted-foreground))]">Table:</span><p class="font-medium" x-text="selectedOrder?.table?.number ? 'Table ' + selectedOrder.table.number : '-'"></p></div>
                        <div x-show="selectedOrder?.delivery_type"><span class="text-[hsl(var(--muted-foreground))]">Delivery:</span><p class="font-medium capitalize" x-text="selectedOrder?.delivery_type || '-'"></p></div>
                        <div x-show="selectedOrder?.alamat"><span class="text-[hsl(var(--muted-foreground))]">Alamat:</span><p class="font-medium" x-text="selectedOrder?.alamat || '-'"></p></div>
                    </div>
                    <div class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-3">Items</h5>
                        <div class="space-y-2">
                            <template x-for="item in selectedOrder?.items" :key="item.id">
                                <div class="flex justify-between text-sm">
                                    <span><span x-text="item.quantity"></span>x <span x-text="item.product?.name || item.product_name || item.product_retailer_id || 'Product'"></span></span>
                                    <span x-text="formatCurrency(item.subtotal)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-2">
                        <div class="flex justify-between text-sm"><span>Subtotal</span><span x-text="formatCurrency(selectedOrder?.subtotal)"></span></div>
                        <div class="flex justify-between text-sm"><span>Tax</span><span x-text="formatCurrency(selectedOrder?.tax_amount)"></span></div>
                        <div class="flex justify-between text-sm"><span>Discount</span><span x-text="'-' + formatCurrency(selectedOrder?.discount_amount || 0)"></span></div>
                        <div x-show="selectedOrder?.ongkir > 0" class="flex justify-between text-sm"><span>Ongkir</span><span x-text="formatCurrency(selectedOrder?.ongkir)"></span></div>
                        <div class="flex justify-between font-bold text-lg pt-2 border-t border-[hsl(var(--border))]"><span>Total</span><span x-text="formatCurrency(selectedOrder?.total)"></span></div>
                    </div>
                    <div x-show="selectedOrder?.catatan" class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-2">Catatan</h5>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] bg-amber-50 p-3 rounded-lg" x-text="selectedOrder?.catatan"></p>
                    </div>
                    <div x-show="selectedOrder?.customer_name || selectedOrder?.customer_phone" class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-3">Customer</h5>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div x-show="selectedOrder?.customer_name"><span class="text-[hsl(var(--muted-foreground))]">Nama:</span><p class="font-medium" x-text="selectedOrder?.customer_name"></p></div>
                            <div x-show="selectedOrder?.customer_phone"><span class="text-[hsl(var(--muted-foreground))]">WhatsApp:</span><p class="font-medium" x-text="selectedOrder?.customer_phone"></p></div>
                        </div>
                    </div>
                    <div class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-3">Payment Method</h5>
                        <template x-if="selectedOrder?.payments && selectedOrder.payments.length > 0">
                            <div class="space-y-2">
                                <template x-for="payment in selectedOrder.payments" :key="payment.id">
                                    <div class="flex justify-between text-sm">
                                        <span class="capitalize" x-text="payment.method"></span>
                                        <span x-text="formatCurrency(payment.amount)"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!selectedOrder?.payments || selectedOrder.payments.length === 0">
                            <p class="text-sm text-[hsl(var(--muted-foreground))]">No payment recorded</p>
                        </template>
                    </div>
                    <!-- QRIS Section -->
                    <template x-if="selectedOrder?.qris_transaction">
                        <div class="border-t border-[hsl(var(--border))] pt-4">
                            <h5 class="font-medium mb-3 flex items-center gap-2">
                                <i class="fas fa-qrcode text-blue-600"></i> QRIS Payment
                                <span class="text-xs px-2 py-0.5 rounded-full font-normal"
                                    :class="{
                                        'bg-amber-100 text-amber-700': selectedOrder.qris_transaction.status === 'pending',
                                        'bg-emerald-100 text-emerald-700': selectedOrder.qris_transaction.status === 'settlement',
                                        'bg-red-100 text-red-700': ['cancel','expire'].includes(selectedOrder.qris_transaction.status)
                                    }"
                                    x-text="selectedOrder.qris_transaction.status">
                                </span>
                            </h5>
                            <div class="flex flex-col items-center gap-3">
                                <template x-if="selectedOrder.qris_transaction.qr_code_url">
                                    <img :src="selectedOrder.qris_transaction.qr_code_url" alt="QRIS Code" class="w-48 h-48 border border-gray-200 rounded-lg p-2">
                                </template>
                                <div class="w-full space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-[hsl(var(--muted-foreground))]">Jumlah</span>
                                        <span class="font-medium" x-text="formatCurrency(selectedOrder.qris_transaction.amount)"></span>
                                    </div>
                                    <div x-show="selectedOrder.qris_transaction.expires_at" class="flex justify-between text-sm">
                                        <span class="text-[hsl(var(--muted-foreground))]">Berlaku hingga</span>
                                        <span x-text="formatDate(selectedOrder.qris_transaction.expires_at)"></span>
                                    </div>
                                    <div class="pt-2">
                                        <a :href="selectedOrder.qris_transaction.shareable_link" target="_blank"
                                            class="btn btn-outline btn-sm w-full flex items-center justify-center gap-2">
                                            <i class="fas fa-external-link-alt"></i>
                                            <span>Buka Link Pembayaran</span>
                                        </a>
                                        <button x-show="selectedOrder?.customer_phone"
                                            @click="resendQrisLink(selectedOrder)"
                                            class="btn btn-ghost btn-sm w-full mt-1 flex items-center justify-center gap-2 text-green-600">
                                            <i class="fab fa-whatsapp"></i>
                                            <span>Kirim Ulang ke WhatsApp</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Delivery / Fulfillment Section -->
                    <div class="border-t border-[hsl(var(--border))] pt-4">
                        <h5 class="font-medium mb-3 flex items-center gap-2">
                            <i class="fas fa-motorcycle text-emerald-600"></i> Pengantaran
                            <template x-if="selectedOrder?.fulfillment_status">
                                <span class="text-xs px-2 py-0.5 rounded-full font-normal"
                                      :class="fulfillClass(selectedOrder.fulfillment_status)"
                                      x-text="fulfillLabel(selectedOrder.fulfillment_status)"></span>
                            </template>
                        </h5>

                        <!-- Confirm action (not yet confirmed) -->
                        <template x-if="canConfirm(selectedOrder)">
                            <div class="space-y-3">
                                <!-- Pickup: simple confirm -->
                                <template x-if="selectedOrder?.delivery_type !== 'delivery'">
                                    <button @click="confirmOrder(selectedOrder)" :disabled="confirming"
                                            class="btn btn-primary w-full flex items-center justify-center gap-2">
                                        <i class="fas fa-check"></i>
                                        <span>Konfirmasi Pesanan (Pickup)</span>
                                    </button>
                                </template>

                                <!-- Delivery: choose driver + confirm -->
                                <template x-if="selectedOrder?.delivery_type === 'delivery'">
                                    <div class="space-y-3 bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                        <p class="text-sm font-medium">Tugaskan Kurir</p>
                                        <div>
                                            <label class="text-xs text-[hsl(var(--muted-foreground))] mb-1 block">Pilih Driver</label>
                                            <select x-model="confirmForm.delivery_driver_id" @change="onDriverSelected()" class="input w-full min-h-[44px]">
                                                <option value="">— Input manual —</option>
                                                <template x-for="d in drivers" :key="d.id">
                                                    <option :value="d.id" x-text="d.name + ' (' + d.phone + ')'"></option>
                                                </template>
                                            </select>
                                            <a href="/dashboard/delivery" class="text-xs text-emerald-600 hover:underline mt-1 inline-block">+ Kelola driver</a>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="text-xs text-[hsl(var(--muted-foreground))] mb-1 block">Nama Pengantar <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="confirmForm.courier_name" class="input w-full min-h-[44px]" placeholder="Nama driver">
                                            </div>
                                            <div>
                                                <label class="text-xs text-[hsl(var(--muted-foreground))] mb-1 block">WhatsApp Pengantar <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="confirmForm.courier_phone" class="input w-full min-h-[44px]" placeholder="08xxxxxxxxxx">
                                            </div>
                                        </div>
                                        <button @click="confirmOrder(selectedOrder)" :disabled="confirming"
                                                class="btn btn-primary w-full flex items-center justify-center gap-2">
                                            <i class="fas fa-paper-plane"></i>
                                            <span x-text="confirming ? 'Memproses...' : 'Konfirmasi & Kirim ke Kurir'"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Already confirmed: show courier + proof -->
                        <template x-if="selectedOrder?.fulfillment_status">
                            <div class="space-y-2 text-sm">
                                <template x-if="selectedOrder?.courier_name">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div><span class="text-[hsl(var(--muted-foreground))]">Kurir:</span><p class="font-medium" x-text="selectedOrder.courier_name"></p></div>
                                        <div><span class="text-[hsl(var(--muted-foreground))]">WA Kurir:</span><p class="font-medium" x-text="selectedOrder.courier_phone"></p></div>
                                    </div>
                                </template>
                                <div x-show="selectedOrder?.delivered_at" class="text-[hsl(var(--muted-foreground))]">
                                    Diterima: <span x-text="formatDate(selectedOrder?.delivered_at)"></span>
                                </div>
                                <div x-show="selectedOrder?.complaint_note" class="bg-red-50 text-red-700 p-3 rounded-lg" x-text="selectedOrder?.complaint_note"></div>
                                <template x-if="selectedOrder?.proof_image_url">
                                    <div>
                                        <p class="text-[hsl(var(--muted-foreground))] mb-1">Bukti Pengiriman:</p>
                                        <img :src="selectedOrder.proof_image_url" alt="Bukti" class="rounded-lg w-full max-h-64 object-cover cursor-pointer" @click="window.open(selectedOrder.proof_image_url,'_blank')">
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function ordersApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true, creating: false,
        orders: [], stores: [], tables: [], products: [], orderItems: [],
        search: '', statusFilter: '', storeFilter: '', productSearch: '',
        viewMode: 'all',
        showCreateModal: false, showViewModal: false,
        selectedOrder: null,
        createForm: { store_id: '', table_id: '', delivery_type: 'pickup', alamat: '', ongkir: 0, catatan: '', customer_name: '', customer_phone: '', payment_method: 'cash' },
        pagination: { currentPage: 1, lastPage: 1, from: 0, to: 0, total: 0 },
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],
        searchTimeout: null,
        // Permissions
        userPermissions: [], isAdmin: true,
        // Sub-merchant QRIS availability
        subMerchantActive: null,
        // Delivery fulfillment
        drivers: [],
        confirming: false,
        confirmForm: { delivery_driver_id: '', courier_name: '', courier_phone: '' },

        async init() {
            this.initDashboard();
            await this.fetchUserPermissions();
            await Promise.all([this.fetchOrders(), this.fetchStores(), this.fetchProducts(), this.fetchSubMerchantStatus(), this.fetchDrivers()]);
            this.openDeepLinkedOrder();
        },

        async fetchDrivers() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${window.location.origin}/api/delivery/drivers?active_only=1`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) this.drivers = data.data || [];
            } catch (e) { /* non-fatal */ }
        },

        // Notif link from Fonnte deep-links to ?order=ORDER_NUMBER — auto-open detail.
        async openDeepLinkedOrder() {
            const num = new URLSearchParams(window.location.search).get('order');
            if (!num) return;
            let found = (this.orders || []).find(o => o.order_number === num);
            if (!found) {
                // Search the backend by order number, then open.
                try {
                    const token = localStorage.getItem('token');
                    const res = await fetch(`${this.API_BASE_URL}/orders?search=${encodeURIComponent(num)}`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                    const data = await res.json();
                    const list = data?.data?.data || data?.data || [];
                    found = list.find(o => o.order_number === num) || list[0];
                } catch (e) { /* ignore */ }
            }
            if (found) this.viewOrder(found);
        },

        async fetchSubMerchantStatus() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${window.location.origin}/api/sub-merchant/status`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    const sm = data.data;
                    this.subMerchantActive = sm.is_sub_merchant && sm.sub_merchant?.is_active && sm.sub_merchant?.can_accept_payments;
                }
            } catch (e) { this.subMerchantActive = false; }
        },

        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.pagination.currentPage = 1;
                this.fetchOrders();
            }, 300);
        },

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
            this.$watch('createForm.store_id', () => {
                this.createForm.table_id = '';
                this.fetchTables();
            });
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

        async fetchOrders() {
            // Check permission first
            if (!this.hasPermission('view_orders') && !this.hasPermission('manage_orders')) {
                this.orders = []; this.loading = false; return;
            }
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const params = new URLSearchParams({ page: this.pagination.currentPage });
                if (this.search) params.append('search', this.search);
                if (this.statusFilter) params.append('status', this.statusFilter);
                if (this.storeFilter) params.append('store_id', this.storeFilter);
                if (this.viewMode === 'delivery') params.append('delivery_type', 'delivery');
                else if (this.viewMode === 'pickup') params.append('delivery_type', 'pickup');
                const res = await fetch(`${this.API_BASE_URL}/orders?${params}`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.orders = data.data.data || data.data;
                    if (data.data.current_page) { this.pagination = { currentPage: data.data.current_page, lastPage: data.data.last_page, from: data.data.from || 0, to: data.data.to || 0, total: data.data.total }; }
                }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        async fetchStores() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/stores`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.stores = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); }
        },

        async fetchTables() {
            if (!this.createForm.store_id) { this.tables = []; return; }
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/tables?store_id=${this.createForm.store_id}`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.tables = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); }
        },

        async fetchProducts() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/products`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.products = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); }
        },

        get filteredProducts() {
            if (!this.productSearch) return this.products;
            const search = this.productSearch.toLowerCase();
            return this.products.filter(p => p.name.toLowerCase().includes(search));
        },

        setViewMode(mode) { this.viewMode = mode; this.pagination.currentPage = 1; this.fetchOrders(); },
        get paginationPages() { const p = [], c = this.pagination.currentPage, l = this.pagination.lastPage; for (let i = Math.max(1, c - 2); i <= Math.min(l, c + 2); i++) p.push(i); return p; },
        goToPage(page) { if (page >= 1 && page <= this.pagination.lastPage) { this.pagination.currentPage = page; this.fetchOrders(); } },
        formatCurrency(a) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(a || 0); },
        formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'; },
        getStatusClass(s) { return { 'pending': 'bg-amber-100 text-amber-700', 'paid': 'bg-emerald-100 text-emerald-700', 'cancelled': 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-700'; },

        get orderSubtotal() { return this.orderItems.reduce((t, i) => t + (i.price * i.quantity), 0); },
        get orderTax() { return this.orderSubtotal * 0.1; },
        get orderTotal() { return this.orderSubtotal + this.orderTax + (this.createForm.delivery_type === 'delivery' ? (parseFloat(this.createForm.ongkir) || 0) : 0); },

        addToOrder(product) { const existing = this.orderItems.find(i => i.product_id === product.id); if (existing) { existing.quantity++; } else { this.orderItems.push({ product_id: product.id, name: product.name, price: product.price, quantity: 1 }); } },
        increaseQty(i) { this.orderItems[i].quantity++; },
        decreaseQty(i) { if (this.orderItems[i].quantity > 1) this.orderItems[i].quantity--; else this.removeItem(i); },
        removeItem(i) { this.orderItems.splice(i, 1); },

        async openCreateModal() { this.createForm = { store_id: '', table_id: '', delivery_type: 'pickup', alamat: '', ongkir: 0, catatan: '', customer_name: '', customer_phone: '', payment_method: 'cash' }; this.orderItems = []; this.products = []; this.productSearch = ''; await this.fetchProducts(); this.showCreateModal = true; },
        closeCreateModal() { this.showCreateModal = false; },

        async createOrder() {
            // Check permission before creating
            if (!this.hasPermission('create_orders') && !this.hasPermission('manage_orders')) {
                alert('You do not have permission to create orders');
                return;
            }
            if (!this.createForm.store_id || this.orderItems.length === 0) return;
            this.creating = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/orders`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ store_id: this.createForm.store_id, table_id: this.createForm.table_id || null, delivery_type: this.createForm.delivery_type, alamat: this.createForm.delivery_type === 'delivery' ? this.createForm.alamat : null, ongkir: this.createForm.delivery_type === 'delivery' ? (parseFloat(this.createForm.ongkir) || 0) : 0, catatan: this.createForm.catatan || null, customer_name: this.createForm.customer_name || null, customer_phone: this.createForm.customer_phone || null, payment_method: this.createForm.payment_method || 'cash', items: this.orderItems.map(i => ({ product_id: i.product_id, quantity: i.quantity })) }) });
                const data = await res.json();
                if (data.success) {
                    this.closeCreateModal();
                    await this.fetchOrders();
                    if (data.qris_warning) { alert('⚠️ ' + data.qris_warning); }
                    if (data.qris_transaction) {
                        const order = data.data;
                        order.qris_transaction = data.qris_transaction;
                        this.selectedOrder = order;
                        this.showViewModal = true;
                    }
                } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.creating = false; }
        },

        async viewOrder(order) {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/orders/${order.id}`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.selectedOrder = data.data;
                    this.showViewModal = true;
                } else {
                    alert(data.message || 'Failed to load order');
                }
            } catch (e) { console.error('Error:', e); alert('Failed to load order'); }
        },
        closeViewModal() { this.showViewModal = false; this.selectedOrder = null; this.resetConfirmForm(); },

        resetConfirmForm() { this.confirmForm = { delivery_driver_id: '', courier_name: '', courier_phone: '' }; },

        // When a directory driver is selected, prefill name/phone (still editable).
        onDriverSelected() {
            const d = this.drivers.find(x => String(x.id) === String(this.confirmForm.delivery_driver_id));
            if (d) { this.confirmForm.courier_name = d.name; this.confirmForm.courier_phone = d.phone; }
            else { this.confirmForm.courier_name = ''; this.confirmForm.courier_phone = ''; }
        },

        canConfirm(order) {
            if (!order || order.fulfillment_status) return false;
            if (order.delivery_type === 'delivery') return order.status === 'paid';
            return ['pending', 'paid'].includes(order.status); // pickup
        },

        async confirmOrder(order) {
            if (!this.hasPermission('manage_orders')) { alert('Anda tidak punya izin.'); return; }
            const isDelivery = order.delivery_type === 'delivery';
            if (isDelivery && (!this.confirmForm.courier_name || !this.confirmForm.courier_phone)) {
                alert('Pilih driver atau isi nama & WhatsApp pengantar.');
                return;
            }
            if (!confirm(isDelivery ? 'Konfirmasi pesanan & tugaskan kurir?' : 'Konfirmasi pesanan pickup ini?')) return;
            this.confirming = true;
            try {
                const token = localStorage.getItem('token');
                const body = isDelivery ? {
                    delivery_driver_id: this.confirmForm.delivery_driver_id || null,
                    courier_name: this.confirmForm.courier_name,
                    courier_phone: this.confirmForm.courier_phone,
                } : {};
                const res = await fetch(`${this.API_BASE_URL}/orders/${order.id}/confirm`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedOrder = data.data;
                    this.resetConfirmForm();
                    await this.fetchOrders();
                    alert(data.message || 'Pesanan dikonfirmasi.');
                } else {
                    alert(data.message || 'Gagal konfirmasi.');
                }
            } catch (e) { console.error(e); alert('Gagal konfirmasi.'); }
            this.confirming = false;
        },

        fulfillLabel(s) {
            return ({ out_for_delivery: 'Sedang Diantar', delivered: 'Diterima', complaint: 'Komplain', confirmed: 'Dikonfirmasi', awaiting_confirmation: 'Menunggu Konfirmasi' })[s] || s;
        },
        fulfillClass(s) {
            return ({ out_for_delivery: 'bg-amber-100 text-amber-700', delivered: 'bg-emerald-100 text-emerald-700', complaint: 'bg-red-100 text-red-700', confirmed: 'bg-blue-100 text-blue-700' })[s] || 'bg-gray-100 text-gray-600';
        },

        async resendQrisLink(order) {
            if (!order?.customer_phone || !order?.qris_transaction?.shareable_link) return;
            try {
                const token = localStorage.getItem('token');
                await fetch(`${this.API_BASE_URL}/orders/${order.id}/resend-qris`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                alert('Link QRIS berhasil dikirim ulang ke WhatsApp customer');
            } catch (e) { console.error('Error:', e); alert('Gagal mengirim ulang link'); }
        },

        async completeOrder(order) {
            if (!confirm('Complete this order?')) return;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/orders/${order.id}/complete`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { await this.fetchOrders(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); }
        },

        async cancelOrder(order) {
            // Check permission before canceling
            if (!this.hasPermission('cancel_orders') && !this.hasPermission('manage_orders')) {
                alert('You do not have permission to cancel orders');
                return;
            }
            if (!confirm('Cancel this order?')) return;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/orders/${order.id}/cancel`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { await this.fetchOrders(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); }
        },

        tooltip: { show: false, text: '', label: '', x: 0, y: 0 },
        showTooltip(el, text, label) {
            if (!text) return;
            const r = el.getBoundingClientRect();
            this.tooltip = { show: true, text, label, x: r.left, y: r.top - 8 };
        },
        hideTooltip() { this.tooltip.show = false; },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
