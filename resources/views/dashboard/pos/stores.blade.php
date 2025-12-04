@extends('layouts.app')

@section('title', 'Stores - QashierWise POS')

@section('content')
<div x-data="storesApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-stores'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Stores', 'description' => 'Manage your store locations'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Store Locations</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Manage your business locations</p>
                    </div>
                    <button @click="openCreateModal()" class="btn btn-primary btn-md">
                        <i class="fas fa-plus"></i>
                        <span>Add Store</span>
                    </button>
                </div>

                <!-- Stores Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <template x-if="loading">
                        <template x-for="i in 3" :key="'skeleton-'+i">
                            <div class="card p-5">
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="skeleton h-12 w-12 rounded-xl"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="skeleton h-4 w-32"></div>
                                        <div class="skeleton h-3 w-20"></div>
                                    </div>
                                </div>
                                <div class="skeleton h-3 w-full mb-2"></div>
                                <div class="skeleton h-3 w-24"></div>
                            </div>
                        </template>
                    </template>

                    <template x-if="!loading">
                        <template x-for="store in stores" :key="store.id">
                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-xl bg-[hsl(var(--primary)/0.1)] flex items-center justify-center">
                                            <i class="fas fa-store text-xl text-[hsl(var(--primary))]"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold" x-text="store.name"></h3>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="store.code"></p>
                                        </div>
                                    </div>
                                    <span class="badge" :class="store.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'" x-text="store.is_active ? 'Active' : 'Inactive'"></span>
                                </div>
                                <div class="space-y-2 text-sm text-[hsl(var(--muted-foreground))]">
                                    <p class="flex items-center gap-2"><i class="fas fa-map-marker-alt w-4"></i><span x-text="store.address || 'No address'"></span></p>
                                    <p class="flex items-center gap-2"><i class="fas fa-phone w-4"></i><span x-text="store.phone || 'No phone'"></span></p>
                                </div>
                                <div class="flex gap-2 mt-4 pt-4 border-t border-[hsl(var(--border))]">
                                    <button @click="openEditModal(store)" class="btn btn-outline btn-sm flex-1"><i class="fas fa-edit"></i> Edit</button>
                                    <button @click="toggleStatus(store)" class="btn btn-outline btn-sm" :class="store.is_active ? 'text-amber-600' : 'text-emerald-600'">
                                        <i class="fas" :class="store.is_active ? 'fa-pause' : 'fa-play'"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && stores.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-store text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No stores found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Add your first store location.</p>
                        <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4"><i class="fas fa-plus"></i> Add Store</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showModal" x-transition class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div x-show="showModal" x-transition class="card relative w-full h-full sm:h-auto sm:max-w-md sm:max-h-[90vh] overflow-hidden flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold" x-text="editingStore ? 'Edit Store' : 'Add New Store'"></h3>
                <button @click="closeModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveStore()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Store Name <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.name" required class="input w-full min-h-[44px]" placeholder="Enter store name">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Store Code <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.code" required class="input w-full min-h-[44px]" placeholder="e.g., STR001">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Address</label>
                    <textarea x-model="form.address" rows="2" class="input w-full" placeholder="Store address"></textarea>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Phone</label>
                    <input type="text" x-model="form.phone" class="input w-full min-h-[44px]" placeholder="Phone number">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="form.is_active" id="is_active" class="rounded border-[hsl(var(--input))]">
                    <label for="is_active" class="text-sm">Active</label>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="saveStore()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Saving...' : 'Save Store'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function storesApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true, saving: false,
        stores: [], showModal: false, editingStore: null,
        form: { name: '', code: '', address: '', phone: '', is_active: true },
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],

        async init() { this.initSidebar(); await this.fetchStores(); },

        initSidebar() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) { this.sidebarOpen = false; }
            else { let s = localStorage.getItem('sidebarOpen'); if (s !== null) this.sidebarOpen = JSON.parse(s); }
            this.$watch('sidebarOpen', v => { if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v)); });
            window.addEventListener('resize', () => {
                const was = this.isMobile; this.isMobile = window.innerWidth < 768;
                if (was && !this.isMobile) { let s = localStorage.getItem('sidebarOpen'); this.sidebarOpen = s !== null ? JSON.parse(s) : true; }
                else if (!was && this.isMobile) { this.sidebarOpen = false; }
            });
            let u = localStorage.getItem('user'); if (u) { try { this.user = JSON.parse(u); } catch (e) { this.user = { name: 'User' }; } } else { this.user = { name: 'User' }; }
        },

        async fetchStores() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/stores`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.stores = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        openCreateModal() { this.editingStore = null; this.form = { name: '', code: '', address: '', phone: '', is_active: true }; this.showModal = true; },
        openEditModal(store) { this.editingStore = store; this.form = { name: store.name, code: store.code, address: store.address || '', phone: store.phone || '', is_active: store.is_active }; this.showModal = true; },
        closeModal() { this.showModal = false; this.editingStore = null; },

        async saveStore() {
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const url = this.editingStore ? `${this.API_BASE_URL}/stores/${this.editingStore.id}` : `${this.API_BASE_URL}/stores`;
                const method = this.editingStore ? 'PUT' : 'POST';
                const res = await fetch(url, { method, headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
                const data = await res.json();
                if (data.success) { this.closeModal(); await this.fetchStores(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.saving = false; }
        },

        async toggleStatus(store) {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/stores/${store.id}`, { method: 'PUT', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ ...store, is_active: !store.is_active }) });
                const data = await res.json();
                if (data.success) { await this.fetchStores(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); }
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
