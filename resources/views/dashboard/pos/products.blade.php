@extends('layouts.app')

@section('title', __('pos.products.title') . ' - QashierWise POS')

@section('content')
<div x-data="productsApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-products'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => __('pos.products.title'), 'description' => __('pos.products.description')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header with Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Product Inventory</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Manage products, pricing, and stock levels</p>
                    </div>
                    <button @click="openCreateModal()" class="btn btn-primary btn-md">
                        <i class="fas fa-plus"></i>
                        <span>Add Product</span>
                    </button>
                </div>

                <!-- Search and Filters -->
                <div class="card p-3 sm:p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Search</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm"></i>
                                <input type="text" x-model="search" @input.debounce.300ms="fetchProducts()" placeholder="Search by name or SKU..." class="input pl-10 w-full min-h-[44px]">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Category</label>
                            <select x-model="categoryFilter" @change="fetchProducts()" class="input w-full min-h-[44px]">
                                <option value="">All Categories</option>
                                <template x-for="category in categories" :key="category.id">
                                    <option :value="category.id" x-text="category.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Products Grid (Mobile) / Table (Desktop) -->
                <!-- Mobile Card View -->
                <div class="block lg:hidden space-y-3">
                    <!-- Loading Skeleton -->
                    <template x-if="loading">
                        <template x-for="i in 4" :key="'skeleton-'+i">
                            <div class="card p-4">
                                <div class="flex gap-3">
                                    <div class="skeleton h-16 w-16 rounded-lg flex-shrink-0"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="skeleton h-4 w-3/4"></div>
                                        <div class="skeleton h-3 w-1/2"></div>
                                        <div class="skeleton h-3 w-1/4"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <!-- Product Cards -->
                    <template x-if="!loading">
                        <template x-for="product in products" :key="product.id">
                            <div class="card p-4 hover:shadow-md transition-shadow">
                                <div class="flex gap-3">
                                    <div class="h-16 w-16 rounded-lg bg-[hsl(var(--muted))] flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-box text-xl text-[hsl(var(--muted-foreground))]"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate" x-text="product.name"></h3>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="'SKU: ' + product.sku"></p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-sm font-bold text-[hsl(var(--primary))]" x-text="formatCurrency(product.price)"></span>
                                            <span class="badge text-xs" :class="product.stock_quantity > 10 ? 'bg-emerald-100 text-emerald-700' : (product.stock_quantity > 0 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700')" x-text="product.stock_quantity + ' in stock'"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-3 pt-3 border-t border-[hsl(var(--border))]">
                                    <button @click="openEditModal(product)" class="btn btn-outline btn-sm flex-1">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button @click="openDeleteModal(product)" class="btn btn-outline btn-sm text-red-600 hover:bg-red-50">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Product</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">SKU</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Category</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Price</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Stock</th>
                                    <th class="text-center p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Status</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <!-- Loading Skeleton -->
                                <template x-if="loading">
                                    <template x-for="i in 5" :key="'table-skeleton-'+i">
                                        <tr>
                                            <td class="p-4"><div class="skeleton h-4 w-32"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-20"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-16 ml-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-12 ml-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-6 w-16 mx-auto rounded-full"></div></td>
                                            <td class="p-4"><div class="skeleton h-8 w-20 ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </template>

                                <!-- Product Rows -->
                                <template x-if="!loading">
                                    <template x-for="product in products" :key="product.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="h-10 w-10 rounded-lg bg-[hsl(var(--muted))] flex items-center justify-center">
                                                        <i class="fas fa-box text-[hsl(var(--muted-foreground))]"></i>
                                                    </div>
                                                    <span class="font-medium" x-text="product.name"></span>
                                                </div>
                                            </td>
                                            <td class="p-4 text-sm text-[hsl(var(--muted-foreground))]" x-text="product.sku"></td>
                                            <td class="p-4 text-sm" x-text="product.category?.name || '-'"></td>
                                            <td class="p-4 text-right font-medium" x-text="formatCurrency(product.price)"></td>
                                            <td class="p-4 text-right" x-text="product.stock_quantity"></td>
                                            <td class="p-4 text-center">
                                                <span class="badge text-xs" :class="product.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'" x-text="product.is_active ? 'Active' : 'Inactive'"></span>
                                            </td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button @click="openEditModal(product)" class="btn btn-ghost btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button @click="openDeleteModal(product)" class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
                <div x-show="!loading && products.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon">
                            <i class="fas fa-box-open text-2xl"></i>
                        </div>
                        <h3 class="font-semibold mt-4">No products found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Add your first product to get started.</p>
                        <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <div x-show="!loading && pagination.lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-[hsl(var(--muted-foreground))]">
                        Showing <span x-text="pagination.from"></span> to <span x-text="pagination.to"></span> of <span x-text="pagination.total"></span> products
                    </p>
                    <div class="flex items-center gap-2">
                        <button @click="goToPage(pagination.currentPage - 1)" :disabled="pagination.currentPage === 1" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <template x-for="page in paginationPages" :key="page">
                            <button @click="goToPage(page)" class="btn btn-sm" :class="page === pagination.currentPage ? 'btn-primary' : 'btn-outline'" x-text="page"></button>
                        </template>
                        <button @click="goToPage(pagination.currentPage + 1)" :disabled="pagination.currentPage === pagination.lastPage" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full h-full sm:h-auto sm:max-w-lg sm:max-h-[90vh] overflow-hidden flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold" x-text="editingProduct ? 'Edit Product' : 'Add New Product'"></h3>
                <button @click="closeModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveProduct()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.name" required class="input w-full min-h-[44px]" placeholder="Enter product name">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Category <span class="text-red-500">*</span></label>
                    <select x-model="form.category_id" required class="input w-full min-h-[44px]">
                        <option value="">Select category...</option>
                        <template x-for="category in categories" :key="category.id">
                            <option :value="category.id" x-text="category.name"></option>
                        </template>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium mb-1.5 block">Price <span class="text-red-500">*</span></label>
                        <input type="number" x-model="form.price" required min="0" step="0.01" class="input w-full min-h-[44px]" placeholder="0.00">
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-1.5 block">Stock Quantity <span class="text-red-500">*</span></label>
                        <input type="number" x-model="form.stock_quantity" required min="0" class="input w-full min-h-[44px]" placeholder="0">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Description</label>
                    <textarea x-model="form.description" rows="3" class="input w-full min-h-[80px]" placeholder="Product description (optional)"></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="form.is_active" id="is_active" class="rounded border-[hsl(var(--input))]">
                    <label for="is_active" class="text-sm">Active (available for sale)</label>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="saveProduct()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Saving...' : 'Save Product'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDeleteModal" x-transition class="fixed inset-0 bg-black/50" @click="closeDeleteModal()"></div>
        <div x-show="showDeleteModal" x-transition class="card relative w-full max-w-md p-6">
            <div class="text-center">
                <div class="mx-auto w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-4">
                    <i class="fas fa-trash text-red-600"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Delete Product</h3>
                <p class="text-sm text-[hsl(var(--muted-foreground))] mb-6">Are you sure you want to delete "<span x-text="deletingProduct?.name"></span>"? This action cannot be undone.</p>
                <div class="flex gap-3">
                    <button @click="closeDeleteModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                    <button @click="deleteProduct()" :disabled="deleting" class="btn btn-destructive btn-md flex-1">
                        <i class="fas" :class="deleting ? 'fa-spinner animate-spin' : 'fa-trash'"></i>
                        <span x-text="deleting ? 'Deleting...' : 'Delete'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function productsApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true,
        saving: false,
        deleting: false,
        products: [],
        categories: [],
        search: '',
        categoryFilter: '',
        showModal: false,
        showDeleteModal: false,
        editingProduct: null,
        deletingProduct: null,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            from: 0,
            to: 0,
            total: 0
        },
        form: {
            name: '',
            category_id: '',
            price: '',
            stock_quantity: '',
            description: '',
            is_active: true
        },
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await Promise.all([this.fetchProducts(), this.fetchCategories()]);
        },

        initSidebar() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                let savedState = localStorage.getItem('sidebarOpen');
                if (savedState !== null) this.sidebarOpen = JSON.parse(savedState);
            }
            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });
            window.addEventListener('resize', () => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth < 768;
                if (wasMobile && !this.isMobile) {
                    let savedState = localStorage.getItem('sidebarOpen');
                    this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
                } else if (!wasMobile && this.isMobile) {
                    this.sidebarOpen = false;
                }
            });
            let storedUser = localStorage.getItem('user');
            if (storedUser) { try { this.user = JSON.parse(storedUser); } catch (e) { this.user = { name: 'User', email: 'user@example.com' }; } }
            else { this.user = { name: 'User', email: 'user@example.com' }; }
        },

        async fetchProducts() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const params = new URLSearchParams({
                    page: this.pagination.currentPage,
                    per_page: 20
                });
                if (this.search) params.append('search', this.search);
                if (this.categoryFilter) params.append('category_id', this.categoryFilter);

                const response = await fetch(`${this.API_BASE_URL}/products?${params}`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.success) {
                    this.products = data.data.data || data.data;
                    if (data.data.meta) {
                        this.pagination = {
                            currentPage: data.data.meta.current_page,
                            lastPage: data.data.meta.last_page,
                            from: data.data.meta.from || 0,
                            to: data.data.meta.to || 0,
                            total: data.data.meta.total
                        };
                    } else if (data.data.current_page) {
                        this.pagination = {
                            currentPage: data.data.current_page,
                            lastPage: data.data.last_page,
                            from: data.data.from || 0,
                            to: data.data.to || 0,
                            total: data.data.total
                        };
                    }
                }
            } catch (e) {
                console.error('Error fetching products:', e);
            } finally {
                this.loading = false;
            }
        },

        async fetchCategories() {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/categories`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.success) {
                    this.categories = data.data.data || data.data;
                }
            } catch (e) {
                console.error('Error fetching categories:', e);
            }
        },

        get paginationPages() {
            const pages = [];
            const current = this.pagination.currentPage;
            const last = this.pagination.lastPage;
            for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
                pages.push(i);
            }
            return pages;
        },

        goToPage(page) {
            if (page >= 1 && page <= this.pagination.lastPage) {
                this.pagination.currentPage = page;
                this.fetchProducts();
            }
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
        },

        openCreateModal() {
            this.editingProduct = null;
            this.form = { name: '', category_id: '', price: '', stock_quantity: '', description: '', is_active: true };
            this.showModal = true;
        },

        openEditModal(product) {
            this.editingProduct = product;
            this.form = {
                name: product.name,
                category_id: product.category_id,
                price: product.price,
                stock_quantity: product.stock_quantity,
                description: product.description || '',
                is_active: product.is_active
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.editingProduct = null;
        },

        async saveProduct() {
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const url = this.editingProduct 
                    ? `${this.API_BASE_URL}/products/${this.editingProduct.id}`
                    : `${this.API_BASE_URL}/products`;
                const method = this.editingProduct ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method,
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await response.json();
                if (data.success) {
                    this.closeModal();
                    await this.fetchProducts();
                } else {
                    alert(data.message || 'Failed to save product');
                }
            } catch (e) {
                console.error('Error saving product:', e);
                alert('Failed to save product');
            } finally {
                this.saving = false;
            }
        },

        openDeleteModal(product) {
            this.deletingProduct = product;
            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            this.deletingProduct = null;
        },

        async deleteProduct() {
            this.deleting = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/products/${this.deletingProduct.id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.success) {
                    this.closeDeleteModal();
                    await this.fetchProducts();
                } else {
                    alert(data.message || 'Failed to delete product');
                }
            } catch (e) {
                console.error('Error deleting product:', e);
                alert('Failed to delete product');
            } finally {
                this.deleting = false;
            }
        },

        logout() {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        },

        addNotification() {},
        clearNotifications() {},
        removeNotification() {},
        formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
