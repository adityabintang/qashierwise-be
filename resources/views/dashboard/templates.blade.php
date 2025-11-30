@extends('layouts.app')

@section('title', 'Templates - QashierWise')

@section('content')
<div x-data="templatesApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'templates'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => 'Templates', 'description' => 'Manage your WhatsApp message templates'])

        <!-- Page Content -->
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto space-y-6" x-data="templatesManager()">
                <!-- Filters -->
                <div class="card p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Status</label>
                            <select x-model="filters.status" @change="filterTemplates" class="input w-full">
                                <option value="">All Statuses</option>
                                <option value="APPROVED">Approved</option>
                                <option value="PENDING">Pending</option>
                                <option value="REJECTED">Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Category</label>
                            <select x-model="filters.category" @change="filterTemplates" class="input w-full">
                                <option value="">All Categories</option>
                                <option value="MARKETING">Marketing</option>
                                <option value="UTILITY">Utility</option>
                                <option value="AUTHENTICATION">Authentication</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5 block">Search</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] text-sm"></i>
                                <input type="text" x-model="filters.search" @input="filterTemplates" placeholder="Search templates..." class="input pl-9 w-full">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Templates Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Loading -->
                    <template x-if="loading">
                        <template x-for="i in 6" :key="'skeleton-'+i">
                            <div class="card p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="skeleton h-5 w-32"></div>
                                    <div class="skeleton h-5 w-20 rounded-full"></div>
                                </div>
                                <div class="skeleton h-24 w-full rounded-lg mb-4"></div>
                                <div class="flex gap-2">
                                    <div class="skeleton h-9 flex-1 rounded-md"></div>
                                    <div class="skeleton h-9 flex-1 rounded-md"></div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <!-- Templates -->
                    <template x-if="!loading">
                        <template x-for="template in filteredTemplates" :key="template.id">
                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <!-- Header -->
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate" x-text="template.name"></h3>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <span class="badge text-xs"
                                                :class="{
                                                    'bg-emerald-100 text-emerald-700': template.status === 'APPROVED',
                                                    'bg-amber-100 text-amber-700': template.status === 'PENDING',
                                                    'bg-red-100 text-red-700': template.status === 'REJECTED'
                                                }">
                                                <i class="mr-1 text-[10px]" :class="{
                                                    'fas fa-check-circle': template.status === 'APPROVED',
                                                    'fas fa-clock': template.status === 'PENDING',
                                                    'fas fa-times-circle': template.status === 'REJECTED'
                                                }"></i>
                                                <span x-text="template.status"></span>
                                            </span>
                                            <span class="badge badge-secondary text-xs" x-text="template.category"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview -->
                                <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-3 mb-4 min-h-[100px] text-sm">
                                    <div x-show="template.header" class="mb-2">
                                        <template x-if="template.header_type === 'TEXT'">
                                            <p class="font-medium" x-text="template.header"></p>
                                        </template>
                                        <template x-if="['IMAGE', 'VIDEO', 'DOCUMENT'].includes(template.header_type)">
                                            <div class="flex items-center gap-2 text-[hsl(var(--muted-foreground))]">
                                                <i :class="{
                                                    'fas fa-image': template.header_type === 'IMAGE',
                                                    'fas fa-video': template.header_type === 'VIDEO',
                                                    'fas fa-file': template.header_type === 'DOCUMENT'
                                                }"></i>
                                                <span class="text-xs" x-text="template.header_type + ' Header'"></span>
                                            </div>
                                        </template>
                                    </div>
                                    <p x-show="template.body" class="text-[hsl(var(--muted-foreground))] line-clamp-3" x-text="template.body"></p>
                                    <p x-show="template.footer" class="text-xs text-[hsl(var(--muted-foreground))] italic mt-2" x-text="template.footer"></p>
                                </div>

                                <!-- Meta -->
                                <div class="flex items-center gap-3 text-xs text-[hsl(var(--muted-foreground))] mb-4">
                                    <span class="flex items-center gap-1">
                                        <i class="fas fa-language"></i>
                                        <span x-text="template.language"></span>
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <button @click="viewTemplate(template)" class="btn btn-outline btn-md flex-1">
                                        <i class="fas fa-eye"></i>
                                        <span>View</span>
                                    </button>
                                    <button x-show="template.status === 'APPROVED'" @click="sendTemplateModal(template)" class="btn btn-primary btn-md flex-1">
                                        <i class="fas fa-paper-plane"></i>
                                        <span>Send</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && filteredTemplates.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon">
                            <i class="fas fa-file-alt text-2xl"></i>
                        </div>
                        <h3 class="font-semibold mt-4">No templates found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Your message templates will appear here.</p>
                    </div>
                </div>

                <!-- View Modal -->
                <div x-show="showViewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="showViewModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="closeViewModal"></div>
                    <div x-show="showViewModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full max-w-lg max-h-[80vh] overflow-y-auto scroll-area">
                        <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Template Details</h3>
                            <button @click="closeViewModal" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="p-6" x-show="selectedTemplate">
                            <h4 class="font-semibold text-lg mb-3" x-text="selectedTemplate?.name"></h4>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="badge" :class="{'bg-emerald-100 text-emerald-700': selectedTemplate?.status === 'APPROVED', 'bg-amber-100 text-amber-700': selectedTemplate?.status === 'PENDING', 'bg-red-100 text-red-700': selectedTemplate?.status === 'REJECTED'}" x-text="selectedTemplate?.status"></span>
                                <span class="badge badge-secondary" x-text="selectedTemplate?.category"></span>
                                <span class="badge badge-outline" x-text="selectedTemplate?.language"></span>
                            </div>
                            <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4 space-y-3">
                                <div x-show="selectedTemplate?.header">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1">Header</p>
                                    <p x-text="selectedTemplate?.header || `[${selectedTemplate?.header_type}]`"></p>
                                </div>
                                <div x-show="selectedTemplate?.body">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1">Body</p>
                                    <p class="whitespace-pre-wrap" x-text="selectedTemplate?.body"></p>
                                </div>
                                <div x-show="selectedTemplate?.footer">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1">Footer</p>
                                    <p class="text-sm italic" x-text="selectedTemplate?.footer"></p>
                                </div>
                                <div x-show="selectedTemplate?.buttons?.length > 0">
                                    <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-2">Buttons</p>
                                    <div class="space-y-1">
                                        <template x-for="btn in selectedTemplate?.buttons" :key="btn.text">
                                            <div class="bg-white border border-[hsl(var(--border))] rounded-md px-3 py-2 text-sm" x-text="btn.text"></div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Send Modal -->
                <div x-show="showSendModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="showSendModal" x-transition class="fixed inset-0 bg-black/50" @click="closeSendModal"></div>
                    <div x-show="showSendModal" x-transition class="card relative w-full max-w-md">
                        <div class="p-6 border-b border-[hsl(var(--border))] flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Send Template</h3>
                            <button @click="closeSendModal" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
                        </div>
                        <form @submit.prevent="sendTemplate" class="p-6">
                            <div class="mb-4">
                                <label class="text-sm font-medium mb-1.5 block">Select Contact</label>
                                <select x-model="sendForm.contactId" required class="input w-full">
                                    <option value="">Choose a contact...</option>
                                    <template x-for="contact in contacts" :key="contact.id">
                                        <option :value="contact.id" x-text="`${contact.name} (${contact.phone_number})`"></option>
                                    </template>
                                </select>
                            </div>
                            <div x-show="selectedTemplate" class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-3 mb-4">
                                <p class="text-xs text-[hsl(var(--muted-foreground))] mb-1">Template</p>
                                <p class="font-medium text-sm" x-text="selectedTemplate?.name"></p>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" @click="closeSendModal" class="btn btn-outline btn-md flex-1">Cancel</button>
                                <button type="submit" :disabled="sending" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="sending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i>
                                    <span x-text="sending ? 'Sending...' : 'Send'"></span>
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
function templatesApp() {
    return {
        sidebarOpen: true, user: null, notifications: [],
        init() {
            let savedState = localStorage.getItem('sidebarOpen');
            if (savedState !== null) this.sidebarOpen = JSON.parse(savedState);
            this.$watch('sidebarOpen', v => localStorage.setItem('sidebarOpen', JSON.stringify(v)));
            let storedUser = localStorage.getItem('user');
            if (storedUser) { try { this.user = JSON.parse(storedUser); } catch (e) { this.user = { name: 'User', email: 'user@example.com' }; } }
            else { this.user = { name: 'User', email: 'user@example.com' }; }
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) { try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; } }
        },
        addNotification(n) { n.id = Date.now() + Math.random(); this.notifications.unshift(n); if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50); localStorage.setItem('notifications', JSON.stringify(this.notifications)); },
        clearNotifications() { this.notifications = []; localStorage.removeItem('notifications'); },
        removeNotification(id) { this.notifications = this.notifications.filter(n => n.id !== id); localStorage.setItem('notifications', JSON.stringify(this.notifications)); },
        formatNotificationTime(t) { let d = new Date(t), diff = Math.floor((new Date() - d) / 1000); if (diff < 60) return 'Just now'; if (diff < 3600) return Math.floor(diff / 60) + 'm ago'; if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'; return d.toLocaleDateString(); },
        logout() { let token = localStorage.getItem('token'); if (token) { fetch(`${window.location.origin}/api/logout`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } }).finally(() => { localStorage.removeItem('token'); localStorage.removeItem('user'); localStorage.removeItem('sidebarOpen'); localStorage.removeItem('notifications'); window.location.href = '/login'; }); } else { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; } }
    }
}

