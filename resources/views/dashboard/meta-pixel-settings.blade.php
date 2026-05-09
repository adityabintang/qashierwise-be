@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Meta Pixel & Conversions API')

@section('content')
<div x-data="metaPixelApp()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">
    <!-- Sidebar -->
    @include('components.dashboard-sidebar', ['activePage' => 'meta-pixel'])

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto">
        @include('components.dashboard-header', [
            'title'       => 'Meta Pixel & Conversions API',
            'description' => 'Hubungkan Meta Business Pixel untuk melacak konversi dari iklan WhatsApp Anda',
        ])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-3xl mx-auto space-y-6">

                {{-- ── Status banner ── --}}
                <div
                    x-show="!loading && settings"
                    class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4"
                    x-cloak
                >
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100">
                        <i class="fas fa-check text-emerald-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-emerald-800">Pixel Terhubung</p>
                        <p class="text-sm text-emerald-600 truncate">
                            Pixel ID: <span class="font-mono font-semibold" x-text="settings?.pixel_id"></span>
                            <span x-show="settings?.test_event_code" class="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                <i class="fas fa-flask text-[10px]"></i>
                                Test Mode
                            </span>
                        </p>
                    </div>
                    <button @click="confirmDelete()" class="btn btn-ghost btn-sm text-[hsl(var(--destructive))]" title="Hapus konfigurasi">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>

                {{-- ── Settings card ── --}}
                <div class="card">
                    <div class="card-header border-b border-[hsl(var(--border))]">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                                <i class="fab fa-meta text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="card-title">Konfigurasi Pixel</h2>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">
                                    Masukkan Pixel ID dan System User Access Token dari Meta Business Manager
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Loading skeleton -->
                    <div x-show="loading" class="p-6 space-y-4" x-cloak>
                        <div class="h-4 w-1/3 animate-pulse rounded bg-[hsl(var(--muted))]"></div>
                        <div class="h-10 animate-pulse rounded-lg bg-[hsl(var(--muted))]"></div>
                        <div class="h-4 w-1/3 animate-pulse rounded bg-[hsl(var(--muted))]"></div>
                        <div class="h-10 animate-pulse rounded-lg bg-[hsl(var(--muted))]"></div>
                    </div>

                    <!-- Form -->
                    <div x-show="!loading" class="p-6 space-y-5" x-cloak>
                        <form @submit.prevent="save()" class="space-y-5">

                            <!-- Pixel ID -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium" for="pixel_id">
                                    Pixel ID <span class="text-[hsl(var(--destructive))]">*</span>
                                </label>
                                <input
                                    id="pixel_id"
                                    type="text"
                                    x-model="form.pixel_id"
                                    placeholder="e.g. 1234567890123456"
                                    class="input font-mono"
                                    :class="errors.pixel_id ? 'border-[hsl(var(--destructive))]' : ''"
                                    required
                                >
                                <p x-show="errors.pixel_id" x-text="errors.pixel_id" class="text-xs text-[hsl(var(--destructive))]"></p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                    Temukan di <strong>Meta Business Manager → Events Manager → Data Sources</strong>
                                </p>
                            </div>

                            <!-- Access Token -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium" for="access_token">
                                    System User Access Token <span class="text-[hsl(var(--destructive))]">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="access_token"
                                        :type="showToken ? 'text' : 'password'"
                                        x-model="form.access_token"
                                        placeholder="Token akan dienkripsi sebelum disimpan"
                                        class="input font-mono pr-10"
                                        :class="errors.access_token ? 'border-[hsl(var(--destructive))]' : ''"
                                        required
                                        autocomplete="off"
                                    >
                                    <button
                                        type="button"
                                        @click="showToken = !showToken"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]"
                                        tabindex="-1"
                                    >
                                        <i class="fas text-sm" :class="showToken ? 'fa-eye-slash' : 'fa-eye'"></i>
                                    </button>
                                </div>
                                <p x-show="errors.access_token" x-text="errors.access_token" class="text-xs text-[hsl(var(--destructive))]"></p>
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                    Buat di <strong>Meta Business Manager → System Users → Generate Token</strong>
                                    dengan permission <code class="rounded bg-[hsl(var(--muted))] px-1">ads_management</code>
                                </p>
                            </div>

                            <!-- Test Event Code -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium" for="test_event_code">
                                    Test Event Code
                                    <span class="ml-1 rounded-full bg-[hsl(var(--muted))] px-2 py-0.5 text-xs font-normal text-[hsl(var(--muted-foreground))]">
                                        Opsional
                                    </span>
                                </label>
                                <input
                                    id="test_event_code"
                                    type="text"
                                    x-model="form.test_event_code"
                                    placeholder="e.g. TEST12345"
                                    class="input font-mono uppercase"
                                    maxlength="50"
                                >
                                <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                    Isi saat testing agar event muncul di <strong>Events Manager → Test Events</strong>.
                                    Kosongkan setelah selesai testing — event test tidak diproses untuk optimasi iklan.
                                </p>
                            </div>

                            <!-- Active toggle -->
                            <div class="flex items-center justify-between rounded-lg border border-[hsl(var(--border))] px-4 py-3">
                                <div>
                                    <p class="text-sm font-medium">Aktifkan CAPI</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                        Nonaktifkan untuk menghentikan pengiriman event tanpa menghapus konfigurasi
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="form.is_active = !form.is_active"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
                                    :class="form.is_active ? 'bg-[hsl(var(--primary))]' : 'bg-[hsl(var(--muted-foreground)/0.4)]'"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                        :class="form.is_active ? 'translate-x-5' : 'translate-x-0'"
                                    ></span>
                                </button>
                            </div>

                            <!-- Submit -->
                            <div class="flex justify-end pt-2">
                                <button type="submit" class="btn btn-primary" :disabled="saving">
                                    <i class="fas fa-spinner animate-spin" x-show="saving"></i>
                                    <i class="fas fa-save" x-show="!saving"></i>
                                    <span x-text="saving ? 'Menyimpan...' : 'Simpan Konfigurasi'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ── Events log card ── --}}
                <div class="card" x-show="!loading && settings" x-cloak>
                    <div class="card-header !flex-row items-center justify-between border-b border-[hsl(var(--border))]">
                        <div>
                            <h2 class="card-title">Log Event CAPI</h2>
                            <p class="text-sm text-[hsl(var(--muted-foreground))]">
                                Riwayat event yang dikirim ke Meta Graph API
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select x-model="eventsFilter" @change="loadEvents()" class="input input-sm text-sm w-36">
                                <option value="">Semua status</option>
                                <option value="sent">Terkirim</option>
                                <option value="failed">Gagal</option>
                                <option value="pending">Pending</option>
                            </select>
                            <button @click="loadEvents()" class="btn btn-ghost btn-icon btn-sm" title="Refresh">
                                <i class="fas fa-sync-alt" :class="eventsLoading ? 'animate-spin' : ''"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Events loading -->
                    <div x-show="eventsLoading" class="flex items-center justify-center py-10" x-cloak>
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-[hsl(var(--primary))]"></div>
                        <span class="ml-3 text-sm text-[hsl(var(--muted-foreground))]">Memuat events...</span>
                    </div>

                    <!-- Events empty -->
                    <div x-show="!eventsLoading && events.length === 0" class="flex flex-col items-center py-10 text-center" x-cloak>
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[hsl(var(--muted))] mb-3">
                            <i class="fas fa-satellite-dish text-xl text-[hsl(var(--muted-foreground))]"></i>
                        </div>
                        <p class="text-sm font-medium">Belum ada event</p>
                        <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">
                            Event akan muncul setelah ada pesan WhatsApp masuk
                        </p>
                    </div>

                    <!-- Events table -->
                    <div x-show="!eventsLoading && events.length > 0" class="overflow-x-auto" x-cloak>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[hsl(var(--border))] text-xs text-[hsl(var(--muted-foreground))] uppercase tracking-wide">
                                    <th class="px-4 py-3 text-left font-medium">Event</th>
                                    <th class="px-4 py-3 text-left font-medium">Source</th>
                                    <th class="px-4 py-3 text-left font-medium">Status</th>
                                    <th class="px-4 py-3 text-left font-medium">Waktu</th>
                                    <th class="px-4 py-3 text-left font-medium">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[hsl(var(--border))]">
                                <template x-for="event in events" :key="event.id">
                                    <tr class="hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                                        <!-- Event name -->
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full"
                                                    :class="{
                                                        'bg-blue-100': event.event_name === 'Lead',
                                                        'bg-emerald-100': event.event_name === 'Purchase',
                                                        'bg-purple-100': event.event_name === 'Contact',
                                                        'bg-[hsl(var(--muted))]': !['Lead','Purchase','Contact'].includes(event.event_name)
                                                    }">
                                                    <i class="text-xs"
                                                        :class="{
                                                            'fas fa-user-plus text-blue-600': event.event_name === 'Lead',
                                                            'fas fa-shopping-bag text-emerald-600': event.event_name === 'Purchase',
                                                            'fas fa-comment text-purple-600': event.event_name === 'Contact',
                                                            'fas fa-bolt text-[hsl(var(--muted-foreground))]': !['Lead','Purchase','Contact'].includes(event.event_name)
                                                        }">
                                                    </i>
                                                </div>
                                                <span class="font-medium" x-text="event.event_name"></span>
                                            </div>
                                        </td>
                                        <!-- Source badge -->
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="event.event_source === 'ctwa'
                                                    ? 'bg-blue-50 text-blue-700 border border-blue-200'
                                                    : 'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]'"
                                            >
                                                <i class="text-[10px]"
                                                    :class="event.event_source === 'ctwa' ? 'fas fa-ad' : 'fas fa-comment-dots'">
                                                </i>
                                                <span x-text="event.event_source === 'ctwa' ? 'CTWA Ad' : 'Organic'"></span>
                                            </span>
                                        </td>
                                        <!-- Status badge -->
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-emerald-50 text-emerald-700 border border-emerald-200': event.status === 'sent',
                                                    'bg-red-50 text-red-700 border border-red-200': event.status === 'failed',
                                                    'bg-amber-50 text-amber-700 border border-amber-200': event.status === 'pending'
                                                }"
                                            >
                                                <i class="text-[10px] fas"
                                                    :class="{
                                                        'fa-check': event.status === 'sent',
                                                        'fa-times': event.status === 'failed',
                                                        'fa-clock': event.status === 'pending'
                                                    }">
                                                </i>
                                                <span class="capitalize" x-text="event.status"></span>
                                            </span>
                                        </td>
                                        <!-- Time -->
                                        <td class="px-4 py-3 text-[hsl(var(--muted-foreground))] text-xs whitespace-nowrap">
                                            <span x-text="formatDate(event.created_at)"></span>
                                        </td>
                                        <!-- Meta response detail -->
                                        <td class="px-4 py-3">
                                            <template x-if="event.status === 'sent'">
                                                <span class="text-xs text-[hsl(var(--muted-foreground))]">
                                                    <span x-text="event.meta_response?.events_received ?? '–'"></span> received
                                                </span>
                                            </template>
                                            <template x-if="event.status === 'failed'">
                                                <button
                                                    @click="showErrorDetail(event)"
                                                    class="text-xs text-[hsl(var(--destructive))] underline underline-offset-2"
                                                >
                                                    Lihat error
                                                </button>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="flex items-center justify-between border-t border-[hsl(var(--border))] px-4 py-3">
                            <p class="text-xs text-[hsl(var(--muted-foreground))]">
                                Total <span class="font-medium" x-text="eventsMeta.total ?? 0"></span> event
                            </p>
                            <div class="flex gap-2">
                                <button
                                    @click="eventsPage--; loadEvents()"
                                    :disabled="eventsPage <= 1"
                                    class="btn btn-ghost btn-sm btn-icon"
                                >
                                    <i class="fas fa-chevron-left text-xs"></i>
                                </button>
                                <span class="flex items-center text-xs text-[hsl(var(--muted-foreground))]">
                                    <span x-text="eventsPage"></span>/<span x-text="eventsMeta.last_page ?? 1"></span>
                                </span>
                                <button
                                    @click="eventsPage++; loadEvents()"
                                    :disabled="eventsPage >= (eventsMeta.last_page ?? 1)"
                                    class="btn btn-ghost btn-sm btn-icon"
                                >
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Setup guide card ── --}}
                <div class="card border-dashed">
                    <div class="card-header">
                        <h2 class="card-title text-base">Cara Setup (3 langkah)</h2>
                    </div>
                    <div class="p-6">
                        <ol class="space-y-4">
                            <li class="flex gap-4">
                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-[hsl(var(--primary))] text-xs font-bold text-white">1</div>
                                <div>
                                    <p class="text-sm font-medium">Buat Pixel di Meta Business Manager</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">
                                        Events Manager → <strong>Data Sources</strong> → Add → Conversions API → klik <em>Connect</em>.
                                        Salin <strong>Pixel ID</strong> yang muncul.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-[hsl(var(--primary))] text-xs font-bold text-white">2</div>
                                <div>
                                    <p class="text-sm font-medium">Generate System User Access Token</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">
                                        Business Settings → <strong>System Users</strong> → pilih user → Generate Token.
                                        Pilih permission <code class="rounded bg-[hsl(var(--muted))] px-1 text-[10px]">ads_management</code>.
                                        Token tidak punya expiry date jika menggunakan System User.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-[hsl(var(--primary))] text-xs font-bold text-white">3</div>
                                <div>
                                    <p class="text-sm font-medium">Test dengan Test Event Code</p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">
                                        Isi <strong>Test Event Code</strong> dari tab <em>Test Events</em> di Events Manager.
                                        Kirim pesan WhatsApp dari nomor test — event <code class="rounded bg-[hsl(var(--muted))] px-1 text-[10px]">Lead</code> atau
                                        <code class="rounded bg-[hsl(var(--muted))] px-1 text-[10px]">Contact</code>
                                        akan muncul real-time.
                                        Hapus kode setelah selesai testing.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

