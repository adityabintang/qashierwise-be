@extends('layouts.app')

@section('title', 'Dashboard - QashierWise')

@section('content')
<div x-data="dashboardApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'dashboard'])

    <div class="flex-1 flex flex-col min-h-screen" :class="{ 'lg:ml-0': true }">
        @include('components.dashboard-header', ['title' => 'Dashboard', 'description' => 'Welcome back! Here\'s your WhatsApp Business overview.'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Trial Expired Banner -->
                <div x-data="subscriptionStatus()" x-init="init()">
                    <div x-show="!loading && subscription.status === 'trial_expired'" x-cloak 
                         class="bg-gradient-to-r from-red-500 to-orange-500 rounded-xl p-4 md:p-6 text-white shadow-lg">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold">Trial Period Expired</h3>
                                    <p class="text-white/90 text-sm">Your free trial has ended. Upgrade now to continue using all features.</p>
                                </div>
                            </div>
                            <a href="/#pricing" class="btn bg-white text-red-600 hover:bg-white/90 font-semibold px-6 py-2 rounded-lg transition-colors flex-shrink-0">
                                <i class="fas fa-rocket mr-2"></i>Upgrade Now
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" x-data="dashboardStats()">
                    <!-- Loading Skeleton -->
                    <template x-for="i in 4" :key="'stat-skeleton-'+i">
                        <div x-show="loading" class="card stats-card p-5">
                            <div class="flex items-center justify-between">
                                <div class="space-y-2">
                                    <div class="skeleton h-3 w-24"></div>
                                    <div class="skeleton h-8 w-16"></div>
                                </div>
                                <div class="skeleton h-12 w-12 rounded-xl"></div>
                            </div>
                            <div class="skeleton h-3 w-32 mt-4"></div>
                        </div>
                    </template>

                    <!-- Total Contacts -->
                    <div x-show="!loading" class="card stats-card p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Total Contacts</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.totalContacts || '0'">0</p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-indigo-50 flex items-center justify-center">
                                <i class="fas fa-users text-xl text-indigo-500"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 mt-3 text-sm">
                            <span :class="stats.contactsGrowth >= 0 ? 'text-emerald-600' : 'text-red-500'" class="flex items-center font-medium">
                                <i class="fas mr-1 text-xs" :class="stats.contactsGrowth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                                <span x-text="(stats.contactsGrowth >= 0 ? '+' : '') + stats.contactsGrowth.toFixed(1) + '%'">0%</span>
                            </span>
                            <span class="text-[hsl(var(--muted-foreground))]">from last month</span>
                        </div>
                    </div>

                    <!-- Total Messages -->
                    <div x-show="!loading" class="card stats-card p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Total Messages</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.totalMessages || '0'">0</p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                                <i class="fas fa-comment-dots text-xl text-emerald-500"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 mt-3 text-sm">
                            <span :class="stats.messagesGrowth >= 0 ? 'text-emerald-600' : 'text-red-500'" class="flex items-center font-medium">
                                <i class="fas mr-1 text-xs" :class="stats.messagesGrowth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                                <span x-text="(stats.messagesGrowth >= 0 ? '+' : '') + stats.messagesGrowth.toFixed(1) + '%'">0%</span>
                            </span>
                            <span class="text-[hsl(var(--muted-foreground))]">from last week</span>
                        </div>
                    </div>

                    <!-- Templates -->
                    <div x-show="!loading" class="card stats-card p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Templates</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.totalTemplates || '0'">0</p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-cyan-50 flex items-center justify-center">
                                <i class="fas fa-file-alt text-xl text-cyan-500"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 mt-3 text-sm text-[hsl(var(--muted-foreground))]">
                            <span x-text="stats.approvedTemplates || '0'">0</span> approved
                        </div>
                    </div>

                    <!-- Unread Messages -->
                    <div x-show="!loading" class="card stats-card p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-[hsl(var(--muted-foreground))]">Unread Messages</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.unreadMessages || '0'">0</p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-orange-50 flex items-center justify-center">
                                <i class="fas fa-bell text-xl text-orange-500"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/dashboard/messages" class="text-sm text-[hsl(var(--primary))] hover:underline font-medium">View all messages →</a>
                        </div>
                    </div>
                </div>

                <!-- Weekly Chart -->
                <div class="card p-6" x-data="weeklyChart()">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-semibold">Weekly Messages</h2>
                            <p class="text-sm text-[hsl(var(--muted-foreground))]">Message statistics for the past 7 days</p>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <span class="text-[hsl(var(--muted-foreground))]">Incoming</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                <span class="text-[hsl(var(--muted-foreground))]">Outgoing</span>
                            </span>
                        </div>
                    </div>
                    <div class="relative" style="min-height: 280px;">
                        <!-- Loading Shimmer -->
                        <div x-show="loading" class="absolute inset-0 z-10 space-y-4">
                            <!-- Y-axis labels shimmer -->
                            <div class="flex gap-4 h-64">
                                <div class="flex flex-col justify-between py-2">
                                    <div class="skeleton h-3 w-8"></div>
                                    <div class="skeleton h-3 w-8"></div>
                                    <div class="skeleton h-3 w-8"></div>
                                    <div class="skeleton h-3 w-8"></div>
                                    <div class="skeleton h-3 w-8"></div>
                                </div>
                                <!-- Chart bars shimmer -->
                                <div class="flex-1 flex items-end justify-between gap-2 pb-8">
                                    <div class="skeleton w-full rounded-t-lg" style="height: 45%;"></div>
                                    <div class="skeleton w-full rounded-t-lg" style="height: 65%;"></div>
                                    <div class="skeleton w-full rounded-t-lg" style="height: 80%;"></div>
                                    <div class="skeleton w-full rounded-t-lg" style="height: 55%;"></div>
                                    <div class="skeleton w-full rounded-t-lg" style="height: 70%;"></div>
                                    <div class="skeleton w-full rounded-t-lg" style="height: 60%;"></div>
                                    <div class="skeleton w-full rounded-t-lg" style="height: 50%;"></div>
                                </div>
                            </div>
                            <!-- X-axis labels shimmer -->
                            <div class="flex justify-between px-12">
                                <div class="skeleton h-3 w-12"></div>
                                <div class="skeleton h-3 w-12"></div>
                                <div class="skeleton h-3 w-12"></div>
                                <div class="skeleton h-3 w-12"></div>
                                <div class="skeleton h-3 w-12"></div>
                                <div class="skeleton h-3 w-12"></div>
                                <div class="skeleton h-3 w-12"></div>
                            </div>
                        </div>
                        <!-- Actual Chart -->
                        <div id="weeklyMessagesChart" :class="loading ? 'opacity-0' : 'opacity-100'" class="transition-opacity duration-300"></div>
                    </div>
                    <!-- Summary Stats -->
                    <div class="grid grid-cols-3 gap-4 mt-6 pt-6 border-t border-[hsl(var(--border))]">
                        <!-- Loading Shimmer -->
                        <template x-if="loading">
                            <div class="col-span-3 grid grid-cols-3 gap-4">
                                <template x-for="i in 3" :key="'summary-skeleton-'+i">
                                    <div class="text-center space-y-2">
                                        <div class="skeleton h-8 w-16 mx-auto"></div>
                                        <div class="skeleton h-4 w-20 mx-auto"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <!-- Actual Stats -->
                        <div x-show="!loading" class="text-center">
                            <p class="text-2xl font-bold" x-text="chartSummary.totalIncoming">0</p>
                            <p class="text-sm text-[hsl(var(--muted-foreground))]">Incoming</p>
                        </div>
                        <div x-show="!loading" class="text-center">
                            <p class="text-2xl font-bold" x-text="chartSummary.totalOutgoing">0</p>
                            <p class="text-sm text-[hsl(var(--muted-foreground))]">Outgoing</p>
                        </div>
                        <div x-show="!loading" class="text-center">
                            <p class="text-2xl font-bold" x-text="chartSummary.total">0</p>
                            <p class="text-sm text-[hsl(var(--muted-foreground))]">Total</p>
                        </div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Messages -->
                    <div class="lg:col-span-2 card" x-data="recentMessages()">
                        <div class="card-header !flex-row items-center justify-between">
                            <div>
                                <h2 class="card-title">Recent Messages</h2>
                                <p class="card-description">Latest conversations</p>
                            </div>
                            <a href="/dashboard/messages" class="text-sm text-[hsl(var(--primary))] hover:underline font-medium">View all →</a>
                        </div>
                        <div class="card-content">
                            <div x-show="loading" class="space-y-3">
                                <template x-for="i in 5" :key="'msg-skeleton-'+i">
                                    <div class="flex items-start gap-3 p-3 rounded-lg bg-[hsl(var(--muted)/0.5)]">
                                        <div class="skeleton h-10 w-10 rounded-full"></div>
                                        <div class="flex-1 space-y-2">
                                            <div class="flex justify-between"><div class="skeleton h-4 w-32"></div><div class="skeleton h-3 w-16"></div></div>
                                            <div class="skeleton h-3 w-3/4"></div>
                                            <div class="flex gap-2"><div class="skeleton h-5 w-16 rounded-full"></div><div class="skeleton h-5 w-14 rounded-full"></div></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div x-show="!loading" class="space-y-2">
                                <template x-for="message in messages" :key="message.id">
                                    <div @click="viewMessage(message)" class="flex items-start gap-3 p-3 rounded-lg hover:bg-[hsl(var(--muted)/0.5)] transition-colors cursor-pointer">
                                        <!-- Avatar with name -->
                                        <img 
                                            x-show="message.contact_name && message.contact_name.trim()"
                                            :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(message.contact_name || 'U')}&backgroundColor=a855f7`" 
                                            :alt="message.contact_name"
                                            class="avatar"
                                        >
                                        <!-- Avatar without name -->
                                        <div 
                                            x-show="!message.contact_name || !message.contact_name.trim()"
                                            class="avatar flex items-center justify-center text-white font-bold"
                                            style="background: linear-gradient(135deg, #a855f7, #9333ea); font-size: 0.75rem;"
                                        >
                                            <span x-text="getCountryCode(message.from_number) || ''"></span>
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-medium truncate" x-text="message.contact_name || message.from_number">Unknown</p>
                                                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="formatTime(message.created_at)">Just now</p>
                                            </div>
                                            <p class="text-sm text-[hsl(var(--muted-foreground))] truncate mt-0.5" x-text="message.body || `[${message.type}]`">Message</p>
                                            <div class="flex items-center gap-2 mt-2">
                                                <span class="badge text-xs" :class="{'bg-blue-100 text-blue-700': message.status === 'sent', 'bg-emerald-100 text-emerald-700': message.status === 'delivered', 'bg-purple-100 text-purple-700': message.status === 'read', 'bg-red-100 text-red-700': message.status === 'failed'}">
                                                    <i class="fas fa-check mr-1 text-[10px]"></i><span x-text="message.status" class="capitalize"></span>
                                                </span>
                                                <span class="badge badge-secondary text-xs"><i :class="`fas fa-${getTypeIcon(message.type)} mr-1 text-[10px]`"></i><span x-text="message.type"></span></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="!messages || messages.length === 0" class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-inbox text-xl"></i></div>
                                    <p class="text-sm font-medium">No messages yet</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">Messages will appear here</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Subscription Status Card -->
                        <div class="card" x-data="subscriptionStatus()" x-init="init()">
                            <div class="card-header">
                                <h2 class="card-title">Subscription</h2>
                            </div>
                            <div class="card-content">
                                <!-- Always show content, no loading state -->
                                <div class="space-y-4">
                                    <!-- Plan Badge -->
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-lg flex items-center justify-center"
                                             :class="{
                                                 'bg-gray-100': subscription.plan_name === 'free_trial',
                                                 'bg-blue-100': subscription.plan_name === 'standard',
                                                 'bg-purple-100': subscription.plan_name === 'pro'
                                             }">
                                            <i class="fas"
                                               :class="{
                                                   'fa-gift text-gray-600': subscription.plan_name === 'free_trial',
                                                   'fa-star text-blue-600': subscription.plan_name === 'standard',
                                                   'fa-crown text-purple-600': subscription.plan_name === 'pro'
                                               }"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold capitalize" x-text="getPlanDisplayName()">Free Trial</p>
                                            <p class="text-xs text-[hsl(var(--muted-foreground))]" x-text="getStatusText()">Active</p>
                                        </div>
                                    </div>

                                    <!-- Trial Countdown (for trial users) -->
                                    <div x-show="subscription.status === 'trial'" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                        <div class="flex items-center gap-2 text-amber-700">
                                            <i class="fas fa-clock"></i>
                                            <span class="text-sm font-medium">
                                                <span x-text="subscription.trial_days_remaining || 0"></span> days remaining
                                            </span>
                                        </div>
                                        <p class="text-xs text-amber-600 mt-1">Upgrade to keep all features after trial ends</p>
                                    </div>

                                    <!-- Period End Date (for active subscriptions) -->
                                    <div x-show="subscription.status === 'active' && subscription.period_end" class="flex items-center gap-2 text-sm text-[hsl(var(--muted-foreground))]">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Next billing: <span x-text="formatDate(subscription.period_end)"></span></span>
                                    </div>

                                    <!-- Cancelled Notice -->
                                    <div x-show="subscription.status === 'cancelled'" class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                                        <div class="flex items-center gap-2 text-orange-700">
                                            <i class="fas fa-info-circle"></i>
                                            <span class="text-sm font-medium">Subscription cancelled</span>
                                        </div>
                                        <p class="text-xs text-orange-600 mt-1">
                                            Access until: <span x-text="formatDate(subscription.period_end)"></span>
                                        </p>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="pt-2 space-y-2">
                                        <!-- Upgrade Button (for trial/expired users) -->
                                        <a x-show="subscription.status === 'trial' || subscription.status === 'trial_expired' || subscription.status === 'expired'"
                                           href="/#pricing"
                                           class="flex items-center justify-center gap-2 w-full py-2.5 px-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all font-medium text-sm">
                                            <i class="fas fa-rocket"></i>
                                            <span>Upgrade Plan</span>
                                        </a>

                                        <!-- Manage Subscription Button (for active/cancelled subscriptions) -->
                                        <button x-show="subscription.status === 'active' || subscription.status === 'cancelled'"
                                                @click="openCustomerPortal()"
                                                :disabled="portalLoading"
                                                class="flex items-center justify-center gap-2 w-full py-2.5 px-4 bg-[hsl(var(--muted))] hover:bg-[hsl(var(--muted)/0.8)] text-[hsl(var(--foreground))] rounded-lg transition-colors font-medium text-sm disabled:opacity-50">
                                            <i class="fas" :class="portalLoading ? 'fa-spinner fa-spin' : 'fa-cog'"></i>
                                            <span x-text="portalLoading ? 'Loading...' : 'Manage Subscription'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Phone Info -->
                        <div class="card" x-data="phoneInfo()">
                            <div class="card-header"><h2 class="card-title">Phone Information</h2></div>
                            <div class="card-content space-y-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-lg bg-[hsl(var(--primary)/0.1)] flex items-center justify-center"><i class="fas fa-phone text-[hsl(var(--primary))]"></i></div>
                                    <div><p class="text-xs text-[hsl(var(--muted-foreground))]">Phone Number</p><p class="text-sm font-medium" x-text="info.display_phone_number || '-'">-</p></div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-lg bg-blue-50 flex items-center justify-center"><i class="fas fa-shield-alt text-blue-500"></i></div>
                                    <div><p class="text-xs text-[hsl(var(--muted-foreground))]">Verified</p><p class="text-sm font-medium" :class="info.verified_name ? 'text-[hsl(var(--primary))]' : 'text-[hsl(var(--muted-foreground))]'" x-text="info.verified_name || 'Not Verified'">-</p></div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-lg bg-purple-50 flex items-center justify-center"><i class="fas fa-star text-purple-500"></i></div>
                                    <div><p class="text-xs text-[hsl(var(--muted-foreground))]">Quality</p><p class="text-sm font-medium capitalize" x-text="info.quality_rating || '-'">-</p></div>
                                </div>
                            </div>
                        </div>
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header"><h2 class="card-title">Quick Actions</h2></div>
                            <div class="card-content space-y-2">
                                <a href="/dashboard/contacts" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                                    <div class="h-9 w-9 rounded-lg bg-blue-500 flex items-center justify-center"><i class="fas fa-users text-white"></i></div>
                                    <div><p class="text-sm font-medium">Manage Contacts</p><p class="text-xs text-[hsl(var(--muted-foreground))]">View and organize</p></div>
                                </a>
                                <a href="/dashboard/messages" class="flex items-center gap-3 p-3 rounded-lg bg-[hsl(var(--primary)/0.1)] hover:bg-[hsl(var(--primary)/0.15)] transition-colors">
                                    <div class="h-9 w-9 rounded-lg bg-[hsl(var(--primary))] flex items-center justify-center"><i class="fas fa-paper-plane text-white"></i></div>
                                    <div><p class="text-sm font-medium">Send Message</p><p class="text-xs text-[hsl(var(--muted-foreground))]">Start a conversation</p></div>
                                </a>
                                <a href="/dashboard/templates" class="flex items-center gap-3 p-3 rounded-lg bg-purple-50 hover:bg-purple-100 transition-colors">
                                    <div class="h-9 w-9 rounded-lg bg-purple-500 flex items-center justify-center"><i class="fas fa-file-alt text-white"></i></div>
                                    <div><p class="text-sm font-medium">Templates</p><p class="text-xs text-[hsl(var(--muted-foreground))]">Manage templates</p></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>


<script>
function dashboardApp() {
    return {
        sidebarOpen: true, 
        isMobile: window.innerWidth < 768,
        user: null, 
        notifications: [],
        init() {
            // Set initial sidebar state based on viewport
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                // Force sidebar to be open on desktop (ignore saved state for now)
                this.sidebarOpen = true;
                localStorage.setItem('sidebarOpen', 'true');
            }
            
            // Watch sidebar state changes (only save on desktop)
            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });
            
            // Handle resize events with debounce
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth < 768;
                    
                    // Auto-adjust sidebar when crossing breakpoint
                    if (wasMobile && !this.isMobile) {
                        // Switched from mobile to desktop
                        let savedState = localStorage.getItem('sidebarOpen');
                        this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
                    } else if (!wasMobile && this.isMobile) {
                        // Switched from desktop to mobile
                        this.sidebarOpen = false;
                    }
                }, 150);
            });
            
            let storedUser = localStorage.getItem('user');
            if (storedUser) { try { this.user = JSON.parse(storedUser); } catch (e) { this.user = { name: 'User', email: 'user@example.com' }; } }
            else { this.user = { name: 'User', email: 'user@example.com' }; }
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) { try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; } }
            window.addEventListener('whatsapp-message-received', (e) => {
                this.addNotification({ type: 'message', icon: 'fa-comment', color: 'purple', title: 'New Message', message: `From ${e.detail.contact?.name || e.detail.contact?.phone_number || 'Unknown'}`, time: new Date().toISOString() });
            });
        },
        addNotification(notif) { notif.id = Date.now() + Math.random(); this.notifications.unshift(notif); if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50); localStorage.setItem('notifications', JSON.stringify(this.notifications)); },
        clearNotifications() { this.notifications = []; localStorage.removeItem('notifications'); },
        removeNotification(id) { this.notifications = this.notifications.filter(n => n.id !== id); localStorage.setItem('notifications', JSON.stringify(this.notifications)); },
        formatNotificationTime(timestamp) { let date = new Date(timestamp), diff = Math.floor((new Date() - date) / 1000); if (diff < 60) return 'Just now'; if (diff < 3600) return Math.floor(diff / 60) + 'm ago'; if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'; return date.toLocaleDateString(); },
        logout() { let token = localStorage.getItem('token'); if (token) { fetch(`${window.location.origin}/api/logout`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } }).finally(() => this.clearAndRedirect()); } else { this.clearAndRedirect(); } },
        clearAndRedirect() { localStorage.removeItem('token'); localStorage.removeItem('user'); localStorage.removeItem('sidebarOpen'); localStorage.removeItem('notifications'); window.location.href = '/login'; }
    }
}

function dashboardStats() {
    return {
        API_BASE_URL: window.location.origin + '/api', loading: true,
        stats: { totalContacts: 0, totalMessages: 0, totalTemplates: 0, unreadMessages: 0, contactsGrowth: 0, messagesGrowth: 0, approvedTemplates: 0 },
        async init() { await this.fetchStats(); window.addEventListener('whatsapp-message-received', () => this.fetchStats()); },
        async fetchStats() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const [statsRes, templatesRes] = await Promise.all([
                    fetch(`${this.API_BASE_URL}/whatsapp/stats`, { headers: { 'Authorization': `Bearer ${token}` } }),
                    fetch(`${this.API_BASE_URL}/whatsapp/templates`, { headers: { 'Authorization': `Bearer ${token}` } })
                ]);
                const statsData = await statsRes.json();
                if (statsData.success && statsData.data) {
                    this.stats.totalContacts = statsData.data.total_contacts || 0;
                    this.stats.totalMessages = statsData.data.total_messages || 0;
                    this.stats.unreadMessages = statsData.data.unread_messages || 0;
                    this.stats.contactsGrowth = statsData.data.contacts_growth || 0;
                    this.stats.messagesGrowth = statsData.data.messages_growth || 0;
                }
                const templatesData = await templatesRes.json();
                this.stats.totalTemplates = templatesData.data?.length || 0;
                this.stats.approvedTemplates = templatesData.data?.filter(t => t.status === 'APPROVED').length || 0;
            } catch (e) { console.error('Error fetching stats:', e); }
            finally { this.loading = false; }
        }
    }
}


function weeklyChart() {
    return {
        API_BASE_URL: window.location.origin + '/api',
        loading: true,
        chart: null,
        chartData: [],
        chartSummary: { totalIncoming: 0, totalOutgoing: 0, total: 0 },
        
        init() {
            this.fetchChartData();
        },
        
        async fetchChartData() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/whatsapp/stats/weekly-chart`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const result = await response.json();
                
                if (result.success && result.data) {
                    this.chartData = result.data;
                    console.log('Chart Data:', this.chartData);
                    this.chartSummary.totalIncoming = this.chartData.reduce((sum, d) => sum + (d.incoming || 0), 0);
                    this.chartSummary.totalOutgoing = this.chartData.reduce((sum, d) => sum + (d.outgoing || 0), 0);
                    this.chartSummary.total = this.chartSummary.totalIncoming + this.chartSummary.totalOutgoing;
                    console.log('Chart Summary:', this.chartSummary);
                    
                    this.loading = false;
                    // Give more time for DOM and ApexCharts to be ready
                    setTimeout(() => this.renderChart(), 300);
                }
            } catch (e) {
                console.error('Error fetching chart:', e);
                this.loading = false;
            }
        },
        
        renderChart() {
            if (this.chartData.length === 0) return;
            
            // Check if ApexCharts is loaded
            if (typeof ApexCharts === 'undefined') {
                console.warn('ApexCharts not loaded yet, retrying...');
                setTimeout(() => this.renderChart(), 100);
                return;
            }
            
            const chartElement = document.querySelector("#weeklyMessagesChart");
            if (!chartElement) {
                console.warn('Chart element not found, retrying...');
                setTimeout(() => this.renderChart(), 100);
                return;
            }
            
            const incomingData = this.chartData.map(d => d.incoming || 0);
            const outgoingData = this.chartData.map(d => d.outgoing || 0);
            console.log('Incoming Data:', incomingData);
            console.log('Outgoing Data:', outgoingData);
            
            const options = {
                series: [
                    {
                        name: 'Incoming',
                        data: incomingData
                    },
                    {
                        name: 'Outgoing',
                        data: outgoingData
                    }
                ],
                chart: {
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    zoom: { enabled: false }
                },
                colors: ['#10b981', '#3b82f6'],
                dataLabels: { enabled: false },
                stroke: {
                    curve: 'smooth',
                    width: 2
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        opacityFrom: 0.4,
                        opacityTo: 0.1,
                    }
                },
                xaxis: {
                    categories: this.chartData.map(d => d.day),
                    labels: {
                        style: {
                            colors: '#64748b',
                            fontSize: '12px'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#64748b',
                            fontSize: '12px'
                        }
                    }
                },
                grid: {
                    borderColor: '#f1f5f9',
                    strokeDashArray: 4
                },
                legend: { show: false },
                tooltip: {
                    theme: 'dark',
                    y: {
                        formatter: function(val) {
                            return val + ' messages';
                        }
                    }
                }
            };
            
            if (this.chart) {
                this.chart.destroy();
            }
            
            this.chart = new ApexCharts(document.querySelector("#weeklyMessagesChart"), options);
            this.chart.render();
        }
    }
}

