@extends('layouts.app')

@section('content')
<script>
    // Initialize Alpine permissions store BEFORE Alpine starts
    document.addEventListener('alpine:init', () => {
        Alpine.store('permissions', {
            isAdmin: true,
            userPermissions: ['*'],

            async init() {
                await this.fetchPermissions();
            },

            async fetchPermissions() {
                try {
                    const token = localStorage.getItem('token');
                    const res = await fetch(window.location.origin + '/api/user/permissions', {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.isAdmin = data.data.is_admin || false;
                        this.userPermissions = data.data.permissions || [];
                    }
                } catch (e) {
                    console.error('Failed to fetch permissions:', e);
                    // Default to admin if fetch fails (for main account owners)
                    this.isAdmin = true;
                    this.userPermissions = ['*'];
                }
            },

            hasPermission(permission) {
                // If admin, allow all
                if (this.isAdmin || this.userPermissions.includes('*')) {
                    return true;
                }
                // Check if user has specific permission
                return this.userPermissions.includes(permission);
            }
        });

        // Initialize permissions on page load
        Alpine.store('permissions').init();
    });
</script>

<div x-data="{
    sidebarOpen: true,
    user: null,
    notifications: [],

    init() {
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

        // Listen for new WhatsApp messages
        window.addEventListener('new-whatsapp-message', (event) => {
            console.log('📩 Dashboard - New message notification:', event.detail);
            this.notifications.unshift({
                id: Date.now(),
                title: 'New WhatsApp Message',
                message: `New message from ${event.detail.contact?.name || 'Unknown'}`,
                time: new Date(),
                icon: 'fa-whatsapp',
                color: 'blue'
            });
        });
    },

    clearNotifications() {
        this.notifications = [];
    },

    removeNotification(id) {
        this.notifications = this.notifications.filter(n => n.id !== id);
    },

    formatNotificationTime(time) {
        if (!time) return '';
        const date = new Date(time);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;

        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours}h ago`;

        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 7) return `${diffDays}d ago`;

        return date.toLocaleDateString();
    },

    logout() {
        let apiBaseUrl = window.location.origin + '/api';
        fetch(`${apiBaseUrl}/logout`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Accept': 'application/json'
            }
        }).then(() => {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        });
    }
}" class="min-h-screen bg-gray-50">
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'"
           class="bg-gradient-to-b from-green-600 to-green-800 text-white fixed h-screen transition-all duration-300 flex flex-col shadow-xl z-50">
        <!-- Header -->
        <div class="p-6 flex items-center justify-between border-b border-green-500">
            <div x-show="sidebarOpen" class="flex items-center space-x-3">
                <i class="fab fa-whatsapp text-3xl"></i>
                <span class="text-xl font-bold">QashierWise</span>
            </div>
            <i x-show="!sidebarOpen" class="fab fa-whatsapp text-3xl mx-auto"></i>
        </div>

         <!-- Navigation -->
        <nav class="flex-1 py-6 overflow-y-auto">
            <a href="/dashboard" class="flex items-center space-x-3 px-6 py-3 hover:bg-green-500 transition">
                <i class="fas fa-home text-xl w-6"></i>
                <span x-show="sidebarOpen">Dashboard</span>
            </a>
            <a href="/dashboard/contacts" class="flex items-center space-x-3 px-6 py-3 hover:bg-green-500 transition">
                <i class="fas fa-address-book text-xl w-6"></i>
                <span x-show="sidebarOpen">Contacts</span>
            </a>
            <a href="/dashboard/messages" class="flex items-center space-x-3 px-6 py-3 hover:bg-green-500 transition">
                <i class="fas fa-comments text-xl w-6"></i>
                <span x-show="sidebarOpen">Messages</span>
            </a>
            <a href="/dashboard/templates" class="flex items-center space-x-3 px-6 py-3 hover:bg-green-500 transition">
                <i class="fas fa-file-alt text-xl w-6"></i>
                <span x-show="sidebarOpen">Templates</span>
            </a>
            <a href="/dashboard/profile" class="flex items-center space-x-3 px-6 py-3 hover:bg-green-500 transition">
                <i class="fas fa-building text-xl w-6"></i>
                <span x-show="sidebarOpen">Business Profile</span>
            </a>
        </nav>

        <!-- Toggle Sidebar -->
        <div class="p-4 border-t border-green-500">
            <button @click="sidebarOpen = !sidebarOpen" class="w-full flex items-center justify-center py-2 hover:bg-green-500 rounded transition">
                <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm">
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">@yield('page-title', 'Dashboard')</h1>
                    <p class="text-sm text-gray-600">@yield('page-description', '')</p>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="relative p-2 text-gray-600 hover:text-gray-900 transition">
                            <i class="fas fa-bell text-xl"></i>
                            <span x-show="notifications.length > 0" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center" x-text="notifications.length"></span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg py-2 z-50">
                            <div class="px-4 py-2 border-b">
                                <h3 class="font-semibold text-gray-900">Notifications</h3>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <template x-if="notifications.length === 0">
                                    <div class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-3xl mb-2"></i>
                                        <p>No notifications</p>
                                    </div>
                                </template>
                                <template x-for="notif in notifications" :key="notif.time">
                                    <div class="px-4 py-3 hover:bg-gray-50 border-b">
                                        <p class="text-sm text-gray-900" x-text="notif.message"></p>
                                        <p class="text-xs text-gray-500 mt-1" x-text="notif.time"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded-lg transition">
                            <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                <span class="text-white font-semibold" x-text="user ? user.name.charAt(0).toUpperCase() : 'U'"></span>
                            </div>
                            <span class="text-sm font-medium text-gray-900" x-text="user ? user.name : 'User'"></span>
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-50">
                            <button @click="logout()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-2">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
         </header>

        <!-- Email Verification Banner -->
        <template x-if="user && user.is_email_verified === false">
            <div class="bg-amber-50 border-b border-amber-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-exclamation-triangle text-amber-600 text-xl"></i>
                            <div>
                                <h3 class="text-amber-900 font-semibold">Email Not Verified</h3>
                                <p class="text-amber-700 text-sm">Please verify your email to access all features</p>
                            </div>
                        </div>
                        <button x-data="{ showOtpModal: false, email: user?.email || '', otpCode: '', sending: false, verifying: false, otpSent: false, resendTimer: 0 }" @click="showOtpModal = true" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            Verify Email
                        </button>

                        <!-- OTP Modal -->
                        <template x-if="showOtpModal">
                            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: flex;">
                                <div @click.away="showOtpModal = false" class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-xl font-bold text-gray-900">Verify Your Email</h3>
                                            <button @click="showOtpModal = false" class="text-gray-400 hover:text-gray-600">
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>

                                        <p class="text-gray-600 mb-4">We'll send a 6-digit code to <span x-text="email" class="font-semibold"></span></p>

                                        <!-- Success Message -->
                                        <div x-show="success" x-transition class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
                                            <span x-text="success"></span>
                                        </div>

                                        <!-- Error Message -->
                                        <div x-show="error" x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                                            <span x-text="error"></span>
                                        </div>

                                        <!-- Send OTP Section -->
                                        <div x-show="!otpSent">
                                            <button @click="
                                                sending = true;
                                                fetch('/api/send-otp', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                                    body: JSON.stringify({ email: email, type: 'email_verification' })
                                                })
                                                .then(res => res.json())
                                                .then(data => {
                                                    sending = false;
                                                    if (data.success) {
                                                        otpSent = true;
                                                        success = 'OTP sent! Check your email.';
                                                        resendTimer = 60;
                                                        const timer = setInterval(() => {
                                                            resendTimer--;
                                                            if (resendTimer <= 0) clearInterval(timer);
                                                        }, 1000);
                                                    } else {
                                                        error = data.message || 'Failed to send OTP';
                                                    }
                                                })
                                                .catch(e => {
                                                    sending = false;
                                                    error = 'Network error. Please try again.';
                                                });
                                            " :disabled="sending" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-lg font-medium transition">
                                                <span x-show="!sending">Send Verification Code</span>
                                                <span x-show="sending" class="flex items-center justify-center">
                                                    <i class="fas fa-spinner animate-spin mr-2"></i> Sending...
                                                </span>
                                            </button>
                                        </div>

                                        <!-- Verify OTP Section -->
                                        <div x-show="otpSent">
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Enter 6-digit code</label>
                                                <input x-model="otpCode" type="text" maxlength="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-center text-2xl tracking-widest font-mono focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="000000">
                                            </div>

                                            <button @click="
                                                verifying = true;
                                                fetch('/api/verify-otp', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                                    body: JSON.stringify({ email: email, code: otpCode, type: 'email_verification' })
                                                })
                                                .then(res => res.json())
                                                .then(data => {
                                                    verifying = false;
                                                    if (data.success) {
                                                        user.is_email_verified = true;
                                                        localStorage.setItem('user', JSON.stringify(user));
                                                        showOtpModal = false;
                                                        success = 'Email verified successfully!';
                                                    } else {
                                                        error = data.message || 'Invalid or expired code';
                                                    }
                                                })
                                                .catch(e => {
                                                    verifying = false;
                                                    error = 'Network error. Please try again.';
                                                });
                                            " :disabled="verifying || otpCode.length !== 6" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-lg font-medium transition mb-2">
                                                <span x-show="!verifying">Verify Code</span>
                                                <span x-show="verifying" class="flex items-center justify-center">
                                                    <i class="fas fa-spinner animate-spin mr-2"></i> Verifying...
                                                </span>
                                            </button>

                                            <button @click="
                                                if (resendTimer > 0) return;
                                                sending = true;
                                                fetch('/api/resend-otp', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                                    body: JSON.stringify({ email: email, type: 'email_verification' })
                                                })
                                                .then(res => res.json())
                                                .then(data => {
                                                    sending = false;
                                                    if (data.success) {
                                                        success = 'New OTP sent! Check your email.';
                                                        resendTimer = 60;
                                                        otpCode = '';
                                                        const timer = setInterval(() => {
                                                            resendTimer--;
                                                            if (resendTimer <= 0) clearInterval(timer);
                                                        }, 1000);
                                                    } else {
                                                        error = data.message || 'Failed to resend OTP';
                                                    }
                                                })
                                                .catch(e => {
                                                    sending = false;
                                                    error = 'Network error. Please try again.';
                                                });
                                            " :disabled="sending || resendTimer > 0" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-lg font-medium transition">
                                                <span x-show="resendTimer === 0">Resend Code</span>
                                                <span x-show="resendTimer > 0">Resend in <span x-text="resendTimer"></span>s</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- Page Content -->
        <main class="flex-1 overflow-auto p-6">
            @yield('content')
        </main>
    </div>
</div>
@endsection