{{-- ── Error detail modal ── --}}
<div
    x-show="errorModal"
    @keydown.escape.window="errorModal = null"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    x-cloak
>
    <div class="absolute inset-0 bg-black/50" @click="errorModal = null"></div>
    <div class="relative z-10 w-full max-w-lg rounded-xl bg-white shadow-xl dark:bg-[hsl(var(--card))]">
        <div class="flex items-center justify-between border-b border-[hsl(var(--border))] px-5 py-4">
            <h3 class="font-semibold text-[hsl(var(--destructive))]">
                <i class="fas fa-exclamation-triangle mr-2"></i>Error Detail
            </h3>
            <button @click="errorModal = null" class="btn btn-ghost btn-icon btn-sm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5">
            <pre class="overflow-auto rounded-lg bg-[hsl(var(--muted))] p-4 text-xs leading-relaxed max-h-72"
                x-text="errorModal ? JSON.stringify(errorModal, null, 2) : ''"></pre>
        </div>
    </div>
</div>

{{-- ── Delete confirm modal ── --}}
<div
    x-show="deleteModal"
    @keydown.escape.window="deleteModal = false"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    x-cloak
>
    <div class="absolute inset-0 bg-black/50" @click="deleteModal = false"></div>
    <div class="relative z-10 w-full max-w-sm rounded-xl bg-white shadow-xl dark:bg-[hsl(var(--card))]">
        <div class="p-6 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-red-100 mx-auto mb-4">
                <i class="fas fa-trash-alt text-xl text-[hsl(var(--destructive))]"></i>
            </div>
            <h3 class="text-lg font-semibold mb-2">Hapus Konfigurasi?</h3>
            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-6">
                Pixel ID dan Access Token akan dihapus. Event CAPI tidak akan dikirim sampai konfigurasi diisi ulang.
            </p>
            <div class="flex gap-3 justify-center">
                <button @click="deleteModal = false" class="btn btn-outline">Batal</button>
                <button @click="deleteSettings()" :disabled="saving" class="btn btn-primary bg-[hsl(var(--destructive))] border-[hsl(var(--destructive))] hover:opacity-90">
                    <i class="fas fa-spinner animate-spin" x-show="saving"></i>
                    <span x-text="saving ? 'Menghapus...' : 'Ya, Hapus'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Toast ── --}}
