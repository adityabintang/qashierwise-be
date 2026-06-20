@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Delivery - QashierWise')

@section('content')
<div x-data="deliveryApp()" x-init="init()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    @include('components.dashboard-sidebar', ['activePage' => 'delivery'])

    <div class="flex-1 flex flex-col overflow-y-auto">
        @include('components.dashboard-header', ['title' => 'Delivery', 'description' => 'Konfigurasi pengantaran, driver, dan bukti pengiriman'])

        <!-- Toast -->
        <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2">
            <template x-for="t in toasts" :key="t.id">
                <div x-show="t.visible" x-transition
                     class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[260px] max-w-[380px] text-sm"
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
            <div class="max-w-6xl mx-auto space-y-6">

                <!-- Tabs -->
                <div class="flex gap-2 border-b border-[hsl(var(--border))]">
                    <button @click="tab = 'drivers'" class="px-4 py-2 text-sm font-medium -mb-px border-b-2 transition-colors"
                            :class="tab === 'drivers' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-[hsl(var(--muted-foreground))]'">
                        Driver
                    </button>
                    <button @click="tab = 'proofs'; loadProofs()" class="px-4 py-2 text-sm font-medium -mb-px border-b-2 transition-colors"
                            :class="tab === 'proofs' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-[hsl(var(--muted-foreground))]'">
                        Bukti Pengiriman
                    </button>
                    <button @click="tab = 'config'" class="px-4 py-2 text-sm font-medium -mb-px border-b-2 transition-colors"
                            :class="tab === 'config' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-[hsl(var(--muted-foreground))]'">
                        Konfigurasi
                    </button>
                </div>

                <!-- ============ DRIVERS ============ -->
                <div x-show="tab === 'drivers'" class="space-y-4">
                    <div class="card p-5">
                        <h3 class="font-semibold mb-4" x-text="driverForm.id ? 'Edit Driver' : 'Tambah Driver'"></h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm font-medium mb-1 block">Nama <span class="text-red-500">*</span></label>
                                <input type="text" x-model="driverForm.name" class="input w-full min-h-[44px]" placeholder="Nama driver">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1 block">WhatsApp <span class="text-red-500">*</span></label>
                                <input type="text" x-model="driverForm.phone" class="input w-full min-h-[44px]" placeholder="08xxxxxxxxxx">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1 block">Kendaraan</label>
                                <input type="text" x-model="driverForm.vehicle" class="input w-full min-h-[44px]" placeholder="Honda Beat">
                            </div>
                            <div>
                                <label class="text-sm font-medium mb-1 block">Plat Nomor</label>
                                <input type="text" x-model="driverForm.plate_number" class="input w-full min-h-[44px]" placeholder="B 1234 XX">
                            </div>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <button @click="saveDriver()" :disabled="saving" class="btn btn-primary" x-text="driverForm.id ? 'Simpan' : 'Tambah'"></button>
                            <button x-show="driverForm.id" @click="resetDriverForm()" class="btn btn-outline">Batal</button>
                        </div>
                    </div>

                    <div class="card overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-[hsl(var(--muted)/0.5)]">
                                <tr>
                                    <th class="text-left p-3 font-medium">Nama</th>
                                    <th class="text-left p-3 font-medium">WhatsApp</th>
                                    <th class="text-left p-3 font-medium hidden sm:table-cell">Kendaraan</th>
                                    <th class="text-center p-3 font-medium">Status</th>
                                    <th class="text-right p-3 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="d in drivers" :key="d.id">
                                    <tr class="border-t border-[hsl(var(--border))]">
                                        <td class="p-3 font-medium" x-text="d.name"></td>
                                        <td class="p-3" x-text="d.phone"></td>
                                        <td class="p-3 hidden sm:table-cell" x-text="(d.vehicle || '-') + (d.plate_number ? ' • ' + d.plate_number : '')"></td>
                                        <td class="p-3 text-center">
                                            <span class="badge text-xs" :class="d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'" x-text="d.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                        </td>
                                        <td class="p-3 text-right whitespace-nowrap">
                                            <button @click="editDriver(d)" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></button>
                                            <button @click="toggleDriver(d)" class="btn btn-ghost btn-sm" :title="d.is_active ? 'Nonaktifkan' : 'Aktifkan'"><i class="fas" :class="d.is_active ? 'fa-toggle-on' : 'fa-toggle-off'"></i></button>
                                            <button @click="deleteDriver(d)" class="btn btn-ghost btn-sm text-red-600"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="drivers.length === 0"><td colspan="5" class="p-6 text-center text-[hsl(var(--muted-foreground))]">Belum ada driver. Tambahkan di atas.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============ PROOFS ============ -->
                <div x-show="tab === 'proofs'" class="space-y-4">
                    <div class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="text-sm font-medium mb-1 block">Filter Driver</label>
                            <select x-model="proofFilterDriver" @change="loadProofs()" class="input min-h-[44px]">
                                <option value="">Semua Driver</option>
                                <template x-for="d in drivers" :key="d.id">
                                    <option :value="d.id" x-text="d.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-1 block">Status</label>
                            <select x-model="proofFilterStatus" @change="loadProofs()" class="input min-h-[44px]">
                                <option value="">Semua</option>
                                <option value="out_for_delivery">Sedang Diantar</option>
                                <option value="delivered">Diterima</option>
                                <option value="complaint">Komplain</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="o in proofs" :key="o.id">
                            <div class="card overflow-hidden">
                                <div class="aspect-video bg-gray-100 flex items-center justify-center">
                                    <template x-if="o.proof_image_url">
                                        <img :src="o.proof_image_url" alt="bukti" class="w-full h-full object-cover cursor-pointer" @click="window.open(o.proof_image_url,'_blank')">
                                    </template>
                                    <template x-if="!o.proof_image_url">
                                        <span class="text-gray-400 text-sm">Belum ada bukti</span>
                                    </template>
                                </div>
                                <div class="p-3 space-y-1 text-sm">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold" x-text="'#' + o.order_number"></span>
                                        <span class="badge text-xs" :class="fulfillClass(o.fulfillment_status)" x-text="fulfillLabel(o.fulfillment_status)"></span>
                                    </div>
                                    <div class="text-[hsl(var(--muted-foreground))]" x-text="'Pembeli: ' + (o.customer_name || '-')"></div>
                                    <div class="text-[hsl(var(--muted-foreground))]" x-text="'Driver: ' + (o.delivery_driver?.name || o.courier_name || '-')"></div>
                                    <div class="text-[hsl(var(--muted-foreground))] text-xs" x-text="o.delivered_at ? ('Diterima: ' + formatDate(o.delivered_at)) : ''"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="proofs.length === 0" class="text-center text-[hsl(var(--muted-foreground))] py-10">Belum ada data pengiriman.</div>
                </div>

                <!-- ============ CONFIG ============ -->
                <div x-show="tab === 'config'" class="card p-5 max-w-xl space-y-5">
                    <!-- Toggle 1: master switch -->
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium">Aktifkan Delivery</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))]">Jika nonaktif, fitur Delivery di halaman AI Agent tidak bisa diaktifkan.</p>
                        </div>
                        <button type="button" @click="config.is_active = !config.is_active"
                                :class="config.is_active ? 'bg-purple-600' : 'bg-gray-300'"
                                class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors flex-shrink-0">
                            <span :class="config.is_active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
                        </button>
                    </div>

                    <!-- Toggle 2: proof required -->
                    <div class="flex items-start justify-between gap-4 border-t border-[hsl(var(--border))] pt-4">
                        <div>
                            <p class="text-sm font-medium">Driver wajib upload bukti pengiriman</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))]">Foto bukti wajib diunggah sebelum konfirmasi pengantaran.</p>
                        </div>
                        <button type="button" @click="config.proof_required = !config.proof_required"
                                :class="config.proof_required ? 'bg-purple-600' : 'bg-gray-300'"
                                class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors flex-shrink-0">
                            <span :class="config.proof_required ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
                        </button>
                    </div>

                    <!-- Toggle 3: address required -->
                    <div class="flex items-start justify-between gap-4 border-t border-[hsl(var(--border))] pt-4">
                        <div>
                            <p class="text-sm font-medium">Driver wajib input alamat penerima</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))]">Lokasi penerima (peta) wajib ditandai sebelum konfirmasi.</p>
                        </div>
                        <button type="button" @click="config.address_required = !config.address_required"
                                :class="config.address_required ? 'bg-purple-600' : 'bg-gray-300'"
                                class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors flex-shrink-0">
                            <span :class="config.address_required ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
                        </button>
                    </div>

                    <!-- Input: ongkir -->
                    <div class="border-t border-[hsl(var(--border))] pt-4">
                        <label class="text-sm font-medium mb-1 block">Ongkos Kirim (Rp)</label>
                        <input type="number" min="0" step="500" x-model.number="config.default_ongkir" class="input w-full min-h-[44px]" placeholder="Contoh: 10000">
                        <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Ongkir diatur di sini dan otomatis dipakai AI Agent saat pelanggan memilih delivery.</p>
                    </div>

                    <button @click="saveConfig()" :disabled="saving" class="btn btn-primary">Simpan Konfigurasi</button>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
