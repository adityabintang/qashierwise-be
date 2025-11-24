@extends('layouts.dashboard')

@section('title', 'Business Profile - WhatsApp Business API')

@section('content')
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Business Profile</h1>
            <p class="mt-1 text-sm text-gray-600">Manage your WhatsApp Business profile information</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="profileManager()">
        <!-- Profile Form -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Profile Information</h2>
                <button @click="toggleEdit" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-medium">
                    <i class="fas mr-2" :class="editMode ? 'fa-times' : 'fa-edit'"></i>
                    <span x-text="editMode ? 'Cancel' : 'Edit'"></span>
                </button>
            </div>

            <form @submit.prevent="saveProfile" class="space-y-6">
                <!-- About -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">About</label>
                    <textarea
                        x-model="profile.about"
                        :disabled="!editMode"
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-50 disabled:text-gray-600"
                        placeholder="Tell customers about your business..."></textarea>
                </div>

                <!-- Address -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <input
                        type="text"
                        x-model="profile.address"
                        :disabled="!editMode"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-50 disabled:text-gray-600"
                        placeholder="Business address">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea
                        x-model="profile.description"
                        :disabled="!editMode"
                        rows="4"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-50 disabled:text-gray-600"
                        placeholder="Detailed business description..."></textarea>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input
                        type="email"
                        x-model="profile.email"
                        :disabled="!editMode"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-50 disabled:text-gray-600"
                        placeholder="business@example.com">
                </div>

                <!-- Vertical (Industry) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Industry Vertical</label>
                    <select
                        x-model="profile.vertical"
                        :disabled="!editMode"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-50 disabled:text-gray-600">
                        <option value="">Select industry...</option>
                        <option value="Automotive">Automotive</option>
                        <option value="Beauty">Beauty, Spa and Salon</option>
                        <option value="Clothing">Clothing and Apparel</option>
                        <option value="Education">Education</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Event">Event Planning and Service</option>
                        <option value="Finance">Finance and Banking</option>
                        <option value="Food">Food and Grocery</option>
                        <option value="Health">Health and Medical</option>
                        <option value="Hotel">Hotel and Lodging</option>
                        <option value="Professional">Professional Services</option>
                        <option value="Shopping">Shopping and Retail</option>
                        <option value="Travel">Travel and Transportation</option>
                        <option value="Restaurant">Restaurant</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Websites -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Websites</label>
                    <div class="space-y-3">
                        <template x-for="(website, index) in profile.websites" :key="index">
                            <div class="flex gap-2">
                                <input
                                    type="url"
                                    x-model="profile.websites[index]"
                                    :disabled="!editMode"
                                    class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-50 disabled:text-gray-600"
                                    placeholder="https://example.com">
                                <button
                                    x-show="editMode && profile.websites.length > 1"
                                    type="button"
                                    @click="removeWebsite(index)"
                                    class="px-4 py-3 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </template>
                        <button
                            x-show="editMode"
                            type="button"
                            @click="addWebsite"
                            class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-purple-500 hover:text-purple-600 transition">
                            <i class="fas fa-plus mr-2"></i> Add Website
                        </button>
                    </div>
                </div>

                <!-- Save Button -->
                <div x-show="editMode" class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 px-6 rounded-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas mr-2" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
                    </button>
                    <button
                        type="button"
                        @click="cancelEdit"
                        :disabled="saving"
                        class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition disabled:opacity-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Phone Info Sidebar -->
        <div class="space-y-6">
            <!-- Phone Number Card -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Phone Information</h2>
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-purple-50 rounded-full p-3">
                            <i class="fas fa-phone text-purple-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Phone Number</p>
                            <p class="text-sm font-semibold text-gray-900" x-text="phoneInfo.display_phone_number || '-'">-</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="bg-blue-100 rounded-full p-3">
                            <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Verified Name</p>
                            <p class="text-sm font-semibold" :class="phoneInfo.verified_name ? 'text-purple-600' : 'text-gray-400'">
                                <span x-text="phoneInfo.verified_name || 'Not Verified'">Not Verified</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="bg-purple-100 rounded-full p-3">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Quality Rating</p>
                            <p class="text-sm font-semibold text-gray-900 capitalize" x-text="phoneInfo.quality_rating || '-'">-</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <div class="bg-orange-100 rounded-full p-3">
                            <i class="fas fa-ban text-orange-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Messaging Limit</p>
                            <p class="text-sm font-semibold text-gray-900" x-text="phoneInfo.messaging_limit || '-'">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Image -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Profile Picture</h2>
                <div class="text-center">
                    <div class="w-32 h-32 mx-auto bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full flex items-center justify-center text-white text-4xl font-bold mb-4">
                        <i class="fas fa-building"></i>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Upload or change your business profile picture</p>
                    <button
                        x-show="editMode"
                        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg text-sm font-medium transition">
                        <i class="fas fa-camera mr-2"></i>Change Picture
                    </button>
                </div>
            </div>

            <!-- Help Card -->
            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl shadow-lg p-6">
                <div class="flex items-start space-x-3">
                    <div class="bg-purple-600 rounded-full p-2 text-white">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Profile Tips</h3>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Keep your profile information up-to-date</li>
                            <li>• Add accurate business details</li>
                            <li>• Include all relevant websites</li>
                            <li>• Use a professional profile picture</li>
                        </ul>
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
                await this.fetchProfile();
                await this.fetchPhoneInfo();
            },

            async fetchProfile() {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/profile`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();

                    if (data.data) {
                        this.profile = {
                            about: data.data.about || '',
                            address: data.data.address || '',
                            description: data.data.description || '',
                            email: data.data.email || '',
                            vertical: data.data.vertical || '',
                            websites: data.data.websites || ['']
                        };
                        this.originalProfile = JSON.parse(JSON.stringify(this.profile));
                    }
                } catch (error) {
                    console.error('Error fetching profile:', error);
                }
            },

            async fetchPhoneInfo() {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/phone-info`, {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    this.phoneInfo = data.data || {};
                } catch (error) {
                    console.error('Error fetching phone info:', error);
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
                        websites: this.profile.websites.filter(w => w.trim() !== '')
                    };

                    const response = await fetch(`${this.API_BASE_URL}/whatsapp/profile`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(cleanProfile)
                    });

                    if (response.ok) {
                        const data = await response.json();
                        alert('Profile updated successfully!');
                        this.originalProfile = JSON.parse(JSON.stringify(this.profile));
                        this.editMode = false;
                        await this.fetchProfile();
                    } else {
                        const errorData = await response.json();
                        alert('Failed to update profile: ' + (errorData.message || 'Unknown error'));
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
@endsection
