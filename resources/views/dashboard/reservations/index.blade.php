@extends('layouts.app')

@section('title', 'Reservasi')

@section('content')
<!-- Toast Notification Container -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] flex flex-col gap-2" x-data="toastManager()">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[300px] max-w-[400px]"
             :class="{
                 'bg-emerald-50 border border-emerald-200 text-emerald-800': toast.type === 'success',
                 'bg-red-50 border border-red-200 text-red-800': toast.type === 'error',
                 'bg-amber-50 border border-amber-200 text-amber-800': toast.type === 'warning',
                 'bg-blue-50 border border-blue-200 text-blue-800': toast.type === 'info'
             }">
            <div class="flex-shrink-0">
                <i class="fas text-lg"
                   :class="{
                       'fa-check-circle text-emerald-500': toast.type === 'success',
                       'fa-exclamation-circle text-red-500': toast.type === 'error',
                       'fa-exclamation-triangle text-amber-500': toast.type === 'warning',
                       'fa-info-circle text-blue-500': toast.type === 'info'
                   }"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium" x-text="toast.message"></p>
            </div>
            <button @click="removeToast(toast.id)" class="flex-shrink-0 p-1 rounded hover:bg-black/5 transition-colors">
                <i class="fas fa-times text-xs opacity-60"></i>
            </button>
        </div>
    </template>
</div>

