@extends('layouts.app')

@section('title', 'Users Management - QashierWise')

@section('content')
<div x-data="posUsersApp()" x-init="initDashboard()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-users'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => 'Users Management', 'description' => 'Manage POS staff and their roles'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Staff Users</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Manage POS staff and assign roles</p>
                    </div>
                    <template x-if="hasPermission('create_users') || hasPermission('manage_users')">
                        <button @click="openCreateModal()" class="btn btn-primary btn-md">
                            <i class="fas fa-plus"></i>
                            <span>Add User</span>
                        </button>
                    </template>
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
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-semibold truncate" x-text="user.user?.name || 'Unknown'"></h3>
                                            <i x-show="user.user?.email_verified_at" class="fas fa-check-circle text-emerald-500 text-xs"></i>
                                        </div>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="user.role?.name || 'No role'"></p>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="user.store?.name || 'No store'"></p>
                                    </div>
                                    <span class="badge" :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'" x-text="user.is_active ? 'Active' : 'Inactive'"></span>
                                </div>
                                <div class="flex gap-2 mt-3 pt-3 border-t border-[hsl(var(--border))]">
                                    <template x-if="hasPermission('edit_users') || hasPermission('manage_users')">
                                        <button @click="openEditModal(user)" class="btn btn-outline btn-sm flex-1"><i class="fas fa-edit"></i> Edit</button>
                                    </template>
                                    <template x-if="hasPermission('edit_users') || hasPermission('manage_users')">
                                        <button @click="toggleStatus(user)" class="btn btn-outline btn-sm" :class="user.is_active ? 'text-amber-600' : 'text-emerald-600'">
                                            <i class="fas" :class="user.is_active ? 'fa-user-slash' : 'fa-user-check'"></i>
                                        </button>
                                    </template>
                                    <template x-if="hasPermission('delete_users') || hasPermission('manage_users')">
                                        <button @click="deleteUser(user)" class="btn btn-outline btn-sm text-red-600">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                                                        <div class="flex items-center gap-2">
                                                            <p class="font-medium" x-text="user.user?.name || 'Unknown'"></p>
                                                            <i x-show="user.user?.email_verified_at" class="fas fa-check-circle text-emerald-500 text-xs"></i>
                                                        </div>
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
                                                    <template x-if="hasPermission('edit_users') || hasPermission('manage_users')">
                                                        <button @click="openEditModal(user)" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></button>
                                                    </template>
                                                    <template x-if="hasPermission('edit_users') || hasPermission('manage_users')">
                                                        <button @click="toggleStatus(user)" class="btn btn-ghost btn-sm" :class="user.is_active ? 'text-amber-600' : 'text-emerald-600'">
                                                            <i class="fas" :class="user.is_active ? 'fa-user-slash' : 'fa-user-check'"></i>
                                                        </button>
                                                    </template>
                                                    <template x-if="hasPermission('delete_users') || hasPermission('manage_users')">
                                                        <button @click="deleteUser(user)" class="btn btn-ghost btn-sm text-red-600">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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
                <div x-show="!loading && users.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon"><i class="fas fa-users text-2xl"></i></div>
                        <h3 class="font-semibold mt-4">No POS users found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Add staff users to manage POS.</p>
                        <template x-if="hasPermission('create_users') || hasPermission('manage_users')">
                            <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4"><i class="fas fa-plus"></i> Add User</button>
                        </template>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create User Modal -->
    <div x-show="showCreateUserModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showCreateUserModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="closeCreateUserModal()"></div>
        <div x-show="showCreateUserModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full h-full sm:h-auto sm:max-w-md sm:max-h-[90vh] flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold">Create New User</h3>
                <button @click="closeCreateUserModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveUser()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Username <span class="text-red-500">*</span></label>
                    <input type="text" x-model="userForm.name" required class="input w-full min-h-[44px]" placeholder="Enter username">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Email <span class="text-red-500">*</span></label>
                    <input type="email" x-model="userForm.email" required class="input w-full min-h-[44px]" placeholder="Enter email address">
                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">User will verify email on first login</p>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Password <span class="text-red-500">*</span></label>
                    <input type="password" x-model="userForm.password" required minlength="8" class="input w-full min-h-[44px]" placeholder="Min 8 characters">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Store <span class="text-red-500">*</span></label>
                    <select x-model="userForm.store_id" required class="input w-full min-h-[44px]">
                        <option value="">Select store...</option>
                        <template x-for="store in stores" :key="store.id">
                            <option :value="store.id" x-text="store.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Role <span class="text-red-500">*</span></label>
                    <select x-model="userForm.role_id" required class="input w-full min-h-[44px]">
                        <option value="">Select role...</option>
                        <template x-for="role in roles" :key="role.id">
                            <option :value="role.id" x-text="role.name"></option>
                        </template>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="userForm.is_active" id="is_active" class="rounded border-[hsl(var(--input))]">
                    <label for="is_active" class="text-sm">Active</label>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeCreateUserModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="saveUser()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Creating...' : 'Create User'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->

    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showEditModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="closeEditModal()"></div>
        <div x-show="showEditModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full h-full sm:h-auto sm:max-w-md sm:max-h-[90vh] flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold">Edit POS User</h3>
                <button @click="closeEditModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveUser()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">User</label>
                    <div class="p-3 bg-[hsl(var(--muted))] rounded-lg">
                        <p class="font-medium" x-text="editingUser?.user?.name"></p>
                        <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="editingUser?.user?.email"></p>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Store <span class="text-red-500">*</span></label>
                    <select x-model="editForm.store_id" required class="input w-full min-h-[44px]">
                        <option value="">Select store...</option>
                        <template x-for="store in stores" :key="store.id">
                            <option :value="store.id" x-text="store.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Role <span class="text-red-500">*</span></label>
                    <select x-model="editForm.role_id" required class="input w-full min-h-[44px]">
                        <option value="">Select role...</option>
                        <template x-for="role in roles" :key="role.id">
                            <option :value="role.id" x-text="role.name"></option>
                        </template>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="editForm.is_active" id="edit_is_active" class="rounded border-[hsl(var(--input))]">
                    <label for="edit_is_active" class="text-sm">Active</label>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeEditModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="updateUser()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
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
        users: [], stores: [], roles: [],
        showCreateUserModal: false, showEditModal: false,
        editingUser: null,
        userForm: { name: '', email: '', password: '', store_id: '', role_id: '', is_active: true },
        editForm: { store_id: '', role_id: '', is_active: true },
        sidebarOpen: window.innerWidth >= 1024, isMobile: window.innerWidth < 768, user: null, notifications: [],
        userPermissions: [], isAdmin: true,

        async init() { this.initDashboard(); await this.fetchUserPermissions(); await Promise.all([this.fetchUsers(), this.fetchStores(), this.fetchRoles()]); },

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

        async fetchUsers() {
            // Check permission first
            if (!this.hasPermission('view_users') && !this.hasPermission('manage_users')) {
                this.users = []; this.loading = false; return;
            }
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
                const res = await fetch(`${this.API_BASE_URL}/roles`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.roles = data.data || []; }
            } catch (e) { console.error('Error:', e); }
        },

        openCreateModal() {
            this.userForm = { name: '', email: '', password: '', store_id: '', role_id: '', is_active: true };
            this.showCreateUserModal = true;
        },

        closeCreateUserModal() {
            this.showCreateUserModal = false;
            this.userForm = { name: '', email: '', password: '', store_id: '', role_id: '', is_active: true };
        },

        async saveUser() {
            // Check permission before creating
            if (!this.hasPermission('create_users') && !this.hasPermission('manage_users')) {
                alert('You do not have permission to create users');
                this.saving = false;
                return;
            }
            this.saving = true;
            try {
                const token = localStorage.getItem('token');

                // Create user first
                const userRes = await fetch(`${this.API_BASE_URL}/users/create-user`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: this.userForm.name,
                        email: this.userForm.email,
                        password: this.userForm.password
                    })
                });
                const userData = await userRes.json();

                if (!userData.success) {
                    alert(userData.message || 'Failed to create user');
                    this.saving = false;
                    return;
                }

                // Then create POS user
                const posUserRes = await fetch(`${this.API_BASE_URL}/users`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: userData.data.id,
                        store_id: this.userForm.store_id,
                        role_id: this.userForm.role_id,
                        is_active: this.userForm.is_active
                    })
                });
                const posUserData = await posUserRes.json();

                if (posUserData.success) {
                    this.closeCreateUserModal();
                    await this.fetchUsers();
                } else {
                    alert(posUserData.message || 'Failed to assign role');
                }
            } catch (e) {
                console.error('Error:', e);
                alert('Failed to create user');
            } finally {
                this.saving = false;
            }
        },

        openEditModal(user) {
            this.editingUser = user;
            this.editForm = { store_id: user.store_id, role_id: user.role_id, is_active: user.is_active };
            this.showEditModal = true;
        },

        closeEditModal() {
            this.showEditModal = false;
            this.editingUser = null;
        },

        async updateUser() {
            // Check permission before updating
            if (!this.hasPermission('edit_users') && !this.hasPermission('manage_users')) {
                alert('You do not have permission to edit users');
                this.saving = false;
                return;
            }
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/users/${this.editingUser.id}`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.editForm)
                });
                const data = await res.json();
                if (data.success) { this.closeEditModal(); await this.fetchUsers(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); } finally { this.saving = false; }
        },

        async toggleStatus(user) {
            // Check permission before toggling
            if (!this.hasPermission('edit_users') && !this.hasPermission('manage_users')) {
                alert('You do not have permission to modify users');
                return;
            }
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/users/${user.id}`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...user, is_active: !user.is_active })
                });
                const data = await res.json();
                if (data.success) { await this.fetchUsers(); } else { alert(data.message || 'Failed'); }
            } catch (e) { console.error('Error:', e); alert('Failed'); }
        },

        async deleteUser(user) {
            // Check permission before deleting
            if (!this.hasPermission('delete_users') && !this.hasPermission('manage_users')) {
                alert('You do not have permission to delete users');
                return;
            }
            if (!confirm(`Are you sure you want to delete ${user.user?.name || 'this user'}?`)) { return; }
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/users/${user.id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) { await this.fetchUsers(); } else { alert(data.message || 'Failed to delete user'); }
            } catch (e) { console.error('Error:', e); alert('Failed to delete user'); }
        },

        logout() { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/login'; },
        addNotification() {}, clearNotifications() {}, removeNotification() {}, formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
