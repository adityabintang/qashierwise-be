@extends('layouts.app')

@section('title', 'Business Profile - WhatsApp Business API')

@section('content')
<div x-data="{
    sidebarOpen: true,
    user: null,
    notifications: [],

    init() {
        // Load sidebar state from localStorage
        let savedSidebarState = localStorage.getItem('sidebarOpen');
        if (savedSidebarState !== null) {
            this.sidebarOpen = JSON.parse(savedSidebarState);
        }

        // Watch for sidebarOpen changes and save to localStorage
        this.$watch('sidebarOpen', value => {
            localStorage.setItem('sidebarOpen', JSON.stringify(value));
        });

        // Load user info
        let storedUser = localStorage.getItem('user');
        if (storedUser) {
            try {
                this.user = JSON.parse(storedUser);
            } catch (e) {
                this.user = { name: 'User', email: 'user@example.com' };
            }
        } else {
            this.user = { name: 'User', email: 'user@example.com' };
        }
    },

    logout() {
        let apiBaseUrl = window.location.origin + '/api';
        let token = localStorage.getItem('token');

        if (token) {
            fetch(`${apiBaseUrl}/logout`, {
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
}" class="min-h-screen flex">
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-slate-900 text-white transition-all duration-300 flex flex-col fixed lg:static inset-y-0 left-0 z-50">
        <!-- Logo -->
        <div class="p-6 flex items-center justify-between border-b border-slate-700">
            <div x-show="sidebarOpen" class="flex items-center space-x-3">
                <img src="{{ asset('images/logo.png') }}" class="h-8 rounded-lg" alt="Logo">
                <span class="text-xl font-bold">QashierWise</span>
            </div>
            <img x-show="!sidebarOpen" src="{{ asset('images/logo.png') }}" class="h-8 rounded-lg mx-auto" alt="Logo">
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-6">
            <a href="/dashboard" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-home text-xl w-6"></i>
                <span x-show="sidebarOpen">Dashboard</span>
            </a>
            <a href="/dashboard/contacts" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-address-book text-xl w-6"></i>
                <span x-show="sidebarOpen">Contacts</span>
            </a>
            <a href="/dashboard/messages" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-comments text-xl w-6"></i>
                <span x-show="sidebarOpen">Messages</span>
            </a>
            <a href="/dashboard/templates" class="flex items-center space-x-3 px-6 py-3 hover:bg-slate-800 transition">
                <i class="fas fa-file-alt text-xl w-6"></i>
                <span x-show="sidebarOpen">Templates</span>
            </a>
            <a href="/dashboard/profile" class="flex items-center space-x-3 px-6 py-3 bg-slate-800 transition">
                <i class="fas fa-building text-xl w-6"></i>
                <span x-show="sidebarOpen">Business Profile</span>
            </a>
        </nav>

        <!-- User Info & Logout -->
        <div class="p-4 border-t border-slate-700">
            <div x-show="sidebarOpen" class="mb-3">
                <div class="flex items-center space-x-3 px-2 py-2 bg-slate-800 rounded-lg mb-2">
                    <div class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-lg" x-text="user ? user.name.charAt(0).toUpperCase() : 'U'"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold text-sm truncate" x-text="user ? user.name : 'User'"></p>
                        <p class="text-slate-400 text-xs truncate" x-text="user ? user.email : ''"></p>
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
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 hover:bg-slate-800 rounded transition">
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
                    <h1 class="text-2xl font-bold text-gray-900">Business Profile</h1>
                    <p class="text-sm text-gray-600">Manage your WhatsApp Business profile information</p>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-auto p-6 bg-gray-50">
            <div class="max-w-7xl mx-auto">
    <!-- Main Content Grid - Two Column Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6" x-data="profileManager()">

        <!-- Left Column: Profile Information -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-green-50 to-white">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                        <i class="fas fa-building text-white"></i>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900">Profile Information</h2>
                </div>
                <button x-show="!loading" @click="toggleEdit" class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm font-medium shadow-sm">
                    <i class="fas mr-2" :class="editMode ? 'fa-times' : 'fa-edit'"></i>
                    <span x-text="editMode ? 'Cancel' : 'Edit'"></span>
                </button>
            </div>

            <!-- Shimmer Loading for Profile Form -->
            <div x-show="loading" class="p-6 space-y-5 animate-pulse">
                <div>
                    <div class="h-4 bg-gray-200 rounded w-16 mb-2"></div>
                    <div class="h-20 bg-gray-200 rounded-xl"></div>
                </div>
                <div>
                    <div class="h-4 bg-gray-200 rounded w-20 mb-2"></div>
                    <div class="h-12 bg-gray-200 rounded-xl"></div>
                </div>
                <div>
                    <div class="h-4 bg-gray-200 rounded w-24 mb-2"></div>
                    <div class="h-24 bg-gray-200 rounded-xl"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="h-4 bg-gray-200 rounded w-14 mb-2"></div>
                        <div class="h-12 bg-gray-200 rounded-xl"></div>
                    </div>
                    <div>
                        <div class="h-4 bg-gray-200 rounded w-18 mb-2"></div>
                        <div class="h-12 bg-gray-200 rounded-xl"></div>
                    </div>
                </div>
                <div>
                    <div class="h-4 bg-gray-200 rounded w-20 mb-2"></div>
                    <div class="h-12 bg-gray-200 rounded-xl"></div>
                </div>
            </div>

            <!-- Actual Profile Form -->
            <form x-show="!loading" @submit.prevent="saveProfile" class="p-6 space-y-5">
                <!-- About -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">About</label>
                    <textarea
                        x-model="profile.about"
                        :disabled="!editMode"
                        rows="2"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600 text-sm transition"
                        placeholder="Tell customers about your business..."></textarea>
                </div>

                <!-- Address -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <input
                        type="text"
                        x-model="profile.address"
                        :disabled="!editMode"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600 text-sm transition"
                        placeholder="Business address">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea
                        x-model="profile.description"
                        :disabled="!editMode"
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600 text-sm transition"
                        placeholder="Detailed business description..."></textarea>
                </div>

                <!-- Email & Industry - Two Columns -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input
                            type="email"
                            x-model="profile.email"
                            :disabled="!editMode"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600 text-sm transition"
                            placeholder="business@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Industry</label>
                        <select
                            x-model="profile.vertical"
                            :disabled="!editMode"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600 text-sm transition">
                            <option value="">Select industry...</option>
                            <option value="AUTO">Automotive</option>
                            <option value="BEAUTY">Beauty, Spa and Salon</option>
                            <option value="APPAREL">Clothing and Apparel</option>
                            <option value="EDU">Education</option>
                            <option value="ENTERTAIN">Entertainment</option>
                            <option value="EVENT_PLAN">Event Planning</option>
                            <option value="FINANCE">Finance and Banking</option>
                            <option value="GROCERY">Food and Grocery</option>
                            <option value="GOVT">Government</option>
                            <option value="HOTEL">Hotel and Lodging</option>
                            <option value="HEALTH">Health and Medical</option>
                            <option value="NONPROFIT">Non-profit</option>
                            <option value="PROF_SERVICES">Professional Services</option>
                            <option value="RETAIL">Shopping and Retail</option>
                            <option value="TRAVEL">Travel and Transportation</option>
                            <option value="RESTAURANT">Restaurant</option>
                            <option value="OTHER">Other</option>
                            <option value="NOT_A_BIZ">Not a Business</option>
                        </select>
                    </div>
                </div>

                <!-- Websites -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Websites</label>
                    <div class="space-y-2">
                        <template x-for="(website, index) in profile.websites" :key="index">
                            <div class="flex gap-2">
                                <input
                                    type="url"
                                    x-model="profile.websites[index]"
                                    :disabled="!editMode"
                                    class="flex-1 px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600 text-sm transition"
                                    placeholder="https://example.com">
                                <button
                                    x-show="editMode && profile.websites.length > 1"
                                    type="button"
                                    @click="removeWebsite(index)"
                                    class="px-4 py-3 bg-red-50 text-red-500 rounded-xl hover:bg-red-100 transition">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                        </template>
                        <button
                            x-show="editMode"
                            type="button"
                            @click="addWebsite"
                            class="w-full px-4 py-3 border-2 border-dashed border-gray-200 rounded-xl text-gray-500 hover:border-green-400 hover:text-green-600 transition text-sm">
                            <i class="fas fa-plus mr-2"></i> Add Website
                        </button>
                    </div>
                </div>

                <!-- Save Button -->
                <div x-show="editMode" class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 bg-green-500 hover:bg-green-600 text-white py-3 px-6 rounded-xl font-medium transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                        <i class="fas mr-2" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
                    </button>
                    <button
                        type="button"
                        @click="cancelEdit"
                        :disabled="saving"
                        class="px-6 py-3 border border-gray-200 rounded-xl text-gray-600 hover:bg-gray-50 transition disabled:opacity-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Phone Info & Profile Picture -->
        <div class="space-y-6">
            <!-- Phone Information Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
                            <i class="fas fa-phone-alt text-white"></i>
                        </div>
                        <h2 class="text-lg font-bold text-gray-900">Phone Information</h2>
                    </div>
                </div>

                <!-- Shimmer Loading for Phone Info -->
                <div x-show="loadingPhone" class="p-6 animate-pulse">
                    <div class="grid grid-cols-2 gap-4">
                        <template x-for="i in 4" :key="'phone-shimmer-'+i">
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                <div class="flex items-center space-x-3 mb-2">
                                    <div class="w-8 h-8 bg-gray-200 rounded-lg"></div>
                                    <div class="h-3 bg-gray-200 rounded w-20"></div>
                                </div>
                                <div class="h-4 bg-gray-300 rounded w-24 ml-11"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Actual Phone Info -->
                <div x-show="!loadingPhone" class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Phone Number -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <div class="flex items-center space-x-3 mb-2">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-phone text-green-600 text-sm"></i>
                                </div>
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Phone Number</span>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 ml-11" x-text="phoneInfo.display_phone_number || '-'">-</p>
                        </div>

                        <!-- Verified Name -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <div class="flex items-center space-x-3 mb-2">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-shield-alt text-blue-600 text-sm"></i>
                                </div>
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Verified Name</span>
                            </div>
                            <p class="text-sm font-semibold ml-11" :class="phoneInfo.verified_name ? 'text-green-600' : 'text-gray-400'">
                                <span x-text="phoneInfo.verified_name || 'Not Verified'">Not Verified</span>
                            </p>
                        </div>

                        <!-- Quality Rating -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <div class="flex items-center space-x-3 mb-2">
                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-star text-purple-600 text-sm"></i>
                                </div>
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Quality Rating</span>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 capitalize ml-11" x-text="phoneInfo.quality_rating || '-'">-</p>
                        </div>

                        <!-- Messaging Limit -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <div class="flex items-center space-x-3 mb-2">
                                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-envelope text-orange-600 text-sm"></i>
                                </div>
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Msg Limit</span>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 ml-11" x-text="phoneInfo.messaging_limit || '-'">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Picture Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-purple-50 to-white">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-purple-500 rounded-xl flex items-center justify-center">
                            <i class="fas fa-image text-white"></i>
                        </div>
                        <h2 class="text-lg font-bold text-gray-900">Profile Picture</h2>
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex items-center space-x-6">
                        <div class="w-24 h-24 bg-gradient-to-br from-green-400 to-blue-500 rounded-2xl flex items-center justify-center text-white text-3xl font-bold shadow-lg flex-shrink-0">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-600 mb-3">Upload or change your business profile picture</p>
                            <button
                                x-show="editMode"
                                class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition">
                                <i class="fas fa-camera mr-2"></i>Change Picture
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Tips Card -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl shadow-sm overflow-hidden text-white">
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-lightbulb text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold mb-3">Profile Tips</h3>
                            <ul class="text-sm text-green-100 space-y-2">
                                <li class="flex items-start">
                                    <i class="fas fa-check mr-2 mt-0.5 text-green-200"></i>
                                    <span>Keep your profile information up-to-date</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check mr-2 mt-0.5 text-green-200"></i>
                                    <span>Add accurate business details</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check mr-2 mt-0.5 text-green-200"></i>
                                    <span>Include all relevant websites</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check mr-2 mt-0.5 text-green-200"></i>
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

<script>
    function profileManager() {
        return {
            API_BASE_URL: window.location.origin + '/api',
            loading: true,
            loadingPhone: true,
            profile: {
                about: '',
                address: '',
                description: '',
                email: '',
                vertical: '',
                websites: ['']
            },
            phoneInfo: {},
            originalProfile: null,
            editMode: false,
            saving: false,

            async init() {
                await Promise.all([
                    this.fetchProfile(),
                    this.fetchPhoneInfo()
                ]);
            },

            async fetchProfile() {
                this.loading = true;
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/profile`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();

                    if (data.success && data.data) {
                        // Handle both formats - direct object or nested in data array
                        const profileData = Array.isArray(data.data) ? data.data[0] : data.data;

                        this.profile = {
                            about: profileData.about || '',
                            address: profileData.address || '',
                            description: profileData.description || '',
                            email: profileData.email || '',
                            vertical: profileData.vertical || '',
                            websites: profileData.websites && profileData.websites.length > 0
                                ? profileData.websites
                                : ['']
                        };
                        this.originalProfile = JSON.parse(JSON.stringify(this.profile));

                        console.log('Profile loaded from:', data.source || 'unknown');
                    }
                } catch (error) {
                    console.error('Error fetching profile:', error);
                } finally {
                    this.loading = false;
                }
            },

            async fetchPhoneInfo() {
                this.loadingPhone = true;
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/phone-info`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.phoneInfo = data.data || {};
                } catch (error) {
                    console.error('Error fetching phone info:', error);
                } finally {
                    this.loadingPhone = false;
                }
            },

            toggleEdit() {
                this.editMode = !this.editMode;
                if (!this.editMode) {
                    this.profile = JSON.parse(JSON.stringify(this.originalProfile));
                }
            },

            cancelEdit() {
                this.editMode = false;
                this.profile = JSON.parse(JSON.stringify(this.originalProfile));
            },

            addWebsite() {
                this.profile.websites.push('');
            },

            removeWebsite(index) {
                this.profile.websites.splice(index, 1);
            },

            async saveProfile() {
                this.saving = true;
                try {
                    const token = localStorage.getItem('token');

                    // Filter out empty websites
                    const cleanProfile = {
                        ...this.profile,
                        websites: this.profile.websites.filter(w => w && w.trim() !== '')
                    };

                    // Ensure websites is an array with at least empty array if no valid websites
                    if (!cleanProfile.websites || cleanProfile.websites.length === 0) {
                        delete cleanProfile.websites;
                    }

                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/profile`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(cleanProfile)
                    });

                    const responseData = await response.json();

                    if (response.ok && responseData.success) {
                        alert('Profile updated successfully!');

                        // Update the local profile with the saved data
                        this.profile = {
                            about: cleanProfile.about || '',
                            address: cleanProfile.address || '',
                            description: cleanProfile.description || '',
                            email: cleanProfile.email || '',
                            vertical: cleanProfile.vertical || '',
                            websites: cleanProfile.websites || ['']
                        };

                        // Ensure websites has at least one empty field for UI
                        if (!this.profile.websites || this.profile.websites.length === 0) {
                            this.profile.websites = [''];
                        }

                        this.originalProfile = JSON.parse(JSON.stringify(this.profile));
                        this.editMode = false;
                    } else {
                        // Show validation errors if any
                        if (responseData.errors) {
                            const errorMessages = Object.values(responseData.errors).flat().join('\n');
                            alert('Validation failed:\n' + errorMessages);
                        } else {
                            alert('Failed to update profile: ' + (responseData.message || 'Unknown error'));
                        }
                    }
                } catch (error) {
                    console.error('Error saving profile:', error);
                    alert('An error occurred while saving. Please try again.');
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
            </div>
        </main>
    </div>
</div>
@endsection
