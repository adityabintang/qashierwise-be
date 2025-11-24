@extends('layouts.dashboard')

@section('title', 'Contacts - WhatsApp Business API')

@section('content')
<div class="space-y-6" x-data="contactsManager()">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Contacts</h1>
            <p class="mt-1 text-sm text-gray-600">Manage your WhatsApp contacts</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="refreshContacts" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                <i class="fas fa-sync-alt mr-2" :class="{'fa-spin': loading}"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <div class="relative">
                    <input
                        type="text"
                        x-model="searchQuery"
                        @input="filterContacts"
                        placeholder="Search contacts by name or phone number..."
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>

            <!-- Sort -->
            <div>
                <select
                    x-model="sortBy"
                    @change="sortContacts"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="name_asc">Name (A-Z)</option>
                    <option value="name_desc">Name (Z-A)</option>
                    <option value="recent">Recently Added</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Contacts Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <template x-for="contact in filteredContacts" :key="contact.id">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition cursor-pointer" @click="showContactDetails(contact)">
                <div class="p-6">
                    <!-- Avatar & Name -->
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="relative">
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                                <span x-text="contact.name ? contact.name.charAt(0).toUpperCase() : '?'">?</span>
                            </div>
                            <div x-show="contact.unread_count > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center font-bold" x-text="contact.unread_count">0</div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900 truncate" x-text="contact.name || 'Unknown'">Unknown</h3>
                            <p class="text-sm text-gray-500 truncate" x-text="contact.phone_number">-</p>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-600" x-show="contact.profile_name">
                            <i class="fas fa-user w-5 text-gray-400"></i>
                            <span class="ml-2 truncate" x-text="contact.profile_name">-</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-comment-dots w-5 text-gray-400"></i>
                            <span class="ml-2" x-text="`${contact.message_count || 0} messages`">0 messages</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-clock w-5 text-gray-400"></i>
                            <span class="ml-2" x-text="formatDate(contact.last_message_at || contact.created_at)">-</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button @click.stop="sendMessage(contact)" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-paper-plane mr-2"></i>Message
                        </button>
                        <button @click.stop="showContactDetails(contact)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Empty State -->
        <div x-show="filteredContacts.length === 0" class="col-span-full text-center py-16">
            <div class="text-gray-400 text-6xl mb-4">
                <i class="fas fa-address-book"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No contacts found</h3>
            <p class="text-gray-600">Contacts will appear here when you receive or send messages.</p>
        </div>
    </div>

    <!-- Contact Details Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>

            <!-- Modal panel -->
            <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4" x-show="selectedContact">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">Contact Details</h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Contact Info -->
                    <div class="space-y-6">
                        <!-- Avatar -->
                        <div class="flex justify-center">
                            <div class="w-24 h-24 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full flex items-center justify-center text-white text-4xl font-bold">
                                <span x-text="selectedContact?.name ? selectedContact.name.charAt(0).toUpperCase() : '?'">?</span>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Name</label>
                                <p class="text-lg font-semibold text-gray-900" x-text="selectedContact?.name || 'Unknown'">-</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Phone Number</label>
                                <p class="text-lg font-semibold text-gray-900" x-text="selectedContact?.phone_number">-</p>
                            </div>
                            <div x-show="selectedContact?.profile_name">
                                <label class="block text-sm font-medium text-gray-500 mb-1">WhatsApp Profile Name</label>
                                <p class="text-lg text-gray-900" x-text="selectedContact?.profile_name">-</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Total Messages</label>
                                <p class="text-lg text-gray-900" x-text="selectedContact?.message_count || 0">0</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Last Activity</label>
                                <p class="text-lg text-gray-900" x-text="formatDate(selectedContact?.last_message_at || selectedContact?.created_at)">-</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                    <button @click="sendMessage(selectedContact)" type="button" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-paper-plane mr-2"></i> Send Message
                    </button>
                    <button @click="closeModal" type="button" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function contactsManager() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            contacts: [],
            filteredContacts: [],
            selectedContact: null,
            showModal: false,
            searchQuery: '',
            sortBy: 'name_asc',
            loading: false,

            async init() {
                await this.fetchContacts();
                this.listenForUpdates();
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
                } catch (error) {
                    console.error('Error fetching contacts:', error);
                } finally {
                    this.loading = false;
                }
            },

            filterContacts() {
                let filtered = this.contacts;

                // Search filter
                if (this.searchQuery) {
                    const query = this.searchQuery.toLowerCase();
                    filtered = filtered.filter(contact =>
                        (contact.name && contact.name.toLowerCase().includes(query)) ||
                        (contact.phone_number && contact.phone_number.includes(query)) ||
                        (contact.profile_name && contact.profile_name.toLowerCase().includes(query))
                    );
                }

                this.filteredContacts = filtered;
                this.sortContacts();
            },

            sortContacts() {
                switch(this.sortBy) {
                    case 'name_asc':
                        this.filteredContacts.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                        break;
                    case 'name_desc':
                        this.filteredContacts.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
                        break;
                    case 'recent':
                        this.filteredContacts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                        break;
                    case 'oldest':
                        this.filteredContacts.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                        break;
                }
            },

            async refreshContacts() {
                await this.fetchContacts();
            },

            showContactDetails(contact) {
                this.selectedContact = contact;
                this.showModal = true;
            },

            closeModal() {
                this.showModal = false;
                setTimeout(() => {
                    this.selectedContact = null;
                }, 300);
            },

            sendMessage(contact) {
                window.location.href = `/dashboard/messages?contact=${contact.id}`;
            },

            formatDate(dateString) {
                if (!dateString) return '-';
                const date = new Date(dateString);
                const now = new Date();
                const diff = now - date;
                const days = Math.floor(diff / 86400000);

                if (days === 0) return 'Today';
                if (days === 1) return 'Yesterday';
                if (days < 7) return `${days} days ago`;
                return date.toLocaleDateString();
            },

            listenForUpdates() {
                // Listen for new messages via Echo
                if (window.Echo && typeof window.Echo.private === 'function') {
                    try {
                        window.Echo.private('whatsapp')
                            .listen('NewWhatsAppMessage', (e) => {
                                this.refreshContacts();
                            });
                    } catch (e) {
                        console.warn('Echo listener setup failed:', e);
                    }
                }
            }
        }
    }
</script>
@endsection
