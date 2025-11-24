@extends('layouts.dashboard')

@section('title', 'Templates - WhatsApp Business API')

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
            <a href="/dashboard/templates" class="flex items-center space-x-3 px-6 py-3 bg-green-500 transition">
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
                    <h1 class="text-2xl font-bold text-gray-900">Templates</h1>
                    <p class="text-sm text-gray-600">Manage your WhatsApp message templates</p>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="space-y-6" x-data="templatesManager()">

    <!-- Filter Bar -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select
                    x-model="filters.status"
                    @change="filterTemplates"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Statuses</option>
                    <option value="APPROVED">Approved</option>
                    <option value="PENDING">Pending</option>
                    <option value="REJECTED">Rejected</option>
                </select>
            </div>

            <!-- Category Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select
                    x-model="filters.category"
                    @change="filterTemplates"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">All Categories</option>
                    <option value="MARKETING">Marketing</option>
                    <option value="UTILITY">Utility</option>
                    <option value="AUTHENTICATION">Authentication</option>
                </select>
            </div>

            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <div class="relative">
                    <input
                        type="text"
                        x-model="filters.search"
                        @input="filterTemplates"
                        placeholder="Search by name..."
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <template x-for="template in filteredTemplates" :key="template.id">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1" x-text="template.name"></h3>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': template.status === 'APPROVED',
                                        'bg-yellow-100 text-yellow-800': template.status === 'PENDING',
                                        'bg-red-100 text-red-800': template.status === 'REJECTED'
                                    }">
                                    <i class="mr-1"
                                    :class="{
                                        'fas fa-check-circle': template.status === 'APPROVED',
                                        'fas fa-clock': template.status === 'PENDING',
                                        'fas fa-times-circle': template.status === 'REJECTED'
                                    }"></i>
                                    <span x-text="template.status">Status</span>
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <span x-text="template.category">Category</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Template Preview -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-4 min-h-[120px]">
                        <!-- Header Component -->
                        <div x-show="template.header" class="mb-3">
                            <template x-if="template.header_type === 'TEXT'">
                                <p class="font-semibold text-gray-900 text-sm" x-text="template.header"></p>
                            </template>
                            <template x-if="template.header_type === 'IMAGE'">
                                <div class="flex items-center space-x-2 text-gray-600">
                                    <i class="fas fa-image"></i>
                                    <span class="text-sm">Image Header</span>
                                </div>
                            </template>
                            <template x-if="template.header_type === 'VIDEO'">
                                <div class="flex items-center space-x-2 text-gray-600">
                                    <i class="fas fa-video"></i>
                                    <span class="text-sm">Video Header</span>
                                </div>
                            </template>
                            <template x-if="template.header_type === 'DOCUMENT'">
                                <div class="flex items-center space-x-2 text-gray-600">
                                    <i class="fas fa-file"></i>
                                    <span class="text-sm">Document Header</span>
                                </div>
                            </template>
                        </div>

                        <!-- Body Component -->
                        <div x-show="template.body" class="mb-3">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap" x-text="template.body"></p>
                        </div>

                        <!-- Footer Component -->
                        <div x-show="template.footer">
                            <p class="text-xs text-gray-500 italic" x-text="template.footer"></p>
                        </div>

                        <!-- Buttons Component -->
                        <div x-show="template.buttons && template.buttons.length > 0" class="mt-3 space-y-1">
                            <template x-for="button in template.buttons" :key="button.text">
                                <div class="flex items-center justify-center py-2 px-3 bg-white border border-gray-300 rounded text-xs text-blue-600">
                                    <i class="mr-2" :class="{
                                        'fas fa-phone': button.type === 'PHONE_NUMBER',
                                        'fas fa-external-link-alt': button.type === 'URL',
                                        'fas fa-reply': button.type === 'QUICK_REPLY'
                                    }"></i>
                                    <span x-text="button.text"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Meta Information -->
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-xs text-gray-600">
                            <i class="fas fa-language w-5"></i>
                            <span x-text="template.language">Language</span>
                        </div>
                        <div class="flex items-center text-xs text-gray-600" x-show="template.quality_score">
                            <i class="fas fa-star w-5"></i>
                            <span x-text="`Quality: ${template.quality_score}`">Quality</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button
                            @click="viewTemplate(template)"
                            class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-eye mr-2"></i>View
                        </button>
                        <button
                            x-show="template.status === 'APPROVED'"
                            @click="sendTemplateModal(template)"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Empty State -->
        <div x-show="filteredTemplates.length === 0" class="col-span-full text-center py-16">
            <div class="text-gray-400 text-6xl mb-4">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No templates found</h3>
            <p class="text-gray-600">Your message templates will appear here.</p>
        </div>
    </div>

    <!-- Send Template Modal -->
    <div x-show="showSendModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="closeSendModal"></div>

            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">Send Template</h3>
                        <button @click="closeSendModal" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <form @submit.prevent="sendTemplate">
                        <!-- Contact Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Contact</label>
                            <select
                                x-model="sendForm.contactId"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="">Choose a contact...</option>
                                <template x-for="contact in contacts" :key="contact.id">
                                    <option :value="contact.id" x-text="`${contact.name} (${contact.phone_number})`"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Template Preview -->
                        <div x-show="selectedTemplate" class="bg-gray-50 rounded-lg p-4 mb-4">
                            <p class="text-sm font-medium text-gray-700 mb-2">Template Preview:</p>
                            <p class="text-sm text-gray-900 font-semibold mb-1" x-text="selectedTemplate?.name"></p>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap" x-text="selectedTemplate?.body || selectedTemplate?.header"></p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="closeSendModal"
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="sending"
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg text-sm font-medium transition disabled:opacity-50">
                                <i class="fas mr-2" :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                                <span x-text="sending ? 'Sending...' : 'Send'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Template Modal -->
    <div x-show="showViewModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="closeViewModal"></div>

            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-6" x-show="selectedTemplate">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">Template Details</h3>
                        <button @click="closeViewModal" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <div class="space-y-6">
                        <!-- Basic Info -->
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-3" x-text="selectedTemplate?.name"></h4>
                            <div class="flex gap-2 mb-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': selectedTemplate?.status === 'APPROVED',
                                        'bg-yellow-100 text-yellow-800': selectedTemplate?.status === 'PENDING',
                                        'bg-red-100 text-red-800': selectedTemplate?.status === 'REJECTED'
                                    }">
                                    <span x-text="selectedTemplate?.status">Status</span>
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <span x-text="selectedTemplate?.category">Category</span>
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                    <span x-text="selectedTemplate?.language">Language</span>
                                </span>
                            </div>
                        </div>

                        <!-- Components -->
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <h5 class="font-semibold text-gray-900">Template Components:</h5>

                            <div x-show="selectedTemplate?.header">
                                <p class="text-sm font-medium text-gray-600 mb-1">Header:</p>
                                <p class="text-gray-900" x-text="selectedTemplate?.header || `[${selectedTemplate?.header_type}]`"></p>
                            </div>

                            <div x-show="selectedTemplate?.body">
                                <p class="text-sm font-medium text-gray-600 mb-1">Body:</p>
                                <p class="text-gray-900 whitespace-pre-wrap" x-text="selectedTemplate?.body"></p>
                            </div>

                            <div x-show="selectedTemplate?.footer">
                                <p class="text-sm font-medium text-gray-600 mb-1">Footer:</p>
                                <p class="text-gray-600 italic" x-text="selectedTemplate?.footer"></p>
                            </div>

                            <div x-show="selectedTemplate?.buttons && selectedTemplate?.buttons.length > 0">
                                <p class="text-sm font-medium text-gray-600 mb-2">Buttons:</p>
                                <div class="space-y-2">
                                    <template x-for="button in selectedTemplate?.buttons" :key="button.text">
                                        <div class="bg-white border border-gray-300 rounded px-4 py-2">
                                            <span class="text-sm font-medium" x-text="button.text"></span>
                                            <span class="text-xs text-gray-500 ml-2" x-text="`(${button.type})`"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function templatesManager() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            templates: [],
            filteredTemplates: [],
            contacts: [],
            selectedTemplate: null,
            showSendModal: false,
            showViewModal: false,
            loading: false,
            sending: false,
            filters: {
                status: '',
                category: '',
                search: ''
            },
            sendForm: {
                contactId: ''
            },

            async init() {
                await this.fetchTemplates();
                await this.fetchContacts();
            },

            async fetchTemplates() {
                this.loading = true;
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.templates = data.data || [];
                    this.filterTemplates();
                } catch (error) {
                    console.error('Error fetching templates:', error);
                } finally {
                    this.loading = false;
                }
            },

            async fetchContacts() {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.contacts = data.data || [];
                } catch (error) {
                    console.error('Error fetching contacts:', error);
                }
            },

            filterTemplates() {
                let filtered = this.templates;

                if (this.filters.status) {
                    filtered = filtered.filter(t => t.status === this.filters.status);
                }

                if (this.filters.category) {
                    filtered = filtered.filter(t => t.category === this.filters.category);
                }

                if (this.filters.search) {
                    const query = this.filters.search.toLowerCase();
                    filtered = filtered.filter(t =>
                        t.name.toLowerCase().includes(query) ||
                        (t.body && t.body.toLowerCase().includes(query))
                    );
                }

                this.filteredTemplates = filtered;
            },

            async refreshTemplates() {
                await this.fetchTemplates();
            },

            viewTemplate(template) {
                this.selectedTemplate = template;
                this.showViewModal = true;
            },

            closeViewModal() {
                this.showViewModal = false;
                setTimeout(() => {
                    this.selectedTemplate = null;
                }, 300);
            },

            sendTemplateModal(template) {
                this.selectedTemplate = template;
                this.sendForm.contactId = '';
                this.showSendModal = true;
            },

            closeSendModal() {
                this.showSendModal = false;
                setTimeout(() => {
                    this.selectedTemplate = null;
                    this.sendForm.contactId = '';
                }, 300);
            },

            async sendTemplate() {
                if (!this.sendForm.contactId || !this.selectedTemplate) return;

                this.sending = true;
                try {
                    const token = localStorage.getItem('token');
                    const contact = this.contacts.find(c => c.id == this.sendForm.contactId);

                    if (!contact) {
                        alert('Contact not found');
                        this.sending = false;
                        return;
                    }

                    const payload = {
                        to: contact.wa_id,
                        template_name: this.selectedTemplate.name,
                        language: this.selectedTemplate.language
                    };

                    console.log('Sending template:', payload);

                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/send/template`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        this.closeSendModal();
                        alert('Template sent successfully!');
                    } else {
                        alert('Failed to send template. Please try again.');
                    }
                } catch (error) {
                    console.error('Error sending template:', error);
                    alert('An error occurred. Please try again.');
                } finally {
                    this.sending = false;
                }
            }
        }
    }
</script>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection
