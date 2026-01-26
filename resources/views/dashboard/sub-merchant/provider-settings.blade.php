@extends('layouts.app')

@section('title', __('submerchant.provider_settings_title'))

@section('content')
<div x-data="providerSettingsApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant-provider-settings'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('submerchant.provider_settings'), 'description' => __('submerchant.manage_provider_credentials')])

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
                                            <span x-show="activeProvider">{{ __('submerchant.active_provider') }}: <span x-text="getProviderDisplayName(activeProvider)"></span></span>
                                            <span x-show="!activeProvider">{{ __('submerchant.no_active_provider') }}</span>
                                        </p>
                                        <p class="text-sm" :class="activeProvider ? 'text-emerald-700' : 'text-amber-700'">
                                            <span x-show="activeProvider">{{ __('submerchant.all_qris_use_provider') }}</span>
                                            <span x-show="!activeProvider">{{ __('submerchant.configure_provider_message') }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Provider Cards Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Doku Provider -->
                            <div class="card opacity-60" :class="getProviderCardClass('doku')">
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
                                        <span class="badge bg-blue-100 text-blue-700">
                                            <i class="fas fa-clock mr-1"></i>{{ __('submerchant.coming_soon') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <div class="text-center py-4">
                                        <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">{{ __('submerchant.integration_coming_soon') }}</p>
                                        <button disabled class="btn btn-outline btn-sm opacity-50 cursor-not-allowed">
                                            <i class="fas fa-lock mr-2"></i>{{ __('submerchant.coming_soon') }}
                                        </button>
                                    </div>
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
                                            <i class="fas fa-check-circle mr-1"></i>{{ __('submerchant.status_active') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <template x-if="!isProviderConfigured('xendit')">
                                        <div class="text-center py-4">
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">{{ __('submerchant.not_configured_yet') }}</p>
                                            <button @click="openConfigureModal('xendit')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus mr-2"></i>{{ __('submerchant.configure') }} Xendit
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="isProviderConfigured('xendit')">
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-[hsl(var(--muted-foreground))]">{{ __('submerchant.last_validated') }}</span>
                                                <span x-text="formatDate(getProvider('xendit').last_validated_at)"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="validateProvider('xendit')" :disabled="validating === 'xendit'" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas" :class="validating === 'xendit' ? 'fa-spinner animate-spin' : 'fa-sync'"></i>
                                                    {{ __('submerchant.revalidate') }}
                                                </button>
                                                <button @click="openConfigureModal('xendit')" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas fa-edit"></i>
                                                    {{ __('submerchant.edit') }}
                                                </button>
                                            </div>
                                            <button @click="setActive('xendit')" :disabled="!isProviderValid('xendit') || isActiveProvider('xendit') || settingActive" class="btn btn-sm w-full" :class="isActiveProvider('xendit') ? 'btn-outline' : 'btn-primary'">
                                                <i class="fas" :class="settingActive ? 'fa-spinner animate-spin' : (isActiveProvider('xendit') ? 'fa-check' : 'fa-power-off')"></i>
                                                <span x-text="isActiveProvider('xendit') ? translations.currentlyActive : translations.setAsActive"></span>
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
                                            <i class="fas fa-check-circle mr-1"></i>{{ __('submerchant.status_active') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <template x-if="!isProviderConfigured('midtrans')">
                                        <div class="text-center py-4">
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">{{ __('submerchant.not_configured_yet') }}</p>
                                            <button @click="openConfigureModal('midtrans')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus mr-2"></i>{{ __('submerchant.configure') }} Midtrans
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="isProviderConfigured('midtrans')">
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-[hsl(var(--muted-foreground))]">{{ __('submerchant.last_validated') }}</span>
                                                <span x-text="formatDate(getProvider('midtrans').last_validated_at)"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="validateProvider('midtrans')" :disabled="validating === 'midtrans'" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas" :class="validating === 'midtrans' ? 'fa-spinner animate-spin' : 'fa-sync'"></i>
                                                    {{ __('submerchant.revalidate') }}
                                                </button>
                                                <button @click="openConfigureModal('midtrans')" class="btn btn-outline btn-sm flex-1">
                                                    <i class="fas fa-edit"></i>
                                                    {{ __('submerchant.edit') }}
                                                </button>
                                            </div>
                                            <button @click="setActive('midtrans')" :disabled="!isProviderValid('midtrans') || isActiveProvider('midtrans') || settingActive" class="btn btn-sm w-full" :class="isActiveProvider('midtrans') ? 'btn-outline' : 'btn-primary'">
                                                <i class="fas" :class="settingActive ? 'fa-spinner animate-spin' : (isActiveProvider('midtrans') ? 'fa-check' : 'fa-power-off')"></i>
                                                <span x-text="isActiveProvider('midtrans') ? translations.currentlyActive : translations.setAsActive"></span>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Duitku Provider -->
                            <div class="card opacity-60" :class="getProviderCardClass('duitku')">
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
                                        <span class="badge bg-orange-100 text-orange-700">
                                            <i class="fas fa-clock mr-1"></i>{{ __('submerchant.coming_soon') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="card-content space-y-4">
                                    <div class="text-center py-4">
                                        <p class="text-sm text-[hsl(var(--muted-foreground))] mb-4">{{ __('submerchant.integration_coming_soon') }}</p>
                                        <button disabled class="btn btn-outline btn-sm opacity-50 cursor-not-allowed">
                                            <i class="fas fa-lock mr-2"></i>{{ __('submerchant.coming_soon') }}
                                        </button>
                                    </div>
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
                <h3 class="text-lg font-semibold">{{ __('submerchant.configure') }} <span x-text="getProviderDisplayName(selectedProvider)"></span></h3>
                <button @click="closeConfigModal()" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveProvider()" class="p-4 overflow-y-auto space-y-4">
                <!-- Xendit Fields -->
                <template x-if="selectedProvider === 'xendit'">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">{{ __('submerchant.api_key') }} <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.api_key" required class="input w-full pr-10" placeholder="{{ __('submerchant.enter_api_key') }}">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">{{ __('submerchant.callback_token') }} <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.callback_token" required class="input w-full pr-10" placeholder="{{ __('submerchant.enter_callback_token') }}">
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
                            <label class="text-sm font-medium mb-1.5 block">{{ __('submerchant.server_key') }} <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" x-model="configForm.server_key" required class="input w-full pr-10" placeholder="{{ __('submerchant.enter_server_key') }}">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">{{ __('submerchant.client_key') }} <span class="text-red-500">*</span></label>
                            <input type="text" x-model="configForm.client_key" required class="input w-full" placeholder="{{ __('submerchant.enter_client_key') }}">
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
                    <button type="button" @click="closeConfigModal()" class="btn btn-outline flex-1">{{ __('submerchant.cancel') }}</button>
                    <button type="submit" :disabled="saving" class="btn btn-primary flex-1">
                        <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                        <span x-text="saving ? translations.saving : translations.saveValidate"></span>
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

        // Translations from server
        translations: {
            currentlyActive: @json(__('submerchant.currently_active')),
            setAsActive: @json(__('submerchant.set_as_active')),
            saving: @json(__('submerchant.saving')),
            saveValidate: @json(__('submerchant.save_validate')),
            providerSaveSuccess: @json(__('submerchant.provider_save_success')),
            providerSaveFailed: @json(__('submerchant.provider_save_failed')),
            providerNotFound: @json(__('submerchant.provider_not_found')),
            setActiveFailed: @json(__('submerchant.set_active_failed')),
            validationError: @json(__('submerchant.validation_error')),
            validationFailed: @json(__('submerchant.validation_failed')),
            errorOccurred: @json(__('submerchant.error_occurred')),
            tryAgain: @json(__('submerchant.try_again')),
            statusValid: @json(__('submerchant.status_valid')),
            statusInvalid: @json(__('submerchant.status_invalid')),
            statusPending: @json(__('submerchant.status_pending_validation')),
            never: @json(__('submerchant.never')),
        },

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
            if (status === 'valid') return this.translations.statusValid;
            if (status === 'invalid') return this.translations.statusInvalid;
            return this.translations.statusPending;
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
                    this.successMessage = this.translations.providerSaveSuccess;
                    await this.fetchProviders();
                    setTimeout(() => {
                        this.closeConfigModal();
                    }, 1500);
                } else {
                    this.errorMessage = data.error?.message || this.translations.providerSaveFailed;
                }
            } catch (e) {
                console.error('Error saving provider:', e);
                this.errorMessage = this.translations.errorOccurred + '. ' + this.translations.tryAgain;
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
                    alert(this.translations.providerNotFound);
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
                    alert(data.error?.message || this.translations.validationFailed);
                }
            } catch (e) {
                console.error('Error validating provider:', e);
                alert(this.translations.validationError);
            } finally {
                this.validating = null;
            }
        },

        async setActive(providerName) {
            const confirmMsg = @json(__('submerchant.set_active_confirm'));
            if (!confirm(confirmMsg.replace(':provider', this.getProviderDisplayName(providerName)))) {
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
                    alert(data.error?.message || this.translations.setActiveFailed);
                }
            } catch (e) {
                console.error('Error setting active provider:', e);
                alert(this.translations.errorOccurred + '. ' + this.translations.tryAgain);
            } finally {
                this.settingActive = false;
            }
        },

        formatDate(dateString) {
            if (!dateString) return this.translations.never;
            const date = new Date(dateString);
            const locale = document.documentElement.lang || 'en';
            return date.toLocaleDateString(locale === 'id' ? 'id-ID' : 'en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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
