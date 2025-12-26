@extends('layouts.app')

@section('title', 'Provider Settings - QashierWise')

@section('content')
<div x-data="providerSettingsApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant-provider-settings'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Provider Settings', 'description' => 'Manage your payment provider credentials'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Loading State -->
                <template x-if="loading">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card p-6">
                            <div class="skeleton h-8 w-48 mb-4"></div>
                            <div class="skeleton h-4 w-full mb-2"></div>
                            <div class="skeleton h-4 w-3/4"></div>
                        </div>
                    </div>
                </template>

                <!-- Main Content -->
                <template x-if="!loading">
                    <div class="space-y-6">
                        <!-- Active Provider Banner -->
                        <div class="card" :class="activeProvider ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'">
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-lg flex items-center justify-center" :class="activeProvider ? 'bg-emerald-500' : 'bg-amber-500'">
                                        <i class="fas text-white" :class="activeProvider ? 'fa-check-circle' : 'fa-exclamation-triangle'"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold" :class="activeProvider ? 'text-emerald-900' : 'text-amber-900'">
                                            <span x-show="activeProvider">Active Provider: <span x-text="getProviderDisplayName(activeProvider)"></span></span>
                                            <span x-show="!activeProvider">No Active Provider</span>
                                        </p>
                                        <p class="text-sm" :class="activeProvider ? 'text-emerald-700' : 'text-amber-700'">
                                            <span x-show="activeProvider">All QRIS generation will use this provider</span>
                                            <span x-show="!activeProvider">Please configure and activate a provider to generate QRIS</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Provider Cards Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Doku Provider -->
                            <div class="card" :class="getProviderCardClass('doku')">
                                <div class="card-header !flex-row items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-building text-2xl text-blue-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-lg">Doku</h3>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))]">SNAP API Integration</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span x-show="getProviderStatus('doku')" class="badge" :class="getProviderStatusClass('doku')" x-text="getProviderStatusText('doku')"></span>
                                        <span x-show="isActiveProvider('doku')" class="badge bg-emerald-100 text-emerald-700">
                                            <i class="fas fa-check-circle mr-1"></i>Active
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <template x-if="!isProviderConfigured('doku')">
                                        <div class="text-center py-4">
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">Not configured yet</p>
                                            <button @click="openConfigureModal('doku')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus mr-2"></i>Configure Doku
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="isProviderConfigured('doku')">
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-[hsl(var(--muted-foreground))]">Last Validated</span>
                                                <span x-text="formatDate(getProvider('doku').last_validated_at)"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="validateProvider('doku')" :disabled="validating === 'doku'" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas" :class="validating === 'doku' ? 'fa-spinner animate-spin' : 'fa-sync'"></i>
                                                    Revalidate
                                                </button>
                                                <button @click="openConfigureModal('doku')" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                            </div>
                                            <button @click="setActive('doku')" :disabled="!isProviderValid('doku') || isActiveProvider('doku') || settingActive" class="btn btn-sm w-full" :class="isActiveProvider('doku') ? 'btn-outline' : 'btn-primary'">
                                                <i class="fas" :class="settingActive ? 'fa-spinner animate-spin' : (isActiveProvider('doku') ? 'fa-check' : 'fa-power-off')"></i>
                                                <span x-text="isActiveProvider('doku') ? 'Currently Active' : 'Set as Active'"></span>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Xendit Provider -->
                            <div class="card" :class="getProviderCardClass('xendit')">
                                <div class="card-header !flex-row items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-lg bg-purple-100 flex items-center justify-center">
                                            <i class="fas fa-bolt text-2xl text-purple-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-lg">Xendit</h3>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))]">QR Codes API</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span x-show="getProviderStatus('xendit')" class="badge" :class="getProviderStatusClass('xendit')" x-text="getProviderStatusText('xendit')"></span>
                                        <span x-show="isActiveProvider('xendit')" class="badge bg-emerald-100 text-emerald-700">
                                            <i class="fas fa-check-circle mr-1"></i>Active
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <template x-if="!isProviderConfigured('xendit')">
                                        <div class="text-center py-4">
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">Not configured yet</p>
                                            <button @click="openConfigureModal('xendit')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus mr-2"></i>Configure Xendit
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="isProviderConfigured('xendit')">
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-[hsl(var(--muted-foreground))]">Last Validated</span>
                                                <span x-text="formatDate(getProvider('xendit').last_validated_at)"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="validateProvider('xendit')" :disabled="validating === 'xendit'" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas" :class="validating === 'xendit' ? 'fa-spinner animate-spin' : 'fa-sync'"></i>
                                                    Revalidate
                                                </button>
                                                <button @click="openConfigureModal('xendit')" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                            </div>
                                            <button @click="setActive('xendit')" :disabled="!isProviderValid('xendit') || isActiveProvider('xendit') || settingActive" class="btn btn-sm w-full" :class="isActiveProvider('xendit') ? 'btn-outline' : 'btn-primary'">
                                                <i class="fas" :class="settingActive ? 'fa-spinner animate-spin' : (isActiveProvider('xendit') ? 'fa-check' : 'fa-power-off')"></i>
                                                <span x-text="isActiveProvider('xendit') ? 'Currently Active' : 'Set as Active'"></span>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Midtrans Provider -->
                            <div class="card" :class="getProviderCardClass('midtrans')">
                                <div class="card-header !flex-row items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-lg bg-green-100 flex items-center justify-center">
                                            <i class="fas fa-credit-card text-2xl text-green-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-lg">Midtrans</h3>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))]">QRIS Charge API</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span x-show="getProviderStatus('midtrans')" class="badge" :class="getProviderStatusClass('midtrans')" x-text="getProviderStatusText('midtrans')"></span>
                                        <span x-show="isActiveProvider('midtrans')" class="badge bg-emerald-100 text-emerald-700">
                                            <i class="fas fa-check-circle mr-1"></i>Active
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <template x-if="!isProviderConfigured('midtrans')">
                                        <div class="text-center py-4">
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">Not configured yet</p>
                                            <button @click="openConfigureModal('midtrans')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus mr-2"></i>Configure Midtrans
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="isProviderConfigured('midtrans')">
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-[hsl(var(--muted-foreground))]">Last Validated</span>
                                                <span x-text="formatDate(getProvider('midtrans').last_validated_at)"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="validateProvider('midtrans')" :disabled="validating === 'midtrans'" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas" :class="validating === 'midtrans' ? 'fa-spinner animate-spin' : 'fa-sync'"></i>
                                                    Revalidate
                                                </button>
                                                <button @click="openConfigureModal('midtrans')" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                            </div>
                                            <button @click="setActive('midtrans')" :disabled="!isProviderValid('midtrans') || isActiveProvider('midtrans') || settingActive" class="btn btn-sm w-full" :class="isActiveProvider('midtrans') ? 'btn-outline' : 'btn-primary'">
                                                <i class="fas" :class="settingActive ? 'fa-spinner animate-spin' : (isActiveProvider('midtrans') ? 'fa-check' : 'fa-power-off')"></i>
                                                <span x-text="isActiveProvider('midtrans') ? 'Currently Active' : 'Set as Active'"></span>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Duitku Provider -->
                            <div class="card" :class="getProviderCardClass('duitku')">
                                <div class="card-header !flex-row items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-lg bg-orange-100 flex items-center justify-center">
                                            <i class="fas fa-wallet text-2xl text-orange-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-lg">Duitku</h3>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))]">Invoice API</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span x-show="getProviderStatus('duitku')" class="badge" :class="getProviderStatusClass('duitku')" x-text="getProviderStatusText('duitku')"></span>
                                        <span x-show="isActiveProvider('duitku')" class="badge bg-emerald-100 text-emerald-700">
                                            <i class="fas fa-check-circle mr-1"></i>Active
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <template x-if="!isProviderConfigured('duitku')">
                                        <div class="text-center py-4">
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">Not configured yet</p>
                                            <button @click="openConfigureModal('duitku')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus mr-2"></i>Configure Duitku
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="isProviderConfigured('duitku')">
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-[hsl(var(--muted-foreground))]">Last Validated</span>
                                                <span x-text="formatDate(getProvider('duitku').last_validated_at)"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="validateProvider('duitku')" :disabled="validating === 'duitku'" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas" :class="validating === 'duitku' ? 'fa-spinner animate-spin' : 'fa-sync'"></i>
                                                    Revalidate
                                                </button>
                                                <button @click="openConfigureModal('duitku')" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                            </div>
                                            <button @click="setActive('duitku')" :disabled="!isProviderValid('duitku') || isActiveProvider('duitku') || settingActive" class="btn btn-sm w-full" :class="isActiveProvider('duitku') ? 'btn-outline' : 'btn-primary'">
                                                <i class="fas" :class="settingActive ? 'fa-spinner animate-spin' : (isActiveProvider('duitku') ? 'fa-check' : 'fa-power-off')"></i>
                                                <span x-text="isActiveProvider('duitku') ? 'Currently Active' : 'Set as Active'"></span>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>

    <!-- Configure Provider Modal -->
    <div x-show="showConfigModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showConfigModal" x-transition class="fixed inset-0 bg-black/50" @click="closeConfigModal()"></div>
        <div x-show="showConfigModal" x-transition class="card relative w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                <h3 class="text-lg font-semibold">Configure <span x-text="getProviderDisplayName(selectedProvider)"></span></h3>
                <button @click="closeConfigModal()" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveProvider()" class="p-4 overflow-y-auto space-y-4">
                <!-- Doku Fields -->
                <template x-if="selectedProvider === 'doku'">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Client ID <span class="text-red-500">*</span></label>
                            <input type="text" x-model="configForm.client_id" required class="input w-full" placeholder="Enter Doku Client ID">
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Secret Key <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.secret_key" required class="input w-full pr-10" placeholder="Enter Doku Secret Key">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Xendit Fields -->
                <template x-if="selectedProvider === 'xendit'">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">API Key <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.api_key" required class="input w-full pr-10" placeholder="Enter Xendit API Key">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Callback Token <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.callback_token" required class="input w-full pr-10" placeholder="Enter Xendit Callback Token">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Midtrans Fields -->
                <template x-if="selectedProvider === 'midtrans'">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Server Key <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.server_key" required class="input w-full pr-10" placeholder="Enter Midtrans Server Key">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Client Key <span class="text-red-500">*</span></label>
                            <input type="text" x-model="configForm.client_key" required class="input w-full" placeholder="Enter Midtrans Client Key">
                        </div>
                    </div>
                </template>

                <!-- Duitku Fields -->
                <template x-if="selectedProvider === 'duitku'">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Merchant Code <span class="text-red-500">*</span></label>
                            <input type="text" x-model="configForm.merchant_code" required class="input w-full" placeholder="Enter Duitku Merchant Code">
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">API Key <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.api_key" required class="input w-full pr-10" placeholder="Enter Duitku API Key">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Error Message -->
                <template x-if="errorMessage">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="flex items-center gap-2 text-red-700">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="text-sm" x-text="errorMessage"></span>
                        </div>
                    </div>
                </template>

                <!-- Success Message -->
                <template x-if="successMessage">
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                        <div class="flex items-center gap-2 text-emerald-700">
                            <i class="fas fa-check-circle"></i>
                            <span class="text-sm" x-text="successMessage"></span>
                        </div>
                    </div>
                </template>

                <!-- Actions -->
                <div class="flex gap-2 pt-4">
                    <button type="button" @click="closeConfigModal()" class="btn btn-outline flex-1">Cancel</button>
                    <button type="submit" :disabled="saving" class="btn btn-primary flex-1">
                        <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                        <span x-text="saving ? 'Saving...' : 'Save & Validate'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function providerSettingsApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/sub-merchant/providers',
        loading: true,
        saving: false,
        validating: null,
        settingActive: false,
        providers: [],
        activeProvider: null,
        showConfigModal: false,
        selectedProvider: null,
        configForm: {},
        showPassword: false,
        errorMessage: '',
        successMessage: '',
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,

        async init() {
            this.initSidebar();
            await this.fetchProviders();
        },

        initSidebar() {
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
        },

        async fetchProviders() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.providers = data.data.providers || [];
                    this.activeProvider = data.data.active_provider || null;
                }
            } catch (e) {
                console.error('Error fetching providers:', e);
            } finally {
                this.loading = false;
            }
        },

        getProvider(providerName) {
            return this.providers.find(p => p.provider === providerName);
        },

        isProviderConfigured(providerName) {
            return this.providers.some(p => p.provider === providerName);
        },

        isProviderValid(providerName) {
            const provider = this.getProvider(providerName);
            return provider && provider.connection_status === 'valid';
        },

        isActiveProvider(providerName) {
            return this.activeProvider === providerName;
        },

        getProviderStatus(providerName) {
            const provider = this.getProvider(providerName);
            return provider ? provider.connection_status : null;
        },

        getProviderStatusText(providerName) {
            const status = this.getProviderStatus(providerName);
            if (status === 'valid') return 'Valid';
            if (status === 'invalid') return 'Invalid';
            return 'Pending';
        },

        getProviderStatusClass(providerName) {
            const status = this.getProviderStatus(providerName);
            if (status === 'valid') return 'bg-emerald-100 text-emerald-700';
            if (status === 'invalid') return 'bg-red-100 text-red-700';
            return 'bg-amber-100 text-amber-700';
        },

        getProviderCardClass(providerName) {
            if (this.isActiveProvider(providerName)) {
                return 'border-emerald-300 shadow-sm';
            }
            return '';
        },

        getProviderDisplayName(providerName) {
            const names = {
                'doku': 'Doku',
                'xendit': 'Xendit',
                'midtrans': 'Midtrans',
                'duitku': 'Duitku'
            };
            return names[providerName] || providerName;
        },

        openConfigureModal(providerName) {
            this.selectedProvider = providerName;
            this.showConfigModal = true;
            this.errorMessage = '';
            this.successMessage = '';
            this.showPassword = false;
            
            // Load existing credentials if editing
            const provider = this.getProvider(providerName);
            if (provider) {
                // Note: We don't load actual credentials for security, user must re-enter
                this.configForm = {};
            } else {
                this.configForm = {};
            }
        },

        closeConfigModal() {
            this.showConfigModal = false;
            this.selectedProvider = null;
            this.configForm = {};
            this.errorMessage = '';
            this.successMessage = '';
        },

        async saveProvider() {
            this.saving = true;
            this.errorMessage = '';
            this.successMessage = '';

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        provider: this.selectedProvider,
                        credentials: this.configForm
                    })
                });
                const data = await res.json();

                if (data.success) {
                    this.successMessage = 'Provider configured and validated successfully!';
                    await this.fetchProviders();
                    setTimeout(() => {
                        this.closeConfigModal();
                    }, 1500);
                } else {
                    this.errorMessage = data.error?.message || 'Failed to save provider credentials';
                }
            } catch (e) {
                console.error('Error saving provider:', e);
                this.errorMessage = 'An error occurred. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async validateProvider(providerName) {
            this.validating = providerName;
            try {
                const token = localStorage.getItem('token');
                const provider = this.getProvider(providerName);
                if (!provider) {
                    alert('Provider not found');
                    return;
                }
                const res = await fetch(`${this.API_BASE_URL}/${provider.id}/revalidate`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();

                if (data.success) {
                    await this.fetchProviders();
                } else {
                    alert(data.error?.message || 'Validation failed');
                }
            } catch (e) {
                console.error('Error validating provider:', e);
                alert('An error occurred during validation');
            } finally {
                this.validating = null;
            }
        },

        async setActive(providerName) {
            if (!confirm(`Set ${this.getProviderDisplayName(providerName)} as your active provider? All QRIS generation will use this provider.`)) {
                return;
            }

            this.settingActive = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/set-active`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ provider: providerName })
                });
                const data = await res.json();

                if (data.success) {
                    await this.fetchProviders();
                } else {
                    alert(data.error?.message || 'Failed to set active provider');
                }
            } catch (e) {
                console.error('Error setting active provider:', e);
                alert('An error occurred. Please try again.');
            } finally {
                this.settingActive = false;
            }
        },

        formatDate(dateString) {
            if (!dateString) return 'Never';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        logout() { 
            localStorage.removeItem('token'); 
            localStorage.removeItem('user'); 
            window.location.href = '/login'; 
        }
    }
}
</script>
@endsection
