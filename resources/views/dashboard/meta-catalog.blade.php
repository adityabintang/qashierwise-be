@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Meta Catalog - QashierWise')

@section('content')
<div x-data="metaCatalogApp()" x-init="init()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'meta-catalog'])

    <div class="flex-1 flex flex-col overflow-y-auto">
        @include('components.dashboard-header', ['title' => 'Meta Catalog', 'description' => 'Produk dari katalog Meta Business Anda'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Katalog Produk Meta</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Kelola dan lihat produk dari Facebook Business Catalog</p>
                    </div>
                    <button @click="selectedCatalog ? backToCatalogs() : fetchCatalogs()" class="btn btn-outline btn-md">
                        <template x-if="selectedCatalog">
                            <span><i class="fas fa-arrow-left mr-2"></i>Kembali ke Katalog</span>
                        </template>
                        <template x-if="!selectedCatalog">
                            <span><i class="fas fa-sync-alt mr-2" :class="loading ? 'animate-spin' : ''"></i>Muat Ulang</span>
                        </template>
                    </button>
                </div>

                <!-- Permission Warning Banner -->
                <template x-if="error && error.code === 'PERMISSION_DENIED'">
                    <div class="card p-5 border border-yellow-300 bg-yellow-50 dark:bg-yellow-950/20">
                        <div class="flex gap-3">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-0.5 flex-shrink-0"></i>
                            <div class="space-y-2">
                                <h3 class="font-semibold text-yellow-800 dark:text-yellow-300">Izin Catalog Diperlukan</h3>
                                <p class="text-sm text-yellow-700 dark:text-yellow-400" x-text="error.message"></p>
                                <div class="mt-3 p-3 rounded bg-yellow-100 dark:bg-yellow-900/40 text-xs font-mono text-yellow-800 dark:text-yellow-300">
                                    Gunakan Config ID: <strong>3015067632023945</strong> (Catalog OAuth) saat menghubungkan akun WhatsApp.
                                </div>
                                <a href="/dashboard/whatsapp-account" class="btn btn-sm mt-2" style="background-color: #f59e0b; color: white;">
                                    <i class="fab fa-whatsapp mr-1"></i>Hubungkan Ulang Akun WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Not Connected Warning -->
                <template x-if="error && error.code === 'WHATSAPP_NOT_CONNECTED'">
                    <div class="card p-5 border border-red-300 bg-red-50 dark:bg-red-950/20">
                        <div class="flex gap-3">
                            <i class="fab fa-whatsapp text-red-500 text-xl mt-0.5 flex-shrink-0"></i>
                            <div class="space-y-2">
                                <h3 class="font-semibold text-red-800 dark:text-red-300">Akun WhatsApp Belum Terhubung</h3>
                                <p class="text-sm text-red-700 dark:text-red-400">Hubungkan akun WhatsApp Business Anda terlebih dahulu untuk mengakses katalog produk Meta.</p>
                                <a href="/dashboard/whatsapp-account" class="btn btn-sm btn-primary mt-2">
                                    <i class="fab fa-whatsapp mr-1"></i>Hubungkan Sekarang
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Generic Error -->
                <template x-if="error && error.code !== 'PERMISSION_DENIED' && error.code !== 'WHATSAPP_NOT_CONNECTED'">
                    <div class="card p-4 border border-red-300 bg-red-50 dark:bg-red-950/20">
                        <div class="flex gap-3 items-start">
                            <i class="fas fa-circle-exclamation text-red-500 mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="text-sm font-medium text-red-800 dark:text-red-300" x-text="error.message"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- CATALOG LIST VIEW -->
                <template x-if="!selectedCatalog">
                    <div>
                        <!-- Loading -->
                        <template x-if="loading">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <template x-for="i in 3" :key="'skel-'+i">
                                    <div class="card p-5 space-y-3">
                                        <div class="skeleton h-5 w-2/3"></div>
                                        <div class="skeleton h-4 w-1/3"></div>
                                        <div class="skeleton h-9 w-full mt-2"></div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Empty State -->
                        <template x-if="!loading && !error && catalogs.length === 0">
                            <div class="card p-10 text-center">
                                <i class="fas fa-store text-4xl text-[hsl(var(--muted-foreground))] mb-4"></i>
                                <h3 class="font-semibold text-lg mb-1">Tidak ada katalog ditemukan</h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">Pastikan akun Meta Business Anda memiliki katalog produk yang aktif.</p>
                            </div>
                        </template>

                        <!-- Catalog Cards -->
                        <template x-if="!loading && catalogs.length > 0">
                            <div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">
                                    <span x-text="catalogs.length"></span> katalog ditemukan
                                    <span x-show="businessId" class="ml-2 font-mono text-xs opacity-60">Business ID: <span x-text="businessId"></span></span>
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <template x-for="catalog in catalogs" :key="catalog.id">
                                        <div class="card p-5 hover:shadow-md transition-shadow flex flex-col gap-3">
                                            <div class="flex items-start gap-3">
                                                <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center flex-shrink-0">
                                                    <i class="fab fa-facebook text-blue-600 text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h3 class="font-semibold truncate" x-text="catalog.name"></h3>
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono" x-text="'ID: ' + catalog.id"></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="badge badge-secondary text-xs">
                                                    <i class="fas fa-box mr-1"></i>
                                                    <span x-text="(catalog.product_count ?? '–') + ' produk'"></span>
                                                </span>
                                                <template x-if="catalog.vertical">
                                                    <span class="badge badge-outline text-xs capitalize" x-text="catalog.vertical.toLowerCase().replace('_', ' ')"></span>
                                                </template>
                                            </div>
                                            <button @click="selectCatalog(catalog)" class="btn btn-primary btn-sm w-full mt-auto">
                                                <i class="fas fa-eye mr-1"></i>Lihat Produk
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- PRODUCT LIST VIEW -->
                <template x-if="selectedCatalog">
                    <div class="space-y-4">
                        <!-- Catalog Info -->
                        <div class="card p-4 flex items-center gap-3">
                            <div class="h-9 w-9 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center flex-shrink-0">
                                <i class="fab fa-facebook text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold" x-text="selectedCatalog.name"></p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono" x-text="'Catalog ID: ' + selectedCatalog.id"></p>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="card p-3">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none"></i>
                                <input
                                    type="text"
                                    x-model="productSearch"
                                    placeholder="Cari produk berdasarkan nama..."
                                    class="input w-full min-h-[44px]"
                                    style="padding-left: 2.5rem;"
                                >
                            </div>
                        </div>

                        <!-- Loading products -->
                        <template x-if="loadingProducts">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <template x-for="i in 8" :key="'pskel-'+i">
                                    <div class="card p-4 space-y-3">
                                        <div class="skeleton h-40 w-full rounded-lg"></div>
                                        <div class="skeleton h-4 w-3/4"></div>
                                        <div class="skeleton h-3 w-1/2"></div>
                                        <div class="skeleton h-5 w-1/3"></div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Empty products -->
                        <template x-if="!loadingProducts && filteredProducts.length === 0">
                            <div class="card p-10 text-center">
                                <i class="fas fa-box-open text-4xl text-[hsl(var(--muted-foreground))] mb-4"></i>
                                <h3 class="font-semibold text-lg mb-1">Tidak ada produk</h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="productSearch ? 'Tidak ada hasil untuk pencarian \'' + productSearch + '\'' : 'Katalog ini belum memiliki produk.'"></p>
                            </div>
                        </template>

                        <!-- Products Grid -->
                        <template x-if="!loadingProducts && filteredProducts.length > 0">
                            <div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">
                                    Menampilkan <span x-text="filteredProducts.length"></span> dari <span x-text="products.length"></span> produk
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    <template x-for="product in filteredProducts" :key="product.id">
                                        <div class="card overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                                            <!-- Product Image -->
                                            <div class="h-44 bg-[hsl(var(--muted))] flex items-center justify-center overflow-hidden">
                                                <template x-if="product.image_url">
                                                    <img :src="product.image_url" :alt="product.name" class="w-full h-full object-cover" loading="lazy">
                                                </template>
                                                <template x-if="!product.image_url">
                                                    <i class="fas fa-image text-3xl text-[hsl(var(--muted-foreground))]"></i>
                                                </template>
                                            </div>

                                            <!-- Product Info -->
                                            <div class="p-4 flex flex-col gap-2 flex-1">
                                                <h3 class="font-semibold text-sm leading-tight line-clamp-2" x-text="product.name"></h3>

                                                <template x-if="product.description">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))] line-clamp-2" x-text="product.description"></p>
                                                </template>

                                                <!-- Price -->
                                                <div class="flex items-center justify-between mt-auto pt-2">
                                                    <span class="font-bold text-[hsl(var(--primary))]" x-text="formatPrice(product.price, product.currency)"></span>
                                                    <span
                                                        class="badge text-xs"
                                                        :class="product.availability === 'in stock' ? 'badge-success' : 'badge-secondary'"
                                                        x-text="product.availability === 'in stock' ? 'Tersedia' : (product.availability ?? '–')"
                                                    ></span>
                                                </div>

                                                <!-- SKU -->
                                                <template x-if="product.retailer_id">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono truncate" x-text="'SKU: ' + product.retailer_id"></p>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Load More -->
                                <template x-if="pagingCursor">
                                    <div class="text-center mt-6">
                                        <button @click="loadMoreProducts()" :disabled="loadingMore" class="btn btn-outline btn-md">
                                            <template x-if="loadingMore">
                                                <span><i class="fas fa-spinner animate-spin mr-2"></i>Memuat...</span>
                                            </template>
                                            <template x-if="!loadingMore">
                                                <span><i class="fas fa-chevron-down mr-2"></i>Muat Lebih Banyak</span>
                                            </template>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

            </div>
        </main>
    </div>
