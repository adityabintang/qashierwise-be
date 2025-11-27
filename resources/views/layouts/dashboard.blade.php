@extends('layouts.app')

@section('content')
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
        window.addEventListener('whatsapp-message-received', (event) => {
            console.log('📩 Dashboard - New message notification:', event.detail);
            this.notifications.unshift({
                message: `New message from ${event.detail.contact?.name || 'Unknown'}`,
                time: new Date().toLocaleTimeString()
            });
        });
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
}" class="min-h-screen flex">
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-gradient-to-b from-green-600 to-green-700 text-white transition-all duration-300 flex flex-col">
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
    <div class="flex-1 flex flex-col">
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

        <!-- Page Content -->
        <main class="flex-1 overflow-auto p-6">
            @yield('content')
        </main>
    </div>
</div>
@endsection
