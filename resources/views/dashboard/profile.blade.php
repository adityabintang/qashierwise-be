@extends('layouts.app')

@section('title', __('dashboard.profile_title'))

@section('content')
<div x-data="profileApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'profile'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => __('whatsapp.business_profile'), 'description' => __('dashboard.menu_business_profile')])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-6xl mx-auto" x-data="profileManager()">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Profile Form -->
                    <div class="card">
                        <div class="card-header !flex-row items-center justify-between border-b border-[hsl(var(--border))]">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                    <i class="fas fa-building text-emerald-600"></i>
                                </div>
                                <div>
                                    <h2 class="card-title">Profile Information</h2>
                                </div>
                            </div>
                            <button x-show="!loading" @click="toggleEdit" class="btn btn-sm" :class="editMode ? 'btn-outline' : 'btn-primary'">
                                <i class="fas" :class="editMode ? 'fa-times' : 'fa-edit'"></i>
                                <span x-text="editMode ? 'Cancel' : 'Edit'"></span>
                            </button>
                        </div>

                        <!-- Loading -->
                        <div x-show="loading" class="p-6 space-y-4">
                            <template x-for="i in 5" :key="'skeleton-'+i">
                                <div>
                                    <div class="skeleton h-3 w-20 mb-2"></div>
                                    <div class="skeleton h-10 w-full rounded-md"></div>
                                </div>
                            </template>
                        </div>

                        <!-- Form -->
                        <form x-show="!loading" @submit.prevent="saveProfile" class="p-6 space-y-4">
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">About</label>
                                <textarea x-model="profile.about" :disabled="!editMode" rows="2" class="input w-full resize-none" placeholder="Tell customers about your business..."></textarea>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Address</label>
                                <input type="text" x-model="profile.address" :disabled="!editMode" class="input w-full" placeholder="Business address">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Description</label>
                                <textarea x-model="profile.description" :disabled="!editMode" rows="3" class="input w-full resize-none" placeholder="Detailed description..."></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium mb-1.5 block">Email</label>
                                    <input type="email" x-model="profile.email" :disabled="!editMode" class="input w-full" placeholder="business@example.com">
                                </div>
                                <div>
                                    <label class="text-sm font-medium mb-1.5 block">Industry</label>
                                    <select x-model="profile.vertical" :disabled="!editMode" class="input w-full">
                                        <option value="">Select industry...</option>
                                        <option value="AUTO">Automotive</option>
                                        <option value="BEAUTY">Beauty & Salon</option>
                                        <option value="APPAREL">Clothing</option>
                                        <option value="EDU">Education</option>
                                        <option value="FINANCE">Finance</option>
                                        <option value="GROCERY">Food & Grocery</option>
                                        <option value="HEALTH">Health & Medical</option>
                                        <option value="RETAIL">Retail</option>
                                        <option value="RESTAURANT">Restaurant</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1.5 block">Websites</label>
                                <div class="space-y-2">
                                    <template x-for="(website, index) in profile.websites" :key="index">
                                        <div class="flex gap-2">
                                            <input type="url" x-model="profile.websites[index]" :disabled="!editMode" class="input flex-1" placeholder="https://example.com">
                                            <button x-show="editMode && profile.websites.length > 1" type="button" @click="removeWebsite(index)" class="btn btn-ghost btn-icon text-[hsl(var(--destructive))]">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </template>
                                    <button x-show="editMode" type="button" @click="addWebsite" class="btn btn-outline btn-sm w-full">
                                        <i class="fas fa-plus"></i>
                                        <span>Add Website</span>
                                    </button>
                                </div>
                            </div>
                            <div x-show="editMode" class="flex gap-3 pt-2">
                                <button type="submit" :disabled="saving" class="btn btn-primary btn-md flex-1">
                                    <i class="fas" :class="saving ? 'fa-spinner animate-spin' : 'fa-save'"></i>
                                    <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Phone Info -->
                        <div class="card">
                            <div class="card-header border-b border-[hsl(var(--border))]">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-phone-alt text-blue-600"></i>
                                    </div>
                                    <h2 class="card-title">Phone Information</h2>
                                </div>
                            </div>

                            <!-- Loading -->
                            <div x-show="loadingPhone" class="p-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <template x-for="i in 4" :key="'phone-skeleton-'+i">
                                        <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                            <div class="skeleton h-3 w-20 mb-2"></div>
                                            <div class="skeleton h-4 w-24"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Info -->
                            <div x-show="!loadingPhone" class="p-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="fas fa-phone text-emerald-500 text-sm"></i>
                                            <span class="text-xs text-[hsl(var(--muted-foreground))]">Phone Number</span>
                                        </div>
                                        <p class="font-medium text-sm" x-text="phoneInfo.display_phone_number || '-'">-</p>
                                    </div>
                                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="fas fa-shield-alt text-blue-500 text-sm"></i>
                                            <span class="text-xs text-[hsl(var(--muted-foreground))]">Verified</span>
                                        </div>
                                        <p class="font-medium text-sm" :class="phoneInfo.verified_name ? 'text-emerald-600' : 'text-[hsl(var(--muted-foreground))]'" x-text="phoneInfo.verified_name || 'Not Verified'">-</p>
                                    </div>
                                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="fas fa-star text-purple-500 text-sm"></i>
                                            <span class="text-xs text-[hsl(var(--muted-foreground))]">Quality</span>
                                        </div>
                                        <p class="font-medium text-sm capitalize" x-text="phoneInfo.quality_rating || '-'">-</p>
                                    </div>
                                    <div class="bg-[hsl(var(--muted)/0.5)] rounded-lg p-4">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="fas fa-envelope text-orange-500 text-sm"></i>
                                            <span class="text-xs text-[hsl(var(--muted-foreground))]">Msg Limit</span>
                                        </div>
                                        <p class="font-medium text-sm" x-text="phoneInfo.messaging_limit || '-'">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Picture -->
                        <div class="card">
                            <div class="card-header border-b border-[hsl(var(--border))]">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <i class="fas fa-image text-purple-600"></i>
                                    </div>
                                    <h2 class="card-title">Profile Picture</h2>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="flex items-center gap-4">
                                    <div class="h-20 w-20 rounded-xl overflow-hidden bg-gradient-to-br from-[hsl(var(--primary))] to-[hsl(var(--primary)/0.7)] flex items-center justify-center text-white text-2xl">
                                        <template x-if="profile.profile_picture_url">
                                            <img :src="profile.profile_picture_url" alt="Profile Picture" class="h-full w-full object-cover">
                                        </template>
                                        <template x-if="!profile.profile_picture_url">
                                            <i class="fas fa-building"></i>
                                        </template>
                                    </div>
                                    <div>
                                        <p class="text-sm text-[hsl(var(--muted-foreground))] mb-2">Upload your business profile picture</p>
                                        <input type="file" x-ref="profilePictureInput" @change="uploadProfilePicture" accept="image/jpeg,image/png,image/jpg" class="hidden">
                                        <button x-show="editMode" @click="$refs.profilePictureInput.click()" :disabled="uploadingPicture" class="btn btn-outline btn-sm">
                                            <i class="fas" :class="uploadingPicture ? 'fa-spinner animate-spin' : 'fa-camera'"></i>
                                            <span x-text="uploadingPicture ? 'Uploading...' : 'Change'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tips -->
                        <div class="card bg-gradient-to-br from-[hsl(var(--primary))] to-[hsl(var(--primary)/0.8)] border-0">
                            <div class="p-6 text-white">
                                <div class="flex items-start gap-4">
                                    <div class="h-10 w-10 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-lightbulb"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold mb-2 text-white">Profile Tips</h3>
                                        <ul class="text-sm space-y-1.5 text-white/90">
                                            <li class="flex items-start gap-2">
                                                <i class="fas fa-check text-xs mt-1"></i>
                                                <span>Keep your profile up-to-date</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <i class="fas fa-check text-xs mt-1"></i>
                                                <span>Add accurate business details</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <i class="fas fa-check text-xs mt-1"></i>
                                                <span>Use a professional profile picture</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function profileApp() {
    return {
        sidebarOpen: window.innerWidth >= 1024, 
        isMobile: window.innerWidth < 768,
        user: null, 
        notifications: [],
        init() {
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

function profileManager() {
    return {
        API_BASE_URL: window.location.origin + '/api',
        loading: true, loadingPhone: true, editMode: false, saving: false, uploadingPicture: false,
        profile: { about: '', address: '', description: '', email: '', vertical: '', websites: [''], profile_picture_url: '' },
        originalProfile: null,
        phoneInfo: {},

        async init() { await Promise.all([this.fetchProfile(), this.fetchPhoneInfo()]); },

        async fetchProfile() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/profile`, { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success && data.data) {
                    const p = Array.isArray(data.data) ? data.data[0] : data.data;
                    this.profile = {
                        about: p.about || '', address: p.address || '', description: p.description || '',
                        email: p.email || '', vertical: p.vertical || '',
                        websites: p.websites?.length > 0 ? p.websites : [''],
                        profile_picture_url: p.profile_picture_url || ''
                    };
                    this.originalProfile = JSON.parse(JSON.stringify(this.profile));
                }
            } catch (e) { console.error('Error:', e); }
            finally { this.loading = false; }
        },

        async fetchPhoneInfo() {
            this.loadingPhone = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/phone-info`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await res.json();
                this.phoneInfo = data.data || {};
            } catch (e) { console.error('Error:', e); }
            finally { this.loadingPhone = false; }
        },

        toggleEdit() {
            this.editMode = !this.editMode;
            if (!this.editMode) this.profile = JSON.parse(JSON.stringify(this.originalProfile));
        },

        addWebsite() { this.profile.websites.push(''); },
        removeWebsite(i) { this.profile.websites.splice(i, 1); },

        async uploadProfilePicture(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPEG or PNG)');
                return;
            }

            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            this.uploadingPicture = true;
            try {
                const token = localStorage.getItem('token');
                const formData = new FormData();
                formData.append('file', file);

                const res = await fetch(`${this.API_BASE_URL}/whatsapp/profile/picture`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.profile.profile_picture_url = data.data.profile_picture_url;
                    this.originalProfile.profile_picture_url = data.data.profile_picture_url;
                    alert('Profile picture updated successfully!');
                } else {
                    alert(data.message || 'Failed to upload profile picture');
                }
            } catch (e) {
                console.error('Error:', e);
                alert('Failed to upload profile picture');
            } finally {
                this.uploadingPicture = false;
                event.target.value = ''; // Reset input
            }
        },

        async saveProfile() {
            this.saving = true;
            try {
                const token = localStorage.getItem('token');
                const cleanProfile = { ...this.profile, websites: this.profile.websites.filter(w => w?.trim()) };
                if (!cleanProfile.websites.length) cleanProfile.websites = [];
                
                const res = await fetch(`${this.API_BASE_URL}/whatsapp/profile`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(cleanProfile)
                });
                const data = await res.json();
                if (data.success) {
                    this.originalProfile = JSON.parse(JSON.stringify(this.profile));
                    this.editMode = false;
                    alert('Profile saved successfully!');
                } else {
                    alert(data.message || 'Failed to save profile');
                }
            } catch (e) { console.error('Error:', e); alert('Failed to save profile'); }
            finally { this.saving = false; }
        }
    }
}
</script>
@endsection