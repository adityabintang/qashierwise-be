@extends('layouts.app')

@section('title', 'Sub-Merchant Settings - QashierWise')

@section('content')
<div x-data="subMerchantSettings()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'sub-merchant'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Sub-Merchant Settings', 'description' => 'Manage your bank account and account settings'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-2xl mx-auto space-y-6">
                <!-- Loading State -->
                <template x-if="loading">
                    <div class="card p-6">
                        <div class="skeleton h-6 w-48 mb-4"></div>
                        <div class="space-y-4">
                            <div class="skeleton h-10 w-full"></div>
                            <div class="skeleton h-10 w-full"></div>
                            <div class="skeleton h-10 w-full"></div>
                        </div>
                    </div>
                </template>

                <!-- Not Registered -->
                <template x-if="!loading && !isSubMerchant">
                    <div class="card p-8 text-center">
                        <div class="h-16 w-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-amber-500"></i>
                        </div>
                        <h2 class="text-xl font-bold mb-2">Not Registered</h2>
                        <p class="text-[hsl(var(--muted-foreground))] mb-6">You need to register as a sub-merchant first.</p>
                        <a href="/dashboard/sub-merchant/register" class="btn btn-primary">
                            <i class="fas fa-user-plus mr-2"></i>
                            Register Now
                        </a>
                    </div>
                </template>

                <!-- Settings Form -->
                <template x-if="!loading && isSubMerchant">
                    <div class="space-y-6">
                        <!-- Bank Account Card -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Bank Account Information</h2>
                                <p class="card-description">Update your bank account details for withdrawals</p>
                            </div>
                            <form @submit.prevent="updateBankAccount()" class="card-content space-y-6">
                                <!-- Bank Name -->
                                <div>
                                    <label class="text-sm font-medium mb-1.5 block">Bank Name <span class="text-red-500">*</span></label>
                                    <select x-model="form.bank_name" required class="input w-full min-h-[44px]">
                                        <option value="">Select Bank</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BNI">BNI</option>
                                        <option value="BRI">BRI</option>
                                        <option value="Mandiri">Mandiri</option>
                                        <option value="CIMB Niaga">CIMB Niaga</option>
                                        <option value="Danamon">Danamon</option>
                                        <option value="Permata">Permata</option>
                                        <option value="BTN">BTN</option>
                                        <option value="OCBC NISP">OCBC NISP</option>
                                        <option value="Maybank">Maybank</option>
                                        <option value="Panin">Panin</option>
                                        <option value="BTPN">BTPN</option>
                                        <option value="Jago">Bank Jago</option>
                                        <option value="Jenius">Jenius (BTPN)</option>
                                        <option value="SeaBank">SeaBank</option>
                                        <option value="Bank Neo Commerce">Bank Neo Commerce</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <template x-if="errors.bank_name">
                                        <p class="text-sm text-red-500 mt-1" x-text="errors.bank_name[0]"></p>
                                    </template>
                                </div>

                                <!-- Account Number -->
                                <div>
                                    <label class="text-sm font-medium mb-1.5 block">Account Number <span class="text-red-500">*</span></label>
                                    <input 
                                        type="text" 
                                        x-model="form.account_number" 
                                        @input="form.account_number = form.account_number.replace(/[^0-9]/g, '')"
                                        required 
                                        class="input w-full min-h-[44px]" 
                                        placeholder="Enter your bank account number"
                                        maxlength="50"
                                    >
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Only numbers allowed</p>
                                    <template x-if="errors.account_number">
                                        <p class="text-sm text-red-500 mt-1" x-text="errors.account_number[0]"></p>
                                    </template>
                                </div>

                                <!-- Account Holder Name -->
                                <div>
                                    <label class="text-sm font-medium mb-1.5 block">Account Holder Name <span class="text-red-500">*</span></label>
                                    <input 
                                        type="text" 
                                        x-model="form.account_holder_name" 
                                        required 
                                        class="input w-full min-h-[44px]" 
                                        placeholder="Enter the name on your bank account"
                                        maxlength="100"
                                    >
                                    <template x-if="errors.account_holder_name">
                                        <p class="text-sm text-red-500 mt-1" x-text="errors.account_holder_name[0]"></p>
                                    </template>
                                </div>

                                <!-- Success Message -->
                                <template x-if="successMessage">
                                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                                        <div class="flex items-center gap-2 text-emerald-700">
                                            <i class="fas fa-check-circle"></i>
                                            <span class="text-sm font-medium" x-text="successMessage"></span>
                                        </div>
                                    </div>
                                </template>

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
                                    <a href="/dashboard/sub-merchant" class="btn btn-outline btn-md">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back
                                    </a>
                                    <button 
                                        type="submit" 
                                        :disabled="saving" 
                                        class="btn btn-primary btn-md flex-1"
                                    >
                                        <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                                        <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Account Status Card -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Account Status</h2>
                            </div>
                            <div class="card-content space-y-4">
                                <div class="flex items-center justify-between py-2 border-b border-[hsl(var(--border))]">
                                    <span class="text-sm text-[hsl(var(--muted-foreground))]">Status</span>
                                    <span class="badge" :class="subMerchant.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'" x-text="subMerchant.is_active ? 'Active' : 'Inactive'"></span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-[hsl(var(--border))]">
                                    <span class="text-sm text-[hsl(var(--muted-foreground))]">Verification</span>
                                    <span class="badge" :class="subMerchant.is_verified ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'" x-text="subMerchant.is_verified ? 'Verified' : 'Pending Verification'"></span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-[hsl(var(--border))]">
                                    <span class="text-sm text-[hsl(var(--muted-foreground))]">Can Accept Payments</span>
                                    <span class="badge" :class="subMerchant.can_accept_payments ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'" x-text="subMerchant.can_accept_payments ? 'Yes' : 'No'"></span>
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <span class="text-sm text-[hsl(var(--muted-foreground))]">Registered On</span>
                                    <span class="text-sm font-medium" x-text="formatDate(subMerchant.created_at)"></span>
                                </div>

                                <!-- Toggle Status -->
                                <div class="pt-4 border-t border-[hsl(var(--border))]">
                                    <template x-if="subMerchant.is_active">
                                        <button @click="deactivateAccount()" :disabled="toggling" class="btn btn-outline btn-sm text-amber-600 hover:bg-amber-50 w-full">
                                            <i class="fas" :class="toggling ? 'fa-spinner animate-spin' : 'fa-pause'"></i>
                                            <span x-text="toggling ? 'Processing...' : 'Deactivate Account'"></span>
                                        </button>
                                    </template>
                                    <template x-if="!subMerchant.is_active">
                                        <button @click="activateAccount()" :disabled="toggling" class="btn btn-outline btn-sm text-emerald-600 hover:bg-emerald-50 w-full">
                                            <i class="fas" :class="toggling ? 'fa-spinner animate-spin' : 'fa-play'"></i>
                                            <span x-text="toggling ? 'Processing...' : 'Activate Account'"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</div>

