@extends('layouts.app')

@section('title', 'Konfigurasi Reservasi')

@section('content')
<!-- Toast Notification Container -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] flex flex-col gap-2" x-data="toastManager()">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[300px] max-w-[400px]"
             :class="{
                 'bg-emerald-50 border border-emerald-200 text-emerald-800': toast.type === 'success',
                 'bg-red-50 border border-red-200 text-red-800': toast.type === 'error',
                 'bg-amber-50 border border-amber-200 text-amber-800': toast.type === 'warning',
                 'bg-blue-50 border border-blue-200 text-blue-800': toast.type === 'info'
             }">
            <div class="flex-shrink-0">
                <i class="fas text-lg"
                   :class="{
                       'fa-check-circle text-emerald-500': toast.type === 'success',
                       'fa-exclamation-circle text-red-500': toast.type === 'error',
                       'fa-exclamation-triangle text-amber-500': toast.type === 'warning',
                       'fa-info-circle text-blue-500': toast.type === 'info'
                   }"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium" x-text="toast.message"></p>
            </div>
            <button @click="removeToast(toast.id)" class="flex-shrink-0 p-1 rounded hover:bg-black/5 transition-colors">
                <i class="fas fa-times text-xs opacity-60"></i>
            </button>
        </div>
    </template>
</div>

