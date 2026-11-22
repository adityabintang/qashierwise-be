@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', __('dashboard.whatsapp_account_title'))

@section('content')
<div x-data="whatsAppAccountApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'whatsapp-account'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => __('whatsapp.account_title'), 'description' => __('whatsapp.account_subtitle')])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-4xl mx-auto">
                <!-- Account Status Card -->
                <div class="card mb-6">
                    <div class="card-header !flex-row items-center justify-between border-b border-[hsl(var(--border))]">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <i class="fab fa-whatsapp text-emerald-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="card-title">WhatsApp Business Account</h2>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">Connect your WhatsApp Business account to send and receive messages</p>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div x-show="loading" class="p-6">
                        <div class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
                            <span class="ml-3 text-[hsl(var(--muted-foreground))]">Loading account status...</span>
                        </div>
                    </div>

                    <!-- Not Connected State (no account or account is inactive) -->
                    <div x-show="!loading && (!account || !account.is_active)" class="p-6">
                        <div class="text-center py-8">
                            <div class="h-16 w-16 rounded-full bg-[hsl(var(--muted))] flex items-center justify-center mx-auto mb-4">
                                <i class="fab fa-whatsapp text-3xl text-[hsl(var(--muted-foreground))]"></i>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">No WhatsApp Account Connected</h3>
                            <p class="text-[hsl(var(--muted-foreground))] mb-6 max-w-md mx-auto">
                                Connect your WhatsApp Business account to start sending and receiving messages through QashierWise.
                            </p>

                            <!-- Connect Button -->
                            <button
                                @click="launchWhatsAppSignup()"
                                :disabled="connecting || !sdkLoaded"
                                class="btn btn-primary btn-lg inline-flex items-center gap-2"
                                style="background-color: #1877f2; border-color: #1877f2;"
                            >
                                <i class="fab fa-facebook" x-show="!connecting"></i>
                                <i class="fas fa-spinner animate-spin" x-show="connecting"></i>
                                <span x-text="connecting ? 'Connecting...' : (sdkLoaded ? 'Connect with Facebook' : 'Loading...')"></span>
                            </button>

                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-4">
                                You'll be redirected to Facebook to authorize your WhatsApp Business account
                            </p>
                        </div>
                    </div>

                    <!-- Connected State (account exists AND is active) -->
                    <div x-show="!loading && account && account.is_active" class="p-6">
                        <!-- Success Banner -->
                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-emerald-800">WhatsApp Account Connected</p>
                                <p class="text-sm text-emerald-600">Your account is active and ready to send messages</p>
                            </div>
                        </div>

                        <!-- Account Details Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <!-- Phone Number -->
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-phone text-emerald-500 text-sm"></i>
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Phone Number</span>
                                </div>
                                <p class="font-medium" x-text="account?.phone_number || '-'">-</p>
                            </div>

                            <!-- Business Name -->
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-building text-blue-500 text-sm"></i>
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Business Name</span>
                                </div>
                                <p class="font-medium" x-text="account?.display_name || account?.verified_name || '-'">-</p>
                            </div>

                            <!-- Verified Name -->
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-shield-alt text-purple-500 text-sm"></i>
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Verified Name</span>
                                </div>
                                <p class="font-medium" :class="account?.verified_name ? 'text-emerald-600' : 'text-[hsl(var(--muted-foreground))]'" x-text="account?.verified_name || 'Not Verified'">-</p>
                            </div>

                            <!-- Quality Rating -->
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-star text-yellow-500 text-sm"></i>
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Quality Rating</span>
                                </div>
                                <p class="font-medium">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700': account?.quality_rating === 'GREEN',
                                            'bg-yellow-100 text-yellow-700': account?.quality_rating === 'YELLOW',
                                            'bg-red-100 text-red-700': account?.quality_rating === 'RED',
                                            'bg-gray-100 text-gray-700': !account?.quality_rating
                                        }"
                                        x-text="account?.quality_rating || 'Unknown'"
                                    ></span>
                                </p>
                            </div>

                            <!-- Connection Status -->
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-plug text-orange-500 text-sm"></i>
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Status</span>
                                </div>
                                <p class="font-medium">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="account?.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="account?.is_active ? 'bg-emerald-500' : 'bg-red-500'"></span>
                                        <span x-text="account?.is_active ? 'Active' : 'Inactive'"></span>
                                    </span>
                                </p>
                            </div>

                            <!-- Coexistence Mode -->
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-sync-alt text-indigo-500 text-sm"></i>
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Coexistence Mode</span>
                                </div>
                                <p class="font-medium">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="account?.coexistence_enabled ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'"
                                        x-text="account?.coexistence_enabled ? 'Enabled' : 'Disabled'"
                                    ></span>
                                </p>
                            </div>
                        </div>

                        <!-- Disconnect Button -->
                        <div class="border-t border-[hsl(var(--border))] pt-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-[hsl(var(--destructive))]">Disconnect Account</p>
                                    <p class="text-sm text-[hsl(var(--muted-foreground))]">Remove this WhatsApp account from QashierWise</p>
                                </div>
                                <button
                                    @click="confirmDisconnect()"
                                    :disabled="disconnecting"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border-2 border-red-200 text-red-600 bg-red-50 hover:bg-red-500 hover:text-white hover:border-red-500 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fas fa-unlink" x-show="!disconnecting"></i>
                                    <i class="fas fa-spinner animate-spin" x-show="disconnecting"></i>
                                    <span x-text="disconnecting ? 'Disconnecting...' : 'Disconnect'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="card bg-gradient-to-br from-[hsl(var(--primary))] to-[hsl(var(--primary)/0.8)] border-0">
                    <div class="p-6 text-white">
                        <div class="flex items-start gap-4">
                            <div class="h-10 w-10 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-2 text-white">About WhatsApp Business Integration</h3>
                                <ul class="text-sm space-y-1.5 text-white/90">
                                    <li class="flex items-start gap-2">
                                        <i class="fas fa-check text-xs mt-1"></i>
                                        <span>Send and receive WhatsApp messages directly from QashierWise</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i class="fas fa-check text-xs mt-1"></i>
                                        <span>Use your own WhatsApp Business number</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i class="fas fa-check text-xs mt-1"></i>
                                        <span>Coexistence mode lets you keep using personal WhatsApp</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i class="fas fa-check text-xs mt-1"></i>
                                        <span>Your credentials are securely encrypted</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Notifications -->
    <div
        x-show="toast.show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
    >
        <div
            class="rounded-lg shadow-lg p-4 flex items-center gap-3 max-w-md"
            :class="{
                'bg-emerald-50 border border-emerald-200 text-emerald-800': toast.type === 'success',
                'bg-red-50 border border-red-200 text-red-800': toast.type === 'error',
                'bg-blue-50 border border-blue-200 text-blue-800': toast.type === 'info'
            }"
        >
            <i class="fas" :class="{
                'fa-check-circle text-emerald-500': toast.type === 'success',
                'fa-exclamation-circle text-red-500': toast.type === 'error',
                'fa-info-circle text-blue-500': toast.type === 'info'
            }"></i>
            <span x-text="toast.message"></span>
            <button @click="toast.show = false" class="ml-2 opacity-50 hover:opacity-100">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Disconnect Confirmation Modal -->
    <div
        x-show="showDisconnectModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="showDisconnectModal = false"
    >
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h3 class="text-lg font-semibold">Disconnect WhatsApp Account?</h3>
            </div>
            <p class="text-[hsl(var(--muted-foreground))] mb-6">
                Are you sure you want to disconnect your WhatsApp Business account? You will no longer be able to send or receive messages until you reconnect.
            </p>
            <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
                <button
                    @click="showDisconnectModal = false"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200"
                >
                    Cancel
                </button>
                <button
                    @click="disconnect()"
                    :disabled="disconnecting"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg bg-red-500 text-white hover:bg-red-600 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-spinner animate-spin" x-show="disconnecting"></i>
                    <span x-text="disconnecting ? 'Disconnecting...' : 'Yes, Disconnect'"></span>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Facebook SDK -->
