@extends('layouts.app')

@section('title', 'Dashboard - WhatsApp Business API')

@section('content')
<div x-data="{
    sidebarOpen: true,
    user: null,
    notifications: [],

    init() {
        // Load sidebar state from localStorage
        let savedSidebarState = localStorage.getItem('sidebarOpen');
        if (savedSidebarState !== null) {
            this.sidebarOpen = JSON.parse(savedSidebarState);
        }

        // Watch for sidebarOpen changes and save to localStorage
        this.$watch('sidebarOpen', value => {
            localStorage.setItem('sidebarOpen', JSON.stringify(value));
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

        // Load saved notifications from localStorage
        let savedNotifications = localStorage.getItem('notifications');
        if (savedNotifications) {
            try {
                this.notifications = JSON.parse(savedNotifications);
            } catch (e) {
                this.notifications = [];
            }
        }

        // Listen for new WhatsApp messages
        window.addEventListener('whatsapp-message-received', (event) => {
            console.log('📩 Dashboard - New message notification:', event.detail);
            this.addNotification({
                type: 'message',
                icon: 'fa-comment',
                color: 'green',
                title: 'New Message',
                message: `From ${event.detail.contact?.name || event.detail.contact?.phone_number || 'Unknown'}`,
                detail: event.detail.message?.body || 'New message received',
                time: new Date().toISOString()
            });
        });

        // Listen for message status updates
        window.addEventListener('whatsapp-status-updated', (event) => {
            console.log('📊 Dashboard - Status update:', event.detail);
            if (event.detail.status === 'read') {
                this.addNotification({
                    type: 'status',
                    icon: 'fa-check-double',
                    color: 'blue',
                    title: 'Message Read',
                    message: 'Your message has been read',
                    time: new Date().toISOString()
                });
            }
        });

        // Listen for profile updates
        window.addEventListener('whatsapp-profile-updated', (event) => {
            console.log('👤 Dashboard - Profile update:', event.detail);
            this.addNotification({
                type: 'profile',
                icon: 'fa-building',
                color: 'yellow',
                title: 'Profile Updated',
                message: 'Business profile has been updated',
                detail: Object.keys(event.detail.profileData || {}).join(', '),
                time: new Date().toISOString()
            });
        });
    },

    addNotification(notif) {
        // Add unique id
        notif.id = Date.now() + Math.random();
        this.notifications.unshift(notif);

        // Keep only last 50 notifications
        if (this.notifications.length > 50) {
            this.notifications = this.notifications.slice(0, 50);
        }

        // Save to localStorage
        localStorage.setItem('notifications', JSON.stringify(this.notifications));

        // Play notification sound (optional)
        this.playNotificationSound();
    },

    playNotificationSound() {
        try {
            let audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleIYNQaKzpYyLjH5oXF2OvNXNpHBBSU1Xh42JjZWgmpF+Y11vn7y0r5+DaVVJYXmEmpycnJqblJifl5KBb2lqZm14foiBe3RwcnV3e3x2cW1ra2xxdnl5d3Nxc3V2d3Z0cXBxc3V3d3d2dHN0dXZ3eHh3dnV1dnd4eXl4d3Z2d3h5eXl4d3d3eHl5eXh3d3d4eXl5eHd3d3h5eXl4d3d3eHl5eXh3d3d4');
            audio.volume = 0.3;
            audio.play().catch(() => {});
        } catch (e) {}
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
        let now = new Date();
        let diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return date.toLocaleDateString();
    },

    logout() {
        let apiBaseUrl = window.location.origin + '/api';
        let token = localStorage.getItem('token');

        if (token) {
            fetch(`${apiBaseUrl}/logout`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            }).then(() => {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                localStorage.removeItem('sidebarOpen');
                localStorage.removeItem('notifications');
                window.location.href = '/login';
            }).catch(() => {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                localStorage.removeItem('sidebarOpen');
                localStorage.removeItem('notifications');
                window.location.href = '/login';
            });
        } else {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            localStorage.removeItem('sidebarOpen');
            localStorage.removeItem('notifications');
            window.location.href = '/login';
        }
    }
}" class="min-h-screen flex">
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-slate-900 text-white transition-all duration-300 flex flex-col fixed lg:static inset-y-0 left-0 z-50">
        <!-- Logo -->
        <div class="p-6 flex items-center justify-between border-b border-slate-700">
            <div x-show="sidebarOpen" class="flex items-center space-x-3">
                <img src="{{ asset('images/logo.png') }}" class="h-8 rounded-lg" alt="Logo">
                <span class="text-xl font-bold">QashierWise</span>
            </div>
            <img x-show="!sidebarOpen" src="{{ asset('images/logo.png') }}" class="h-8 rounded-lg mx-auto" alt="Logo">
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-6">
            <a href="/dashboard" class="flex items-center space-x-3 px-6 py-3 bg-slate-800 transition">
                <i class="fas fa-home text-xl w-6"></i>
                <span x-show="sidebarOpen">Dashboard</span>
            </a>
            <a href="/dashboard/contacts" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-address-book text-xl w-6"></i>
                <span x-show="sidebarOpen">Contacts</span>
            </a>
            <a href="/dashboard/messages" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-comments text-xl w-6"></i>
                <span x-show="sidebarOpen">Messages</span>
            </a>
            <a href="/dashboard/templates" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-file-alt text-xl w-6"></i>
                <span x-show="sidebarOpen">Templates</span>
            </a>
            <a href="/dashboard/profile" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-building text-xl w-6"></i>
                <span x-show="sidebarOpen">Business Profile</span>
            </a>
        </nav>

        <!-- User Info & Logout -->
        <div class="p-4 border-t border-slate-700">
            <div x-show="sidebarOpen" class="mb-3">
                <div class="flex items-center space-x-3 px-2 py-2 bg-slate-800 rounded-lg mb-2">
                    <div class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-lg" x-text="user ? user.name.charAt(0).toUpperCase() : 'U'"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold text-sm truncate" x-text="user ? user.name : 'User'"></p>
                        <p class="text-slate-400 text-xs truncate" x-text="user ? user.email : ''"></p>
                    </div>
                </div>
                <button @click="logout()" class="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-red-500 hover:bg-red-600 rounded-lg transition text-white font-medium">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>

            <!-- Toggle & Collapsed Actions -->
            <div class="flex items-center justify-between">
                <button x-show="!sidebarOpen" @click="logout()" class="p-2 hover:bg-red-500 rounded transition" title="Logout">
                    <i class="fas fa-sign-out-alt text-xl"></i>
                </button>
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 hover:bg-slate-800 rounded transition">
                    <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm">
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-600">Welcome back! Here's your WhatsApp Business overview.</p>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="relative p-2 text-gray-600 hover:text-gray-900 transition">
                            <i class="fas fa-bell text-xl"></i>
                            <span x-show="notifications.length > 0" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center" x-text="notifications.length > 9 ? '9+' : notifications.length"></span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-lg py-2 z-50">
                            <div class="px-4 py-2 border-b flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Notifications</h3>
                                <button x-show="notifications.length > 0" @click="clearNotifications()" class="text-xs text-red-500 hover:text-red-700">
                                    Clear all
                                </button>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <template x-if="notifications.length === 0">
                                    <div class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-3xl mb-2"></i>
                                        <p>No notifications</p>
                                    </div>
                                </template>
                                <template x-for="notif in notifications" :key="notif.id">
                                    <div class="px-4 py-3 hover:bg-gray-50 border-b flex items-start space-x-3 group">
                                        <div class="flex-shrink-0 mt-1">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                 :class="{
                                                     'bg-green-100 text-green-600': notif.color === 'green',
                                                     'bg-blue-100 text-blue-600': notif.color === 'blue',
                                                     'bg-yellow-100 text-yellow-600': notif.color === 'yellow',
                                                     'bg-red-100 text-red-600': notif.color === 'red',
                                                     'bg-gray-100 text-gray-600': !notif.color
                                                 }">
                                                <i class="fas" :class="notif.icon || 'fa-bell'"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900" x-text="notif.title || 'Notification'"></p>
                                            <p class="text-sm text-gray-600 truncate" x-text="notif.message"></p>
                                            <p x-show="notif.detail" class="text-xs text-gray-500 truncate mt-0.5" x-text="notif.detail"></p>
                                            <p class="text-xs text-gray-400 mt-1" x-text="formatNotificationTime(notif.time)"></p>
                                        </div>
                                        <button @click.stop="removeNotification(notif.id)" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6" x-data="dashboardStats()">
        <!-- Shimmer Loading for Stats -->
        <template x-if="loading">
            <div class="contents">
                <template x-for="i in 4" :key="i">
                    <div class="bg-white rounded-xl shadow-lg p-5 lg:p-6 animate-pulse">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="h-3 bg-gray-200 rounded w-24 mb-3"></div>
                                <div class="h-8 bg-gray-300 rounded w-16"></div>
                            </div>
                            <div class="w-12 h-12 bg-gray-200 rounded-full"></div>
                        </div>
                        <div class="mt-4 h-3 bg-gray-200 rounded w-32"></div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Actual Stats Cards (shown after loading) -->
        <template x-if="!loading">
            <div class="contents">
            <!-- Total Contacts -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 lg:p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs sm:text-sm font-medium">Total Contacts</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2" x-text="stats.totalContacts || '0'">0</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-xl text-indigo-500"></i>
                    </div>
                </div>
                <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm">
                    <span :class="stats.contactsGrowth >= 0 ? 'text-green-500' : 'text-red-500'" class="flex items-center font-medium">
                        <i class="fas mr-1" :class="stats.contactsGrowth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                        <span x-text="Math.abs(stats.contactsGrowth) || '0'">0</span>%
                    </span>
                    <span class="text-gray-400 ml-1">from last month</span>
                </div>
            </div>

            <!-- Total Messages -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 lg:p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs sm:text-sm font-medium">Total Messages</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2" x-text="stats.totalMessages || '0'">0</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-comment-dots text-xl text-emerald-500"></i>
                    </div>
                </div>
                <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm">
                    <span :class="stats.messagesGrowth >= 0 ? 'text-green-500' : 'text-red-500'" class="flex items-center font-medium">
                        <i class="fas mr-1" :class="stats.messagesGrowth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                        <span x-text="Math.abs(stats.messagesGrowth) || '0'">0</span>%
                    </span>
                    <span class="text-gray-400 ml-1">from last week</span>
                </div>
            </div>

            <!-- Templates -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 lg:p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs sm:text-sm font-medium">Templates</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2" x-text="stats.totalTemplates || '0'">0</p>
                    </div>
                    <div class="w-12 h-12 bg-cyan-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-file-alt text-xl text-cyan-500"></i>
                    </div>
                </div>
                <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm text-gray-500">
                    <span x-text="stats.approvedTemplates || '0'">0</span>&nbsp;approved
                </div>
            </div>

            <!-- Unread Messages -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 lg:p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs sm:text-sm font-medium">Unread Messages</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2" x-text="stats.unreadMessages || '0'">0</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                        <i class="fas fa-bell text-xl text-orange-500"></i>
                    </div>
                </div>
                <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm">
                    <a href="/dashboard/messages" class="text-gray-400 hover:text-gray-600">View all messages →</a>
                </div>
            </div>
            </div>
        </template>
    </div>

    <!-- Weekly Messages Chart -->
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-6" x-data="weeklyChart()" x-init="init()">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-lg sm:text-xl font-bold text-gray-900">Weekly Messages Statistics</h2>
            <div class="flex items-center space-x-2 text-sm">
                <span class="flex items-center">
                    <span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span>
                    <span class="text-gray-600">Incoming</span>
                </span>
                <span class="flex items-center">
                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span>
                    <span class="text-gray-600">Outgoing</span>
                </span>
            </div>
        </div>

        <!-- Shimmer Loading for Chart -->
        <div x-show="loading" class="animate-pulse">
            <div class="h-64 bg-gray-200 rounded-lg"></div>
        </div>

        <!-- Chart Canvas -->
        <div x-show="!loading" class="relative" style="height: 280px;">
            <canvas id="weeklyMessagesChart"></canvas>
        </div>

        <!-- Chart Summary -->
        <div x-show="!loading" class="grid grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-100">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900" x-text="chartSummary.totalIncoming">0</p>
                <p class="text-sm text-gray-500">Incoming</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900" x-text="chartSummary.totalOutgoing">0</p>
                <p class="text-sm text-gray-500">Outgoing</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900" x-text="chartSummary.total">0</p>
                <p class="text-sm text-gray-500">Total This Week</p>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
        <!-- Recent Messages -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-4 sm:p-6" x-data="recentMessages()">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900">Recent Messages</h2>
                <a href="/dashboard/messages" class="text-xs sm:text-sm text-green-600 hover:text-green-700 font-medium">View all →</a>
            </div>

            <!-- Shimmer Loading for Messages -->
            <div x-show="loading" class="space-y-3 sm:space-y-4">
                <template x-for="i in 5" :key="'msg-shimmer-'+i">
                    <div class="flex items-start space-x-3 sm:space-x-4 p-3 sm:p-4 bg-gray-50 rounded-lg animate-pulse">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gray-300 rounded-full"></div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <div class="h-4 bg-gray-300 rounded w-32"></div>
                                <div class="h-3 bg-gray-200 rounded w-16"></div>
                            </div>
                            <div class="h-3 bg-gray-200 rounded w-3/4 mb-2"></div>
                            <div class="flex space-x-2">
                                <div class="h-5 bg-gray-200 rounded-full w-16"></div>
                                <div class="h-5 bg-gray-200 rounded-full w-14"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Actual Messages -->
            <div x-show="!loading" class="space-y-3 sm:space-y-4">
                <template x-for="message in messages" :key="message.id">
                    <div class="flex items-start space-x-3 sm:space-x-4 p-3 sm:p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition cursor-pointer" @click="viewMessage(message)">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold">
                                <span x-text="message.contact_name ? message.contact_name.charAt(0).toUpperCase() : '?'">?</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="message.contact_name || message.from_number">Unknown</p>
                                <p class="text-xs text-gray-500" x-text="formatTime(message.created_at)">Just now</p>
                            </div>
                            <p class="text-sm text-gray-600 truncate mt-1" x-text="message.body || `[${message.type}]`">Message content</p>
                            <div class="flex items-center mt-2 space-x-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                      :class="{
                                          'bg-blue-100 text-blue-800': message.status === 'sent',
                                          'bg-green-100 text-green-800': message.status === 'delivered',
                                          'bg-purple-100 text-purple-800': message.status === 'read',
                                          'bg-red-100 text-red-800': message.status === 'failed'
                                      }">
                                    <i class="fas fa-check mr-1"></i>
                                    <span x-text="message.status">Status</span>
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <i :class="`fas fa-${getTypeIcon(message.type)} mr-1`"></i>
                                    <span x-text="message.type">Type</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="!messages || messages.length === 0" class="text-center py-12 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>No messages yet</p>
                </div>
            </div>
        </div>

        <!-- Phone Info & Quick Actions -->
        <div class="space-y-4 lg:space-y-6">
            <!-- Phone Info Card -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6" x-data="phoneInfo()">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-4">Phone Information</h2>
                <div class="space-y-3 sm:space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-green-100 rounded-full p-2">
                            <i class="fas fa-phone text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Phone Number</p>
                            <p class="text-sm font-semibold text-gray-900" x-text="info.display_phone_number || '-'">-</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="bg-blue-100 rounded-full p-2">
                            <i class="fas fa-shield-alt text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Verified</p>
                            <p class="text-sm font-semibold" :class="info.verified_name ? 'text-green-600' : 'text-gray-400'">
                                <span x-text="info.verified_name || 'Not Verified'">Not Verified</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="bg-purple-100 rounded-full p-2">
                            <i class="fas fa-star text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Quality</p>
                            <p class="text-sm font-semibold text-gray-900 capitalize" x-text="info.quality_rating || '-'">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
                <div class="space-y-2 sm:space-y-3">
                    <a href="/dashboard/contacts" class="flex items-center space-x-3 p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                        <div class="bg-blue-500 rounded-full p-2 text-white">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Manage Contacts</p>
                            <p class="text-xs text-gray-500">View and organize contacts</p>
                        </div>
                    </a>
                    <a href="/dashboard/messages" class="flex items-center space-x-3 p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                        <div class="bg-green-500 rounded-full p-2 text-white">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Send Message</p>
                            <p class="text-xs text-gray-500">Start a conversation</p>
                        </div>
                    </a>
                    <a href="/dashboard/templates" class="flex items-center space-x-3 p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                        <div class="bg-purple-500 rounded-full p-2 text-white">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Templates</p>
                            <p class="text-xs text-gray-500">Manage message templates</p>
                        </div>
                    </a>
                </div>
            </div>
            </div>
        </div>
        </main>
    </div>