<div x-data="reservationsApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'reservations'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto" :class="{ 'lg:ml-0': true }">
        <!-- Header -->
        @include('components.dashboard-header', ['title' => 'Reservasi', 'description' => 'Kelola reservasi pelanggan'])

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Pending Payment</p>
                                <p class="text-2xl font-bold text-amber-600" x-text="stats.pending_payment || 0"></p>
                            </div>
                            <i class="fas fa-clock text-2xl text-amber-500"></i>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Confirmed</p>
                                <p class="text-2xl font-bold text-blue-600" x-text="stats.confirmed || 0"></p>
                            </div>
                            <i class="fas fa-check-circle text-2xl text-blue-500"></i>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Hari Ini</p>
                                <p class="text-2xl font-bold text-green-600" x-text="stats.today || 0"></p>
                            </div>
                            <i class="fas fa-calendar-day text-2xl text-green-500"></i>
                        </div>
                    </div>
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Upcoming</p>
                                <p class="text-2xl font-bold text-purple-600" x-text="stats.upcoming || 0"></p>
                            </div>
                            <i class="fas fa-calendar-alt text-2xl text-purple-500"></i>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 justify-between">
                    <div class="flex gap-2">
                        <a href="/dashboard/reservations/calendar" class="btn btn-outline btn-md">
                            <i class="fas fa-calendar mr-2"></i>
                            Kalender
                        </a>
                        <a href="/dashboard/reservations/config" class="btn btn-outline btn-md">
                            <i class="fas fa-cog mr-2"></i>
                            Konfigurasi
                        </a>
                    </div>
                    <button @click="refreshData()" :disabled="loading" class="btn btn-primary btn-md">
                        <i class="fas fa-sync-alt mr-2" :class="{ 'animate-spin': loading }"></i>
                        Refresh
                    </button>
                </div>

                <!-- Filters -->
                <div class="card p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="text-xs font-medium text-gray-600 mb-1.5 block">Status</label>
                            <select x-model="filters.status" @change="loadReservations()" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="">Semua</option>
                                <option value="pending_payment">Pending Payment</option>
                                <option value="dp_confirmed">DP Confirmed</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600 mb-1.5 block">Tanggal</label>
                            <input type="date" x-model="filters.date" @change="loadReservations()" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-gray-600 mb-1.5 block">Pencarian</label>
                            <input type="text" x-model="filters.search" @input.debounce.500ms="loadReservations()" placeholder="Cari nama, nomor, email..." class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>
                </div>

                <!-- Reservations Table -->
                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Order ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Pelanggan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Tanggal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Tamu</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Meja</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-600 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <template x-if="loading && reservations.length === 0">
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center">
                                            <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-2"></i>
                                            <p class="text-gray-500">Memuat data...</p>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="!loading && reservations.length === 0">
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center">
                                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-2"></i>
                                            <p class="text-gray-500">Belum ada reservasi</p>
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="reservation in reservations" :key="reservation.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono" x-text="reservation.order_id"></td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900" x-text="reservation.customer_name"></div>
                                            <div class="text-xs text-gray-500" x-text="reservation.customer_phone"></div>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <div x-text="reservation.reservation_date_formatted"></div>
                                            <div class="text-xs text-gray-500" x-text="reservation.reservation_time || ''"></div>
                                        </td>
                                        <td class="px-4 py-3 text-sm" x-text="reservation.guest_count + ' orang'"></td>
                                        <td class="px-4 py-3 text-sm" x-text="reservation.table?.name || '-'"></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full"
                                                  :class="{
                                                      'bg-amber-100 text-amber-800': reservation.status === 'pending_payment',
                                                      'bg-cyan-100 text-cyan-700': reservation.status === 'confirmed' && reservation.payment_type === 'dp',
                                                      'bg-blue-100 text-blue-800': reservation.status === 'confirmed',
                                                      'bg-green-100 text-green-800': reservation.status === 'completed',
                                                      'bg-red-100 text-red-800': reservation.status === 'cancelled'
                                                  }"
                                                  x-text="reservation.status_label">
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium" x-text="'Rp ' + formatNumber(reservation.total_amount)"></td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button @click="viewDetails(reservation)" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <template x-if="reservation.status === 'confirmed'">
                                                    <button @click="completeReservation(reservation.id)" class="text-green-600 hover:text-green-800">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </template>
                                                <template x-if="reservation.can_be_cancelled">
                                                    <button @click="cancelReservation(reservation.id)" class="text-red-600 hover:text-red-800">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div x-show="pagination.last_page > 1" class="px-4 py-3 border-t flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            Menampilkan <span x-text="((pagination.current_page - 1) * pagination.per_page) + 1"></span>
                            - <span x-text="Math.min(pagination.current_page * pagination.per_page, pagination.total)"></span>
                            dari <span x-text="pagination.total"></span> data
                        </div>
                        <div class="flex gap-2">
                            <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="btn btn-sm btn-outline">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="btn btn-sm btn-outline">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Modal -->
            <div x-show="detailModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                 @click.self="detailModal.show = false">
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
                     @click.stop>
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold">Detail Reservasi</h3>
                            <button @click="detailModal.show = false" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <template x-if="detailModal.data">
                            <div class="space-y-4">
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-2">Informasi Pelanggan</h4>
                                    <div class="bg-gray-50 p-3 rounded space-y-1 text-sm">
                                        <div><span class="font-medium">Nama:</span> <span x-text="detailModal.data.customer_name"></span></div>
                                        <div><span class="font-medium">Telepon:</span> <span x-text="detailModal.data.customer_phone"></span></div>
                                        <div><span class="font-medium">Email:</span> <span x-text="detailModal.data.customer_email"></span></div>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-2">Detail Reservasi</h4>
                                    <div class="bg-gray-50 p-3 rounded space-y-1 text-sm">
                                        <div><span class="font-medium">Order ID:</span> <span class="font-mono" x-text="detailModal.data.order_id"></span></div>
                                        <div><span class="font-medium">Tanggal:</span> <span x-text="detailModal.data.reservation_date_formatted"></span></div>
                                        <div><span class="font-medium">Waktu:</span> <span x-text="detailModal.data.reservation_time || '-'"></span></div>
                                        <div><span class="font-medium">Jumlah Tamu:</span> <span x-text="detailModal.data.guest_count + ' orang'"></span></div>
                                        <div><span class="font-medium">Meja:</span> <span x-text="detailModal.data.table?.name || '-'"></span></div>
                                        <div><span class="font-medium">Status:</span> <span x-text="detailModal.data.status_label"></span></div>
                                    </div>
                                </div>

                                <div x-show="detailModal.data.notes" class="border-l-4 border-blue-500">
                                    <h4 class="font-semibold text-gray-700 mb-2 pl-3">Catatan Pelanggan</h4>
                                    <div class="bg-blue-50 p-3 rounded space-y-1 text-sm">
                                        <p class="text-gray-700 whitespace-pre-wrap" x-text="detailModal.data.notes"></p>
                                    </div>
                                </div>

                                <div x-show="detailModal.data.selected_products && detailModal.data.selected_products.length > 0">
                                    <h4 class="font-semibold text-gray-700 mb-2">Makanan yang Dipilih</h4>
                                    <div class="bg-gray-50 p-3 rounded space-y-2">
                                        <template x-for="product in detailModal.data.selected_products" :key="product.id">
                                            <div class="flex justify-between text-sm">
                                                <span x-text="product.name + ' x' + product.quantity"></span>
                                                <span x-text="'Rp ' + formatNumber(product.price * product.quantity)"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-2">Informasi Pembayaran</h4>
                                    <div class="bg-gray-50 p-3 rounded space-y-1 text-sm">
                                        <div><span class="font-medium">Jenis:</span> <span x-text="detailModal.data.payment_type_label"></span></div>
                                        <div><span class="font-medium">Total:</span> <span x-text="'Rp ' + formatNumber(detailModal.data.total_amount)"></span></div>
                                        <div><span class="font-medium">Dibayar:</span> <span x-text="'Rp ' + formatNumber(detailModal.data.paid_amount)"></span></div>
                                        <div x-show="detailModal.data.remaining_amount > 0">
                                            <span class="font-medium">Sisa:</span>
                                            <span class="text-red-600 font-semibold" x-text="'Rp ' + formatNumber(detailModal.data.remaining_amount)"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function toastManager() {
    return {
        toasts: [],
        addToast(message, type = 'info') {
            const id = Date.now();
            this.toasts.push({ id, message, type, visible: true });
            setTimeout(() => this.removeToast(id), 5000);
        },
        removeToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) {
                toast.visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }
        }
    };
}

