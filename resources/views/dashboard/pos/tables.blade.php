@extends('layouts.app')

@section('title', __('pos.tables.title') . ' - QashierWise POS')

@section('content')
<div x-data="tablesApp()" x-init="initDashboard()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-tables'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('pos.tables.title'), 'description' => __('pos.tables.description')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Table Management</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">View and manage table status</p>
                    </div>
                    <div class="flex gap-2">
                        <select x-model="storeFilter" @change="fetchTables()" class="input min-h-[44px]">
                            <option value="">All Stores</option>
                            <template x-for="store in stores" :key="store.id">
                                <option :value="store.id" x-text="store.name"></option>
                            </template>
                        </select>
                        <template x-if="hasPermission('create_tables') || hasPermission('manage_tables')">
                            <button @click="openCreateModal()" class="btn btn-primary btn-md">
                                <i class="fas fa-plus"></i>
                                <span class="hidden sm:inline">Add Table</span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Status Legend -->
                <div class="flex flex-wrap gap-4 text-sm">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> Available</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-500"></span> Occupied</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-500"></span> Reserved</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-gray-400"></span> Unavailable</span>
                </div>

                <!-- Tables Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <template x-if="loading">
                        <template x-for="i in 12" :key="'skeleton-'+i">
                            <div class="card p-4 aspect-square flex flex-col items-center justify-center">
                                <div class="skeleton h-12 w-12 rounded-full mb-2"></div>
                                <div class="skeleton h-4 w-16"></div>
                            </div>
                        </template>
                    </template>

                    <template x-if="!loading">
                        <template x-for="table in tables" :key="table.id">
                            <div class="card p-4 aspect-square flex flex-col items-center justify-center cursor-pointer hover:shadow-md transition-all"
                                 :class="getTableBorderClass(table.status)"
                                 @click="openEditModal(table)">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mb-2" :class="getTableBgClass(table.status)">
                                    <i class="fas fa-chair text-white text-lg"></i>
                                </div>
                                <span class="font-bold text-lg" x-text="'#' + table.number"></span>
                                <span class="text-xs text-[hsl(var(--muted-foreground))]" x-text="table.capacity + ' seats'"></span>
                                <span class="badge text-xs mt-2" :class="getStatusBadgeClass(table.status)" x-text="table.status"></span>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && tables.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-chair text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No tables found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Add tables to manage seating.</p>
                        <template x-if="hasPermission('create_tables') || hasPermission('manage_tables')">
                            <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4"><i class="fas fa-plus"></i> Add Table</button>
                        </template>
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
                <h3 class="text-lg font-semibold" x-text="editingTable ? 'Edit Table' : 'Add New Table'"></h3>
                <button @click="closeModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveTable()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Store <span class="text-red-500">*</span></label>
                    <select x-model="form.store_id" required class="input w-full min-h-[44px]">
                        <option value="">Select store...</option>
                        <template x-for="store in stores" :key="store.id">
                            <option :value="store.id" x-text="store.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Table Number <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.number" required class="input w-full min-h-[44px]" placeholder="e.g., 1, A1">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Capacity <span class="text-red-500">*</span></label>
                    <input type="number" x-model="form.capacity" required min="1" class="input w-full min-h-[44px]" placeholder="Number of seats">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Status</label>
                    <select x-model="form.status" class="input w-full min-h-[44px]">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <template x-if="editingTable">
                    <button type="button" @click="deleteTable()" :disabled="deleting" class="btn btn-destructive btn-md">
                        <i class="fas" :class="deleting ? 'fa-spinner animate-spin' : 'fa-trash'"></i>
                    </button>
                </template>
                <button @click="saveTable()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Saving...' : 'Save'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function tablesApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true, saving: false, deleting: false,
        tables: [], stores: [], showModal: false, editingTable: null, storeFilter: '',
        form: { store_id: '', number: '', capacity: 4, status: 'available' },
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],
        userPermissions: [], isAdmin: true,

        async init() { this.initDashboard(); await this.fetchUserPermissions(); await Promise.all([this.fetchStores(), this.fetchTables()]); },

        initDashboard() {
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
            this.fetchUserPermissions();
        },

        async fetchUserPermissions() {
            try {
                const token = localStorage.getItem('token');
                if (!token) { this.isAdmin = true; this.userPermissions = []; return; }
                const res = await fetch(`${window.location.origin}/api/user/permissions`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.success) { this.isAdmin = data.data.is_admin || false; this.userPermissions = data.data.permissions || []; }
                }
            } catch (e) { console.error('Failed to fetch permissions:', e); }
        },

        hasPermission(permission) {
            if (this.isAdmin) return true;
            if (this.userPermissions.includes('*')) return true;
            return this.userPermissions.includes(permission);
        },

        async fetchStores() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/stores`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.stores = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); }
        },

        async fetchTables() {
            // Check permission first
            if (!this.hasPermission('view_tables') && !this.hasPermission('manage_tables')) {
                this.tables = []; this.loading = false; return;
            }
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const params = this.storeFilter ? `?store_id=${this.storeFilter}` : '';
                const res = await fetch(`${this.API_BASE_URL}/tables${params}`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.tables = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        getTableBgClass(status) { return { 'available': 'bg-emerald-500', 'occupied': 'bg-amber-500', 'reserved': 'bg-blue-500', 'unavailable': 'bg-gray-400' }[status] || 'bg-gray-400'; },
        getTableBorderClass(status) { return { 'available': 'border-2 border-emerald-200', 'occupied': 'border-2 border-amber-200', 'reserved': 'border-2 border-blue-200', 'unavailable': 'border-2 border-gray-200' }[status] || ''; },
        getStatusBadgeClass(status) { return { 'available': 'bg-emerald-100 text-emerald-700', 'occupied': 'bg-amber-100 text-amber-700', 'reserved': 'bg-blue-100 text-blue-700', 'unavailable': 'bg-gray-100 text-gray-700' }[status] || 'bg-gray-100 text-gray-700'; },

        openCreateModal() { this.editingTable = null; this.form = { store_id: this.storeFilter || '', number: '', capacity: 4, status: 'available' }; this.showModal = true; },
        openEditModal(table) { this.editingTable = table; this.form = { store_id: table.store_id, number: table.number, capacity: table.capacity, status: table.status }; this.showModal = true; },
        closeModal() { this.showModal = false; this.editingTable = null; },

        async saveTable() {
            // Check permission before saving
            const canCreate = this.hasPermission('create_tables') || this.hasPermission('manage_tables');
            const canEdit = this.hasPermission('edit_tables') || this.hasPermission('manage_tables');
            if ((!this.editingTable && !canCreate) || (this.editingTable && !canEdit)) {
                alert('You do not have permission to perform this action');
                return;
            }
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const url = this.editingTable ? `${this.API_BASE_URL}/tables/${this.editingTable.id}` : `${this.API_BASE_URL}/tables`;
                const method = this.editingTable ? 'PUT' : 'POST';
                const res = await fetch(url, { method, headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
                const data = await res.json();
                if (data.success) { this.closeModal(); await this.fetchTables(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.saving = false; }
        },

        async deleteTable() {
            // Check permission before deleting
            if (!this.hasPermission('delete_tables') && !this.hasPermission('manage_tables')) {
                alert('You do not have permission to delete tables');
                return;
            }
            if (!confirm('Delete this table?')) return;
            this.deleting = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/tables/${this.editingTable.id}`, { method: 'DELETE', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.closeModal(); await this.fetchTables(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.deleting = false; }
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