function recentMessages() {
    return {
        API_BASE_URL: window.location.origin + '/api', messages: [], loading: true,
        async init() { await this.fetchMessages(); },
        async fetchMessages() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/whatsapp/messages?limit=5`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await response.json();
                this.messages = data.data || [];
            } catch (e) { console.error('Error:', e); }
            finally { this.loading = false; }
        },
        formatTime(timestamp) { const diff = new Date() - new Date(timestamp), hours = Math.floor(diff / 3600000), minutes = Math.floor(diff / 60000); if (hours > 24) return new Date(timestamp).toLocaleDateString(); if (hours > 0) return `${hours}h ago`; if (minutes > 0) return `${minutes}m ago`; return 'Just now'; },
        getTypeIcon(type) { const icons = { text: 'comment', image: 'image', video: 'video', audio: 'microphone', document: 'file', location: 'map-marker-alt' }; return icons[type] || 'comment'; },
        viewMessage(message) { window.location.href = `/dashboard/messages?contact=${message.contact_id}`; },
        getCountryCode(phoneNumber) {
            if (!phoneNumber) return '';
            const match = phoneNumber.match(/^\+(\d{1,3})/);
            if (match) return '+' + match[1];
            const digits = phoneNumber.match(/^(\d{2,3})/);
            if (digits) return digits[1];
            return '';
        }
    }
}

function phoneInfo() {
    return {
        API_BASE_URL: window.location.origin + '/api', info: {},
        async init() {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/whatsapp/phone-info`, { headers: { 'Authorization': `Bearer ${token}` } });
                const data = await response.json();
                this.info = data.data || {};
            } catch (e) { console.error('Error:', e); }
        }
    }
}