function reservationsApp() {
    return {
        // Dashboard base properties
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        // Page-specific properties
        loading: false,
        reservations: [],
        stats: {},
        filters: {
            status: '',
            date: '',
            search: ''
        },
        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0
        },
        detailModal: {
            show: false,
            data: null
        },

        async init() {
            // Dashboard base init
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
            if (storedUser) {
                try { this.user = JSON.parse(storedUser); }
                catch (e) { this.user = { name: 'User', email: 'user@example.com' }; }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }

            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; }
            }

            // Page-specific init
            await this.loadStats();
            await this.loadReservations();
        },

        async loadStats() {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch('/api/reservations/stats', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load stats');

                const data = await response.json();
                if (data.success) {
                    this.stats = data.data;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        },

        async loadReservations(page = 1) {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const params = new URLSearchParams({
                    page: page,
                    per_page: this.pagination.per_page,
                    ...(this.filters.status && { status: this.filters.status }),
                    ...(this.filters.date && { date: this.filters.date }),
                    ...(this.filters.search && { search: this.filters.search })
                });

                const response = await fetch(`/api/reservations?${params}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to load reservations');

                const data = await response.json();
                if (data.success) {
                    this.reservations = data.data;
                    this.pagination = data.meta;
                }
            } catch (error) {
                console.error('Error loading reservations:', error);
                Alpine.store('toast').addToast('Gagal memuat data reservasi', 'error');
            } finally {
                this.loading = false;
            }
        },

        async refreshData() {
            await this.loadStats();
            await this.loadReservations(this.pagination.current_page);
        },

        changePage(page) {
            if (page >= 1 && page <= this.pagination.last_page) {
                this.loadReservations(page);
            }
        },

        viewDetails(reservation) {
            this.detailModal.data = reservation;
            this.detailModal.show = true;
        },

        async completeReservation(id) {
            if (!confirm('Tandai reservasi sebagai selesai?')) return;

            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`/api/reservations/${id}/complete`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    Alpine.store('toast').addToast(data.message || 'Reservasi berhasil diselesaikan', 'success');
                    await this.refreshData();
                } else {
                    Alpine.store('toast').addToast(data.message || 'Gagal menyelesaikan reservasi', 'error');
                }
            } catch (error) {
                console.error('Error completing reservation:', error);
                Alpine.store('toast').addToast('Terjadi kesalahan', 'error');
            }
        },

        async cancelReservation(id) {
            const reason = prompt('Alasan pembatalan (opsional):');
            if (reason === null) return;

            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`/api/reservations/${id}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reason })
                });

                const data = await response.json();
                if (data.success) {
                    Alpine.store('toast').addToast(data.message || 'Reservasi berhasil dibatalkan', 'success');
                    await this.refreshData();
                } else {
                    Alpine.store('toast').addToast(data.message || 'Gagal membatalkan reservasi', 'error');
                }
            } catch (error) {
                console.error('Error cancelling reservation:', error);
                Alpine.store('toast').addToast('Terjadi kesalahan', 'error');
            }
        },

        formatNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        },

        // Dashboard base methods
        addNotification(notif) {
            notif.id = Date.now() + Math.random();
            this.notifications.unshift(notif);
            if (this.notifications.length > 50) this.notifications = this.notifications.slice(0, 50);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        clearNotifications() {
            this.notifications = [];
            localStorage.removeItem('notifications');
        },

        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        formatNotificationTime(timestamp) {
            let date = new Date(timestamp);
            let diff = Math.floor((new Date() - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return date.toLocaleDateString();
        },

        logout() {
            let token = localStorage.getItem('token');
            if (token) {
                fetch(`${window.location.origin}/api/logout`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                }).finally(() => {
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    localStorage.removeItem('sidebarOpen');
                    localStorage.removeItem('notifications');
                    window.location.href = '/login';
                });
            } else {
                window.location.href = '/login';
            }
        }
    };
}

// Initialize Alpine store for toast
document.addEventListener('alpine:init', () => {
    Alpine.store('toast', {
        addToast(message, type) {
            const event = new CustomEvent('show-toast', {
                detail: { message, type }
            });
            document.dispatchEvent(event);
        }
    });
});

document.addEventListener('show-toast', (e) => {
    const container = document.querySelector('[x-data="toastManager()"]');
    if (container && container.__x) {
        container.__x.$data.addToast(e.detail.message, e.detail.type);
    }
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection
