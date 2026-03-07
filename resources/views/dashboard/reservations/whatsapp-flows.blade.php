@extends('layouts.app')

@section('title', 'WhatsApp Flow Reservasi')

@section('content')
<!-- Toast Container -->
<div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2" x-data="toastManager()">
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

<div x-data="waFlowsApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'reservations-whatsapp-flows'])

    <div class="flex-1 flex flex-col overflow-y-auto">
        @include('components.dashboard-header', ['title' => 'WA Flow Reservasi', 'description' => 'Kelola WhatsApp Flow untuk proses reservasi pelanggan'])

        <main class="flex-1 p-4 md:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl space-y-6">

                <!-- Back + Create button row -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="/dashboard/reservations" class="inline-flex items-center gap-2 rounded-lg border border-border/60 bg-background px-3 py-1.5 text-sm font-medium text-foreground shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-border hover:shadow">
                        <i class="fas fa-arrow-left text-xs text-muted-foreground"></i>
                        Kembali ke Reservasi
                    </a>
                    <button @click="createFlow()"
                            :disabled="creating"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-emerald-700 hover:-translate-y-0.5 disabled:opacity-60 disabled:cursor-not-allowed">
                        <i class="fas" :class="creating ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                        <span x-text="creating ? 'Membuat...' : 'Buat Flow Baru'"></span>
                    </button>
                </div>

                <!-- Info banner -->
                <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 flex gap-3">
                    <i class="fab fa-whatsapp text-emerald-600 text-xl flex-shrink-0 mt-0.5"></i>
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-blue-900">Cara kerja WA Flow Reservasi</p>
                        <p class="text-sm text-blue-700">
                            Buat flow &rarr; Publish flow &rarr; Kirim ke nomor pelanggan. Pelanggan mengisi form reservasi langsung di dalam WhatsApp tanpa perlu membuka browser.
                        </p>
                    </div>
                </div>

                <!-- Loading state -->
                <div x-show="loading" class="flex items-center justify-center py-16">
                    <div class="flex flex-col items-center gap-3 text-muted-foreground">
                        <i class="fas fa-spinner fa-spin text-2xl"></i>
                        <span class="text-sm">Memuat flows...</span>
                    </div>
                </div>

                <!-- Empty state -->
                <div x-show="!loading && flows.length === 0"
                     class="rounded-xl border border-dashed border-border/70 bg-card p-12 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted">
                        <i class="fab fa-whatsapp text-2xl text-muted-foreground"></i>
                    </div>
                    <h3 class="text-base font-semibold text-foreground">Belum ada WhatsApp Flow</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Klik "Buat Flow Baru" untuk membuat flow reservasi pertama Anda.</p>
                    <button @click="createFlow()"
                            :disabled="creating"
                            class="mt-5 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60">
                        <i class="fas fa-plus"></i> Buat Flow Baru
                    </button>
                </div>

                <!-- Flows list -->
                <div x-show="!loading && flows.length > 0" class="space-y-4">
                    <template x-for="flow in flows" :key="flow.id">
                        <div class="rounded-xl border border-border/70 bg-card shadow-sm overflow-hidden">
                            <div class="flex flex-wrap items-start justify-between gap-4 p-5">
                                <div class="flex items-start gap-4 min-w-0">
                                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl"
                                         :class="{
                                             'bg-emerald-100 text-emerald-600': flow.status === 'PUBLISHED',
                                             'bg-amber-100 text-amber-600': flow.status === 'DRAFT',
                                             'bg-blue-100 text-blue-600': flow.status === 'IN_REVIEW',
                                             'bg-zinc-100 text-zinc-500': !['PUBLISHED','DRAFT','IN_REVIEW'].includes(flow.status)
                                         }">
                                        <i class="fab fa-whatsapp text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-foreground" x-text="flow.name"></p>
                                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                  :class="{
                                                      'bg-emerald-100 text-emerald-700': flow.status === 'PUBLISHED',
                                                      'bg-amber-100 text-amber-700': flow.status === 'DRAFT',
                                                      'bg-blue-100 text-blue-700': flow.status === 'IN_REVIEW',
                                                      'bg-zinc-100 text-zinc-600': !['PUBLISHED','DRAFT','IN_REVIEW'].includes(flow.status)
                                                  }"
                                                  x-text="flow.status || 'UNKNOWN'">
                                            </span>
                                        </div>
                                        <p class="mt-0.5 text-xs text-muted-foreground">ID: <span class="font-mono" x-text="flow.id"></span></p>
                                        <p class="mt-0.5 text-xs text-muted-foreground" x-text="'Kategori: ' + (flow.categories ? flow.categories.join(', ') : '-')"></p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button x-show="flow.status === 'DRAFT'"
                                            @click="publishFlow(flow)"
                                            :disabled="flow._publishing"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition-all hover:bg-emerald-100 disabled:opacity-60">
                                        <i class="fas text-xs" :class="flow._publishing ? 'fa-spinner fa-spin' : 'fa-rocket'"></i>
                                        <span x-text="flow._publishing ? 'Publishing...' : 'Publish'"></span>
                                    </button>

                                    <button x-show="flow.status === 'PUBLISHED'"
                                            @click="openSendModal(flow)"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 transition-all hover:bg-blue-100">
                                        <i class="fas fa-paper-plane text-xs"></i>
                                        Kirim ke Pelanggan
                                    </button>

                                    <button @click="deleteFlow(flow)"
                                            :disabled="flow._deleting"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition-all hover:bg-red-100 disabled:opacity-60">
                                        <i class="fas text-xs" :class="flow._deleting ? 'fa-spinner fa-spin' : 'fa-trash'"></i>
                                        <span x-text="flow._deleting ? 'Menghapus...' : 'Hapus'"></span>
                                    </button>
                                </div>
                            </div>

                            <div x-show="flow.status === 'PUBLISHED'"
                                 class="border-t border-border/40 bg-emerald-50/60 px-5 py-3 flex items-center gap-2">
                                <i class="fas fa-check-circle text-emerald-500 text-xs"></i>
                                <p class="text-xs text-emerald-700 font-medium">Flow sudah dipublish dan siap dikirim ke pelanggan.</p>
                            </div>

                            <div x-show="flow.status === 'DRAFT'"
                                 class="border-t border-border/40 bg-amber-50/60 px-5 py-3 flex items-center gap-2">
                                <i class="fas fa-exclamation-circle text-amber-500 text-xs"></i>
                                <p class="text-xs text-amber-700 font-medium">Flow masih dalam status DRAFT. Publish terlebih dahulu agar bisa dikirim ke pelanggan.</p>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </main>
    </div>

    <!-- Send Flow Modal -->
    <div x-show="sendModal.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         x-cloak>
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl"
             @click.outside="sendModal.open = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between border-b border-border/60 px-6 py-4">
                <div>
                    <h3 class="text-base font-semibold">Kirim WA Flow ke Pelanggan</h3>
                    <p class="text-xs text-muted-foreground mt-0.5" x-text="'Flow: ' + (sendModal.flow ? sendModal.flow.name : '')"></p>
                </div>
                <button @click="sendModal.open = false" class="p-2 rounded-lg hover:bg-muted transition-colors">
                    <i class="fas fa-times text-sm text-muted-foreground"></i>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-foreground">Nomor WhatsApp Pelanggan</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground font-medium">+</span>
                        <input x-model="sendModal.phone"
                               type="tel"
                               placeholder="6281234567890"
                               class="w-full rounded-lg border border-border bg-background pl-7 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    </div>
                    <p class="text-xs text-muted-foreground">Masukkan nomor dengan kode negara tanpa tanda + (contoh: 6281234567890)</p>
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-foreground">Pesan Pembuka <span class="text-muted-foreground font-normal">(opsional)</span></label>
                    <textarea x-model="sendModal.message"
                              rows="3"
                              placeholder="Halo! Silakan isi form reservasi melalui link berikut..."
                              class="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 resize-none"></textarea>
                </div>
                <template x-if="sendModal.error">
                    <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2" x-text="sendModal.error"></p>
                </template>
            </div>
            <div class="flex justify-end gap-3 border-t border-border/60 px-6 py-4">
                <button @click="sendModal.open = false"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors">
                    Batal
                </button>
                <button @click="sendFlow()"
                        :disabled="sendModal.sending || !sendModal.phone.trim()"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60 disabled:cursor-not-allowed transition-all">
                    <i class="fas" :class="sendModal.sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                    <span x-text="sendModal.sending ? 'Mengirim...' : 'Kirim Flow'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div x-show="confirmDelete.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         x-cloak>
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-2xl"
             @click.outside="confirmDelete.open = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="p-6 text-center space-y-4">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                    <i class="fas fa-trash text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-foreground">Hapus Flow?</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Flow <span class="font-semibold" x-text="confirmDelete.flow ? confirmDelete.flow.name : ''"></span> akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button @click="confirmDelete.open = false"
                            class="flex-1 rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground hover:bg-muted transition-colors">
                        Batal
                    </button>
                    <button @click="confirmDeleteFlow()"
                            class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
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