function templatesManager() {
    return {
        API_BASE_URL: window.location.origin + '/api',
        templates: [], filteredTemplates: [], contacts: [], selectedTemplate: null,
        showSendModal: false, showViewModal: false, loading: true, sending: false,
        filters: { status: '', category: '', search: '' },
        sendForm: { contactId: '' },

        async init() { await Promise.all([this.fetchTemplates(), this.fetchContacts()]); },

        async fetchTemplates() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/templates`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.templates = data.data || [];
                this.filterTemplates();
            } catch (e) { console.error('Error:', e); }
            finally { this.loading = false; }
        },

        async fetchContacts() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/contacts`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.contacts = data.data || [];
            } catch (e) { console.error('Error:', e); }
        },

        filterTemplates() {
            let filtered = this.templates;
            if (this.filters.status) filtered = filtered.filter(t => t.status === this.filters.status);
            if (this.filters.category) filtered = filtered.filter(t => t.category === this.filters.category);
            if (this.filters.search) {
                const q = this.filters.search.toLowerCase();
                filtered = filtered.filter(t => t.name.toLowerCase().includes(q) || (t.body && t.body.toLowerCase().includes(q)));
            }
            this.filteredTemplates = filtered;
        },

        viewTemplate(t) { this.selectedTemplate = t; this.showViewModal = true; },
        closeViewModal() { this.showViewModal = false; setTimeout(() => this.selectedTemplate = null, 200); },
        sendTemplateModal(t) { this.selectedTemplate = t; this.sendForm.contactId = ''; this.showSendModal = true; },
        closeSendModal() { this.showSendModal = false; setTimeout(() => { this.selectedTemplate = null; this.sendForm.contactId = ''; }, 200); },

        async sendTemplate() {
            if (!this.sendForm.contactId || !this.selectedTemplate) return;
            this.sending = true;
            try {
                const token = localStorage.getItem('token');
                const contact = this.contacts.find(c => c.id == this.sendForm.contactId);
                if (!contact) { alert('Contact not found'); return; }
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/send-template`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ to: contact.phone_number, template_name: this.selectedTemplate.name, language_code: this.selectedTemplate.language || 'en' })
                });
                const data = await res.json();
                if (data.success) { alert('Template sent successfully!'); this.closeSendModal(); }
                else { alert(data.message || 'Failed to send template'); }
            } catch (e) { console.error('Error:', e); alert('Failed to send template'); }
            finally { this.sending = false; }
        }
    }
}
</script>
@endsection
