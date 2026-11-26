@extends('layouts.app')
@include('components.dashboard-scripts')
@section('title', __('dashboard.messages_title'))
@section('content')
@vite('resources/js/chat-markdown.js')
<div x-data="messagesApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'messages'])
    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => __('whatsapp.messages_title'), 'description' => __('whatsapp.messages_subtitle')])
        <!-- Page Content -->
        <main class="flex-1 overflow-hidden flex flex-col">
            <div class="flex-1 min-h-0 flex flex-col gap-3 p-3 md:p-4" x-data="messagesManager()" @visibilitychange.document="syncLiveMessages()">
                <section class="card flex flex-col gap-4 p-3 shadow-sm sm:p-4 xl:flex-row xl:items-center xl:justify-between" aria-label="Ekspor riwayat pesan">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5M12 15V3"></path></svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-[hsl(var(--foreground))]">Ekspor riwayat pesan</h2>
                            <p class="text-xs text-[hsl(var(--muted-foreground))]">Unduh semua percakapan atau gunakan rentang tanggal.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-end">
                        <label class="min-w-0 text-xs font-medium text-[hsl(var(--muted-foreground))]">Dari tanggal
                            <input type="date" x-ref="exportFrom" class="input mt-1 h-10 w-full min-w-0 rounded-lg px-3 text-sm sm:w-40">
                        </label>
                        <label class="min-w-0 text-xs font-medium text-[hsl(var(--muted-foreground))]">Sampai tanggal
                            <input type="date" x-ref="exportTo" class="input mt-1 h-10 w-full min-w-0 rounded-lg px-3 text-sm sm:w-40">
                        </label>
                        <button type="button" class="btn btn-primary col-span-2 min-h-10 justify-center gap-2 whitespace-nowrap px-4 sm:col-span-1" @click="window.qashierExportMessages(null, 'csv', $refs.exportFrom.value, $refs.exportTo.value)">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5M12 15V3"></path></svg>
                            <span>Unduh semua</span>
                        </button>
                        <button type="button" class="col-span-2 inline-flex min-h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-medium text-emerald-800 transition hover:bg-emerald-100 sm:col-span-1" @click="window.qashierExportMessages(null, 'xlsx', $refs.exportFrom.value, $refs.exportTo.value)">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6M8 13l5 6m0-6-5 6"></path></svg>
                            <span>Ekspor Excel</span>
                        </button>
                    </div>
                </section>
                <div class="card flex min-h-0 flex-1 overflow-hidden">
                    <!-- Contacts Sidebar -->
                    <div
                        class="border-r border-[hsl(var(--border))] flex flex-col transition-all duration-300"
                        :class="isMobileMessages ? (mobileView === 'contacts' ? 'w-full' : 'hidden') : 'w-80 md:w-60 lg:w-80'"
                        x-show="!isMobileMessages || mobileView === 'contacts'"
                    >
                        <!-- Search -->
                        <div class="p-4 pb-2 border-b border-[hsl(var(--border))]">
                            <div class="relative">
                                <svg aria-hidden="true" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[hsl(var(--muted-foreground))] pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                                <input
                                    type="text"
                                    x-model="contactSearch"
                                    @input.debounce.300ms="fetchContacts()"
                                    placeholder="{{ __('whatsapp.search_contacts') }}"
                                    class="input w-full"
                                    style="padding-left: 2.5rem;"
                                >
                            </div>
                            <!-- Tag Filter Bar -->
                            <div x-show="allTags.length > 0" class="flex items-center gap-1.5 mt-2 overflow-x-auto pb-1 scroll-area" style="scrollbar-width: thin;">
                                <button
                                    @click="showTagManageModal = true"
                                    class="flex-shrink-0 h-6 w-6 rounded-full flex items-center justify-center bg-[hsl(var(--muted))] hover:bg-[hsl(var(--muted-foreground)/0.2)] transition-colors"
                                    title="Manage Tags"
                                >
                                    <i class="fas fa-cog text-[10px] text-[hsl(var(--muted-foreground))]"></i>
                                </button>
                                <template x-for="tag in allTags" :key="'filter-'+tag.id">
                                    <button
                                        @click="filterByTag(tag.id)"
                                        class="flex-shrink-0 text-[10px] font-medium px-2 py-0.5 rounded-full transition-all duration-200 cursor-pointer border"
                                        :style="`
                                            background: ${selectedFilterTag == tag.id ? tag.color : tagBgColor(tag.color)};
                                            color: ${selectedFilterTag == tag.id ? '#fff' : tag.color};
                                            border-color: ${tag.color};
                                        `"
                                        x-text="tag.name"
                                    ></button>
                                </template>
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
                                    <template x-for="contact in contacts" :key="contact.id">
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
                                                <!-- Contact Tags -->
                                                <div x-show="contact.tags && contact.tags.length > 0" class="flex items-center gap-1 mt-1 flex-wrap">
                                                    <template x-for="tag in (contact.tags || []).slice(0, 3)" :key="'ctag-'+contact.id+'-'+tag.id">
                                                        <span
                                                            class="text-[9px] font-medium px-1.5 py-0 rounded-full leading-4"
                                                            :style="`background: ${tagBgColor(tag.color)}; color: ${tag.color};`"
                                                            x-text="tag.name"
                                                        ></span>
                                                    </template>
                                                    <span x-show="(contact.tags || []).length > 3" class="text-[9px] text-[hsl(var(--muted-foreground))]" x-text="'+' + ((contact.tags || []).length - 3)"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Empty -->
                                    <div x-show="contacts.length === 0" class="empty-state py-12">
                                        <div class="empty-state-icon h-12 w-12">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]">No contacts found</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="contactsLastPage > 1" class="flex items-center justify-between gap-2 border-t p-2">
                            <button type="button" class="btn btn-ghost" :disabled="loadingContacts || contactsPage <= 1" @click="fetchContacts(contactsPage - 1)">
                                {{ __('common.previous') }}
                            </button>
                            <span class="text-xs" x-text="contactsPage + ' / ' + contactsLastPage"></span>
                            <button type="button" class="btn btn-ghost" :disabled="loadingContacts || contactsPage >= contactsLastPage" @click="fetchContacts(contactsPage + 1)">
                                {{ __('common.next') }}
                            </button>
                        </div>
                    </div>
                    <!-- Chat Area -->
                    <div
                        class="flex-1 flex flex-col"
                        x-show="selectedContact && (!isMobileMessages || mobileView === 'chat')"
                        :class="isMobileMessages ? 'w-full' : ''"
                    >
                        <!-- Chat Header -->
                        <p x-cloak x-show="readErrors[selectedContact?.id]" role="status" class="px-4 py-2 text-sm text-red-600">
                            {{ __('whatsapp.error_mark_read') }}
                        </p>
                        <div class="px-3 md:px-4 border-b border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)]">
                            <div class="h-14 md:h-16 flex items-center justify-between">
                                <div class="flex items-center gap-2 md:gap-3 flex-1 min-w-0">
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
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-xs md:text-sm" x-text="selectedContact?.name || 'Unknown'">Unknown</p>
                                        <p class="text-[10px] md:text-xs text-[hsl(var(--muted-foreground))]" x-text="selectedContact?.phone_number">-</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <button type="button" class="btn btn-outline btn-sm min-h-9 gap-1.5 px-2.5" title="Unduh percakapan sebagai CSV" aria-label="Unduh percakapan sebagai CSV" @click="window.qashierExportMessages(selectedContact.id, 'csv', $refs.exportFrom.value, $refs.exportTo.value)">
                                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5M12 15V3"></path></svg>
                                            <span class="hidden sm:inline">CSV</span>
                                        </button>
                                        <button type="button" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-sm font-medium text-emerald-800 transition hover:bg-emerald-100" title="Unduh percakapan sebagai Excel" aria-label="Unduh percakapan sebagai Excel" @click="window.qashierExportMessages(selectedContact.id, 'xlsx', $refs.exportFrom.value, $refs.exportTo.value)">
                                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6M8 13l5 6m0-6-5 6"></path></svg>
                                            <span class="hidden sm:inline">Excel</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <!-- AI Toggle -->
                                    <div
                                        x-show="selectedContact"
                                        class="flex items-center gap-1.5 px-2"
                                        :title="selectedContact?.ai_active !== false ? 'AI Aktif - Klik untuk nonaktifkan' : 'AI Nonaktif - Klik untuk aktifkan'"
                                    >
                                        <span class="text-xs hidden md:inline" :class="selectedContact?.ai_active !== false ? 'text-purple-600' : 'text-gray-400'">AI</span>
                                        <button
                                            @click="toggleContactAi(selectedContact)"
                                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none"
                                            :class="selectedContact?.ai_active !== false ? 'bg-purple-600' : 'bg-gray-300'"
                                        >
                                            <span
                                                class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                :class="selectedContact?.ai_active !== false ? 'translate-x-4' : 'translate-x-1'"
                                            ></span>
                                        </button>
                                    </div>
                                    <!-- Tag Assign Button -->
                                    <div class="relative">
                                        <button @click="showTagAssignPopover = !showTagAssignPopover" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]" title="Manage contact tags">
                                            <i class="fas fa-tags"></i>
                                        </button>
                                        <!-- Tag Assignment Popover -->
                                        <div
                                            x-show="showTagAssignPopover"
                                            @click.away="showTagAssignPopover = false"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            x-cloak
                                            class="absolute right-0 top-12 w-64 bg-white rounded-xl shadow-xl border border-[hsl(var(--border))] z-50 overflow-hidden"
                                        >
                                            <div class="p-3 border-b border-[hsl(var(--border))]">
                                                <p class="text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">Assign Tags</p>
                                            </div>
                                            <div class="max-h-48 overflow-y-auto scroll-area">
                                                <template x-for="tag in allTags" :key="'assign-'+tag.id">
                                                    <button
                                                        @click="toggleContactTag(tag.id)"
                                                        class="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-[hsl(var(--muted))] transition-colors text-left"
                                                    >
                                                        <div
                                                            class="h-4 w-4 rounded border-2 flex items-center justify-center transition-all duration-200 flex-shrink-0"
                                                            :style="`border-color: ${tag.color}; background: ${contactHasTag(tag.id) ? tag.color : 'transparent'};`"
                                                        >
                                                            <i x-show="contactHasTag(tag.id)" class="fas fa-check text-white text-[8px]"></i>
                                                        </div>
                                                        <span
                                                            class="text-xs font-medium px-2 py-0.5 rounded-full flex-1 truncate"
                                                            :style="`background: ${tagBgColor(tag.color)}; color: ${tag.color};`"
                                                            x-text="tag.name"
                                                        ></span>
                                                    </button>
                                                </template>
                                                <div x-show="allTags.length === 0" class="px-3 py-4 text-center">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">No tags yet</p>
                                                </div>
                                            </div>
                                            <!-- Inline Create Tag -->
                                            <div class="p-3 border-t border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)]">
                                                <div class="flex items-center gap-2">
                                                    <div class="relative flex-shrink-0">
                                                        <div
                                                            class="h-5 w-5 rounded-full cursor-pointer border-2 border-white shadow-sm"
                                                            :style="`background: ${inlineTagColor};`"
                                                            @click="
                                                                const idx = tagPresetColors.indexOf(inlineTagColor);
                                                                inlineTagColor = tagPresetColors[(idx + 1) % tagPresetColors.length];
                                                            "
                                                            title="Click to change color"
                                                        ></div>
                                                    </div>
                                                    <input
                                                        type="text"
                                                        x-model="inlineTagName"
                                                        @keydown.enter.prevent="createTagInline()"
                                                        placeholder="New tag name..."
                                                        class="input text-xs py-1 flex-1"
                                                        maxlength="50"
                                                    >
                                                    <button
                                                        @click="createTagInline()"
                                                        :disabled="!inlineTagName.trim()"
                                                        class="btn btn-primary btn-sm px-2 py-1 text-xs"
                                                    >
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="refreshMessages" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]">
                                        <i class="fas fa-sync-alt" :class="{'animate-spin': loadingMessages}"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Assigned Tags Pills -->
                            <div x-show="(selectedContact?.tags || []).length > 0" class="flex items-center gap-1.5 pb-2 flex-wrap -mt-1">
                                <template x-for="tag in (selectedContact?.tags || [])" :key="'hdr-tag-'+tag.id">
                                    <span
                                        class="inline-flex items-center gap-1 text-[10px] font-medium pl-2 pr-1 py-0.5 rounded-full transition-all duration-200"
                                        :style="`background: ${tagBgColor(tag.color)}; color: ${tag.color};`"
                                    >
                                        <span x-text="tag.name"></span>
                                        <button
                                            @click="removeContactTag(tag.id)"
                                            class="h-3.5 w-3.5 rounded-full flex items-center justify-center hover:bg-black/10 transition-colors"
                                            title="Remove tag"
                                        >
                                            <i class="fas fa-times text-[7px]"></i>
                                        </button>
                                    </span>
                                </template>
                            </div>
                        </div>
                        <!-- Messages -->
                        <div class="flex-1 overflow-y-auto scroll-area p-4 space-y-3 bg-[hsl(var(--muted)/0.2)]" x-ref="messagesContainer" @scroll="onMessagesScroll($event)">
                            <!-- Load-older spinner (top) -->
                            <div x-show="loadingOlder" class="flex justify-center py-2">
                                <i class="fas fa-circle-notch fa-spin text-[hsl(var(--muted-foreground))]"></i>
                            </div>
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
                                                    class="rounded-2xl px-3 py-2 md:px-4 md:py-2.5 shadow-sm overflow-hidden"
                                                    style="overflow-wrap:anywhere;word-break:break-word;"
                                                    :class="message?.direction === 'outgoing' ? 'bg-[hsl(var(--primary))] text-white rounded-br-md' : 'bg-white border border-[hsl(var(--border))] rounded-bl-md'"
                                                >
                                                    <!-- Text -->
                                                    <div x-show="message?.type === 'text'" x-data="{ expanded: false }">
                                                        <div
                                                            class="chat-markdown text-sm break-words overflow-hidden"
                                                            :class="!expanded && (message?.content || message?.body || '').length > 300 ? 'line-clamp-4' : ''"
                                                            x-html="message?.type === 'text' ? formatChatMarkdown(message.content || message.body) : ''"
                                                        ></div>
                                                        <button
                                                            x-show="(message?.content || message?.body || '').length > 300"
                                                            @click.stop="expanded = !expanded"
                                                            class="text-xs mt-1 underline opacity-70 hover:opacity-100"
                                                            x-text="expanded ? 'Show less' : 'Show more'"
                                                        ></button>
                                                    </div>
                                                    <!-- Template -->
                                                    <div x-show="message?.type === 'template'" class="text-sm" x-data="{ expanded: false }">
                                                        <div class="flex items-center gap-2 mb-1 opacity-80">
                                                            <i class="fas fa-file-alt text-xs"></i>
                                                            <span class="text-xs font-medium">Template</span>
                                                        </div>
                                                        <div
                                                            class="chat-markdown break-words overflow-hidden"
                                                            :class="!expanded && (message?.body || '').length > 300 ? 'line-clamp-4' : ''"
                                                            x-html="message?.type === 'template' ? formatChatMarkdown(message.body || 'Template message') : ''"
                                                        ></div>
                                                        <button
                                                            x-show="(message?.body || '').length > 300"
                                                            @click.stop="expanded = !expanded"
                                                            class="text-xs mt-1 underline opacity-70 hover:opacity-100"
                                                            x-text="expanded ? 'Show less' : 'Show more'"
                                                        ></button>
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
                                                        <div x-show="message?.caption" class="chat-markdown text-sm mt-2" x-html="message?.type === 'image' ? formatChatMarkdown(message.caption) : ''"></div>
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
                                                        <div x-show="message?.caption" class="chat-markdown text-sm mt-2" x-html="message?.type === 'video' ? formatChatMarkdown(message.caption) : ''"></div>
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
                                                    <template x-if="message?.interactive_details">
                                                        <div class="space-y-2 text-sm" x-data="{ get details() { return message.interactive_details; } }">
                                                            <p x-show="details.header" class="font-semibold whitespace-pre-wrap" x-text="details.header"></p>
                                                            <div x-show="details.body" class="chat-markdown" x-html="formatChatMarkdown(details.body)"></div>
                                                            <div x-show="details.footer" class="chat-markdown text-xs opacity-80" x-html="formatChatMarkdown(details.footer)"></div>
                                                            <div x-show="details.selection" class="border-l-2 border-violet-500 pl-2.5">
                                                                <p class="text-xs font-medium text-violet-700"><i class="fas fa-check mr-1" aria-hidden="true"></i>Pilihan pelanggan</p>
                                                                <p class="font-semibold mt-0.5" x-text="details.selection"></p>
                                                                <div x-show="details.description" class="chat-markdown text-xs opacity-80 mt-0.5" x-html="formatChatMarkdown(details.description)"></div>
                                                            </div>
                                                            <div x-show="details.buttons?.length || details.button_text || details.sections?.length" class="space-y-1.5">
                                                                <p x-show="details.button_text" class="rounded-lg bg-white/15 px-3 py-1.5 text-xs font-semibold" x-text="details.button_text"></p>
                                                                <template x-for="(button, buttonIndex) in (details.buttons || [])" :key="buttonIndex">
                                                                    <div class="rounded-lg border border-white/20 px-3 py-1.5 text-center text-xs font-medium bg-white/15" x-text="button.reply?.title || button.title"></div>
                                                                </template>
                                                                <template x-for="(section, sectionIndex) in (details.sections || [])" :key="sectionIndex">
                                                                    <div class="rounded px-3 py-2" :class="message.direction === 'outgoing' ? 'bg-white/20' : 'bg-[hsl(var(--muted))]'">
                                                                        <p x-show="section.title" class="text-xs font-semibold" x-text="section.title"></p>
                                                                        <ul class="divide-y divide-current/15">
                                                                            <template x-for="(row, rowIndex) in section.rows" :key="rowIndex">
                                                                                <li class="py-1">
                                                                                    <div class="flex items-baseline justify-between gap-3">
                                                                                        <span x-text="row.title || row.name || row.product_retailer_id"></span>
                                                                                        <span x-show="row.quantity != null" class="shrink-0 text-xs" x-text="'× ' + row.quantity"></span>
                                                                                    </div>
                                                                                    <div x-show="row.description" class="chat-markdown text-xs opacity-80" x-html="formatChatMarkdown(row.description)"></div>
                                                                                </li>
                                                                            </template>
                                                                        </ul>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                            <p x-show="!details.header && !details.body && !details.footer && !details.selection && !details.buttons?.length && !details.button_text && !details.sections?.length">Detail pesan interaktif tidak tersedia</p>
                                                        </div>
                                                    </template>
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
                                                    <div x-show="!message?.interactive_details && !['text', 'template', 'image', 'video', 'document', 'audio', 'location', 'sticker', 'contacts', 'contact', 'reaction'].includes(message?.type)">
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
                                    @env(['local', 'development'])
                                        <label x-show="trackCtwaEnabled && !isTemplateMessage" x-cloak class="flex items-center gap-2 mt-1 text-xs">
                                            <input type="checkbox" x-model="trackCtwaRecap" :disabled="sending">
                                            Tandai sebagai rekap order
                                        </label>
                                    @endenv
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
                <!-- Tag Management Modal -->
                <div x-show="showTagManageModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showTagManageModal = false; cancelEditTag()"></div>
                    <div class="card relative w-full max-w-md max-h-[85vh] overflow-hidden">
                        <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="font-semibold">Manage Tags</h3>
                            <button @click="showTagManageModal = false; cancelEditTag()" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <!-- Create / Edit Tag Form -->
                        <div class="p-4 border-b border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.2)]">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs font-medium mb-1 block" x-text="editingTag ? 'Edit Tag' : 'Create New Tag'"></label>
                                    <input
                                        type="text"
                                        x-model="tagForm.name"
                                        @keydown.enter.prevent="saveTagFromModal()"
                                        class="input w-full text-sm"
                                        placeholder="Tag name (e.g. VIP, New Lead)"
                                        maxlength="50"
                                    >
                                </div>
                                <!-- Color Palette -->
                                <div>
                                    <label class="text-xs font-medium mb-1.5 block">Color</label>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="color in tagPresetColors" :key="'palette-'+color">
                                            <button
                                                type="button"
                                                @click="tagForm.color = color"
                                                class="h-7 w-7 rounded-full transition-all duration-200 border-2 flex items-center justify-center"
                                                :style="`background: ${color}; border-color: ${tagForm.color === color ? '#1e293b' : 'transparent'}; transform: ${tagForm.color === color ? 'scale(1.15)' : 'scale(1)'};`"
                                            >
                                                <i x-show="tagForm.color === color" class="fas fa-check text-white text-[10px]"></i>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <!-- Preview -->
                                <div x-show="tagForm.name.trim()" class="flex items-center gap-2">
                                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Preview:</span>
                                    <span
                                        class="text-xs font-medium px-2.5 py-0.5 rounded-full"
                                        :style="`background: ${tagBgColor(tagForm.color)}; color: ${tagForm.color};`"
                                        x-text="tagForm.name"
                                    ></span>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        x-show="editingTag"
                                        @click="cancelEditTag()"
                                        class="btn btn-outline btn-sm flex-1"
                                    >Cancel</button>
                                    <button
                                        @click="saveTagFromModal()"
                                        :disabled="!tagForm.name.trim()"
                                        class="btn btn-primary btn-sm flex-1"
                                        x-text="editingTag ? 'Update Tag' : 'Create Tag'"
                                    >Create Tag</button>
                                </div>
                            </div>
                        </div>
                        <!-- Tags List -->
                        <div class="max-h-64 overflow-y-auto scroll-area">
                            <template x-for="tag in allTags" :key="'manage-'+tag.id">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-[hsl(var(--border)/0.5)] hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="h-3 w-3 rounded-full flex-shrink-0" :style="`background: ${tag.color};`"></div>
                                        <span class="text-sm font-medium truncate" x-text="tag.name"></span>
                                        <span class="text-[10px] text-[hsl(var(--muted-foreground))] flex-shrink-0" x-text="(tag.contacts_count || 0) + ' contacts'"></span>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <button @click="editTag(tag)" class="btn btn-ghost btn-icon btn-sm" title="Edit tag">
                                            <i class="fas fa-pen text-xs text-[hsl(var(--muted-foreground))]"></i>
                                        </button>
                                        <button @click="deleteTag(tag.id)" class="btn btn-ghost btn-icon btn-sm" title="Delete tag">
                                            <i class="fas fa-trash text-xs text-red-400"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <div x-show="allTags.length === 0" class="empty-state py-12">
                                <div class="empty-state-icon"><i class="fas fa-tags"></i></div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))] mt-2">No tags yet</p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">Create your first tag above</p>
                            </div>
                        </div>
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
        contacts: [], contactsPage: 1, contactsLastPage: 1, contactsRequestId: 0,
        readErrors: {},
        selectedContact: null, messages: [], templates: [],
        pendingMessageStatuses: new Map(),
        liveCursor: null, liveTimer: null, syncingLiveMessages: false, fetchingLatest: false, conversationVersion: 0,
        // Windowed (WhatsApp-like) message loading + browser cache
        messagesPerPage: 10, hasMoreOlder: false, loadingOlder: false, oldestId: null,
        newMessage: '', contactSearch: '', loadingContacts: true, loadingMessages: false, sending: false,
        trackCtwaEnabled: false, trackCtwaRecap: false,
        mediaPreview: null, mediaFile: null,
        showTemplateModal: false, showImageModal: false, showVideoModal: false, showDocumentModal: false,
        showLocationModal: false, showButtonModal: false, showListModal: false, showAudioModal: false,
        showFormatBar: false, formatBarPosition: { top: 0, left: 0 },
        isTemplateMessage: false, selectedTemplateInfo: null,
        // Tag system state
        allTags: [],
        selectedFilterTag: null,
        showTagManageModal: false,
        showTagAssignPopover: false,
        tagForm: { name: '', color: '#a855f7' },
        editingTag: null,
        tagPresetColors: [
            '#a855f7', '#3b82f6', '#06b6d4', '#14b8a6',
            '#22c55e', '#84cc16', '#eab308', '#f97316',
            '#ef4444', '#ec4899', '#f43f5e', '#64748b'
        ],
        inlineTagName: '',
        inlineTagColor: '#a855f7',
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
            @env(['local', 'development'])
            fetch('/api/track-ctwa/inbox', {
                headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token'), 'Accept': 'application/json' }
            }).then(response => response.ok ? response.json() : {}).then(data => {
                this.trackCtwaEnabled = data.enabled === true;
            }).catch(() => {});
            @endenv
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

            // Listen for real-time messages from broadcast
            window.addEventListener('whatsapp-message-received', async (e) => {
                const newMessage = e.detail.message;
                const added = this.showNewMessage(newMessage);
                if (added && newMessage.direction === 'incoming') {
                    await this.markContactAsRead(newMessage.contact_id);
                }
                // Update contacts list silently
                if (!this.loadingContacts) this.fetchContacts(this.contactsPage, false);
            });

            // Listen for message status updates (delivered, read)
            window.addEventListener('whatsapp-status-updated', (e) => {
                const update = e.detail;
                if (this.selectedContact && String(update.contact_id) === String(this.selectedContact.id)) {
                    const id = String(update.id);
                    const message = this.messages.find(m => String(m.id) === id);
                    if (message) {
                        this.upsertMessages([{ ...message, ...this.mergeMessageStatus(message, update) }]);
                        this.cacheMessages(this.selectedContact.id);
                    } else {
                        this.pendingMessageStatuses.set(id, this.mergeMessageStatus(this.pendingMessageStatuses.get(id), update));
                    }
                }
                if (update.direction === 'incoming') this.fetchContacts(this.contactsPage, false);
            });

            window.addEventListener('echo-ready', () => {
                if (!this.loadingContacts) this.fetchContacts(this.contactsPage, false);
                this.syncLiveMessages();
            });

            this.scheduleLiveSync();
            await Promise.all([this.fetchContacts(), this.fetchTemplates(), this.fetchTags()]);
            const contactId = new URLSearchParams(window.location.search).get('contact');
            const contact = this.contacts.find(c => String(c.id) === contactId);
            if (contact) this.selectContact(contact);
        },
        destroy() {
            clearTimeout(this.liveTimer);
            this.liveTimer = null;
            this.conversationVersion++;
        },
        scheduleLiveSync() {
            const delay = window.isEchoReady?.() ? 15000 : 4000;
            this.liveTimer = setTimeout(async () => {
                await this.syncLiveMessages();
                if (this.liveTimer !== null) this.scheduleLiveSync();
            }, delay);
        },
        async fetchContacts(page = 1, showLoading = true) {
            const requestId = ++this.contactsRequestId;
            if (showLoading) this.loadingContacts = true;
            try {
                const token = localStorage.getItem('token');
                const params = new URLSearchParams({ page, search: this.contactSearch.trim() });
                if (this.selectedFilterTag) params.set('tag_id', this.selectedFilterTag);
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts?${params}`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (requestId !== this.contactsRequestId) return;
                if (!res.ok || !data.success) throw new Error('Failed to load WhatsApp contacts');
                this.contacts = data.data;
                this.contactsPage = data.pagination.current_page;
                this.contactsLastPage = data.pagination.last_page;
            } catch (e) { console.error('Error:', e); }
            finally {
                if (requestId === this.contactsRequestId) this.loadingContacts = false;
            }
        },
        async fetchTemplates() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.templates = (data.data || []).filter(t => t.status === 'APPROVED');
            } catch (e) { console.error('Error:', e); }
        },
        async selectContact(contact) {
            this.trackCtwaRecap = false;
            const version = ++this.conversationVersion;
            this.selectedContact = contact;
            this.liveCursor = null;
            this.syncingLiveMessages = false;
            this.fetchingLatest = false;
            this.pendingMessageStatuses.clear();
            this.hasMoreOlder = false; this.oldestId = null;
            // Switch to chat view on mobile (Requirements 4.2, 4.4)
            if (this.isMobileMessages) {
                this.mobileView = 'chat';
            }

            // WhatsApp-like instant render from browser cache, then refresh.
            const cached = this.loadCachedMessages(contact.id);
            if (cached.length) {
                this.messages = cached;
                this.oldestId = cached[0].id;
                this.hasMoreOlder = true; // assume older exist until proven otherwise
                await this.$nextTick();
                setTimeout(() => this.scrollToBottom(), 30);
            } else {
                this.messages = [];
            }

            if (version !== this.conversationVersion) return;
            await this.fetchMessages();
            // Mark messages as read when opening conversation
            if (version === this.conversationVersion && contact.unread_count > 0) {
                await this.markContactAsRead(contact.id);
            }
        },
        // Back to contacts list on mobile (Requirements 4.3)
        backToContacts() {
            this.mobileView = 'contacts';
        },
        async syncLiveMessages() {
            if (document.hidden || (this.isMobileMessages && this.mobileView !== 'chat') ||
                !this.selectedContact?.id || this.syncingLiveMessages) return;
            if (this.liveCursor === null) return this.fetchMessages();

            const contactId = this.selectedContact.id;
            const version = this.conversationVersion;
            const token = localStorage.getItem('token');
            let unread = false;
            this.syncingLiveMessages = true;
            try {
                let hasMore;
                do {
                    const params = new URLSearchParams({ after_id: this.liveCursor, per_page: 50 });
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/messages?${params}`, {
                        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
                    });
                    const page = await response.json();
                    if (version !== this.conversationVersion) return;
                    if (!response.ok || !page.success) throw new Error('Failed to sync WhatsApp messages');
                    if (!page.data.length) break;

                    const knownIds = new Set(this.messages.map(message => String(message.id)));
                    const added = page.data.filter(message => !knownIds.has(String(message.id)));
                    unread ||= added.some(message => message.direction === 'incoming' && !message.is_read);
                    const follow = added.length && this.isNearChatEnd();
                    this.upsertMessages(page.data);
                    this.liveCursor = page.data.at(-1).id;
                    this.oldestId = this.messages[0].id;
                    this.cacheMessages(contactId);
                    if (follow) this.$nextTick(() => this.scrollToBottom());
                    hasMore = page.has_more;
                } while (hasMore);
            } catch (error) {
                console.error('Error syncing WhatsApp messages:', error);
            } finally {
                if (version === this.conversationVersion) {
                    this.syncingLiveMessages = false;
                    if (unread && (!this.isMobileMessages || this.mobileView === 'chat') && !document.hidden) {
                        this.markContactAsRead(contactId).then(() => {
                            if (!this.loadingContacts) this.fetchContacts(this.contactsPage, false);
                        });
                    }
                }
            }
        },
        // Fetch the latest page (10) and merge into the view.
        async fetchMessages() {
            if (!this.selectedContact?.id || this.fetchingLatest) return;
            this.fetchingLatest = true;
            const version = this.conversationVersion;
            const hadCache = this.messages.length > 0;
            if (!hadCache) this.loadingMessages = true;
            try {
                const token = localStorage.getItem('token');
                const contactId = this.selectedContact.id;
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/messages?per_page=${this.messagesPerPage}`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                if (version !== this.conversationVersion) return;
                if (!res.ok || !data.success) throw new Error('Failed to load WhatsApp messages');

                const latest = data.data || [];
                this.liveCursor ??= latest.at(-1)?.id ?? 0;
                this.upsertMessages(latest);
                // If we had no cache, the server's has_more is authoritative.
                if (!hadCache) this.hasMoreOlder = !!data.has_more;
                if (this.messages.length) this.oldestId = this.messages[0].id;
                this.cacheMessages(contactId);

                await this.$nextTick();
                setTimeout(() => this.scrollToBottom(), 100);
            } catch (e) { console.error('Error:', e); }
            finally {
                if (version === this.conversationVersion) {
                    this.fetchingLatest = false;
                    this.loadingMessages = false;
                }
            }
        },
        // Load the previous page (older messages) when scrolling to the top.
        async loadOlderMessages() {
            if (this.loadingOlder || !this.hasMoreOlder || !this.selectedContact?.id || !this.oldestId) return;
            this.loadingOlder = true;
            const container = this.$refs.messagesContainer;
            const prevHeight = container ? container.scrollHeight : 0;
            try {
                const token = localStorage.getItem('token');
                const contactId = this.selectedContact.id;
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/messages?per_page=${this.messagesPerPage}&before_id=${this.oldestId}`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                if (this.selectedContact?.id !== contactId) return;

                const older = data.data || [];
                if (older.length) {
                    this.upsertMessages(older);
                    this.oldestId = this.messages[0].id;
                    this.cacheMessages(contactId);
                }
                this.hasMoreOlder = !!data.has_more;

                // Preserve scroll position so the view doesn't jump.
                await this.$nextTick();
                if (container) container.scrollTop = container.scrollHeight - prevHeight;
            } catch (e) { console.error('Error:', e); }
            finally { this.loadingOlder = false; }
        },
        onMessagesScroll(e) {
            if (e.target.scrollTop <= 60 && this.hasMoreOlder && !this.loadingOlder) {
                this.loadOlderMessages();
            }
        },
        mergeMessageStatus(current, update) {
            const rank = { sent: 1, failed: 2, delivered: 3, read: 4 };
            const status = (rank[update.status] ?? 0) >= (rank[current?.status] ?? 0)
                ? (update.status ?? current?.status)
                : current.status;
            return {
                status,
                delivered_at: update.delivered_at ?? current?.delivered_at,
                read_at: update.read_at ?? current?.read_at,
            };
        },
        upsertMessages(incoming) {
            if (!incoming || !incoming.length) return;
            const byId = new Map(this.messages.map(m => [String(m.id), m]));
            for (const message of incoming) {
                const id = String(message.id);
                const existing = byId.get(id);
                let status = this.mergeMessageStatus(existing, message);
                const pending = this.pendingMessageStatuses.get(id);
                if (pending) {
                    status = this.mergeMessageStatus(status, pending);
                    this.pendingMessageStatuses.delete(id);
                }
                byId.set(id, { ...existing, ...message, ...status });
            }
            this.messages = Array.from(byId.values()).sort((a, b) => Number(a.id) - Number(b.id));
        },
        showNewMessage(message, forceFollow = false) {
            if (!message || !this.selectedContact || String(message.contact_id) !== String(this.selectedContact.id)) return false;
            const added = !this.messages.some(existing => String(existing.id) === String(message.id));
            const follow = added && (forceFollow || this.isNearChatEnd());
            this.upsertMessages([message]);
            this.cacheMessages(this.selectedContact.id);
            if (follow) this.$nextTick(() => this.scrollToBottom(true));
            return added;
        },
        acceptSentMessage(data) {
            this.showNewMessage(data.saved_message, true);
            this.fetchContacts(this.contactsPage, false);
        },
        // ---- browser cache (WhatsApp-like instant open) ----
        cacheKey(contactId) { return `wa_msgs_v2_${contactId}`; },
        loadCachedMessages(contactId) {
            try { return JSON.parse(localStorage.getItem(this.cacheKey(contactId))) || []; }
            catch (e) { return []; }
        },
        cacheMessages(contactId) {
            try {
                // Keep only the most recent 40 to bound storage.
                const tail = this.messages.slice(-40);
                localStorage.setItem(this.cacheKey(contactId), JSON.stringify(tail));
            } catch (e) { /* quota / disabled — ignore */ }
        },
        async refreshMessages() { await this.fetchMessages(); },

        async toggleContactAi(contact) {
            if (!contact) return;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contact.id}/toggle-ai`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    contact.ai_active = data.data.ai_active;
                    this.contacts = [...this.contacts];
                }
            } catch (e) { console.error('Toggle AI error:', e); }
        },

        // Mark all messages from contact as read
        async markContactAsRead(contactId) {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contactId}/mark-read`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error('Failed to mark WhatsApp messages as read');
                if (this.selectedContact?.id === contactId) {
                    this.selectedContact.unread_count = data.unread_count;
                }
                const contact = this.contacts.find(c => c.id === contactId);
                if (contact) contact.unread_count = data.unread_count;
                this.readErrors[contactId] = data.failed_count > 0 && data.unread_count > 0;
                if (data.updated_count > 0) {
                    try {
                        localStorage.setItem('whatsapp-read-state-changed', String(Date.now()));
                    } catch (e) { console.error('Error notifying dashboard:', e); }
                }
            } catch (e) {
                this.readErrors[contactId] = true;
                console.error('Error marking as read:', e);
            }
        },
        selectTemplate(tpl) {
            this.trackCtwaRecap = false;
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
        formatChatMarkdown(text) {
            if (!text) return '';
            if (window.renderChatMarkdown) return window.renderChatMarkdown(text);

            const fallback = document.createElement('div');
            fallback.textContent = text;
            return fallback.innerHTML;
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
            const markedRecap = this.trackCtwaEnabled && this.trackCtwaRecap;

            // Clear input immediately for better UX
            this.newMessage = '';
            this.isTemplateMessage = false;
            this.selectedTemplateInfo = null;
            this.trackCtwaRecap = false;

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

                if (endpoint === '/whatsapp/send/text' && markedRecap) {
                    payload.track_ctwa_recap = true;
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
                    this.trackCtwaRecap = markedRecap;
                } else {
                    this.acceptSentMessage(data);
                    if (data.track_ctwa_warning) alert(data.track_ctwa_warning);
                }
            } catch (e) {
                console.error('Error:', e);
                // Restore message if failed
                this.newMessage = messageText;
                this.isTemplateMessage = isTemplate;
                this.selectedTemplateInfo = templateInfo;
                this.trackCtwaRecap = markedRecap;
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
                    this.acceptSentMessage(data);
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
                    this.acceptSentMessage(data);
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
                    this.acceptSentMessage(data);
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
                    this.acceptSentMessage(data);
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
                    this.acceptSentMessage(data);
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
                    this.acceptSentMessage(data);
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
                    this.acceptSentMessage(data);
                }
                else alert(data.message || 'Failed to send');
            } catch (e) { console.error('Error:', e); }
            finally { this.sending = false; }
        },
        // ==========================================
        // Tag Management Methods
        // ==========================================

        async fetchTags() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/tags`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.allTags = data.data || [];
            } catch (e) { console.error('Error fetching tags:', e); }
        },

        async createTag(name, color) {
            if (!name || !name.trim()) return;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/tags`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name.trim(), color: color })
                });
                const data = await res.json();
                if (data.success) {
                    await this.fetchTags();
                    return data.data;
                } else {
                    alert(data.message || 'Failed to create tag');
                    return null;
                }
            } catch (e) { console.error('Error creating tag:', e); return null; }
        },

        async saveTagFromModal() {
            if (this.editingTag) {
                await this.updateTag(this.editingTag.id, this.tagForm.name, this.tagForm.color);
            } else {
                await this.createTag(this.tagForm.name, this.tagForm.color);
            }
            this.tagForm = { name: '', color: '#a855f7' };
            this.editingTag = null;
        },

        editTag(tag) {
            this.editingTag = tag;
            this.tagForm = { name: tag.name, color: tag.color };
        },

        cancelEditTag() {
            this.editingTag = null;
            this.tagForm = { name: '', color: '#a855f7' };
        },

        async updateTag(id, name, color) {
            try {
                const token = localStorage.getItem('token');
                const payload = {};
                if (name) payload.name = name.trim();
                if (color) payload.color = color;
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/tags/${id}`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) await this.fetchTags();
                else alert(data.message || 'Failed to update tag');
            } catch (e) { console.error('Error updating tag:', e); }
        },

        async deleteTag(id) {
            if (!confirm('Delete this tag? It will be removed from all contacts.')) return;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/tags/${id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await res.json();
                if (data.success) {
                    await this.fetchTags();
                    // If the deleted tag was the active filter, clear it
                    if (this.selectedFilterTag == id) {
                        this.selectedFilterTag = null;
                        await this.fetchContacts();
                    }
                    // Update the currently selected contact's tags if visible
                    if (this.selectedContact) {
                        this.selectedContact.tags = (this.selectedContact.tags || []).filter(t => t.id != id);
                    }
                } else alert(data.message || 'Failed to delete tag');
            } catch (e) { console.error('Error deleting tag:', e); }
        },

        // Toggle a tag on/off for the currently selected contact
        async toggleContactTag(tagId) {
            if (!this.selectedContact) return;
            const currentTags = this.selectedContact.tags || [];
            const hasTag = currentTags.some(t => t.id == tagId);
            let newTagIds;

            if (hasTag) {
                // Remove this tag
                newTagIds = currentTags.filter(t => t.id != tagId).map(t => t.id);
            } else {
                // Add this tag
                newTagIds = [...currentTags.map(t => t.id), tagId];
            }

            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${this.selectedContact.id}/tags`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tag_ids: newTagIds })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedContact.tags = data.data.tags || [];
                    // Also update in the contacts list
                    const idx = this.contacts.findIndex(c => c.id == this.selectedContact.id);
                    if (idx !== -1) this.contacts[idx].tags = this.selectedContact.tags;
                } else alert(data.message || 'Failed to update tags');
            } catch (e) { console.error('Error toggling tag:', e); }
        },

        // Remove a single tag from the selected contact (used by tag pill X button)
        async removeContactTag(tagId) {
            if (!this.selectedContact) return;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${this.selectedContact.id}/tags/${tagId}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedContact.tags = data.data.tags || [];
                    const idx = this.contacts.findIndex(c => c.id == this.selectedContact.id);
                    if (idx !== -1) this.contacts[idx].tags = this.selectedContact.tags;
                }
            } catch (e) { console.error('Error removing tag:', e); }
        },

        // Create tag inline from the assign popover
        async createTagInline() {
            if (!this.inlineTagName.trim()) return;
            const newTag = await this.createTag(this.inlineTagName, this.inlineTagColor);
            if (newTag) {
                this.inlineTagName = '';
                this.inlineTagColor = '#a855f7';
                // Automatically assign the new tag to the current contact
                await this.toggleContactTag(newTag.id);
            }
        },

        // Filter contacts by tag
        async filterByTag(tagId) {
            if (this.selectedFilterTag == tagId) {
                this.selectedFilterTag = null; // Toggle off
            } else {
                this.selectedFilterTag = tagId;
            }
            await this.fetchContacts();
        },

        // Check if contact has a specific tag
        contactHasTag(tagId) {
            return (this.selectedContact?.tags || []).some(t => t.id == tagId);
        },

        // Get tag color with opacity for background
        tagBgColor(color) {
            // Convert hex to RGB and add opacity
            const r = parseInt(color.slice(1, 3), 16);
            const g = parseInt(color.slice(3, 5), 16);
            const b = parseInt(color.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, 0.15)`;
        },

        isNearChatEnd() {
            const container = this.$refs.messagesContainer;
            return !container || container.scrollHeight - container.scrollTop - container.clientHeight < 96;
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