</div>

<script>
    // Global function for sidebar state management
    function initSidebarState() {
        // Make sidebar state persistent across pages
        window.sidebarState = {
            save: function(state) {
                localStorage.setItem('sidebarOpen', JSON.stringify(state));
            },
            load: function() {
                const saved = localStorage.getItem('sidebarOpen');
                return saved !== null ? JSON.parse(saved) : true;
            }
        };
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initSidebarState);
    function dashboardStats() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            loading: true,
            stats: {
                totalContacts: 0,
                totalMessages: 0,
                totalTemplates: 0,
                unreadMessages: 0,
                contactsGrowth: 0,
                messagesGrowth: 0,
                approvedTemplates: 0
            },

            async init() {
                await this.fetchStats();

                // Listen for incoming messages to update stats
                window.addEventListener('whatsapp-message-received', () => {
                    console.log('📊 Dashboard - Refreshing stats...');
                    this.fetchStats();
                });
            },

            async fetchStats() {
                this.loading = true;
                try {
                    const token = localStorage.getItem('token');

                    // Fetch dashboard stats (optimized single endpoint)
                    const statsRes = await fetch(`${this.API_BASE_URL}/whatsapp/stats`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const statsData = await statsRes.json();

                    if (statsData.success && statsData.data) {
                        this.stats.totalContacts = statsData.data.total_contacts || 0;
                        this.stats.totalMessages = statsData.data.total_messages || 0;
                        this.stats.unreadMessages = statsData.data.unread_messages || 0;
                        this.stats.contactsGrowth = statsData.data.contacts_growth || 0;
                        this.stats.messagesGrowth = statsData.data.messages_growth || 0;
                    }

                    // Fetch templates count
                    const templatesRes = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const templatesData = await templatesRes.json();
                    this.stats.totalTemplates = templatesData.data?.length || 0;
                    this.stats.approvedTemplates = templatesData.data?.filter(t => t.status === 'APPROVED').length || 0;

                } catch (error) {
                    console.error('Error fetching stats:', error);
                } finally {
                    this.loading = false;
                }
            }
        }
    }

    function weeklyChart() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            loading: true,
            chart: null,
            chartData: [],
            chartSummary: {
                totalIncoming: 0,
                totalOutgoing: 0,
                total: 0
            },

            async init() {
                await this.fetchChartData();
            },

            async fetchChartData() {
                this.loading = true;
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/stats/weekly-chart`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const result = await response.json();

                    if (result.success && result.data) {
                        this.chartData = result.data;
                        this.calculateSummary();

                        // Wait for DOM to be ready
                        await this.$nextTick();
                        this.renderChart();
                    }
                } catch (error) {
                    console.error('Error fetching chart data:', error);
                } finally {
                    this.loading = false;
                }
            },

            calculateSummary() {
                this.chartSummary.totalIncoming = this.chartData.reduce((sum, d) => sum + d.incoming, 0);
                this.chartSummary.totalOutgoing = this.chartData.reduce((sum, d) => sum + d.outgoing, 0);
                this.chartSummary.total = this.chartSummary.totalIncoming + this.chartSummary.totalOutgoing;
            },

            renderChart() {
                const ctx = document.getElementById('weeklyMessagesChart');
                if (!ctx) return;

                // Destroy existing chart if exists
                if (this.chart) {
                    this.chart.destroy();
                }

                const labels = this.chartData.map(d => d.day);
                const incomingData = this.chartData.map(d => d.incoming);
                const outgoingData = this.chartData.map(d => d.outgoing);

                this.chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Incoming',
                                data: incomingData,
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: 'rgb(34, 197, 94)',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 7
                            },
                            {
                                label: 'Outgoing',
                                data: outgoingData,
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: 'rgb(59, 130, 246)',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 7
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                padding: 12,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    title: function(context) {
                                        return context[0].label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    color: '#6b7280'
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#6b7280'
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        }
    }

    function recentMessages() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            messages: [],
            loading: true,

            async init() {
                await this.fetchMessages();
            },

            async fetchMessages() {
                this.loading = true;
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/messages?limit=5`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.messages = data.data || [];
                } catch (error) {
                    console.error('Error fetching messages:', error);
                } finally {
                    this.loading = false;
                }
            },

            formatTime(timestamp) {
                const date = new Date(timestamp);
                const now = new Date();
                const diff = now - date;
                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor(diff / 60000);

                if (hours > 24) return date.toLocaleDateString();
                if (hours > 0) return `${hours}h ago`;
                if (minutes > 0) return `${minutes}m ago`;
                return 'Just now';
            },

            getTypeIcon(type) {
                const icons = {
                    text: 'comment',
                    image: 'image',
                    video: 'video',
                    audio: 'microphone',
                    document: 'file',
                    location: 'map-marker-alt'
                };
                return icons[type] || 'comment';
            },

            viewMessage(message) {
                window.location.href = `/dashboard/messages?contact=${message.contact_id}`;
            }
        }
    }

    function phoneInfo() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            info: {},

            async init() {
                await this.fetchPhoneInfo();
            },

            async fetchPhoneInfo() {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/phone-info`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.info = data.data || {};
                } catch (error) {
                    console.error('Error fetching phone info:', error);
                }
            }
        }
    }
</script>
@endsection