</div>

<script>
function metaCatalogApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/whatsapp',
        loading: false,
        catalogs: [],
        businessId: null,
        selectedCatalog: null,
        products: [],
        loadingProducts: false,
        loadingMore: false,
        pagingCursor: null,
        productSearch: '',
        error: null,
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        get filteredProducts() {
            if (!this.productSearch) return this.products;
            const q = this.productSearch.toLowerCase();
            return this.products.filter(p =>
                (p.name || '').toLowerCase().includes(q) ||
                (p.retailer_id || '').toLowerCase().includes(q) ||
                (p.description || '').toLowerCase().includes(q)
            );
        },

        async init() {
            this.initDashboard();
            await this.fetchCatalogs();
        },

        initDashboard() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                let saved = localStorage.getItem('sidebarOpen');
                if (saved !== null) this.sidebarOpen = JSON.parse(saved);
            }
            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });
            window.addEventListener('resize', () => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth < 768;
                if (wasMobile && !this.isMobile) {
                    let saved = localStorage.getItem('sidebarOpen');
                    this.sidebarOpen = saved !== null ? JSON.parse(saved) : true;
                } else if (!wasMobile && this.isMobile) {
                    this.sidebarOpen = false;
                }
            });
            let storedUser = localStorage.getItem('user');
            if (storedUser) {
                try { this.user = JSON.parse(storedUser); }
                catch (e) { this.user = { name: 'User', email: '' }; }
            } else {
                this.user = { name: 'User', email: '' };
            }
        },

        async fetchCatalogs() {
            this.loading = true;
            this.error = null;
            this.catalogs = [];
            this.selectedCatalog = null;
            this.products = [];

            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/catalog/catalogs`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (response.status === 401) {
                    window.location.href = '/login';
                    return;
                }

                if (data.success) {
                    this.catalogs = data.data.catalogs || [];
                    this.businessId = data.data.business_id || null;
                } else {
                    this.error = {
                        code: data.error_code || 'UNKNOWN',
                        message: data.message || 'Gagal memuat katalog.',
                    };
                }
            } catch (e) {
                console.error('fetchCatalogs error:', e);
                this.error = { code: 'NETWORK_ERROR', message: 'Gagal terhubung ke server. Periksa koneksi internet Anda.' };
            } finally {
                this.loading = false;
            }
        },

        async selectCatalog(catalog) {
            this.selectedCatalog = catalog;
            this.products = [];
            this.pagingCursor = null;
            this.productSearch = '';
            await this.fetchProducts();
        },

        backToCatalogs() {
            this.selectedCatalog = null;
            this.products = [];
            this.pagingCursor = null;
            this.productSearch = '';
            this.error = null;
        },

        async fetchProducts() {
            if (!this.selectedCatalog) return;
            this.loadingProducts = true;
            this.error = null;

            try {
                const token = localStorage.getItem('token');
                const response = await fetch(
                    `${this.API_BASE_URL}/catalog/${this.selectedCatalog.id}/products?limit=30`,
                    {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json',
                        },
                    }
                );

                const data = await response.json();

                if (response.status === 401) {
                    window.location.href = '/login';
                    return;
                }

                if (data.success) {
                    this.products = data.data.products || [];
                    this.pagingCursor = data.data.paging?.cursors?.after || null;
                } else {
                    this.error = {
                        code: data.error_code || 'UNKNOWN',
                        message: data.message || 'Gagal memuat produk.',
                    };
                }
            } catch (e) {
                console.error('fetchProducts error:', e);
                this.error = { code: 'NETWORK_ERROR', message: 'Gagal terhubung ke server.' };
            } finally {
                this.loadingProducts = false;
            }
        },

        async loadMoreProducts() {
            if (!this.pagingCursor || !this.selectedCatalog) return;
            this.loadingMore = true;

            try {
                const token = localStorage.getItem('token');
                const response = await fetch(
                    `${this.API_BASE_URL}/catalog/${this.selectedCatalog.id}/products?limit=30&after=${encodeURIComponent(this.pagingCursor)}`,
                    {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json',
                        },
                    }
                );

                const data = await response.json();

                if (data.success) {
                    this.products = [...this.products, ...(data.data.products || [])];
                    this.pagingCursor = data.data.paging?.cursors?.after || null;
                }
            } catch (e) {
                console.error('loadMoreProducts error:', e);
            } finally {
                this.loadingMore = false;
            }
        },

        formatPrice(price, currency) {
            if (!price) return '–';
            try {
                const num = parseFloat(price);
                if (isNaN(num)) return price;
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: currency || 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(num);
            } catch (e) {
                return price + (currency ? ' ' + currency : '');
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
        formatNotificationTime() { return ''; },
    };
}
</script>
@endsection