<div x-data="configApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'reservations-config'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => 'Konfigurasi Reservasi', 'description' => 'Atur pengaturan reservasi untuk setiap toko'])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-8">
            <div class="relative mx-auto max-w-6xl">
                <div class="pointer-events-none absolute -top-24 right-0 h-64 w-64 rounded-full bg-[radial-gradient(circle_at_center,rgba(16,185,129,0.18),transparent_70%)] blur-2xl"></div>
                <div class="pointer-events-none absolute -bottom-28 left-0 h-72 w-72 rounded-full bg-[radial-gradient(circle_at_center,rgba(59,130,246,0.12),transparent_70%)] blur-2xl"></div>
                <div class="pointer-events-none absolute inset-x-0 top-16 h-px bg-gradient-to-r from-transparent via-emerald-300/40 to-transparent"></div>

                <div class="flex flex-wrap items-center justify-between gap-4 pb-6">
                    <a href="/dashboard/reservations" class="btn btn-ghost btn-sm border border-transparent transition-all duration-200 hover:-translate-y-0.5 hover:border-input">
                        <i class="fas fa-arrow-left"></i>
                        Kembali
                    </a>
                    <div class="flex items-center gap-3 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-card px-3 py-1">
                            <span class="h-2 w-2 rounded-full bg-emerald-500" :class="{ 'bg-emerald-500': selectedStoreId, 'bg-muted-foreground': !selectedStoreId }"></span>
                            <span x-text="selectedStoreId ? 'Toko aktif' : 'Pilih toko dulu'"></span>
                        </span>
                        <span class="hidden sm:inline">Konfigurasi akan tersimpan per toko.</span>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[1.05fr_2fr]">
                    <div class="space-y-6">
                        <div class="card p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">Reservasi</p>
                                    <h3 class="text-xl font-semibold">Pilih Toko</h3>
                                    <p class="text-sm text-muted-foreground">Kelola slot, menu, dan pembayaran per toko.</p>
                                </div>
                                <span class="inline-flex items-center rounded-full border border-border/70 bg-muted/40 px-4 py-2 text-xs font-medium shadow-sm whitespace-nowrap flex-shrink-0">
                                    Konfigurasi
                                </span>
                            </div>
                            <div class="mt-4">
                                <select x-model="selectedStoreId" @change="loadConfig()" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                    <option value="">-- Pilih Toko --</option>
                                    <template x-for="store in stores" :key="store.id">
                                        <option :value="store.id" x-text="store.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" x-show="selectedStoreId" x-cloak>
                                <div class="rounded-lg border border-border/70 bg-muted/30 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:bg-background/80">
                                    <p class="text-sm text-muted-foreground mb-2">Slot terjadwal</p>
                                    <p class="text-2xl font-semibold" x-text="form.available_slots.length"></p>
                                </div>
                                <div class="rounded-lg border border-border/70 bg-muted/30 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:bg-background/80">
                                    <p class="text-sm text-muted-foreground mb-2">Kapasitas terbooking</p>
                                    <p class="text-2xl font-semibold" x-text="bookedCapacity"></p>
                                </div>
                                <div class="hidden xl:block rounded-lg border border-border/70 bg-muted/30 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:bg-background/80" x-show="totalTableCapacity > 0" x-cloak>
                                    <p class="text-sm text-muted-foreground mb-2">Total kapasitas meja</p>
                                    <p class="text-2xl font-semibold" x-text="totalTableCapacity"></p>
                                </div>

                            </div>
                        </div>

                        <div x-show="selectedStoreId && userSlug" x-cloak class="card p-6 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-semibold">Link Reservasi Publik</h4>
                                    <p class="text-sm text-muted-foreground">Bagikan link ini untuk pelanggan.</p>
                                </div>
                                <span class="inline-flex items-center rounded-full border border-border/70 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 shadow-sm">Aktif</span>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <input type="text" :value="reservationLink" readonly
                                       class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm">
                                <button type="button" @click="copyLink()" class="btn btn-primary btn-sm shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <template x-if="selectedStoreId">
                            <form @submit.prevent="saveConfig()" class="space-y-6">
                                <div class="grid gap-6 xl:grid-cols-2">
                                    <div class="card transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                                        <div class="card-header">
                                            <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">Slot</p>
                                            <div class="card-title">Tanggal & Jam Tersedia</div>
                                            <p class="card-description">Atur slot waktu yang bisa dipilih pelanggan.</p>
                                        </div>
                                        <div class="card-content space-y-3">
                                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                                <template x-for="(slot, index) in displayedSlots" :key="index">
                                                    <div class="flex items-center gap-2">
                                                        <!-- Input container with formatted display -->
                                                        <div class="flex-1 relative">
                                                            <!-- Formatted display (visible to user) -->
                                                            <div class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm flex items-center justify-between cursor-pointer relative z-0">
                                                                <span x-text="formatSlotDisplay(slot)" class="text-foreground font-medium"></span>
                                                                <i class="fas fa-calendar-alt text-muted-foreground text-xs"></i>
                                                            </div>
                                                            <!-- Native input (invisible but functional) -->
                                                            <input type="datetime-local"
                                                                   x-model="form.available_slots[index]"
                                                                   :min="new Date().toISOString().slice(0, 16)"
                                                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                                        </div>
                                                        <!-- Delete button -->
                                                        <button type="button" @click="removeSlot(index)" class="btn btn-outline btn-sm text-destructive transition-all duration-200 hover:-translate-y-0.5">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            <template x-if="hasMoreSlots">
                                                <button type="button" @click="loadMoreSlots()" class="btn btn-outline btn-sm w-full transition-all duration-200 hover:-translate-y-0.5">
                                                    <i class="fas fa-chevron-down"></i>
                                                    Load More (<span x-text="remainingSlots"></span> slot)
                                                </button>
                                            </template>
                                            <template x-if="form.available_slots.length > 0">
                                                <p class="text-xs text-muted-foreground text-center">Menampilkan <span x-text="displayedSlots.length"></span> dari <span x-text="form.available_slots.length"></span> slot</p>
                                            </template>
                                            <button type="button" @click="openBulkSlotModal()" class="btn btn-sm w-full bg-purple-600 text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-purple-700 hover:shadow-lg">
                                                <i class="fas fa-layer-group"></i>
                                                Bulk Time Slot
                                            </button>
                                        </div>
                                    </div>



                                    <div class="card transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                                        <div class="card-header">
                                            <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">Pembayaran</p>
                                            <div class="card-title">Skema Pembayaran</div>
                                            <p class="card-description">Tetapkan biaya dan pilihan DP/pembayaran penuh.</p>
                                        </div>
                                        <div class="card-content space-y-4">
                                            <div>
                                                <label class="text-sm font-medium">Biaya Reservasi (Rp)</label>
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    x-model="reservationFeeDisplay"
                                                    @input="handleReservationFeeInput($event)"
                                                    placeholder="Rp 0"
                                                    class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                                >
                                                <p class="mt-2 text-xs text-muted-foreground">Pelanggan wajib membayar sebelum reservasi aktif.</p>
                                                <p class="mt-2 text-xs text-destructive" x-show="!isReservationFeeValid">Biaya reservasi wajib diisi.</p>
                                            </div>

                                            <div>
                                                <label class="text-sm font-medium">Kapasitas Per Slot</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    x-model.number="form.capacity_per_slot"
                                                    placeholder="12"
                                                    class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                                >
                                                <p class="mt-2 text-xs text-muted-foreground">Maksimal reservasi per slot waktu.</p>
                                            </div>
                                            <div class="space-y-3">
                                                <label class="flex items-start gap-3 rounded-lg border border-border/70 bg-muted/30 p-3 transition-all duration-200 hover:bg-background/80">
                                                    <input type="checkbox" x-model="form.allow_full_payment" id="allow_full" class="mt-1 h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                                    <span>
                                                        <span class="text-sm font-medium">Izinkan Pembayaran Penuh</span>
                                                        <span class="block text-xs text-muted-foreground">Pelanggan dapat melunasi sekaligus.</span>
                                                    </span>
                                                </label>
                                                <label class="flex items-start gap-3 rounded-lg border border-border/70 bg-muted/30 p-3 transition-all duration-200 hover:bg-background/80">
                                                    <input type="checkbox" x-model="form.allow_dp_payment" id="allow_dp" class="mt-1 h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                                    <span>
                                                        <span class="text-sm font-medium">Izinkan Pembayaran DP</span>
                                                        <span class="block text-xs text-muted-foreground">Tentukan persentase pembayaran awal.</span>
                                                    </span>
                                                </label>
                                                <p class="text-xs text-destructive" x-show="!hasValidPaymentType">Pilih minimal satu jenis pembayaran.</p>
                                            </div>
                                            <div x-show="form.allow_dp_payment" x-transition class="rounded-lg border border-border/70 bg-muted/20 p-3">
                                                <label class="text-sm font-medium">Persentase DP (%)</label>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <button type="button"
                                                            @click="form.dp_percentage = 50"
                                                            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                                                            :class="isDpPresetSelected(50) ? 'bg-purple-600 text-white border-purple-600' : 'bg-background text-muted-foreground border-border/70 hover:border-purple-400'">
                                                        50%
                                                    </button>
                                                    <button type="button"
                                                            @click="form.dp_percentage = 40"
                                                            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                                                            :class="isDpPresetSelected(40) ? 'bg-purple-600 text-white border-purple-600' : 'bg-background text-muted-foreground border-border/70 hover:border-purple-400'">
                                                        40%
                                                    </button>
                                                    <button type="button"
                                                            @click="form.dp_percentage = 20"
                                                            class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                                                            :class="isDpPresetSelected(20) ? 'bg-purple-600 text-white border-purple-600' : 'bg-background text-muted-foreground border-border/70 hover:border-purple-400'">
                                                        20%
                                                    </button>
                                                </div>
                                                <input type="number" x-model.number="form.dp_percentage"
                                                       min="0" max="100" step="0.01"
                                                       class="mt-3 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                                <p class="mt-2 text-xs text-muted-foreground">Default: 50%</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                                        <div class="card-header">
                                            <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">Meja</p>
                                            <div class="card-title">Pengaturan Meja</div>
                                            <p class="card-description">Pilih meja yang bisa dipesan.</p>
                                        </div>
                                        <div class="card-content space-y-3">
                                            <div x-show="loadingTables" class="text-center py-4">
                                                <i class="fas fa-circle-notch fa-spin text-muted-foreground"></i>
                                                <p class="text-sm text-muted-foreground mt-2">Memuat data meja...</p>
                                            </div>
                                            <div x-show="!loadingTables" class="space-y-2 max-h-60 overflow-y-auto rounded-lg border border-border/70 bg-muted/20 p-3">
                                                <template x-if="tables.length === 0">
                                                    <p class="text-sm text-muted-foreground text-center py-4">Tidak ada meja tersedia</p>
                                                </template>
                                                <div class="flex gap-2 items-center">
                                                    <input type="checkbox" @change="toggleAllTables($event.target.checked)" :checked="allTablesSelected" id="select-all-tables" class="h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                                    <label for="select-all-tables" class="text-sm font-medium cursor-pointer">Pilih Semua</label>
                                                    <button type="button" @click="deselectAllTables()" class="ml-auto text-xs text-destructive hover:underline">Hapus Semua</button>
                                                </div>
                                                <template x-for="table in tables" :key="table.id">
                                                    <label class="flex items-center gap-2 rounded-md border border-transparent p-2 transition-all duration-200 hover:border-border/60 hover:bg-background/70">
                                                        <input type="checkbox" :value="table.id" x-model="form.available_tables" :id="'table-'+table.id" class="h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                                        <span class="text-sm" x-text="'Meja ' + table.number + ' (Kapasitas: ' + table.capacity + ' orang)'"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                                    <div class="card-header">
                                        <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">Menu</p>
                                        <div class="card-title">Pengaturan Menu</div>
                                        <p class="card-description">Tentukan pilihan menu yang terlihat saat reservasi.</p>
                                    </div>
                                    <div class="card-content space-y-4">
                                        <div class="flex items-center gap-3 rounded-lg border border-border/70 bg-muted/30 p-3 transition-all duration-200 hover:bg-background/80">
                                            <input type="checkbox" x-model="form.enable_menu_selection" id="enable_menu" class="h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                            <label for="enable_menu" class="text-sm font-medium cursor-pointer">Aktifkan pilihan menu</label>
                                        </div>
                                        <div x-show="form.enable_menu_selection" x-transition class="space-y-3">
                                            <label class="flex items-center gap-3 rounded-lg border border-border/70 bg-muted/30 p-3 transition-all duration-200 hover:bg-background/80">
                                                <input type="checkbox" x-model="form.require_menu_selection" id="require_menu" class="h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                                <span class="text-sm text-muted-foreground">Wajibkan pelanggan memilih menu</span>
                                            </label>
                                            <div>
                                                <div x-show="loadingProducts" class="text-center py-4">
                                                    <i class="fas fa-circle-notch fa-spin text-muted-foreground"></i>
                                                    <p class="text-sm text-muted-foreground mt-2">Memuat data menu...</p>
                                                </div>
                                                <div x-show="!loadingProducts" class="space-y-2 max-h-64 overflow-y-auto rounded-lg border border-border/70 bg-muted/20 p-3">
                                                    <template x-if="products.length === 0">
                                                        <p class="text-sm text-muted-foreground text-center py-4">Tidak ada produk tersedia</p>
                                                    </template>
                                                    <div class="flex gap-2 items-center">
                                                        <input type="checkbox" @change="toggleAllProducts($event.target.checked)" :checked="allProductsSelected" id="select-all-products" class="h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                                        <label for="select-all-products" class="text-sm font-medium cursor-pointer">Pilih Semua</label>
                                                        <button type="button" @click="deselectAllProducts()" class="ml-auto text-xs text-destructive hover:underline">Hapus Semua</button>
                                                    </div>
                                                    <template x-for="product in products" :key="product.id">
                                                        <label class="flex items-center gap-2 rounded-md border border-transparent p-2 transition-all duration-200 hover:border-border/60 hover:bg-background/70">
                                                            <input type="checkbox" :value="product.id" x-model="form.available_products" :id="'product-'+product.id" class="h-4 w-4 rounded border-input text-emerald-600 focus-visible:ring-ring">
                                                            <span class="text-sm">
                                                                <span x-text="product.name"></span>
                                                                <span class="text-xs text-muted-foreground ml-1" x-text="'(Rp' + parseFloat(product.price).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ')'"></span>
                                                            </span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border/70 pt-4">
                                    <div class="text-xs text-muted-foreground">
                                        Pastikan slot dan menu sesuai kapasitas operasional.
                                    </div>
                                    <div class="flex gap-3">
                                        <button type="button" @click="loadConfig()" class="btn btn-outline relative h-11 min-w-[150px] overflow-hidden border-border/80 bg-background/60 px-6 text-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-muted/60">
                                            <span class="absolute inset-0 bg-gradient-to-r from-transparent via-muted/50 to-transparent opacity-0 transition-opacity duration-300 hover:opacity-100"></span>
                                            <i class="fas fa-undo relative"></i>
                                            <span class="relative">Reset</span>
                                        </button>
                                        <button type="submit" :disabled="saving || !hasValidPaymentType || !isReservationFeeValid" class="btn btn-primary relative h-11 min-w-[150px] overflow-hidden px-6 text-sm shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                                            <span class="absolute inset-0 bg-gradient-to-r from-emerald-500 via-emerald-600 to-emerald-700 opacity-0 transition-opacity duration-300 hover:opacity-100"></span>
                                            <i class="fas fa-save relative" :class="{ 'animate-spin': saving }"></i>
                                            <span class="relative" x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </template>

                        <template x-if="!selectedStoreId">
                            <div class="card p-8 text-center text-muted-foreground transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg">
                                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-border/70 bg-muted/30">
                                    <i class="fas fa-store text-xl"></i>
                                </div>
                                <p class="font-medium">Pilih toko untuk mulai konfigurasi reservasi.</p>
                                <p class="text-sm text-muted-foreground mt-2">Data tersimpan spesifik per lokasi toko.</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </main>
        <div x-show="bulkSlotModalOpen" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-white px-4 py-8">
            <div class="absolute inset-0 bg-white" @click="closeBulkSlotModal()"></div>
            <div x-show="bulkSlotModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-6 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-6 scale-95"
                 class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-border/70 bg-background shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-border/70 px-6 py-5">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-purple-600">Bulk Time Slot</p>
                        <h3 class="text-xl font-semibold">Bulk Time Slot Generator</h3>
                        <p class="text-sm text-muted-foreground">Buat slot waktu untuk banyak tanggal sekaligus.</p>
                    </div>
                    <button type="button" @click="closeBulkSlotModal()" class="btn btn-ghost btn-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form @submit.prevent="generateBulkSlots()" class="space-y-6 px-6 py-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <p class="text-xs uppercase tracking-[0.25em] text-purple-600">Schedule Range</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">From Date</label>
                                    <input type="date" x-model="bulkSlotForm.start_date" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">To Date</label>
                                    <input type="date" x-model="bulkSlotForm.end_date" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-xs uppercase tracking-[0.25em] text-purple-600">Operating Hours (WIB)</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Start (GMT+7)</label>
                                    <input type="text" inputmode="numeric" placeholder="17:00" x-model="bulkSlotForm.opening_time" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">End (GMT+7)</label>
                                    <input type="text" inputmode="numeric" placeholder="23:00" x-model="bulkSlotForm.closing_time" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">Gunakan format 24 jam (WIB).</p>
                        </div>
                        <div class="space-y-3">
                            <p class="text-xs uppercase tracking-[0.25em] text-purple-600">Capacity Per Slot</p>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Capacity</label>
                                <input type="number" min="1" max="100" x-model.number="bulkSlotForm.capacity_per_slot" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                <p class="text-xs text-muted-foreground">Jumlah maksimal reservasi per slot.</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-xs uppercase tracking-[0.25em] text-purple-600">Slot Duration</p>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <button type="button" @click="bulkSlotForm.slot_duration = 30" class="rounded-xl border px-4 py-3 text-sm font-semibold transition"
                                        :class="bulkSlotForm.slot_duration === 30 ? 'border-purple-600 bg-purple-50 text-purple-700' : 'border-border/70 bg-background hover:border-purple-300'">30 Min</button>
                                <button type="button" @click="bulkSlotForm.slot_duration = 60" class="rounded-xl border px-4 py-3 text-sm font-semibold transition"
                                        :class="bulkSlotForm.slot_duration === 60 ? 'border-purple-600 bg-purple-50 text-purple-700' : 'border-border/70 bg-background hover:border-purple-300'">1 Hr</button>
                                <button type="button" @click="bulkSlotForm.slot_duration = 120" class="rounded-xl border px-4 py-3 text-sm font-semibold transition"
                                        :class="bulkSlotForm.slot_duration === 120 ? 'border-purple-600 bg-purple-50 text-purple-700' : 'border-border/70 bg-background hover:border-purple-300'">2 Hr</button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 rounded-2xl border border-dashed border-purple-200 bg-purple-50/40 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs uppercase tracking-[0.25em] text-purple-600">Exclude Dates</p>
                            <span class="text-xs text-muted-foreground">Optional</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <input type="date" x-model="bulkSlotExcludeInput" class="flex-1 min-w-[180px] rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                            <button type="button" @click="addExcludedDate()" class="btn btn-outline btn-sm">
                                <i class="fas fa-plus"></i>
                                Tambah
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="(date, index) in bulkSlotForm.exclude_dates" :key="date">
                                <span class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-white px-3 py-1 text-xs font-medium text-purple-700">
                                    <span x-text="date"></span>
                                    <button type="button" @click="removeExcludedDate(index)" class="text-purple-400 hover:text-purple-700">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border/70 pt-4">
                        <div class="text-sm text-muted-foreground">
                            <span class="font-semibold text-foreground" x-text="bulkSlotPreviewCount"></span>
                            <span>slot akan dibuat.</span>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="closeBulkSlotModal()" class="btn btn-outline btn-sm">Batal</button>
                            <button type="submit" :disabled="bulkSlotLoading" class="btn btn-sm bg-purple-600 text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-purple-700 hover:shadow-lg">
                                <i class="fas fa-wand-magic-sparkles" :class="{ 'animate-spin': bulkSlotLoading }"></i>
                                <span x-text="bulkSlotLoading ? 'Memproses...' : 'Generate All Slots'"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toastManager() {
    return {
        toasts: [],
        addToast(message, type = 'info') {
            const id = Date.now();
            this.toasts.push({ id, message, type, visible: true });
            setTimeout(() => this.removeToast(id), 5000);
        },
        removeToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) {
                toast.visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }
        }
    };
}

