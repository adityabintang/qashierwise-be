@extends('layouts.app')

@section('title', __('dashboard.reservations_title'))

@section('content')
<div x-data="reservationsApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'reservations'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => __('dashboard.reservations'), 'description' => __('dashboard.manage_reservations')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- Tabs Navigation -->
                <div class="card">
                    <div class="flex border-b border-[hsl(var(--border))] overflow-x-auto">
                        <button
                            @click="activeTab = 'reservations'"
                            :class="activeTab === 'reservations' ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground'"
                            class="px-6 py-3 font-medium transition-colors hover:text-primary whitespace-nowrap">
                            <i class="fas fa-calendar-check mr-2"></i>{{ __('dashboard.reservations') }}
                        </button>
                        <button
                            @click="activeTab = 'flows'"
                            :class="activeTab === 'flows' ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground'"
                            class="px-6 py-3 font-medium transition-colors hover:text-primary whitespace-nowrap">
                            <i class="fab fa-whatsapp mr-2"></i>{{ __('dashboard.whatsapp_flows') }}
                        </button>
                        <button
                            @click="activeTab = 'flow-config'; loadFlowConfig()"
                            :class="activeTab === 'flow-config' ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground'"
                            class="px-6 py-3 font-medium transition-colors hover:text-primary whitespace-nowrap">
                            <i class="fas fa-cog mr-2"></i>{{ __('dashboard.flow_configuration') }}
                        </button>
                    </div>
                </div>

                <!-- Reservations Tab -->
                <div x-show="activeTab === 'reservations'" x-transition>
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">{{ __('dashboard.today') }}</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.today"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                                <i class="fas fa-calendar-day text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">{{ __('dashboard.reservations_today') }}</p>
                    </div>

                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">{{ __('dashboard.upcoming') }}</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.upcoming"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/20 flex items-center justify-center">
                                <i class="fas fa-clock text-emerald-600 dark:text-emerald-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">{{ __('dashboard.future_reservations') }}</p>
                    </div>

                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">{{ __('dashboard.pending') }}</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.pending"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-yellow-100 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">{{ __('dashboard.awaiting_confirmation') }}</p>
                    </div>

                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">{{ __('dashboard.this_month') }}</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.total_this_month"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 dark:text-purple-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">{{ __('dashboard.total_reservations') }}</p>
                    </div>
                </div>

                <!-- Action Buttons & Filters... (kode lengkap terlalu panjang) -->
                </div>
                <!-- End Reservations Tab -->

                <!-- WhatsApp Flows Tab -->
                <div x-show="activeTab === 'flows'" x-transition class="space-y-6">
                    <!-- Help Section - Collapsible -->
                    <div class="card overflow-hidden">
                        <button @click="showFlowHelp = !showFlowHelp" class="w-full p-4 flex items-center justify-between bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 hover:from-purple-100 hover:to-indigo-100 dark:hover:from-purple-900/30 dark:hover:to-indigo-900/30 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                    <i class="fas fa-question-circle text-primary text-lg"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-semibold">📚 Cara Menggunakan WhatsApp Flow</h3>
                                    <p class="text-sm text-muted-foreground">Pelajari cara membuat dan mengirim form reservasi via WhatsApp</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down transition-transform" :class="showFlowHelp && 'rotate-180'"></i>
                        </button>
                        <div x-show="showFlowHelp" x-collapse class="p-6 border-t border-[hsl(var(--border))]">
                            <!-- Video Tutorial -->
                            <div class="mb-6">
                                <h4 class="font-semibold mb-3 flex items-center gap-2">
                                    <i class="fas fa-play-circle text-red-500"></i>
                                    Video Tutorial
                                </h4>
                                <a href="https://www.youtube.com/watch?v=whatsapp-flow-tutorial" target="_blank" class="flex items-center gap-4 p-4 rounded-xl bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 border border-red-100 dark:border-red-900/30 hover:shadow-md transition-all group">
                                    <div class="h-16 w-24 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center group-hover:scale-105 transition-transform">
                                        <i class="fab fa-youtube text-red-500 text-3xl"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium">Tutorial Lengkap WhatsApp Flow</p>
                                        <p class="text-sm text-muted-foreground">5 menit • Bahasa Indonesia</p>
                                    </div>
                                    <i class="fas fa-external-link-alt ml-auto text-muted-foreground"></i>
                                </a>
                            </div>

                            <!-- Step by Step Guide -->
                            <h4 class="font-semibold mb-4">🚀 4 Langkah Mudah</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="relative p-4 rounded-xl bg-gradient-to-br from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 border border-blue-100 dark:border-blue-900/30">
                                    <div class="absolute -top-2 -left-2 h-8 w-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-sm">1</div>
                                    <div class="pt-2">
                                        <i class="fas fa-plus-circle text-blue-500 text-2xl mb-2"></i>
                                        <h5 class="font-semibold">Buat Flow</h5>
                                        <p class="text-sm text-muted-foreground mt-1">Klik "Buat Flow Baru" atau pilih template yang tersedia</p>
                                    </div>
                                </div>
                                <div class="relative p-4 rounded-xl bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 border border-purple-100 dark:border-purple-900/30">
                                    <div class="absolute -top-2 -left-2 h-8 w-8 rounded-full bg-purple-500 text-white flex items-center justify-center font-bold text-sm">2</div>
                                    <div class="pt-2">
                                        <i class="fas fa-cog text-purple-500 text-2xl mb-2"></i>
                                        <h5 class="font-semibold">Atur Konfigurasi</h5>
                                        <p class="text-sm text-muted-foreground mt-1">Sesuaikan jam operasional, meja, menu, dan pembayaran</p>
                                    </div>
                                </div>
                                <div class="relative p-4 rounded-xl bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border border-green-100 dark:border-green-900/30">
                                    <div class="absolute -top-2 -left-2 h-8 w-8 rounded-full bg-green-500 text-white flex items-center justify-center font-bold text-sm">3</div>
                                    <div class="pt-2">
                                        <i class="fas fa-rocket text-green-500 text-2xl mb-2"></i>
                                        <h5 class="font-semibold">Publish Flow</h5>
                                        <p class="text-sm text-muted-foreground mt-1">Aktifkan flow agar siap digunakan pelanggan</p>
                                    </div>
                                </div>
                                <div class="relative p-4 rounded-xl bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 border border-orange-100 dark:border-orange-900/30">
                                    <div class="absolute -top-2 -left-2 h-8 w-8 rounded-full bg-orange-500 text-white flex items-center justify-center font-bold text-sm">4</div>
                                    <div class="pt-2">
                                        <i class="fas fa-paper-plane text-orange-500 text-2xl mb-2"></i>
                                        <h5 class="font-semibold">Kirim ke Pelanggan</h5>
                                        <p class="text-sm text-muted-foreground mt-1">Bagikan link atau kirim langsung via WhatsApp</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tips Box -->
                            <div class="mt-6 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/30">
                                <h4 class="font-semibold flex items-center gap-2 text-amber-700 dark:text-amber-400">
                                    <i class="fas fa-lightbulb"></i>
                                    Tips untuk Pemula
                                </h4>
                                <ul class="mt-2 space-y-1 text-sm text-amber-800 dark:text-amber-300">
                                    <li>• Gunakan template "Restoran" jika bisnis Anda adalah cafe atau restoran</li>
                                    <li>• Pastikan nomor WhatsApp Business sudah terverifikasi sebelum publish</li>
                                    <li>• Test flow ke nomor sendiri sebelum mengirim ke pelanggan</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Flow Management Header -->
                    <div class="card p-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-2xl font-bold flex items-center gap-2">
                                    <span class="h-10 w-10 rounded-xl bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                                        <i class="fab fa-whatsapp text-green-600 dark:text-green-400 text-xl"></i>
                                    </span>
                                    WhatsApp Flow Management
                                </h2>
                                <p class="text-muted-foreground mt-2">Buat form reservasi interaktif yang dikirim langsung ke WhatsApp pelanggan</p>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-xs font-medium">
                                        <i class="fas fa-check-circle"></i> Otomatis
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 text-xs font-medium">
                                        <i class="fas fa-mobile-alt"></i> Mobile Friendly
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-purple-100 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400 text-xs font-medium">
                                        <i class="fas fa-credit-card"></i> Terima Pembayaran
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="showTemplateModal = true" class="btn btn-secondary">
                                    <i class="fas fa-magic mr-2"></i>Gunakan Template
                                </button>
                                <button @click="showCreateFlowModal = true" class="btn btn-primary">
                                    <i class="fas fa-plus mr-2"></i>Buat Flow Baru
                                </button>
                            </div>
                        </div>

                        <!-- Flows List -->
                        <div x-show="flowsLoading" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-3xl text-muted-foreground"></i>
                            <p class="mt-2 text-muted-foreground">Memuat flows...</p>
                        </div>

                        <!-- Empty State - Enhanced -->
                        <div x-show="!flowsLoading && flows.length === 0" class="text-center py-12">
                            <!-- Illustration -->
                            <div class="mb-6">
                                <svg class="mx-auto w-48 h-48" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="100" cy="100" r="80" fill="url(#gradient1)" opacity="0.1"/>
                                    <circle cx="100" cy="100" r="60" fill="url(#gradient1)" opacity="0.1"/>
                                    <rect x="60" y="50" width="80" height="100" rx="8" fill="white" stroke="#e2e8f0" stroke-width="2"/>
                                    <rect x="70" y="65" width="40" height="4" rx="2" fill="#cbd5e1"/>
                                    <rect x="70" y="75" width="60" height="4" rx="2" fill="#e2e8f0"/>
                                    <rect x="70" y="85" width="50" height="4" rx="2" fill="#e2e8f0"/>
                                    <rect x="70" y="100" width="60" height="25" rx="4" fill="#25D366"/>
                                    <text x="100" y="117" text-anchor="middle" fill="white" font-size="10" font-weight="bold">WhatsApp</text>
                                    <circle cx="150" cy="60" r="20" fill="#25D366"/>
                                    <path d="M145 60l4 4 8-8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <defs>
                                        <linearGradient id="gradient1" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#25D366"/>
                                            <stop offset="100%" stop-color="#128C7E"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold mb-2">Belum Ada Flow</h3>
                            <p class="text-muted-foreground max-w-md mx-auto mb-6">Buat flow pertama Anda untuk mulai menerima reservasi via WhatsApp. Pelanggan dapat mengisi form langsung dari chat!</p>

                            <!-- Quick Start Options -->
                            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                                <button @click="showTemplateModal = true" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-magic mr-2"></i>Mulai dengan Template
                                </button>
                                <span class="text-muted-foreground">atau</span>
                                <button @click="activeTab = 'flow-config'; loadFlowConfig()" class="btn btn-primary btn-lg">
                                    <i class="fas fa-cog mr-2"></i>Konfigurasi Manual
                                </button>
                            </div>
                        </div>

                        <div x-show="!flowsLoading && flows.length > 0" class="space-y-4">
                            <template x-for="flow in flows" :key="flow.id">
                                <div class="card p-4 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-xl bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                                            <i class="fab fa-whatsapp text-green-600 dark:text-green-400 text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold" x-text="flow.name"></h3>
                                            <p class="text-sm text-muted-foreground">
                                                <span x-text="flow.status"></span> •
                                                Created <span x-text="formatDate(flow.created_at)"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            x-show="flow.status !== 'PUBLISHED'"
                                            @click="publishFlow(flow.id)"
                                            class="btn btn-sm btn-primary">
                                            <i class="fas fa-rocket mr-2"></i>Publish
                                        </button>
                                        <button
                                            @click="openSendFlowModal(flow)"
                                            class="btn btn-sm btn-secondary">
                                            <i class="fas fa-paper-plane mr-2"></i>Send
                                        </button>
                                        <button
                                            @click="deleteFlow(flow.id)"
                                            class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <!-- End Flows Tab -->

                <!-- Flow Configuration Tab -->
                <div x-show="activeTab === 'flow-config'" x-transition class="space-y-6">
                    <!-- Quick Tips Card -->
                    <div class="card p-4 bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 border-blue-100 dark:border-blue-900/30">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-full bg-blue-500 text-white flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-blue-800 dark:text-blue-300">Tips Konfigurasi</h4>
                                <p class="text-sm text-blue-700 dark:text-blue-400 mt-1">Sesuaikan pengaturan di bawah ini sesuai kebutuhan bisnis Anda. Setelah selesai, klik <strong>"Simpan"</strong> lalu <strong>"Publish"</strong> untuk mengaktifkan flow.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card p-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-2xl font-bold flex items-center gap-2">
                                    <span class="h-10 w-10 rounded-xl bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center">
                                        <i class="fas fa-cog text-purple-600 dark:text-purple-400 text-xl"></i>
                                    </span>
                                    Konfigurasi Flow
                                </h2>
                                <p class="text-muted-foreground mt-2">Atur pengaturan WhatsApp Flow untuk reservasi</p>
                            </div>
                            <div class="flex gap-2">
                                <span x-show="flowConfig.flow_status === 'published'" class="px-3 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400 text-sm font-medium">
                                    <i class="fas fa-check-circle mr-1"></i>Sudah Dipublish
                                </span>
                                <span x-show="flowConfig.flow_status === 'draft'" class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400 text-sm font-medium">
                                    <i class="fas fa-pencil-alt mr-1"></i>Draft
                                </span>
                                <button @click="showTemplateModal = true" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-magic mr-1"></i>Template
                                </button>
                            </div>
                        </div>

                        <div x-show="flowConfigLoading" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-3xl text-muted-foreground"></i>
                            <p class="mt-2 text-muted-foreground">Memuat konfigurasi...</p>
                        </div>

                        <div x-show="!flowConfigLoading" class="space-y-8">
                            <!-- Time Configuration -->
                            <div class="border-b border-[hsl(var(--border))] pb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                    <i class="fas fa-clock text-primary"></i>
                                    Pengaturan Waktu
                                    <span class="group relative">
                                        <i class="fas fa-question-circle text-muted-foreground text-sm cursor-help"></i>
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Atur jam operasional dan interval waktu reservasi</span>
                                    </span>
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-2">Jam Buka</label>
                                        <input type="time" x-model="flowConfig.opening_time" class="input w-full">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-2">Jam Tutup</label>
                                        <input type="time" x-model="flowConfig.closing_time" class="input w-full">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-2 flex items-center gap-1">
                                            Interval Waktu
                                            <span class="group relative">
                                                <i class="fas fa-info-circle text-muted-foreground text-xs cursor-help"></i>
                                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Jarak waktu antar slot reservasi</span>
                                            </span>
                                        </label>
                                        <select x-model="flowConfig.time_interval" class="input w-full">
                                            <option value="30">30 menit</option>
                                            <option value="60">60 menit</option>
                                            <option value="90">90 menit</option>
                                            <option value="120">120 menit</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-2 flex items-center gap-1">
                                            Maks. Hari ke Depan
                                            <span class="group relative">
                                                <i class="fas fa-info-circle text-muted-foreground text-xs cursor-help"></i>
                                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Berapa hari ke depan pelanggan bisa reservasi</span>
                                            </span>
                                        </label>
                                        <input type="number" x-model="flowConfig.max_advance_days" class="input w-full" min="1" max="365">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium mb-2">Hari Operasional</label>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="(day, index) in ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']" :key="index">
                                            <button type="button"
                                                @click="toggleOperatingDay(index + 1)"
                                                class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-all hover:shadow-sm"
                                                :class="flowConfig.operating_days?.includes(index + 1) ? 'bg-green-500 text-white border-green-500 font-semibold shadow-md' : 'border-[hsl(var(--border))] hover:border-green-300 hover:bg-green-50 dark:hover:bg-green-900/20'">
                                                <i class="fas fa-check text-xs" x-show="flowConfig.operating_days?.includes(index + 1)"></i>
                                                <span x-text="day" class="text-sm font-medium"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Guest Configuration -->
                            <div class="border-b border-[hsl(var(--border))] pb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                    <i class="fas fa-users text-primary"></i>
                                    Pengaturan Tamu
                                    <span class="group relative">
                                        <i class="fas fa-question-circle text-muted-foreground text-sm cursor-help"></i>
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Batasi jumlah tamu per reservasi</span>
                                    </span>
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-2">Minimum Tamu</label>
                                        <input type="number" x-model="flowConfig.min_guests" class="input w-full" min="1" max="100">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-2">Maximum Tamu</label>
                                        <input type="number" x-model="flowConfig.max_guests" class="input w-full" min="1" max="100">
                                    </div>
                                </div>
                            </div>

                            <!-- Table Configuration -->
                            <div class="border-b border-[hsl(var(--border))] pb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                    <i class="fas fa-chair text-primary"></i>
                                    Pengaturan Meja
                                    <span class="group relative">
                                        <i class="fas fa-question-circle text-muted-foreground text-sm cursor-help"></i>
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Biarkan pelanggan memilih meja favorit</span>
                                    </span>
                                </h3>
                                <div class="flex items-center gap-4 mb-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" x-model="flowConfig.enable_table_selection" class="form-checkbox">
                                        <span class="font-medium">Aktifkan pilihan meja</span>
                                    </label>
                                </div>
                                <div x-show="flowConfig.enable_table_selection" class="mt-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-sm font-medium">Meja yang Tersedia <span x-show="availableTables.length > 0" class="text-muted-foreground">(<span x-text="flowConfig.available_table_ids?.length || 0"></span>/<span x-text="availableTables.length"></span> dipilih)</span></label>
                                        <div x-show="availableTables.length > 0" class="flex gap-2">
                                            <button type="button" @click="selectAllTables()" class="text-xs text-primary hover:underline">Pilih Semua</button>
                                            <span class="text-muted-foreground">|</span>
                                            <button type="button" @click="flowConfig.available_table_ids = []" class="text-xs text-muted-foreground hover:underline">Hapus Semua</button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <template x-for="table in availableTables" :key="table.id">
                                            <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-all hover:shadow-sm"
                                                :class="flowConfig.available_table_ids?.includes(table.id) ? 'bg-primary/10 border-primary' : 'border-[hsl(var(--border))]'">
                                                <input type="checkbox"
                                                    :checked="flowConfig.available_table_ids?.includes(table.id)"
                                                    @change="toggleTableSelection(table.id)"
                                                    class="form-checkbox">
                                                <span class="text-sm">Meja <span x-text="table.number"></span> (<span x-text="table.capacity"></span> org)</span>
                                            </label>
                                        </template>
                                    </div>
                                    <p x-show="availableTables.length === 0" class="text-muted-foreground text-sm mt-2 p-4 bg-muted/50 rounded-lg flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                        <span class="flex-1"><i class="fas fa-info-circle mr-1"></i>Belum ada meja tersedia. Tambahkan meja di pengaturan POS terlebih dahulu.</span>
                                        <a href="/dashboard/pos" class="btn btn-sm btn-primary whitespace-nowrap">
                                            <i class="fas fa-plus mr-1"></i>Tambah Meja
                                        </a>
                                    </p>
                                </div>
                            </div>

                            <!-- Menu Configuration -->
                            <div class="border-b border-[hsl(var(--border))] pb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                    <i class="fas fa-utensils text-primary"></i>
                                    Pengaturan Menu
                                    <span class="group relative">
                                        <i class="fas fa-question-circle text-muted-foreground text-sm cursor-help"></i>
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Izinkan pelanggan pre-order menu saat reservasi</span>
                                    </span>
                                </h3>
                                <div class="flex items-center gap-4 mb-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" x-model="flowConfig.enable_menu_selection" class="form-checkbox">
                                        <span class="font-medium">Aktifkan pilihan menu</span>
                                    </label>
                                    <label x-show="flowConfig.enable_menu_selection" class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" x-model="flowConfig.require_menu_selection" class="form-checkbox">
                                        <span class="font-medium">Wajib pilih menu</span>
                                    </label>
                                </div>
                                <div x-show="flowConfig.enable_menu_selection" class="mt-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-sm font-medium">Produk yang Tersedia <span x-show="availableProducts.length > 0" class="text-muted-foreground">(<span x-text="flowConfig.available_product_ids?.length || 0"></span>/<span x-text="availableProducts.length"></span> dipilih)</span></label>
                                        <div x-show="availableProducts.length > 0" class="flex gap-2">
                                            <button type="button" @click="selectAllProducts()" class="text-xs text-primary hover:underline">Pilih Semua</button>
                                            <span class="text-muted-foreground">|</span>
                                            <button type="button" @click="flowConfig.available_product_ids = []" class="text-xs text-muted-foreground hover:underline">Hapus Semua</button>
                                        </div>
                                    </div>
                                    <div class="max-h-60 overflow-y-auto border rounded-lg p-2">
                                        <template x-for="product in availableProducts" :key="product.id">
                                            <label class="flex items-center gap-2 p-2 rounded hover:bg-muted cursor-pointer transition-colors">
                                                <input type="checkbox"
                                                    :checked="flowConfig.available_product_ids?.includes(product.id)"
                                                    @change="toggleProductSelection(product.id)"
                                                    class="form-checkbox">
                                                <span class="text-sm flex-1" x-text="product.name"></span>
                                                <span class="text-sm text-muted-foreground" x-text="formatCurrency(product.price)"></span>
                                            </label>
                                        </template>
                                    </div>
                                    <p x-show="availableProducts.length === 0" class="text-muted-foreground text-sm mt-2 p-4 bg-muted/50 rounded-lg flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                        <span class="flex-1"><i class="fas fa-info-circle mr-1"></i>Belum ada produk tersedia. Tambahkan produk di pengaturan POS terlebih dahulu.</span>
                                        <a href="/dashboard/products" class="btn btn-sm btn-primary whitespace-nowrap">
                                            <i class="fas fa-plus mr-1"></i>Tambah Produk
                                        </a>
                                    </p>
                                </div>
                            </div>

                            <!-- Payment Configuration -->
                            <div class="border-b border-[hsl(var(--border))] pb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                    <i class="fas fa-credit-card text-primary"></i>
                                    Pengaturan Pembayaran
                                    <span class="group relative">
                                        <i class="fas fa-question-circle text-muted-foreground text-sm cursor-help"></i>
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Terima pembayaran DP atau full saat reservasi</span>
                                    </span>
                                </h3>
                                <div class="flex items-center gap-4 mb-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" x-model="flowConfig.enable_payment" class="form-checkbox">
                                        <span class="font-medium">Aktifkan pembayaran</span>
                                    </label>
                                </div>
                                <div x-show="flowConfig.enable_payment" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-2 flex items-center gap-1">
                                                Biaya Reservasi (Rp)
                                                <span class="group relative">
                                                    <i class="fas fa-info-circle text-muted-foreground text-xs cursor-help"></i>
                                                    <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Biaya minimum untuk reservasi meja</span>
                                                </span>
                                            </label>
                                            <input type="number" x-model="flowConfig.table_fee" class="input w-full" min="0" step="1000">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-2 flex items-center gap-1">
                                                Persentase DP (%)
                                                <span class="group relative">
                                                    <i class="fas fa-info-circle text-muted-foreground text-xs cursor-help"></i>
                                                    <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Persentase dari total untuk pembayaran DP</span>
                                                </span>
                                            </label>
                                            <input type="number" x-model="flowConfig.dp_percentage" class="input w-full" min="10" max="100">
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-muted transition-colors">
                                            <input type="checkbox" x-model="flowConfig.allow_dp_payment" class="form-checkbox">
                                            <span>Izinkan bayar DP</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-muted transition-colors">
                                            <input type="checkbox" x-model="flowConfig.allow_full_payment" class="form-checkbox">
                                            <span>Izinkan bayar penuh</span>
                                        </label>
                                    </div>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-muted transition-colors">
                                            <input type="checkbox" x-model="flowConfig.enable_qris" class="form-checkbox">
                                            <span><i class="fas fa-qrcode mr-1"></i>QRIS</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-muted transition-colors">
                                            <input type="checkbox" x-model="flowConfig.enable_cash" class="form-checkbox">
                                            <span><i class="fas fa-money-bill-wave mr-1"></i>Tunai</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Data Configuration -->
                            <div class="border-b border-[hsl(var(--border))] pb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                    <i class="fas fa-user text-primary"></i>
                                    Data Pelanggan
                                    <span class="group relative">
                                        <i class="fas fa-question-circle text-muted-foreground text-sm cursor-help"></i>
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Data tambahan yang wajib diisi pelanggan</span>
                                    </span>
                                </h3>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-muted transition-colors">
                                        <input type="checkbox" x-model="flowConfig.require_email" class="form-checkbox">
                                        <span><i class="fas fa-envelope mr-1"></i>Wajib isi email</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-muted transition-colors">
                                        <input type="checkbox" x-model="flowConfig.require_event_type" class="form-checkbox">
                                        <span><i class="fas fa-calendar-alt mr-1"></i>Wajib pilih jenis acara</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Message Configuration -->
                            <div class="pb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                                    <i class="fas fa-comment-alt text-primary"></i>
                                    Pesan di Flow
                                    <span class="group relative">
                                        <i class="fas fa-question-circle text-muted-foreground text-sm cursor-help"></i>
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-foreground text-background text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">Pesan yang tampil saat pelanggan membuka flow</span>
                                    </span>
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-2">Teks Header (maks 60 karakter)</label>
                                        <input type="text" x-model="flowConfig.header_text" class="input w-full" maxlength="60" placeholder="📅 Buat Reservasi">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-2">Teks Tombol CTA (maks 20 karakter)</label>
                                        <input type="text" x-model="flowConfig.cta_text" class="input w-full" maxlength="20" placeholder="Reservasi Sekarang">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium mb-2">Teks Body</label>
                                    <textarea x-model="flowConfig.body_text" class="input w-full" rows="3" maxlength="1024"
                                        placeholder="Silakan isi form di bawah ini untuk membuat reservasi."></textarea>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium mb-2">Teks Footer (maks 60 karakter)</label>
                                    <input type="text" x-model="flowConfig.footer_text" class="input w-full" maxlength="60" placeholder="Powered by QashierWise">
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap items-center gap-3 pt-6 border-t border-[hsl(var(--border))]">
                                <button @click="saveFlowConfig()" class="btn btn-primary btn-lg" :disabled="savingConfig">
                                    <i class="fas mr-2" :class="savingConfig ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                                    <span x-text="savingConfig ? 'Menyimpan...' : 'Simpan Konfigurasi'"></span>
                                </button>
                                <button @click="publishFlowConfig()" class="btn btn-secondary btn-lg" :disabled="publishingConfig">
                                    <i class="fas mr-2" :class="publishingConfig ? 'fa-spinner fa-spin' : 'fa-rocket'"></i>
                                    <span x-text="publishingConfig ? 'Publishing...' : 'Publish Flow'"></span>
                                </button>
                                <button @click="showSendConfigFlowModal = true" class="btn btn-secondary btn-lg" x-show="flowConfig.flow_status === 'published'">
                                    <i class="fas fa-paper-plane mr-2"></i>Kirim ke Pelanggan
                                </button>
                                <div class="ml-auto text-sm text-muted-foreground" x-show="flowConfig.updated_at">
                                    <i class="fas fa-clock mr-1"></i>Terakhir diupdate: <span x-text="formatDate(flowConfig.updated_at)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Flow Configuration Tab -->

            </div>
        </main>
    </div>

    <!-- Send Flow from Config Modal -->
    <div x-show="showSendConfigFlowModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @click.self="showSendConfigFlowModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop>
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fab fa-whatsapp text-green-500"></i>
                    Kirim Flow ke Pelanggan
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Nomor WhatsApp Pelanggan</label>
                        <input
                            x-model="sendConfigFlowPhone"
                            type="tel"
                            class="input w-full"
                            placeholder="+62812xxxxxxxx">
                        <p class="text-xs text-muted-foreground mt-1">Sertakan kode negara (contoh: +62 untuk Indonesia)</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showSendConfigFlowModal = false" class="btn btn-secondary">Batal</button>
                    <button @click="sendConfigFlow()" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>Kirim Flow
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Flow Modal -->
    <div x-show="showCreateFlowModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @click.self="showCreateFlowModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop>
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-primary"></i>
                    Buat Flow Baru
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Nama Flow</label>
                        <input
                            x-model="newFlow.name"
                            type="text"
                            class="input w-full"
                            placeholder="contoh: Flow Reservasi Restoran">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Kategori (pisahkan dengan koma)</label>
                        <input
                            x-model="newFlow.categories"
                            type="text"
                            class="input w-full"
                            placeholder="APPOINTMENT_BOOKING, MAKE_A_RESERVATION">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showCreateFlowModal = false" class="btn btn-secondary">Batal</button>
                    <button @click="createFlow()" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i>Buat Flow
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Flow Modal -->
    <div x-show="showSendFlowModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @click.self="showSendFlowModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop>
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fab fa-whatsapp text-green-500"></i>
                    Kirim Flow ke Pelanggan
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Nomor WhatsApp Pelanggan</label>
                        <input
                            x-model="sendFlowData.phone"
                            type="tel"
                            class="input w-full"
                            placeholder="+62812xxxxxxxx">
                        <p class="text-xs text-muted-foreground mt-1">Sertakan kode negara (contoh: +62 untuk Indonesia)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Pesan (Opsional)</label>
                        <textarea
                            x-model="sendFlowData.message"
                            class="input w-full"
                            rows="3"
                            placeholder="Halo! Silakan isi form ini untuk membuat reservasi."></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showSendFlowModal = false" class="btn btn-secondary">Batal</button>
                    <button @click="sendFlow()" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>Kirim Flow
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Template Selector Modal -->
    <div x-show="showTemplateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @click.self="showTemplateModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden" @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-[hsl(var(--border))] bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold flex items-center gap-2">
                                <i class="fas fa-magic text-primary"></i>
                                Pilih Template Flow
                            </h3>
                            <p class="text-muted-foreground mt-1">Mulai dengan template yang sudah dikonfigurasi untuk bisnis Anda</p>
                        </div>
                        <button @click="showTemplateModal = false" class="h-8 w-8 rounded-full hover:bg-muted flex items-center justify-center">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Template Grid -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <template x-for="template in flowTemplates" :key="template.id">
                            <div @click="selectedTemplate = template.id"
                                class="p-5 rounded-xl border-2 cursor-pointer transition-all hover:shadow-lg"
                                :class="selectedTemplate === template.id ? 'border-primary bg-primary/5 shadow-md' : 'border-[hsl(var(--border))] hover:border-primary/50'">
                                <div class="flex items-start gap-4">
                                    <div class="h-14 w-14 rounded-xl flex items-center justify-center text-2xl"
                                        :class="{
                                            'bg-orange-100 dark:bg-orange-900/20': template.color === 'orange',
                                            'bg-pink-100 dark:bg-pink-900/20': template.color === 'pink',
                                            'bg-amber-100 dark:bg-amber-900/20': template.color === 'amber',
                                            'bg-purple-100 dark:bg-purple-900/20': template.color === 'purple'
                                        }">
                                        <i :class="'fas ' + template.icon"
                                            :style="{
                                                color: template.color === 'orange' ? '#ea580c' :
                                                       template.color === 'pink' ? '#db2777' :
                                                       template.color === 'amber' ? '#d97706' : '#9333ea'
                                            }"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-lg" x-text="template.name"></h4>
                                        <p class="text-sm text-muted-foreground mt-1" x-text="template.description"></p>
                                        <div class="flex flex-wrap gap-1 mt-3">
                                            <template x-for="feature in template.features" :key="feature">
                                                <span class="px-2 py-0.5 rounded-full bg-muted text-xs font-medium" x-text="feature"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="selectedTemplate === template.id" class="text-primary">
                                        <i class="fas fa-check-circle text-xl"></i>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t border-[hsl(var(--border))] bg-muted/30 flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        <i class="fas fa-info-circle mr-1"></i>
                        Template akan diterapkan ke konfigurasi flow Anda
                    </p>
                    <div class="flex gap-3">
                        <button @click="showTemplateModal = false" class="btn btn-secondary">Batal</button>
                        <button @click="applyTemplate()" :disabled="!selectedTemplate" class="btn btn-primary">
                            <i class="fas fa-check mr-2"></i>Gunakan Template
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
function reservationsApp() {
    return {
        // Base properties for sidebar and header
        sidebarOpen: true,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        // Reservations-specific properties
        API_BASE_URL: window.location.origin + '/api',
        loading: false,
        reservations: [],
        stats: { today: 0, upcoming: 0, pending: 0, total_this_month: 0 },

        // Tabs
        activeTab: 'reservations',

        // Flow Management
        flows: [],
        flowsLoading: false,
        showCreateFlowModal: false,
        showSendFlowModal: false,
        newFlow: {
            name: '',
            categories: 'APPOINTMENT_BOOKING'
        },
        sendFlowData: {
            flow_id: '',
            phone: '',
            message: 'Hi! Please fill out this form to make a reservation.'
        },

        // Flow Configuration
        flowConfig: {
            operating_days: [1, 2, 3, 4, 5, 6, 7],
            available_table_ids: [],
            available_product_ids: []
        },
        flowConfigLoading: false,
        savingConfig: false,
        publishingConfig: false,
        availableTables: [],
        availableProducts: [],
        showSendConfigFlowModal: false,
        sendConfigFlowPhone: '',

        // Help & Templates
        showFlowHelp: false,
        showTemplateModal: false,
        selectedTemplate: null,
        flowTemplates: [
            {
                id: 'restaurant',
                name: '🍽️ Restoran',
                description: 'Cocok untuk restoran dengan pilihan meja dan menu',
                icon: 'fa-utensils',
                color: 'orange',
                features: ['Pilihan meja', 'Pre-order menu', 'DP pembayaran', 'Slot waktu fleksibel'],
                config: {
                    flow_name: 'Reservasi Restoran',
                    opening_time: '10:00',
                    closing_time: '22:00',
                    time_interval: 60,
                    operating_days: [1, 2, 3, 4, 5, 6, 7],
                    max_advance_days: 30,
                    min_guests: 1,
                    max_guests: 20,
                    enable_table_selection: true,
                    enable_menu_selection: true,
                    require_menu_selection: false,
                    enable_payment: true,
                    table_fee: 100000,
                    dp_percentage: 50,
                    allow_dp_payment: true,
                    allow_full_payment: true,
                    enable_qris: true,
                    enable_cash: true,
                    require_email: false,
                    require_event_type: true,
                    header_text: '📅 Reservasi Meja',
                    body_text: 'Silakan isi form untuk reservasi meja di restoran kami.',
                    footer_text: 'Powered by QashierWise',
                    cta_text: 'Reservasi Sekarang'
                }
            },
            {
                id: 'salon',
                name: '💇 Salon & Spa',
                description: 'Untuk salon, barbershop, atau spa dengan layanan booking',
                icon: 'fa-cut',
                color: 'pink',
                features: ['Booking per jam', 'Tanpa pilihan meja', 'Konfirmasi cepat', 'Reminder otomatis'],
                config: {
                    flow_name: 'Booking Salon',
                    opening_time: '09:00',
                    closing_time: '20:00',
                    time_interval: 30,
                    operating_days: [1, 2, 3, 4, 5, 6],
                    max_advance_days: 14,
                    min_guests: 1,
                    max_guests: 5,
                    enable_table_selection: false,
                    enable_menu_selection: false,
                    require_menu_selection: false,
                    enable_payment: true,
                    table_fee: 50000,
                    dp_percentage: 30,
                    allow_dp_payment: true,
                    allow_full_payment: true,
                    enable_qris: true,
                    enable_cash: false,
                    require_email: true,
                    require_event_type: false,
                    header_text: '💇 Booking Appointment',
                    body_text: 'Pilih jadwal yang tersedia untuk appointment Anda.',
                    footer_text: 'Powered by QashierWise',
                    cta_text: 'Book Sekarang'
                }
            },
            {
                id: 'cafe',
                name: '☕ Cafe',
                description: 'Setup sederhana untuk cafe dengan walk-in friendly',
                icon: 'fa-coffee',
                color: 'amber',
                features: ['Setup minimal', 'Tanpa DP', 'Walk-in friendly', 'Cepat & mudah'],
                config: {
                    flow_name: 'Reservasi Cafe',
                    opening_time: '08:00',
                    closing_time: '22:00',
                    time_interval: 60,
                    operating_days: [1, 2, 3, 4, 5, 6, 7],
                    max_advance_days: 7,
                    min_guests: 1,
                    max_guests: 10,
                    enable_table_selection: false,
                    enable_menu_selection: false,
                    require_menu_selection: false,
                    enable_payment: false,
                    table_fee: 0,
                    dp_percentage: 0,
                    allow_dp_payment: false,
                    allow_full_payment: false,
                    enable_qris: false,
                    enable_cash: false,
                    require_email: false,
                    require_event_type: false,
                    header_text: '☕ Reservasi Meja',
                    body_text: 'Book meja favorit Anda di cafe kami.',
                    footer_text: 'Powered by QashierWise',
                    cta_text: 'Reservasi'
                }
            },
            {
                id: 'event',
                name: '🎉 Event Space',
                description: 'Untuk venue event, meeting room, atau ruang acara',
                icon: 'fa-calendar-alt',
                color: 'purple',
                features: ['Grup besar', 'Jenis event', 'Full payment', 'Advance booking'],
                config: {
                    flow_name: 'Booking Event Space',
                    opening_time: '08:00',
                    closing_time: '23:00',
                    time_interval: 120,
                    operating_days: [1, 2, 3, 4, 5, 6, 7],
                    max_advance_days: 90,
                    min_guests: 10,
                    max_guests: 100,
                    enable_table_selection: true,
                    enable_menu_selection: true,
                    require_menu_selection: true,
                    enable_payment: true,
                    table_fee: 500000,
                    dp_percentage: 50,
                    allow_dp_payment: true,
                    allow_full_payment: true,
                    enable_qris: true,
                    enable_cash: true,
                    require_email: true,
                    require_event_type: true,
                    header_text: '🎉 Booking Event',
                    body_text: 'Reservasi ruang untuk acara spesial Anda.',
                    footer_text: 'Powered by QashierWise',
                    cta_text: 'Book Event'
                }
            }
        ],

        async init() {
            // Initialize sidebar state
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                this.sidebarOpen = true;
                localStorage.setItem('sidebarOpen', 'true');
            }

            // Watch sidebar state changes (only save on desktop)
            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });

            // Handle resize events with debounce
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

            // Load user info
            let storedUser = localStorage.getItem('user');
            if (storedUser) {
                try {
                    this.user = JSON.parse(storedUser);
                } catch (e) {
                    this.user = { name: 'User', email: 'user@example.com' };
                }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }

            // Load saved notifications
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try {
                    this.notifications = JSON.parse(savedNotifs);
                } catch (e) {
                    this.notifications = [];
                }
            }

            // Fetch reservations data
            await Promise.all([
                this.fetchStatistics(),
                this.fetchReservations(),
            ]);

            // Watch for tab changes
            this.$watch('activeTab', (tab) => {
                if (tab === 'flows' && this.flows.length === 0) {
                    this.fetchFlows();
                }
            });
        },

        addNotification(notif) {
            notif.id = Date.now() + Math.random();
            this.notifications.unshift(notif);
            if (this.notifications.length > 50) {
                this.notifications = this.notifications.slice(0, 50);
            }
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        async fetchStatistics() {
            const token = localStorage.getItem('token');
            const response = await fetch(`${this.API_BASE_URL}/reservations/statistics`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await response.json();
            if (data.success) {
                this.stats = data.data.statistics;
            }
        },

        async fetchReservations() {
            this.loading = true;
            const token = localStorage.getItem('token');
            const response = await fetch(`${this.API_BASE_URL}/reservations`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await response.json();
            if (data.success) {
                this.reservations = data.data.data;
            }
            this.loading = false;
        },

        // Flow Management Methods
        async fetchFlows() {
            this.flowsLoading = true;
            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/list`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await response.json();
                if (data.success) {
                    this.flows = data.data.flows || [];
                }
            } catch (error) {
                console.error('Error fetching flows:', error);
                this.addNotification({
                    type: 'error',
                    icon: 'fa-exclamation-circle',
                    color: 'red',
                    title: 'Error',
                    message: 'Failed to fetch flows',
                    time: new Date().toISOString()
                });
            }
            this.flowsLoading = false;
        },

        async createFlow() {
            if (!this.newFlow.name) {
                alert('Please enter a flow name');
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/create`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: this.newFlow.name,
                        categories: this.newFlow.categories.split(',').map(c => c.trim())
                    })
                });
                const data = await response.json();

                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-check-circle',
                        color: 'green',
                        title: 'Success',
                        message: 'Flow created successfully',
                        time: new Date().toISOString()
                    });
                    this.showCreateFlowModal = false;
                    this.newFlow = { name: '', categories: 'APPOINTMENT_BOOKING' };
                    await this.fetchFlows();
                } else {
                    alert(data.message || 'Failed to create flow');
                }
            } catch (error) {
                console.error('Error creating flow:', error);
                alert('Failed to create flow');
            }
        },

        async publishFlow(flowId) {
            if (!confirm('Publish this flow? Once published, the flow will be available for customers.')) {
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/publish`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ flow_id: flowId })
                });
                const data = await response.json();

                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-rocket',
                        color: 'green',
                        title: 'Success',
                        message: 'Flow published successfully',
                        time: new Date().toISOString()
                    });
                    await this.fetchFlows();
                } else {
                    alert(data.message || 'Failed to publish flow');
                }
            } catch (error) {
                console.error('Error publishing flow:', error);
                alert('Failed to publish flow');
            }
        },

        openSendFlowModal(flow) {
            this.sendFlowData.flow_id = flow.id;
            this.sendFlowData.phone = '';
            this.showSendFlowModal = true;
        },

        async sendFlow() {
            if (!this.sendFlowData.phone) {
                alert('Please enter customer phone number');
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/send`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.sendFlowData)
                });
                const data = await response.json();

                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-paper-plane',
                        color: 'blue',
                        title: 'Success',
                        message: 'Flow sent to customer',
                        time: new Date().toISOString()
                    });
                    this.showSendFlowModal = false;
                    this.sendFlowData = {
                        flow_id: '',
                        phone: '',
                        message: 'Hi! Please fill out this form to make a reservation.'
                    };
                } else {
                    alert(data.message || 'Failed to send flow');
                }
            } catch (error) {
                console.error('Error sending flow:', error);
                alert('Failed to send flow');
            }
        },

        async deleteFlow(flowId) {
            if (!confirm('Delete this flow? This action cannot be undone.')) {
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/delete`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ flow_id: flowId })
                });
                const data = await response.json();

                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-trash',
                        color: 'red',
                        title: 'Success',
                        message: 'Flow deleted successfully',
                        time: new Date().toISOString()
                    });
                    await this.fetchFlows();
                } else {
                    alert(data.message || 'Failed to delete flow');
                }
            } catch (error) {
                console.error('Error deleting flow:', error);
                alert('Failed to delete flow');
            }
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        },

        formatCurrency(amount) {
            return 'Rp' + new Intl.NumberFormat('id-ID').format(amount);
        },

        formatNotificationTime(t) {
            let d = new Date(t);
            let diff = Math.floor((new Date() - d) / 1000);
            if (diff < 60) return 'Baru saja';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
            return d.toLocaleDateString('id-ID');
        },

        clearNotifications() {
            this.notifications = [];
            localStorage.removeItem('notifications');
        },

        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        // Flow Configuration Methods
        async loadFlowConfig() {
            if (this.flowConfigLoading) return;

            this.flowConfigLoading = true;
            const token = localStorage.getItem('token');

            // Check if token exists
            if (!token) {
                console.warn('No auth token found, redirecting to login');
                window.location.href = '/login';
                return;
            }

            try {
                // Fetch flow config, tables, and products in parallel
                const [configResponse, tablesResponse, productsResponse] = await Promise.all([
                    fetch(`${this.API_BASE_URL}/reservations/flow-config`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    }),
                    fetch(`${this.API_BASE_URL}/pos/tables`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    }),
                    fetch(`${this.API_BASE_URL}/pos/products?per_page=100&active_only=true`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    })
                ]);

                // Check for auth errors (redirect to login if 401)
                if (configResponse.status === 401 || tablesResponse.status === 401 || productsResponse.status === 401) {
                    console.warn('Unauthorized, redirecting to login');
                    localStorage.removeItem('token');
                    window.location.href = '/login';
                    return;
                }

                // Parse JSON responses with error handling
                let configData = { success: false };
                let tablesData = { success: false };
                let productsData = { success: false };

                try {
                    const configText = await configResponse.text();
                    configData = configText ? JSON.parse(configText) : { success: false };
                } catch (e) {
                    console.error('Error parsing config response:', e);
                }

                try {
                    const tablesText = await tablesResponse.text();
                    tablesData = tablesText ? JSON.parse(tablesText) : { success: false };
                } catch (e) {
                    console.error('Error parsing tables response:', e);
                }

                try {
                    const productsText = await productsResponse.text();
                    productsData = productsText ? JSON.parse(productsText) : { success: false };
                } catch (e) {
                    console.error('Error parsing products response:', e);
                }

                if (configData.success) {
                    this.flowConfig = configData.data.config || {};

                    // Initialize arrays if null
                    if (!this.flowConfig.operating_days) {
                        this.flowConfig.operating_days = [1, 2, 3, 4, 5, 6, 7];
                    }
                    if (!this.flowConfig.available_table_ids) {
                        this.flowConfig.available_table_ids = [];
                    }
                    if (!this.flowConfig.available_product_ids) {
                        this.flowConfig.available_product_ids = [];
                    }
                }

                // Load tables from POS API
                if (tablesData.success) {
                    this.availableTables = tablesData.data || [];
                } else {
                    this.availableTables = [];
                }

                // Load products from POS API (handle paginated response)
                if (productsData.success) {
                    // Products API returns paginated data
                    this.availableProducts = productsData.data?.data || productsData.data || [];
                } else {
                    this.availableProducts = [];
                }
            } catch (error) {
                console.error('Error loading flow config:', error);
                this.addNotification({
                    type: 'error',
                    icon: 'fa-exclamation-circle',
                    color: 'red',
                    title: 'Error',
                    message: 'Failed to load flow configuration',
                    time: new Date().toISOString()
                });
            }

            this.flowConfigLoading = false;
        },

        async saveFlowConfig() {
            this.savingConfig = true;
            const token = localStorage.getItem('token');

            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flow-config`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.flowConfig)
                });

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    alert('Server error: Invalid response');
                    this.savingConfig = false;
                    return;
                }

                if (data.success) {
                    this.flowConfig = data.data.config || this.flowConfig;
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-check-circle',
                        color: 'green',
                        title: 'Success',
                        message: 'Configuration saved successfully',
                        time: new Date().toISOString()
                    });
                } else {
                    alert(data.message || 'Failed to save configuration');
                }
            } catch (error) {
                console.error('Error saving flow config:', error);
                alert('Failed to save configuration');
            }

            this.savingConfig = false;
        },

        async publishFlowConfig() {
            if (!confirm('Publish this flow? It will be available for customers.')) return;

            this.publishingConfig = true;
            const token = localStorage.getItem('token');

            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flow-config/publish`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    alert('Server error: Invalid response');
                    this.publishingConfig = false;
                    return;
                }

                if (data.success) {
                    this.flowConfig = data.data || this.flowConfig;
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-rocket',
                        color: 'green',
                        title: 'Success',
                        message: 'Flow published successfully',
                        time: new Date().toISOString()
                    });
                } else {
                    alert(data.message || 'Failed to publish flow');
                }
            } catch (error) {
                console.error('Error publishing flow config:', error);
                alert('Failed to publish flow');
            }

            this.publishingConfig = false;
        },

        async sendConfigFlow() {
            if (!this.sendConfigFlowPhone) {
                alert('Please enter customer phone number');
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flow-config/send`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ phone: this.sendConfigFlowPhone })
                });
                const data = await response.json();

                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-paper-plane',
                        color: 'blue',
                        title: 'Success',
                        message: 'Flow sent to customer',
                        time: new Date().toISOString()
                    });
                    this.showSendConfigFlowModal = false;
                    this.sendConfigFlowPhone = '';
                } else {
                    alert(data.message || 'Failed to send flow');
                }
            } catch (error) {
                console.error('Error sending flow:', error);
                alert('Failed to send flow');
            }
        },

        toggleOperatingDay(day) {
            if (!this.flowConfig.operating_days) {
                this.flowConfig.operating_days = [];
            }

            const index = this.flowConfig.operating_days.indexOf(day);
            if (index > -1) {
                this.flowConfig.operating_days.splice(index, 1);
            } else {
                this.flowConfig.operating_days.push(day);
                this.flowConfig.operating_days.sort((a, b) => a - b);
            }
        },

        toggleTableSelection(tableId) {
            if (!this.flowConfig.available_table_ids) {
                this.flowConfig.available_table_ids = [];
            }

            const index = this.flowConfig.available_table_ids.indexOf(tableId);
            if (index > -1) {
                this.flowConfig.available_table_ids.splice(index, 1);
            } else {
                this.flowConfig.available_table_ids.push(tableId);
            }
        },

        toggleProductSelection(productId) {
            if (!this.flowConfig.available_product_ids) {
                this.flowConfig.available_product_ids = [];
            }

            const index = this.flowConfig.available_product_ids.indexOf(productId);
            if (index > -1) {
                this.flowConfig.available_product_ids.splice(index, 1);
            } else {
                this.flowConfig.available_product_ids.push(productId);
            }
        },

        selectAllTables() {
            this.flowConfig.available_table_ids = this.availableTables.map(t => t.id);
        },

        selectAllProducts() {
            this.flowConfig.available_product_ids = this.availableProducts.map(p => p.id);
        },

        // Template Methods
        applyTemplate() {
            if (!this.selectedTemplate) return;

            const template = this.flowTemplates.find(t => t.id === this.selectedTemplate);
            if (!template) return;

            // Apply template config to flowConfig
            Object.keys(template.config).forEach(key => {
                this.flowConfig[key] = template.config[key];
            });

            // Close modal and switch to config tab
            this.showTemplateModal = false;
            this.selectedTemplate = null;
            this.activeTab = 'flow-config';

            // Show success notification
            this.addNotification({
                type: 'success',
                icon: 'fa-magic',
                color: 'purple',
                title: 'Template Diterapkan',
                message: `Template "${template.name}" berhasil diterapkan. Silakan sesuaikan konfigurasi.`,
                time: new Date().toISOString()
            });

            // Save the config
            this.saveFlowConfig();
        },

        // Tooltip helper
        showTooltip(event, text) {
            // Remove existing tooltip
            const existingTooltip = document.querySelector('.flow-tooltip');
            if (existingTooltip) existingTooltip.remove();

            // Create tooltip element
            const tooltip = document.createElement('div');
            tooltip.className = 'flow-tooltip fixed z-[100] px-3 py-2 text-sm bg-foreground text-background rounded-lg shadow-lg max-w-xs';
            tooltip.textContent = text;
            document.body.appendChild(tooltip);

            // Position tooltip
            const rect = event.target.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top - tooltip.offsetHeight - 8}px`;

            // Auto-hide after 3 seconds
            setTimeout(() => tooltip.remove(), 3000);
        },

        hideTooltip() {
            const tooltip = document.querySelector('.flow-tooltip');
            if (tooltip) tooltip.remove();
        },
    };
}
</script>
@endsection
