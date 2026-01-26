@extends('layouts.app')

@section('title', __('pos.categories.title') . ' - QashierWise POS')

@section('content')
<div x-data="categoriesApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'pos-categories'])

    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => __('pos.categories.title'), 'description' => __('pos.categories.description')])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Header with Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Product Categories</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] hidden sm:block">Organize and manage product categories</p>
                    </div>
                    <button @click="openCreateModal()" class="btn btn-primary btn-md">
                        <i class="fas fa-plus"></i>
                        <span>Add Category</span>
                    </button>
                </div>

                <!-- Categories Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Loading Skeleton -->
                    <template x-if="loading">
                        <template x-for="i in 6" :key="'skeleton-'+i">
                            <div class="card p-5">
                                <div class="flex items-center gap-4">
                                    <div class="skeleton h-12 w-12 rounded-xl"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="skeleton h-4 w-24"></div>
                                        <div class="skeleton h-3 w-16"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <!-- Category Cards -->
                    <template x-if="!loading">
                        <template x-for="category in categories" :key="category.id">
                            <div class="card p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-xl bg-[hsl(var(--primary)/0.1)] flex items-center justify-center">
                                            <i class="fas fa-tags text-xl text-[hsl(var(--primary))]"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold" x-text="category.name"></h3>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))]" x-text="category.slug"></p>
                                        </div>
                                    </div>
                                    <span class="badge" :class="category.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'" x-text="category.is_active ? 'Active' : 'Inactive'"></span>
                                </div>
                                <div class="mt-4 pt-4 border-t border-[hsl(var(--border))]">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2 text-sm text-[hsl(var(--muted-foreground))]">
                                            <i class="fas fa-box"></i>
                                            <span x-text="(category.products_count || 0) + ' products'"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button @click="openEditModal(category)" class="btn btn-ghost btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="openDeleteModal(category)" class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Empty State -->
                <div x-show="!loading && categories.length === 0" class="card">
                    <div class="empty-state py-16">
                        <div class="empty-state-icon">
                            <i class="fas fa-tags text-2xl"></i>
                        </div>
                        <h3 class="font-semibold mt-4">No categories found</h3>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Create your first category to organize products.</p>
                        <button @click="openCreateModal()" class="btn btn-primary btn-md mt-4">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4">
        <div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
        <div x-show="showModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="card relative w-full h-full sm:h-auto sm:max-w-md sm:max-h-[90vh] overflow-hidden flex flex-col sm:rounded-lg rounded-none">
            <div class="p-4 sm:p-6 border-b border-[hsl(var(--border))] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold" x-text="editingCategory ? 'Edit Category' : 'Add New Category'"></h3>
                <button @click="closeModal()" class="btn btn-ghost btn-icon min-h-[44px] min-w-[44px]"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveCategory()" class="flex-1 overflow-y-auto scroll-area p-4 sm:p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.name" required class="input w-full min-h-[44px]" placeholder="Enter category name">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1.5 block">Description</label>
                    <textarea x-model="form.description" rows="3" class="input w-full min-h-[80px]" placeholder="Category description (optional)"></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="form.is_active" id="is_active" class="rounded border-[hsl(var(--input))]">
                    <label for="is_active" class="text-sm">Active</label>
                </div>
            </form>
            <div class="p-4 sm:p-6 border-t border-[hsl(var(--border))] flex gap-3 flex-shrink-0">
                <button type="button" @click="closeModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                <button @click="saveCategory()" :disabled="saving" class="btn btn-primary btn-md flex-1">
                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                    <span x-text="saving ? 'Saving...' : 'Save Category'"></span>
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
                <h3 class="text-lg font-semibold mb-2">Delete Category</h3>
                <p class="text-sm text-[hsl(var(--muted-foreground))] mb-2">Are you sure you want to delete "<span x-text="deletingCategory?.name"></span>"?</p>
                <p x-show="deletingCategory?.products_count > 0" class="text-sm text-amber-600 mb-4">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    This category has <span x-text="deletingCategory?.products_count"></span> products. You cannot delete it.
                </p>
                <div class="flex gap-3">
                    <button @click="closeDeleteModal()" class="btn btn-outline btn-md flex-1">Cancel</button>
                    <button @click="deleteCategory()" :disabled="deleting || deletingCategory?.products_count > 0" class="btn btn-destructive btn-md flex-1">
                        <i class="fas" :class="deleting ? 'fa-spinner animate-spin' : 'fa-trash'"></i>
                        <span x-text="deleting ? 'Deleting...' : 'Delete'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function categoriesApp() {
    return {
        API_BASE_URL: window.location.origin + '/api/pos',
        loading: true,
        saving: false,
        deleting: false,
        categories: [],
        showModal: false,
        showDeleteModal: false,
        editingCategory: null,
        deletingCategory: null,
        form: { name: '', description: '', is_active: true },
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        async init() {
            this.initSidebar();
            await this.fetchCategories();
        },

        initSidebar() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                let savedState = localStorage.getItem('sidebarOpen');
                if (savedState !== null) this.sidebarOpen = JSON.parse(savedState);
            }
            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });
            window.addEventListener('resize', () => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth < 768;
                if (wasMobile && !this.isMobile) {
                    let savedState = localStorage.getItem('sidebarOpen');
                    this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
                } else if (!wasMobile && this.isMobile) {
                    this.sidebarOpen = false;
                }
            });
            let storedUser = localStorage.getItem('user');
            if (storedUser) { try { this.user = JSON.parse(storedUser); } catch (e) { this.user = { name: 'User' }; } }
            else { this.user = { name: 'User' }; }
        },

        async fetchCategories() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/categories`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.success) {
                    this.categories = data.data.data || data.data;
                }
            } catch (e) {
                console.error('Error fetching categories:', e);
            } finally {
                this.loading = false;
            }
        },

        openCreateModal() {
            this.editingCategory = null;
            this.form = { name: '', description: '', is_active: true };
            this.showModal = true;
        },

        openEditModal(category) {
            this.editingCategory = category;
            this.form = { name: category.name, description: category.description || '', is_active: category.is_active };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.editingCategory = null;
        },

        async saveCategory() {
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const url = this.editingCategory
                    ? `${this.API_BASE_URL}/categories/${this.editingCategory.id}`
                    : `${this.API_BASE_URL}/categories`;
                const method = this.editingCategory ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method,
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await response.json();
                if (data.success) {
                    this.closeModal();
                    await this.fetchCategories();
                } else {
                    alert(data.message || 'Failed to save category');
                }
            } catch (e) {
                console.error('Error saving category:', e);
                alert('Failed to save category');
            } finally {
                this.saving = false;
            }
        },

        openDeleteModal(category) {
            this.deletingCategory = category;
            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            this.deletingCategory = null;
        },

        async deleteCategory() {
            if (this.deletingCategory?.products_count > 0) return;
            this.deleting = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/categories/${this.deletingCategory.id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.success) {
                    this.closeDeleteModal();
                    await this.fetchCategories();
                } else {
                    alert(data.message || 'Failed to delete category');
                }
            } catch (e) {
                console.error('Error deleting category:', e);
                alert('Failed to delete category');
            } finally {
                this.deleting = false;
            }
        },

        logout() {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        },

        addNotification() {},
        clearNotifications() {},
        removeNotification() {},
        formatNotificationTime() { return ''; }
    }
}
</script>
@endsection
