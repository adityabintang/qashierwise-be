@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Meta Catalog - QashierWise')

@push('scripts')
<script>
    window.fbAsyncInit = function() {
        if (window.__fbSdkReady) return;
        window.__fbSdkReady = true;
        window.dispatchEvent(new Event('fb-sdk-ready'));
    };
    (function(d, s, id) {
        if (d.getElementById(id)) { window.fbAsyncInit(); return; }
        var js = d.createElement(s); js.id = id;
        js.src = 'https://connect.facebook.net/en_US/sdk.js';
        js.async = true; js.defer = true;
        d.head.appendChild(js);
    }(document, 'script', 'facebook-jssdk'));
</script>
@endpush

@section('content')
<div x-data="metaCatalogApp()" x-init="init()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'meta-catalog'])

    <div class="flex-1 flex flex-col overflow-y-auto">
        @include('components.dashboard-header', ['title' => 'Meta Catalog', 'description' => 'Produk dari katalog Meta Business Anda'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-5">

                <!-- ── Page Header ── -->
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div class="min-w-0">
                        <template x-if="!selectedCatalog">
                            <div>
                                <h2 class="text-lg font-semibold flex items-center gap-2 flex-wrap">
                                    Katalog Meta Anda
                                    <span x-show="!loading && catalogs.length > 0"
                                          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-[hsl(var(--primary)/0.1)] text-[hsl(var(--primary))]"
                                          x-text="catalogs.length + ' katalog'"></span>
                                </h2>
                                <p x-show="businessId" class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5 font-mono">
                                    Business ID: <span x-text="businessId"></span>
                                </p>
                            </div>
                        </template>
                        <template x-if="selectedCatalog">
                            <div>
                                <div class="flex items-center gap-1.5 text-xs text-[hsl(var(--muted-foreground))] mb-1">
                                    <button @click="backToCatalogs()" class="hover:text-[hsl(var(--foreground))] transition-colors">Katalog</button>
                                    <i class="fas fa-chevron-right text-[9px] opacity-50"></i>
                                    <span class="text-[hsl(var(--foreground))] font-medium truncate max-w-[200px]" x-text="selectedCatalog.name"></span>
                                </div>
                                <h2 class="text-lg font-semibold truncate" x-text="selectedCatalog.name"></h2>
                                <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono mt-0.5">ID: <span x-text="selectedCatalog.id"></span></p>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <template x-if="selectedCatalog">
                            <button @click="backToCatalogs()" class="btn btn-outline btn-sm">
                                <i class="fas fa-arrow-left mr-1.5"></i>Kembali
                            </button>
                        </template>
                        <template x-if="!selectedCatalog && !(error && error.code === 'WHATSAPP_NOT_CONNECTED')">
                            <button @click="launchSignup()" :disabled="signingUp || loading" class="btn btn-outline btn-sm">
                                <template x-if="signingUp">
                                    <span><i class="fas fa-spinner animate-spin mr-1.5"></i>Menghubungkan...</span>
                                </template>
                                <template x-if="!signingUp">
                                    <span><i class="fas fa-sync-alt mr-1.5"></i>Muat Ulang</span>
                                </template>
                            </button>
                        </template>
                        <template x-if="!selectedCatalog && businessId && !(error && error.code === 'WHATSAPP_NOT_CONNECTED')">
                            <a :href="`https://web.facebook.com/products/catalogs/new/?business_id=${businessId}&nav_source=commerce_manager_launchpad`"
                               target="_blank" rel="noopener"
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-plus mr-1.5"></i>Buat Katalog
                            </a>
                        </template>
                        <template x-if="selectedCatalog">
                            <button @click="openCreateModal()" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus mr-1.5"></i>Tambah Produk
                            </button>
                        </template>
                    </div>
                </div>

                <!-- ── Generic Error ── -->
                <template x-if="error && error.code !== 'WHATSAPP_NOT_CONNECTED'">
                    <div class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950/20 p-4 flex gap-3 items-start">
                        <i class="fas fa-circle-exclamation text-red-500 mt-0.5 flex-shrink-0"></i>
                        <p class="text-sm text-red-800 dark:text-red-300" x-text="error.message"></p>
                    </div>
                </template>

                <!-- ── Toast ── -->
                <div class="fixed bottom-5 right-5 z-50 pointer-events-none">
                    <div x-show="toast"
                         x-transition:enter="transition ease-out duration-250"
                         x-transition:enter-start="opacity-0 translate-y-3"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-3"
                         class="pointer-events-auto rounded-xl shadow-lg border px-4 py-3 flex items-center gap-3 min-w-[260px] max-w-sm bg-[hsl(var(--card))]"
                         :class="toast?.type === 'success' ? 'border-green-200 dark:border-green-900' : 'border-red-200 dark:border-red-900'">
                        <div class="flex-shrink-0">
                            <template x-if="toast?.type === 'success'">
                                <div class="h-7 w-7 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                                    <i class="fas fa-check text-green-600 text-xs"></i>
                                </div>
                            </template>
                            <template x-if="toast?.type !== 'success'">
                                <div class="h-7 w-7 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                                    <i class="fas fa-times text-red-500 text-xs"></i>
                                </div>
                            </template>
                        </div>
                        <span class="text-sm font-medium flex-1" x-text="toast?.message"></span>
                        <button @click="toast = null" class="text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors flex-shrink-0 ml-1">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- ════════ WHATSAPP NOT CONNECTED STATE ════════ -->
                <template x-if="!selectedCatalog && error && error.code === 'WHATSAPP_NOT_CONNECTED'">
                    <div class="flex items-center justify-center py-16">
                        <div class="card max-w-md w-full p-8 text-center shadow-sm">
                            <!-- Icon -->
                            <div class="flex items-center justify-center mb-6">
                                <div class="relative">
                                    <div class="h-20 w-20 rounded-2xl flex items-center justify-center"
                                         style="background:rgba(37,211,102,0.12)">
                                        <i class="fab fa-whatsapp text-4xl" style="color:#25d366"></i>
                                    </div>
                                    <div class="absolute -bottom-1 -right-1 h-6 w-6 rounded-full bg-[hsl(var(--card))] flex items-center justify-center border-2 border-[hsl(var(--border))]">
                                        <i class="fas fa-link-slash text-[10px] text-[hsl(var(--muted-foreground))]"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Title & desc -->
                            <h2 class="text-xl font-bold mb-2">WhatsApp Belum Terhubung</h2>
                            <p class="text-sm text-[hsl(var(--muted-foreground))] leading-relaxed mb-8">
                                Untuk mengakses dan mengelola katalog produk Meta, Anda perlu menghubungkan akun WhatsApp Business terlebih dahulu.
                            </p>

                            <!-- Steps -->
                            <div class="flex items-start gap-3 text-left mb-8">
                                <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                    <div class="h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:#25d366">1</div>
                                    <div class="w-px flex-1 mt-1" style="background:rgba(37,211,102,0.25); min-height:28px"></div>
                                </div>
                                <div class="pb-7">
                                    <p class="text-sm font-medium">Hubungkan WhatsApp Business</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">Masuk ke pengaturan akun dan ikuti proses Embedded Signup.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-left mb-8 -mt-8">
                                <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                    <div class="h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:#25d366">2</div>
                                    <div class="w-px flex-1 mt-1" style="background:rgba(37,211,102,0.25); min-height:28px"></div>
                                </div>
                                <div class="pb-7">
                                    <p class="text-sm font-medium">Pilih katalog saat signup</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">Centang katalog yang ingin Anda kelola di popup Meta.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-left -mt-8">
                                <div class="flex flex-col items-center flex-shrink-0 mt-0.5">
                                    <div class="h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:#25d366">3</div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Kelola produk dari sini</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">Tambah, edit, dan hapus produk langsung dari dashboard.</p>
                                </div>
                            </div>

                            <!-- CTA -->
                            <a href="/dashboard/whatsapp-account"
                               class="mt-8 flex items-center justify-center gap-2 w-full py-3 px-6 rounded-xl font-semibold text-sm text-white transition-opacity hover:opacity-90"
                               style="background:#25d366">
                                <i class="fab fa-whatsapp text-base"></i>
                                Hubungkan WhatsApp Sekarang
                            </a>
                        </div>
                    </div>
                </template>

                <!-- ════════ CATALOG LIST VIEW ════════ -->
                <template x-if="!selectedCatalog && !(error && error.code === 'WHATSAPP_NOT_CONNECTED')">
                    <div>
                        <!-- Skeleton loading -->
                        <template x-if="loading">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <template x-for="i in 3" :key="'skel-'+i">
                                    <div class="card p-5 space-y-4">
                                        <div class="flex items-center gap-3">
                                            <div class="skeleton h-10 w-10 rounded-xl flex-shrink-0"></div>
                                            <div class="space-y-2 flex-1">
                                                <div class="skeleton h-4 w-3/4"></div>
                                                <div class="skeleton h-3 w-1/2"></div>
                                            </div>
                                        </div>
                                        <div class="skeleton h-3 w-1/4 rounded-full"></div>
                                        <div class="skeleton h-9 w-full rounded-lg"></div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Empty state -->
                        <template x-if="!loading && !error && catalogs.length === 0">
                            <div class="card p-12 text-center">
                                <div class="h-14 w-14 rounded-2xl bg-[hsl(var(--muted))] flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-store text-2xl text-[hsl(var(--muted-foreground))]"></i>
                                </div>
                                <h3 class="font-semibold text-base mb-1">Tidak Ada Katalog</h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))] max-w-xs mx-auto">Pastikan akun Meta Business Anda memiliki katalog produk yang aktif.</p>
                            </div>
                        </template>

                        <!-- Catalog grid -->
                        <template x-if="!loading && catalogs.length > 0">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <template x-for="catalog in catalogs" :key="catalog.id">
                                    <div class="card p-5 hover:shadow-md hover:border-[hsl(var(--primary)/0.25)] transition-all flex flex-col gap-4 cursor-pointer group"
                                         @click="selectCatalog(catalog)">
                                        <div class="flex items-start gap-3">
                                            <div class="h-10 w-10 rounded-xl bg-[#1877f2]/10 flex items-center justify-center flex-shrink-0">
                                                <i class="fab fa-facebook text-[#1877f2] text-lg"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-semibold text-sm leading-tight truncate group-hover:text-[hsl(var(--primary))] transition-colors"
                                                    x-text="catalog.name"></h3>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono mt-0.5 truncate"
                                                   x-text="'ID: ' + catalog.id"></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]">
                                                <i class="fas fa-box text-[10px]"></i>
                                                <span x-text="(catalog.product_count ?? '–') + ' produk'"></span>
                                            </span>
                                            <template x-if="catalog.vertical">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs border border-[hsl(var(--border))] text-[hsl(var(--muted-foreground))] capitalize"
                                                      x-text="catalog.vertical.toLowerCase().replace(/_/g, ' ')"></span>
                                            </template>
                                        </div>
                                        <button class="btn btn-primary btn-sm w-full mt-auto" @click.stop="selectCatalog(catalog)">
                                            <i class="fas fa-eye mr-1.5"></i>Lihat Produk
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- ════════ PRODUCT LIST VIEW ════════ -->
                <template x-if="selectedCatalog">
                    <div class="space-y-4">
                        <!-- Search bar + count -->
                        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                            <div class="relative flex-1 w-full">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none"></i>
                                <input type="text" x-model="productSearch"
                                       placeholder="Cari produk berdasarkan nama atau SKU..."
                                       class="input w-full !pl-9 min-h-[40px]">
                            </div>
                            <span x-show="!loadingProducts && products.length > 0"
                                  class="text-xs text-[hsl(var(--muted-foreground))] whitespace-nowrap flex-shrink-0 tabular-nums">
                                <span x-text="filteredProducts.length"></span> / <span x-text="products.length"></span> produk
                            </span>
                        </div>

                        <!-- Loading skeleton -->
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

                        <!-- Empty state -->
                        <template x-if="!loadingProducts && filteredProducts.length === 0">
                            <div class="card p-12 text-center">
                                <div class="h-14 w-14 rounded-2xl bg-[hsl(var(--muted))] flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-box-open text-2xl text-[hsl(var(--muted-foreground))]"></i>
                                </div>
                                <h3 class="font-semibold text-base mb-1"
                                    x-text="productSearch ? 'Tidak Ada Hasil' : 'Katalog Kosong'"></h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))] max-w-xs mx-auto"
                                   x-text="productSearch
                                       ? 'Tidak ada produk yang cocok dengan \'' + productSearch + '\''
                                       : 'Katalog ini belum memiliki produk. Klik Tambah Produk untuk memulai.'"></p>
                            </div>
                        </template>

                        <!-- Products grid -->
                        <template x-if="!loadingProducts && filteredProducts.length > 0">
                            <div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    <template x-for="product in filteredProducts" :key="product.id">
                                        <div class="card overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                                            <!-- Gambar -->
                                            <div class="h-44 bg-[hsl(var(--muted))] flex items-center justify-center overflow-hidden relative group">
                                                <template x-if="product.image_url">
                                                    <img :src="product.image_url" :alt="product.name"
                                                         class="w-full h-full object-cover" loading="lazy">
                                                </template>
                                                <template x-if="!product.image_url">
                                                    <i class="fas fa-image text-3xl text-[hsl(var(--muted-foreground)/0.4)]"></i>
                                                </template>
                                                <!-- Hover actions (desktop) -->
                                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                    <button @click.stop="openEditModal(product)"
                                                        class="btn btn-sm bg-white text-gray-800 hover:bg-gray-100 shadow-sm">
                                                        <i class="fas fa-pencil mr-1"></i>Edit
                                                    </button>
                                                    <button @click.stop="confirmDelete(product)"
                                                        class="btn btn-sm bg-red-500 text-white hover:bg-red-600 shadow-sm">
                                                        <i class="fas fa-trash mr-1"></i>Hapus
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Info -->
                                            <div class="p-4 flex flex-col gap-1.5 flex-1">
                                                <h3 class="font-semibold text-sm leading-snug line-clamp-2" x-text="product.name"></h3>
                                                <template x-if="product.description">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))] line-clamp-2" x-text="product.description"></p>
                                                </template>
                                                <template x-if="product.retailer_id">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono truncate" x-text="'SKU: ' + product.retailer_id"></p>
                                                </template>
                                                <!-- Harga + availability -->
                                                <div class="flex items-center justify-between mt-auto pt-2 border-t border-[hsl(var(--border))]">
                                                    <span class="font-bold text-[hsl(var(--primary))] text-sm"
                                                          x-text="formatPrice(product.price, product.currency)"></span>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                                          :style="product.availability === 'in stock'
                                                              ? 'background:rgba(34,197,94,0.15);color:rgb(22,163,74)'
                                                              : product.availability === 'out of stock'
                                                              ? 'background:rgba(239,68,68,0.15);color:rgb(220,38,38)'
                                                              : product.availability === 'preorder'
                                                              ? 'background:rgba(59,130,246,0.15);color:rgb(37,99,235)'
                                                              : 'background:rgba(100,116,139,0.15);color:rgb(100,116,139)'">
                                                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                                              :style="product.availability === 'in stock'
                                                                  ? 'background:rgb(22,163,74)'
                                                                  : product.availability === 'out of stock'
                                                                  ? 'background:rgb(220,38,38)'
                                                                  : product.availability === 'preorder'
                                                                  ? 'background:rgb(37,99,235)'
                                                                  : 'background:rgb(100,116,139)'"></span>
                                                        <span x-text="product.availability === 'in stock' ? 'Tersedia' :
                                                                      product.availability === 'out of stock' ? 'Habis' :
                                                                      product.availability === 'preorder' ? 'Pre-order' :
                                                                      (product.availability ?? '–')"></span>
                                                    </span>
                                                </div>
                                                <!-- Mobile actions -->
                                                <div class="flex gap-2 mt-2 sm:hidden">
                                                    <button @click="openEditModal(product)" class="btn btn-outline btn-sm flex-1">
                                                        <i class="fas fa-pencil mr-1"></i>Edit
                                                    </button>
                                                    <button @click="confirmDelete(product)" class="btn btn-sm flex-1 bg-red-500 text-white hover:bg-red-600 border-0">
                                                        <i class="fas fa-trash mr-1"></i>Hapus
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Load more -->
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

    <!-- ════════ CREATE MODAL ════════ -->
    <template x-if="showCreateModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showCreateModal = false"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-[hsl(var(--card))] flex items-center justify-between px-5 py-4 border-b border-[hsl(var(--border))] z-10">
                    <div class="flex items-center gap-2">
                        <div class="h-7 w-7 rounded-lg bg-[hsl(var(--primary)/0.1)] flex items-center justify-center">
                            <i class="fas fa-plus text-[hsl(var(--primary))] text-xs"></i>
                        </div>
                        <h3 class="font-semibold">Tambah Produk Baru</h3>
                    </div>
                    <button @click="showCreateModal = false" class="btn btn-ghost btn-sm p-1.5 rounded-lg">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form @submit.prevent="createProduct()" class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Nama Produk <span class="text-red-500">*</span></label>
                        <input type="text" x-model="createForm.name" class="input w-full" placeholder="Nama produk" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Ketersediaan</label>
                        <select x-model="createForm.availability" class="input w-full">
                            <option value="in stock">Tersedia</option>
                            <option value="out of stock">Habis</option>
                            <option value="preorder">Pre-order</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Harga <span class="text-red-500">*</span></label>
                            <input type="number" x-model="createForm.price_display" class="input w-full" placeholder="30000" min="0" required>
                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Dalam Rupiah</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Mata Uang</label>
                            <select x-model="createForm.currency" class="input w-full">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="SGD">SGD</option>
                                <option value="MYR">MYR</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Gambar Produk <span class="text-red-500">*</span></label>

                        <!-- Preview setelah upload/URL diisi -->
                        <template x-if="createForm.image_url && !uploadingCreateImage">
                            <div class="relative mb-2 rounded-lg overflow-hidden border border-[hsl(var(--border))] h-36 bg-[hsl(var(--muted))]">
                                <img :src="createForm.image_url" class="w-full h-full object-cover" x-on:error="$el.style.display='none'">
                                <button type="button" @click="createForm.image_url = ''"
                                    class="absolute top-2 right-2 h-6 w-6 rounded-full bg-black/60 text-white flex items-center justify-center hover:bg-black/80 transition-colors">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                        </template>

                        <!-- State: mengupload -->
                        <template x-if="uploadingCreateImage">
                            <div class="mb-2 rounded-lg border border-[hsl(var(--border))] h-36 bg-[hsl(var(--muted))] flex flex-col items-center justify-center gap-2">
                                <i class="fas fa-spinner animate-spin text-[hsl(var(--primary))]"></i>
                                <span class="text-xs text-[hsl(var(--muted-foreground))]">Mengupload gambar...</span>
                            </div>
                        </template>

                        <!-- Dropzone (saat belum ada gambar) -->
                        <template x-if="!createForm.image_url && !uploadingCreateImage">
                            <label class="mb-2 flex flex-col items-center justify-center h-36 rounded-lg border-2 border-dashed border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.4)] cursor-pointer hover:border-[hsl(var(--primary)/0.4)] hover:bg-[hsl(var(--primary)/0.03)] transition-colors"
                                   x-on:dragover.prevent
                                   x-on:drop.prevent="if($event.dataTransfer.files[0]) uploadImageFile($event.dataTransfer.files[0], 'create')">
                                <i class="fas fa-cloud-upload-alt text-2xl text-[hsl(var(--muted-foreground))] mb-2"></i>
                                <span class="text-xs font-medium text-[hsl(var(--muted-foreground))]">Klik atau seret gambar ke sini</span>
                                <span class="text-[10px] text-[hsl(var(--muted-foreground)/0.6)] mt-0.5">JPG, PNG, GIF, WebP — maks. 5MB</span>
                                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden"
                                       x-on:change="if($event.target.files[0]) uploadImageFile($event.target.files[0], 'create')">
                            </label>
                        </template>

                        <!-- Validasi: gambar wajib -->
                        <template x-if="!createForm.image_url && !uploadingCreateImage && createImageTouched">
                            <p class="text-xs text-red-500 mt-1"><i class="fas fa-circle-exclamation mr-1"></i>Gambar produk wajib diupload.</p>
                        </template>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Deskripsi</label>
                        <textarea x-model="createForm.description"
                            x-init="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                            x-on:input="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                            class="input w-full resize-none overflow-hidden" rows="3" placeholder="Deskripsi produk..."></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Brand</label>
                            <input type="text" x-model="createForm.brand" class="input w-full" placeholder="Nama brand">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Kondisi</label>
                            <select x-model="createForm.condition" class="input w-full">
                                <option value="new">Baru</option>
                                <option value="refurbished">Refurbished</option>
                                <option value="used">Bekas</option>
                            </select>
                        </div>
                    </div>
                    <template x-if="createError">
                        <div class="flex gap-2 items-start rounded-lg bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-3">
                            <i class="fas fa-circle-exclamation text-red-500 text-sm mt-0.5 flex-shrink-0"></i>
                            <p class="text-sm text-red-700 dark:text-red-400" x-text="createError"></p>
                        </div>
                    </template>
                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="showCreateModal = false" class="btn btn-outline flex-1">Batal</button>
                        <button type="submit" :disabled="creating" class="btn btn-primary flex-1">
                            <template x-if="creating"><span><i class="fas fa-spinner animate-spin mr-2"></i>Menyimpan...</span></template>
                            <template x-if="!creating"><span><i class="fas fa-plus mr-2"></i>Tambah Produk</span></template>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- ════════ EDIT MODAL ════════ -->
    <template x-if="showEditModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showEditModal = false"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-[hsl(var(--card))] flex items-center justify-between px-5 py-4 border-b border-[hsl(var(--border))] z-10">
                    <div class="flex items-center gap-2">
                        <div class="h-7 w-7 rounded-lg bg-[hsl(var(--primary)/0.1)] flex items-center justify-center">
                            <i class="fas fa-pencil text-[hsl(var(--primary))] text-xs"></i>
                        </div>
                        <h3 class="font-semibold">Edit Produk</h3>
                    </div>
                    <button @click="showEditModal = false" class="btn btn-ghost btn-sm p-1.5 rounded-lg">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form @submit.prevent="updateProduct()" class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Nama Produk</label>
                        <input type="text" x-model="editForm.name" class="input w-full" placeholder="Nama produk">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Ketersediaan</label>
                            <select x-model="editForm.availability" class="input w-full">
                                <option value="in stock">Tersedia</option>
                                <option value="out of stock">Habis</option>
                                <option value="preorder">Pre-order</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Kondisi</label>
                            <select x-model="editForm.condition" class="input w-full">
                                <option value="new">Baru</option>
                                <option value="refurbished">Refurbished</option>
                                <option value="used">Bekas</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Harga</label>
                            <input type="number" x-model="editForm.price_display" class="input w-full" placeholder="30000" min="0">
                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Dalam Rupiah</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Mata Uang</label>
                            <select x-model="editForm.currency" class="input w-full">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="SGD">SGD</option>
                                <option value="MYR">MYR</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Gambar Produk</label>

                        <!-- Preview -->
                        <template x-if="editForm.image_url && !uploadingEditImage">
                            <div class="relative mb-2 rounded-lg overflow-hidden border border-[hsl(var(--border))] h-36 bg-[hsl(var(--muted))]">
                                <img :src="editForm.image_url" class="w-full h-full object-cover" x-on:error="$el.style.display='none'">
                                <button type="button" @click="editForm.image_url = ''"
                                    class="absolute top-2 right-2 h-6 w-6 rounded-full bg-black/60 text-white flex items-center justify-center hover:bg-black/80 transition-colors">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                        </template>

                        <!-- State: mengupload -->
                        <template x-if="uploadingEditImage">
                            <div class="mb-2 rounded-lg border border-[hsl(var(--border))] h-36 bg-[hsl(var(--muted))] flex flex-col items-center justify-center gap-2">
                                <i class="fas fa-spinner animate-spin text-[hsl(var(--primary))]"></i>
                                <span class="text-xs text-[hsl(var(--muted-foreground))]">Mengupload gambar...</span>
                            </div>
                        </template>

                        <!-- Dropzone (saat belum ada gambar) -->
                        <template x-if="!editForm.image_url && !uploadingEditImage">
                            <label class="mb-2 flex flex-col items-center justify-center h-36 rounded-lg border-2 border-dashed border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.4)] cursor-pointer hover:border-[hsl(var(--primary)/0.4)] hover:bg-[hsl(var(--primary)/0.03)] transition-colors"
                                   x-on:dragover.prevent
                                   x-on:drop.prevent="if($event.dataTransfer.files[0]) uploadImageFile($event.dataTransfer.files[0], 'edit')">
                                <i class="fas fa-cloud-upload-alt text-2xl text-[hsl(var(--muted-foreground))] mb-2"></i>
                                <span class="text-xs font-medium text-[hsl(var(--muted-foreground))]">Klik atau seret gambar ke sini</span>
                                <span class="text-[10px] text-[hsl(var(--muted-foreground)/0.6)] mt-0.5">JPG, PNG, GIF, WebP — maks. 5MB</span>
                                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden"
                                       x-on:change="if($event.target.files[0]) uploadImageFile($event.target.files[0], 'edit')">
                            </label>
                        </template>

                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Deskripsi</label>
                        <textarea x-model="editForm.description"
                            x-init="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                            x-on:input="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                            class="input w-full resize-none overflow-hidden" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Brand</label>
                        <input type="text" x-model="editForm.brand" class="input w-full">
                    </div>
                    <template x-if="editError">
                        <div class="flex gap-2 items-start rounded-lg bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-3">
                            <i class="fas fa-circle-exclamation text-red-500 text-sm mt-0.5 flex-shrink-0"></i>
                            <p class="text-sm text-red-700 dark:text-red-400" x-text="editError"></p>
                        </div>
                    </template>
                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="showEditModal = false" class="btn btn-outline flex-1">Batal</button>
                        <button type="submit" :disabled="editing" class="btn btn-primary flex-1">
                            <template x-if="editing"><span><i class="fas fa-spinner animate-spin mr-2"></i>Menyimpan...</span></template>
                            <template x-if="!editing"><span><i class="fas fa-save mr-2"></i>Simpan Perubahan</span></template>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- ════════ DELETE CONFIRM ════════ -->
    <template x-if="showDeleteConfirm">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showDeleteConfirm = false"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl w-full max-w-sm p-6 space-y-4">
                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-trash text-red-500"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold">Hapus Produk</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">
                            Yakin ingin menghapus <strong x-text="deleteTarget?.name"></strong>?
                            Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button @click="showDeleteConfirm = false" class="btn btn-outline flex-1">Batal</button>
                    <button @click="deleteProduct()" :disabled="deleting" class="btn flex-1 bg-red-500 text-white hover:bg-red-600 border-0">
                        <template x-if="deleting"><span><i class="fas fa-spinner animate-spin mr-2"></i>Menghapus...</span></template>
                        <template x-if="!deleting"><span><i class="fas fa-trash mr-2"></i>Ya, Hapus</span></template>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ════════ CREATE CATALOG MODAL ════════ -->
    <template x-if="showCreateCatalogModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showCreateCatalogModal = false"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl w-full max-w-md">
                <div class="flex items-center justify-between px-5 py-4 border-b border-[hsl(var(--border))]">
                    <div class="flex items-center gap-2">
                        <div class="h-7 w-7 rounded-lg bg-[#1877f2]/10 flex items-center justify-center">
                            <i class="fab fa-facebook text-[#1877f2] text-xs"></i>
                        </div>
                        <h3 class="font-semibold">Buat Katalog Baru</h3>
                    </div>
                    <button @click="showCreateCatalogModal = false" class="btn btn-ghost btn-sm p-1.5 rounded-lg">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form @submit.prevent="submitCreateCatalog()" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Nama Katalog <span class="text-red-500">*</span></label>
                        <input type="text" x-model="createCatalogForm.name" class="input w-full" placeholder="Contoh: Katalog Produk Utama" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide mb-1.5">Jenis Katalog</label>
                        <select x-model="createCatalogForm.vertical" class="input w-full">
                            <option value="commerce">E-Commerce (Produk Umum)</option>
                            <option value="destinations">Destinasi</option>
                            <option value="flights">Penerbangan</option>
                            <option value="home_listings">Properti</option>
                            <option value="hotels">Hotel</option>
                            <option value="vehicles">Kendaraan</option>
                        </select>
                    </div>
                    <template x-if="createCatalogError">
                        <div class="flex gap-2 items-start rounded-lg bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-3">
                            <i class="fas fa-circle-exclamation text-red-500 text-sm mt-0.5 flex-shrink-0"></i>
                            <p class="text-sm text-red-700 dark:text-red-400" x-text="createCatalogError"></p>
                        </div>
                    </template>
                    <div class="flex gap-3 pt-1">
                        <button type="button" @click="showCreateCatalogModal = false" class="btn btn-outline flex-1">Batal</button>
                        <button type="submit" :disabled="creatingCatalog" class="btn btn-primary flex-1">
                            <template x-if="creatingCatalog"><span><i class="fas fa-spinner animate-spin mr-2"></i>Membuat...</span></template>
                            <template x-if="!creatingCatalog"><span><i class="fas fa-plus mr-2"></i>Buat Katalog</span></template>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>

