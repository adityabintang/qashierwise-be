@extends('layouts.app')

@section('title', 'AI Agent - QashierWise')

@section('content')
<div x-data="aiAgentApp()" x-init="init()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'ai-agent'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => 'AI Agent', 'description' => 'Configure your WhatsApp AI Assistant'])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-4xl mx-auto">

                <!-- Loading State -->
                <div x-show="loading" class="card p-6">
                    <div class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                        <span class="ml-3 text-[hsl(var(--muted-foreground))]">Loading AI Agent configuration...</span>
                    </div>
                </div>

                <!-- No WhatsApp Account Connected -->
                <div x-show="!loading && !hasWhatsAppAccount" class="card p-8 md:p-12">
                    <div class="text-center max-w-sm mx-auto">
                        <div class="h-20 w-20 rounded-full bg-gradient-to-br from-emerald-50 to-emerald-100 flex items-center justify-center mx-auto mb-6 shadow-sm">
                            <i class="fab fa-whatsapp text-4xl text-emerald-500"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-[hsl(var(--foreground))] mb-3">WhatsApp Account Required</h3>
                        <p class="text-[hsl(var(--muted-foreground))] mb-8 leading-relaxed">
                            Please connect your WhatsApp Business account first before configuring AI Agent.
                        </p>
                        <a href="/dashboard/whatsapp-account"
                           class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-medium rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                            <i class="fab fa-whatsapp text-lg"></i>
                            <span>Connect WhatsApp Account</span>
                        </a>
                    </div>
                </div>

                <!-- Main Configuration -->
                <div x-show="!loading && hasWhatsAppAccount" x-cloak>

                    <!-- Status Card -->
                    <div class="card mb-6">
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-12 w-12 rounded-xl bg-purple-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-robot text-purple-600 text-2xl"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold">AI Agent Status</h2>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]">
                                            <span x-show="config.is_active" class="text-emerald-600 font-medium">
                                                <i class="fas fa-circle text-xs mr-1"></i> Active
                                            </span>
                                            <span x-show="!config.is_active" class="text-gray-500">
                                                <i class="fas fa-circle text-xs mr-1"></i> Inactive
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <!-- Active Toggle -->
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <span class="text-sm font-medium text-[hsl(var(--foreground))]">Enable AI Agent</span>
                                        <button
                                            type="button"
                                            @click="config.is_active = !config.is_active"
                                            :disabled="togglingActive"
                                            :class="config.is_active ? 'bg-emerald-500' : 'bg-gray-300'"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 cursor-pointer"
                                        >
                                            <span
                                                :class="config.is_active ? 'translate-x-6' : 'translate-x-1'"
                                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"
                                            ></span>
                                        </button>
                                    </label>
                                </div>
                            </div>
                            <!-- Info if not saved -->
                            <p x-show="!config.id" class="text-xs text-amber-600 mt-3 flex items-center gap-1">
                                <i class="fas fa-info-circle"></i>
                                <span>Simpan konfigurasi untuk mengaktifkan AI Agent</span>
                            </p>
                        </div>
                    </div>

                    <!-- Configuration Form -->
                    <form @submit.prevent="saveConfig()" class="space-y-6">

                        <!-- Bot Identity Card -->
                        <div class="card">
                            <div class="card-header border-b border-[hsl(var(--border))]">
                                <h3 class="card-title flex items-center gap-2">
                                    <i class="fas fa-id-card text-purple-500"></i>
                                    Bot Identity
                                </h3>
                            </div>
                            <div class="p-4 sm:p-6 space-y-5">
                                <!-- Bot Name -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-[hsl(var(--foreground))]">
                                        <i class="fas fa-robot text-purple-400 mr-1.5"></i>
                                        Bot Name <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        x-model="form.bot_name"
                                        placeholder="Contoh: Assistant Resto Saya"
                                        class="w-full h-10 px-3 rounded-lg border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                        required
                                    >
                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                        Nama yang akan digunakan AI untuk memperkenalkan diri
                                    </p>
                                </div>

                                <!-- System Prompt -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-[hsl(var(--foreground))]">
                                        <i class="fas fa-comment-alt text-purple-400 mr-1.5"></i>
                                        System Prompt <span class="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        x-model="form.system_prompt"
                                        rows="6"
                                        placeholder="Contoh: Kamu adalah asisten virtual untuk restaurant seafood. Tugas utamamu adalah membantu pelanggan dengan ramah dan profesional. Jawab pertanyaan tentang menu, harga, dan jam operasional. Gunakan bahasa Indonesia yang sopan."
                                        class="w-full px-3 py-2.5 rounded-lg border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-y min-h-[150px]"
                                        required
                                    ></textarea>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                        Instruksi dasar untuk AI Agent. Jelaskan bagaimana bot harus berperilaku, fungsinya, dan gaya bahasa yang digunakan.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Business Information Card -->
                        <div class="card">
                            <div class="card-header border-b border-[hsl(var(--border))]">
                                <h3 class="card-title flex items-center gap-2">
                                    <i class="fas fa-store text-blue-500"></i>
                                    Business Information
                                </h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">
                                    Informasi ini akan disertakan dalam konteks AI agar bisa menjawab pertanyaan pelanggan
                                </p>
                            </div>
                            <div class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                                <!-- Operating Hours -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-[hsl(var(--foreground))]">
                                        <i class="fas fa-clock text-blue-400 mr-1.5"></i>
                                        Jam Operasional
                                    </label>
                                    <input
                                        type="text"
                                        x-model="form.business_info.operating_hours"
                                        placeholder="Contoh: Senin-Jumat 08:00-22:00"
                                        class="w-full h-10 px-3 rounded-lg border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                    >
                                </div>

                                <!-- Phone -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-[hsl(var(--foreground))]">
                                        <i class="fas fa-phone text-blue-400 mr-1.5"></i>
                                        Nomor Telepon
                                    </label>
                                    <input
                                        type="text"
                                        x-model="form.business_info.phone"
                                        placeholder="Contoh: 021-1234567"
                                        class="w-full h-10 px-3 rounded-lg border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                    >
                                </div>

                                <!-- Address -->
                                <div class="md:col-span-2 space-y-2">
                                    <label class="block text-sm font-medium text-[hsl(var(--foreground))]">
                                        <i class="fas fa-map-marker-alt text-blue-400 mr-1.5"></i>
                                        Alamat
                                    </label>
                                    <input
                                        type="text"
                                        x-model="form.business_info.address"
                                        placeholder="Contoh: Jl. Sudirman No. 123, Jakarta Pusat"
                                        class="w-full h-10 px-3 rounded-lg border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                    >
                                </div>

                                <!-- Description -->
                                <div class="md:col-span-2 space-y-2">
                                    <label class="block text-sm font-medium text-[hsl(var(--foreground))]">
                                        <i class="fas fa-info-circle text-blue-400 mr-1.5"></i>
                                        Deskripsi Bisnis
                                    </label>
                                    <textarea
                                        x-model="form.business_info.description"
                                        rows="3"
                                        placeholder="Contoh: Restaurant seafood premium dengan menu andalan kepiting saus padang dan udang bakar madu. Menyediakan private room untuk acara keluarga."
                                        class="w-full px-3 py-2.5 rounded-lg border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-y min-h-[80px]"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Order Feature Card -->
                        <div class="card">
                            <div class="card-header border-b border-[hsl(var(--border))]">
                                <h3 class="card-title flex items-center gap-2">
                                    <i class="fas fa-shopping-cart text-emerald-500"></i>
                                    Order Feature
                                </h3>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">
                                    Aktifkan fitur ini agar AI Agent dapat membantu pelanggan melakukan pemesanan
                                </p>
                            </div>
                            <div class="p-4 sm:p-6 space-y-5">
                                <!-- Default Store Selection - FIRST -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-[hsl(var(--foreground))]">
                                        <i class="fas fa-store text-emerald-400 mr-1.5"></i>
                                        Pilih Store
                                        <span class="text-red-500" x-show="config.order_enabled">*</span>
                                    </label>
                                    <select
                                        x-model="form.default_store_id"
                                        class="w-full h-10 px-3 rounded-lg border border-[hsl(var(--input))] bg-[hsl(var(--background))] text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                                        :required="config.order_enabled"
                                    >
                                        <option value="">-- Pilih Store --</option>
                                        <template x-for="store in stores" :key="store.id">
                                            <option :value="store.id" x-text="store.name"></option>
                                        </template>
                                    </select>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                        Order dari AI Agent akan masuk ke store ini
                                    </p>
                                    <p x-show="stores.length === 0" class="text-xs text-amber-600 mt-1 flex items-center gap-1">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Belum ada store. <a href="/dashboard/pos/stores" class="underline hover:no-underline">Buat store terlebih dahulu</a>
                                    </p>
                                </div>

                                <!-- Order Toggle - AFTER store selection -->
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-xl" :class="form.default_store_id ? 'bg-emerald-50 border border-emerald-200' : 'bg-gray-50 border border-gray-200'">
                                    <div class="flex-1">
                                        <p class="font-medium" :class="form.default_store_id ? 'text-emerald-900' : 'text-gray-600'">Enable Order via Chat</p>
                                        <p class="text-sm" :class="form.default_store_id ? 'text-emerald-700' : 'text-gray-500'">
                                            <span x-show="form.default_store_id">Pelanggan bisa melihat produk, menambah ke keranjang, dan order langsung via chat</span>
                                            <span x-show="!form.default_store_id"><i class="fas fa-info-circle mr-1"></i>Pilih store terlebih dahulu untuk mengaktifkan fitur ini</span>
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="if(form.default_store_id) config.order_enabled = !config.order_enabled"
                                        :disabled="!form.default_store_id"
                                        :class="[
                                            config.order_enabled && form.default_store_id ? 'bg-emerald-500' : 'bg-gray-300',
                                            !form.default_store_id ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                        ]"
                                        class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 flex-shrink-0"
                                    >
                                        <span
                                            :class="config.order_enabled && form.default_store_id ? 'translate-x-6' : 'translate-x-1'"
                                            class="inline-block h-5 w-5 transform rounded-full bg-white transition-transform shadow-md"
                                        ></span>
                                    </button>
                                </div>

                                <!-- Order Info -->
                                <div x-show="config.order_enabled && form.default_store_id" x-transition class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                                    <p class="text-sm text-emerald-800 font-medium">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Order feature aktif! AI Agent dapat:
                                    </p>
                                    <ul class="text-sm text-emerald-700 mt-2 ml-6 list-disc space-y-1">
                                        <li>Mencari dan menampilkan produk</li>
                                        <li>Menambahkan produk ke keranjang</li>
                                        <li>Menampilkan ringkasan pesanan</li>
                                        <li>Membuat order setelah konfirmasi</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="card">
                            <div class="p-4 sm:p-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <p x-show="lastSaved" class="text-sm text-[hsl(var(--muted-foreground))]">
                                            <i class="fas fa-check-circle text-emerald-500 mr-1"></i>
                                            Terakhir disimpan: <span x-text="lastSaved"></span>
                                        </p>
                                        <p x-show="!lastSaved && !config.id" class="text-sm text-amber-600 flex items-center gap-1">
                                            <i class="fas fa-info-circle"></i>
                                            <span>Konfigurasi belum pernah disimpan</span>
                                        </p>
                                    </div>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                                        <button
                                            type="button"
                                            @click="openTestModal()"
                                            :disabled="!config.is_active || !form.bot_name || !form.system_prompt"
                                            class="h-10 px-4 rounded-lg border font-medium text-sm flex items-center justify-center gap-2 transition-all"
                                            :class="(!config.is_active || !form.bot_name || !form.system_prompt) ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed' : 'border-purple-300 text-purple-600 bg-white hover:bg-purple-50 hover:border-purple-400 cursor-pointer'"
                                        >
                                            <i class="fas fa-flask"></i>
                                            <span>Test AI Agent</span>
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="saving || !form.bot_name || !form.system_prompt"
                                            class="h-10 px-6 rounded-lg font-medium text-sm flex items-center justify-center gap-2 text-white transition-all"
                                            :class="(saving || !form.bot_name || !form.system_prompt) ? 'bg-purple-300 cursor-not-allowed' : 'bg-purple-600 hover:bg-purple-700 cursor-pointer shadow-sm hover:shadow'"
                                        >
                                            <i class="fas fa-save" x-show="!saving"></i>
                                            <i class="fas fa-spinner animate-spin" x-show="saving"></i>
                                            <span x-text="saving ? 'Menyimpan...' : 'Simpan Konfigurasi'"></span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Help text for disabled buttons -->
                                <p x-show="!form.bot_name || !form.system_prompt" class="text-xs text-amber-600 mt-3 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>Lengkapi Bot Name dan System Prompt untuk menyimpan konfigurasi</span>
                                </p>
                                <p x-show="form.bot_name && form.system_prompt && !config.is_active" class="text-xs text-blue-600 mt-3 flex items-center gap-1">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Aktifkan AI Agent (toggle di atas) lalu simpan untuk bisa melakukan test</span>
                                </p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Test Modal -->
    <div
        x-show="showTestModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @keydown.escape.window="showTestModal = false"
    >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="showTestModal = false"></div>

        <!-- Modal Content -->
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-semibold flex items-center gap-2">
                    <i class="fas fa-flask text-purple-500"></i>
                    Test AI Agent
                </h3>
                <button @click="showTestModal = false" class="btn btn-ghost btn-icon">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Chat Area -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 min-h-[300px] max-h-[400px]" id="testChatArea">
                <template x-for="(msg, index) in testMessages" :key="index">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div :class="msg.role === 'user' ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-800'" class="rounded-lg px-4 py-2 max-w-[80%]">
                            <p class="text-sm whitespace-pre-wrap" x-text="msg.content"></p>
                        </div>
                    </div>
                </template>
                <div x-show="testLoading" class="flex justify-start">
                    <div class="bg-gray-100 rounded-lg px-4 py-2">
                        <i class="fas fa-spinner animate-spin text-gray-500"></i>
                        <span class="text-sm text-gray-500 ml-2">AI is typing...</span>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-4 border-t bg-gray-50/50">
                <form @submit.prevent="sendTestMessage()" class="flex items-center gap-3">
                    <input
                        type="text"
                        x-model="testInput"
                        placeholder="Ketik pesan untuk test AI Agent..."
                        class="flex-1 px-4 py-2.5 border border-gray-200 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"
                        :disabled="testLoading"
                    >
                    <button
                        type="submit"
                        :disabled="testLoading || !testInput.trim()"
                        class="flex items-center justify-center h-10 w-10 rounded-full bg-purple-500 hover:bg-purple-600 text-white transition-all duration-200 shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-purple-500 disabled:hover:shadow-sm"
                    >
                        <i class="fas fa-paper-plane text-sm" :class="testLoading ? 'opacity-0' : ''"></i>
                        <i x-show="testLoading" class="fas fa-spinner animate-spin text-sm absolute"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function aiAgentApp() {
    return {
        // Sidebar state
        sidebarOpen: true,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        // Page state
        loading: true,
        saving: false,
        hasWhatsAppAccount: false,
        togglingActive: false,
        togglingOrder: false,
        lastSaved: null,
        stores: [],
        config: {
            id: null,
            is_active: false,
            order_enabled: false,
        },
        form: {
            bot_name: '',
            system_prompt: '',
            business_info: {
                operating_hours: '',
                address: '',
                description: '',
                phone: '',
            },
            default_store_id: '',
        },
        // Test modal
        showTestModal: false,
        testInput: '',
        testMessages: [],
        testLoading: false,

        async init() {
            // Initialize sidebar state
            this.initSidebar();

            try {
                // Load stores
                await this.loadStores();

                // Load AI Agent config
                await this.loadConfig();
            } catch (error) {
                console.error('Init error:', error);
            } finally {
                this.loading = false;
            }
        },

        async loadStores() {
            const token = localStorage.getItem('token');
            try {
                const response = await fetch('/api/pos/stores', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    }
                });
                const data = await response.json();
                if (data.success && data.data) {
                    this.stores = data.data.filter(s => s.is_active);
                }
            } catch (error) {
                console.error('Failed to load stores:', error);
            }
        },

        async loadConfig() {
            const token = localStorage.getItem('token');
            try {
                const response = await fetch('/api/ai-agent', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();

                if (response.status === 404 || !data.success) {
                    // WhatsApp account not connected
                    this.hasWhatsAppAccount = false;
                    return;
                }

                this.hasWhatsAppAccount = true;

                if (data.success && data.data) {
                    this.config = {
                        id: data.data.id,
                        is_active: data.data.is_active,
                        order_enabled: data.data.order_enabled,
                    };
                    this.form = {
                        bot_name: data.data.bot_name || '',
                        system_prompt: data.data.system_prompt || '',
                        business_info: {
                            operating_hours: data.data.business_info?.operating_hours || '',
                            address: data.data.business_info?.address || '',
                            description: data.data.business_info?.description || '',
                            phone: data.data.business_info?.phone || '',
                        },
                        default_store_id: data.data.default_store_id || '',
                    };
                    if (data.data.updated_at) {
                        this.lastSaved = new Date(data.data.updated_at).toLocaleString('id-ID');
                    }
                } else if (data.data === null) {
                    // AI Agent not configured yet, but WhatsApp is connected
                    this.hasWhatsAppAccount = true;
                }
            } catch (error) {
                console.error('Failed to load config:', error);
                // Assume WhatsApp is connected but there was a network error
                this.hasWhatsAppAccount = true;
            }
        },

        async saveConfig() {
            this.saving = true;
            const token = localStorage.getItem('token');

            try {
                const response = await fetch('/api/ai-agent', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        bot_name: this.form.bot_name,
                        system_prompt: this.form.system_prompt,
                        business_info: this.form.business_info,
                        default_store_id: this.form.default_store_id || null,
                        is_active: this.config.is_active,
                        order_enabled: this.config.order_enabled,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    this.config.id = data.data.id;
                    this.lastSaved = new Date().toLocaleString('id-ID');
                    this.showNotification('Configuration saved successfully!', 'success');
                } else {
                    this.showNotification(data.message || 'Failed to save', 'error');
                }
            } catch (error) {
                console.error('Save error:', error);
                this.showNotification('Failed to save configuration', 'error');
            } finally {
                this.saving = false;
            }
        },

        async toggleActive() {
            if (!this.config.id) {
                this.showNotification('Please save configuration first', 'warning');
                return;
            }

            this.togglingActive = true;
            const token = localStorage.getItem('token');

            try {
                const response = await fetch('/api/ai-agent/toggle-active', {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    this.config.is_active = data.data.is_active;
                    this.showNotification(data.message, 'success');
                } else {
                    this.showNotification(data.message || 'Failed to toggle', 'error');
                }
            } catch (error) {
                console.error('Toggle error:', error);
                this.showNotification('Failed to toggle AI Agent', 'error');
            } finally {
                this.togglingActive = false;
            }
        },

        async toggleOrder() {
            if (!this.config.id) {
                this.showNotification('Please save configuration first', 'warning');
                return;
            }

            if (!this.form.default_store_id) {
                this.showNotification('Please select a default store first', 'warning');
                return;
            }

            this.togglingOrder = true;
            const token = localStorage.getItem('token');

            try {
                const response = await fetch('/api/ai-agent/toggle-order', {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    this.config.order_enabled = data.data.order_enabled;
                    this.showNotification(data.message, 'success');
                } else {
                    this.showNotification(data.message || 'Failed to toggle', 'error');
                }
            } catch (error) {
                console.error('Toggle error:', error);
                this.showNotification('Failed to toggle order feature', 'error');
            } finally {
                this.togglingOrder = false;
            }
        },

        openTestModal() {
            this.showTestModal = true;
            this.testMessages = [];
            this.testInput = '';
        },

        async sendTestMessage() {
            if (!this.testInput.trim() || this.testLoading) return;

            const message = this.testInput.trim();
            this.testMessages.push({ role: 'user', content: message });
            this.testInput = '';
            this.testLoading = true;

            // Scroll to bottom
            this.$nextTick(() => {
                const chatArea = document.getElementById('testChatArea');
                if (chatArea) chatArea.scrollTop = chatArea.scrollHeight;
            });

            const token = localStorage.getItem('token');

            try {
                const response = await fetch('/api/ai-agent/test', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ message }),
                });

                const data = await response.json();

                if (data.success) {
                    this.testMessages.push({ role: 'assistant', content: data.data.ai_response });
                } else {
                    this.testMessages.push({ role: 'assistant', content: 'Error: ' + (data.message || 'Failed to get response') });
                }
            } catch (error) {
                console.error('Test error:', error);
                this.testMessages.push({ role: 'assistant', content: 'Error: Failed to connect to AI Agent' });
            } finally {
                this.testLoading = false;
                this.$nextTick(() => {
                    const chatArea = document.getElementById('testChatArea');
                    if (chatArea) chatArea.scrollTop = chatArea.scrollHeight;
                });
            }
        },

        showNotification(message, type = 'info') {
            // Simple alert for now, can be replaced with toast
            if (type === 'error') {
                alert('Error: ' + message);
            } else if (type === 'warning') {
                alert('Warning: ' + message);
            } else {
                alert(message);
            }
        },

        // Sidebar initialization
        initSidebar() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                let savedState = localStorage.getItem('sidebarOpen');
                this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
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
                try { this.notifications = JSON.parse(savedNotifs); }
                catch (e) { this.notifications = []; }
            }
        },

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

        logout() {
            let token = localStorage.getItem('token');
            if (token) {
                fetch(`${window.location.origin}/api/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                }).finally(() => this.clearAndRedirect());
            } else {
                this.clearAndRedirect();
            }
        },

        clearAndRedirect() {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            localStorage.removeItem('sidebarOpen');
            localStorage.removeItem('notifications');
            window.location.href = '/login';
        }
    };
}
</script>
@endpush
@endsection