document.addEventListener('alpine:init', () => {
    Alpine.store('toast', {
        addToast(message, type) {
            document.dispatchEvent(new CustomEvent('show-toast', { detail: { message, type } }));
        }
    });
});

document.addEventListener('show-toast', (e) => {
    const container = document.querySelector('[x-data="toastManager()"]');
    if (container && container._x_dataStack) {
        container._x_dataStack[0].addToast(e.detail.message, e.detail.type);
    }
});

function waFlowsApp() {
    return {
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        loading: true,
        creating: false,
        flows: [],
        sendModal: {
            open: false,
            flow: null,
            phone: '',
            message: '',
            sending: false,
            error: null,
        },
        confirmDelete: {
            open: false,
            flow: null,
        },

        async init() {
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
                try { this.user = JSON.parse(storedUser); } catch (e) { this.user = { name: 'User', email: 'user@example.com' }; }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }

            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try { this.notifications = JSON.parse(savedNotifs); } catch (e) { this.notifications = []; }
            }

            await this.loadFlows();
        },

        showToast(message, type = 'info') {
            Alpine.store('toast').addToast(message, type);
        },

        async loadFlows() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch('/api/whatsapp/flows', {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                    },
                });
                if (!res.ok) throw new Error(await res.text());
                const json = await res.json();
                this.flows = (json.data || []).map(f => ({ ...f, _publishing: false, _deleting: false }));
            } catch (e) {
                this.showToast('Gagal memuat daftar flows: ' + e.message, 'error');
            } finally {
                this.loading = false;
            }
        },

        async createFlow() {
            this.creating = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch('/api/whatsapp/flows', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || JSON.stringify(json));
                this.showToast('Flow berhasil dibuat!', 'success');
                await this.loadFlows();
            } catch (e) {
                this.showToast('Gagal membuat flow: ' + e.message, 'error');
            } finally {
                this.creating = false;
            }
        },

        async publishFlow(flow) {
            flow._publishing = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch('/api/whatsapp/flows/' + flow.id + '/publish', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || JSON.stringify(json));
                this.showToast('Flow berhasil dipublish!', 'success');
                await this.loadFlows();
            } catch (e) {
                this.showToast('Gagal publish flow: ' + e.message, 'error');
                flow._publishing = false;
            }
        },

        deleteFlow(flow) {
            this.confirmDelete.flow = flow;
            this.confirmDelete.open = true;
        },

        async confirmDeleteFlow() {
            const flow = this.confirmDelete.flow;
            this.confirmDelete.open = false;
            flow._deleting = true;
            try {
                const token = localStorage.getItem('token');
                const res = await fetch('/api/whatsapp/flows/' + flow.id, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || JSON.stringify(json));
                this.showToast('Flow berhasil dihapus.', 'success');
                this.flows = this.flows.filter(f => f.id !== flow.id);
            } catch (e) {
                this.showToast('Gagal menghapus flow: ' + e.message, 'error');
                flow._deleting = false;
            }
        },

        openSendModal(flow) {
            this.sendModal = {
                open: true,
                flow,
                phone: '',
                message: '',
                sending: false,
                error: null,
            };
        },

        async sendFlow() {
            const { flow, phone, message } = this.sendModal;
            if (!phone.trim()) return;

            this.sendModal.sending = true;
            this.sendModal.error = null;
            try {
                const token = localStorage.getItem('token');
                const body = { phone: phone.trim(), flow_id: flow.id };
                if (message.trim()) body.message = message.trim();

                const res = await fetch('/api/whatsapp/flows/send', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || JSON.stringify(json));
                this.sendModal.open = false;
                this.showToast('Flow berhasil dikirim ke +' + phone + '!', 'success');
            } catch (e) {
                this.sendModal.error = e.message;
            } finally {
                this.sendModal.sending = false;
            }
        },
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection
