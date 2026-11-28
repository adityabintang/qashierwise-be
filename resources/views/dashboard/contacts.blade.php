@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', __('dashboard.contacts_title'))

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
<div x-data="contactsApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'contacts'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => __('whatsapp.contacts_title'), 'description' => __('whatsapp.contacts_subtitle')])

        <!-- Page Content -->
        <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
            <div class="max-w-6xl mx-auto space-y-6" x-data="contactsManager()">
                <!-- Search & Filter -->
                <div class="card p-3 md:p-4 -mx-4 md:mx-0 rounded-none md:rounded-lg shadow-sm">
                    <div class="flex flex-wrap items-center gap-3 md:gap-4" style="display:flex;flex-wrap:wrap;align-items:center;width:100%;box-sizing:border-box;">
                        <div class="relative min-w-0 flex-1" style="flex:1 1 380px;min-width:min(100%,260px);max-width:100%;">
                            <svg aria-hidden="true" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[hsl(var(--muted-foreground))] pointer-events-none z-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                            <input
                                type="text"
                                x-model="searchQuery"
                                @input="scheduleSearch()"
                                placeholder="{{ __('whatsapp.search_contacts') }}"
                                class="input w-full"
                                style="padding-left: 2.5rem;"
                            >
                        </div>
                        <select x-model="sortBy" @change="fetchContacts()" aria-label="{{ __('whatsapp.sort_by') }}" class="input" style="width:210px;max-width:100%;min-width:min(100%,170px);flex:0 1 210px;">
                            <option value="name_asc">{{ __('whatsapp.name_asc') }}</option>
                            <option value="name_desc">{{ __('whatsapp.name_desc') }}</option>
                            <option value="recent">{{ __('whatsapp.recently_added') }}</option>
                            <option value="oldest">{{ __('whatsapp.oldest_first') }}</option>
                        </select>
                        <div class="grid grid-cols-2 gap-2" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;flex:1 1 280px;max-width:320px;min-width:min(100%,260px);">
                            <button type="button" class="btn btn-primary min-h-10 justify-center gap-2 whitespace-nowrap px-4" style="min-width:0;" @click="window.qashierDownload('/api/whatsapp/exports/contacts?format=csv&search='+encodeURIComponent(searchQuery || ''))">
                                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5M12 15V3"></path></svg>
                                <span>Unduh kontak</span>
                            </button>
                            <button type="button" class="btn min-h-10 justify-center gap-2 whitespace-nowrap border border-emerald-200 bg-emerald-50 px-4 text-emerald-800 transition hover:bg-emerald-100" style="min-width:0;" @click="window.qashierDownload('/api/whatsapp/exports/contacts?format=xlsx&search='+encodeURIComponent(searchQuery || ''))">
                                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6M8 13l5 6m0-6-5 6"></path></svg>
                                <span>Excel</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="dashboard-ui dashboard-ui-card flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="dashboard-ui-check" :checked="broadcastPageSelected" :disabled="loading || !contacts.length"
                                x-effect="$el.indeterminate = !broadcastPageSelected && contacts.some(contact => contact.id in broadcastSelection)"
                                @change="selectBroadcastPage($event.target.checked)">
                            {{ __('whatsapp_broadcast.select_page') }}
                        </label>
                        <span class="dashboard-ui-muted text-sm" aria-live="polite" x-text="broadcastCopy('selected', { count: broadcastCounts.total })"></span>
                        <button type="button" class="btn btn-ghost" x-cloak x-show="broadcastCounts.total" @click="broadcastSelection = {}">{{ __('whatsapp_broadcast.clear') }}</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary" @click="openBroadcast('history')">{{ __('whatsapp_broadcast.history') }}</button>
                        <button type="button" class="btn btn-primary" :disabled="!broadcastCounts.total" @click="openBroadcast('compose')">{{ __('whatsapp_broadcast.title') }}</button>
                    </div>
                </div>

                @include('dashboard.partials.whatsapp-broadcast')

                <!-- Contacts Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Loading Skeleton -->
                    <template x-if="loading">
                        <template x-for="i in 6" :key="'skeleton-'+i">
                            <div class="card p-5">
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="skeleton h-14 w-14 rounded-full"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="skeleton h-4 w-32"></div>
                                        <div class="skeleton h-3 w-24"></div>
                                    </div>
                                </div>
                                <div class="space-y-2 mb-4">
                                    <div class="skeleton h-3 w-full"></div>
                                    <div class="skeleton h-3 w-3/4"></div>
                                </div>
                                <div class="flex gap-2">
                                    <div class="skeleton h-9 flex-1 rounded-md"></div>
                                    <div class="skeleton h-9 w-9 rounded-md"></div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <!-- Contact Cards -->
                    <template x-if="!loading">
                        <template x-for="contact in contacts" :key="contact.id">
                            <div class="card contact-card p-5 cursor-pointer" @click="showContactDetails(contact)">
                                <!-- Avatar & Name -->
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="relative">
                                        <!-- Avatar with name -->
                                        <img
                                            x-show="contact.name && contact.name.trim()"
                                            :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(contact.name || 'U')}&backgroundColor=a855f7`"
                                            :alt="contact.name"
                                            class="avatar avatar-xl"
                                        >
                                        <!-- Avatar without name -->
                                        <div
                                            x-show="!contact.name || !contact.name.trim()"
                                            class="avatar avatar-xl flex items-center justify-center text-white font-bold"
                                            style="background: linear-gradient(135deg, #a855f7, #9333ea); font-size: 1.25rem;"
                                        >
                                            <span x-text="getCountryCode(contact.phone_number) || '?'"></span>
                                        </div>
                                        <span x-show="contact.unread_count > 0" class="notification-badge" x-text="contact.unread_count">0</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate" x-text="contact.name || 'Unknown'">Unknown</h3>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))] truncate" x-text="contact.phone_number">-</p>
                                    </div>
                                    <input type="checkbox" class="dashboard-ui dashboard-ui-check" :checked="contact.id in broadcastSelection"
                                        :aria-label="broadcastCopy('select_contact', { name: contact.name || contact.phone_number })"
                                        @click.stop @change="selectBroadcastContact(contact, $event.target.checked)">
                                </div>

                                <!-- Info -->
                                <div class="space-y-2 mb-4 text-sm">
                                    <div class="flex items-center gap-2 text-[hsl(var(--muted-foreground))]" x-show="contact.profile_name">
                                        <i class="fas fa-user w-4"></i>
                                        <span class="truncate" x-text="contact.profile_name">-</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-[hsl(var(--muted-foreground))]">
                                        <i class="fas fa-comment-dots w-4"></i>
                                        <span x-text="`${contact.messages_count || 0} messages`">0 messages</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-[hsl(var(--muted-foreground))]">
                                        <i class="fas fa-clock w-4"></i>
                                        <span x-text="formatDate(contact.last_message_at || contact.created_at)">-</span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <button @click.stop="toggleAi(contact)" class="btn btn-icon md:btn-md" :class="contact.ai_active !== false ? 'btn-primary' : 'btn-outline text-gray-400'" :title="contact.ai_active !== false ? 'AI Active - Click to disable' : 'AI Inactive - Click to enable'">
                                        <i class="fas fa-robot"></i>
                                    </button>
                                    <button @click.stop="sendMessage(contact)" class="btn btn-primary btn-icon md:btn-md md:flex-1">
                                        <i class="fas fa-paper-plane"></i>
                                        <span class="hidden md:inline">Message</span>
                                    </button>
                                    <button @click.stop="showContactDetails(contact)" class="btn btn-outline btn-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Empty State -->
                <div x-show="contacts.length === 0 && !loading && !loadFailed" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon">
                            <i class="fas fa-address-book text-2xl"></i>
                        </div>
                        <h3 class="font-semibold mt-4">{{ __('whatsapp.no_contacts_found') }}</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">{{ __('whatsapp.contacts_will_appear') }}</p>
                    </div>
                </div>

                <div x-show="loadFailed" x-cloak class="card flex flex-wrap items-center gap-3 p-4">
                    <p role="alert">{{ __('whatsapp.contacts_load_failed') }}</p>
                    <button type="button" class="btn btn-outline" @click="fetchContacts(requestedPage)" :disabled="loading">
                        {{ __('whatsapp.contacts_retry') }}
                    </button>
                </div>

                <nav x-show="total > 0 && !loadFailed" x-cloak aria-label="{{ __('whatsapp.contacts_pagination') }}"
                    class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <p class="text-sm text-[hsl(var(--muted-foreground))]" aria-live="polite"
                        data-label="{{ __('whatsapp.contacts_range') }}"
                        x-text="$el.dataset.label.replace(':from', (page - 1) * perPage + 1).replace(':to', (page - 1) * perPage + contacts.length).replace(':total', total)"></p>
                    <div x-show="lastPage > 1" class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline" @click="fetchContacts(page - 1)" :disabled="loading || page <= 1">
                            {{ __('common.previous') }}
                        </button>
                        <button type="button" class="btn btn-outline" @click="fetchContacts(page + 1)" :disabled="loading || page >= lastPage">
                            {{ __('common.next') }}
                        </button>
                    </div>
                </nav>

                <!-- Contact Details Modal -->
                <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <!-- Backdrop -->
                    <div
                        x-show="showModal"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 bg-black/50"
                        @click="closeModal"
                    ></div>

                    <!-- Modal -->
                    <div
                        x-show="showModal"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="card relative w-full max-w-md overflow-hidden"
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between p-6 border-b border-[hsl(var(--border))]">
                            <h3 class="text-lg font-semibold">{{ __('whatsapp.contact_details') }}</h3>
                            <button @click="closeModal" class="btn btn-ghost btn-icon">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-6" x-show="selectedContact">
                            <!-- Avatar -->
                            <div class="flex justify-center mb-6">
                                <!-- Avatar with name -->
                                <img
                                    x-show="selectedContact?.name && selectedContact.name.trim()"
                                    :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(selectedContact?.name || 'U')}&backgroundColor=a855f7`"
                                    :alt="selectedContact?.name"
                                    class="h-24 w-24 rounded-full"
                                >
                                <!-- Avatar without name -->
                                <div
                                    x-show="!selectedContact?.name || !selectedContact.name.trim()"
                                    class="h-24 w-24 rounded-full flex items-center justify-center text-white font-bold"
                                    style="background: linear-gradient(135deg, #a855f7, #9333ea); font-size: 2rem;"
                                >
                                    <span x-text="getCountryCode(selectedContact?.phone_number) || '?'"></span>
                                </div>
                            </div>

                            <!-- Details -->
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide">{{ __('whatsapp.name') }}</label>
                                    <p class="text-lg font-semibold mt-1" x-text="selectedContact?.name || 'Unknown'">-</p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide">{{ __('whatsapp.phone_number') }}</label>
                                    <p class="text-lg font-semibold mt-1" x-text="selectedContact?.phone_number">-</p>
                                </div>
                                <div x-show="selectedContact?.profile_name">
                                    <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide">{{ __('whatsapp.profile_name') }}</label>
                                    <p class="mt-1" x-text="selectedContact?.profile_name">-</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide">{{ __('whatsapp.messages_count') }}</label>
                                        <p class="text-lg font-semibold mt-1" x-text="selectedContact?.messages_count || 0">0</p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] uppercase tracking-wide">{{ __('whatsapp.last_activity') }}</label>
                                        <p class="mt-1" x-text="formatDate(selectedContact?.last_message_at || selectedContact?.created_at)">-</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg" :class="selectedContact?.ai_active !== false ? 'bg-purple-50 border border-purple-200' : 'bg-gray-50 border border-gray-200'">
                                    <div>
                                        <p class="font-medium text-sm flex items-center gap-2">
                                            <i class="fas fa-robot" :class="selectedContact?.ai_active !== false ? 'text-purple-600' : 'text-gray-400'"></i>
                                            AI Agent
                                        </p>
                                        <p class="text-xs" :class="selectedContact?.ai_active !== false ? 'text-purple-600' : 'text-gray-500'">
                                            <span x-show="selectedContact?.ai_active !== false">AI aktif untuk kontak ini</span>
                                            <span x-show="selectedContact?.ai_active === false">AI nonaktif untuk kontak ini</span>
                                        </p>
                                    </div>
                                    <button
                                        @click="toggleAi(selectedContact)"
                                        :class="selectedContact?.ai_active !== false ? 'bg-purple-600' : 'bg-gray-300'"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 cursor-pointer"
                                    >
                                        <span
                                            :class="selectedContact?.ai_active !== false ? 'translate-x-6' : 'translate-x-1'"
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"
                                        ></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex gap-3 p-4 md:p-6 border-t border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)]">
                            <button @click="closeModal" class="btn btn-outline btn-md flex-1">
                                <i class="fas fa-times md:hidden"></i>
                                <span class="hidden md:inline">{{ __('whatsapp.close') }}</span>
                                <span class="md:hidden">{{ __('whatsapp.close') }}</span>
                            </button>
                            <button @click="sendMessage(selectedContact)" class="btn btn-primary btn-md flex-1">
                                <i class="fas fa-paper-plane"></i>
                                <span class="hidden md:inline">{{ __('whatsapp.send_message') }}</span>
                                <span class="md:hidden">{{ __('whatsapp.message') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function contactsApp() {
    return {
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        init() {
            // Set initial sidebar state based on viewport
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                let savedState = localStorage.getItem('sidebarOpen');
                if (savedState !== null) this.sidebarOpen = JSON.parse(savedState);
            }

            // Watch sidebar state changes (only save on desktop)
            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });

            // Handle resize events with debounce
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

function contactsManager() {
    return Object.assign(contactsBroadcast(), {
        API_BASE_URL: window.location.origin + '/api',
        contacts: [],
        selectedContact: null,
        showModal: false,
        searchQuery: '',
        sortBy: 'name_asc',
        loading: true,
        loadFailed: false,
        page: 1,
        lastPage: 1,
        perPage: 15,
        total: 0,
        requestedPage: 1,
        requestId: 0,
        searchTimer: null,

        async init() {
            await this.fetchContacts();
        },

        scheduleSearch() {
            clearTimeout(this.searchTimer);
            this.requestId++;
            this.loading = true;
            this.loadFailed = false;
            this.searchTimer = setTimeout(() => this.fetchContacts(), 250);
        },

        destroy() {
            clearTimeout(this.searchTimer);
            this.closeBroadcast();
            this.requestId++;
        },

        async fetchContacts(page = 1) {
            clearTimeout(this.searchTimer);
            const requestId = ++this.requestId;
            this.requestedPage = page;
            this.loading = true;
            this.loadFailed = false;
            try {
                const token = localStorage.getItem('token');
                const params = new URLSearchParams({
                    page, per_page: this.perPage, search: this.searchQuery.trim(), sort: this.sortBy,
                });
                const response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts?${params}`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (requestId !== this.requestId) return;
                if (!response.ok || !data.success) throw new Error('Failed to load WhatsApp contacts');
                if (page > data.pagination.last_page) return this.fetchContacts(data.pagination.last_page);

                this.contacts = data.data;
                for (const contact of this.contacts) {
                    if (contact.id in this.broadcastSelection) this.selectBroadcastContact(contact, true);
                }
                this.page = data.pagination.current_page;
                this.lastPage = data.pagination.last_page;
                this.total = data.total;
            } catch (e) {
                if (requestId === this.requestId) {
                    this.contacts = [];
                    this.loadFailed = true;
                    console.error('Error:', e);
                }
            } finally {
                if (requestId === this.requestId) this.loading = false;
            }
        },

        async toggleAi(contact) {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts/${contact.id}/toggle-ai`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    contact.ai_active = data.data.ai_active;
                }
            } catch (e) { console.error('Toggle AI error:', e); }
        },

        showContactDetails(contact) {
            this.selectedContact = contact;
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            setTimeout(() => this.selectedContact = null, 200);
        },

        sendMessage(contact) {
            window.location.href = `/dashboard/messages?contact=${contact.id}`;
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const days = Math.floor((new Date() - date) / 86400000);
            if (days === 0) return 'Today';
            if (days === 1) return 'Yesterday';
            if (days < 7) return `${days} days ago`;
            return date.toLocaleDateString();
        },

        getCountryCode(phoneNumber) {
            if (!phoneNumber) return '?';
            const match = phoneNumber.match(/^\+(\d{1,3})/);
            if (match) return '+' + match[1];
            const digits = phoneNumber.match(/^(\d{2,3})/);
            if (digits) return digits[1];
            return '?';
        }
    });
}
</script>
@endsection
