@extends('layouts.app')

@section('title', 'Reservations - QashierWise')

@section('content')
<div x-data="reservationsApp()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'reservations'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Reservations', 'description' => 'Manage customer reservations'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- Tabs Navigation -->
                <div class="card">
                    <div class="flex border-b border-[hsl(var(--border))]">
                        <button 
                            @click="activeTab = 'reservations'" 
                            :class="activeTab === 'reservations' ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground'"
                            class="px-6 py-3 font-medium transition-colors hover:text-primary">
                            <i class="fas fa-calendar-check mr-2"></i>Reservations
                        </button>
                        <button 
                            @click="activeTab = 'flows'" 
                            :class="activeTab === 'flows' ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground'"
                            class="px-6 py-3 font-medium transition-colors hover:text-primary">
                            <i class="fab fa-whatsapp mr-2"></i>WhatsApp Flows
                        </button>
                    </div>
                </div>

                <!-- Reservations Tab -->
                <div x-show="activeTab === 'reservations'" x-transition>
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">Today</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.today"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                                <i class="fas fa-calendar-day text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">Reservations today</p>
                    </div>

                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">Upcoming</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.upcoming"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/20 flex items-center justify-center">
                                <i class="fas fa-clock text-emerald-600 dark:text-emerald-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">Future reservations</p>
                    </div>

                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">Pending</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.pending"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-yellow-100 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">Awaiting confirmation</p>
                    </div>

                    <div class="card stats-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground font-medium">This Month</p>
                                <p class="text-3xl font-bold mt-1" x-text="stats.total_this_month"></p>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 dark:text-purple-400 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-3">Total reservations</p>
                    </div>
                </div>

                <!-- Action Buttons & Filters... (kode lengkap terlalu panjang) -->
                </div>
                <!-- End Reservations Tab -->

                <!-- WhatsApp Flows Tab -->
                <div x-show="activeTab === 'flows'" x-transition>
                    <!-- Flow Management Header -->
                    <div class="card p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-2xl font-bold">WhatsApp Flow Management</h2>
                                <p class="text-muted-foreground mt-1">Create and manage reservation flows for WhatsApp</p>
                            </div>
                            <button @click="showCreateFlowModal = true" class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i>Create New Flow
                            </button>
                        </div>

                        <!-- Flows List -->
                        <div x-show="flowsLoading" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-3xl text-muted-foreground"></i>
                            <p class="mt-2 text-muted-foreground">Loading flows...</p>
                        </div>

                        <div x-show="!flowsLoading && flows.length === 0" class="text-center py-12">
                            <i class="fas fa-inbox text-5xl text-muted-foreground mb-4"></i>
                            <p class="text-muted-foreground">No flows created yet. Create your first flow to start collecting reservations via WhatsApp.</p>
                        </div>

                        <div x-show="!flowsLoading && flows.length > 0" class="space-y-4">
                            <template x-for="flow in flows" :key="flow.id">
                                <div class="card p-4 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-xl bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                                            <i class="fab fa-whatsapp text-green-600 dark:text-green-400 text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold" x-text="flow.name"></h3>
                                            <p class="text-sm text-muted-foreground">
                                                <span x-text="flow.status"></span> • 
                                                Created <span x-text="formatDate(flow.created_at)"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button 
                                            x-show="flow.status !== 'PUBLISHED'"
                                            @click="publishFlow(flow.id)" 
                                            class="btn btn-sm btn-primary">
                                            <i class="fas fa-rocket mr-2"></i>Publish
                                        </button>
                                        <button 
                                            @click="openSendFlowModal(flow)" 
                                            class="btn btn-sm btn-secondary">
                                            <i class="fas fa-paper-plane mr-2"></i>Send
                                        </button>
                                        <button 
                                            @click="deleteFlow(flow.id)" 
                                            class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <!-- End Flows Tab -->

            </div>
        </main>
    </div>

    <!-- Create Flow Modal -->
    <div x-show="showCreateFlowModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @click.self="showCreateFlowModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop>
                <h3 class="text-xl font-bold mb-4">Create New Flow</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Flow Name</label>
                        <input 
                            x-model="newFlow.name" 
                            type="text" 
                            class="input w-full" 
                            placeholder="e.g., Restaurant Reservation Flow">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Categories (comma separated)</label>
                        <input 
                            x-model="newFlow.categories" 
                            type="text" 
                            class="input w-full" 
                            placeholder="APPOINTMENT_BOOKING, MAKE_A_RESERVATION">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showCreateFlowModal = false" class="btn btn-secondary">Cancel</button>
                    <button @click="createFlow()" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i>Create Flow
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Flow Modal -->
    <div x-show="showSendFlowModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @click.self="showSendFlowModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
            <div class="relative bg-[hsl(var(--card))] rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop>
                <h3 class="text-xl font-bold mb-4">Send Flow to Customer</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Customer Phone Number</label>
                        <input 
                            x-model="sendFlowData.phone" 
                            type="tel" 
                            class="input w-full" 
                            placeholder="+62812xxxxxxxx">
                        <p class="text-xs text-muted-foreground mt-1">Include country code (e.g., +62 for Indonesia)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Message (Optional)</label>
                        <textarea 
                            x-model="sendFlowData.message" 
                            class="input w-full" 
                            rows="3" 
                            placeholder="Hi! Please fill out this form to make a reservation."></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showSendFlowModal = false" class="btn btn-secondary">Cancel</button>
                    <button @click="sendFlow()" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>Send Flow
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
function reservationsApp() {
    return {
        // Base properties for sidebar and header
        sidebarOpen: true,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],
        
        // Reservations-specific properties
        API_BASE_URL: window.location.origin + '/api',
        loading: false,
        reservations: [],
        stats: { today: 0, upcoming: 0, pending: 0, total_this_month: 0 },
        
        // Tabs
        activeTab: 'reservations',
        
        // Flow Management
        flows: [],
        flowsLoading: false,
        showCreateFlowModal: false,
        showSendFlowModal: false,
        newFlow: {
            name: '',
            categories: 'APPOINTMENT_BOOKING'
        },
        sendFlowData: {
            flow_id: '',
            phone: '',
            message: 'Hi! Please fill out this form to make a reservation.'
        },

        async init() {
            // Initialize sidebar state
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
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
                    
                    if (wasMobile && !this.isMobile) {
                        let savedState = localStorage.getItem('sidebarOpen');
                        this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
                    } else if (!wasMobile && this.isMobile) {
                        this.sidebarOpen = false;
                    }
                }, 150);
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
            
            // Load saved notifications
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try {
                    this.notifications = JSON.parse(savedNotifs);
                } catch (e) {
                    this.notifications = [];
                }
            }
            
            // Fetch reservations data
            await Promise.all([
                this.fetchStatistics(),
                this.fetchReservations(),
            ]);
            
            // Watch for tab changes
            this.$watch('activeTab', (tab) => {
                if (tab === 'flows' && this.flows.length === 0) {
                    this.fetchFlows();
                }
            });
        },

        addNotification(notif) {
            notif.id = Date.now() + Math.random();
            this.notifications.unshift(notif);
            if (this.notifications.length > 50) {
                this.notifications = this.notifications.slice(0, 50);
            }
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        async fetchStatistics() {
            const token = localStorage.getItem('token');
            const response = await fetch(`${this.API_BASE_URL}/reservations/statistics`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await response.json();
            if (data.success) {
                this.stats = data.data.statistics;
            }
        },

        async fetchReservations() {
            this.loading = true;
            const token = localStorage.getItem('token');
            const response = await fetch(`${this.API_BASE_URL}/reservations`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await response.json();
            if (data.success) {
                this.reservations = data.data.data;
            }
            this.loading = false;
        },

        // Flow Management Methods
        async fetchFlows() {
            this.flowsLoading = true;
            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/list`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await response.json();
                if (data.success) {
                    this.flows = data.data.flows || [];
                }
            } catch (error) {
                console.error('Error fetching flows:', error);
                this.addNotification({
                    type: 'error',
                    icon: 'fa-exclamation-circle',
                    color: 'red',
                    title: 'Error',
                    message: 'Failed to fetch flows',
                    time: new Date().toISOString()
                });
            }
            this.flowsLoading = false;
        },

        async createFlow() {
            if (!this.newFlow.name) {
                alert('Please enter a flow name');
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/create`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: this.newFlow.name,
                        categories: this.newFlow.categories.split(',').map(c => c.trim())
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-check-circle',
                        color: 'green',
                        title: 'Success',
                        message: 'Flow created successfully',
                        time: new Date().toISOString()
                    });
                    this.showCreateFlowModal = false;
                    this.newFlow = { name: '', categories: 'APPOINTMENT_BOOKING' };
                    await this.fetchFlows();
                } else {
                    alert(data.message || 'Failed to create flow');
                }
            } catch (error) {
                console.error('Error creating flow:', error);
                alert('Failed to create flow');
            }
        },

        async publishFlow(flowId) {
            if (!confirm('Publish this flow? Once published, the flow will be available for customers.')) {
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/publish`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ flow_id: flowId })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-rocket',
                        color: 'green',
                        title: 'Success',
                        message: 'Flow published successfully',
                        time: new Date().toISOString()
                    });
                    await this.fetchFlows();
                } else {
                    alert(data.message || 'Failed to publish flow');
                }
            } catch (error) {
                console.error('Error publishing flow:', error);
                alert('Failed to publish flow');
            }
        },

        openSendFlowModal(flow) {
            this.sendFlowData.flow_id = flow.id;
            this.sendFlowData.phone = '';
            this.showSendFlowModal = true;
        },

        async sendFlow() {
            if (!this.sendFlowData.phone) {
                alert('Please enter customer phone number');
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/send`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.sendFlowData)
                });
                const data = await response.json();
                
                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-paper-plane',
                        color: 'blue',
                        title: 'Success',
                        message: 'Flow sent to customer',
                        time: new Date().toISOString()
                    });
                    this.showSendFlowModal = false;
                    this.sendFlowData = {
                        flow_id: '',
                        phone: '',
                        message: 'Hi! Please fill out this form to make a reservation.'
                    };
                } else {
                    alert(data.message || 'Failed to send flow');
                }
            } catch (error) {
                console.error('Error sending flow:', error);
                alert('Failed to send flow');
            }
        },

        async deleteFlow(flowId) {
            if (!confirm('Delete this flow? This action cannot be undone.')) {
                return;
            }

            const token = localStorage.getItem('token');
            try {
                const response = await fetch(`${this.API_BASE_URL}/reservations/flows/delete`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ flow_id: flowId })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.addNotification({
                        type: 'success',
                        icon: 'fa-trash',
                        color: 'red',
                        title: 'Success',
                        message: 'Flow deleted successfully',
                        time: new Date().toISOString()
                    });
                    await this.fetchFlows();
                } else {
                    alert(data.message || 'Failed to delete flow');
                }
            } catch (error) {
                console.error('Error deleting flow:', error);
                alert('Failed to delete flow');
            }
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
        },
    };
}
</script>
@endsection