function subscriptionStatus() {
    return {
        API_BASE_URL: window.location.origin + '/api',
        loading: true,
        portalLoading: false,
        error: false,
        subscription: {
            status: 'trial',
            plan_name: 'free_trial',
            trial_days_remaining: 14,
            period_end: null,
            cancelled_at: null
        },

        async init() {
            console.log('[Subscription] Initializing...');
            // Set timeout to prevent infinite loading
            setTimeout(() => {
                if (this.loading) {
                    console.warn('[Subscription] Loading timeout, showing default state');
                    this.loading = false;
                }
            }, 5000);
            await this.fetchSubscriptionStatus();
        },

        async fetchSubscriptionStatus() {
            this.loading = true;
            this.error = false;
            try {
                const token = localStorage.getItem('token');
                console.log('[Subscription] Token exists:', !!token);
                if (!token) {
                    console.warn('[Subscription] No token found');
                    this.loading = false;
                    return;
                }
                const response = await fetch(`${this.API_BASE_URL}/subscription/status`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                console.log('[Subscription] API response status:', response.status);
                const data = await response.json();
                console.log('[Subscription] API data:', data);
                if (data.success && data.data?.subscription) {
                    this.subscription = data.data.subscription;
                    console.log('[Subscription] Updated subscription:', this.subscription);
                } else {
                    console.warn('[Subscription] API returned no data, using defaults');
                }
            } catch (e) {
                console.error('[Subscription] Error:', e);
                this.error = true;
            } finally {
                this.loading = false;
                console.log('[Subscription] Loading complete, status:', this.subscription.status);
            }
        },

        getPlanDisplayName() {
            const names = {
                'free_trial': 'Free Trial',
                'standard': 'Standard',
                'pro': 'Pro'
            };
            return names[this.subscription.plan_name] || this.subscription.plan_name;
        },

        getStatusText() {
            const statuses = {
                'trial': 'Trial Active',
                'trial_expired': 'Trial Expired',
                'active': 'Active',
                'cancelled': 'Cancelled',
                'expired': 'Expired'
            };
            return statuses[this.subscription.status] || this.subscription.status;
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        async openCustomerPortal() {
            this.portalLoading = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${this.API_BASE_URL}/subscription/portal`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.success && data.data?.portal_url) {
                    window.open(data.data.portal_url, '_blank');
                } else {
                    console.error('Failed to get portal URL:', data.error?.message);
                    // More user-friendly error message
                    if (data.error?.code === 'PORTAL_URL_FAILED') {
                        alert('Subscription portal tidak tersedia saat ini. Untuk mengelola subscription, silakan hubungi support atau kunjungi Polar.sh dashboard.');
                    } else {
                        alert(data.error?.message || 'Gagal membuka subscription portal');
                    }
                }
            } catch (e) {
                console.error('Error opening customer portal:', e);
                alert('Gagal membuka subscription portal. Silakan coba lagi.');
            } finally {
                this.portalLoading = false;
            }
        }
    }
}
</script>
@endsection