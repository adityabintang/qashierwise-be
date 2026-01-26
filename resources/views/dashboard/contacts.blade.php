@extends('layouts.app')

@section('title', __('dashboard.contacts_title'))

@section('content')
<div x-data="contactsApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'contacts'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => __('whatsapp.contacts_title'), 'description' => __('whatsapp.contacts_subtitle')])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6" x-data="contactsManager()">
                <!-- Search & Filter -->
                <div class="card p-3 md:p-4 -mx-4 md:mx-0 rounded-none md:rounded-lg">
                    <div class="flex flex-col sm:flex-row gap-3 md:gap-4">
                        <div class="relative" style="flex: 1 1 0%; min-width: 0;">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm pointer-events-none z-10"></i>
                            <input
                                type="text"
                                x-model="searchQuery"
                                @input="filterContacts"
                                placeholder="{{ __('whatsapp.search_contacts') }}"
                                class="input w-full"
                                style="padding-left: 2.5rem;"
                            >
                        </div>
                        <select x-model="sortBy" @change="sortContacts" class="input w-full sm:w-auto" style="flex: 0 0 auto; min-width: 180px; max-width: 220px;">
                            <option value="name_asc">{{ __('whatsapp.name_asc') }}</option>
                            <option value="name_desc">{{ __('whatsapp.name_desc') }}</option>
                            <option value="recent">{{ __('whatsapp.recently_added') }}</option>
                            <option value="oldest">{{ __('whatsapp.oldest_first') }}</option>
                        </select>
                    </div>
                </div>

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
                        <template x-for="contact in filteredContacts" :key="contact.id">
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
                <div x-show="filteredContacts.length === 0 && !loading" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon">
                            <i class="fas fa-address-book text-2xl"></i>
                        </div>
                        <h3 class="font-semibold mt-4">{{ __('whatsapp.no_contacts_found') }}</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">{{ __('whatsapp.contacts_will_appear') }}</p>
                    </div>
                </div>

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
    return {
        API_BASE_URL: window.location.origin + '/api',
        contacts: [],
        filteredContacts: [],
        selectedContact: null,
        showModal: false,
        searchQuery: '',
        sortBy: 'name_asc',
        loading: true,

        async init() {
            await this.fetchContacts();
        },

        async fetchContacts() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await response.json();
                this.contacts = data.data || [];
                this.filterContacts();
            } catch (e) { console.error('Error:', e); }
            finally { this.loading = false; }
        },

        filterContacts() {
            let filtered = this.contacts;
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(c =>
                    (c.name && c.name.toLowerCase().includes(query)) ||
                    (c.phone_number && c.phone_number.includes(query)) ||
                    (c.profile_name && c.profile_name.toLowerCase().includes(query))
                );
            }
            this.filteredContacts = filtered;
            this.sortContacts();
        },

        sortContacts() {
            switch(this.sortBy) {
                case 'name_asc': this.filteredContacts.sort((a, b) => (a.name || '').localeCompare(b.name || '')); break;
                case 'name_desc': this.filteredContacts.sort((a, b) => (b.name || '').localeCompare(a.name || '')); break;
                case 'recent': this.filteredContacts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)); break;
                case 'oldest': this.filteredContacts.sort((a, b) => new Date(a.created_at) - new Date(b.created_at)); break;
            }
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
    }
}
</script>
@endsection