function deliveryApp() {
    return {
        // Shared dashboard shell state (required by sidebar + header)
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 768,
        user: null,
        notifications: [],

        API: window.location.origin + '/api/delivery',
        tab: 'drivers',
        saving: false,
        toasts: [],
        config: { is_active: true, default_ongkir: 0, proof_required: true, address_required: true, notes: '' },
        drivers: [],
        driverForm: { id: null, name: '', phone: '', vehicle: '', plate_number: '' },
        proofs: [],
        proofFilterDriver: '',
        proofFilterStatus: '',

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

        async init() {
            this.initSidebar();
            await this.loadConfig();
            await this.loadDrivers();
        },

        notify(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type, visible: true });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3500);
        },

        formatDate(s) { return s ? new Date(s).toLocaleString('id-ID') : ''; },
        fulfillLabel(s) {
            return ({ out_for_delivery: 'Diantar', delivered: 'Diterima', complaint: 'Komplain', confirmed: 'Dikonfirmasi', awaiting_confirmation: 'Menunggu' })[s] || (s || '-');
        },
        fulfillClass(s) {
            return ({
                out_for_delivery: 'bg-amber-100 text-amber-700',
                delivered: 'bg-emerald-100 text-emerald-700',
                complaint: 'bg-red-100 text-red-700',
                confirmed: 'bg-blue-100 text-blue-700'
            })[s] || 'bg-gray-100 text-gray-600';
        },

        async loadConfig() {
            try {
                const r = await fetch(`${this.API}/config`, { headers: this.headers() });
                const j = await r.json();
                if (j.success) this.config = Object.assign(this.config, j.data);
            } catch (e) { console.error(e); }
        },
        async saveConfig() {
            this.saving = true;
            try {
                const r = await fetch(`${this.API}/config`, { method: 'PUT', headers: this.headers(true), body: JSON.stringify(this.config) });
                const j = await r.json();
                j.success ? this.notify('Konfigurasi disimpan') : this.notify(j.message || 'Gagal', 'error');
            } catch (e) { this.notify('Gagal menyimpan', 'error'); }
            this.saving = false;
        },

        async loadDrivers() {
            try {
                const r = await fetch(`${this.API}/drivers`, { headers: this.headers() });
                const j = await r.json();
                if (j.success) this.drivers = j.data;
            } catch (e) { console.error(e); }
        },
        resetDriverForm() { this.driverForm = { id: null, name: '', phone: '', vehicle: '', plate_number: '' }; },
        editDriver(d) { this.driverForm = { id: d.id, name: d.name, phone: d.phone, vehicle: d.vehicle || '', plate_number: d.plate_number || '' }; },
        async saveDriver() {
            if (!this.driverForm.name || !this.driverForm.phone) { this.notify('Nama & WhatsApp wajib diisi', 'error'); return; }
            this.saving = true;
            const isEdit = !!this.driverForm.id;
            const url = isEdit ? `${this.API}/drivers/${this.driverForm.id}` : `${this.API}/drivers`;
            try {
                const r = await fetch(url, { method: isEdit ? 'PUT' : 'POST', headers: this.headers(true), body: JSON.stringify(this.driverForm) });
                const j = await r.json();
                if (j.success) { this.notify(isEdit ? 'Driver diperbarui' : 'Driver ditambahkan'); this.resetDriverForm(); this.loadDrivers(); }
                else this.notify(j.message || 'Gagal', 'error');
            } catch (e) { this.notify('Gagal menyimpan', 'error'); }
            this.saving = false;
        },
        async toggleDriver(d) {
            try {
                const r = await fetch(`${this.API}/drivers/${d.id}`, { method: 'PUT', headers: this.headers(true), body: JSON.stringify({ is_active: !d.is_active }) });
                const j = await r.json();
                if (j.success) this.loadDrivers();
            } catch (e) { this.notify('Gagal', 'error'); }
        },
        async deleteDriver(d) {
            if (!confirm(`Hapus driver ${d.name}?`)) return;
            try {
                const r = await fetch(`${this.API}/drivers/${d.id}`, { method: 'DELETE', headers: this.headers() });
                const j = await r.json();
                if (j.success) { this.notify('Driver dihapus'); this.loadDrivers(); }
            } catch (e) { this.notify('Gagal', 'error'); }
        },

        async loadProofs() {
            const params = new URLSearchParams();
            if (this.proofFilterDriver) params.set('delivery_driver_id', this.proofFilterDriver);
            if (this.proofFilterStatus) params.set('fulfillment_status', this.proofFilterStatus);
            try {
                const r = await fetch(`${this.API}/proofs?${params}`, { headers: this.headers() });
                const j = await r.json();
                if (j.success) this.proofs = j.data.data || [];
            } catch (e) { console.error(e); }
        },
    };
}
</script>
@endsection