<script>
    // Facebook SDK initialization
    window.fbAsyncInit = function() {
        FB.init({
            appId: window.whatsAppConfig?.app_id || '',
            autoLogAppEvents: true,
            xfbml: true,
            version: 'v24.0'
        });

        // Dispatch event when SDK is ready
        window.dispatchEvent(new CustomEvent('fb-sdk-ready'));
    };

    // Load the JavaScript SDK after page load to prevent browser tab loading indicator
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

    // Load SDK after page is fully loaded with a small delay to ensure browser tab stops loading
    function initFacebookSDK() {
        // Add a small delay to ensure the browser tab loading indicator stops first
        setTimeout(loadFacebookSDK, 100);
    }

    if (document.readyState === 'complete') {
        initFacebookSDK();
    } else {
        window.addEventListener('load', initFacebookSDK);
    }
</script>

<script>
function whatsAppAccountApp() {
    return {
        // App state
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        // WhatsApp account state
        loading: true,
        connecting: false,
        disconnecting: false,
        sdkLoaded: false,
        account: null,
        config: null,

        // UI state
        toast: { show: false, message: '', type: 'info' },
        showDisconnectModal: false,

        API_BASE_URL: window.location.origin + '/api',

        async init() {
            // Initialize sidebar state
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

            // Handle resize
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
                try { this.user = JSON.parse(storedUser); }
                catch (e) { this.user = { name: 'User', email: 'user@example.com' }; }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }

            // Load notifications
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try { this.notifications = JSON.parse(savedNotifs); }
                catch (e) { this.notifications = []; }
            }

            // Setup session info message listener for Embedded Signup
            this.setupMessageListener();

            // Load config and account status in parallel for faster loading
            await Promise.all([
                this.loadConfig(),
                this.loadAccountStatus()
            ]);

            // Listen for Facebook SDK ready event
            window.addEventListener('fb-sdk-ready', () => {
                this.sdkLoaded = true;
            });

            // Check if SDK is already loaded
            if (typeof FB !== 'undefined') {
                this.sdkLoaded = true;
            }

            // Timeout fallback: if SDK doesn't load within 5 seconds, enable button anyway
            // User will see error message if they try to connect without SDK
            setTimeout(() => {
                if (!this.sdkLoaded) {
                    console.warn('Facebook SDK loading timeout - enabling button with fallback');
                    this.sdkLoaded = true;
                }
            }, 5000);
        },

        /**
         * Setup message listener for WA_EMBEDDED_SIGNUP events from Facebook
         * Requirements: 1.3, 7.4
         */
        setupMessageListener() {
            window.addEventListener('message', (event) => {
                // Only accept messages from Facebook
                if (event.origin !== "https://www.facebook.com" &&
                    event.origin !== "https://web.facebook.com") {
                    return;
                }

                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'WA_EMBEDDED_SIGNUP') {
                        // Handle different event types
                        if (data.event === 'FINISH') {
                            // User completed signup
                            console.log('Embedded Signup completed:', data.data);
                            this.handleSignupComplete(data.data);
                        } else if (data.event === 'CANCEL') {
                            // User cancelled signup
                            console.log('Embedded Signup cancelled');
                            this.handleSignupCancel();
                        } else if (data.event === 'ERROR') {
                            // Error occurred
                            console.error('Embedded Signup error:', data.data);
                            this.handleSignupError(data.data);
                        }
                    }
                } catch (e) {
                    // Not a JSON message or not from embedded signup
                    console.debug('Non-ES message received');
                }
            });
        },

        /**
         * Load Embedded Signup configuration from backend
         */
        async loadConfig() {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

            try {
                const token = localStorage.getItem('token');
                if (!token) {
                    console.warn('No auth token found for config');
                    return;
                }

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/embedded-signup/config`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (!res.ok) {
                    console.error('Config API error:', res.status);
                    return;
                }

                const data = await res.json();

                if (data.success) {
                    this.config = data.data;
                    // Store config globally for Facebook SDK
                    window.whatsAppConfig = data.data;

                    // Re-initialize FB SDK if already loaded
                    if (typeof FB !== 'undefined' && this.config.app_id) {
                        FB.init({
                            appId: this.config.app_id,
                            autoLogAppEvents: true,
                            xfbml: true,
                            version: 'v24.0'
                        });
                        this.sdkLoaded = true;
                    }
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    console.error('Request timeout loading config');
                } else {
                    console.error('Error loading config:', e);
                }
            } finally {
                clearTimeout(timeoutId);
            }
        },

        /**
         * Load current WhatsApp account status
         */
        async loadAccountStatus() {
            this.loading = true;
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

            try {
                const token = localStorage.getItem('token');
                if (!token) {
                    console.warn('No auth token found');
                    this.account = null;
                    return;
                }

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/account`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (!res.ok) {
                    console.error('API error:', res.status);
                    this.account = null;
                    return;
                }

                const data = await res.json();

                if (data.success && data.data) {
                    this.account = data.data;
                } else {
                    this.account = null;
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    console.error('Request timeout loading account status');
                    this.showToast('Loading timeout. Please refresh the page.', 'error');
                } else {
                    console.error('Error loading account status:', e);
                }
                this.account = null;
            } finally {
                clearTimeout(timeoutId);
                this.loading = false;
            }
        },

        /**
         * Launch WhatsApp Embedded Signup flow
         * Requirements: 1.1, 1.2, 6.3
         */
        launchWhatsAppSignup() {
            if (!this.config?.config_id || !this.config?.app_id) {
                this.showToast('WhatsApp configuration not available. Please refresh the page and try again.', 'error');
                return;
            }

            this.connecting = true;

            // Try FB.login first if SDK is available
            if (typeof FB !== 'undefined') {
                // Store reference to this for use in callback
                const self = this;

                FB.login((response) => {
                    if (response.authResponse) {
                        const code = response.authResponse.code;
                        // Get session info from extras if available (contains waba_id, phone_number_id, business_id)
                        const sessionInfo = response.authResponse.extras?.session_info || null;
                        console.log('FB.login response:', response);
                        console.log('Session info from embedded signup:', sessionInfo);
                        self.sendCodeToBackend(code, sessionInfo);
                    } else {
                        console.log('User cancelled login or did not fully authorize.');
                        self.connecting = false;
                        self.handleSignupCancel();
                    }
                }, {
                    config_id: this.config.config_id,
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: {
                        setup: {},
                        featureType: 'whatsapp_business_app_onboarding',
                        sessionInfoVersion: '3'
                    }
                });
            } else {
                // Fallback: redirect to Facebook onboarding page directly
                this.launchWhatsAppSignupRedirect();
            }
        },

        /**
         * Fallback method: Launch WhatsApp Embedded Signup via popup window
         * Used when FB SDK is not available
         */
        launchWhatsAppSignupRedirect() {
            const appId = this.config.app_id;
            const configId = this.config.config_id;
            const extras = encodeURIComponent(JSON.stringify({
                featureType: 'whatsapp_business_app_onboarding',
                sessionInfoVersion: '3',
                version: 'v3'
            }));

            const url = `https://business.facebook.com/messaging/whatsapp/onboard/?app_id=${appId}&config_id=${configId}&extras=${extras}`;

            // Open in popup window
            const width = 600;
            const height = 700;
            const left = (window.innerWidth - width) / 2;
            const top = (window.innerHeight - height) / 2;

            const popup = window.open(
                url,
                'whatsapp_signup',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes`
            );

            if (!popup) {
                this.showToast('Popup blocked. Please allow popups for this site.', 'error');
                this.connecting = false;
                return;
            }

            // Note: The code will be received via postMessage from setupMessageListener
            // The popup will send WA_EMBEDDED_SIGNUP events
        },

        /**
         * Send authorization code and session info to backend for token exchange
         * Requirements: 1.4
         * @param {string} code - Authorization code from Facebook
         * @param {object} sessionInfo - Session info object containing waba_id, phone_number_id, business_id, etc.
         */
        async sendCodeToBackend(code, sessionInfo = null) {
            try {
                const token = localStorage.getItem('token');

                // Build request body with code and optional session info
                const body = { code };
                if (sessionInfo) {
                    body.waba_id = sessionInfo.waba_id;
                    body.phone_number_id = sessionInfo.phone_number_id;
                    body.business_id = sessionInfo.business_id;
                }

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/embedded-signup/callback`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(body)
                });

                const data = await res.json();

                if (data.success) {
                    this.showToast(`WhatsApp connected: ${data.data.display_name || data.data.phone_number}`, 'success');
                    // Reload account status
                    await this.loadAccountStatus();
                } else {
                    this.showToast(data.message || 'Failed to connect WhatsApp account', 'error');
                }
            } catch (e) {
                console.error('Error sending code to backend:', e);
                this.showToast('Network error. Please try again.', 'error');
            } finally {
                this.connecting = false;
            }
        },

        /**
         * Handle successful signup completion from session info
         */
        handleSignupComplete(data) {
            console.log('Signup completed with session data:', data);
            // The actual account creation is handled by sendCodeToBackend
        },

        /**
         * Handle signup cancellation
         * Requirements: 7.4
         */
        handleSignupCancel() {
            this.connecting = false;
            this.showToast('WhatsApp connection cancelled.', 'info');
        },

        /**
         * Handle signup error
         * Requirements: 7.3
         */
        handleSignupError(errorData) {
            this.connecting = false;
            this.showToast(`Connection failed: ${errorData?.error_message || 'Unknown error'}`, 'error');
        },

        /**
         * Show disconnect confirmation modal
         */
        confirmDisconnect() {
            this.showDisconnectModal = true;
        },

        /**
         * Disconnect WhatsApp account
         * Requirements: 4.2
         */
        async disconnect() {
            this.disconnecting = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/account`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                const data = await res.json();

                if (data.success) {
                    this.account = null;
                    this.showToast('WhatsApp account disconnected successfully', 'success');
                } else {
                    this.showToast(data.message || 'Failed to disconnect account', 'error');
                }
            } catch (e) {
                console.error('Error disconnecting:', e);
                this.showToast('Network error. Please try again.', 'error');
            } finally {
                this.disconnecting = false;
                this.showDisconnectModal = false;
            }
        },

        /**
         * Show toast notification
         */
        showToast(message, type = 'info') {
            this.toast = { show: true, message, type };
            setTimeout(() => {
                this.toast.show = false;
            }, 5000);
        },

        // Notification helpers
        addNotification(n) {
            n.id = Date.now() + Math.random();
            this.notifications.unshift(n);
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

        formatNotificationTime(t) {
            let d = new Date(t), diff = Math.floor((new Date() - d) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return d.toLocaleDateString();
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
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                window.location.href = '/login';
            }
        }
    }
}
</script>
@endsection
