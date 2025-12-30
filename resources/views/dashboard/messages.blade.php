@extends('layouts.app')
@section('title', __('dashboard.messages_title'))
@section('content')
<div x-data="messagesApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'messages'])
    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => __('whatsapp.messages_title'), 'description' => __('whatsapp.messages_subtitle')])
        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto h-[calc(100vh-10rem)]" x-data="messagesManager()">
                <div class="card flex h-full overflow-hidden">
                    <!-- Contacts Sidebar -->
                    <div 
                        class="border-r border-[hsl(var(--border))] flex flex-col transition-all duration-300"
                        :class="isMobileMessages ? (mobileView === 'contacts' ? 'w-full' : 'hidden') : 'w-80 md:w-60 lg:w-80'"
                        x-show="!isMobileMessages || mobileView === 'contacts'"
                    >
                        <!-- Search -->
                        <div class="p-4 border-b border-[hsl(var(--border))]">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none"></i>
                                <input
                                    type="text"
                                    x-model="contactSearch"
                                    @input="filterContactList"
                                    placeholder="{{ __('whatsapp.search_contacts') }}"
                                    class="input pl-10 w-full h-9 text-sm"
                                >
                            </div>
                        </div>
                        <!-- Contacts List -->
                        <div class="flex-1 overflow-y-auto scroll-area">
                            <!-- Loading -->
                            <template x-if="loadingContacts">
                                <div class="p-2 space-y-1">
                                    <template x-for="i in 8" :key="'contact-skeleton-'+i">
                                        <div class="flex items-center gap-3 p-3 rounded-lg">
                                            <div class="skeleton h-10 w-10 rounded-full"></div>
                                            <div class="flex-1 space-y-2">
                                                <div class="skeleton h-3 w-24"></div>
                                                <div class="skeleton h-2 w-32"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <!-- Contacts -->
                            <template x-if="!loadingContacts">
                                <div class="p-2 space-y-1">
                                    <template x-for="contact in filteredContactList" :key="contact.id">
                                        <div
                                            @click="selectContact(contact)"
                                            class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors"
                                            :class="selectedContact?.id === contact.id ? 'bg-[hsl(var(--primary)/0.1)]' : 'hover:bg-[hsl(var(--muted))]'"
                                        >
                                            <div class="relative flex-shrink-0">
                                                <!-- Avatar with name (use DiceBear) -->
                                                <img
                                                    x-show="contact.name && contact.name.trim()"
                                                    :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(contact.name || 'U')}&backgroundColor=a855f7`"
                                                    :alt="contact.name"
                                                    class="avatar"
                                                >
                                                <!-- Avatar without name (show country code) -->
                                                <div
                                                    x-show="!contact.name || !contact.name.trim()"
                                                    class="avatar flex items-center justify-center text-white font-bold"
                                                    style="background: linear-gradient(135deg, #a855f7, #9333ea); font-size: 0.75rem;"
                                                >
                                                    <span x-text="getCountryCode(contact.phone_number) || '?'"></span>
                                                </div>
                                                <!-- Unread Badge -->
                                                <span x-show="contact.unread_count > 0" class="notification-badge text-[10px]" x-text="contact.unread_count">0</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm font-medium truncate" x-text="contact.name || contact.phone_number">Unknown</p>
                                                    <p class="text-[10px] text-[hsl(var(--muted-foreground))]" x-text="formatTime(contact.last_message_at)">-</p>
                                                </div>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))] truncate mt-0.5" :class="{'font-medium': contact.unread_count > 0}" x-text="contact.last_message_text || 'No messages'">-</p>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Empty -->
                                    <div x-show="filteredContactList.length === 0" class="empty-state py-12">
                                        <div class="empty-state-icon h-12 w-12">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]">No contacts found</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <!-- Chat Area -->
                    <div 
                        class="flex-1 flex flex-col" 
                        x-show="selectedContact && (!isMobileMessages || mobileView === 'chat')"
                        :class="isMobileMessages ? 'w-full' : ''"
                    >
                        <!-- Chat Header -->
                        <div class="h-14 md:h-16 px-3 md:px-4 border-b border-[hsl(var(--border))] flex items-center justify-between bg-[hsl(var(--muted)/0.3)]">
                            <div class="flex items-center gap-2 md:gap-3">
                                <!-- Back Button (Mobile Only) - Requirements 4.2, 4.3 -->
                                <button 
                                    x-show="isMobileMessages" 
                                    @click="backToContacts()" 
                                    class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"
                                    title="Back to contacts"
                                >
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <!-- Avatar with name -->
                                <img
                                    x-show="selectedContact?.name && selectedContact.name.trim()"
                                    :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(selectedContact?.name || 'U')}&backgroundColor=a855f7`"
                                    :alt="selectedContact?.name"
                                    class="avatar h-9 w-9 md:h-10 md:w-10"
                                >
                                <!-- Avatar without name -->
                                <div
                                    x-show="!selectedContact?.name || !selectedContact.name.trim()"
                                    class="avatar h-9 w-9 md:h-10 md:w-10 flex items-center justify-center text-white font-bold"
                                    style="background: linear-gradient(135deg, #a855f7, #9333ea); font-size: 0.75rem;"
                                >
                                    <span x-text="getCountryCode(selectedContact?.phone_number) || '?'"></span>
                                </div>
                                <div>
                                    <p class="font-medium text-xs md:text-sm" x-text="selectedContact?.name || 'Unknown'">Unknown</p>
                                    <p class="text-[10px] md:text-xs text-[hsl(var(--muted-foreground))]" x-text="selectedContact?.phone_number">-</p>
                                </div>
                            </div>
                            <button @click="refreshMessages" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]">
                                <i class="fas fa-sync-alt" :class="{'animate-spin': loadingMessages}"></i>
                            </button>
                        </div>
                        <!-- Messages -->
                        <div class="flex-1 overflow-y-auto scroll-area p-4 space-y-3 bg-[hsl(var(--muted)/0.2)]" x-ref="messagesContainer">
                            <!-- Loading -->
                            <template x-if="loadingMessages">
                                <div class="space-y-3">
                                    <template x-for="i in 5" :key="'msg-skeleton-'+i">
                                        <div class="flex" :class="i % 2 === 0 ? 'justify-end' : 'justify-start'">
                                            <div class="skeleton h-16 w-48 rounded-lg"></div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <!-- Messages List -->
                            <template x-if="!loadingMessages">
                                <div class="space-y-3">
                                    <template x-for="(message, index) in messages" :key="message?.id || `msg-${index}`">
                                        <div x-show="message && message.id" class="flex" :class="message?.direction === 'outgoing' ? 'justify-end' : 'justify-start'">
                                            <!-- Message bubble max-width: 85% on mobile, 70% on desktop (Requirements 4.5) -->
                                            <div class="max-w-[85%] md:max-w-[70%]">
                                                <!-- Message Bubble - Adjusted padding for mobile (Requirements 4.5) -->
                                                <div
                                                    class="rounded-2xl px-3 py-1.5 md:px-4 md:py-2 shadow-sm"
                                                    :class="message?.direction === 'outgoing' ? 'bg-[hsl(var(--primary))] text-white rounded-br-md' : 'bg-white border border-[hsl(var(--border))] rounded-bl-md'"
                                                >
                                                    <!-- Text -->
                                                    <div x-show="message?.type === 'text'">
                                                        <p class="text-sm whitespace-pre-wrap" x-html="formatWhatsAppText(message?.content || message?.body)"></p>
                                                    </div>
                                                    <!-- Template -->
                                                    <div x-show="message?.type === 'template'" class="text-sm">
                                                        <div class="flex items-center gap-2 mb-1 opacity-80">
                                                            <i class="fas fa-file-alt text-xs"></i>
                                                            <span class="text-xs font-medium">Template</span>
                                                        </div>
                                                        <p class="whitespace-pre-wrap" x-html="formatWhatsAppText(message?.body || 'Template message')"></p>
                                                    </div>
                                                    <!-- Image -->
                                                    <div x-show="message?.type === 'image'">
                                                        <a x-show="message?.media_url" :href="message?.media_url" target="_blank">
                                                            <img :src="message?.media_url" class="rounded-lg max-w-[200px] max-h-[150px] object-cover" alt="Image">
                                                        </a>
                                                        <div x-show="!message?.media_url" class="flex items-center gap-2 py-2">
                                                            <i class="fas fa-image"></i>
                                                            <span class="text-sm">Image</span>
                                                        </div>
                                                        <p x-show="message?.caption" class="text-sm mt-2" x-text="message?.caption"></p>
                                                    </div>
                                                    <!-- Video -->
                                                    <div x-show="message?.type === 'video'">
                                                        <div x-show="message?.media_url" class="max-w-[220px]">
                                                            <video controls class="rounded-lg w-full max-h-[150px]">
                                                                <source :src="message?.media_url" type="video/mp4">
                                                            </video>
                                                        </div>
                                                        <div x-show="!message?.media_url" class="flex items-center gap-2 py-2">
                                                            <i class="fas fa-video"></i>
                                                            <span class="text-sm">Video</span>
                                                        </div>
                                                        <p x-show="message?.caption" class="text-sm mt-2" x-text="message?.caption"></p>
                                                    </div>
                                                    <!-- Document -->
                                                    <div x-show="message?.type === 'document'" class="flex items-center gap-3">
                                                        <div class="h-10 w-10 rounded-lg flex items-center justify-center" :class="message?.direction === 'outgoing' ? 'bg-white/20' : 'bg-[hsl(var(--muted))]'">
                                                            <i class="fas fa-file-alt"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium truncate" x-text="message?.filename || 'Document'"></p>
                                                            <a x-show="message?.media_url" :href="message?.media_url" target="_blank" class="text-xs underline opacity-80">Download</a>
                                                        </div>
                                                    </div>
                                                    <!-- Audio -->
                                                    <div x-show="message?.type === 'audio'">
                                                        <template x-if="message?.media_url">
                                                            <audio controls class="h-10 max-w-[200px]" :src="message?.media_url"></audio>
                                                        </template>
                                                        <div x-show="!message?.media_url" class="flex items-center gap-2">
                                                            <i class="fas fa-microphone"></i>
                                                            <span class="text-sm">Audio message</span>
                                                        </div>
                                                    </div>
                                                    <!-- Location -->
                                                    <div x-show="message?.type === 'location'">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            <span class="text-sm font-medium">Location</span>
                                                        </div>
                                                        <p x-show="message?.location_name" class="text-sm" x-text="message?.location_name"></p>
                                                        <a x-show="message?.latitude && message?.longitude" :href="`https://maps.google.com/?q=${message?.latitude},${message?.longitude}`" target="_blank" class="text-xs underline">Open in Maps</a>
                                                    </div>
                                                    <!-- Interactive - Buttons -->
                                                    <div x-show="message?.type === 'interactive'">
                                                        <p x-show="message?.body" class="text-sm mb-2" x-text="message?.body"></p>
                                                        <!-- Button Reply -->
                                                        <div x-show="message?.buttons && message?.buttons.length > 0" class="space-y-1 mt-2">
                                                            <template x-for="btn in (message?.buttons || [])" :key="btn.id || btn.title">
                                                                <div class="text-xs py-1.5 px-3 rounded text-center" :class="message?.direction === 'outgoing' ? 'bg-white/20' : 'bg-[hsl(var(--muted))]'">
                                                                    <span x-text="btn.title || btn.reply?.title"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                        <!-- List -->
                                                        <div x-show="message?.list_sections && message?.list_sections.length > 0" class="mt-2 rounded p-2" :class="message?.direction === 'outgoing' ? 'bg-white/20' : 'bg-[hsl(var(--muted))]'">
                                                            <div class="flex items-center justify-center gap-2 text-xs py-1">
                                                                <i class="fas fa-list"></i>
                                                                <span x-text="message?.button_text || 'View Options'"></span>
                                                            </div>
                                                        </div>
                                                        <!-- Fallback if no buttons/list -->
                                                        <p x-show="!message?.buttons?.length && !message?.list_sections?.length && !message?.body" class="text-sm">Interactive message</p>
                                                    </div>
                                                    <!-- Sticker -->
                                                    <div x-show="message?.type === 'sticker'">
                                                        <img x-show="message?.media_url" :src="message?.media_url" class="w-24 h-24" alt="Sticker">
                                                        <div x-show="!message?.media_url" class="text-4xl text-center">🎭</div>
                                                    </div>
                                                    <!-- Contacts -->
                                                    <div x-show="message?.type === 'contacts' || message?.type === 'contact'" class="flex items-center gap-2">
                                                        <i class="fas fa-address-book"></i>
                                                        <div>
                                                            <p class="text-sm font-medium">Contact Card</p>
                                                            <p class="text-xs opacity-80" x-text="message?.body || 'Contact shared'"></p>
                                                        </div>
                                                    </div>
                                                    <!-- Reaction -->
                                                    <div x-show="message?.type === 'reaction'" class="text-2xl" x-text="message?.emoji || message?.body || '👍'"></div>
                                                    <!-- Unknown/Other -->
                                                    <div x-show="!['text', 'template', 'image', 'video', 'document', 'audio', 'location', 'interactive', 'sticker', 'contacts', 'contact', 'reaction'].includes(message?.type)">
                                                        <p class="text-sm" x-text="message?.body || message?.content || (message?.type ? message.type + ' message' : 'Message')"></p>
                                                    </div>
                                                </div>
                                                <!-- Meta -->
                                                <div class="flex items-center justify-between mt-1 px-1">
                                                    <p class="text-[10px] text-[hsl(var(--muted-foreground))]" x-text="formatTime(message?.created_at)">-</p>
                                                    <div x-show="message?.direction === 'outgoing'" class="flex items-center gap-1">
                                                        <i class="text-[10px]"
                                                            :class="{
                                                                'fas fa-check text-[hsl(var(--muted-foreground))]': message?.status === 'sent',
                                                                'fas fa-check-double text-blue-500': message?.status === 'delivered',
                                                                'fas fa-check-double text-emerald-500': message?.status === 'read',
                                                                'fas fa-exclamation-triangle text-red-500': message?.status === 'failed'
                                                            }"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Empty -->
                                    <div x-show="messages.length === 0" class="empty-state py-16">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-comments text-xl"></i>
                                        </div>
                                        <p class="text-sm font-medium mt-2">No messages yet</p>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]">Start a conversation!</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <!-- Input -->
                        <!-- Input Area - Adjusted for mobile touch targets (Requirements 6.2, 6.3) -->
                        <div class="p-3 md:p-4 border-t border-[hsl(var(--border))] bg-white sticky bottom-0">
                            <form @submit.prevent="sendMessage" class="flex items-end gap-2">
                                <!-- Attachment Menu - All options shown with touch-friendly sizing -->
                                <div x-data="{ showMenu: false }" class="relative">
                                    <button type="button" @click="showMenu = !showMenu" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]" title="Attach">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <div x-show="showMenu" @click.away="showMenu = false" x-transition class="absolute bottom-12 left-0 bg-white rounded-lg shadow-lg border border-[hsl(var(--border))] py-2 w-48 z-10 max-h-[60vh] overflow-y-auto">
                                        <button type="button" @click="showMenu = false; showTemplateModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-file-alt w-5 text-purple-500"></i> Template
                                        </button>
                                        <button type="button" @click="showMenu = false; showImageModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-image w-5 text-blue-500"></i> Image
                                        </button>
                                        <button type="button" @click="showMenu = false; showVideoModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-video w-5 text-pink-500"></i> Video
                                        </button>
                                        <button type="button" @click="showMenu = false; showDocumentModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-file w-5 text-orange-500"></i> Document
                                        </button>
                                        <button type="button" @click="showMenu = false; showAudioModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-microphone w-5 text-yellow-500"></i> Audio
                                        </button>
                                        <button type="button" @click="showMenu = false; showLocationModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-map-marker-alt w-5 text-red-500"></i> Location
                                        </button>
                                        <button type="button" @click="showMenu = false; showButtonModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-hand-pointer w-5 text-green-500"></i> Buttons
                                        </button>
                                        <button type="button" @click="showMenu = false; showListModal = true" class="w-full flex items-center gap-3 px-4 py-3 md:py-2 hover:bg-[hsl(var(--muted))] text-sm min-h-[44px]">
                                            <i class="fas fa-list w-5 text-cyan-500"></i> List
                                        </button>
                                    </div>
                                </div>
                                <div class="flex-1 relative">
                                    <!-- Template Indicator -->
                                    <div x-show="isTemplateMessage" x-cloak class="absolute -top-8 left-0 right-0 flex items-center justify-between px-2 py-1 bg-purple-50 border border-purple-200 rounded-t-lg text-xs">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-file-alt text-purple-600"></i>
                                            <span class="text-purple-700 font-medium">Template: <span x-text="selectedTemplateInfo?.name"></span></span>
                                        </div>
                                        <button type="button" @click="cancelTemplate()" class="text-purple-600 hover:text-purple-800">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <!-- Formatting Toolbar -->
                                    <div
                                        x-show="showFormatBar"
                                        x-cloak
                                        :style="`top: ${formatBarPosition.top}px; left: ${formatBarPosition.left}px;`"
                                        class="absolute z-50 bg-[hsl(var(--card))] rounded-lg shadow-lg border border-[hsl(var(--border))] flex items-center gap-1 p-1"
                                        @click.away="showFormatBar = false"
                                    >
                                        <button type="button" @click="formatText('bold')" class="btn btn-ghost btn-icon btn-sm" title="Bold (Ctrl+B)">
                                            <i class="fas fa-bold"></i>
                                        </button>
                                        <button type="button" @click="formatText('italic')" class="btn btn-ghost btn-icon btn-sm" title="Italic (Ctrl+I)">
                                            <i class="fas fa-italic"></i>
                                        </button>
                                        <button type="button" @click="formatText('strikethrough')" class="btn btn-ghost btn-icon btn-sm" title="Strikethrough">
                                            <i class="fas fa-strikethrough"></i>
                                        </button>
                                        <button type="button" @click="formatText('code')" class="btn btn-ghost btn-icon btn-sm" title="Code">
                                            <i class="fas fa-code"></i>
                                        </button>
                                        <div class="w-px h-6 bg-[hsl(var(--border))]"></div>
                                        <button type="button" @click="formatText('bullet')" class="btn btn-ghost btn-icon btn-sm" title="Bullet List">
                                            <i class="fas fa-list-ul"></i>
                                        </button>
                                        <button type="button" @click="formatText('numbered')" class="btn btn-ghost btn-icon btn-sm" title="Numbered List">
                                            <i class="fas fa-list-ol"></i>
                                        </button>
                                        <button type="button" @click="formatText('quote')" class="btn btn-ghost btn-icon btn-sm" title="Quote">
                                            <i class="fas fa-quote-right"></i>
                                        </button>
                                    </div>
                                    <!-- Textarea with minimum 44px touch target (Requirements 6.3) -->
                                    <textarea
                                        x-ref="messageInput"
                                        x-model="newMessage"
                                        @input="autoResizeTextarea($event.target)"
                                        @keydown.enter="handleEnterKey($event)"
                                        @keydown.ctrl.b.prevent="formatText('bold')"
                                        @keydown.ctrl.i.prevent="formatText('italic')"
                                        @select="handleTextSelect($event)"
                                        @mouseup="handleTextSelect($event)"
                                        placeholder="Type a message..."
                                        rows="1"
                                        class="input w-full resize-none py-2 md:py-2 overflow-hidden text-base md:text-sm"
                                        style="min-height: 44px; max-height: 200px;"
                                        :disabled="sending"
                                    ></textarea>
                                </div>
                                <!-- Send button with minimum 44px touch target (Requirements 6.3) -->
                                <button type="submit" class="btn btn-primary btn-icon min-h-[44px] min-w-[44px]" :disabled="sending || !newMessage.trim()">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- No Contact Selected - Hidden on mobile when contacts view is active -->
                    <div x-show="!selectedContact && !isMobileMessages" class="flex-1 flex items-center justify-center bg-[hsl(var(--muted)/0.2)]">
                        <div class="text-center">
                            <div class="h-16 w-16 rounded-full bg-[hsl(var(--muted))] flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-comments text-2xl text-[hsl(var(--muted-foreground))]"></i>
                            </div>
                            <h3 class="font-medium">Select a conversation</h3>
                            <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Choose a contact to start messaging</p>
                        </div>
                    </div>
                </div>
                <!-- Template Modal -->
                <div x-show="showTemplateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showTemplateModal = false"></div>
                    <div class="card relative w-full max-w-lg max-h-[85vh] overflow-hidden">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Select Template</h3>
                            <button @click="showTemplateModal = false" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>

                        <!-- Template List -->
                        <div class="p-4 max-h-96 overflow-y-auto scroll-area space-y-2">
                            <template x-for="tpl in templates" :key="tpl.id">
                                <div @click="selectTemplate(tpl)" class="p-3 border border-[hsl(var(--border))] rounded-lg hover:bg-[hsl(var(--muted))] cursor-pointer transition-colors">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1">
                                            <p class="font-medium text-sm" x-text="tpl.name"></p>
                                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1 line-clamp-2" x-text="tpl.body"></p>
                                        </div>
                                        <i class="fas fa-chevron-right text-[hsl(var(--muted-foreground))] text-xs mt-1"></i>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span class="text-[10px] px-2 py-0.5 rounded bg-[hsl(var(--muted))]" x-text="tpl.language || 'en'"></span>
                                        <span class="text-[10px] px-2 py-0.5 rounded" :class="tpl.status === 'APPROVED' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'" x-text="tpl.status"></span>
                                    </div>
                                </div>
                            </template>
                            <div x-show="templates.length === 0" class="empty-state py-12">
                                <div class="empty-state-icon"><i class="fas fa-file-alt"></i></div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">No templates available</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Image Modal -->
                <div x-show="showImageModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showImageModal = false; imageForm = { file: null, caption: '', preview: null }"></div>
                    <div class="card relative w-full max-w-md">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Send Image</h3>
                            <button @click="showImageModal = false; imageForm = { file: null, caption: '', preview: null }" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendImage" class="p-4 space-y-4">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Select Image</label>
                                <input type="file" @change="handleImageSelect" accept="image/jpeg,image/jpg,image/png" class="input w-full p-2" required>
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Supported: JPG, PNG (max 5MB)</p>
                            </div>
                            <div x-show="imageForm.preview" class="rounded-lg overflow-hidden border border-[hsl(var(--border))]">
                                <img :src="imageForm.preview" class="w-full max-h-48 object-contain bg-[hsl(var(--muted))]" alt="Preview">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Caption (optional)</label>
                                <input type="text" x-model="imageForm.caption" class="input w-full" placeholder="Image caption">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showImageModal = false; imageForm = { file: null, caption: '', preview: null }" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending || !imageForm.file" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Video Modal -->
                <div x-show="showVideoModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showVideoModal = false; videoForm = { file: null, caption: '', preview: null }"></div>
                    <div class="card relative w-full max-w-md">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Send Video</h3>
                            <button @click="showVideoModal = false; videoForm = { file: null, caption: '', preview: null }" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendVideo" class="p-4 space-y-4">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Select Video</label>
                                <input type="file" @change="handleVideoSelect" accept="video/mp4,video/3gpp" class="input w-full p-2" required>
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Supported: MP4, 3GP (max 16MB)</p>
                            </div>
                            <div x-show="videoForm.preview" class="rounded-lg overflow-hidden border border-[hsl(var(--border))]">
                                <video :src="videoForm.preview" controls class="w-full max-h-48 bg-[hsl(var(--muted))]"></video>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Caption (optional)</label>
                                <input type="text" x-model="videoForm.caption" class="input w-full" placeholder="Video caption">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showVideoModal = false; videoForm = { file: null, caption: '', preview: null }" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending || !videoForm.file" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Document Modal -->
                <div x-show="showDocumentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showDocumentModal = false; documentForm = { file: null, filename: '', caption: '' }"></div>
                    <div class="card relative w-full max-w-md">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Send Document</h3>
                            <button @click="showDocumentModal = false; documentForm = { file: null, filename: '', caption: '' }" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendDocument" class="p-4 space-y-4">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Select Document</label>
                                <input type="file" @change="handleDocumentSelect" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" class="input w-full p-2" required>
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Supported: PDF, DOC, XLS, PPT, TXT (max 100MB)</p>
                            </div>
                            <div x-show="documentForm.file" class="p-3 bg-[hsl(var(--muted))] rounded-lg flex items-center gap-3">
                                <i class="fas fa-file-alt text-xl text-[hsl(var(--primary))]"></i>
                                <span class="text-sm truncate" x-text="documentForm.filename"></span>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Caption (optional)</label>
                                <input type="text" x-model="documentForm.caption" class="input w-full" placeholder="Document caption">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showDocumentModal = false; documentForm = { file: null, filename: '', caption: '' }" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending || !documentForm.file" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Audio Modal -->
                <div x-show="showAudioModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showAudioModal = false; audioForm = { file: null, filename: '', preview: null }"></div>
                    <div class="card relative w-full max-w-md">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Send Audio</h3>
                            <button @click="showAudioModal = false; audioForm = { file: null, filename: '', preview: null }" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendAudio" class="p-4 space-y-4">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Select Audio File</label>
                                <input type="file" @change="handleAudioSelect" accept="audio/*,.mp3,.wav,.ogg,.m4a,.aac" class="input w-full p-2" required>
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Supported: MP3, WAV, OGG, M4A, AAC (max 16MB)</p>
                            </div>
                            <div x-show="audioForm.file" class="p-3 bg-[hsl(var(--muted))] rounded-lg">
                                <div class="flex items-center gap-3 mb-2">
                                    <i class="fas fa-music text-xl text-[hsl(var(--primary))]"></i>
                                    <span class="text-sm truncate" x-text="audioForm.filename"></span>
                                </div>
                                <template x-if="audioForm.preview">
                                    <audio :src="audioForm.preview" controls class="w-full h-10"></audio>
                                </template>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showAudioModal = false; audioForm = { file: null, filename: '', preview: null }" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending || !audioForm.file" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Location Modal -->
                <div x-show="showLocationModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showLocationModal = false; locationForm = { latitude: '', longitude: '', name: '', address: '', mapUrl: '' }"></div>
                    <div class="card relative w-full max-w-lg">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Send Location</h3>
                            <button @click="showLocationModal = false; locationForm = { latitude: '', longitude: '', name: '', address: '', mapUrl: '' }" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendLocation" class="p-4 space-y-4">
                            <!-- Search Location -->
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Search Location</label>
                                <div class="relative">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm"></i>
                                    <input type="text" x-model="locationForm.searchQuery" @input.debounce.500ms="searchLocation" class="input w-full pl-9" placeholder="Search for a place...">
                                </div>
                                <!-- Search Results -->
                                <div x-show="locationForm.searchResults && locationForm.searchResults.length > 0" class="mt-2 border border-[hsl(var(--border))] rounded-lg max-h-40 overflow-y-auto">
                                    <template x-for="result in locationForm.searchResults" :key="result.place_id">
                                        <div @click="selectSearchResult(result)" class="p-2 hover:bg-[hsl(var(--muted))] cursor-pointer border-b border-[hsl(var(--border))] last:border-b-0">
                                            <p class="text-sm font-medium" x-text="result.name"></p>
                                            <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="result.formatted_address"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <!-- Get Current Location Button -->
                            <div class="flex gap-2">
                                <button type="button" @click="getCurrentLocation" class="btn btn-outline btn-sm flex-1" :disabled="locationForm.gettingLocation">
                                    <i class="fas" :class="locationForm.gettingLocation ? 'fa-spinner animate-spin' : 'fa-crosshairs'"></i>
                                    <span x-text="locationForm.gettingLocation ? 'Getting...' : 'Use My Location'"></span>
                                </button>
                                <a x-show="locationForm.latitude && locationForm.longitude" :href="`https://www.google.com/maps?q=${locationForm.latitude},${locationForm.longitude}`" target="_blank" class="btn btn-outline btn-sm">
                                    <i class="fas fa-external-link-alt"></i> View Map
                                </a>
                            </div>
                            <!-- Map Preview -->
                            <div x-show="locationForm.latitude && locationForm.longitude" class="rounded-lg overflow-hidden border border-[hsl(var(--border))]">
                                <iframe
                                    :src="`https://www.google.com/maps/embed/v1/place?key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8&q=${locationForm.latitude},${locationForm.longitude}&zoom=15`"
                                    width="100%"
                                    height="200"
                                    style="border:0;"
                                    allowfullscreen=""
                                    loading="lazy">
                                </iframe>
                            </div>
                            <!-- Coordinates (readonly, auto-filled) -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium mb-1.5 block">Latitude</label>
                                    <input type="number" step="any" x-model="locationForm.latitude" class="input w-full bg-[hsl(var(--muted))]" placeholder="-6.2088" readonly required>
                                </div>
                                <div>
                                    <label class="text-sm font-medium mb-1.5 block">Longitude</label>
                                    <input type="number" step="any" x-model="locationForm.longitude" class="input w-full bg-[hsl(var(--muted))]" placeholder="106.8456" readonly required>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Location Name</label>
                                <input type="text" x-model="locationForm.name" class="input w-full" placeholder="Location name">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Address</label>
                                <input type="text" x-model="locationForm.address" class="input w-full" placeholder="Full address">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showLocationModal = false; locationForm = { latitude: '', longitude: '', name: '', address: '', mapUrl: '' }" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending || !locationForm.latitude || !locationForm.longitude" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Button Message Modal -->
                <div x-show="showButtonModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showButtonModal = false"></div>
                    <div class="card relative w-full max-w-md">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Send Button Message</h3>
                            <button @click="showButtonModal = false" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendButtonMessage" class="p-4 space-y-4">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Body Text</label>
                                <textarea x-model="buttonForm.body" class="input w-full" rows="2" placeholder="Message body" required></textarea>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Buttons (max 3)</label>
                                <template x-for="(btn, idx) in buttonForm.buttons" :key="idx">
                                    <div class="flex gap-2 mb-2">
                                        <input type="text" x-model="buttonForm.buttons[idx]" class="input flex-1" :placeholder="'Button ' + (idx+1)">
                                        <button type="button" x-show="buttonForm.buttons.length > 1" @click="buttonForm.buttons.splice(idx, 1)" class="btn btn-ghost btn-icon text-red-500"><i class="fas fa-trash"></i></button>
                                    </div>
                                </template>
                                <button type="button" x-show="buttonForm.buttons.length < 3" @click="buttonForm.buttons.push('')" class="btn btn-outline btn-sm w-full"><i class="fas fa-plus mr-2"></i>Add Button</button>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showButtonModal = false" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- List Message Modal -->
                <div x-show="showListModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showListModal = false"></div>
                    <div class="card relative w-full max-w-md max-h-[90vh] overflow-y-auto">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between sticky top-0 bg-white">
                            <h3 class="font-semibold">Send List Message</h3>
                            <button @click="showListModal = false" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendListMessage" class="p-4 space-y-4">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Body Text</label>
                                <textarea x-model="listForm.body" class="input w-full" rows="2" placeholder="Message body" required></textarea>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Button Text</label>
                                <input type="text" x-model="listForm.buttonText" class="input w-full" placeholder="View Options" required>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Section Title</label>
                                <input type="text" x-model="listForm.sectionTitle" class="input w-full" placeholder="Options">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Items</label>
                                <template x-for="(item, idx) in listForm.items" :key="idx">
                                    <div class="flex gap-2 mb-2">
                                        <input type="text" x-model="listForm.items[idx].title" class="input flex-1" placeholder="Item title">
                                        <button type="button" x-show="listForm.items.length > 1" @click="listForm.items.splice(idx, 1)" class="btn btn-ghost btn-icon text-red-500"><i class="fas fa-trash"></i></button>
                                    </div>
                                </template>
                                <button type="button" x-show="listForm.items.length < 10" @click="listForm.items.push({title: '', description: ''})" class="btn btn-outline btn-sm w-full"><i class="fas fa-plus mr-2"></i>Add Item</button>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="showListModal = false" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script>
