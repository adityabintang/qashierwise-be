@extends('layouts.app')

@section('title', 'Messages - WhatsApp Business API')

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
            <a href="/dashboard" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-home text-xl w-6"></i>
                <span x-show="sidebarOpen">Dashboard</span>
            </a>
            <a href="/dashboard/contacts" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-address-book text-xl w-6"></i>
                <span x-show="sidebarOpen">Contacts</span>
            </a>
            <a href="/dashboard/messages" class="flex items-center space-x-3 px-6 py-3 bg-slate-800 transition">
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
                    <h1 class="text-2xl font-bold text-gray-900">Messages</h1>
                    <p class="text-sm text-gray-600">Chat with your WhatsApp contacts</p>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="h-[calc(100vh-12rem)]" x-data="messagesManager()">
                    <div class="flex h-full bg-white rounded-xl shadow-lg overflow-hidden">
                        <!-- Contacts Sidebar -->
        <div class="w-full md:w-1/3 border-r border-gray-200 flex flex-col">
            <!-- Search -->
            <div class="p-4 border-b border-gray-200">
                <div class="relative">
                    <input
                        type="text"
                        x-model="contactSearch"
                        @input="filterContactList"
                        placeholder="Search contacts..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>

            <!-- Contacts List -->
            <div class="flex-1 overflow-y-auto">
                <!-- Shimmer Loading for Contacts -->
                <template x-if="loadingContacts">
                    <div>
                        <template x-for="i in 6" :key="'contact-shimmer-'+i">
                            <div class="flex items-center space-x-3 p-4 border-b border-gray-100 animate-pulse">
                                <div class="w-12 h-12 bg-gray-300 rounded-full"></div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="h-4 bg-gray-300 rounded w-24"></div>
                                        <div class="h-3 bg-gray-200 rounded w-12"></div>
                                    </div>
                                    <div class="h-3 bg-gray-200 rounded w-20 mb-1"></div>
                                    <div class="h-3 bg-gray-200 rounded w-32"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Actual Contacts List -->
                <template x-if="!loadingContacts">
                    <div>
                        <template x-for="contact in filteredContactList" :key="contact.id">
                            <div
                                @click="selectContact(contact)"
                                class="flex items-center space-x-3 p-4 hover:bg-gray-50 cursor-pointer transition border-b border-gray-100"
                                :class="{'bg-green-50': selectedContact?.id === contact.id}">
                                <div class="relative flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                        <span x-text="contact.name ? contact.name.charAt(0).toUpperCase() : '?'">?</span>
                                    </div>
                                    <div x-show="contact.unread_count > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold" x-text="contact.unread_count">0</div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-semibold text-gray-900 truncate" x-text="contact.name || contact.phone_number || 'Unknown'">Unknown</p>
                                        <p class="text-xs text-gray-500" x-text="formatTime(contact.last_message_at)">-</p>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate mt-0.5" x-text="contact.phone_number" x-show="contact.name">-</p>
                                    <p class="text-sm text-gray-600 truncate mt-1"
                                       :class="{'font-semibold': contact.unread_count > 0}"
                                       x-text="contact.last_message_text || 'No messages yet'">-</p>
                                </div>
                            </div>
                        </template>

                        <div x-show="filteredContactList.length === 0" class="text-center py-12 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>No contacts found</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="flex-1 flex flex-col" x-show="selectedContact">
            <!-- Chat Header -->
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                            <span x-text="selectedContact?.name ? selectedContact.name.charAt(0).toUpperCase() : '?'">?</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900" x-text="selectedContact?.name || 'Unknown'">Unknown</p>
                            <p class="text-xs text-gray-600" x-text="selectedContact?.phone_number">-</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button @click="refreshMessages" class="p-2 text-gray-600 hover:text-gray-900 transition">
                            <i class="fas fa-sync-alt" :class="{'fa-spin': loadingMessages}"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50" x-ref="messagesContainer">
                <!-- Shimmer Loading for Messages -->
                <template x-if="loadingMessages">
                    <div class="space-y-4">
                        <template x-for="i in 5" :key="'msg-shimmer-'+i">
                            <div class="flex animate-pulse" :class="i % 2 === 0 ? 'justify-end' : 'justify-start'">
                                <div class="max-w-xs rounded-lg p-3 shadow" :class="i % 2 === 0 ? 'bg-slate-800' : 'bg-white'">
                                    <div class="h-3 rounded w-32 mb-2" :class="i % 2 === 0 ? 'bg-slate-700' : 'bg-gray-200'"></div>
                                    <div class="h-3 rounded w-48" :class="i % 2 === 0 ? 'bg-slate-700' : 'bg-gray-200'"></div>
                                    <div class="flex items-center justify-end mt-2 space-x-1">
                                        <div class="h-2 rounded w-10" :class="i % 2 === 0 ? 'bg-slate-700' : 'bg-gray-200'"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Actual Messages -->
                <template x-if="!loadingMessages">
                    <div class="space-y-4">
                        <template x-for="(message, index) in messages" :key="message?.id || `msg-${index}`">
                            <div x-show="message && message.id" class="flex" :class="message?.direction === 'outgoing' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-xs md:max-w-md lg:max-w-lg">
                                    <!-- Message Bubble -->
                                    <div class="rounded-lg p-3 shadow"
                                 :class="message?.direction === 'outgoing' ? 'bg-slate-900 text-white' : 'bg-white text-gray-900'">

                                <!-- Text Message -->
                                <template x-if="message?.type === 'text'">
                                    <p class="text-sm whitespace-pre-wrap" x-text="message?.content || message?.body"></p>
                                </template>

                                <!-- Template Message -->
                                <template x-if="message?.type === 'template'">
                                    <div class="text-sm">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <i class="fas fa-file-alt"></i>
                                            <span class="font-medium">Template</span>
                                        </div>
                                        <div class="bg-white/20 rounded p-2 text-xs">
                                            <span x-text="message?.template_name || message?.body || 'Template message'"></span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Image Message -->
                                <template x-if="message?.type === 'image'">
                                    <div class="max-w-[200px]">
                                        <template x-if="message?.media_url">
                                            <a :href="message?.media_url" target="_blank">
                                                <img :src="message?.media_url" class="rounded-lg w-full h-auto max-h-[150px] object-cover cursor-pointer hover:opacity-90 transition" alt="Image">
                                            </a>
                                        </template>
                                        <template x-if="!message?.media_url">
                                            <div class="bg-white/20 rounded-lg p-4 text-center">
                                                <i class="fas fa-image text-2xl mb-2"></i>
                                                <p class="text-xs">Image</p>
                                            </div>
                                        </template>
                                        <p x-show="message?.caption" class="text-sm mt-1" x-text="message?.caption"></p>
                                    </div>
                                </template>

                                <!-- Document Message -->
                                <template x-if="message?.type === 'document'">
                                    <div class="flex items-center space-x-3 p-2 bg-white/10 rounded-lg">
                                        <div class="w-10 h-10 bg-white/20 rounded flex items-center justify-center">
                                            <i class="fas fa-file-pdf text-xl"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium truncate" x-text="message?.filename || 'Document'"></p>
                                            <template x-if="message?.media_url">
                                                <a :href="message?.media_url" target="_blank" class="text-xs underline opacity-80 hover:opacity-100">Download</a>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Audio Message -->
                                <template x-if="message?.type === 'audio'">
                                    <div class="max-w-[220px]">
                                        <template x-if="message?.media_url">
                                            <audio controls class="w-full h-10">
                                                <source :src="message?.media_url" type="audio/mpeg">
                                            </audio>
                                        </template>
                                        <template x-if="!message?.media_url">
                                            <div class="flex items-center space-x-2 p-2 bg-white/10 rounded-lg">
                                                <i class="fas fa-microphone text-xl"></i>
                                                <span class="text-sm">Audio message</span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Video Message -->
                                <template x-if="message?.type === 'video'">
                                    <div class="max-w-[200px]">
                                        <template x-if="message?.media_url">
                                            <video controls class="rounded-lg w-full max-h-[150px]">
                                                <source :src="message?.media_url" type="video/mp4">
                                            </video>
                                        </template>
                                        <template x-if="!message?.media_url">
                                            <div class="bg-white/20 rounded-lg p-4 text-center">
                                                <i class="fas fa-video text-2xl mb-2"></i>
                                                <p class="text-xs">Video</p>
                                            </div>
                                        </template>
                                        <p x-show="message?.caption" class="text-sm mt-1" x-text="message?.caption"></p>
                                    </div>
                                </template>

                                <!-- Location Message -->
                                <template x-if="message?.type === 'location'">
                                    <div class="p-2 bg-white/10 rounded-lg">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <i class="fas fa-map-marker-alt text-xl"></i>
                                            <span class="font-medium text-sm">Location</span>
                                        </div>
                                        <p x-show="message?.location_name" class="text-sm" x-text="message?.location_name"></p>
                                        <p x-show="message?.location_address" class="text-xs opacity-80" x-text="message?.location_address"></p>
                                        <a x-show="message?.latitude && message?.longitude"
                                           :href="`https://maps.google.com/?q=${message?.latitude},${message?.longitude}`"
                                           target="_blank"
                                           class="text-xs underline mt-1 inline-block">
                                            Open in Maps
                                        </a>
                                    </div>
                                </template>

                                <!-- Interactive / Button Message -->
                                <template x-if="message?.type === 'interactive' && message?.buttons?.length > 0">
                                    <div>
                                        <p class="text-sm mb-2" x-text="message?.body"></p>
                                        <div class="space-y-1">
                                            <template x-for="btn in (message?.buttons || [])" :key="btn.id || btn.title">
                                                <div class="bg-white/20 rounded px-3 py-1.5 text-xs text-center">
                                                    <span x-text="btn.title || btn.reply?.title"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Interactive / List Message -->
                                <template x-if="message?.type === 'interactive' && message?.list_sections?.length > 0">
                                    <div>
                                        <p class="text-sm mb-2" x-text="message?.body"></p>
                                        <div class="bg-white/20 rounded-lg p-2 mt-2">
                                            <div class="flex items-center justify-center space-x-2 text-xs py-1">
                                                <i class="fas fa-list"></i>
                                                <span x-text="message?.button_text || 'View Options'"></span>
                                            </div>
                                            <template x-for="section in (message?.list_sections || [])" :key="section.title">
                                                <div class="mt-2 border-t border-white/20 pt-2">
                                                    <p class="text-xs font-semibold mb-1" x-text="section.title"></p>
                                                    <template x-for="row in (section.rows || [])" :key="row.id || row.title">
                                                        <div class="text-xs py-1 px-2 bg-white/10 rounded mb-1">
                                                            <span x-text="row.title"></span>
                                                            <p x-show="row.description" class="opacity-70 text-[10px]" x-text="row.description"></p>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Interactive fallback (no buttons or sections) -->
                                <template x-if="message?.type === 'interactive' && !message?.buttons?.length && !message?.list_sections?.length">
                                    <div>
                                        <p class="text-sm" x-text="message?.body || 'Interactive message'"></p>
                                    </div>
                                </template>

                                <!-- Contact Message -->
                                <template x-if="message?.type === 'contact' || message?.type === 'contacts'">
                                    <div class="flex items-center space-x-2 p-2 bg-white/10 rounded-lg">
                                        <i class="fas fa-address-book text-xl"></i>
                                        <div>
                                            <p class="text-sm font-medium">Contact Card</p>
                                            <p class="text-xs opacity-80" x-text="message?.body || 'Contact shared'"></p>
                                        </div>
                                    </div>
                                </template>

                                <!-- Sticker Message -->
                                <template x-if="message?.type === 'sticker'">
                                    <div class="max-w-[120px]">
                                        <template x-if="message?.media_url">
                                            <img :src="message?.media_url" class="w-full" alt="Sticker">
                                        </template>
                                        <template x-if="!message?.media_url">
                                            <div class="text-4xl text-center">🎭</div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Unknown/Other Message Type -->
                                <template x-if="!['text', 'template', 'image', 'document', 'audio', 'video', 'location', 'interactive', 'contact', 'contacts', 'sticker'].includes(message?.type)">
                                    <div class="text-sm">
                                        <i class="fas fa-comment mr-1"></i>
                                        <span x-text="message?.body || message?.content || message?.type + ' message'"></span>
                                    </div>
                                </template>
                            </div>

                            <!-- Message Meta -->
                            <div class="flex items-center justify-between mt-1 px-2">
                                <p class="text-xs text-gray-500" x-text="formatTime(message?.created_at)">-</p>
                                <div x-show="message?.direction === 'outgoing'" class="flex items-center space-x-1">
                                    <span class="text-xs capitalize"
                                        :class="{
                                            'text-gray-500': message?.status === 'sent',
                                            'text-blue-600': message?.status === 'delivered',
                                            'text-green-600': message?.status === 'read',
                                            'text-red-600': message?.status === 'failed'
                                        }"
                                        x-text="message?.status">-</span>
                                    <i class="text-xs"
                                    :class="{
                                        'fas fa-check text-gray-500': message?.status === 'sent',
                                        'fas fa-check-double text-blue-600': message?.status === 'delivered',
                                        'fas fa-check-double text-green-600': message?.status === 'read',
                                        'fas fa-exclamation-triangle text-red-600': message?.status === 'failed'
                                    }"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="messages.length === 0 && !loadingMessages" class="text-center py-12 text-gray-500">
                    <i class="fas fa-comments text-4xl mb-4"></i>
                    <p>No messages yet. Start a conversation!</p>
                </div>
                    </div>
                </template>
            </div>

            <!-- Message Input -->
            <div class="p-4 border-t border-gray-200 bg-white" :class="selectedTemplate ? 'pt-6' : ''">
                <form @submit.prevent="sendTextMessage" class="space-y-3">
                    <!-- Attachment Preview -->
                    <div x-show="attachmentPreview" class="flex items-center space-x-3 p-3 bg-gray-100 rounded-lg">
                        <div class="flex-shrink-0">
                            <template x-if="attachmentType === 'image'">
                                <img :src="attachmentPreview" class="w-16 h-16 object-cover rounded-lg">
                            </template>
                            <template x-if="attachmentType === 'video'">
                                <div class="w-16 h-16 bg-gray-300 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-video text-2xl text-gray-600"></i>
                                </div>
                            </template>
                            <template x-if="attachmentType === 'audio'">
                                <div class="w-16 h-16 bg-gray-300 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-music text-2xl text-gray-600"></i>
                                </div>
                            </template>
                            <template x-if="attachmentType === 'document'">
                                <div class="w-16 h-16 bg-gray-300 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-file text-2xl text-gray-600"></i>
                                </div>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate" x-text="attachmentName"></p>
                            <p class="text-xs text-gray-500" x-text="attachmentSize"></p>
                        </div>
                        <button type="button" @click="clearAttachment()" class="p-2 text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="flex items-end space-x-2">
                        <!-- Attachment Menu -->
                        <div class="relative" x-data="{ showAttachMenu: false }">
                            <button type="button" @click="showAttachMenu = !showAttachMenu" class="p-3 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg transition">
                                <i class="fas fa-plus text-xl"></i>
                            </button>

                            <!-- Attachment Dropdown Menu -->
                            <div x-show="showAttachMenu" @click.away="showAttachMenu = false" x-transition
                                 class="absolute bottom-full left-0 mb-2 w-56 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
                                <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Send Attachment</p>

                                <!-- Image -->
                                <button type="button" @click="$refs.imageInput.click(); showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-green-50 transition">
                                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-image text-green-600"></i>
                                    </div>
                                    <span class="text-gray-700">Image</span>
                                </button>

                                <!-- Video -->
                                <button type="button" @click="$refs.videoInput.click(); showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-purple-50 transition">
                                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-video text-purple-600"></i>
                                    </div>
                                    <span class="text-gray-700">Video</span>
                                </button>

                                <!-- Audio -->
                                <button type="button" @click="$refs.audioInput.click(); showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-orange-50 transition">
                                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-music text-orange-600"></i>
                                    </div>
                                    <span class="text-gray-700">Audio</span>
                                </button>

                                <!-- Document -->
                                <button type="button" @click="$refs.documentInput.click(); showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-blue-50 transition">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-file-alt text-blue-600"></i>
                                    </div>
                                    <span class="text-gray-700">Document</span>
                                </button>

                                <hr class="my-2">
                                <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Interactive</p>

                                <!-- Location -->
                                <button type="button" @click="showLocationModal = true; showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-red-50 transition">
                                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-map-marker-alt text-red-600"></i>
                                    </div>
                                    <span class="text-gray-700">Location</span>
                                </button>

                                <!-- Contact -->
                                <button type="button" @click="showContactModal = true; showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-cyan-50 transition">
                                    <div class="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-cyan-600"></i>
                                    </div>
                                    <span class="text-gray-700">Contact</span>
                                </button>

                                <!-- Button Message -->
                                <button type="button" @click="showButtonModal = true; showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-indigo-50 transition">
                                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-hand-pointer text-indigo-600"></i>
                                    </div>
                                    <span class="text-gray-700">Button Message</span>
                                </button>

                                <!-- List Message -->
                                <button type="button" @click="showListModal = true; showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-teal-50 transition">
                                    <div class="w-10 h-10 bg-teal-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-list text-teal-600"></i>
                                    </div>
                                    <span class="text-gray-700">List Message</span>
                                </button>

                                <hr class="my-2">

                                <!-- Template -->
                                <button type="button" @click="showTemplateModal = true; showAttachMenu = false"
                                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-yellow-50 transition">
                                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-file-code text-yellow-600"></i>
                                    </div>
                                    <span class="text-gray-700">Template</span>
                                </button>
                            </div>
                        </div>

                        <!-- Hidden File Inputs -->
                        <input type="file" x-ref="imageInput" @change="handleFileSelect($event, 'image')" accept="image/*" class="hidden">
                        <input type="file" x-ref="videoInput" @change="handleFileSelect($event, 'video')" accept="video/*" class="hidden">
                        <input type="file" x-ref="audioInput" @change="handleFileSelect($event, 'audio')" accept="audio/*" class="hidden">
                        <input type="file" x-ref="documentInput" @change="handleFileSelect($event, 'document')" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" class="hidden">

                        <!-- Message Input -->
                        <div class="flex-1 relative">
                            <!-- Template Indicator -->
                            <div x-show="selectedTemplate" class="absolute -top-10 left-0 right-0">
                                <div class="flex items-center justify-between bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-1.5">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-file-code text-yellow-600 text-sm"></i>
                                        <span class="text-sm text-yellow-800 font-medium">Template: </span>
                                        <span class="text-sm text-yellow-700" x-text="selectedTemplate?.name"></span>
                                    </div>
                                    <button type="button" @click="clearSelectedTemplate()" class="text-yellow-600 hover:text-yellow-800 p-1">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <textarea
                                x-model="newMessage"
                                @keydown.enter.prevent="$event.shiftKey ? (newMessage += '\n') : (selectedTemplate ? sendTemplate() : (attachmentFile ? sendMediaMessage() : sendTextMessage()))"
                                :placeholder="selectedTemplate ? 'Template message (preview only)' : (attachmentFile ? 'Add a caption (optional)...' : 'Type a message...')"
                                :readonly="selectedTemplate !== null"
                                rows="2"
                                class="w-full px-4 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none transition"
                                :class="selectedTemplate ? 'bg-yellow-50 border-yellow-300 text-yellow-800' : 'bg-white border-gray-300'"></textarea>
                        </div>

                        <!-- Send Button -->
                        <button
                            type="button"
                            @click="selectedTemplate ? sendTemplate() : (attachmentFile ? sendMediaMessage() : sendTextMessage())"
                            :disabled="(!newMessage.trim() && !attachmentFile && !selectedTemplate) || sending"
                            class="p-4 text-white rounded-xl transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
                            :class="selectedTemplate ? 'bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700' : 'bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700'">
                            <i class="fas" :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Empty State -->
        <div x-show="!selectedContact" class="flex-1 flex items-center justify-center bg-gray-50">
            <div class="text-center">
                <i class="fas fa-comment-dots text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Select a Contact</h3>
                <p class="text-gray-500">Choose a contact from the list to start messaging</p>
            </div>
        </div>
    </div>

    <!-- Template Modal -->
    <div x-show="showTemplateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showTemplateModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showTemplateModal = false"></div>

            <div class="relative bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden z-10">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-file-alt text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Select Template</h3>
                        </div>
                        <button @click="showTemplateModal = false" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2">
                        <template x-for="template in templates" :key="template.id">
                            <button type="button"
                                    @click="selectTemplate(template)"
                                    class="w-full text-left border rounded-xl p-4 cursor-pointer transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                    :class="selectedTemplate && selectedTemplate.id === template.id ? 'border-green-500 bg-green-50 ring-2 ring-green-500' : 'border-gray-200 hover:border-green-500 hover:bg-green-50'">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <h4 class="font-semibold text-gray-900" x-text="template.name"></h4>
                                        <i x-show="selectedTemplate && selectedTemplate.id === template.id" class="fas fa-check-circle text-green-500"></i>
                                    </div>
                                    <span class="text-xs px-3 py-1 rounded-full font-medium"
                                          :class="template.status === 'APPROVED' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                          x-text="template.status"></span>
                                </div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-2" x-text="template.category"></p>
                                <p class="text-sm text-gray-600 line-clamp-2" x-text="template.body || template.header || 'No preview available'"></p>
                            </button>
                        </template>

                        <div x-show="templates.length === 0" class="text-center py-12 text-gray-500">
                            <i class="fas fa-file-excel text-4xl text-gray-300 mb-3"></i>
                            <p class="font-medium">No approved templates available</p>
                            <p class="text-sm mt-1">Create templates in WhatsApp Business Manager</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Modal -->
    <div x-show="showLocationModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showLocationModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showLocationModal = false"></div>

            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-red-600 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Send Location</h3>
                        </div>
                        <button @click="showLocationModal = false" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form @submit.prevent="sendLocationMessage()" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Latitude *</label>
                                <input type="text" x-model="locationForm.latitude" placeholder="-6.200000"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitude *</label>
                                <input type="text" x-model="locationForm.longitude" placeholder="106.816666"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name (Optional)</label>
                            <input type="text" x-model="locationForm.name" placeholder="Location name"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address (Optional)</label>
                            <input type="text" x-model="locationForm.address" placeholder="Full address"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>

                        <button type="button" @click="getCurrentLocation()"
                                class="w-full flex items-center justify-center space-x-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-xl text-gray-600 hover:border-red-500 hover:text-red-600 transition">
                            <i class="fas fa-crosshairs"></i>
                            <span>Use Current Location</span>
                        </button>

                        <div class="flex space-x-3 pt-4">
                            <button type="button" @click="showLocationModal = false"
                                    class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!locationForm.latitude || !locationForm.longitude || sending"
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:from-red-600 hover:to-red-700 transition font-medium disabled:opacity-50">
                                <span x-show="!sending">Send Location</span>
                                <span x-show="sending"><i class="fas fa-spinner fa-spin mr-2"></i>Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Modal -->
    <div x-show="showContactModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showContactModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showContactModal = false"></div>

            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-cyan-600 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Send Contact</h3>
                        </div>
                        <button @click="showContactModal = false" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form @submit.prevent="sendContactMessage()" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                <input type="text" x-model="contactForm.firstName" placeholder="John"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" x-model="contactForm.lastName" placeholder="Doe"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                            <input type="text" x-model="contactForm.phone" placeholder="+628123456789"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email (Optional)</label>
                            <input type="email" x-model="contactForm.email" placeholder="john@example.com"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Organization (Optional)</label>
                            <input type="text" x-model="contactForm.org" placeholder="Company name"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                        </div>

                        <div class="flex space-x-3 pt-4">
                            <button type="button" @click="showContactModal = false"
                                    class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!contactForm.firstName || !contactForm.phone || sending"
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-cyan-500 to-cyan-600 text-white rounded-xl hover:from-cyan-600 hover:to-cyan-700 transition font-medium disabled:opacity-50">
                                <span x-show="!sending">Send Contact</span>
                                <span x-show="sending"><i class="fas fa-spinner fa-spin mr-2"></i>Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Button Message Modal -->
    <div x-show="showButtonModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showButtonModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showButtonModal = false"></div>

            <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-hand-pointer text-indigo-600 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Button Message</h3>
                        </div>
                        <button @click="showButtonModal = false" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form @submit.prevent="sendButtonMessage()" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Header (Optional)</label>
                            <input type="text" x-model="buttonForm.header" placeholder="Header text"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Body Text *</label>
                            <textarea x-model="buttonForm.body" rows="3" placeholder="Main message content"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Footer (Optional)</label>
                            <input type="text" x-model="buttonForm.footer" placeholder="Footer text"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Buttons (Max 3)</label>
                            <template x-for="(btn, index) in buttonForm.buttons" :key="index">
                                <div class="flex items-center space-x-2 mb-2">
                                    <input type="text" x-model="buttonForm.buttons[index]"
                                           :placeholder="'Button ' + (index + 1) + ' text'"
                                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    <button type="button" @click="buttonForm.buttons.splice(index, 1)" x-show="buttonForm.buttons.length > 1"
                                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="buttonForm.buttons.length < 3 && buttonForm.buttons.push('')"
                                    x-show="buttonForm.buttons.length < 3"
                                    class="w-full flex items-center justify-center space-x-2 px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-indigo-500 hover:text-indigo-500 transition">
                                <i class="fas fa-plus"></i>
                                <span>Add Button</span>
                            </button>
                        </div>

                        <div class="flex space-x-3 pt-4">
                            <button type="button" @click="showButtonModal = false"
                                    class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!buttonForm.body || buttonForm.buttons.filter(b => b.trim()).length === 0 || sending"
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-xl hover:from-indigo-600 hover:to-indigo-700 transition font-medium disabled:opacity-50">
                                <span x-show="!sending">Send Message</span>
                                <span x-show="sending"><i class="fas fa-spinner fa-spin mr-2"></i>Sending...</span>
                            </button>
                        </div>
                    </form>

                    <!-- Preview -->
                    <div class="mt-6 p-4 bg-gray-100 rounded-xl">
                        <p class="text-xs font-medium text-gray-500 mb-2">Preview</p>
                        <div class="bg-white rounded-lg shadow p-4">
                            <p x-show="buttonForm.header" class="font-semibold text-gray-900 mb-1" x-text="buttonForm.header"></p>
                            <p class="text-gray-700 text-sm mb-2" x-text="buttonForm.body || 'Your message here...'"></p>
                            <p x-show="buttonForm.footer" class="text-xs text-gray-500 mb-3" x-text="buttonForm.footer"></p>
                            <div class="space-y-2">
                                <template x-for="(btn, i) in buttonForm.buttons.filter(b => b.trim())" :key="i">
                                    <button class="w-full py-2 bg-gray-100 text-indigo-600 rounded-lg text-sm font-medium" x-text="btn"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- List Message Modal -->
    <div x-show="showListModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showListModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showListModal = false"></div>

            <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-teal-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-list text-teal-600 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">List Message</h3>
                        </div>
                        <button @click="showListModal = false" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form @submit.prevent="sendListMessage()" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Header (Optional)</label>
                            <input type="text" x-model="listForm.header" placeholder="Header text"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Body Text *</label>
                            <textarea x-model="listForm.body" rows="3" placeholder="Main message content"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Footer (Optional)</label>
                            <input type="text" x-model="listForm.footer" placeholder="Footer text"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Button Text *</label>
                            <input type="text" x-model="listForm.buttonText" placeholder="View Options"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sections</label>
                            <template x-for="(section, sIndex) in listForm.sections" :key="sIndex">
                                <div class="border border-gray-200 rounded-xl p-4 mb-3">
                                    <div class="flex items-center justify-between mb-3">
                                        <input type="text" x-model="section.title" placeholder="Section Title"
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm font-medium">
                                        <button type="button" @click="listForm.sections.splice(sIndex, 1)" x-show="listForm.sections.length > 1"
                                                class="ml-2 p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <template x-for="(row, rIndex) in section.rows" :key="rIndex">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <input type="text" x-model="row.title" placeholder="Item title"
                                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm">
                                            <input type="text" x-model="row.description" placeholder="Description (optional)"
                                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm">
                                            <button type="button" @click="section.rows.splice(rIndex, 1)" x-show="section.rows.length > 1"
                                                    class="p-2 text-red-400 hover:text-red-600 transition">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </template>
                                    <button type="button" @click="section.rows.push({ title: '', description: '' })"
                                            class="w-full flex items-center justify-center space-x-1 py-2 text-teal-600 hover:text-teal-700 text-sm">
                                        <i class="fas fa-plus"></i>
                                        <span>Add Row</span>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="listForm.sections.push({ title: '', rows: [{ title: '', description: '' }] })"
                                    class="w-full flex items-center justify-center space-x-2 px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-teal-500 hover:text-teal-500 transition">
                                <i class="fas fa-plus"></i>
                                <span>Add Section</span>
                            </button>
                        </div>

                        <div class="flex space-x-3 pt-4">
                            <button type="button" @click="showListModal = false"
                                    class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!listForm.body || !listForm.buttonText || sending"
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-teal-500 to-teal-600 text-white rounded-xl hover:from-teal-600 hover:to-teal-700 transition font-medium disabled:opacity-50">
                                <span x-show="!sending">Send Message</span>
                                <span x-show="sending"><i class="fas fa-spinner fa-spin mr-2"></i>Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Media Upload Progress Modal -->
    <div x-show="uploadingMedia" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
            <div class="relative bg-white rounded-2xl shadow-xl p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-cloud-upload-alt text-green-600 text-2xl fa-bounce"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Uploading Media</h3>
                <p class="text-gray-500">Please wait...</p>
                <div class="mt-4 w-48 mx-auto bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full animate-pulse" style="width: 60%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function messagesManager() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            contacts: [],
            filteredContactList: [],
            selectedContact: null,
            messages: [],
            templates: [],
            newMessage: '',
            contactSearch: '',
            loadingContacts: true,
            loadingMessages: false,
            sending: false,
            uploadingMedia: false,

            // Modals
            showTemplateModal: false,
            showLocationModal: false,
            showContactModal: false,
            showButtonModal: false,
            showListModal: false,

            // Selected template
            selectedTemplate: null,

            // Attachment state
            attachmentFile: null,
            attachmentPreview: null,
            attachmentType: null,
            attachmentName: '',
            attachmentSize: '',

            // Form data for modals
            locationForm: {
                latitude: '',
                longitude: '',
                name: '',
                address: ''
            },

            contactForm: {
                firstName: '',
                lastName: '',
                phone: '',
                email: '',
                org: ''
            },

            buttonForm: {
                header: '',
                body: '',
                footer: '',
                buttons: ['']
            },

            listForm: {
                header: '',
                body: '',
                footer: '',
                buttonText: 'View Options',
                sections: [
                    {
                        title: '',
                        rows: [{ title: '', description: '' }]
                    }
                ]
            },

            async init() {
                this.messages = [];
                await this.fetchContacts();
                await this.fetchTemplates();
                this.checkUrlParams();
                this.listenForUpdates();

                // Listen for new messages
                window.addEventListener('whatsapp-message-received', (event) => {
                    console.log('📩 Messages page - Received via custom event:', event.detail);
                    this.handleIncomingMessage(event.detail);
                });

                // Listen for status updates
                window.addEventListener('whatsapp-status-updated', (event) => {
                    console.log('📊 Messages page - Status update received:', event.detail);
                    this.handleStatusUpdate(event.detail);
                });
            },

            // Handle real-time status updates
            handleStatusUpdate(data) {
                console.log('📊 Handling status update:', data);

                // Find message by id (database id) or message_id (WhatsApp message id)
                const messageIndex = this.messages.findIndex(m =>
                    m && (m.id === data.id || m.message_id === data.message_id)
                );

                if (messageIndex !== -1) {
                    console.log('✅ Found message to update at index:', messageIndex);

                    // Update status
                    this.messages[messageIndex].status = data.status;

                    // Update timestamps
                    if (data.delivered_at) {
                        this.messages[messageIndex].delivered_at = data.delivered_at;
                    }
                    if (data.read_at) {
                        this.messages[messageIndex].read_at = data.read_at;
                    }

                    // Force Alpine to react to the change
                    this.messages = [...this.messages];

                    console.log('✅ Message status updated to:', data.status);

                    // Update contact's last message if this is the most recent message
                    if (this.selectedContact && this.selectedContact.id === data.contact_id) {
                        // Optionally refresh contacts to update badges
                        this.fetchContacts();
                    }
                } else {
                    console.log('⚠️ Message not found in current list, id:', data.id, 'message_id:', data.message_id);
                }
            },

            handleIncomingMessage(e) {
                console.log('🔔 Handling incoming message:', e);

                if (this.selectedContact && e.contact && e.contact.id === this.selectedContact.id) {
                    console.log('✅ Contact matches! Adding message to chat');

                    if (e.message && e.message.id) {
                        if (!this.messages.find(m => m && m.id === e.message.id)) {
                            this.messages.push(e.message);
                            this.scrollToBottom();
                            console.log('✅ Message added to chat, total messages:', this.messages.length);
                        }
                    }
                }
                this.fetchContacts();
            },

            async fetchContacts() {
                this.loadingContacts = true;
                try {
                    let token = localStorage.getItem('token');
                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    let data = await response.json();
                    this.contacts = data.data || [];
                    this.filteredContactList = this.contacts;
                } catch (error) {
                    console.error('Error fetching contacts:', error);
                } finally {
                    this.loadingContacts = false;
                }
            },

            async fetchTemplates() {
                try {
                    let token = localStorage.getItem('token');
                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    let data = await response.json();
                    this.templates = (data.data || []).filter(t => t.status === 'APPROVED');
                } catch (error) {
                    console.error('Error fetching templates:', error);
                }
            },

            checkUrlParams() {
                let urlParams = new URLSearchParams(window.location.search);
                let contactId = urlParams.get('contact');
                if (contactId) {
                    let contact = this.contacts.find(c => c.id == contactId);
                    if (contact) {
                        this.selectContact(contact);
                    }
                }
            },

            filterContactList() {
                if (!this.contactSearch) {
                    this.filteredContactList = this.contacts;
                    return;
                }

                let query = this.contactSearch.toLowerCase();
                this.filteredContactList = this.contacts.filter(contact =>
                    (contact.name && contact.name.toLowerCase().includes(query)) ||
                    (contact.phone_number && contact.phone_number.includes(query))
                );
            },

            async selectContact(contact) {
                this.selectedContact = contact;
                await this.fetchMessages();
                await this.markContactMessagesAsRead(contact.id);
                this.scrollToBottom();
            },

            async markContactMessagesAsRead(contactId) {
                try {
                    let token = localStorage.getItem('token');
                    await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/mark-read`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        }
                    });

                    let contactInList = this.contacts.find(c => c.id === contactId);
                    if (contactInList) contactInList.unread_count = 0;

                    let contactInFiltered = this.filteredContactList.find(c => c.id === contactId);
                    if (contactInFiltered) contactInFiltered.unread_count = 0;
                } catch (error) {
                    console.error('Error marking messages as read:', error);
                }
            },

            async fetchMessages() {
                if (!this.selectedContact) {
                    this.messages = [];
                    return;
                }

                this.loadingMessages = true;
                try {
                    let token = localStorage.getItem('token');
                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${this.selectedContact.id}/messages`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });

                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                    let data = await response.json();
                    let rawMessages = Array.isArray(data.data) ? data.data : [];
                    let validMessages = rawMessages.filter(m => m != null && m !== undefined && m.id);

                    let seenIds = new Set();
                    this.messages = validMessages.filter(m => {
                        if (seenIds.has(m.id)) return false;
                        seenIds.add(m.id);
                        return true;
                    });

                    this.scrollToBottom();
                } catch (error) {
                    console.error('Error fetching messages:', error);
                    this.messages = [];
                } finally {
                    this.loadingMessages = false;
                }
            },

            async refreshMessages() {
                await this.fetchMessages();
            },

            // File handling
            handleFileSelect(event, type) {
                let file = event.target.files[0];
                if (!file) return;

                this.attachmentFile = file;
                this.attachmentType = type;
                this.attachmentName = file.name;
                this.attachmentSize = this.formatFileSize(file.size);

                if (type === 'image') {
                    let reader = new FileReader();
                    reader.onload = (e) => {
                        this.attachmentPreview = e.target.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    this.attachmentPreview = type;
                }
            },

            clearAttachment() {
                this.attachmentFile = null;
                this.attachmentPreview = null;
                this.attachmentType = null;
                this.attachmentName = '';
                this.attachmentSize = '';
            },

            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                let k = 1024;
                let sizes = ['Bytes', 'KB', 'MB', 'GB'];
                let i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },

            // Send Text Message
            async sendTextMessage() {
                if (!this.newMessage.trim() || !this.selectedContact) return;

                this.sending = true;
                try {
                    let token = localStorage.getItem('token');
                    let payload = {
                        to: this.selectedContact.wa_id,
                        message: this.newMessage
                    };

                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/send/text`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.newMessage = '';
                        await this.fetchMessages();
                    } else {
                        let error = await response.json();
                        alert('Failed to send message: ' + (error.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    alert('Error sending message: ' + error.message);
                } finally {
                    this.sending = false;
                }
            },

            // Send Media Message (Image, Video, Audio, Document)
            async sendMediaMessage() {
                if (!this.attachmentFile || !this.selectedContact) return;

                this.sending = true;
                this.uploadingMedia = true;

                try {
                    let token = localStorage.getItem('token');
                    let formData = new FormData();
                    formData.append('to', this.selectedContact.wa_id);
                    formData.append('file', this.attachmentFile);
                    if (this.newMessage.trim()) {
                        formData.append('caption', this.newMessage);
                    }

                    let endpoint = '';
                    switch (this.attachmentType) {
                        case 'image': endpoint = '/whatsapp/send/image'; break;
                        case 'video': endpoint = '/whatsapp/send/video'; break;
                        case 'audio': endpoint = '/whatsapp/send/audio'; break;
                        case 'document': endpoint = '/whatsapp/send/document'; break;
                    }

                    let response = await fetch(`${this.API_BASE_URL}${endpoint}`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${token}` },
                        body: formData
                    });

                    if (response.ok) {
                        this.newMessage = '';
                        this.clearAttachment();
                        await this.fetchMessages();
                    } else {
                        let error = await response.json();
                        alert('Failed to send media: ' + (error.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending media:', error);
                    alert('Error sending media: ' + error.message);
                } finally {
                    this.sending = false;
                    this.uploadingMedia = false;
                }
            },

            // Send Location Message
            async sendLocationMessage() {
                if (!this.locationForm.latitude || !this.locationForm.longitude || !this.selectedContact) return;

                this.sending = true;
                try {
                    let token = localStorage.getItem('token');
                    let payload = {
                        to: this.selectedContact.wa_id,
                        latitude: parseFloat(this.locationForm.latitude),
                        longitude: parseFloat(this.locationForm.longitude),
                        name: this.locationForm.name || undefined,
                        address: this.locationForm.address || undefined
                    };

                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/send/location`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.showLocationModal = false;
                        this.resetLocationForm();
                        await this.fetchMessages();
                    } else {
                        let error = await response.json();
                        alert('Failed to send location: ' + (error.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending location:', error);
                    alert('Error sending location: ' + error.message);
                } finally {
                    this.sending = false;
                }
            },

            getCurrentLocation() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.locationForm.latitude = position.coords.latitude.toString();
                            this.locationForm.longitude = position.coords.longitude.toString();
                        },
                        (error) => {
                            alert('Unable to get location: ' + error.message);
                        }
                    );
                } else {
                    alert('Geolocation is not supported by this browser.');
                }
            },

            resetLocationForm() {
                this.locationForm = { latitude: '', longitude: '', name: '', address: '' };
            },

            // Send Contact Message
            async sendContactMessage() {
                if (!this.contactForm.firstName || !this.contactForm.phone || !this.selectedContact) return;

                this.sending = true;
                try {
                    let token = localStorage.getItem('token');
                    let payload = {
                        to: this.selectedContact.wa_id,
                        contacts: [{
                            name: {
                                formatted_name: `${this.contactForm.firstName} ${this.contactForm.lastName}`.trim(),
                                first_name: this.contactForm.firstName,
                                last_name: this.contactForm.lastName || undefined
                            },
                            phones: [{
                                phone: this.contactForm.phone,
                                type: 'CELL'
                            }],
                            emails: this.contactForm.email ? [{ email: this.contactForm.email, type: 'WORK' }] : undefined,
                            org: this.contactForm.org ? { company: this.contactForm.org } : undefined
                        }]
                    };

                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/send/contact`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.showContactModal = false;
                        this.resetContactForm();
                        await this.fetchMessages();
                    } else {
                        let error = await response.json();
                        alert('Failed to send contact: ' + (error.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending contact:', error);
                    alert('Error sending contact: ' + error.message);
                } finally {
                    this.sending = false;
                }
            },

            resetContactForm() {
                this.contactForm = { firstName: '', lastName: '', phone: '', email: '', org: '' };
            },

            // Send Button Message
            async sendButtonMessage() {
                if (!this.buttonForm.body || !this.selectedContact) return;

                let validButtons = this.buttonForm.buttons.filter(b => b.trim());
                if (validButtons.length === 0) return;

                this.sending = true;
                try {
                    let token = localStorage.getItem('token');
                    let payload = {
                        to: this.selectedContact.wa_id,
                        body: this.buttonForm.body,
                        buttons: validButtons.map((text, i) => ({
                            type: 'reply',
                            reply: {
                                id: `btn_${i + 1}`,
                                title: text.substring(0, 20)
                            }
                        })),
                        header: this.buttonForm.header || undefined,
                        footer: this.buttonForm.footer || undefined
                    };

                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/send/button`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.showButtonModal = false;
                        this.resetButtonForm();
                        await this.fetchMessages();
                    } else {
                        let error = await response.json();
                        alert('Failed to send button message: ' + (error.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending button message:', error);
                    alert('Error sending button message: ' + error.message);
                } finally {
                    this.sending = false;
                }
            },

            resetButtonForm() {
                this.buttonForm = { header: '', body: '', footer: '', buttons: [''] };
            },

            // Send List Message
            async sendListMessage() {
                if (!this.listForm.body || !this.listForm.buttonText || !this.selectedContact) return;

                this.sending = true;
                try {
                    let token = localStorage.getItem('token');
                    let sections = this.listForm.sections.map((section, sIndex) => ({
                        title: section.title || `Section ${sIndex + 1}`,
                        rows: section.rows.filter(r => r.title.trim()).map((row, rIndex) => ({
                            id: `row_${sIndex}_${rIndex}`,
                            title: row.title.substring(0, 24),
                            description: row.description ? row.description.substring(0, 72) : undefined
                        }))
                    })).filter(s => s.rows.length > 0);

                    let payload = {
                        to: this.selectedContact.wa_id || this.selectedContact.phone_number?.replace('+', ''),
                        body: this.listForm.body,
                        button_text: this.listForm.buttonText,
                        sections: sections,
                        header: this.listForm.header || undefined,
                        footer: this.listForm.footer || undefined
                    };

                    console.log('Sending list message:', payload);

                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/send/list`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    let data = await response.json();

                    if (response.ok && data.success) {
                        this.showListModal = false;
                        this.resetListForm();
                        await this.fetchMessages();
                    } else {
                        alert('Failed to send list message: ' + (data.message || data.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending list message:', error);
                    alert('Error sending list message: ' + error.message);
                } finally {
                    this.sending = false;
                }
            },

            resetListForm() {
                this.listForm = {
                    header: '',
                    body: '',
                    footer: '',
                    buttonText: 'View Options',
                    sections: [{ title: '', rows: [{ title: '', description: '' }] }]
                };
            },

            // Select Template (put into message input)
            selectTemplate(template) {
                this.selectedTemplate = template;
                this.newMessage = template.body || template.header || '';
                this.showTemplateModal = false;
            },

            // Clear selected template
            clearSelectedTemplate() {
                this.selectedTemplate = null;
                this.newMessage = '';
            },

            // Send Template
            async sendTemplate() {
                if (!this.selectedContact) {
                    alert('Please select a contact first');
                    return;
                }

                if (!this.selectedTemplate) {
                    return;
                }

                this.sending = true;
                try {
                    let token = localStorage.getItem('token');
                    let payload = {
                        to: this.selectedContact.wa_id,
                        template_name: this.selectedTemplate.name,
                        language: this.selectedTemplate.language
                    };

                    console.log('Sending template:', payload);

                    let response = await fetch(`${this.API_BASE_URL}/whatsapp/send/template`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.selectedTemplate = null;
                        this.newMessage = '';
                        await this.fetchMessages();
                    } else {
                        let error = await response.json();
                        alert('Failed to send template: ' + (error.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error sending template:', error);
                    alert('Error sending template: ' + error.message);
                } finally {
                    this.sending = false;
                }
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    let container = this.$refs.messagesContainer;
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
            },

            formatTime(timestamp) {
                let date = new Date(timestamp);
                let now = new Date();
                let isToday = date.toDateString() === now.toDateString();

                if (isToday) {
                    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                }
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            },

            listenForUpdates() {
                console.log('📡 Messages page - Setting up message listener');
                console.log('📡 Listening for: whatsapp-message-received, whatsapp-status-updated events');

                // Check if Echo is ready
                if (window.isEchoReady && window.isEchoReady()) {
                    console.log('✅ Echo is ready for real-time updates');
                } else {
                    console.log('⏳ Waiting for Echo to be ready...');
                    window.addEventListener('echo-ready', () => {
                        console.log('✅ Echo is now ready for real-time updates');
                    });
                }
            }
        }
    }
</script>
@endsection
