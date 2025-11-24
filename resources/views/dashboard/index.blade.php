@extends('layouts.dashboard')

@section('title', 'Dashboard - WhatsApp Business API')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600">Welcome back! Here's your WhatsApp Business overview.</p>
        </div>
        <div>
            <button onclick="location.reload()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-sm">
                <i class="fas fa-sync-alt mr-2"></i> Refresh
            </button>
        </div>
    </div>

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
        <div class="bg-gradient-to-br from-purple-600 to-indigo-600 rounded-xl shadow-lg p-5 lg:p-6 text-white transform hover:scale-105 transition-transform">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-xs sm:text-sm font-medium">Total Messages</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-2" x-text="stats.totalMessages || '0'">0</p>
                </div>
                <div class="bg-purple-400 bg-opacity-30 rounded-full p-2 sm:p-3">
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
                <a href="/dashboard/messages" class="text-xs sm:text-sm text-purple-600 hover:text-purple-700 font-medium">View all →</a>
            </div>

            <div class="space-y-3 sm:space-y-4">
                <template x-for="message in messages" :key="message.id">
                    <div class="flex items-start space-x-3 sm:space-x-4 p-3 sm:p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition cursor-pointer" @click="viewMessage(message)">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full flex items-center justify-center text-white font-semibold">
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
                        <div class="bg-purple-50 rounded-full p-2">
                            <i class="fas fa-phone text-purple-600"></i>
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
                            <p class="text-sm font-semibold" :class="info.verified_name ? 'text-purple-600' : 'text-gray-400'">
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
                    <a href="/dashboard/messages" class="flex items-center space-x-3 p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                        <div class="bg-purple-600 rounded-full p-2 text-white">
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
</div>

<script>
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