<script>
function subMerchantSettings() {
    return {
        API_BASE_URL: window.location.origin + '/api/sub-merchant',
        loading: true,
        saving: false,
        toggling: false,
        isSubMerchant: false,
        subMerchant: {},
        form: { bank_name: '', account_number: '', account_holder_name: '' },
        errors: {},
        errorMessage: '',
        successMessage: '',
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await this.fetchProfile();
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

        async fetchProfile() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/profile`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.isSubMerchant = true;
                    this.subMerchant = data.data.sub_merchant;
                    this.form.bank_name = this.subMerchant.bank_name;
                    // Account number is masked, so we need to clear it for editing
                    this.form.account_number = '';
                    this.form.account_holder_name = this.subMerchant.account_holder_name;
                } else {
                    this.isSubMerchant = false;
                }
            } catch (e) {
                console.error('Error fetching profile:', e);
                this.isSubMerchant = false;
            } finally {
                this.loading = false;
            }
        },

        async updateBankAccount() {
            this.saving = true;
            this.errors = {};
            this.errorMessage = '';
            this.successMessage = '';

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/bank-account`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();

                if (data.success) {
                    this.successMessage = 'Bank account updated successfully!';
                    this.subMerchant = { ...this.subMerchant, ...data.data.sub_merchant };
                    // Clear account number field after successful update
                    this.form.account_number = '';
                } else {
                    if (data.errors) {
                        this.errors = data.errors;
                    }
                    this.errorMessage = data.error?.message || 'Update failed. Please try again.';
                }
            } catch (e) {
                console.error('Error updating bank account:', e);
                this.errorMessage = 'An error occurred. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async deactivateAccount() {
            if (!confirm('Are you sure you want to deactivate your sub-merchant account? You will not be able to accept payments until you reactivate.')) {
                return;
            }

            this.toggling = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/deactivate`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.subMerchant.is_active = false;
                    this.subMerchant.can_accept_payments = false;
                    this.successMessage = 'Account deactivated successfully.';
                } else {
                    this.errorMessage = data.error?.message || 'Failed to deactivate account.';
                }
            } catch (e) {
                console.error('Error deactivating:', e);
                this.errorMessage = 'An error occurred. Please try again.';
            } finally {
                this.toggling = false;
            }
        },

        async activateAccount() {
            this.toggling = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/activate`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.subMerchant.is_active = true;
                    this.subMerchant.can_accept_payments = true;
                    this.successMessage = 'Account activated successfully.';
                } else {
                    this.errorMessage = data.error?.message || 'Failed to activate account.';
                }
            } catch (e) {
                console.error('Error activating:', e);
                this.errorMessage = 'An error occurred. Please try again.';
            } finally {
                this.toggling = false;
            }
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; }
    }
}
</script>
@endsection
