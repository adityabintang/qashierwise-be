@extends('layouts.app')

@section('title', 'Konfigurasi Reservasi')

@section('content')
<!-- Toast Notification Container -->
<div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2" x-data="toastManager()">
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
    <div class="flex-1 flex flex-col overflow-y-auto">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => 'Konfigurasi Reservasi', 'description' => 'Atur pengaturan reservasi untuk setiap toko'])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl space-y-6">

                <!-- Top bar: breadcrumb + status -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="/dashboard/reservations" class="inline-flex items-center gap-2 rounded-lg border border-border/60 bg-background px-3 py-1.5 text-sm font-medium text-foreground shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-border hover:shadow">
                        <i class="fas fa-arrow-left text-xs text-muted-foreground"></i>
                        Kembali ke Reservasi
                    </a>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
                            <span class="h-1.5 w-1.5 rounded-full transition-colors" :class="selectedStoreId ? 'bg-emerald-500' : 'bg-zinc-300'"></span>
                            <span x-text="selectedStoreId ? 'Toko aktif' : 'Belum ada toko dipilih'"></span>
                        </span>
                        <span class="hidden sm:inline-block text-xs text-muted-foreground">Konfigurasi tersimpan per toko.</span>
                    </div>
                </div>

                <!-- Store Selector — Card -->
                <div class="rounded-xl border border-border/70 bg-card shadow-sm overflow-hidden">
                    <!-- Top row: title + dropdown -->
                    <div class="flex items-center justify-between gap-6 px-5 py-4 border-b border-border/60">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-600">
                                <i class="fas fa-store text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold leading-tight">Pilih Toko</p>
                                <p class="text-xs text-muted-foreground">Konfigurasi tersimpan per toko</p>
                            </div>
                        </div>
                        <div class="flex items-end gap-3 flex-shrink-0">
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1.5">Toko</label>
                                <select x-model="selectedStoreId" @change="loadConfig()" class="min-w-[220px] rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                                    <option value="">— Pilih Toko —</option>
                                    <template x-for="store in stores" :key="store.id">
                                        <option :value="store.id" x-text="store.name"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!-- Bottom row: stat cards -->
                    <div x-show="selectedStoreId" x-cloak class="grid grid-cols-3 gap-3 p-4">
                        <!-- Slot terjadwal -->
                        <div class="rounded-lg border border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                                    <i class="fas fa-calendar-check text-xs"></i>
                                </div>
                                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Slot Terjadwal</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold tabular-nums leading-none" x-text="form.available_slots.length"></span>
                                <span class="text-xs font-medium text-muted-foreground">slot</span>
                            </div>
                        </div>
                        <!-- Terbooking -->
                        <div class="rounded-lg border border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-500">
                                    <i class="fas fa-bookmark text-xs"></i>
                                </div>
                                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Terbooking</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold tabular-nums leading-none" x-text="bookedCapacity"></span>
                                <span class="text-xs text-muted-foreground" x-show="form.capacity_per_slot > 0">
                                    / <span x-text="form.capacity_per_slot"></span> kapasitas
                                </span>
                            </div>
                        </div>
                        <!-- Kapasitas meja -->
                        <div class="rounded-lg border border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-50 text-purple-500">
                                    <i class="fas fa-users text-xs"></i>
                                </div>
                                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Kapasitas Meja</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold tabular-nums leading-none" x-text="totalTableCapacity > 0 ? totalTableCapacity : '—'"></span>
                                <span class="text-xs font-medium text-muted-foreground" x-show="totalTableCapacity > 0">orang</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Link Publik: full-width banner -->
                <div x-show="selectedStoreId && userSlug" x-cloak class="rounded-xl border border-border/70 bg-card shadow-sm">
                    <div class="flex items-center gap-4 px-5 py-3.5 flex-wrap">
                        <div class="flex items-center gap-2.5 flex-shrink-0">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <i class="fas fa-link text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold leading-tight">Link Publik</p>
                                <p class="text-xs text-muted-foreground">Bagikan ke pelanggan</p>
                            </div>
                        </div>
                        <div class="flex flex-1 min-w-0 gap-2">
                            <input type="text" :value="reservationLink" readonly
                                   class="flex-1 min-w-0 rounded-lg border border-input bg-muted/30 px-3 py-2 text-xs text-muted-foreground truncate shadow-sm">
                            <button type="button" @click="copyLink()" class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-border/70 bg-background px-3 py-2 text-xs font-medium shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow">
                                <i class="fas fa-copy text-xs"></i>
                                Salin
                            </button>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 flex-shrink-0">Aktif</span>
                    </div>
                </div>

                <!-- Main grid: 2-2-1 layout -->
                <template x-if="selectedStoreId">
                    <form @submit.prevent="saveConfig()" class="flex flex-col gap-5">

                        <!-- Row 1: Payment + Time Slots -->
                        <div class="grid gap-5 md:grid-cols-2">

                            <!-- Payment Card -->
                            <div class="rounded-xl border border-border/70 bg-card shadow-sm flex flex-col">
                                <div class="border-b border-border/60 px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                            <i class="fas fa-credit-card text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold leading-tight">Skema Pembayaran</p>
                                            <p class="text-xs text-muted-foreground">Biaya dan opsi DP / lunas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-4 p-5 flex-1">
                                    <!-- Fee -->
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Biaya Reservasi (Rp)</label>
                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            x-model="reservationFeeDisplay"
                                            @input="handleReservationFeeInput($event)"
                                            placeholder="Rp 0"
                                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors"
                                        >
                                        <p class="text-xs text-muted-foreground">Dibayar sebelum reservasi aktif.</p>
                                        <p class="text-xs text-destructive" x-show="!isReservationFeeValid">Biaya reservasi wajib diisi.</p>
                                    </div>

                                    <!-- Capacity -->
                                    <div class="space-y-1.5">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kapasitas Per Slot</label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="100"
                                            x-model.number="form.capacity_per_slot"
                                            placeholder="12"
                                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors"
                                        >
                                        <p class="text-xs text-muted-foreground">Maksimal reservasi per slot waktu.</p>
                                    </div>

                                    <!-- Payment options -->
                                    <div class="space-y-2">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Metode Pembayaran</label>
                                        <label class="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5 cursor-pointer transition-colors hover:bg-muted/40">
                                            <input type="checkbox" x-model="form.allow_full_payment" id="allow_full" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
                                            <span class="flex-1">
                                                <span class="block text-sm font-medium">Pembayaran Penuh</span>
                                                <span class="block text-xs text-muted-foreground">Pelanggan melunasi sekaligus</span>
                                            </span>
                                        </label>
                                        <label class="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5 cursor-pointer transition-colors hover:bg-muted/40">
                                            <input type="checkbox" x-model="form.allow_dp_payment" id="allow_dp" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
                                            <span class="flex-1">
                                                <span class="block text-sm font-medium">Pembayaran DP</span>
                                                <span class="block text-xs text-muted-foreground">Bayar sebagian di awal</span>
                                            </span>
                                        </label>
                                        <p class="text-xs text-destructive" x-show="!hasValidPaymentType">Pilih minimal satu jenis pembayaran.</p>
                                    </div>

                                    <!-- DP Percentage -->
                                    <div x-show="form.allow_dp_payment" x-transition class="rounded-lg border border-border/60 bg-muted/20 p-3 space-y-2.5">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Persentase DP (%)</label>
                                        <div class="flex gap-1.5">
                                            <button type="button" @click="form.dp_percentage = 50"
                                                    class="rounded-full border px-3 py-1 text-xs font-semibold transition-all duration-150"
                                                    :class="isDpPresetSelected(50) ? 'border-purple-600 bg-purple-600 text-white' : 'border-border/70 bg-background text-muted-foreground hover:border-purple-400 hover:text-purple-600'">50%</button>
                                            <button type="button" @click="form.dp_percentage = 40"
                                                    class="rounded-full border px-3 py-1 text-xs font-semibold transition-all duration-150"
                                                    :class="isDpPresetSelected(40) ? 'border-purple-600 bg-purple-600 text-white' : 'border-border/70 bg-background text-muted-foreground hover:border-purple-400 hover:text-purple-600'">40%</button>
                                            <button type="button" @click="form.dp_percentage = 20"
                                                    class="rounded-full border px-3 py-1 text-xs font-semibold transition-all duration-150"
                                                    :class="isDpPresetSelected(20) ? 'border-purple-600 bg-purple-600 text-white' : 'border-border/70 bg-background text-muted-foreground hover:border-purple-400 hover:text-purple-600'">20%</button>
                                        </div>
                                        <input type="number" x-model.number="form.dp_percentage"
                                               min="0" max="100" step="0.01"
                                               class="mt-3 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                                        <p class="text-xs text-muted-foreground">Default: 50%</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Slot Card -->
                                    <div class="rounded-xl border border-border/70 bg-card shadow-sm flex flex-col">
                                        <div class="border-b border-border/60 px-5 py-4">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                                    <i class="fas fa-calendar-alt text-sm"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold leading-tight">Tanggal &amp; Jam Tersedia</p>
                                                    <p class="text-xs text-muted-foreground">Atur slot waktu untuk pelanggan</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-3 p-5 flex-1">
                                            <div class="space-y-2 max-h-80 overflow-y-auto pr-0.5">
                                                <template x-if="form.available_slots.length === 0">
                                                    <div class="flex flex-col items-center justify-center py-8 text-center">
                                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-muted/40 mb-3">
                                                            <i class="fas fa-calendar-plus text-muted-foreground"></i>
                                                        </div>
                                                        <p class="text-sm text-muted-foreground">Belum ada slot waktu</p>
                                                        <p class="text-xs text-muted-foreground/70 mt-1">Buat slot menggunakan Bulk Generator</p>
                                                    </div>
                                                </template>
                                                <template x-for="(slot, index) in displayedSlots" :key="index">
                                                    <div class="flex items-center gap-2 group">
                                                        <div class="flex-1 relative">
                                                            <div class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm flex items-center justify-between cursor-pointer relative z-0 group-hover:border-border transition-colors">
                                                                <span x-text="formatSlotDisplay(slot)" class="text-foreground text-sm font-medium"></span>
                                                                <i class="fas fa-calendar-alt text-muted-foreground/60 text-xs"></i>
                                                            </div>
                                                            <input type="datetime-local"
                                                                   x-model="form.available_slots[index]"
                                                                   :min="new Date().toISOString().slice(0, 16)"
                                                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                                        </div>
                                                        <button type="button" @click="removeSlot(index)" class="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-red-400 transition-all duration-200 hover:border-red-200 hover:bg-red-50 hover:text-red-600">
                                                            <i class="fas fa-trash text-xs"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            <template x-if="hasMoreSlots">
                                                <button type="button" @click="loadMoreSlots()" class="w-full rounded-lg border border-dashed border-border/70 bg-transparent py-2 text-xs font-medium text-muted-foreground transition-all duration-200 hover:border-border hover:bg-muted/30">
                                                    <i class="fas fa-chevron-down mr-1"></i>
                                                    Tampilkan lebih banyak (<span x-text="remainingSlots"></span> slot)
                                                </button>
                                            </template>
                                            <template x-if="form.available_slots.length > 0">
                                                <p class="text-center text-xs text-muted-foreground/70">Menampilkan <span x-text="displayedSlots.length"></span> dari <span x-text="form.available_slots.length"></span> slot</p>
                                            </template>
                                            <button type="button" @click="openBulkSlotModal()" class="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-purple-700 hover:shadow">
                                                <i class="fas fa-layer-group text-xs"></i>
                                                Bulk Time Slot Generator
                                            </button>
                                        </div>
                                    </div>

                                </div>
                                <!-- /Row 1 -->

                                <!-- Row 2: Tables + Menu side by side -->
                                <div class="grid gap-5 md:grid-cols-2">

                                    <!-- Tables Card -->
                                    <div class="rounded-xl border border-border/70 bg-card shadow-sm flex flex-col">
                                        <div class="border-b border-border/60 px-5 py-4">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                                                    <i class="fas fa-chair text-sm"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold leading-tight">Pengaturan Meja</p>
                                                    <p class="text-xs text-muted-foreground">Pilih meja yang bisa dipesan</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-5 flex-1">
                                            <div x-show="loadingTables" class="flex flex-col items-center justify-center py-8 gap-2">
                                                <i class="fas fa-circle-notch fa-spin text-muted-foreground"></i>
                                                <p class="text-xs text-muted-foreground">Memuat data meja...</p>
                                            </div>
                                            <div x-show="!loadingTables" class="space-y-2">
                                                <div class="flex items-center justify-between pb-1 border-b border-border/50">
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="checkbox" @change="toggleAllTables($event.target.checked)" :checked="allTablesSelected" id="select-all-tables" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
                                                        <span class="text-sm font-medium">Pilih Semua</span>
                                                    </label>
                                                    <button type="button" @click="deselectAllTables()" class="text-xs text-muted-foreground hover:text-destructive transition-colors">Hapus Semua</button>
                                                </div>
                                                <div class="max-h-52 overflow-y-auto space-y-1 pr-0.5">
                                                    <template x-if="tables.length === 0">
                                                        <p class="text-sm text-muted-foreground text-center py-6">Tidak ada meja tersedia</p>
                                                    </template>
                                                    <template x-for="table in tables" :key="table.id">
                                                        <label class="flex items-center gap-2.5 rounded-lg border border-transparent px-2.5 py-2 cursor-pointer transition-all duration-150 hover:border-border/60 hover:bg-muted/30">
                                                            <input type="checkbox" :value="table.id" x-model="form.available_tables" :id="'table-'+table.id" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
                                                            <span class="text-sm" x-text="'Meja ' + table.number + ' — ' + table.capacity + ' orang'"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Menu Card -->
                                    <div class="rounded-xl border border-border/70 bg-card shadow-sm flex flex-col">
                                        <div class="border-b border-border/60 px-5 py-4">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-600">
                                                    <i class="fas fa-utensils text-sm"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold leading-tight">Pengaturan Menu</p>
                                                    <p class="text-xs text-muted-foreground">Menu yang ditampilkan saat reservasi</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="p-5 flex-1 flex flex-col gap-3">
                                            <label class="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5 cursor-pointer transition-colors hover:bg-muted/40">
                                                <input type="checkbox" x-model="form.enable_menu_selection" id="enable_menu" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
                                                <span class="text-sm font-medium">Aktifkan pilihan menu</span>
                                            </label>
                                            <div x-show="form.enable_menu_selection" x-transition class="flex flex-col gap-3">
                                                <label class="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5 cursor-pointer transition-colors hover:bg-muted/40">
                                                    <input type="checkbox" x-model="form.require_menu_selection" id="require_menu" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-sm text-muted-foreground">Wajibkan pilih menu</span>
                                                </label>
                                                <div x-show="loadingProducts" class="flex flex-col items-center justify-center py-8 gap-2">
                                                    <i class="fas fa-circle-notch fa-spin text-muted-foreground"></i>
                                                    <p class="text-xs text-muted-foreground">Memuat data menu...</p>
                                                </div>
                                                <div x-show="!loadingProducts" class="space-y-2">
                                                    <div class="flex items-center justify-between pb-1 border-b border-border/50">
                                                        <label class="flex items-center gap-2 cursor-pointer">
                                                            <input type="checkbox" @change="toggleAllProducts($event.target.checked)" :checked="allProductsSelected" id="select-all-products" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
                                                            <span class="text-sm font-medium">Pilih Semua</span>
                                                        </label>
                                                        <button type="button" @click="deselectAllProducts()" class="text-xs text-muted-foreground hover:text-destructive transition-colors">Hapus Semua</button>
                                                    </div>
                                                    <div class="max-h-52 overflow-y-auto space-y-1 pr-0.5">
                                                        <template x-if="products.length === 0">
                                                            <p class="text-sm text-muted-foreground text-center py-6">Tidak ada produk tersedia</p>
                                                        </template>
                                                        <template x-for="product in products" :key="product.id">
                                                            <label class="flex items-center gap-2.5 rounded-lg border border-transparent px-2.5 py-2 cursor-pointer transition-all duration-150 hover:border-border/60 hover:bg-muted/30">
                                                                <input type="checkbox" :value="product.id" x-model="form.available_products" :id="'product-'+product.id" class="h-4 w-4 rounded border-input text-emerald-600 focus:ring-emerald-500">
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
                                </div>
                                <!-- /Row 2 -->

                                <!-- Row 3: Reminder (full width) -->
                                <div class="rounded-xl border border-border/70 bg-card shadow-sm overflow-hidden">
                                    <!-- Header -->
                                    <div class="flex items-center justify-between gap-4 border-b border-border/60 px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-green-500 text-white shadow-sm">
                                                <i class="fab fa-whatsapp text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="text-base font-semibold leading-tight">Pengingat Otomatis WhatsApp</p>
                                                <p class="text-xs text-muted-foreground mt-0.5">Kelola pengaturan notifikasi otomatis untuk reservasi pelanggan</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 flex-shrink-0">
                                            <!-- Status toggle -->
                                            <label for="reminder_toggle" class="relative inline-flex cursor-pointer items-center gap-2.5 flex-shrink-0">
                                                <span class="text-sm font-medium" :class="form.reminder_enabled ? 'text-foreground' : 'text-muted-foreground'">Status Aktif</span>
                                                <input type="checkbox" x-model="form.reminder_enabled" id="reminder_toggle" class="sr-only">
                                                <span class="relative inline-block w-11 h-6">
                                                    <span class="block w-11 h-6 rounded-full border-2 transition-all duration-200"
                                                          :class="form.reminder_enabled ? 'border-purple-600 bg-purple-600' : 'border-border/60 bg-muted'"></span>
                                                    <span class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform duration-200"
                                                          :class="form.reminder_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                                </span>
                                            </label>
                                            <!-- Test send button -->
                                            <button type="button" x-show="form.reminder_enabled" x-cloak
                                                    @click="testMessageModal = true"
                                                    class="inline-flex items-center gap-2 rounded-lg border border-purple-400 bg-background px-4 py-2 text-sm font-medium text-purple-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-purple-500 hover:shadow">
                                                <i class="fas fa-paper-plane text-xs"></i>
                                                Kirim Pesan Tes
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Disabled state -->
                                    <div x-show="!form.reminder_enabled" class="flex items-center gap-4 px-6 py-8">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-muted/40">
                                            <i class="fas fa-bell-slash text-muted-foreground"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">Pengingat dinonaktifkan</p>
                                            <p class="text-xs text-muted-foreground mt-0.5">Aktifkan untuk mengkonfigurasi pengiriman pesan otomatis sebelum waktu reservasi.</p>
                                        </div>
                                    </div>

                                    <!-- Enabled body: 3-col -->
                                    <div x-show="form.reminder_enabled" x-transition class="grid md:grid-cols-3 gap-px bg-border/40">

                                        <!-- Col 1: Template + Language + Timing -->
                                        <div class="p-6 space-y-5 bg-card">
                                            <!-- Template -->
                                            <div class="space-y-1.5">
                                                <label class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Template WhatsApp</label>
                                                <div x-show="loadingTemplates" class="flex items-center gap-2 py-2">
                                                    <i class="fas fa-circle-notch fa-spin text-muted-foreground text-xs"></i>
                                                    <p class="text-xs text-muted-foreground">Memuat template...</p>
                                                </div>
                                                <div x-show="!loadingTemplates">
                                                    <select x-model="form.reminder_template" @change="onTemplateChange()" class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                                                        <option value="">Pilih template...</option>
                                                        <template x-for="template in templates" :key="template.name">
                                                            <option :value="template.name" x-text="template.name + ' (' + (template.body_examples?.length || 0) + ' params)'"></option>
                                                        </template>
                                                    </select>
                                                    <p class="text-xs text-muted-foreground mt-1.5" x-show="form.reminder_template === ''">Hanya menampilkan template dengan parameter</p>
                                                </div>
                                            </div>

                                            <!-- Language -->
                                            <div x-show="form.reminder_template" class="space-y-1.5">
                                                <label class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Bahasa Template</label>
                                                <select x-model="form.reminder_template_language" class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                                                    <option value="id">Bahasa Indonesia (id)</option>
                                                    <option value="en">English (en)</option>
                                                </select>
                                            </div>

                                            <!-- Timing -->
                                            <div x-show="form.reminder_template" class="space-y-2">
                                                <label class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Waktu Pengingat</label>
                                                <label class="flex items-center gap-2.5 cursor-pointer group">
                                                    <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border-2 transition-all duration-150"
                                                          :class="form.reminder_timing.includes('1440') ? 'border-purple-600 bg-purple-600' : 'border-border/60 bg-background group-hover:border-purple-300'">
                                                        <i class="fas fa-check text-[9px] text-white" x-show="form.reminder_timing.includes('1440')"></i>
                                                    </span>
                                                    <input type="checkbox" value="1440" x-model="form.reminder_timing" class="sr-only">
                                                    <span class="text-sm">24 jam sebelumnya</span>
                                                </label>
                                                <label class="flex items-center gap-2.5 cursor-pointer group">
                                                    <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border-2 transition-all duration-150"
                                                          :class="form.reminder_timing.includes('120') ? 'border-purple-600 bg-purple-600' : 'border-border/60 bg-background group-hover:border-purple-300'">
                                                        <i class="fas fa-check text-[9px] text-white" x-show="form.reminder_timing.includes('120')"></i>
                                                    </span>
                                                    <input type="checkbox" value="120" x-model="form.reminder_timing" class="sr-only">
                                                    <span class="text-sm">2 jam sebelumnya</span>
                                                </label>
                                                <label class="flex items-center gap-2.5 cursor-pointer group">
                                                    <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border-2 transition-all duration-150"
                                                          :class="form.reminder_timing.includes('60') ? 'border-purple-600 bg-purple-600' : 'border-border/60 bg-background group-hover:border-purple-300'">
                                                        <i class="fas fa-check text-[9px] text-white" x-show="form.reminder_timing.includes('60')"></i>
                                                    </span>
                                                    <input type="checkbox" value="60" x-model="form.reminder_timing" class="sr-only">
                                                    <span class="text-sm">1 jam sebelumnya</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Col 2: Parameter Mapping -->
                                        <div class="p-6 space-y-4 bg-card">
                                            <div>
                                                <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Pemetaan Parameter</p>
                                                <p class="text-xs text-muted-foreground mt-1">Hubungkan variabel template WhatsApp ke data sistem Anda secara dinamis.</p>
                                            </div>

                                            <div x-show="currentTemplateParams.length > 0" class="space-y-3">
                                                <template x-for="(param, index) in currentTemplateParams" :key="index">
                                                    <div class="flex items-center gap-3">
                                                        <span class="inline-flex items-center rounded-md bg-purple-50 border border-purple-200 px-2.5 py-1.5 font-mono text-xs font-semibold text-purple-600 whitespace-nowrap flex-shrink-0"
                                                              x-text="'{{ ' + param + ' }}'"></span>
                                                        <i class="fas fa-arrow-right text-xs text-muted-foreground/50 flex-shrink-0"></i>
                                                        <select x-model="form.reminder_param_mapping[param]"
                                                                class="flex-1 min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                                                            <option value="">Pilih field sumber...</option>
                                                            <option value="customer_name">Nama Pelanggan</option>
                                                            <option value="customer_phone">Nomor WhatsApp</option>
                                                            <option value="customer_email">Email Pelanggan</option>
                                                            <option value="reservation_datetime">Tanggal &amp; Jam Reservasi</option>
                                                            <option value="reservation_date">Tanggal Reservasi</option>
                                                            <option value="reservation_time">Jam Reservasi</option>
                                                            <option value="guest_count">Jumlah Tamu</option>
                                                            <option value="table_name">Nama Meja</option>
                                                            <option value="store_name">Nama Toko</option>
                                                            <option value="store_address">Alamat Toko</option>
                                                            <option value="reservation_code">Kode Reservasi</option>
                                                            <option value="status">Status Reservasi</option>
                                                            <option value="payment_type">Jenis Pembayaran</option>
                                                            <option value="total_amount">Total Tagihan</option>
                                                            <option value="paid_amount">Jumlah Dibayar</option>
                                                            <option value="remaining_amount">Sisa Tagihan</option>
                                                            <option value="reservation_notes">Catatan Reservasi</option>
                                                        </select>
                                                    </div>
                                                </template>
                                            </div>

                                            <div x-show="currentTemplateParams.length === 0 && form.reminder_template" class="flex items-start gap-2.5 rounded-lg border border-border/60 bg-muted/30 p-3">
                                                <i class="fas fa-info-circle text-xs text-muted-foreground mt-0.5 flex-shrink-0"></i>
                                                <p class="text-xs text-muted-foreground">Template ini tidak memiliki parameter yang perlu dipetakan.</p>
                                            </div>

                                            <div x-show="currentTemplateParams.length > 0" class="flex items-start gap-2.5 rounded-lg border border-blue-100 bg-blue-50/60 p-3">
                                                <i class="fas fa-info-circle text-xs text-blue-400 mt-0.5 flex-shrink-0"></i>
                                                <p class="text-xs text-blue-600/80">Sistem akan menarik data real-time dari setiap reservasi untuk mengisi parameter di atas. Pastikan field yang dipilih sesuai dengan format template WhatsApp Anda.</p>
                                            </div>
                                        </div>

                                        <!-- Col 3: Message Preview (phone mockup) -->
                                        <div class="p-6 flex flex-col bg-card">
                                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-4">Pratinjau Pesan</p>
                                            <!-- Phone frame -->
                                            <div class="w-full rounded-[32px] border-[7px] border-slate-800 bg-slate-800 shadow-2xl overflow-hidden">
                                                <!-- Status bar -->
                                                <div class="bg-slate-800 px-5 pt-2.5 pb-1.5 flex items-center justify-between">
                                                    <span class="text-xs text-white font-semibold">9:41</span>
                                                    <div class="flex items-center gap-1.5">
                                                        <i class="fas fa-signal text-white text-[10px]"></i>
                                                        <i class="fas fa-wifi text-white text-[10px]"></i>
                                                        <i class="fas fa-battery-three-quarters text-white text-[10px]"></i>
                                                    </div>
                                                </div>
                                                <!-- Chat area -->
                                                <div class="bg-[#e5ddd5]">
                                                    <!-- WA Header bar -->
                                                    <div class="bg-[#075e54] px-4 py-2.5 flex items-center gap-2.5">
                                                        <div class="h-8 w-8 rounded-full bg-green-400 flex items-center justify-center flex-shrink-0">
                                                            <i class="fas fa-store text-white text-xs"></i>
                                                        </div>
                                                        <span class="text-white text-sm font-semibold truncate">Nama Toko</span>
                                                    </div>
                                                    <!-- Messages -->
                                                    <div class="px-3 py-4 space-y-2 min-h-[280px]">
                                                        <template x-if="form.reminder_template">
                                                            <div class="ml-auto max-w-[88%] rounded-xl rounded-tr-none bg-[#dcf8c6] px-3.5 py-2.5 shadow-sm">
                                                                <p class="text-xs leading-relaxed text-slate-800" x-html="getPreviewBody() || '<em style=\'color:#9ca3af\'>Pratinjau pesan akan muncul di sini...</em>'"></p>
                                                                <p class="text-right text-[10px] text-slate-500 mt-1.5">14:20 ✓✓</p>
                                                            </div>
                                                        </template>
                                                        <template x-if="!form.reminder_template">
                                                            <div class="flex items-center justify-center h-[240px]">
                                                                <p class="text-xs text-slate-500 text-center">Pilih template untuk<br>melihat pratinjau</p>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <!-- Input bar -->
                                                    <div class="bg-[#f0f0f0] px-3 py-2 flex items-center gap-2">
                                                        <div class="flex-1 rounded-full bg-white px-3 py-1.5">
                                                            <span class="text-xs text-slate-400">Ketik pesan...</span>
                                                        </div>
                                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#25d366]">
                                                            <i class="fas fa-microphone text-white text-xs"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- /Row 3 -->

                                <!-- Save Bar -->
                                <div class="flex items-center justify-between gap-4 rounded-xl border border-border/70 bg-card px-5 py-4 shadow-sm">
                                    <p class="text-xs text-muted-foreground hidden sm:block">Pastikan slot dan menu sesuai kapasitas operasional.</p>
                                    <div class="flex gap-2.5 ml-auto">
                                        <button type="button" @click="loadConfig()" class="inline-flex items-center gap-2 rounded-lg border border-border/70 bg-background px-4 py-2 text-sm font-medium shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow">
                                            <i class="fas fa-undo text-xs"></i>
                                            Reset
                                        </button>
                                        <button type="submit" :disabled="saving || !hasValidPaymentType || !isReservationFeeValid"
                                                class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-purple-700 hover:shadow disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0">
                                            <i class="fas fa-save text-xs" :class="{ 'fa-spin fa-circle-notch': saving }"></i>
                                            <span x-text="saving ? 'Menyimpan...' : 'Simpan Konfigurasi'"></span>
                                        </button>
                                    </div>
                                </div>

                            </form>
                        </template>

                        <template x-if="!selectedStoreId">
                            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-card py-20 text-center shadow-sm">
                                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-border/70 bg-muted/30">
                                    <i class="fas fa-store text-xl text-muted-foreground"></i>
                                </div>
                                <p class="text-sm font-semibold">Pilih toko untuk mulai konfigurasi</p>
                                <p class="text-xs text-muted-foreground mt-1.5 max-w-xs">Data konfigurasi tersimpan secara independen per lokasi toko.</p>
                            </div>
                        </template>

            </div>
        </main>
        <!-- Test Message Modal -->
        <div x-show="testMessageModal" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4 py-8">
            <div class="absolute inset-0" @click="testMessageModal = false"></div>
            <div x-show="testMessageModal"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                 class="relative w-full max-w-md overflow-hidden rounded-2xl border border-border/70 bg-background shadow-2xl">
                <div class="flex items-center justify-between gap-4 border-b border-border/60 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-600">
                            <i class="fab fa-whatsapp text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold">Kirim Pesan Tes</h3>
                            <p class="text-xs text-muted-foreground">Kirim pesan WhatsApp percobaan ke nomor tujuan</p>
                        </div>
                    </div>
                    <button type="button" @click="testMessageModal = false" class="flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-muted-foreground transition-colors hover:border-border/60 hover:bg-muted/40">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Nomor WhatsApp Tujuan</label>
                        <input type="tel" x-model="testMessagePhone"
                               placeholder="Contoh: 6281234567890"
                               class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500/40 focus:border-green-400 transition-colors">
                        <p class="text-xs text-muted-foreground">Gunakan format internasional tanpa tanda + (misal: 628123456789)</p>
                    </div>
                    <div x-show="form.reminder_template" class="rounded-lg border border-border/60 bg-muted/30 px-4 py-3 space-y-1">
                        <p class="text-xs font-medium text-muted-foreground">Template:</p>
                        <p class="text-sm font-semibold" x-text="form.reminder_template || '-'"></p>
                        <p class="text-xs text-muted-foreground">Parameter akan diisi dengan nilai contoh dari template.</p>
                    </div>
                    <div x-show="!form.reminder_template" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs text-amber-700"><i class="fas fa-exclamation-triangle mr-1"></i>Pilih template WhatsApp terlebih dahulu sebelum mengirim pesan tes.</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-border/60 px-6 py-4">
                    <button type="button" @click="testMessageModal = false"
                            class="inline-flex items-center gap-2 rounded-lg border border-border/60 bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-all duration-200 hover:bg-muted/40">
                        Batal
                    </button>
                    <button type="button" @click="sendTestMessage()"
                            :disabled="sendingTestMessage || !form.reminder_template || !testMessagePhone"
                            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-circle-notch fa-spin text-xs" x-show="sendingTestMessage"></i>
                        <i class="fas fa-paper-plane text-xs" x-show="!sendingTestMessage"></i>
                        <span x-text="sendingTestMessage ? 'Mengirim...' : 'Kirim Sekarang'"></span>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="bulkSlotModalOpen" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4 py-8">
            <div class="absolute inset-0" @click="closeBulkSlotModal()"></div>
            <div x-show="bulkSlotModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                 class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-border/70 bg-background shadow-2xl">
                <div class="flex items-center justify-between gap-4 border-b border-border/60 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
                            <i class="fas fa-layer-group text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold">Bulk Time Slot Generator</h3>
                            <p class="text-xs text-muted-foreground">Buat slot waktu untuk banyak tanggal sekaligus</p>
                        </div>
                    </div>
                    <button type="button" @click="closeBulkSlotModal()" class="flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-muted-foreground transition-colors hover:border-border/60 hover:bg-muted/40">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <form @submit.prevent="generateBulkSlots()" class="space-y-5 px-6 py-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <!-- Date Range -->
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-purple-600">Rentang Tanggal</p>
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-muted-foreground">Tanggal Mulai</label>
                                <input type="date" x-model="bulkSlotForm.start_date" class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-muted-foreground">Tanggal Akhir</label>
                                <input type="date" x-model="bulkSlotForm.end_date" class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                            </div>
                        </div>
                        <!-- Operating Hours -->
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-purple-600">Jam Operasional (WIB / GMT+7)</p>
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-muted-foreground">Jam Buka</label>
                                <input type="text" inputmode="numeric" placeholder="17:00" x-model="bulkSlotForm.opening_time" class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-muted-foreground">Jam Tutup</label>
                                <input type="text" inputmode="numeric" placeholder="23:00" x-model="bulkSlotForm.closing_time" class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                            </div>
                            <p class="text-xs text-muted-foreground">Format 24 jam. Contoh: 17:00 – 23:00</p>
                        </div>
                        <!-- Capacity -->
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-purple-600">Kapasitas Per Slot</p>
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-muted-foreground">Jumlah maksimal reservasi per slot</label>
                                <input type="number" min="1" max="100" x-model.number="bulkSlotForm.capacity_per_slot" class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                            </div>
                        </div>
                        <!-- Slot Duration -->
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-purple-600">Durasi Per Slot</p>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" @click="bulkSlotForm.slot_duration = 30"
                                        class="rounded-xl border py-2.5 text-sm font-semibold transition-all duration-150"
                                        :class="bulkSlotForm.slot_duration === 30 ? 'border-purple-600 bg-purple-600 text-white shadow-sm' : 'border-border/70 bg-background text-muted-foreground hover:border-purple-300 hover:text-purple-600'">30 min</button>
                                <button type="button" @click="bulkSlotForm.slot_duration = 60"
                                        class="rounded-xl border py-2.5 text-sm font-semibold transition-all duration-150"
                                        :class="bulkSlotForm.slot_duration === 60 ? 'border-purple-600 bg-purple-600 text-white shadow-sm' : 'border-border/70 bg-background text-muted-foreground hover:border-purple-300 hover:text-purple-600'">1 jam</button>
                                <button type="button" @click="bulkSlotForm.slot_duration = 120"
                                        class="rounded-xl border py-2.5 text-sm font-semibold transition-all duration-150"
                                        :class="bulkSlotForm.slot_duration === 120 ? 'border-purple-600 bg-purple-600 text-white shadow-sm' : 'border-border/70 bg-background text-muted-foreground hover:border-purple-300 hover:text-purple-600'">2 jam</button>
                            </div>
                        </div>
                    </div>

                    <!-- Exclude Dates -->
                    <div class="rounded-xl border border-dashed border-purple-200 bg-purple-50/50 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-purple-600">Tanggal Dikecualikan</p>
                            <span class="text-xs text-muted-foreground">Opsional</span>
                        </div>
                        <div class="flex gap-2">
                            <input type="date" x-model="bulkSlotExcludeInput" class="flex-1 min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 transition-colors">
                            <button type="button" @click="addExcludedDate()" class="inline-flex items-center gap-1.5 rounded-lg border border-border/70 bg-background px-3 py-2 text-xs font-medium shadow-sm transition-all hover:-translate-y-0.5 hover:shadow">
                                <i class="fas fa-plus text-xs"></i>
                                Tambah
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2" x-show="bulkSlotForm.exclude_dates.length > 0">
                            <template x-for="(date, index) in bulkSlotForm.exclude_dates" :key="date">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-white px-2.5 py-1 text-xs font-medium text-purple-700">
                                    <span x-text="date"></span>
                                    <button type="button" @click="removeExcludedDate(index)" class="text-purple-400 hover:text-purple-700 transition-colors">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between gap-3 border-t border-border/60 pt-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-bold tabular-nums text-foreground" x-text="bulkSlotPreviewCount"></span>
                            <span class="text-sm text-muted-foreground">slot akan dibuat</span>
                        </div>
                        <div class="flex gap-2.5">
                            <button type="button" @click="closeBulkSlotModal()" class="inline-flex items-center gap-2 rounded-lg border border-border/70 bg-background px-4 py-2 text-sm font-medium shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow">
                                Batal
                            </button>
                            <button type="submit" :disabled="bulkSlotLoading" class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-purple-700 hover:shadow disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0">
                                <i class="fas text-xs" :class="bulkSlotLoading ? 'fa-circle-notch fa-spin' : 'fa-wand-magic-sparkles'"></i>
                                <span x-text="bulkSlotLoading ? 'Memproses...' : 'Generate Semua Slot'"></span>
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
        loadingTemplates: false,
        templates: [],
        currentTemplateParams: [],
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
            require_menu_selection: false,
            // Reminder fields
            reminder_enabled: false,
            reminder_template: '',
            reminder_template_language: 'id',
            reminder_param_mapping: {},
            reminder_timing: []
        },
        reservationFeeDisplay: '',
        bulkSlotModalOpen: false,
        testMessageModal: false,
        testMessagePhone: '',
        sendingTestMessage: false,
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

            // Watch for store selection changes and load config automatically
            this.$watch('selectedStoreId', (value) => {
                console.log('Store changed to:', value);
                if (value) {
                    localStorage.setItem('selectedReservationStoreId', value);
                    this.loadConfig();
                }
            });

            // Restore previously selected store from localStorage
            const savedStoreId = localStorage.getItem('selectedReservationStoreId');
            console.log('Saved store ID:', savedStoreId);
            console.log('Stores loaded:', this.stores);
            if (savedStoreId && this.stores.length > 0) {
                // Check if the saved store still exists
                const storeExists = this.stores.some(s => s.id == savedStoreId);
                console.log('Store exists:', storeExists);
                if (storeExists) {
                    this.selectedStoreId = savedStoreId;
                }
            }
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

        async loadTemplates() {
            this.loadingTemplates = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch('/api/whatsapp/templates?status=APPROVED', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load templates');

                const data = await response.json();
                if (data.success) {
                    // Get all templates first for debugging
                    const allTemplates = data.data || [];
                    console.log('All templates:', allTemplates.map(t => ({ name: t.name, body_examples: t.body_examples })));

                    // Only show templates that have body parameters
                    this.templates = allTemplates.filter(t => t.body_examples && t.body_examples.length > 0);
                    console.log('Filtered templates:', this.templates.map(t => t.name));
                    console.log('Looking for template:', this.form.reminder_template);

                    // If a template is already selected, extract its params
                    if (this.form.reminder_template) {
                        setTimeout(() => {
                            this.onTemplateChange();
                        }, 100);
                    }
                }
            } catch (error) {
                console.error('Error loading templates:', error);
            } finally {
                this.loadingTemplates = false;
            }
        },

        onTemplateChange() {
            const template = this.templates.find(t => t.name === this.form.reminder_template);
            if (template) {
                // Extract actual param names/keys from template body
                // Use RegExp constructor to avoid Blade parsing {{}} as PHP
                const varRegex = new RegExp('{' + '{' + '([^}]+)' + '}' + '}', 'g');
                const body = template.body || '';
                const params = [];
                let m;
                while ((m = varRegex.exec(body)) !== null) {
                    params.push(m[1]); // e.g. 'nama', 'order_id', or '1', '2'
                }
                // Fallback to counting body_examples if body is unavailable
                if (params.length === 0) {
                    const paramCount = template.body_examples?.length || 0;
                    for (let i = 1; i <= paramCount; i++) { params.push(String(i)); }
                }
                this.currentTemplateParams = params;

                // Rebuild mapping with only current template's params, preserving existing values
                const prevMapping = this.form.reminder_param_mapping;
                const freshMapping = {};
                this.currentTemplateParams.forEach(param => {
                    freshMapping[param] = prevMapping[param] || '';
                });
                this.form.reminder_param_mapping = freshMapping;
            } else {
                this.currentTemplateParams = [];
                this.form.reminder_param_mapping = {};
            }
        },

        getPreviewBody() {
            const template = this.templates.find(t => t.name === this.form.reminder_template);
            if (!template) return null;

            const fieldLabels = {
                customer_name: 'Nama Pelanggan',
                customer_phone: 'Nomor WhatsApp',
                customer_email: 'Email Pelanggan',
                reservation_datetime: 'Tanggal & Jam Reservasi',
                reservation_date: 'Tanggal Reservasi',
                reservation_time: 'Jam Reservasi',
                guest_count: 'Jumlah Tamu',
                table_name: 'Nama Meja',
                store_name: 'Nama Toko',
                store_address: 'Alamat Toko',
                reservation_code: 'Kode Reservasi',
                status: 'Status Reservasi',
                payment_type: 'Jenis Pembayaran',
                total_amount: 'Total Tagihan',
                paid_amount: 'Jumlah Dibayar',
                remaining_amount: 'Sisa Tagihan',
                reservation_notes: 'Catatan Reservasi',
            };

            let body = template.body || '';

            // Replace each placeholder with the mapped field label or a gray placeholder
            // Note: split with variable avoids Blade compiling curly braces as PHP
            this.currentTemplateParams.forEach((param, index) => {
                const mappedField = this.form.reminder_param_mapping[param];
                const label = mappedField ? (fieldLabels[mappedField] || mappedField) : ('param_' + (index + 1));
                const style = mappedField
                    ? 'background:#bbf7d0;color:#166534;font-weight:700;padding:1px 5px;border-radius:4px;font-size:0.8em;'
                    : 'background:#f3f4f6;color:#9ca3af;font-weight:600;padding:1px 5px;border-radius:4px;font-size:0.8em;border:1px dashed #d1d5db;';
                // Use the actual param name/key as the placeholder (works for both {{1}} and {{nama}})
                const placeholder = '{' + '{' + param + '}' + '}';
                body = body.split(placeholder).join('<span style="' + style + '">' + label + '</span>');
            });

            return body || null;
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
            console.log('loadConfig called with storeId:', this.selectedStoreId);
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
                console.log('API Response:', data);
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
                        require_menu_selection: config.require_menu_selection || false,
                        // Reminder fields
                        reminder_enabled: config.reminder_enabled || false,
                        reminder_template: config.reminder_template || '',
                        reminder_template_language: config.reminder_template_language || 'id',
                        reminder_param_mapping: config.reminder_param_mapping || {},
                        reminder_timing: config.reminder_timing || []
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
                        require_menu_selection: false,
                        // Reminder fields
                        reminder_enabled: false,
                        reminder_template: '',
                        reminder_template_language: 'id',
                        reminder_param_mapping: {},
                        reminder_timing: []
                    };
                }
                this.syncReservationFeeDisplay();
                // Reload tables and products for the selected store
                await this.loadTables();
                await this.loadProducts();
                await this.loadReservationStats();
                await this.loadTemplates();
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

                // Filter reminder_param_mapping to only include keys for the current template's params
                const filteredMapping = {};
                this.currentTemplateParams.forEach(param => {
                    filteredMapping[param] = this.form.reminder_param_mapping[param] || '';
                });

                const payload = {
                    ...this.form,
                    store_id: this.selectedStoreId,
                    reminder_param_mapping: filteredMapping
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
                    // Check for validation errors (422 response)
                    if (data.errors && typeof data.errors === 'object') {
                        // Build error message from validation errors
                        const errorMessages = [];
                        for (const [field, messages] of Object.entries(data.errors)) {
                            if (Array.isArray(messages)) {
                                errorMessages.push(...messages);
                            } else {
                                errorMessages.push(messages);
                            }
                        }
                        const errorMessage = errorMessages.join(' ');
                        Alpine.store('toast').addToast(errorMessage, 'error');
                    } else {
                        const errorMessage = data?.message || 'Gagal membuat slot.';
                        Alpine.store('toast').addToast(errorMessage, 'error');
                    }
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

        async sendTestMessage() {
            if (!this.form.reminder_template) {
                Alpine.store('toast').addToast('Pilih template WhatsApp terlebih dahulu.', 'error');
                return;
            }

            if (!this.testMessagePhone) {
                Alpine.store('toast').addToast('Masukkan nomor WhatsApp tujuan.', 'error');
                return;
            }

            const template = this.templates.find(t => t.name === this.form.reminder_template);
            if (!template) {
                Alpine.store('toast').addToast('Template tidak ditemukan.', 'error');
                return;
            }

            this.sendingTestMessage = true;
            try {
                const token = localStorage.getItem('token');

                const payload = {
                    to: this.testMessagePhone,
                    template_name: this.form.reminder_template,
                    language: this.form.reminder_template_language || 'id',
                };

                // Build body_params using the configured parameter mapping with sample data.
                // Falls back to body_examples if a param has no mapping configured.
                const sampleValues = {
                    customer_name: 'Pelanggan Tes',
                    customer_phone: '08123456789',
                    customer_email: 'pelanggan@email.com',
                    reservation_datetime: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' 14:00 WIB',
                    reservation_date: new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
                    reservation_time: '14:00',
                    guest_count: '4',
                    table_name: 'Meja A1',
                    store_name: this.form.store_name || 'Toko Anda',
                    store_address: 'Jl. Contoh No. 1',
                    reservation_code: 'RSV-1234567890-AB12CD',
                    status: 'Dikonfirmasi',
                    payment_type: 'DP',
                    total_amount: '500.000',
                    paid_amount: '250.000',
                    remaining_amount: '250.000',
                    reservation_notes: 'Minta tempat dekat jendela',
                };

                const paramKeys = this.currentTemplateParams.length > 0
                    ? this.currentTemplateParams
                    : (template.body_examples || []).map((_, i) => String(i + 1));

                if (paramKeys.length > 0) {
                    const isNamed = (template.variable_type || 'numeric') === 'named';
                    let variableNames = [];

                    if (isNamed && template.body) {
                        // Extract variable names in order from the template body
                        // Use RegExp constructor to avoid Blade parsing double-curly braces as PHP expressions
                        const varRegex = new RegExp('{' + '{' + '([a-zA-Z_][a-zA-Z0-9_]*)' + '}' + '}', 'g');
                        let match;
                        while ((match = varRegex.exec(template.body)) !== null) {
                            variableNames.push(match[1]);
                        }
                    }

                    payload.body_params = paramKeys.map((param, index) => {
                        const mappedField = this.form.reminder_param_mapping[param];
                        const value = (mappedField && sampleValues[mappedField])
                            ? sampleValues[mappedField]
                            : (template.body_examples?.[index] != null ? String(template.body_examples[index]) : `[param ${index + 1}]`);
                        const paramObj = { type: 'text', text: value };
                        if (isNamed && variableNames[index]) {
                            paramObj.parameter_name = variableNames[index];
                        }
                        return paramObj;
                    });
                }

                // Use the template's own language code, falling back to the configured one
                payload.language = template.language || this.form.reminder_template_language || 'id';

                const response = await fetch('/api/whatsapp/send/template', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (data.success) {
                    Alpine.store('toast').addToast('Pesan tes berhasil dikirim ke ' + this.testMessagePhone, 'success');
                    this.testMessageModal = false;
                } else {
                    Alpine.store('toast').addToast(data.message || 'Gagal mengirim pesan tes.', 'error');
                }
            } catch (error) {
                console.error('Error sending test message:', error);
                Alpine.store('toast').addToast('Terjadi kesalahan saat mengirim pesan tes.', 'error');
            } finally {
                this.sendingTestMessage = false;
            }
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
    if (container) {
        Alpine.$data(container).addToast(e.detail.message, e.detail.type);
    }
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection
