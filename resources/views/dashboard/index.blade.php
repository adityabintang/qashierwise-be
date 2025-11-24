@extends('layouts.dashboard')

@section('title', 'Dashboard - WhatsApp Business API')

@section('content')
<div x-data="{
    sidebarOpen: true,
    user: null,
    notifications: [],

    logout() {
        const API_BASE_URL = 'https://api.qashierwise.com/api';
        const token = localStorage.getItem('token');

        if (token) {
            fetch(`${API_BASE_URL}/logout`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            }).then(() => {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                localStorage.removeItem('sidebarOpen');
                window.location.href = '/login';
            }).catch(() => {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                localStorage.removeItem('sidebarOpen');
                window.location.href = '/login';
            });
        } else {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            localStorage.removeItem('sidebarOpen');
            window.location.href = '/login';
        }
    }
}" x-init="
    // Load sidebar state from localStorage
    const savedSidebarState = localStorage.getItem('sidebarOpen');
    if (savedSidebarState !== null) {
        sidebarOpen = JSON.parse(savedSidebarState);
    }

    // Watch for sidebarOpen changes and save to localStorage
    $watch('sidebarOpen', value => {
        localStorage.setItem('sidebarOpen', JSON.stringify(value));
    });

    // Load user info
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
        try {
            user = JSON.parse(storedUser);
        } catch (e) {
            user = { name: 'User', email: 'user@example.com' };
        }
    } else {
        user = { name: 'User', email: 'user@example.com' };
    }

    // Listen for new WhatsApp messages
    window.addEventListener('whatsapp-message-received', (event) => {
        console.log('📩 Dashboard - New message notification:', event.detail);
        notifications.unshift({
            message: `New message from ${event.detail.contact?.name || 'Unknown'}`,
            time: new Date().toLocaleTimeString()
        });
    });
" class="min-h-screen flex">
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-gradient-to-b from-green-600 to-green-700 text-white transition-all duration-300 flex flex-col fixed lg:static inset-y-0 left-0 z-50">
        <!-- Logo -->
        <div class="p-6 flex items-center justify-between border-b border-green-500">
            <div x-show="sidebarOpen" class="flex items-center space-x-3">
                <i class="fab fa-whatsapp text-3xl"></i>
                <span class="text-xl font-bold">QashierWise</span>
            </div>
            <i x-show="!sidebarOpen" class="fab fa-whatsapp text-3xl mx-auto"></i>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-6">
            <a href="/dashboard" class="flex items-center space-x-3 px-6 py-3 bg-green-500 transition">
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

        <!-- User Info & Logout -->
        <div class="p-4 border-t border-green-500">
            <div x-show="sidebarOpen" class="mb-3">
                <div class="flex items-center space-x-3 px-2 py-2 bg-green-500 bg-opacity-30 rounded-lg mb-2">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-lg" x-text="user ? user.name.charAt(0).toUpperCase() : 'U'"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold text-sm truncate" x-text="user ? user.name : 'User'"></p>
                        <p class="text-green-100 text-xs truncate" x-text="user ? user.email : ''"></p>
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
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 hover:bg-green-500 rounded transition">
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

                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6" x-data="dashboardStats()">
        <!-- Total Contacts -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 lg:p-6 text-white transform hover:scale-105 transition-transform">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-xs sm:text-sm font-medium">Total Contacts</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-2" x-text="stats.totalContacts || '0'">0</p>
                </div>
                <div class="bg-blue-400 bg-opacity-30 rounded-full p-2 sm:p-3">
                    <i class="fas fa-users text-xl sm:text-2xl"></i>
                </div>
            </div>
            <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm">
                <i class="fas fa-arrow-up mr-1"></i>
                <span x-text="stats.contactsGrowth || '0'">0</span>% from last month
            </div>
        </div>

        <!-- Total Messages -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-5 lg:p-6 text-white transform hover:scale-105 transition-transform">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-xs sm:text-sm font-medium">Total Messages</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-2" x-text="stats.totalMessages || '0'">0</p>
                </div>
                <div class="bg-green-400 bg-opacity-30 rounded-full p-2 sm:p-3">
                    <i class="fas fa-comment-dots text-xl sm:text-2xl"></i>
                </div>
            </div>
            <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm">
                <i class="fas fa-arrow-up mr-1"></i>
                <span x-text="stats.messagesGrowth || '0'">0</span>% from last week
            </div>
        </div>

        <!-- Templates -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-5 lg:p-6 text-white transform hover:scale-105 transition-transform">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-xs sm:text-sm font-medium">Templates</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-2" x-text="stats.totalTemplates || '0'">0</p>
                </div>
                <div class="bg-purple-400 bg-opacity-30 rounded-full p-2 sm:p-3">
                    <i class="fas fa-file-alt text-xl sm:text-2xl"></i>
                </div>
            </div>
            <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm">
                <span x-text="stats.approvedTemplates || '0'">0</span> approved
            </div>
        </div>

        <!-- Unread Messages -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-5 lg:p-6 text-white transform hover:scale-105 transition-transform">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-xs sm:text-sm font-medium">Unread Messages</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-2" x-text="stats.unreadMessages || '0'">0</p>
                </div>
                <div class="bg-orange-400 bg-opacity-30 rounded-full p-2 sm:p-3">
                    <i class="fas fa-envelope text-xl sm:text-2xl"></i>
                </div>
            </div>
            <div class="mt-3 sm:mt-4 flex items-center text-xs sm:text-sm">
                <a href="/dashboard/messages" class="hover:underline">View all messages →</a>
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

            <div class="space-y-3 sm:space-y-4">
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
            API_BASE_URL: 'https://api.qashierwise.com/api',
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
                }
            }
        }
    }

    function recentMessages() {
        return {
            API_BASE_URL: 'https://api.qashierwise.com/api',
            messages: [],

            async init() {
                await this.fetchMessages();
            },

            async fetchMessages() {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/messages?limit=5`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.messages = data.data || [];
                } catch (error) {
                    console.error('Error fetching messages:', error);
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
            API_BASE_URL: 'https://api.qashierwise.com/api',
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