<script>
function metaCatalogApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/whatsapp',
        initialCatalogId: @json($initialCatalogId ?? null),

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
        toast: null,

        // Create product
        showCreateModal: false,
        creating: false,
        createError: null,
        createForm: {},

        // Edit product
        showEditModal: false,
        editing: false,
        editError: null,
        editForm: {},
        editingProductId: null,

        // Delete product
        showDeleteConfirm: false,
        deleting: false,
        deleteTarget: null,

        // Image upload
        uploadingCreateImage: false,
        uploadingEditImage: false,
        createImageTouched: false,

        // Create catalog
        showCreateCatalogModal: false,
        creatingCatalog: false,
        createCatalogForm: { name: '', vertical: 'commerce' },
        createCatalogError: null,

        // Embedded signup (Muat Ulang)
        signingUp: false,
        sdkLoaded: false,
        signupConfig: null,

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
            this.loadSignupConfig();
            this.setupSignupMessageListener();
            await this.fetchCatalogs();

            if (this.initialCatalogId && this.catalogs.length > 0) {
                const found = this.catalogs.find(c => c.id === String(this.initialCatalogId));
                if (found) {
                    await this.selectCatalog(found, false);
                } else {
                    this.selectedCatalog = { id: String(this.initialCatalogId), name: 'Katalog ' + this.initialCatalogId };
                    await this.fetchProducts();
                }
            }
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
            window.addEventListener('popstate', () => {
                const path = window.location.pathname;
                const match = path.match(/\/dashboard\/meta-catalog\/(.+)/);
                if (match) {
                    const found = this.catalogs.find(c => c.id === match[1]);
                    if (found) this.selectCatalog(found, false);
                } else {
                    this.selectedCatalog = null;
                    this.products = [];
                }
            });
            let storedUser = localStorage.getItem('user');
            if (storedUser) {
                try { this.user = JSON.parse(storedUser); } catch(e) { this.user = { name: 'User', email: '' }; }
            } else {
                this.user = { name: 'User', email: '' };
            }
        },

        // ─── EMBEDDED SIGNUP (MUAT ULANG) ────────────────────
        async loadSignupConfig() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/embedded-signup/config`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) {
                    this.signupConfig = data.data;
                    const initFB = () => {
                        FB.init({ appId: data.data.app_id, version: data.data.api_version || 'v22.0', xfbml: false, cookie: true });
                        this.sdkLoaded = true;
                    };
                    if (window.FB) {
                        initFB();
                    } else {
                        window.addEventListener('fb-sdk-ready', initFB, { once: true });
                    }
                }
            } catch(e) {}
        },

        setupSignupMessageListener() {
            window.addEventListener('message', (event) => {
                if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
                try {
                    const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                    if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'FINISH') {
                        const { phone_number_id, waba_id, business_id } = data.data || {};
                        this._pendingSessionInfo = { phone_number_id, waba_id, business_id };
                    }
                } catch(e) {}
            });
        },

        launchSignup() {
            if (!this.sdkLoaded || !this.signupConfig) {
                this.showToast('Konfigurasi signup belum siap, coba lagi.', 'error');
                return;
            }
            this._pendingSessionInfo = null;
            FB.login((response) => {
                if (response.authResponse?.code) {
                    this.signingUp = true;
                    this.sendCodeToBackend(response.authResponse.code, this._pendingSessionInfo || {});
                } else if (response.status === 'not_authorized' || response.status === 'unknown') {
                    // user cancelled — do nothing
                }
            }, {
                config_id: this.signupConfig.config_id,
                response_type: 'code',
                override_default_response_type: true,
                extras: { sessionInfoVersion: 3 },
            });
        },

        async sendCodeToBackend(code, sessionInfo) {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/embedded-signup/callback`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code, session_info: sessionInfo }),
                });
                const data = await res.json();
                if (data.success) {
                    this.showToast('Akun berhasil diperbarui. Memuat katalog...', 'success');
                    await this.fetchCatalogs();
                } else {
                    this.showToast(data.message || 'Gagal memperbarui akun.', 'error');
                }
            } catch(e) {
                this.showToast('Gagal terhubung ke server.', 'error');
            } finally {
                this.signingUp = false;
            }
        },

        async fetchCatalogs() {
            this.loading = true;
            this.error = null;
            this.catalogs = [];
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/catalog/catalogs`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (response.status === 401) { window.location.href = '/login'; return; }
                if (data.success) {
                    this.catalogs = data.data.catalogs || [];
                    this.businessId = data.data.business_id || null;
                } else {
                    this.error = { code: data.error_code || 'UNKNOWN', message: data.message || 'Gagal memuat katalog.' };
                }
            } catch(e) {
                this.error = { code: 'NETWORK_ERROR', message: 'Gagal terhubung ke server.' };
            } finally {
                this.loading = false;
            }
        },

        async selectCatalog(catalog, pushState = true) {
            this.selectedCatalog = catalog;
            this.products = [];
            this.pagingCursor = null;
            this.productSearch = '';
            if (pushState) {
                history.pushState({}, '', `/dashboard/meta-catalog/${catalog.id}`);
            }
            await this.fetchProducts();
        },

        backToCatalogs() {
            this.selectedCatalog = null;
            this.products = [];
            this.pagingCursor = null;
            this.productSearch = '';
            this.error = null;
            history.pushState({}, '', '/dashboard/meta-catalog');
        },

        async fetchProducts() {
            if (!this.selectedCatalog) return;
            this.loadingProducts = true;
            this.error = null;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(
                    `${this.API_BASE_URL}/catalog/${this.selectedCatalog.id}/products?limit=30`,
                    { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } }
                );
                const data = await response.json();
                if (response.status === 401) { window.location.href = '/login'; return; }
                if (data.success) {
                    this.products = data.data.products || [];
                    this.pagingCursor = data.data.paging?.cursors?.after || null;
                } else {
                    this.error = { code: data.error_code || 'UNKNOWN', message: data.message || 'Gagal memuat produk.' };
                }
            } catch(e) {
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
                    { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } }
                );
                const data = await response.json();
                if (data.success) {
                    this.products = [...this.products, ...(data.data.products || [])];
                    this.pagingCursor = data.data.paging?.cursors?.after || null;
                }
            } catch(e) {} finally {
                this.loadingMore = false;
            }
        },

        // ─── CREATE PRODUCT ───────────────────────────────────
        openCreateModal() {
            const ts = Date.now().toString(36).toUpperCase();
            const rand = Math.random().toString(36).slice(2, 6).toUpperCase();
            const catalogUrl = `https://qashierwise.com/dashboard/meta-catalog/${this.selectedCatalog?.id || ''}`;
            const defaultBrand = this.user?.name || '';
            const defaultDesc = 'Produk makanan & minuman pilihan, disiapkan dengan bahan segar berkualitas untuk pengalaman kuliner terbaik Anda.';
            this.createForm = {
                name: '', retailer_id: `SKU-${ts}-${rand}`, price_display: '', currency: 'IDR',
                image_url: '', url: catalogUrl, availability: 'in stock',
                description: defaultDesc, brand: defaultBrand, condition: 'new',
            };
            this.createError = null;
            this.createImageTouched = false;
            this.showCreateModal = true;
        },

        async createProduct() {
            this.createImageTouched = true;
            if (!this.createForm.image_url) {
                this.createError = 'Gambar produk wajib diupload terlebih dahulu.';
                return;
            }
            this.creating = true;
            this.createError = null;
            try {
                const token = localStorage.getItem('token');
                const NO_SUBUNIT = ['IDR', 'JPY', 'KRW', 'VND'];
                const multiplier = NO_SUBUNIT.includes(this.createForm.currency) ? 1 : 100;
                const payload = {
                    ...this.createForm,
                    price: Math.round(parseFloat(this.createForm.price_display || 0) * multiplier),
                };
                delete payload.price_display;

                const response = await fetch(
                    `${this.API_BASE_URL}/catalog/${this.selectedCatalog.id}/products`,
                    {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    }
                );
                const data = await response.json();
                if (data.success) {
                    this.showCreateModal = false;
                    this.showToast('Produk berhasil ditambahkan', 'success');
                    await this.fetchProducts();
                } else {
                    this.createError = data.message || 'Gagal menambahkan produk.';
                }
            } catch(e) {
                this.createError = 'Gagal terhubung ke server.';
            } finally {
                this.creating = false;
            }
        },

        // ─── EDIT PRODUCT ─────────────────────────────────────
        openEditModal(product) {
            this.editingProductId = product.id;
            const priceNum = product.price ? this.parseMetaPrice(product.price) : 0;
            this.editForm = {
                name: product.name || '',
                availability: product.availability || 'in stock',
                condition: product.condition || 'new',
                price_display: priceNum > 0 ? String(Math.round(priceNum)) : '',
                currency: product.currency || 'IDR',
                image_url: product.image_url || '',
                url: product.url || '',
                description: product.description || 'Produk makanan & minuman pilihan, disiapkan dengan bahan segar berkualitas untuk pengalaman kuliner terbaik Anda.',
                brand: product.brand || this.user?.name || '',
            };
            this.editError = null;
            this.showEditModal = true;
        },

        async updateProduct() {
            this.editing = true;
            this.editError = null;
            try {
                const token = localStorage.getItem('token');
                const payload = { ...this.editForm };
                if (payload.price_display) {
                    const NO_SUBUNIT = ['IDR', 'JPY', 'KRW', 'VND'];
                    const multiplier = NO_SUBUNIT.includes(payload.currency) ? 1 : 100;
                    payload.price = Math.round(parseFloat(payload.price_display) * multiplier);
                }
                delete payload.price_display;
                Object.keys(payload).forEach(k => { if (payload[k] === '') delete payload[k]; });

                const response = await fetch(
                    `${this.API_BASE_URL}/catalog/products/${this.editingProductId}`,
                    {
                        method: 'PUT',
                        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    }
                );
                const data = await response.json();
                if (data.success) {
                    this.showEditModal = false;
                    this.showToast('Produk berhasil diperbarui', 'success');
                    await this.fetchProducts();
                } else {
                    this.editError = data.message || 'Gagal memperbarui produk.';
                }
            } catch(e) {
                this.editError = 'Gagal terhubung ke server.';
            } finally {
                this.editing = false;
            }
        },

        // ─── DELETE PRODUCT ───────────────────────────────────
        confirmDelete(product) {
            this.deleteTarget = product;
            this.showDeleteConfirm = true;
        },

        async deleteProduct() {
            if (!this.deleteTarget) return;
            this.deleting = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(
                    `${this.API_BASE_URL}/catalog/products/${this.deleteTarget.id}`,
                    {
                        method: 'DELETE',
                        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
                    }
                );
                const data = await response.json();
                if (data.success) {
                    this.showDeleteConfirm = false;
                    this.products = this.products.filter(p => p.id !== this.deleteTarget.id);
                    this.showToast('Produk berhasil dihapus', 'success');
                    this.deleteTarget = null;
                } else {
                    this.showToast(data.message || 'Gagal menghapus produk.', 'error');
                    this.showDeleteConfirm = false;
                }
            } catch(e) {
                this.showToast('Gagal terhubung ke server.', 'error');
                this.showDeleteConfirm = false;
            } finally {
                this.deleting = false;
            }
        },

        // ─── CREATE CATALOG ───────────────────────────────────
        async submitCreateCatalog() {
            if (!this.createCatalogForm.name.trim()) {
                this.createCatalogError = 'Nama katalog wajib diisi.';
                return;
            }
            this.creatingCatalog = true;
            this.createCatalogError = null;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/catalog/catalogs`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.createCatalogForm),
                });
                const data = await res.json();
                if (data.success) {
                    this.showCreateCatalogModal = false;
                    this.showToast('Katalog berhasil dibuat.', 'success');
                    await this.fetchCatalogs();
                } else {
                    this.createCatalogError = data.message || 'Gagal membuat katalog.';
                }
            } catch (e) {
                this.createCatalogError = 'Gagal terhubung ke server.';
            } finally {
                this.creatingCatalog = false;
            }
        },

        // ─── IMAGE UPLOAD ─────────────────────────────────────
        async uploadImageFile(file, formKey) {
            if (!file || !this.selectedCatalog) return;

            const uploadingKey = formKey === 'create' ? 'uploadingCreateImage' : 'uploadingEditImage';
            this[uploadingKey] = true;

            try {
                const token = localStorage.getItem('token');
                const formData = new FormData();
                formData.append('image', file);

                const res = await fetch(`${this.API_BASE_URL}/catalog/${this.selectedCatalog.id}/upload-image`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await res.json();
                if (data.success) {
                    if (formKey === 'create') {
                        this.createForm.image_url = data.data.image_url;
                    } else {
                        this.editForm.image_url = data.data.image_url;
                    }
                    this.showToast('Gambar berhasil diupload.', 'success');
                } else {
                    this.showToast(data.message || 'Gagal mengupload gambar.', 'error');
                }
            } catch (e) {
                this.showToast('Gagal mengupload gambar.', 'error');
            } finally {
                this[uploadingKey] = false;
            }
        },

        // ─── HELPERS ──────────────────────────────────────────
        showToast(message, type = 'success') {
            this.toast = { message, type };
            setTimeout(() => { this.toast = null; }, 3000);
        },

        parseMetaPrice(price) {
            if (price === null || price === undefined || price === '') return 0;
            if (typeof price === 'number') return Math.round(price);
            const str = String(price).trim();
            const dotCount = (str.match(/\./g) || []).length;
            const commaCount = (str.match(/,/g) || []).length;
            if (dotCount > 1) {
                return parseInt(str.replace(/[^0-9]/g, ''), 10) || 0;
            }
            if (commaCount >= 1) {
                const lastComma = str.lastIndexOf(',');
                const lastDot = str.lastIndexOf('.');
                if (lastComma > lastDot) {
                    return Math.round(parseFloat(str.replace(/\./g, '').replace(',', '.')) || 0);
                }
                return Math.round(parseFloat(str.replace(/,/g, '')) || 0);
            }
            if (dotCount === 1) {
                const afterDot = str.slice(str.lastIndexOf('.') + 1).replace(/[^0-9]/g, '');
                if (afterDot.length === 3) {
                    return parseInt(str.replace(/[^0-9]/g, ''), 10) || 0;
                }
                return Math.round(parseFloat(str.replace(/[^0-9.]/g, '')) || 0);
            }
            return parseInt(str.replace(/[^0-9]/g, ''), 10) || 0;
        },

        formatPrice(price, currency) {
            if (!price) return '–';
            try {
                const num = this.parseMetaPrice(price);
                if (!num) return '–';
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: currency || 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(num);
            } catch(e) {
                return price;
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
