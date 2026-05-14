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
                        <h2 class="text-lg font-semibold" x-text="selectedCatalog ? selectedCatalog.name : 'Katalog Produk Meta'"></h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block"
                           x-text="selectedCatalog ? 'ID: ' + selectedCatalog.id : 'Kelola dan lihat produk dari Facebook Business Catalog'"></p>
                    </div>
                    <div class="flex gap-2">
                        <template x-if="selectedCatalog">
                            <button @click="openCreateModal()" class="btn btn-primary btn-md">
                                <i class="fas fa-plus mr-2"></i>Tambah Produk
                            </button>
                        </template>
                        <button @click="selectedCatalog ? backToCatalogs() : fetchCatalogs()" class="btn btn-outline btn-md">
                            <template x-if="selectedCatalog">
                                <span><i class="fas fa-arrow-left mr-2"></i>Kembali</span>
                            </template>
                            <template x-if="!selectedCatalog">
                                <span><i class="fas fa-sync-alt mr-2" :class="loading ? 'animate-spin' : ''"></i>Muat Ulang</span>
                            </template>
                        </button>
                    </div>
                </div>

                <!-- Permission Warning Banner -->
                <template x-if="error && error.code === 'PERMISSION_DENIED'">
                    <div class="card p-5 border border-yellow-300 bg-yellow-50 dark:bg-yellow-950/20">
                        <div class="flex gap-3">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-0.5 flex-shrink-0"></i>
                            <div class="space-y-2">
                                <h3 class="font-semibold text-yellow-800 dark:text-yellow-300">Izin Catalog Diperlukan</h3>
                                <p class="text-sm text-yellow-700 dark:text-yellow-400" x-text="error.message"></p>
                                <button @click="launchCatalogSignup()" class="btn btn-sm mt-2" style="background-color: #f59e0b; color: white;">
                                    <span><i class="fab fa-facebook mr-1"></i>Hubungkan Katalog</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Catalog Not Connected Warning -->
                <template x-if="error && error.code === 'CATALOG_NOT_CONNECTED'">
                    <div class="card p-5 border border-yellow-300 bg-yellow-50 dark:bg-yellow-950/20">
                        <div class="flex gap-3">
                            <i class="fas fa-store text-yellow-600 text-xl mt-0.5 flex-shrink-0"></i>
                            <div class="space-y-2">
                                <h3 class="font-semibold text-yellow-800 dark:text-yellow-300">Katalog Belum Terhubung</h3>
                                <p class="text-sm text-yellow-700 dark:text-yellow-400" x-text="error.message"></p>
                                <button @click="launchCatalogSignup()" class="btn btn-sm mt-2" style="background-color: #f59e0b; color: white;">
                                    <span><i class="fab fa-facebook mr-1"></i>Hubungkan Katalog</span>
                                </button>
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
                <template x-if="error && error.code !== 'PERMISSION_DENIED' && error.code !== 'WHATSAPP_NOT_CONNECTED' && error.code !== 'CATALOG_NOT_CONNECTED'">
                    <div class="card p-4 border border-red-300 bg-red-50 dark:bg-red-950/20">
                        <div class="flex gap-3 items-start">
                            <i class="fas fa-circle-exclamation text-red-500 mt-0.5 flex-shrink-0"></i>
                            <p class="text-sm font-medium text-red-800 dark:text-red-300" x-text="error.message"></p>
                        </div>
                    </div>
                </template>

                <!-- Toast notification -->
                <template x-if="toast">
                    <div class="fixed top-5 right-5 z-50 card px-5 py-3 shadow-lg flex items-center gap-3 border"
                         :class="toast.type === 'success' ? 'border-green-300 bg-green-50 dark:bg-green-950/40' : 'border-red-300 bg-red-50 dark:bg-red-950/40'">
                        <i :class="toast.type === 'success' ? 'fas fa-check-circle text-green-500' : 'fas fa-times-circle text-red-500'"></i>
                        <span class="text-sm font-medium" x-text="toast.message"></span>
                    </div>
                </template>

                <!-- CATALOG LIST VIEW -->
                <template x-if="!selectedCatalog">
                    <div>
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

                        <template x-if="!loading && !error && catalogs.length === 0">
                            <div class="card p-10 text-center">
                                <i class="fas fa-store text-4xl text-[hsl(var(--muted-foreground))] mb-4"></i>
                                <h3 class="font-semibold text-lg mb-1">Tidak ada katalog ditemukan</h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">Pastikan akun Meta Business Anda memiliki katalog produk yang aktif.</p>
                            </div>
                        </template>

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
                        <!-- Search -->
                        <div class="card p-3">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none"></i>
                                <input type="text" x-model="productSearch" placeholder="Cari produk berdasarkan nama atau SKU..."
                                    class="input w-full min-h-[44px]" style="padding-left: 2.5rem;">
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
                                <p class="text-sm text-[hsl(var(--muted-foreground))]"
                                   x-text="productSearch ? 'Tidak ada hasil untuk \'' + productSearch + '\'' : 'Katalog ini belum memiliki produk. Klik Tambah Produk untuk memulai.'"></p>
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
                                            <div class="h-44 bg-[hsl(var(--muted))] flex items-center justify-center overflow-hidden relative group">
                                                <template x-if="product.image_url">
                                                    <img :src="product.image_url" :alt="product.name" class="w-full h-full object-cover" loading="lazy">
                                                </template>
                                                <template x-if="!product.image_url">
                                                    <i class="fas fa-image text-3xl text-[hsl(var(--muted-foreground))]"></i>
                                                </template>
                                                <!-- Action overlay -->
                                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                    <button @click.stop="openEditModal(product)"
                                                        class="btn btn-sm bg-white text-gray-800 hover:bg-gray-100">
                                                        <i class="fas fa-pencil mr-1"></i>Edit
                                                    </button>
                                                    <button @click.stop="confirmDelete(product)"
                                                        class="btn btn-sm bg-red-500 text-white hover:bg-red-600">
                                                        <i class="fas fa-trash mr-1"></i>Hapus
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="p-4 flex flex-col gap-2 flex-1">
                                                <h3 class="font-semibold text-sm leading-tight line-clamp-2" x-text="product.name"></h3>
                                                <template x-if="product.description">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))] line-clamp-2" x-text="product.description"></p>
                                                </template>
                                                <div class="flex items-center justify-between mt-auto pt-2">
                                                    <span class="font-bold text-[hsl(var(--primary))]" x-text="formatPrice(product.price, product.currency)"></span>
                                                    <span class="badge text-xs"
                                                        :class="product.availability === 'in stock' ? 'badge-success' : 'badge-secondary'"
                                                        x-text="product.availability === 'in stock' ? 'Tersedia' : (product.availability ?? '–')">
                                                    </span>
                                                </div>
                                                <template x-if="product.retailer_id">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono truncate" x-text="'SKU: ' + product.retailer_id"></p>
                                                </template>
                                                <!-- Edit/Delete buttons (mobile fallback) -->
                                                <div class="flex gap-2 mt-1 sm:hidden">
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

    <!-- ===== CREATE MODAL ===== -->
    <template x-if="showCreateModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" @click="showCreateModal = false"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-[hsl(var(--border))]">
                    <h3 class="font-semibold text-lg">Tambah Produk Baru</h3>
                    <button @click="showCreateModal = false" class="btn btn-ghost btn-sm p-1">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <form @submit.prevent="createProduct()" class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">Nama Produk <span class="text-red-500">*</span></label>
                            <input type="text" x-model="createForm.name" class="input w-full" placeholder="Nama produk" required>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">SKU / Retailer ID <span class="text-red-500">*</span></label>
                            <input type="text" x-model="createForm.retailer_id" class="input w-full" placeholder="SKU-001" required>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Ketersediaan</label>
                            <select x-model="createForm.availability" class="input w-full">
                                <option value="in stock">Tersedia</option>
                                <option value="out of stock">Habis</option>
                                <option value="preorder">Pre-order</option>
                            </select>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Harga <span class="text-red-500">*</span></label>
                            <input type="number" x-model="createForm.price_display" class="input w-full" placeholder="30000" min="0" required>
                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Masukkan dalam Rupiah (misal: 30000)</p>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Mata Uang</label>
                            <select x-model="createForm.currency" class="input w-full">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="SGD">SGD</option>
                                <option value="MYR">MYR</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">URL Gambar <span class="text-red-500">*</span></label>
                            <input type="url" x-model="createForm.image_url" class="input w-full" placeholder="https://..." required>
                        </div>
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">URL Produk <span class="text-red-500">*</span></label>
                            <input type="url" x-model="createForm.url" class="input w-full" placeholder="https://..." required>
                        </div>
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">Deskripsi</label>
                            <textarea x-model="createForm.description" class="input w-full resize-none" rows="3" placeholder="Deskripsi produk..."></textarea>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Brand</label>
                            <input type="text" x-model="createForm.brand" class="input w-full" placeholder="Nama brand">
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Kondisi</label>
                            <select x-model="createForm.condition" class="input w-full">
                                <option value="new">Baru</option>
                                <option value="refurbished">Refurbished</option>
                                <option value="used">Bekas</option>
                            </select>
                        </div>
                    </div>
                    <template x-if="createError">
                        <p class="text-sm text-red-500" x-text="createError"></p>
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

    <!-- ===== EDIT MODAL ===== -->
    <template x-if="showEditModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" @click="showEditModal = false"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-[hsl(var(--border))]">
                    <h3 class="font-semibold text-lg">Edit Produk</h3>
                    <button @click="showEditModal = false" class="btn btn-ghost btn-sm p-1">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <form @submit.prevent="updateProduct()" class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">Nama Produk</label>
                            <input type="text" x-model="editForm.name" class="input w-full" placeholder="Nama produk">
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Ketersediaan</label>
                            <select x-model="editForm.availability" class="input w-full">
                                <option value="in stock">Tersedia</option>
                                <option value="out of stock">Habis</option>
                                <option value="preorder">Pre-order</option>
                            </select>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Kondisi</label>
                            <select x-model="editForm.condition" class="input w-full">
                                <option value="new">Baru</option>
                                <option value="refurbished">Refurbished</option>
                                <option value="used">Bekas</option>
                            </select>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Harga</label>
                            <input type="number" x-model="editForm.price_display" class="input w-full" placeholder="30000" min="0">
                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Masukkan dalam Rupiah</p>
                        </div>
                        <div>
                            <label class="label text-sm font-medium mb-1 block">Mata Uang</label>
                            <select x-model="editForm.currency" class="input w-full">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="SGD">SGD</option>
                                <option value="MYR">MYR</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">URL Gambar</label>
                            <input type="url" x-model="editForm.image_url" class="input w-full" placeholder="https://...">
                        </div>
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">URL Produk</label>
                            <input type="url" x-model="editForm.url" class="input w-full" placeholder="https://...">
                        </div>
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">Deskripsi</label>
                            <textarea x-model="editForm.description" class="input w-full resize-none" rows="3"></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="label text-sm font-medium mb-1 block">Brand</label>
                            <input type="text" x-model="editForm.brand" class="input w-full">
                        </div>
                    </div>
                    <template x-if="editError">
                        <p class="text-sm text-red-500" x-text="editError"></p>
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

    <!-- ===== DELETE CONFIRM ===== -->
    <template x-if="showDeleteConfirm">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" @click="showDeleteConfirm = false"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl w-full max-w-sm p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-trash text-red-500"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold">Hapus Produk</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))]">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <p class="text-sm">Yakin ingin menghapus produk <strong x-text="deleteTarget?.name"></strong>?</p>
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

