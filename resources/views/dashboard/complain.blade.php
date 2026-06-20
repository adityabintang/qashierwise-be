@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Complain - QashierWise')

@section('content')
<div x-data="complaintApp()" x-init="init()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'complain'])

    <div class="flex-1 flex flex-col overflow-y-auto">
        @include('components.dashboard-header', ['title' => 'Complain', 'description' => 'Tangani keluhan pelanggan dari pengantaran'])

        <!-- Toast -->
        <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2">
            <template x-for="t in toasts" :key="t.id">
                <div x-show="t.visible" x-transition
                     class="px-4 py-3 rounded-lg shadow-lg min-w-[260px] max-w-[380px] text-sm"
                     :class="{
                        'bg-emerald-50 border border-emerald-200 text-emerald-800': t.type === 'success',
                        'bg-red-50 border border-red-200 text-red-800': t.type === 'error',
                        'bg-blue-50 border border-blue-200 text-blue-800': t.type === 'info'
                     }">
                    <span x-text="t.message"></span>
                </div>
            </template>
        </div>

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-5xl mx-auto space-y-5">

                <!-- Filter -->
                <div class="flex gap-2">
                    <button @click="filter = ''; load()" class="px-3 py-1.5 rounded-lg text-sm font-medium"
                            :class="filter === '' ? 'bg-emerald-600 text-white' : 'bg-white border border-[hsl(var(--border))]'">Semua</button>
                    <button @click="filter = 'open'; load()" class="px-3 py-1.5 rounded-lg text-sm font-medium"
                            :class="filter === 'open' ? 'bg-red-600 text-white' : 'bg-white border border-[hsl(var(--border))]'">Belum Selesai</button>
                    <button @click="filter = 'resolved'; load()" class="px-3 py-1.5 rounded-lg text-sm font-medium"
                            :class="filter === 'resolved' ? 'bg-emerald-600 text-white' : 'bg-white border border-[hsl(var(--border))]'">Selesai</button>
                </div>

                <!-- List -->
                <div class="space-y-3">
                    <template x-for="c in complaints" :key="c.id">
                        <div class="card p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-semibold" x-text="c.customer_name || 'Pelanggan'"></h4>
                                        <span class="badge text-xs" :class="c.status === 'open' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                              x-text="c.status === 'open' ? 'Belum Selesai' : 'Selesai'"></span>
                                    </div>
                                    <p class="text-sm text-[hsl(var(--muted-foreground))] mt-0.5">
                                        <span x-text="c.customer_phone || '-'"></span>
                                        <span x-show="c.order"> • Pesanan #<span x-text="c.order?.order_number"></span></span>
                                    </p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5" x-text="'Komplain: ' + formatDate(c.created_at)"></p>
                                    <div x-show="c.status === 'resolved'" class="mt-2 text-sm bg-emerald-50 border border-emerald-100 rounded-lg p-2">
                                        <span class="font-medium">Penyelesaian:</span> <span x-text="c.resolution_note"></span>
                                        <span class="block text-xs text-[hsl(var(--muted-foreground))] mt-0.5" x-text="'Selesai: ' + formatDate(c.resolved_at)"></span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 shrink-0">
                                    <a :href="c.whatsapp_contact_id ? ('/dashboard/messages?contact=' + c.whatsapp_contact_id) : '/dashboard/messages'"
                                       class="btn btn-outline btn-sm whitespace-nowrap">
                                        <i class="fab fa-whatsapp"></i> <span class="hidden sm:inline">Kirim Pesan</span>
                                    </a>
                                    <button x-show="c.status === 'open'" @click="openResolve(c)" class="btn btn-primary btn-sm whitespace-nowrap">
                                        <i class="fas fa-check"></i> Selesai
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="complaints.length === 0" class="text-center text-[hsl(var(--muted-foreground))] py-12">
                        Tidak ada komplain.
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Resolve modal -->
    <div x-show="showResolve" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showResolve = false"></div>
        <div class="card relative w-full max-w-md">
            <div class="p-4 border-b border-[hsl(var(--border))] flex items-center justify-between">
                <h3 class="font-semibold">Selesaikan Komplain</h3>
                <button @click="showResolve = false" class="btn btn-ghost btn-icon"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <p class="text-sm text-[hsl(var(--muted-foreground))]">
                    Pelanggan: <span class="font-medium" x-text="active?.customer_name || '-'"></span>
                </p>
                <div>
                    <label class="text-sm font-medium mb-1 block">Deskripsi Penyelesaian <span class="text-red-500">*</span></label>
                    <textarea x-model="resolutionNote" rows="4" class="input w-full" placeholder="Jelaskan bagaimana keluhan diselesaikan..."></textarea>
                </div>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">
                    Pesan penyelesaian akan dikirim ke pelanggan dan bot AI akan diaktifkan kembali untuk nomor ini.
                </p>
                <div class="flex gap-2 justify-end">
                    <button @click="showResolve = false" class="btn btn-outline">Batal</button>
                    <button @click="submitResolve()" :disabled="saving || !resolutionNote.trim()" class="btn btn-primary">
                        <span x-text="saving ? 'Memproses...' : 'Kirim & Selesaikan'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function complaintApp() {
    return {
        // Shared dashboard shell state (required by sidebar + header)
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        API: window.location.origin + '/api/complaints',
        complaints: [],
        filter: 'open',
        toasts: [],
        showResolve: false,
        active: null,
        resolutionNote: '',
        saving: false,

        initSidebar() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                const saved = localStorage.getItem('sidebarOpen');
                if (saved !== null) this.sidebarOpen = JSON.parse(saved);
            }
            this.$watch('sidebarOpen', v => { if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v)); });
            let t;
            window.addEventListener('resize', () => {
                clearTimeout(t);
                t = setTimeout(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth < 768;
                    if (wasMobile && !this.isMobile) {
                        const saved = localStorage.getItem('sidebarOpen');
                        this.sidebarOpen = saved !== null ? JSON.parse(saved) : true;
                    } else if (!wasMobile && this.isMobile) {
                        this.sidebarOpen = false;
                    }
                }, 150);
            });
            const u = localStorage.getItem('user');
            if (u) { try { this.user = JSON.parse(u); } catch (e) { this.user = { name: 'User' }; } }
        },

        token() { return localStorage.getItem('token'); },
        headers(json = false) {
            const h = { 'Authorization': 'Bearer ' + this.token(), 'Accept': 'application/json' };
            if (json) h['Content-Type'] = 'application/json';
            return h;
        },
        notify(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type, visible: true });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3500);
        },
        formatDate(s) { return s ? new Date(s).toLocaleString('id-ID') : ''; },

        async init() { this.initSidebar(); await this.load(); },

        async load() {
            const params = new URLSearchParams();
            if (this.filter) params.set('status', this.filter);
            try {
                const r = await fetch(`${this.API}?${params}`, { headers: this.headers() });
                const j = await r.json();
                if (j.success) this.complaints = j.data.data || [];
            } catch (e) { console.error(e); }
        },

        openResolve(c) { this.active = c; this.resolutionNote = ''; this.showResolve = true; },

        async submitResolve() {
            if (!this.resolutionNote.trim()) return;
            this.saving = true;
            try {
                const r = await fetch(`${this.API}/${this.active.id}/resolve`, {
                    method: 'POST', headers: this.headers(true),
                    body: JSON.stringify({ resolution_note: this.resolutionNote }),
                });
                const j = await r.json();
                if (j.success) {
                    this.notify('Komplain diselesaikan & pelanggan diberi tahu.');
                    this.showResolve = false;
                    await this.load();
                } else {
                    this.notify(j.message || 'Gagal menyelesaikan.', 'error');
                }
            } catch (e) { this.notify('Gagal menyelesaikan.', 'error'); }
            this.saving = false;
        },
    };
}
</script>
@endsection
