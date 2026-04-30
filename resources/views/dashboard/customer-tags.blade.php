@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', __('dashboard.customer_tags_title'))

@section('content')
<div x-data="customerTagsApp()" x-init="init()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'customer-tags'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => __('dashboard.menu_customer_tags'), 'description' => __('dashboard.customer_tags_description')])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-6xl mx-auto space-y-6">

                <!-- Loading State -->
                <div x-show="loading" class="card p-6">
                    <div class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                        <span class="ml-3 text-[hsl(var(--muted-foreground))]">{{ __('dashboard.loading_data') }}</span>
                    </div>
                </div>

                <!-- Main Content (loaded) -->
                <div x-show="!loading" x-cloak>

                    <!-- Tag Management Card -->
                    <div class="card mb-6">
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-12 w-12 rounded-xl bg-purple-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-tags text-purple-600 text-2xl"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold">{{ __('dashboard.menu_customer_tags') }}</h2>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]">
                                            <span x-text="allTags.length"></span> tag · <span x-text="contacts.length"></span> {{ strtolower(__('dashboard.menu_contacts')) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <!-- Auto-tagging Toggle -->
                                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)]">
                                        <div class="flex flex-col leading-tight">
                                            <span class="text-xs font-semibold text-[hsl(var(--foreground))]">Auto-tagging</span>
                                            <span class="text-[10px] text-[hsl(var(--muted-foreground))]" x-text="autoTaggingEnabled ? 'Aktif' : 'Nonaktif'"></span>
                                        </div>
                                        <button
                                            @click="toggleAutoTagging()"
                                            :disabled="autoTaggingLoading"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                            :class="autoTaggingEnabled ? 'bg-purple-500' : 'bg-[hsl(var(--muted))]'"
                                            :title="autoTaggingEnabled ? 'Klik untuk nonaktifkan auto-tagging' : 'Klik untuk aktifkan auto-tagging'"
                                        >
                                            <span
                                                class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                :class="autoTaggingEnabled ? 'translate-x-5' : 'translate-x-0'"
                                            ></span>
                                            <span x-show="autoTaggingLoading" class="absolute inset-0 flex items-center justify-center">
                                                <i class="fas fa-spinner animate-spin text-[8px]" :class="autoTaggingEnabled ? 'text-white' : 'text-[hsl(var(--muted-foreground))]'"></i>
                                            </span>
                                        </button>
                                    </div>
                                    <button
                                        @click="showCreateModal = true; tagForm = { name: '', color: '#a855f7' }; editingTag = null"
                                        class="btn btn-primary btn-md gap-2"
                                    >
                                        <i class="fas fa-plus"></i>
                                        <span x-text="'{{ __('dashboard.create') }} Tag'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tags Overview Grid -->
                    <div x-show="allTags.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
                        <template x-for="tag in allTags" :key="'overview-'+tag.id">
                            <div class="card hover:shadow-md transition-all duration-200 group cursor-pointer" @click="filterByTag(tag.id)">
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <span
                                            class="text-sm font-semibold px-3 py-1 rounded-full"
                                            :style="`background: ${tagBgColor(tag.color)}; color: ${tag.color};`"
                                            x-text="tag.name"
                                        ></span>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click.stop="openEditModal(tag)" class="h-7 w-7 rounded-full flex items-center justify-center hover:bg-[hsl(var(--muted))] transition-colors" title="{{ __('dashboard.edit') }}">
                                                <i class="fas fa-pen text-[10px] text-[hsl(var(--muted-foreground))]"></i>
                                            </button>
                                            <button @click.stop="deleteTag(tag.id)" class="h-7 w-7 rounded-full flex items-center justify-center hover:bg-red-50 transition-colors" title="{{ __('dashboard.delete') }}">
                                                <i class="fas fa-trash text-[10px] text-red-400"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-[hsl(var(--muted-foreground))]">
                                        <i class="fas fa-users text-[10px]"></i>
                                        <span x-text="(tag.contacts_count || 0) + ' {{ strtolower(__('dashboard.menu_contacts')) }}'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Empty Tag State -->
                    <div x-show="allTags.length === 0" class="card p-8 md:p-12 mb-6">
                        <div class="text-center max-w-sm mx-auto">
                            <div class="h-20 w-20 rounded-full bg-gradient-to-br from-purple-50 to-purple-100 flex items-center justify-center mx-auto mb-6 shadow-sm">
                                <i class="fas fa-tags text-4xl text-purple-400"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-[hsl(var(--foreground))] mb-3">{{ __('dashboard.no_data') }}</h3>
                            <p class="text-[hsl(var(--muted-foreground))] mb-8 leading-relaxed">
                                {{ __('dashboard.customer_tags_description') }}
                            </p>
                            <button
                                @click="showCreateModal = true; tagForm = { name: '', color: '#a855f7' }; editingTag = null"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-purple-500 hover:bg-purple-600 text-white font-medium rounded-lg transition-all duration-200 shadow-sm hover:shadow-md"
                            >
                                <i class="fas fa-plus"></i>
                                <span x-text="'{{ __('dashboard.create') }} Tag'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Contacts Table Card -->
                    <div class="card">
                        <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))]">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <h3 class="font-semibold flex items-center gap-2">
                                    <i class="fas fa-address-book text-blue-500"></i>
                                    {{ __('dashboard.menu_contacts') }}
                                    <span x-show="activeFilterTag" class="text-xs font-normal text-[hsl(var(--muted-foreground))]">
                                        — {{ __('dashboard.filter') }}: <span class="font-medium" :style="`color: ${activeFilterTag?.color}`" x-text="activeFilterTag?.name"></span>
                                        <button @click="clearFilter()" class="ml-1 text-red-400 hover:text-red-600"><i class="fas fa-times text-[10px]"></i></button>
                                    </span>
                                </h3>
                                <div class="flex items-center gap-3">
                                    <!-- Tag Filter Dropdown -->
                                    <div class="relative" x-data="{ filterOpen: false }">
                                        <button @click="filterOpen = !filterOpen" class="btn btn-outline btn-sm gap-2">
                                            <i class="fas fa-filter text-xs"></i>
                                            <span>{{ __('dashboard.filter') }}</span>
                                        </button>
                                        <div
                                            x-show="filterOpen"
                                            @click.away="filterOpen = false"
                                            x-transition
                                            x-cloak
                                            class="absolute right-0 top-10 w-56 bg-white rounded-xl shadow-xl border border-[hsl(var(--border))] z-30 overflow-hidden"
                                        >
                                            <div class="p-2">
                                                <button
                                                    @click="clearFilter(); filterOpen = false"
                                                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-sm hover:bg-[hsl(var(--muted))] transition-colors"
                                                    :class="!activeFilterTag ? 'bg-[hsl(var(--muted))] font-medium' : ''"
                                                >
                                                    <i class="fas fa-list text-xs text-[hsl(var(--muted-foreground))]"></i>
                                                    <span>{{ __('dashboard.view_all') }}</span>
                                                </button>
                                                <template x-for="tag in allTags" :key="'filter-dd-'+tag.id">
                                                    <button
                                                        @click="filterByTag(tag.id); filterOpen = false"
                                                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-sm hover:bg-[hsl(var(--muted))] transition-colors"
                                                        :class="activeFilterTag?.id == tag.id ? 'bg-[hsl(var(--muted))] font-medium' : ''"
                                                    >
                                                        <div class="h-2.5 w-2.5 rounded-full flex-shrink-0" :style="`background: ${tag.color};`"></div>
                                                        <span x-text="tag.name" class="flex-1"></span>
                                                        <span class="text-[10px] text-[hsl(var(--muted-foreground))]" x-text="tag.contacts_count || 0"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Search -->
                                    <div class="relative">
                                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-xs pointer-events-none"></i>
                                        <input
                                            type="text"
                                            x-model="searchQuery"
                                            @input="filterContacts()"
                                            placeholder="{{ __('dashboard.search_placeholder') }}"
                                            class="input text-sm pl-9 w-48 md:w-64"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contacts List -->
                        <div class="divide-y divide-[hsl(var(--border)/0.5)]">
                            <template x-for="contact in filteredContacts" :key="'contact-'+contact.id">
                                <div class="flex items-center gap-4 px-4 sm:px-6 py-4 hover:bg-[hsl(var(--muted)/0.3)] transition-colors group">
                                    <!-- Avatar -->
                                    <img
                                        x-show="contact.name && contact.name.trim()"
                                        :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(contact.name || 'U')}&backgroundColor=a855f7`"
                                        :alt="contact.name"
                                        class="avatar h-10 w-10 flex-shrink-0"
                                    >
                                    <div
                                        x-show="!contact.name || !contact.name.trim()"
                                        class="avatar h-10 w-10 flex-shrink-0 flex items-center justify-center text-white font-bold text-xs"
                                        style="background: linear-gradient(135deg, #a855f7, #9333ea);"
                                    >
                                        <span x-text="(contact.phone_number || '?').substring(0, 3)"></span>
                                    </div>

                                    <!-- Contact Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium truncate" x-text="contact.name || contact.phone_number || 'Unknown'"></p>
                                        </div>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))] truncate" x-text="contact.phone_number"></p>
                                    </div>

                                    <!-- Current Tags -->
                                    <div class="flex items-center gap-1.5 flex-wrap justify-end max-w-[300px]">
                                        <template x-for="tag in (contact.tags || [])" :key="'ct-'+contact.id+'-'+tag.id">
                                            <span
                                                class="inline-flex items-center gap-1 text-[10px] font-medium pl-2 pr-1 py-0.5 rounded-full transition-all duration-200"
                                                :style="`background: ${tagBgColor(tag.color)}; color: ${tag.color};`"
                                            >
                                                <span x-text="tag.name"></span>
                                                <button
                                                    @click="removeTagFromContact(contact, tag.id)"
                                                    class="h-3.5 w-3.5 rounded-full flex items-center justify-center hover:bg-black/10 transition-colors"
                                                    title="{{ __('dashboard.remove') }}"
                                                >
                                                    <i class="fas fa-times text-[7px]"></i>
                                                </button>
                                            </span>
                                        </template>
                                        <span x-show="!contact.tags || contact.tags.length === 0" class="text-[10px] text-[hsl(var(--muted-foreground))] italic">No tags</span>
                                    </div>

                                    <!-- Assign Tag Button -->
                                    <div class="relative flex-shrink-0" x-data="{ assignOpen: false }">
                                        <button
                                            @click="assignOpen = !assignOpen"
                                            class="btn btn-outline btn-sm btn-icon h-8 w-8 opacity-60 group-hover:opacity-100 transition-opacity"
                                            title="{{ __('dashboard.add') }} Tag"
                                        >
                                            <i class="fas fa-plus text-xs"></i>
                                        </button>
                                        <!-- Assign Popover -->
                                        <div
                                            x-show="assignOpen"
                                            @click.away="assignOpen = false"
                                            x-transition
                                            x-cloak
                                            class="absolute right-0 top-10 w-60 bg-white rounded-xl shadow-xl border border-[hsl(var(--border))] z-30 overflow-hidden"
                                        >
                                            <div class="p-2.5 border-b border-[hsl(var(--border))]">
                                                <p class="text-[10px] font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">{{ __('dashboard.add') }} Tag</p>
                                            </div>
                                            <div class="max-h-40 overflow-y-auto scroll-area">
                                                <template x-for="tag in allTags" :key="'assign-popup-'+contact.id+'-'+tag.id">
                                                    <button
                                                        @click="toggleTagForContact(contact, tag.id)"
                                                        class="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-[hsl(var(--muted))] transition-colors text-left"
                                                    >
                                                        <div
                                                            class="h-4 w-4 rounded border-2 flex items-center justify-center transition-all duration-200 flex-shrink-0"
                                                            :style="`border-color: ${tag.color}; background: ${(contact.tags || []).some(t => t.id == tag.id) ? tag.color : 'transparent'};`"
                                                        >
                                                            <i x-show="(contact.tags || []).some(t => t.id == tag.id)" class="fas fa-check text-white text-[8px]"></i>
                                                        </div>
                                                        <span
                                                            class="text-xs font-medium px-2 py-0.5 rounded-full truncate"
                                                            :style="`background: ${tagBgColor(tag.color)}; color: ${tag.color};`"
                                                            x-text="tag.name"
                                                        ></span>
                                                    </button>
                                                </template>
                                                <div x-show="allTags.length === 0" class="px-3 py-4 text-center">
                                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ __('dashboard.no_data') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty Contacts State -->
                            <div x-show="filteredContacts.length === 0" class="px-6 py-12 text-center">
                                <div class="h-12 w-12 rounded-full bg-[hsl(var(--muted))] flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-users text-[hsl(var(--muted-foreground))]"></i>
                                </div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ __('dashboard.no_results') }}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- Create / Edit Tag Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showCreateModal = false">
        <div class="fixed inset-0 bg-black/50" @click="showCreateModal = false; editingTag = null"></div>
        <div class="card relative w-full max-w-md overflow-hidden">
            <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                <h3 class="font-semibold" x-text="editingTag ? '{{ __('dashboard.edit') }} Tag' : '{{ __('dashboard.create') }} Tag'"></h3>
                <button @click="showCreateModal = false; editingTag = null" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-5 space-y-5">
                <!-- Name -->
                <div class="space-y-2">
                    <label class="text-sm font-medium block">{{ __('dashboard.name') }}</label>
                    <input
                        type="text"
                        x-model="tagForm.name"
                        @keydown.enter.prevent="saveTag()"
                        class="input w-full"
                        placeholder="e.g. VIP, New Lead, Loyal Customer"
                        maxlength="50"
                    >
                </div>

                <!-- Color Palette -->
                <div class="space-y-2">
                    <label class="text-sm font-medium block">Color</label>
                    <div class="flex flex-wrap gap-2.5">
                        <template x-for="color in tagPresetColors" :key="'modal-color-'+color">
                            <button
                                type="button"
                                @click="tagForm.color = color"
                                class="h-8 w-8 rounded-full transition-all duration-200 border-2 flex items-center justify-center focus:outline-none"
                                :style="`background: ${color}; border-color: ${tagForm.color === color ? '#1e293b' : 'transparent'}; transform: ${tagForm.color === color ? 'scale(1.2)' : 'scale(1)'}; box-shadow: ${tagForm.color === color ? '0 0 0 3px rgba(168,85,247,0.2)' : 'none'};`"
                            >
                                <i x-show="tagForm.color === color" class="fas fa-check text-white text-xs"></i>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Preview -->
                <div x-show="tagForm.name.trim()" class="flex items-center gap-2 pt-1">
                    <span class="text-xs text-[hsl(var(--muted-foreground))]">Preview:</span>
                    <span
                        class="text-sm font-medium px-3 py-1 rounded-full"
                        :style="`background: ${tagBgColor(tagForm.color)}; color: ${tagForm.color};`"
                        x-text="tagForm.name"
                    ></span>
                </div>
            </div>
            <div class="p-4 border-t border-[hsl(var(--border))] flex gap-3">
                <button @click="showCreateModal = false; editingTag = null" class="btn btn-outline btn-md flex-1">{{ __('dashboard.cancel') }}</button>
                <button
                    @click="saveTag()"
                    :disabled="!tagForm.name.trim() || savingTag"
                    class="btn btn-primary btn-md flex-1"
                >
                    <i x-show="savingTag" class="fas fa-spinner animate-spin mr-1"></i>
                    <span x-text="editingTag ? '{{ __('dashboard.update') }}' : '{{ __('dashboard.create') }}'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="!deletingTag && closeDeleteModal()">
        <div x-show="showDeleteModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="!deletingTag && closeDeleteModal()"></div>
        <div x-show="showDeleteModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full max-w-md">
            <!-- Loading Overlay -->
            <div x-show="deletingTag" x-transition class="absolute inset-0 bg-white/80 z-10 flex items-center justify-center rounded-lg">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-red-500 mb-3"></i>
                    <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ __('dashboard.deleting') }}...</p>
                </div>
            </div>

            <!-- Modal Header -->
            <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                <h3 class="text-lg font-semibold text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    {{ __('dashboard.delete') }} Tag
                </h3>
                <button @click="closeDeleteModal" :disabled="deletingTag" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <p class="text-[hsl(var(--muted-foreground))] mb-4">
                    {{ __('dashboard.confirm_delete_message') }}
                </p>
                <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                    <p class="text-xs text-[hsl(var(--muted-foreground))] mb-1">Tag Name</p>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-sm font-semibold px-3 py-1 rounded-full"
                            :style="`background: ${tagBgColor(tagToDelete?.color)}; color: ${tagToDelete?.color};`"
                            x-text="tagToDelete?.name"
                        ></span>
                    </div>
                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-3">
                        <i class="fas fa-users text-[10px] mr-1"></i>
                        <span x-text="(tagToDelete?.contacts_count || 0) + ' {{ strtolower(__('dashboard.menu_contacts')) }} will be untagged'"></span>
                    </p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-6 border-t border-[hsl(var(--border))] flex gap-3">
                <button @click="closeDeleteModal" :disabled="deletingTag" class="btn btn-outline btn-md flex-1">
                    {{ __('dashboard.cancel') }}
                </button>
                <button type="button" @click="confirmDelete" :disabled="deletingTag" class="btn btn-md bg-red-600 hover:bg-red-700 text-white flex-1">
                    <i x-show="deletingTag" class="fas fa-spinner fa-spin mr-2"></i>
                    <i x-show="!deletingTag" class="fas fa-trash mr-2"></i>
                    <span>{{ __('dashboard.delete') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function customerTagsApp() {
    return {
        // Sidebar state
        sidebarOpen: true,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        // Page state
        loading: true,
        allTags: [],
        contacts: [],
        filteredContacts: [],
        searchQuery: '',
        activeFilterTag: null,

        // Auto-tagging
        autoTaggingEnabled: false,
        autoTaggingLoading: false,

        // Modal state
        showCreateModal: false,
        showDeleteModal: false,
        editingTag: null,
        tagToDelete: null,
        savingTag: false,
        deletingTag: false,
        tagForm: { name: '', color: '#a855f7' },
        tagPresetColors: [
            '#a855f7', '#3b82f6', '#06b6d4', '#14b8a6',
            '#22c55e', '#84cc16', '#eab308', '#f97316',
            '#ef4444', '#ec4899', '#f43f5e', '#64748b'
        ],

        API_BASE_URL: window.location.origin + '/api',

        async init() {
            this.initSidebar();
            await Promise.all([this.fetchTags(), this.fetchContacts(), this.fetchAutoTagging()]);
            this.loading = false;
        },

        initSidebar() {
            this.isMobile = window.innerWidth < 768;
            this.sidebarOpen = !this.isMobile && (localStorage.getItem('sidebarOpen') !== 'false');
            const user = localStorage.getItem('user');
            if (user) {
                try { this.user = JSON.parse(user); } catch(e) {}
            }
            window.addEventListener('resize', () => {
                this.isMobile = window.innerWidth < 768;
            });
            this.$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val));
        },

        getToken() {
            return localStorage.getItem('token');
        },

        // ---- Tag CRUD ----

        async fetchTags() {
            try {
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/tags`, {
                    headers: { 'Authorization': `Bearer ${this.getToken()}` }
                });
                const data = await res.json();
                this.allTags = data.data || [];
            } catch (e) { console.error('Error fetching tags:', e); }
        },

        async fetchContacts() {
            try {
                let url = `${this.API_BASE_URL}/whatsapp/contacts?per_page=200`;
                if (this.activeFilterTag) url += `&tag_id=${this.activeFilterTag.id}`;
                const res = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${this.getToken()}` }
                });
                const data = await res.json();
                this.contacts = data.data || [];
                this.filterContacts();
            } catch (e) { console.error('Error fetching contacts:', e); }
        },

        async fetchAutoTagging() {
            try {
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/auto-tagging`, {
                    headers: { 'Authorization': `Bearer ${this.getToken()}` }
                });
                const data = await res.json();
                this.autoTaggingEnabled = data.data?.auto_tagging_enabled ?? false;
            } catch (e) { console.error('Error fetching auto-tagging setting:', e); }
        },

        async toggleAutoTagging() {
            this.autoTaggingLoading = true;
            try {
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/auto-tagging`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${this.getToken()}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled: !this.autoTaggingEnabled })
                });
                const data = await res.json();
                if (data.success) {
                    this.autoTaggingEnabled = data.data.auto_tagging_enabled;
                }
            } catch (e) { console.error('Error toggling auto-tagging:', e); }
            finally { this.autoTaggingLoading = false; }
        },

        filterContacts() {
            if (!this.searchQuery.trim()) {
                this.filteredContacts = this.contacts;
            } else {
                const q = this.searchQuery.toLowerCase();
                this.filteredContacts = this.contacts.filter(c =>
                    (c.name && c.name.toLowerCase().includes(q)) ||
                    (c.phone_number && c.phone_number.includes(q))
                );
            }
        },

        async saveTag() {
            if (!this.tagForm.name.trim()) return;
            this.savingTag = true;
            try {
                const token = this.getToken();
                let res;
                if (this.editingTag) {
                    res = await fetch(`${this.API_BASE_URL}/whatsapp/tags/${this.editingTag.id}`, {
                        method: 'PUT',
                        headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: this.tagForm.name.trim(), color: this.tagForm.color })
                    });
                } else {
                    res = await fetch(`${this.API_BASE_URL}/whatsapp/tags`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: this.tagForm.name.trim(), color: this.tagForm.color })
                    });
                }
                const data = await res.json();
                if (data.success) {
                    this.showCreateModal = false;
                    this.editingTag = null;
                    this.tagForm = { name: '', color: '#a855f7' };
                    await this.fetchTags();
                } else {
                    alert(data.message || 'Failed');
                }
            } catch (e) { console.error('Error saving tag:', e); }
            finally { this.savingTag = false; }
        },

        openEditModal(tag) {
            this.editingTag = tag;
            this.tagForm = { name: tag.name, color: tag.color };
            this.showCreateModal = true;
        },

        async deleteTag(id) {
            const tag = this.allTags.find(t => t.id == id);
            if (!tag) return;
            this.tagToDelete = tag;
            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            this.tagToDelete = null;
        },

        async confirmDelete() {
            if (!this.tagToDelete) return;
            this.deletingTag = true;
            try {
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/tags/${this.tagToDelete.id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${this.getToken()}` }
                });
                const data = await res.json();
                if (data.success) {
                    await this.fetchTags();
                    if (this.activeFilterTag?.id == this.tagToDelete.id) {
                        this.activeFilterTag = null;
                    }
                    await this.fetchContacts();
                    this.closeDeleteModal();
                } else {
                    alert(data.message || 'Failed');
                }
            } catch (e) {
                console.error('Error deleting tag:', e);
                alert('Failed to delete tag');
            } finally {
                this.deletingTag = false;
            }
        },

        // ---- Tag Assignment ----

        async toggleTagForContact(contact, tagId) {
            const currentTags = contact.tags || [];
            const hasTag = currentTags.some(t => t.id == tagId);
            let newTagIds;
            if (hasTag) {
                newTagIds = currentTags.filter(t => t.id != tagId).map(t => t.id);
            } else {
                newTagIds = [...currentTags.map(t => t.id), tagId];
            }
            try {
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contact.id}/tags`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${this.getToken()}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tag_ids: newTagIds })
                });
                const data = await res.json();
                if (data.success) {
                    contact.tags = data.data.tags || [];
                    await this.fetchTags(); // refresh counts
                }
            } catch (e) { console.error('Error toggling tag:', e); }
        },

        async removeTagFromContact(contact, tagId) {
            try {
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contact.id}/tags/${tagId}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${this.getToken()}` }
                });
                const data = await res.json();
                if (data.success) {
                    contact.tags = data.data.tags || [];
                    await this.fetchTags(); // refresh counts
                }
            } catch (e) { console.error('Error removing tag:', e); }
        },

        // ---- Filtering ----

        async filterByTag(tagId) {
            const tag = this.allTags.find(t => t.id == tagId);
            if (this.activeFilterTag?.id == tagId) {
                this.activeFilterTag = null;
            } else {
                this.activeFilterTag = tag || null;
            }
            await this.fetchContacts();
        },

        async clearFilter() {
            this.activeFilterTag = null;
            await this.fetchContacts();
        },

        // ---- Helpers ----

        tagBgColor(color) {
            const r = parseInt(color.slice(1, 3), 16);
            const g = parseInt(color.slice(3, 5), 16);
            const b = parseInt(color.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, 0.15)`;
        },

        showNotification(message, type = 'info') {
            alert(message);
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
@endpush
@endsection
