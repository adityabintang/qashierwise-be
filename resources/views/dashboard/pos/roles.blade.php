@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Roles Management - QashierWise')

@section('content')
<div x-data="rolesApp()" x-init="initDashboard()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-roles'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => 'Roles Management', 'description' => 'Manage user roles and permissions'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Roles & Permissions</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Define roles and assign permissions for user access control</p>
                    </div>
                    <template x-if="hasPermission('create_roles') || hasPermission('manage_roles')">
                        <button @click="openCreateModal()" class="btn btn-primary btn-md">
                            <i class="fas fa-plus"></i>
                            <span>Add Role</span>
                        </button>
                    </template>
                </div>

                <!-- Roles List -->
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
                        <template x-for="role in roles" :key="role.id">
                            <div class="card p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start gap-3">
                                    <div class="h-12 w-12 rounded-full bg-[hsl(var(--primary))] flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-shield-alt text-white"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate" x-text="role.name"></h3>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="role.pos_users_count + ' user(s) assigned'"></p>
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            <template x-for="(perm, index) in (role.permissions || []).slice(0, 3)" :key="index">
                                                <span class="badge badge-xs bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]" x-text="getPermissionLabel(perm)"></span>
                                            </template>
                                            <span x-show="(role.permissions || []).length > 3" class="badge badge-xs bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]" x-text="'+' + ((role.permissions || []).length - 3) + ' more'"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-3 pt-3 border-t border-[hsl(var(--border))]">
                                    <template x-if="hasPermission('edit_roles') || hasPermission('manage_roles')">
                                        <button @click="openEditModal(role)" class="btn btn-outline btn-sm flex-1"><i class="fas fa-edit"></i> Edit</button>
                                    </template>
                                    <template x-if="hasPermission('delete_roles') || hasPermission('manage_roles')">
                                        <button @click="openDeleteModal(role)" class="btn btn-outline btn-sm text-red-600 hover:bg-red-50"><i class="fas fa-trash"></i></button>
                                    </template>
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
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Role Name</th>
                                    <th class="text-left p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Permissions</th>
                                    <th class="text-center p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Users Assigned</th>
                                    <th class="text-right p-4 text-sm font-medium text-[hsl(var(--muted-foreground))]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <template x-if="loading">
                                    <template x-for="i in 5" :key="'table-skeleton-'+i">
                                        <tr>
                                            <td class="p-4"><div class="skeleton h-4 w-32"></div></td>
                                            <td class="p-4"><div class="skeleton h-4 w-48"></div></td>
                                            <td class="p-4 text-center"><div class="skeleton h-4 w-12 mx-auto"></div></td>
                                            <td class="p-4"><div class="skeleton h-8 w-20 ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!loading">
                                    <template x-for="role in roles" :key="role.id">
                                        <tr class="hover:bg-[hsl(var(--muted)/0.3)] transition-colors">
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="h-10 w-10 rounded-full bg-[hsl(var(--primary))] flex items-center justify-center">
                                                        <i class="fas fa-shield-alt text-white text-sm"></i>
                                                    </div>
                                                    <span class="font-medium" x-text="role.name"></span>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div class="flex flex-wrap gap-1">
                                                    <template x-for="(perm, index) in (role.permissions || []).slice(0, 3)" :key="index">
                                                        <span class="badge badge-xs bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]" x-text="getPermissionLabel(perm)"></span>
                                                    </template>
                                                    <span x-show="(role.permissions || []).length > 3" class="badge badge-xs bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]" x-text="'+' + ((role.permissions || []).length - 3) + ' more'"></span>
                                                    <span x-show="(role.permissions || []).length === 0" class="text-sm text-[hsl(var(--muted-foreground))]">No permissions</span>
                                                </div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <span class="badge" x-text="role.pos_users_count + ' user(s)'"></span>
                                            </td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <template x-if="hasPermission('edit_roles') || hasPermission('manage_roles')">
                                                        <button @click="openEditModal(role)" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></button>
                                                    </template>
                                                    <template x-if="hasPermission('delete_roles') || hasPermission('manage_roles')">
                                                        <button @click="openDeleteModal(role)" class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50"><i class="fas fa-trash"></i></button>
                                                    </template>
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
                <div x-show="!loading && roles.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-shield-alt text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No roles found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Create roles to manage user permissions.</p>
                        <template x-if="hasPermission('create_roles') || hasPermission('manage_roles')">
                            <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4"><i class="fas fa-plus"></i> Add Role</button>
                        </template>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full h-full sm:h-auto sm:max-w-2xl sm:max-h-[90vh] flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold" x-text="editingRole ? 'Edit Role' : 'Add New Role'"></h3>
                <button @click="closeModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveRole()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Role Name <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.name" required class="input w-full min-h-[44px]" placeholder="Enter role name">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Permissions</label>
                    <p class="text-xs text-[hsl(var(--muted-foreground))] mb-2">Select the permissions that this role should have access to:</p>
                    <div class="space-y-3 max-h-64 overflow-y-auto p-2 border border-[hsl(var(--border))] rounded-lg">
                        <template x-for="(label, key) in availablePermissions" :key="key">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" :id="'perm-'+key" :value="key" x-model="form.permissions" class="rounded border-[hsl(var(--input))] min-h-[20px] min-w-[20px)]">
                                <label :for="'perm-'+key" class="text-sm" x-text="label"></label>
                            </div>
                        </template>
                    </div>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="saveRole()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Saving...' : 'Save Role'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDeleteModal" x-transition class="fixed inset-0 bg-black/50" @click="closeDeleteModal()"></div>
        <div x-show="showDeleteModal" x-transition class="card relative w-full max-w-md p-6">
            <div class="text-center">
                <div class="mx-auto w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-4">
                    <i class="fas fa-trash text-red-600"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Delete Role</h3>
                <p class="text-sm text-[hsl(var(--muted-foreground))] mb-6">Are you sure you want to delete "<span x-text="deletingRole?.name"></span>"? This action cannot be undone.</p>
                <div class="flex gap-3">
                    <button @click="closeDeleteModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                    <button @click="deleteRole()" :disabled="deleting" class="btn btn-destructive btn-md flex-1">
                        <i class="fas" :class="deleting ? 'fa-spinner animate-spin' : 'fa-trash'"></i>
                        <span x-text="deleting ? 'Deleting...' : 'Delete'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function rolesApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true,
        saving: false,
        deleting: false,
        roles: [],
        availablePermissions: {},
        showModal: false,
        showDeleteModal: false,
        editingRole: null,
        deletingRole: null,
        form: { name: '', permissions: [] },
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],
        userPermissions: [], isAdmin: true,

        async init() {
            this.initDashboard();
            await this.fetchUserPermissions();
            await Promise.all([this.fetchRoles(), this.fetchPermissions()]);
        },

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

        async fetchRoles() {
            // Check permission first
            if (!this.hasPermission('view_roles') && !this.hasPermission('manage_roles')) {
                this.roles = []; this.loading = false; return;
            }
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/roles`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.roles = data.data || []; }
            } catch (e) { console.error('Error:', e); } finally { this.loading = false; }
        },

        async fetchPermissions() {
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/roles/permissions`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.availablePermissions = data.data || {}; }
            } catch (e) { console.error('Error:', e); }
        },

        getPermissionLabel(key) {
            return this.availablePermissions[key] || key;
        },

        openCreateModal() { this.editingRole = null; this.form = { name: '', permissions: [] }; this.showModal = true; },
        openEditModal(role) { this.editingRole = role; this.form = { name: role.name, permissions: role.permissions || [] }; this.showModal = true; },
        closeModal() { this.showModal = false; this.editingRole = null; },

        async saveRole() {
            // Check permission before saving
            const canCreate = this.hasPermission('create_roles') || this.hasPermission('manage_roles');
            const canEdit = this.hasPermission('edit_roles') || this.hasPermission('manage_roles');
            if ((!this.editingRole && !canCreate) || (this.editingRole && !canEdit)) {
                alert('You do not have permission to perform this action');
                return;
            }
            if (!this.form.name.trim()) { alert('Role name is required'); return; }
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const url = this.editingRole ? `${this.API_BASE_URL}/roles/${this.editingRole.id}` : `${this.API_BASE_URL}/roles`;
                const method = this.editingRole ? 'PUT' : 'POST';
                const res = await fetch(url, { method, headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
                const data = await res.json();
                if (data.success) { this.closeModal(); await this.fetchRoles(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.saving = false; }
        },

        openDeleteModal(role) { this.deletingRole = role; this.showDeleteModal = true; },
        closeDeleteModal() { this.showDeleteModal = false; this.deletingRole = null; },

        async deleteRole() {
            // Check permission before deleting
            if (!this.hasPermission('delete_roles') && !this.hasPermission('manage_roles')) {
                alert('You do not have permission to delete roles');
                return;
            }
            this.deleting = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/roles/${this.deletingRole.id}`, { method: 'DELETE', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.closeDeleteModal(); await this.fetchRoles(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.deleting = false; }
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
