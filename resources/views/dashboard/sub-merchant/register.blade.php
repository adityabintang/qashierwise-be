@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', __('submerchant.register_title'))

@section('content')
<div x-data="subMerchantRegister()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('submerchant.register_as_submerchant'), 'description' => __('submerchant.start_accepting_qris')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-2xl mx-auto">
                <!-- Already Registered -->
                <template x-if="alreadyRegistered">
                    <div class="card p-8 text-center">
                        <div class="h-16 w-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check text-3xl text-emerald-500"></i>
                        </div>
                        <h2 class="text-xl font-bold mb-2">Already Registered</h2>
                        <p class="text-[hsl(var(--muted-foreground))] mb-6">You are already registered as a sub-merchant.</p>
                        <a href="/dashboard/sub-merchant" class="btn btn-primary">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Go to Dashboard
                        </a>
                    </div>
                </template>

                <!-- Registration Form -->
                <template x-if="!alreadyRegistered">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Business Information</h2>
                            <p class="card-description">Enter your business details to start accepting payments</p>
                        </div>
                        <form @submit.prevent="register()" class="card-content space-y-6">
                            <!-- Business Name -->
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Business Name <span class="text-[hsl(var(--muted-foreground))]">(optional)</span></label>
                                <input
                                    type="text"
                                    x-model="form.business_name"
                                    class="input w-full min-h-[44px]"
                                    placeholder="Enter your business name (leave empty to use your account name)"
                                    maxlength="255"
                                >
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">This will be displayed on payment receipts</p>
                                <template x-if="errors.business_name">
                                    <p class="text-sm text-red-500 mt-1" x-text="errors.business_name[0]"></p>
                                </template>
                            </div>

                            <!-- Terms -->
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <input type="checkbox" x-model="acceptTerms" id="terms" class="mt-1 rounded border-[hsl(var(--input))]">
                                    <label for="terms" class="text-sm text-[hsl(var(--muted-foreground))]">
                                        I agree to the <a href="/terms-of-service" target="_blank" class="text-[hsl(var(--primary))] hover:underline">Terms of Service</a> and understand that a <strong>2.5% platform fee</strong> will be deducted from each successful transaction.
                                    </label>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <template x-if="errorMessage">
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div class="flex items-center gap-2 text-red-700">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span class="text-sm font-medium" x-text="errorMessage"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Submit Button -->
                            <div class="flex gap-3 pt-4">
                                <a href="/dashboard/sub-merchant" class="btn btn-outline btn-md flex-1">
                                    Cancel
                                </a>
                                <button
                                    type="submit"
                                    :disabled="saving || !acceptTerms"
                                    class="btn btn-primary btn-md flex-1"
                                >
                                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-user-plus'"></i>
                                    <span x-text="saving ? 'Registering...' : 'Register'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </template>

                <!-- Info Card -->
                <div class="card mt-6">
                    <div class="card-content">
                        <h3 class="font-semibold mb-3">
                            <i class="fas fa-info-circle text-[hsl(var(--primary))] mr-2"></i>
                            Getting Started
                        </h3>
                        <ul class="space-y-2 text-sm text-[hsl(var(--muted-foreground))]">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1"></i>
                                <span>Register as a sub-merchant to start accepting QRIS payments</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1"></i>
                                <span>Configure your payment provider credentials in Provider Settings</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1"></i>
                                <span>Generate QRIS codes for your customers to pay</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1"></i>
                                <span>A 2.5% platform fee is deducted from each successful transaction</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function subMerchantRegister() {
    return {
        API_BASE_URL: window.location.origin + '/api/sub-merchant',
        loading: true,
        saving: false,
        alreadyRegistered: false,
        acceptTerms: false,
        form: { business_name: '' },
        errors: {},
        errorMessage: '',
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await this.checkStatus();
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

        async checkStatus() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/status`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.data.is_sub_merchant) {
                    this.alreadyRegistered = true;
                }
            } catch (e) {
                console.error('Error checking status:', e);
            } finally {
                this.loading = false;
            }
        },

        async register() {
            this.saving = true;
            this.errors = {};
            this.errorMessage = '';

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/register`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = '/dashboard/sub-merchant';
                } else {
                    if (data.errors) {
                        this.errors = data.errors;
                    }
                    this.errorMessage = data.error?.message || 'Registration failed. Please try again.';
                }
            } catch (e) {
                console.error('Error registering:', e);
                this.errorMessage = 'An error occurred. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; }
    }
}
</script>
@endsection