function configApp() {
    return {
        // Dashboard base properties
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        // Page-specific properties
        loading: false,
        saving: false,
        stores: [],
        selectedStoreId: '',
        configId: null,
        userSlug: '',
        tables: [],
        products: [],
        loadingTables: false,
        loadingProducts: false,
        loadingStats: false,
        reservationStats: {
            booked_capacity: 0,
        },
        form: {
            is_active: true,
            available_slots: [],
            capacity_per_slot: 12,
            reservation_fee: 0,
            dp_percentage: 50,
            allow_full_payment: true,
            allow_dp_payment: true,
            available_tables: [],
            available_products: [],
            enable_menu_selection: false,
            require_menu_selection: false
        },
        reservationFeeDisplay: '',
        bulkSlotModalOpen: false,
        bulkSlotLoading: false,
        bulkSlotExcludeInput: '',
        bulkSlotForm: {
            start_date: '',
            end_date: '',
            opening_time: '17:00',
            closing_time: '23:00',
            slot_duration: 30,
            capacity_per_slot: 12,
            exclude_dates: []
        },
        slotPaginationOffset: 10,

        async init() {
            // Dashboard base init
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

            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth < 768;
                    if (wasMobile && !this.isMobile) {
                        let savedState = localStorage.getItem('sidebarOpen');
                        this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
                    } else if (!wasMobile && this.isMobile) {
                        this.sidebarOpen = false;
                    }
                }, 150);
            });

            let storedUser = localStorage.getItem('user');
            if (storedUser) {
                try { this.user = JSON.parse(storedUser); }
                catch (e) { this.user = { name: 'User', email: 'user@example.com' }; }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }

            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; }
            }

            // Page-specific init
            await this.loadStores();
            await this.loadUserSlug();
            await this.loadTables();
            await this.loadProducts();
            this.syncReservationFeeDisplay();
        },

        get hasValidPaymentType() {
            return this.form.allow_full_payment || this.form.allow_dp_payment;
        },

        get isReservationFeeValid() {
            return this.form.reservation_fee !== null
                && this.form.reservation_fee !== ''
                && !Number.isNaN(this.form.reservation_fee);
        },

        get bulkSlotPreviewCount() {
            return this.calculateBulkSlotCount();
        },

        get displayedSlots() {
            return this.form.available_slots.slice(0, this.slotPaginationOffset);
        },

        get hasMoreSlots() {
            return this.form.available_slots.length > this.slotPaginationOffset;
        },

        get remainingSlots() {
            return this.form.available_slots.length - this.slotPaginationOffset;
        },

        get bookedCapacity() {
            return this.reservationStats?.booked_capacity ?? 0;
        },

        get totalTableCapacity() {
            if (this.tables.length === 0 || this.form.available_tables.length === 0) {
                return 0;
            }

            const selectedTableIds = new Set(this.form.available_tables.map(id => Number(id)));

            return this.tables
                .filter(table => selectedTableIds.has(Number(table.id)))
                .reduce((total, table) => total + Number(table.capacity || 0), 0);
        },

        loadMoreSlots() {
            this.slotPaginationOffset += 10;
        },

        get allTablesSelected() {
            return this.tables.length > 0 && this.form.available_tables.length === this.tables.length;
        },

        get allProductsSelected() {
            return this.products.length > 0 && this.form.available_products.length === this.products.length;
        },

        toggleAllTables(checked) {
            if (checked) {
                this.form.available_tables = this.tables.map(t => t.id);
            } else {
                this.form.available_tables = [];
            }
        },

        deselectAllTables() {
            this.form.available_tables = [];
        },

        toggleAllProducts(checked) {
            if (checked) {
                this.form.available_products = this.products.map(p => p.id);
            } else {
                this.form.available_products = [];
            }
        },

        deselectAllProducts() {
            this.form.available_products = [];
        },

        async loadTables() {
            if (!this.selectedStoreId) return;

            this.loadingTables = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`/api/pos/tables?store_id=${this.selectedStoreId}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load tables');

                const data = await response.json();
                if (data.success) {
                    this.tables = data.data.data || data.data || [];
                }
            } catch (error) {
                console.error('Error loading tables:', error);
            } finally {
                this.loadingTables = false;
            }
        },

        async loadProducts() {
            if (!this.selectedStoreId) return;

            this.loadingProducts = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`/api/pos/products?store_id=${this.selectedStoreId}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load products');

                const data = await response.json();
                if (data.success) {
                    this.products = data.data.data || data.data || [];
                }
            } catch (error) {
                console.error('Error loading products:', error);
            } finally {
                this.loadingProducts = false;
            }
        },

        async loadReservationStats() {
            if (!this.selectedStoreId) return;

            this.loadingStats = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`/api/reservations/stats?store_id=${this.selectedStoreId}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load stats');

                const data = await response.json();
                if (data.success && data.data) {
                    this.reservationStats = data.data;
                }
            } catch (error) {
                console.error('Error loading reservation stats:', error);
            } finally {
                this.loadingStats = false;
            }
        },

        async loadStores() {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch('/api/pos/stores', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load stores');

                const data = await response.json();
                if (data.success) {
                    this.stores = data.data.data || data.data;
                }
            } catch (error) {
                console.error('Error loading stores:', error);
                Alpine.store('toast').addToast('Gagal memuat data toko', 'error');
            }
        },

        async loadUserSlug() {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch('/api/me', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load user');

                const data = await response.json();
                if (data.success && data.data.slug) {
                    this.userSlug = data.data.slug;
                }
            } catch (error) {
                console.error('Error loading user slug:', error);
            }
        },

        get reservationLink() {
            if (!this.userSlug) return '';
            return `${window.location.origin}/reservations/form?merchantName=${this.userSlug}`;
        },

        async loadConfig() {
            if (!this.selectedStoreId) return;

            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`/api/reservation-config?store_id=${this.selectedStoreId}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load config');

                const data = await response.json();
                if (data.success && data.data.length > 0) {
                    const config = data.data[0];
                    this.configId = config.id;
                    this.form = {
                        is_active: config.is_active,
                        available_slots: config.available_slots || [],
                        capacity_per_slot: config.capacity_per_slot || 12,

                        reservation_fee: config.reservation_fee || 0,
                        dp_percentage: config.dp_percentage || 50,
                        allow_full_payment: config.allow_full_payment,
                        allow_dp_payment: config.allow_dp_payment,
                        available_tables: config.available_tables || [],
                        available_products: config.available_products || [],
                        enable_menu_selection: config.enable_menu_selection || false,
                        require_menu_selection: config.require_menu_selection || false
                    };
                    this.bulkSlotForm.capacity_per_slot = config.capacity_per_slot || 12;
                } else {
                    // Reset to defaults if no config exists
                    this.configId = null;
                    this.form = {
                        is_active: true,
                        available_slots: [],
                        capacity_per_slot: 12,

                        reservation_fee: 0,
                        dp_percentage: 50,
                        allow_full_payment: true,
                        allow_dp_payment: true,
                        available_tables: [],
                        available_products: [],
                        enable_menu_selection: false,
                        require_menu_selection: false
                    };
                }
                this.syncReservationFeeDisplay();
                // Reload tables and products for the selected store
                await this.loadTables();
                await this.loadProducts();
                await this.loadReservationStats();
            } catch (error) {
                console.error('Error loading config:', error);
            } finally {
                this.loading = false;
            }
        },

        async saveConfig() {
            if (!this.hasValidPaymentType) {
                Alpine.store('toast').addToast('Pilih minimal satu jenis pembayaran.', 'error');
                return;
            }

            if (!this.isReservationFeeValid) {
                Alpine.store('toast').addToast('Biaya reservasi wajib diisi.', 'error');
                return;
            }

            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const url = this.configId
                    ? `/api/reservation-config/${this.configId}`
                    : '/api/reservation-config';
                const method = this.configId ? 'PUT' : 'POST';

                const payload = {
                    ...this.form,
                    store_id: this.selectedStoreId
                };

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (data.success) {
                    Alpine.store('toast').addToast(data.message || 'Konfigurasi berhasil disimpan', 'success');
                    if (data.data && data.data.id) {
                        this.configId = data.data.id;
                    }
                } else {
                    Alpine.store('toast').addToast(data.message || 'Gagal menyimpan konfigurasi', 'error');
                }
            } catch (error) {
                console.error('Error saving config:', error);
                Alpine.store('toast').addToast('Terjadi kesalahan', 'error');
            } finally {
                this.saving = false;
            }
        },

        removeSlot(index) {
            this.form.available_slots.splice(index, 1);
        },

        openBulkSlotModal() {
            const today = new Date();
            const tomorrow = new Date();
            tomorrow.setDate(today.getDate() + 1);
            const defaultDate = this.formatDateKey(tomorrow);

            if (!this.bulkSlotForm.start_date) {
                this.bulkSlotForm.start_date = defaultDate;
            }

            if (!this.bulkSlotForm.end_date) {
                this.bulkSlotForm.end_date = this.bulkSlotForm.start_date;
            }

            if (!this.bulkSlotForm.opening_time) {
                this.bulkSlotForm.opening_time = '17:00';
            }

            if (!this.bulkSlotForm.closing_time) {
                this.bulkSlotForm.closing_time = '23:00';
            }

            if (!this.bulkSlotForm.slot_duration) {
                this.bulkSlotForm.slot_duration = 30;
            }

            if (!this.bulkSlotForm.capacity_per_slot) {
                this.bulkSlotForm.capacity_per_slot = 12;
            }

            this.bulkSlotModalOpen = true;
        },

        closeBulkSlotModal() {
            this.bulkSlotModalOpen = false;
        },

        addExcludedDate() {
            const date = this.bulkSlotExcludeInput;
            if (!date) return;

            if (!this.bulkSlotForm.exclude_dates.includes(date)) {
                this.bulkSlotForm.exclude_dates.push(date);
            }
            this.bulkSlotExcludeInput = '';
        },

        removeExcludedDate(index) {
            this.bulkSlotForm.exclude_dates.splice(index, 1);
        },

        calculateBulkSlotCount() {
            if (!this.bulkSlotForm.start_date || !this.bulkSlotForm.end_date) return 0;
            if (!this.bulkSlotForm.opening_time || !this.bulkSlotForm.closing_time) return 0;
            if (!this.bulkSlotForm.slot_duration) return 0;

            const startDate = new Date(`${this.bulkSlotForm.start_date}T00:00`);
            const endDate = new Date(`${this.bulkSlotForm.end_date}T00:00`);
            if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) return 0;

            const excludeSet = new Set(this.bulkSlotForm.exclude_dates || []);
            let count = 0;
            const cursor = new Date(startDate.getTime());

            while (cursor.getTime() <= endDate.getTime()) {
                const dateKey = this.formatDateKey(cursor);
                if (!excludeSet.has(dateKey)) {
                    const slotStart = new Date(`${dateKey}T${this.bulkSlotForm.opening_time}`);
                    const slotEnd = new Date(`${dateKey}T${this.bulkSlotForm.closing_time}`);
                    if (!Number.isNaN(slotStart.getTime()) && !Number.isNaN(slotEnd.getTime())) {
                        let slotCursor = new Date(slotStart.getTime());
                        while (slotCursor.getTime() < slotEnd.getTime()) {
                            count += 1;
                            slotCursor = new Date(slotCursor.getTime() + (this.bulkSlotForm.slot_duration * 60000));
                        }
                    }
                }
                cursor.setDate(cursor.getDate() + 1);
            }

            return count;
        },

        formatDateKey(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        async generateBulkSlots() {
            if (!this.selectedStoreId) {
                Alpine.store('toast').addToast('Pilih toko terlebih dahulu.', 'error');
                return;
            }

            if (!this.bulkSlotForm.start_date || !this.bulkSlotForm.end_date) {
                Alpine.store('toast').addToast('Tanggal mulai dan akhir wajib diisi.', 'error');
                return;
            }

            if (!this.bulkSlotForm.opening_time || !this.bulkSlotForm.closing_time) {
                Alpine.store('toast').addToast('Jam operasional wajib diisi.', 'error');
                return;
            }

            this.bulkSlotLoading = true;
            try {
                const token = localStorage.getItem('token');
                const payload = {
                    store_id: this.selectedStoreId,
                    start_date: this.bulkSlotForm.start_date,
                    end_date: this.bulkSlotForm.end_date,
                    opening_time: this.bulkSlotForm.opening_time,
                    closing_time: this.bulkSlotForm.closing_time,
                    slot_duration: this.bulkSlotForm.slot_duration,
                    capacity_per_slot: this.bulkSlotForm.capacity_per_slot,
                    exclude_dates: this.bulkSlotForm.exclude_dates
                };

                const response = await fetch('/api/reservation-config/generate-slots', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (!response.ok) {
                    const errorMessage = data?.message || 'Gagal membuat slot.';
                    Alpine.store('toast').addToast(errorMessage, 'error');
                    return;
                }

                if (data.success) {
                    const slots = data.data?.slots || [];
                    const normalizedSlots = slots
                        .map(slot => (typeof slot === 'string' ? slot : slot?.datetime))
                        .filter(Boolean);
                    const capacityFromSlots = slots.find(slot => typeof slot === 'object' && slot?.capacity !== undefined)?.capacity;
                    const normalizedCapacity = Number(capacityFromSlots);

                    this.form.available_slots = normalizedSlots;
                    this.slotPaginationOffset = 10;

                    if (Number.isFinite(normalizedCapacity) && normalizedCapacity > 0) {
                        this.form.capacity_per_slot = normalizedCapacity;
                        this.bulkSlotForm.capacity_per_slot = normalizedCapacity;
                    }

                    Alpine.store('toast').addToast(`Berhasil membuat ${data.data?.count || 0} slot.`, 'success');
                    this.closeBulkSlotModal();
                } else {
                    Alpine.store('toast').addToast(data.message || 'Gagal membuat slot.', 'error');
                }
            } catch (error) {
                console.error('Error generating slots:', error);
                Alpine.store('toast').addToast('Terjadi kesalahan saat membuat slot.', 'error');
            } finally {
                this.bulkSlotLoading = false;
            }
        },



        copyLink() {
            navigator.clipboard.writeText(this.reservationLink);
            Alpine.store('toast').addToast('Link berhasil disalin!', 'success');
        },

        syncReservationFeeDisplay() {
            if (this.form.reservation_fee === null || this.form.reservation_fee === '') {
                this.reservationFeeDisplay = '';
                return;
            }
            this.reservationFeeDisplay = this.formatCurrency(this.form.reservation_fee);
        },

        handleReservationFeeInput(event) {
            const digits = event.target.value.replace(/[^0-9]/g, '');
            if (!digits) {
                this.form.reservation_fee = null;
                this.reservationFeeDisplay = '';
                return;
            }

            const numericValue = Number(digits);
            this.form.reservation_fee = numericValue;
            this.reservationFeeDisplay = this.formatCurrency(numericValue);
        },

        isDpPresetSelected(value) {
            const current = Number(this.form.dp_percentage);
            return Number.isFinite(current) && current === value;
        },

        // Dashboard base methods
        addNotification(notif) {
            notif.id = Date.now() + Math.random();
            this.notifications.unshift(notif);
            if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        clearNotifications() {
            this.notifications = [];
            localStorage.removeItem('notifications');
        },

        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        formatNotificationTime(timestamp) {
            let date = new Date(timestamp);
            let diff = Math.floor((new Date() - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return date.toLocaleDateString();
        },

        formatCurrency(amount) {
            const normalized = Number(amount || 0);
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(normalized);
        },

        formatSlotDisplay(slot) {
            if (!slot) return '';
            try {
                // Parse ISO datetime string and display in WIB 24-hour format
                const date = new Date(slot);
                if (isNaN(date.getTime())) return '';

                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');

                return `${day}/${month}/${year} ${hours}:${minutes} WIB`;
            } catch (e) {
                return '';
            }
        },

        logout() {
            let token = localStorage.getItem('token');
            if (token) {
                fetch(`${window.location.origin}/api/logout`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                }).finally(() => {
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    localStorage.removeItem('sidebarOpen');
                    localStorage.removeItem('notifications');
                    window.location.href = '/login';
                });
            } else {
                window.location.href = '/login';
            }
        }
    };
}

// Initialize Alpine store for toast
document.addEventListener('alpine:init', () => {
    Alpine.store('toast', {
        addToast(message, type) {
            const event = new CustomEvent('show-toast', {
                detail: { message, type }
            });
            document.dispatchEvent(event);
        }
    });
});

document.addEventListener('show-toast', (e) => {
    const container = document.querySelector('[x-data="toastManager()"]');
    if (container && container.__x) {
        container.__x.$data.addToast(e.detail.message, e.detail.type);
    }
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection
