@extends('layouts.app')

@section('title', 'POS Users - QashierWise')

@section('content')
<div x-data="posUsersApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-users'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'POS Users', 'description' => 'Manage staff users and roles'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Staff Users</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Manage POS staff and permissions</p>
                    </div>
                    <button @click="openCreateModal()" class="btn btn-primary btn-md">
                        <i class="fas fa-plus"></i>
                        <span>Add User</span>
                    </button>
                </div>

                <!-- Users List -->
                <!-- Mobile Card View -->
                <div class="block lg:hidden space-y-3">
                    <template x-if="loading">
                        <template x-for="i in 3" :key="'skeleton-'+i">
                            <div class="card p-4">
                                <div class="flex items-center gap-3">
                                    <div class="skeleton h-12 w-12 rounded-full"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="skeleton h-4 w-32"></div>
                                        <div class="skeleton h-3 w-24"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <template x-if="!loading">
                        <template x-for="user in users" :key="user.id">
                            <div class="card p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center gap-3">
                                    <img :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(user.user?.name || 'U')}&backgroundColor=a855f7`" class="h-12 w-12 rounded-full">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate" x-text="user.user?.name || 'Unknown'"></h3>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="user.role?.name || 'No role'"></p>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="user.store?.name || 'No store'"></p>
                                    </div>
                                    <span class="badge" :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'" x-text="user.is_active ? 'Active' : 'Inactive'"></span>
                                </div>
                                <div class="flex gap-2 mt-3 pt-3 border-t border-[hsl(var(--border))]">
                                    <button @click="openEditModal(user)" class="btn btn-outline btn-sm flex-1"><i class="fas fa-edit"></i> Edit</button>
                                    <button @click="toggleStatus(user)" class="btn btn-outline btn-sm" :class="user.is_active ? 'text-amber-600' : 'text-emerald-600'">
                                        <i class="fas" :class="user.is_active ? 'fa-user-slash' : 'fa-user-check'"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden lg:block card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[hsl(var(--muted)/0.5)]">
                                <tr>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">User</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Role</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Store</th>
                                    <th class="text-center p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Status</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <template x-if="loading">
                                    <template x-for="i in 5" :key="'table-skeleton-'+i">
                                        <tr>
                                            <td class="p-4"><div class="flex items-center gap-3"><div class="skeleton h-10 w-10 rounded-full"></div><div class="skeleton h-4 w-32"></div></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-20"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-24"></div></td>
                                            <td class="p-4"><div class="skeleton h-6 w-16 mx-auto rounded-full"></div></td>
                                            <td class="p-4"><div class="skeleton h-8 w-20 ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!loading">
                                    <template x-for="user in users" :key="user.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <img :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(user.user?.name || 'U')}&backgroundColor=a855f7`" class="h-10 w-10 rounded-full">
                                                    <div>
                                                        <p class="font-medium" x-text="user.user?.name || 'Unknown'"></p>
                                                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="user.user?.email || ''"></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4" x-text="user.role?.name || '-'"></td>
                                            <td class="p-4" x-text="user.store?.name || '-'"></td>
                                            <td class="p-4 text-center">
                                                <span class="badge" :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'" x-text="user.is_active ? 'Active' : 'Inactive'"></span>
                                            </td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button @click="openEditModal(user)" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></button>
                                                    <button @click="toggleStatus(user)" class="btn btn-ghost btn-sm" :class="user.is_active ? 'text-amber-600' : 'text-emerald-600'">
                                                        <i class="fas" :class="user.is_active ? 'fa-user-slash' : 'fa-user-check'"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && users.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-users text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No POS users found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Add staff users to manage POS.</p>
                        <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4"><i class="fas fa-plus"></i> Add User</button>
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
                <h3 class="text-lg font-semibold" x-text="editingUser ? 'Edit POS User' : 'Add POS User'"></h3>
                <button @click="closeModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveUser()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">User <span class="text-red-500">*</span></label>
                    <select x-model="form.user_id" required class="input w-full min-h-[44px]" :disabled="editingUser">
                        <option value="">Select user...</option>
                        <template x-for="u in availableUsers" :key="u.id">
                            <option :value="u.id" x-text="u.name + ' (' + u.email + ')'"></option>
                        </template>
                    </select>
                </div>
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
                    <label class="text-sm font-medium mb-1.5 block">Role <span class="text-red-500">*</span></label>
                    <select x-model="form.role_id" required class="input w-full min-h-[44px]">
                        <option value="">Select role...</option>
                        <template x-for="role in roles" :key="role.id">
                            <option :value="role.id" x-text="role.name"></option>
                        </template>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="form.is_active" id="is_active" class="rounded border-[hsl(var(--input))]">
                    <label for="is_active" class="text-sm">Active</label>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="saveUser()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Saving...' : 'Save User'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function posUsersApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true, saving: false,
        users: [], stores: [], roles: [], availableUsers: [],
        showModal: false, editingUser: null,
        form: { user_id: '', store_id: '', role_id: '', is_active: true },
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],

        async init() { this.initSidebar(); await Promise.all([this.fetchUsers(), this.fetchStores(), this.fetchRoles(), this.fetchAvailableUsers()]); },

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

        async fetchUsers() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/users`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.users = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        async fetchStores() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/stores`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.stores = data.data.data || data.data; }
            } catch (e) { console.error('Error:', e); }
        },

        async fetchRoles() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/users/roles`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.roles = data.data || []; }
            } catch (e) { console.error('Error:', e); this.roles = [{ id: 1, name: 'Admin' }, { id: 2, name: 'Cashier' }, { id: 3, name: 'Manager' }]; }
        },

        async fetchAvailableUsers() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${window.location.origin}/api/users`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success || data.data) { this.availableUsers = data.data || []; }
            } catch (e) { console.error('Error:', e); }
        },

        openCreateModal() { this.editingUser = null; this.form = { user_id: '', store_id: '', role_id: '', is_active: true }; this.showModal = true; },
        openEditModal(user) { this.editingUser = user; this.form = { user_id: user.user_id, store_id: user.store_id, role_id: user.role_id, is_active: user.is_active }; this.showModal = true; },
        closeModal() { this.showModal = false; this.editingUser = null; },

        async saveUser() {
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const url = this.editingUser ? `${this.API_BASE_URL}/users/${this.editingUser.id}` : `${this.API_BASE_URL}/users`;
                const method = this.editingUser ? 'PUT' : 'POST';
                const res = await fetch(url, { method, headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
                const data = await res.json();
                if (data.success) { this.closeModal(); await this.fetchUsers(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.saving = false; }
        },

        async toggleStatus(user) {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/users/${user.id}`, { method: 'PUT', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ ...user, is_active: !user.is_active }) });
                const data = await res.json();
                if (data.success) { await this.fetchUsers(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); }
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