function messagesApp() {
    return {
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],
        init() {
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
            let storedUser = localStorage.getItem('user');
            if (storedUser) {
                try { this.user = JSON.parse(storedUser); }
                catch (e) { this.user = { name: 'User', email: 'user@example.com' }; }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; }
            }
        },
        addNotification(notif) {
            notif.id = Date.now() + Math.random();
            this.notifications.unshift(notif);
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
        formatNotificationTime(timestamp) {
            let date = new Date(timestamp);
            let diff = Math.floor((new Date() - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return date.toLocaleDateString();
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
function messagesManager() {
    return {
        API_BASE_URL: window.location.origin + '/api',
        contacts: [], filteredContactList: [], selectedContact: null, messages: [], templates: [],
        newMessage: '', contactSearch: '', loadingContacts: true, loadingMessages: false, sending: false,
        mediaPreview: null, mediaFile: null,
        showTemplateModal: false, showImageModal: false, showVideoModal: false, showDocumentModal: false,
        showLocationModal: false, showButtonModal: false, showListModal: false, showAudioModal: false,
        showFormatBar: false, formatBarPosition: { top: 0, left: 0 },
        isTemplateMessage: false, selectedTemplateInfo: null,
        imageForm: { file: null, caption: '', preview: null },
        videoForm: { file: null, caption: '', preview: null },
        documentForm: { file: null, filename: '', caption: '' },
        audioForm: { file: null, filename: '', preview: null },
        locationForm: { latitude: '', longitude: '', name: '', address: '', searchQuery: '', searchResults: [], gettingLocation: false },
        buttonForm: { body: '', buttons: ['', ''] },
        listForm: { body: '', buttonText: 'View Options', sectionTitle: 'Options', items: [{title: ''}, {title: ''}] },
        // Mobile view state management (Requirements 4.1, 4.4)
        mobileView: 'contacts', // 'contacts' | 'chat'
        isMobileMessages: window.innerWidth < 768,
        async init() {
            // Initialize mobile detection
            this.isMobileMessages = window.innerWidth < 768;
            
            // Add resize listener for viewport detection
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const wasMobile = this.isMobileMessages;
                    this.isMobileMessages = window.innerWidth < 768;
                    // Reset to contacts view when switching from mobile to desktop
                    if (wasMobile && !this.isMobileMessages) {
                        this.mobileView = 'contacts';
                    }
                }, 150);
            });
            
            await Promise.all([this.fetchContacts(), this.fetchTemplates()]);
            const urlParams = new URLSearchParams(window.location.search);
            const contactId = urlParams.get('contact');
            if (contactId) { const contact = this.contacts.find(c => c.id == contactId); if (contact) this.selectContact(contact); }

            // Listen for real-time messages from broadcast
            window.addEventListener('whatsapp-message-received', (e) => {
                const newMessage = e.detail.message;
                const messageContact = e.detail.contact;

                if (this.selectedContact && messageContact?.id === this.selectedContact.id && newMessage) {
                    // Check if message already exists to prevent duplicates
                    const exists = this.messages.some(m => m.id === newMessage.id || m.message_id === newMessage.message_id);
                    if (!exists) {
                        this.messages.push(newMessage);
                        // Scroll to bottom with smooth animation for new messages
                        this.$nextTick(() => setTimeout(() => this.scrollToBottom(true), 50));
                        // Mark incoming message as read since user is viewing
                        if (newMessage.direction === 'incoming') {
                            this.markContactAsRead(messageContact.id);
                        }
                    }
                }
                // Update contacts list silently
                this.updateContactsList();
            });

            // Listen for message status updates (delivered, read)
            window.addEventListener('whatsapp-status-updated', (e) => {
                console.log('📊 Status update received:', e.detail);
                const { id, message_id, status } = e.detail;
                // Find message by id or message_id
                const msg = this.messages.find(m => m.id === id || m.message_id === message_id);
                if (msg) {
                    console.log('✅ Updating message status:', msg.id, '->', status);
                    msg.status = status;
                }
            });
        },
        // Fetch contacts with loading indicator (initial load only)
        async fetchContacts() {
            this.loadingContacts = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.contacts = data.data || [];
                this.filterContactList();
            } catch (e) { console.error('Error:', e); }
            finally { this.loadingContacts = false; }
        },

        // Update contacts list silently (no loading indicator)
        async updateContactsList() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.contacts = data.data || [];
                this.filterContactList();
            } catch (e) { console.error('Error:', e); }
        },
        async fetchTemplates() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.templates = (data.data || []).filter(t => t.status === 'APPROVED');
            } catch (e) { console.error('Error:', e); }
        },
        filterContactList() {
            if (!this.contactSearch) { this.filteredContactList = this.contacts; }
            else {
                const q = this.contactSearch.toLowerCase();
                this.filteredContactList = this.contacts.filter(c => (c.name && c.name.toLowerCase().includes(q)) || (c.phone_number && c.phone_number.includes(q)));
            }
        },
        async selectContact(contact) {
            this.messages = [];
            this.selectedContact = contact;
            // Switch to chat view on mobile (Requirements 4.2, 4.4)
            if (this.isMobileMessages) {
                this.mobileView = 'chat';
            }
            await this.fetchMessages();
            // Mark messages as read when opening conversation
            if (contact.unread_count > 0) {
                await this.markContactAsRead(contact.id);
            }
        },
        // Back to contacts list on mobile (Requirements 4.3)
        backToContacts() {
            this.mobileView = 'contacts';
        },
        async fetchMessages() {
            if (!this.selectedContact?.id) return;
            this.loadingMessages = true; this.messages = [];
            try {
                const token = localStorage.getItem('token');
                const contactId = this.selectedContact.id;
                // Use the correct endpoint that filters by contact_id
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/messages`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                if (this.selectedContact?.id === contactId) {
                    this.messages = data.data || [];
                    // Wait for DOM to render then scroll to bottom (latest messages)
                    await this.$nextTick();
                    setTimeout(() => this.scrollToBottom(), 100);
                }
            } catch (e) { console.error('Error:', e); }
            finally { this.loadingMessages = false; }
        },
        async refreshMessages() { await this.fetchMessages(); },

        // Mark all messages from contact as read
        async markContactAsRead(contactId) {
            try {
                const token = localStorage.getItem('token');
                await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/mark-read`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' }
                });
                // Update local contact unread count
                if (this.selectedContact?.id === contactId) {
                    this.selectedContact.unread_count = 0;
                }
                // Update in contacts list
                const contact = this.contacts.find(c => c.id === contactId);
                if (contact) contact.unread_count = 0;
            } catch (e) { console.error('Error marking as read:', e); }
        },
        selectTemplate(tpl) {
            // Insert template body into the message textarea
            this.newMessage = tpl.body || '';
            this.isTemplateMessage = true;
            this.selectedTemplateInfo = {
                name: tpl.name,
                language: tpl.language || 'en',
                originalBody: tpl.body
            };
            this.showTemplateModal = false;
            // Focus on the textarea so user can edit
            this.$nextTick(() => {
                const textarea = this.$refs.messageInput;
                if (textarea) {
                    textarea.focus();
                    // Move cursor to end
                    textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                    // Auto resize after inserting template
                    this.autoResizeTextarea(textarea);
                }
            });
        },
        cancelTemplate() {
            // Cancel template - clear everything and don't send any message
            this.isTemplateMessage = false;
            this.selectedTemplateInfo = null;
            this.newMessage = '';
            // Reset textarea height
            this.$nextTick(() => {
                const textarea = this.$refs.messageInput;
                if (textarea) textarea.style.height = '40px';
            });
        },
        autoResizeTextarea(textarea) {
            if (!textarea) return;
            // Reset height to auto to get the correct scrollHeight
            textarea.style.height = 'auto';
            // Set height based on content, respecting min and max
            const newHeight = Math.min(Math.max(textarea.scrollHeight, 40), 200);
            textarea.style.height = newHeight + 'px';
        },
        handleEnterKey(event) {
            if (event.shiftKey) {
                // Shift+Enter: allow new line (default behavior)
                return;
            } else {
                // Enter only: send message
                event.preventDefault();
                this.sendMessage();
            }
        },
        handleTextSelect(event) {
            const textarea = event.target;
            const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);

            if (selectedText.length > 0) {
                // Show formatting toolbar
                const rect = textarea.getBoundingClientRect();

                // Calculate approximate position (above the textarea)
                this.showFormatBar = true;
                this.formatBarPosition = {
                    top: -45,
                    left: Math.min(rect.width / 2 - 150, rect.width - 320)
                };
            } else {
                // Hide formatting toolbar
                this.showFormatBar = false;
            }
        },
        formatText(type) {
            const textarea = this.$refs.messageInput;
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);

            if (!selectedText) return;
            let formattedText = '';
            let cursorOffset = 0;
            switch(type) {
                case 'bold':
                    formattedText = `*${selectedText}*`;
                    cursorOffset = 1;
                    break;
                case 'italic':
                    formattedText = `_${selectedText}_`;
                    cursorOffset = 1;
                    break;
                case 'strikethrough':
                    formattedText = `~${selectedText}~`;
                    cursorOffset = 1;
                    break;
                case 'code':
                    formattedText = `\`\`\`${selectedText}\`\`\``;
                    cursorOffset = 3;
                    break;
                case 'bullet':
                    formattedText = selectedText.split('\n').map(line => `• ${line}`).join('\n');
                    cursorOffset = 2;
                    break;
                case 'numbered':
                    formattedText = selectedText.split('\n').map((line, i) => `${i + 1}. ${line}`).join('\n');
                    cursorOffset = 3;
                    break;
                case 'quote':
                    formattedText = selectedText.split('\n').map(line => `> ${line}`).join('\n');
                    cursorOffset = 2;
                    break;
            }
            // Replace selected text with formatted text
            this.newMessage = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);

            // Restore focus and selection
            this.$nextTick(() => {
                textarea.focus();
                textarea.setSelectionRange(start + cursorOffset, start + formattedText.length - cursorOffset);
                this.autoResizeTextarea(textarea);
            });
        },
        formatWhatsAppText(text) {
            if (!text) return '';

            // Escape HTML first
            let formatted = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            // Bold: *text*
            formatted = formatted.replace(/\*([^\*]+)\*/g, '<strong>$1</strong>');

            // Italic: _text_
            formatted = formatted.replace(/_([^_]+)_/g, '<em>$1</em>');

            // Strikethrough: ~text~
            formatted = formatted.replace(/~([^~]+)~/g, '<del>$1</del>');

            // Code: ```text```
            formatted = formatted.replace(/```([^`]+)```/g, '<code class="bg-black/10 px-1 py-0.5 rounded text-xs font-mono">$1</code>');

            // Quote: > text (at start of line)
            formatted = formatted.replace(/^&gt; (.+)$/gm, '<div class="border-l-2 border-current pl-2 opacity-80">$1</div>');

            return formatted;
        },
        getCountryCode(phoneNumber) {
            if (!phoneNumber) {
                console.log('getCountryCode: no phone number');
                return '?';
            }
            console.log('getCountryCode input:', phoneNumber);
            // Extract country code (e.g., +62 from +6281234567890)
            const match = phoneNumber.match(/^\+(\d{1,3})/);
            if (match) {
                console.log('getCountryCode match:', '+' + match[1]);
                return '+' + match[1];
            }
            // If no + prefix, try to extract first 2-3 digits
            const digits = phoneNumber.match(/^(\d{2,3})/);
            if (digits) {
                console.log('getCountryCode digits:', digits[1]);
                return digits[1];
            }
            console.log('getCountryCode fallback: ?');
            return '?';
        },
        async sendMessage() {
            if (!this.newMessage.trim() || !this.selectedContact || this.sending) return;
            this.sending = true;
            const messageText = this.newMessage;
            const isTemplate = this.isTemplateMessage;
            const templateInfo = this.selectedTemplateInfo;

            // Clear input immediately for better UX
            this.newMessage = '';
            this.isTemplateMessage = false;
            this.selectedTemplateInfo = null;

            // Reset textarea height
            this.$nextTick(() => {
                const textarea = this.$refs.messageInput;
                if (textarea) textarea.style.height = '40px';
            });

            try {
                const token = localStorage.getItem('token');
                let payload, endpoint;

                if (isTemplate && templateInfo) {
                    // Check if template body was edited
                    const isEdited = messageText !== templateInfo.originalBody;

                    if (isEdited) {
                        // Template was edited - send as regular text message instead
                        // because WhatsApp templates cannot be modified after approval
                        payload = {
                            to: this.selectedContact.phone_number,
                            message: messageText
                        };
                        endpoint = '/whatsapp/send/text';
                    } else {
                        // Template not edited - send as original template
                        payload = {
                            to: this.selectedContact.phone_number,
                            template_name: templateInfo.name,
                            language: templateInfo.language
                        };
                        endpoint = '/whatsapp/send/template';
                    }
                } else {
                    // Send as regular text message
                    payload = {
                        to: this.selectedContact.phone_number,
                        message: messageText
                    };
                    endpoint = '/whatsapp/send/text';
                }

                const res = await fetch(`${this.API_BASE_URL}${endpoint}`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!data.success) {
                    alert(data.message || 'Failed to send');
                    // Restore message if failed
                    this.newMessage = messageText;
                    this.isTemplateMessage = isTemplate;
                    this.selectedTemplateInfo = templateInfo;
                }
                // Message will be added via broadcast event - no need to add locally
            } catch (e) {
                console.error('Error:', e);
                // Restore message if failed
                this.newMessage = messageText;
                this.isTemplateMessage = isTemplate;
                this.selectedTemplateInfo = templateInfo;
            }
            finally { this.sending = false; }
        },

        // Handle image file selection
        handleImageSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.imageForm.file = file;
                this.imageForm.preview = URL.createObjectURL(file);
            }
        },

        // Handle video file selection
        handleVideoSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.videoForm.file = file;
                this.videoForm.preview = URL.createObjectURL(file);
            }
        },

        // Handle document file selection
        handleDocumentSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.documentForm.file = file;
                this.documentForm.filename = file.name;
            }
        },
        async sendImage() {
            if (!this.imageForm.file || !this.selectedContact) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const formData = new FormData();
                formData.append('to', this.selectedContact.phone_number);
                formData.append('file', this.imageForm.file);
                if (this.imageForm.caption) formData.append('caption', this.imageForm.caption);

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send/image`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.showImageModal = false;
                    this.imageForm = { file: null, caption: '', preview: null };
                    // Message will be added via broadcast event
                }
                else alert(data.message || 'Failed to send');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        async sendVideo() {
            if (!this.videoForm.file || !this.selectedContact) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const formData = new FormData();
                formData.append('to', this.selectedContact.phone_number);
                formData.append('file', this.videoForm.file);
                if (this.videoForm.caption) formData.append('caption', this.videoForm.caption);

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send/video`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.showVideoModal = false;
                    this.videoForm = { file: null, caption: '', preview: null };
                    // Message will be added via broadcast event
                }
                else alert(data.message || 'Failed to send video');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        async sendDocument() {
            if (!this.documentForm.file || !this.selectedContact) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const formData = new FormData();
                formData.append('to', this.selectedContact.phone_number);
                formData.append('file', this.documentForm.file);
                formData.append('filename', this.documentForm.filename);
                if (this.documentForm.caption) formData.append('caption', this.documentForm.caption);

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send/document`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.showDocumentModal = false;
                    this.documentForm = { file: null, filename: '', caption: '' };
                    // Message will be added via broadcast event
                }
                else alert(data.message || 'Failed to send');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        // Handle audio file selection
        handleAudioSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.audioForm.file = file;
                this.audioForm.filename = file.name;
                this.audioForm.preview = URL.createObjectURL(file);
            }
        },
        async sendAudio() {
            if (!this.audioForm.file || !this.selectedContact) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const formData = new FormData();
                formData.append('to', this.selectedContact.phone_number);
                formData.append('file', this.audioForm.file);

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send/audio`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.showAudioModal = false;
                    this.audioForm = { file: null, filename: '', preview: null };
                    // Message will be added via broadcast event
                }
                else alert(data.message || 'Failed to send');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        // Get current location using browser geolocation
        getCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser');
                return;
            }
            this.locationForm.gettingLocation = true;
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    this.locationForm.latitude = position.coords.latitude;
                    this.locationForm.longitude = position.coords.longitude;
                    // Try to get address from coordinates
                    await this.reverseGeocode(position.coords.latitude, position.coords.longitude);
                    this.locationForm.gettingLocation = false;
                },
                (error) => {
                    console.error('Geolocation error:', error);
                    alert('Unable to get your location. Please search for a location instead.');
                    this.locationForm.gettingLocation = false;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        },
        // Search location using Google Places API (via Nominatim as free alternative)
        async searchLocation() {
            if (!this.locationForm.searchQuery || this.locationForm.searchQuery.length < 3) {
                this.locationForm.searchResults = [];
                return;
            }
            try {
                const query = encodeURIComponent(this.locationForm.searchQuery);
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}&limit=5`);
                const data = await res.json();
                this.locationForm.searchResults = data.map(item => ({
                    place_id: item.place_id,
                    name: item.display_name.split(',')[0],
                    formatted_address: item.display_name,
                    lat: parseFloat(item.lat),
                    lon: parseFloat(item.lon)
                }));
            } catch (e) {
                console.error('Search error:', e);
                this.locationForm.searchResults = [];
            }
        },
        // Select a search result
        selectSearchResult(result) {
            this.locationForm.latitude = result.lat;
            this.locationForm.longitude = result.lon;
            this.locationForm.name = result.name;
            this.locationForm.address = result.formatted_address;
            this.locationForm.searchResults = [];
            this.locationForm.searchQuery = '';
        },
        // Reverse geocode to get address from coordinates
        async reverseGeocode(lat, lon) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
                const data = await res.json();
                if (data.display_name) {
                    this.locationForm.name = data.name || data.display_name.split(',')[0];
                    this.locationForm.address = data.display_name;
                }
            } catch (e) { console.error('Reverse geocode error:', e); }
        },
        async sendLocation() {
            if (!this.locationForm.latitude || !this.locationForm.longitude || !this.selectedContact) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send/location`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        to: this.selectedContact.phone_number,
                        latitude: parseFloat(this.locationForm.latitude),
                        longitude: parseFloat(this.locationForm.longitude),
                        name: this.locationForm.name,
                        address: this.locationForm.address
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.showLocationModal = false;
                    this.locationForm = { latitude: '', longitude: '', name: '', address: '', searchQuery: '', searchResults: [], gettingLocation: false };
                    // Message will be added via broadcast event
                }
                else alert(data.message || 'Failed to send');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        async sendButtonMessage() {
            if (!this.buttonForm.body || !this.selectedContact) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const buttons = this.buttonForm.buttons.filter(b => b.trim()).map((b, i) => ({ id: `btn_${i}`, title: b }));
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send/button`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        to: this.selectedContact.phone_number,
                        body: this.buttonForm.body,
                        buttons: buttons
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.showButtonModal = false;
                    this.buttonForm = { body: '', buttons: ['', ''] };
                    // Message will be added via broadcast event
                }
                else alert(data.message || 'Failed to send');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        async sendListMessage() {
            if (!this.listForm.body || !this.selectedContact) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const rows = this.listForm.items.filter(i => i.title.trim()).map((i, idx) => ({ id: `item_${idx}`, title: i.title, description: i.description || '' }));
                const sections = [{ title: this.listForm.sectionTitle, rows: rows }];
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send/list`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        to: this.selectedContact.phone_number,
                        body: this.listForm.body,
                        button_text: this.listForm.buttonText,
                        sections: sections
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.showListModal = false;
                    this.listForm = { body: '', buttonText: 'View Options', sectionTitle: 'Options', items: [{title: ''}, {title: ''}] };
                    // Message will be added via broadcast event
                }
                else alert(data.message || 'Failed to send');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        scrollToBottom(smooth = false) {
            const c = this.$refs.messagesContainer;
            if (c) {
                if (smooth) {
                    c.scrollTo({ top: c.scrollHeight, behavior: 'smooth' });
                } else {
                    c.scrollTop = c.scrollHeight;
                }
            }
        },
        formatTime(timestamp) {
            if (!timestamp) return '-';
            const date = new Date(timestamp), now = new Date(), diff = now - date, hours = Math.floor(diff / 3600000);
            if (hours < 24) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            if (hours < 48) return 'Yesterday';
            return date.toLocaleDateString();
        }
    }
}
</script>
@endsection