<div
    x-show="toast.show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-2"
    class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg"
    :class="toast.type === 'success'
        ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-red-50 border-red-200 text-red-800'"
    x-cloak
>
    <i class="fas text-base" :class="toast.type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
    <span class="text-sm font-medium" x-text="toast.message"></span>
</div>

<script>
function metaPixelApp() {
    return {
        loading: true,
        saving: false,
        showToken: false,
        settings: null,
        form: {
            pixel_id: '',
            access_token: '',
            test_event_code: '',
            is_active: true,
        },
        errors: {},
        toast: { show: false, type: 'success', message: '' },
        errorModal: null,
        deleteModal: false,

        // Events log
        events: [],
        eventsMeta: {},
        eventsLoading: false,
        eventsFilter: '',
        eventsPage: 1,

        async init() {
            await this.loadSettings();
        },

        async loadSettings() {
            this.loading = true;
            try {
                const res = await fetch('/api/meta-pixel/settings', {
                    headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
                });
                if (!res.ok) throw new Error('Gagal memuat pengaturan');
                const json = await res.json();
                this.settings = json.data;
                if (this.settings) {
                    this.form.pixel_id        = this.settings.pixel_id;
                    this.form.test_event_code = this.settings.test_event_code ?? '';
                    this.form.is_active       = this.settings.is_active;
                    // Don't prefill access_token — masked server-side for security
                    this.loadEvents();
                }
            } catch (e) {
                this.showToast('error', e.message);
            } finally {
                this.loading = false;
            }
        },

        async save() {
            this.errors = {};
            if (!this.form.pixel_id.trim()) {
                this.errors.pixel_id = 'Pixel ID wajib diisi';
            }
            if (!this.form.access_token.trim() && !this.settings) {
                this.errors.access_token = 'Access Token wajib diisi saat pertama kali menyimpan';
            }
            if (Object.keys(this.errors).length) return;

            this.saving = true;
            try {
                const body = {
                    pixel_id: this.form.pixel_id.trim(),
                    is_active: this.form.is_active,
                };
                if (this.form.access_token.trim()) {
                    body.access_token = this.form.access_token.trim();
                }
                if (this.form.test_event_code.trim()) {
                    body.test_event_code = this.form.test_event_code.trim().toUpperCase();
                } else {
                    body.test_event_code = null;
                }

                const res = await fetch('/api/meta-pixel/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
                    },
                    body: JSON.stringify(body),
                });
                const json = await res.json();
                if (!res.ok) {
                    if (json.errors) this.errors = json.errors;
                    throw new Error(json.message || 'Gagal menyimpan');
                }
                this.showToast('success', 'Konfigurasi berhasil disimpan');
                this.form.access_token = '';
                await this.loadSettings();
            } catch (e) {
                if (!Object.keys(this.errors).length) {
                    this.showToast('error', e.message);
                }
            } finally {
                this.saving = false;
            }
        },

        confirmDelete() {
            this.deleteModal = true;
        },

        async deleteSettings() {
            this.saving = true;
            try {
                const res = await fetch('/api/meta-pixel/settings', {
                    method: 'DELETE',
                    headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` },
                });
                if (!res.ok) throw new Error('Gagal menghapus');
                this.settings = null;
                this.form = { pixel_id: '', access_token: '', test_event_code: '', is_active: true };
                this.events = [];
                this.showToast('success', 'Konfigurasi berhasil dihapus');
            } catch (e) {
                this.showToast('error', e.message);
            } finally {
                this.saving = false;
                this.deleteModal = false;
            }
        },

        async loadEvents() {
            this.eventsLoading = true;
            try {
                const params = new URLSearchParams({ page: this.eventsPage, per_page: 10 });
                if (this.eventsFilter) params.set('status', this.eventsFilter);

                const res = await fetch(`/api/meta-pixel/events?${params}`, {
                    headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` },
                });
                const json = await res.json();
                this.events    = json.data ?? [];
                this.eventsMeta = { total: json.total, last_page: json.last_page };
            } catch (e) {
                // Silently fail — events log is non-critical
            } finally {
                this.eventsLoading = false;
            }
        },

        showErrorDetail(event) {
            this.errorModal = event.meta_response;
        },

        showToast(type, message) {
            this.toast = { show: true, type, message };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },

        formatDate(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
                + ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
@endsection
