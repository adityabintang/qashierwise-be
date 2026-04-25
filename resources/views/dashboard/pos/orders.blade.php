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
                    <template x-if="hasPermission('create_orders') || hasPermission('manage_orders')">
                        <button @click="openCreateModal()" class="btn btn-primary btn-md">
                            <i class="fas fa-plus"></i>
                            <span>New Order</span>
                        </button>
                    </template>
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
                        <table class="w-full">
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
                                    <span><span x-text="item.quantity"></span>x <span x-text="item.product?.name || 'Product'"></span></span>
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
        showCreateModal: false, showViewModal: false,
        selectedOrder: null,
        createForm: { store_id: '', table_id: '', delivery_type: 'pickup', alamat: '', ongkir: 0, catatan: '' },
        pagination: { currentPage: 1, lastPage: 1, from: 0, to: 0, total: 0 },
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],
        searchTimeout: null,
        // Permissions
        userPermissions: [], isAdmin: true,

        async init() {
            this.initDashboard();
            await this.fetchUserPermissions();
            await Promise.all([this.fetchOrders(), this.fetchStores(), this.fetchProducts()]);
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

        async openCreateModal() { this.createForm = { store_id: '', table_id: '', delivery_type: 'pickup', alamat: '', ongkir: 0, catatan: '' }; this.orderItems = []; this.products = []; this.productSearch = ''; await this.fetchProducts(); this.showCreateModal = true; },
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
                const res = await fetch(`${this.API_BASE_URL}/orders`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ store_id: this.createForm.store_id, table_id: this.createForm.table_id || null, delivery_type: this.createForm.delivery_type, alamat: this.createForm.delivery_type === 'delivery' ? this.createForm.alamat : null, ongkir: this.createForm.delivery_type === 'delivery' ? (parseFloat(this.createForm.ongkir) || 0) : 0, catatan: this.createForm.catatan || null, items: this.orderItems.map(i => ({ product_id: i.product_id, quantity: i.quantity })) }) });
                const data = await res.json();
                if (data.success) { this.closeCreateModal(); await this.fetchOrders(); } else { alert(data.message || 'Failed'); }
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
        closeViewModal() { this.showViewModal = false; this.selectedOrder = null; },

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

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