</div>

<!-- Facebook SDK -->
<script>
    window.fbAsyncInit = function() {
        FB.init({
            appId: window.catalogConfig?.app_id || '',
            autoLogAppEvents: true,
            xfbml: true,
            version: 'v24.0',
        });
        window.dispatchEvent(new CustomEvent('fb-catalog-sdk-ready'));
    };

    function loadFacebookSDK() {
        var d = document, s = 'script', id = 'facebook-jssdk';
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s);
        js.id = id;
        js.async = true;
        js.defer = true;
        js.src = "https://connect.facebook.net/en_US/sdk.js";
        fjs.parentNode.insertBefore(js, fjs);
    }

    if (document.readyState === 'complete') {
        setTimeout(loadFacebookSDK, 100);
    } else {
        window.addEventListener('load', function() { setTimeout(loadFacebookSDK, 100); });
    }
</script>

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
        catalogConfig: null,
        connectingCatalog: false,

        // Create
        showCreateModal: false,
        creating: false,
        createError: null,
        createForm: {},

        // Edit
        showEditModal: false,
        editing: false,
        editError: null,
        editForm: {},
        editingProductId: null,

        // Delete
        showDeleteConfirm: false,
        deleting: false,
        deleteTarget: null,

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

        sdkLoaded: false,

        async init() {
            this.initDashboard();
            this.setupCatalogMessageListener();

            window.addEventListener('fb-catalog-sdk-ready', () => {
                this.sdkLoaded = true;
            });
            if (typeof FB !== 'undefined') {
                this.sdkLoaded = true;
            }
            setTimeout(() => { this.sdkLoaded = true; }, 5000);

            await Promise.all([
                this.loadCatalogConfig(),
                this.fetchCatalogs(),
            ]);
            // Auto-open catalog from URL if present
            if (this.initialCatalogId && this.catalogs.length > 0) {
                const found = this.catalogs.find(c => c.id === String(this.initialCatalogId));
                if (found) {
                    await this.selectCatalog(found, false);
                } else {
                    // Catalog ID from URL not in list — open directly
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
            // Handle browser back/forward button
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

        async loadCatalogConfig() {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);

            try {
                const token = localStorage.getItem('token');
                if (!token) return;

                const res = await fetch(`${this.API_BASE_URL}/catalog/embedded-signup/config`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                    signal: controller.signal,
                });

                clearTimeout(timeoutId);

                if (!res.ok) return;

                const data = await res.json();
                if (data.success) {
                    this.catalogConfig = data.data;
                    window.catalogConfig = data.data;

                    if (typeof FB !== 'undefined' && this.catalogConfig?.app_id) {
                        FB.init({
                            appId: this.catalogConfig.app_id,
                            autoLogAppEvents: true,
                            xfbml: true,
                            version: 'v24.0',
                        });
                        this.sdkLoaded = true;
                    }
                }
            } catch (e) {
                if (e.name !== 'AbortError') {
                    console.error('Error loading catalog config:', e);
                }
            } finally {
                clearTimeout(timeoutId);
            }
        },

        setupCatalogMessageListener() {
            window.addEventListener('message', (event) => {
                if (event.origin !== 'https://www.facebook.com' &&
                    event.origin !== 'https://web.facebook.com') {
                    return;
                }
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'FINISH') {
                        console.log('Catalog signup completed via message event:', data.data);
                    } else if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'CANCEL') {
                        this.showToast('Koneksi dibatalkan.', 'error');
                    } else if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'ERROR') {
                        this.showToast('Terjadi kesalahan saat menghubungkan katalog.', 'error');
                    }
                } catch (e) {}
            });
        },

        launchCatalogSignup() {
            if (!this.catalogConfig?.config_id || !this.catalogConfig?.app_id) {
                this.showToast('Konfigurasi katalog belum tersedia. Silakan muat ulang halaman.', 'error');
                return;
            }

            if (typeof FB !== 'undefined') {
                const self = this;

                FB.login((response) => {
                    if (response.authResponse) {
                        const code = response.authResponse.code;
                        const sessionInfo = response.authResponse.extras?.session_info || null;
                        self.sendCatalogCodeToBackend(code, sessionInfo);
                    } else {
                        self.showToast('Koneksi dibatalkan atau tidak diizinkan.', 'error');
                    }
                }, {
                    config_id: this.catalogConfig.config_id,
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: {
                        setup: {},
                        sessionInfoVersion: '3',
                    },
                });
            } else {
                this.launchCatalogSignupRedirect();
            }
        },

        launchCatalogSignupRedirect() {
            const appId = this.catalogConfig.app_id;
            const configId = this.catalogConfig.config_id;
            const extras = encodeURIComponent(JSON.stringify({
                sessionInfoVersion: '3',
                version: 'v3',
            }));

            const url = `https://business.facebook.com/messaging/whatsapp/onboard/?app_id=${appId}&config_id=${configId}&extras=${extras}`;
            const width = 600;
            const height = 700;
            const left = (window.innerWidth - width) / 2;
            const top = (window.innerHeight - height) / 2;

            const popup = window.open(
                url,
                'catalog_signup',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes`
            );

            if (!popup) {
                this.showToast('Popup diblokir. Izinkan popup untuk situs ini.', 'error');
            }
        },

        async sendCatalogCodeToBackend(code, sessionInfo = null) {
            try {
                const token = localStorage.getItem('token');
                const body = { code };
                if (sessionInfo?.business_id) {
                    body.business_id = sessionInfo.business_id;
                }

                const res = await fetch(`${this.API_BASE_URL}/catalog/embedded-signup/callback`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });

                const data = await res.json();
                if (data.success) {
                    this.showToast('Katalog berhasil terhubung.', 'success');
                    await this.fetchCatalogs();
                } else {
                    this.showToast(data.message || 'Gagal menghubungkan katalog.', 'error');
                }
            } catch (e) {
                console.error('Error sending catalog code to backend:', e);
                this.showToast('Gagal terhubung ke server.', 'error');
            } finally {
                this.connectingCatalog = false;
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

        // ─── CREATE ───────────────────────────────────────────
        openCreateModal() {
            this.createForm = { name: '', retailer_id: '', price_display: '', currency: 'IDR', image_url: '', url: '', availability: 'in stock', description: '', brand: '', condition: 'new' };
            this.createError = null;
            this.showCreateModal = true;
        },

        async createProduct() {
            this.creating = true;
            this.createError = null;
            try {
                const token = localStorage.getItem('token');
                const payload = {
                    ...this.createForm,
                    price: Math.round(parseFloat(this.createForm.price_display || 0) * 100),
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

        // ─── EDIT ─────────────────────────────────────────────
        openEditModal(product) {
            this.editingProductId = product.id;
            // Parse price: Meta returns formatted string like "Rp30.000", extract number
            let priceNum = 0;
            if (product.price) {
                const raw = String(product.price).replace(/[^0-9.]/g, '');
                priceNum = parseFloat(raw) || 0;
                // If price > 1000 it's likely already in cents format (divide by 100)
                if (priceNum > 1000) priceNum = priceNum / 100;
            }
            this.editForm = {
                name: product.name || '',
                availability: product.availability || 'in stock',
                condition: product.condition || 'new',
                price_display: priceNum > 0 ? String(Math.round(priceNum)) : '',
                currency: product.currency || 'IDR',
                image_url: product.image_url || '',
                url: product.url || '',
                description: product.description || '',
                brand: product.brand || '',
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
                    payload.price = Math.round(parseFloat(payload.price_display) * 100);
                }
                delete payload.price_display;
                // Remove empty fields
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

        // ─── DELETE ───────────────────────────────────────────
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

        // ─── HELPERS ──────────────────────────────────────────
        showToast(message, type = 'success') {
            this.toast = { message, type };
            setTimeout(() => { this.toast = null; }, 3000);
        },

        formatPrice(price, currency) {
            if (!price) return '–';
            try {
                const raw = String(price).replace(/[^0-9.]/g, '');
                const num = parseFloat(raw);
                if (isNaN(num)) return price;
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
