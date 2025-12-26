@extends('layouts.app')

@section('title', 'Migrate to BYOK')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <!-- Migration Wizard Card -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-8 text-white">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-center mb-2">Migrate to BYOK System</h1>
                <p class="text-center text-blue-100">Bring Your Own Key - Enhanced Security & Control</p>
            </div>

            <!-- Migration Steps -->
            <div class="px-6 py-8" x-data="migrationWizard()">
                <!-- Step Indicator -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full transition-colors"
                                     :class="step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'">
                                    <span class="font-semibold">1</span>
                                </div>
                                <div class="flex-1 h-1 mx-2 transition-colors"
                                     :class="step >= 2 ? 'bg-blue-600' : 'bg-gray-200'"></div>
                            </div>
                            <p class="text-xs mt-2 text-center" :class="step >= 1 ? 'text-blue-600 font-semibold' : 'text-gray-500'">
                                Introduction
                            </p>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full transition-colors"
                                     :class="step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'">
                                    <span class="font-semibold">2</span>
                                </div>
                                <div class="flex-1 h-1 mx-2 transition-colors"
                                     :class="step >= 3 ? 'bg-blue-600' : 'bg-gray-200'"></div>
                            </div>
                            <p class="text-xs mt-2 text-center" :class="step >= 2 ? 'text-blue-600 font-semibold' : 'text-gray-500'">
                                Credentials
                            </p>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full transition-colors"
                                     :class="step >= 3 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'">
                                    <span class="font-semibold">3</span>
                                </div>
                            </div>
                            <p class="text-xs mt-2 text-center" :class="step >= 3 ? 'text-blue-600 font-semibold' : 'text-gray-500'">
                                Complete
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Introduction -->
                <div x-show="step === 1" x-transition>
                    <div class="space-y-6">
                        <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Important Update</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>We're upgrading to a more secure BYOK (Bring Your Own Key) system. You'll need to provide your own Midtrans API credentials to continue using QRIS.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-3">What's Changing?</h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <svg class="w-6 h-6 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <strong>Enhanced Security:</strong> Your credentials are encrypted with AES-256 and only you can access them
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-6 h-6 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <strong>Full Control:</strong> Use your own Midtrans account and manage your credentials
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-6 h-6 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <strong>Transaction History:</strong> All your existing transactions will be preserved
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-6 h-6 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <strong>Multi-Provider Support:</strong> Future support for Doku, Xendit, and Duitku
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">What You'll Need</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Your Midtrans Server Key and Client Key. You can get these from your <a href="https://dashboard.midtrans.com/settings/config_info" target="_blank" class="underline font-semibold">Midtrans Dashboard</a>.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between pt-4">
                            <button @click="skipMigration" 
                                    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                Skip for Now
                            </button>
                            <button @click="step = 2" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Continue
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Enter Credentials -->
                <div x-show="step === 2" x-transition>
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Enter Your Midtrans Credentials</h3>
                            <p class="text-gray-600 mb-6">
                                Get your credentials from <a href="https://dashboard.midtrans.com/settings/config_info" target="_blank" class="text-blue-600 hover:underline font-semibold">Midtrans Dashboard → Settings → Access Keys</a>
                            </p>
                        </div>

                        <!-- Error Message -->
                        <div x-show="error" x-transition class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700" x-text="error"></p>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="submitMigration">
                            <div class="space-y-4">
                                <div>
                                    <label for="server_key" class="block text-sm font-medium text-gray-700 mb-2">
                                        Server Key <span class="text-red-500">*</span>
                                    </label>
                                    <input type="password" 
                                           id="server_key" 
                                           x-model="credentials.server_key"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="SB-Mid-server-xxxxxxxxxxxxxxxx"
                                           required>
                                    <p class="mt-1 text-xs text-gray-500">Starts with "SB-Mid-server-" for sandbox or "Mid-server-" for production</p>
                                </div>

                                <div>
                                    <label for="client_key" class="block text-sm font-medium text-gray-700 mb-2">
                                        Client Key <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="client_key" 
                                           x-model="credentials.client_key"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="SB-Mid-client-xxxxxxxxxxxxxxxx"
                                           required>
                                    <p class="mt-1 text-xs text-gray-500">Starts with "SB-Mid-client-" for sandbox or "Mid-client-" for production</p>
                                </div>
                            </div>

                            <div class="bg-gray-50 p-4 rounded-lg mt-6">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="text-sm text-gray-600">
                                        <p class="font-semibold mb-1">Your credentials are secure</p>
                                        <p>All credentials are encrypted with AES-256 before storage. Only you can access them.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between pt-6">
                                <button type="button" 
                                        @click="step = 1" 
                                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                                        :disabled="loading">
                                    Back
                                </button>
                                <button type="submit" 
                                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                                        :disabled="loading">
                                    <span x-show="!loading">Validate & Migrate</span>
                                    <span x-show="loading" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Validating...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Step 3: Success -->
                <div x-show="step === 3" x-transition>
                    <div class="text-center space-y-6 py-8">
                        <div class="flex justify-center">
                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Migration Complete!</h3>
                            <p class="text-gray-600">Your account has been successfully migrated to the BYOK system.</p>
                        </div>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-left">
                            <h4 class="font-semibold text-green-900 mb-2">What's Next?</h4>
                            <ul class="space-y-2 text-sm text-green-800">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Your credentials are encrypted and stored securely</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Midtrans is set as your active payment provider</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span x-text="`${transactionsUpdated} transaction(s) updated with provider information`"></span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>You can now generate QRIS codes as before</span>
                                </li>
                            </ul>
                        </div>

                        <div class="flex justify-center gap-4 pt-4">
                            <a href="{{ route('dashboard.sub-merchant.provider-settings') }}" 
                               class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                Manage Providers
                            </a>
                            <a href="{{ route('dashboard.sub-merchant.qris') }}" 
                               class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Generate QRIS
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function migrationWizard() {
    return {
        step: 1,
        loading: false,
        error: '',
        credentials: {
            server_key: '',
            client_key: ''
        },
        transactionsUpdated: 0,

        async submitMigration() {
            this.loading = true;
            this.error = '';

            try {
                const response = await fetch('/api/sub-merchant/migration/migrate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.credentials)
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.transactionsUpdated = data.transactions_updated || 0;
                    this.step = 3;
                } else {
                    this.error = data.message || 'Migration failed. Please try again.';
                }
            } catch (error) {
                console.error('Migration error:', error);
                this.error = 'An unexpected error occurred. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        async skipMigration() {
            try {
                await fetch('/api/sub-merchant/migration/skip', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Accept': 'application/json'
                    }
                });

                // Redirect to dashboard
                window.location.href = '{{ route('dashboard') }}';
            } catch (error) {
                console.error('Skip migration error:', error);
                window.location.href = '{{ route('dashboard') }}';
            }
        }
    }
}
</script>
@endsection
