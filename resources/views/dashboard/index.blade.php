@extends('layouts.app')

@section('title', 'Dashboard - QashierWise')

@section('content')
<div x-data="dashboardApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'dashboard'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Dashboard', 'description' => 'Welcome back! Here\'s your WhatsApp Business overview.'])

        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto space-y-6">
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
                        <div class="card-header flex-row items-center justify-between">
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
                            <div x-show="!loading" x-cloak class="space-y-2">
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
        sidebarOpen: true, user: null, notifications: [],
        init() {
            let savedState = localStorage.getItem('sidebarOpen');
            if (savedState !== null) this.sidebarOpen = JSON.parse(savedState);
            this.$watch('sidebarOpen', v => localStorage.setItem('sidebarOpen', JSON.stringify(v)));
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
</script>
@endsection