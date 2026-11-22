@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'Developer Webhooks')

@section('content')
<div x-data="developerWebhooksApp()" x-init="init()" class="h-screen flex bg-[hsl(var(--muted)/0.4)] overflow-hidden">

    {{-- Sidebar --}}
    @include('components.dashboard-sidebar', ['activePage' => 'developer-webhooks'])

    {{-- Main --}}
    <div class="flex-1 flex flex-col overflow-y-auto">
        @include('components.dashboard-header', [
            'title'       => 'Developer Webhooks',
            'description' => 'Kirim payload pesan masuk ke URL webhook milik Anda secara real-time.',
        ])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-4xl mx-auto space-y-6">

                {{-- ── Info Banner ── --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="card p-4 flex items-start gap-3">
                        <div class="h-9 w-9 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                            <i class="fab fa-whatsapp text-emerald-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-[hsl(var(--foreground))]">Event Didukung</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5 font-mono">whatsapp.message.received</p>
                        </div>
                    </div>
                    <div class="card p-4 flex items-start gap-3">
                        <div class="h-9 w-9 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-lock text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-[hsl(var(--foreground))]">Verifikasi Signature</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5 font-mono">X-Webhook-Signature</p>
                        </div>
                    </div>
                    <div class="card p-4 flex items-start gap-3">
                        <div class="h-9 w-9 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-rotate text-amber-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-[hsl(var(--foreground))]">Auto Retry</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))] mt-0.5">3x (30s / 2m / 5m)</p>
                        </div>
                    </div>
                </div>

                {{-- ── Webhook List Header ── --}}
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-[hsl(var(--foreground))]">Webhook Terdaftar</h2>
                    <button @click="openCreateModal()" class="btn btn-primary text-sm gap-2">
                        <i class="fas fa-plus"></i>
                        <span>Tambah Webhook</span>
                    </button>
                </div>

                {{-- ── Loading ── --}}
                <div x-show="loading" class="card p-8 flex items-center justify-center gap-3">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600"></div>
                    <span class="text-sm text-[hsl(var(--muted-foreground))]">Memuat webhook...</span>
                </div>

                {{-- ── Empty State ── --}}
                <div x-show="!loading && webhooks.length === 0" x-cloak class="card p-12 text-center">
                    <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-purple-50 to-purple-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-webhook text-3xl text-purple-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Belum ada webhook</h3>
                    <p class="text-sm text-[hsl(var(--muted-foreground))] mb-6 max-w-xs mx-auto">
                        Tambahkan URL webhook Anda dan kami akan meneruskan setiap pesan WhatsApp masuk ke sana.
                    </p>
                    <button @click="openCreateModal()" class="btn btn-primary gap-2 mx-auto">
                        <i class="fas fa-plus"></i>
                        <span>Tambah Webhook Pertama</span>
                    </button>
                </div>

                {{-- ── Webhook Cards ── --}}
                <div x-show="!loading && webhooks.length > 0" x-cloak class="space-y-3">
                    <template x-for="webhook in webhooks" :key="webhook.id">
                        <div class="card p-4 sm:p-5 transition-all duration-200"
                             :class="webhook.is_active ? '' : 'opacity-60'">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">

                                {{-- Status dot + Info --}}
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="h-10 w-10 rounded-xl flex items-center justify-center flex-shrink-0"
                                         :class="webhook.is_active ? 'bg-emerald-50' : 'bg-[hsl(var(--muted))]'">
                                        <i class="fas fa-webhook text-lg"
                                           :class="webhook.is_active ? 'text-emerald-600' : 'text-[hsl(var(--muted-foreground))]'"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-semibold text-sm truncate" x-text="webhook.name"></span>
                                            <span class="badge text-xs px-2 py-0.5 rounded-full font-medium"
                                                  :class="webhook.is_active
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]'">
                                                <i class="fas fa-circle text-[8px] mr-1"></i>
                                                <span x-text="webhook.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                            </span>
                                        </div>
                                        <p class="text-xs text-[hsl(var(--muted-foreground))] truncate mt-0.5 font-mono" x-text="webhook.url"></p>
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            <template x-for="event in (webhook.events || [])" :key="event">
                                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-mono" x-text="event"></span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1.5 flex-wrap sm:flex-nowrap">
                                    {{-- Toggle --}}
                                    <button
                                        @click="toggleWebhook(webhook)"
                                        :disabled="togglingId === webhook.id"
                                        class="btn btn-outline text-xs px-3 py-1.5 gap-1.5"
                                        :title="webhook.is_active ? 'Nonaktifkan' : 'Aktifkan'">
                                        <i class="fas fa-circle-dot text-xs" :class="togglingId === webhook.id ? 'animate-spin' : ''"></i>
                                        <span x-text="webhook.is_active ? 'Nonaktifkan' : 'Aktifkan'"></span>
                                    </button>

                                    {{-- Test --}}
                                    <button
                                        @click="testWebhook(webhook)"
                                        :disabled="testingId === webhook.id"
                                        class="btn btn-outline text-xs px-3 py-1.5 gap-1.5"
                                        title="Kirim test payload">
                                        <i class="fas fa-paper-plane text-xs" :class="testingId === webhook.id ? 'animate-pulse' : ''"></i>
                                        <span>Test</span>
                                    </button>

                                    {{-- Delivery Log --}}
                                    <button
                                        @click="openDeliveries(webhook)"
                                        class="btn btn-outline text-xs px-3 py-1.5 gap-1.5"
                                        title="Lihat log pengiriman">
                                        <i class="fas fa-list-check text-xs"></i>
                                        <span>Log</span>
                                    </button>

                                    {{-- Dropdown: Edit, Regen Secret, Hapus --}}
                                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                        <button @click="open = !open" class="btn btn-outline text-xs px-2.5 py-1.5">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div x-show="open" x-cloak x-transition
                                             class="absolute right-0 top-full mt-1 z-20 bg-[hsl(var(--card))] border border-[hsl(var(--border))] rounded-xl shadow-lg p-1 min-w-[160px]">
                                            <button
                                                @click="openEdit(webhook); open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-lg hover:bg-[hsl(var(--muted))] transition-colors text-left">
                                                <i class="fas fa-pen w-4 text-center text-[hsl(var(--muted-foreground))]"></i>
                                                Edit
                                            </button>
                                            <button
                                                @click="regenerateSecret(webhook); open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-lg hover:bg-[hsl(var(--muted))] transition-colors text-left">
                                                <i class="fas fa-key w-4 text-center text-amber-500"></i>
                                                Ganti Secret
                                            </button>
                                            <div class="my-1 border-t border-[hsl(var(--border))]"></div>
                                            <button
                                                @click="confirmDelete(webhook); open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-lg hover:bg-red-50 transition-colors text-left text-red-600">
                                                <i class="fas fa-trash w-4 text-center"></i>
                                                Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </main>
    </div>

    {{-- ══════════════════════════════
         MODAL: BUAT WEBHOOK
    ══════════════════════════════ --}}
    <div x-show="showCreateModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showCreateModal = false">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showCreateModal = false"></div>
        <div class="relative bg-[hsl(var(--card))] rounded-2xl shadow-xl w-full max-w-md"
             @click.stop x-transition>
            <div class="flex items-center justify-between p-5 border-b border-[hsl(var(--border))]">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-purple-100 flex items-center justify-center">
                        <i class="fas fa-plus text-purple-600"></i>
                    </div>
                    <h3 class="font-semibold text-[hsl(var(--foreground))]">Tambah Webhook</h3>
                </div>
                <button @click="showCreateModal = false" class="btn btn-ghost p-2 rounded-lg">
                    <i class="fas fa-xmark text-[hsl(var(--muted-foreground))]"></i>
                </button>
            </div>
            <form @submit.prevent="createWebhook()" class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Nama Webhook</label>
                    <input x-model="form.name" type="text" class="input w-full"
                           placeholder="Contoh: Server Produksi" required maxlength="100">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">URL Endpoint</label>
                    <input x-model="form.url" type="url" class="input w-full"
                           placeholder="https://yourserver.com/webhook" required maxlength="500">
                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Harus bisa diakses publik dan merespons HTTP 2xx.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Events</label>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)] cursor-pointer hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                        <input type="checkbox" class="rounded" value="whatsapp.message.received"
                               x-model="form.events">
                        <div>
                            <p class="text-sm font-medium">whatsapp.message.received</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))]">Pesan WhatsApp masuk dari pelanggan</p>
                        </div>
                    </label>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showCreateModal = false" class="btn btn-outline flex-1">Batal</button>
                    <button type="submit" :disabled="creating || form.events.length === 0"
                            class="btn btn-primary flex-1 gap-2">
                        <i class="fas fa-spinner animate-spin" x-show="creating"></i>
                        <span x-text="creating ? 'Menyimpan...' : 'Buat Webhook'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════
         MODAL: EDIT WEBHOOK
    ══════════════════════════════ --}}
    <div x-show="showEditModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showEditModal = false">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showEditModal = false"></div>
        <div class="relative bg-[hsl(var(--card))] rounded-2xl shadow-xl w-full max-w-md"
             @click.stop x-transition>
            <div class="flex items-center justify-between p-5 border-b border-[hsl(var(--border))]">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-pen text-blue-600"></i>
                    </div>
                    <h3 class="font-semibold text-[hsl(var(--foreground))]">Edit Webhook</h3>
                </div>
                <button @click="showEditModal = false" class="btn btn-ghost p-2 rounded-lg">
                    <i class="fas fa-xmark text-[hsl(var(--muted-foreground))]"></i>
                </button>
            </div>
            <form @submit.prevent="updateWebhook()" class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Nama Webhook</label>
                    <input x-model="editForm.name" type="text" class="input w-full"
                           placeholder="Nama webhook" required maxlength="100">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">URL Endpoint</label>
                    <input x-model="editForm.url" type="url" class="input w-full"
                           placeholder="https://yourserver.com/webhook" required maxlength="500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Events</label>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)] cursor-pointer hover:bg-[hsl(var(--muted)/0.5)] transition-colors">
                        <input type="checkbox" class="rounded" value="whatsapp.message.received"
                               x-model="editForm.events">
                        <div>
                            <p class="text-sm font-medium">whatsapp.message.received</p>
                            <p class="text-xs text-[hsl(var(--muted-foreground))]">Pesan WhatsApp masuk dari pelanggan</p>
                        </div>
                    </label>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showEditModal = false" class="btn btn-outline flex-1">Batal</button>
                    <button type="submit" :disabled="updating || editForm.events.length === 0"
                            class="btn btn-primary flex-1 gap-2">
                        <i class="fas fa-spinner animate-spin" x-show="updating"></i>
                        <span x-text="updating ? 'Menyimpan...' : 'Simpan Perubahan'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════
         MODAL: SECRET (SEKALI TAMPIL)
    ══════════════════════════════ --}}
    <div x-show="showSecretModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showSecretModal = false">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative bg-[hsl(var(--card))] rounded-2xl shadow-xl w-full max-w-lg"
             @click.stop x-transition>
            <div class="p-6">
                <div class="text-center mb-5">
                    <div class="h-14 w-14 rounded-2xl bg-amber-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-key text-2xl text-amber-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-1">Simpan Secret Ini!</h3>
                    <p class="text-sm text-[hsl(var(--muted-foreground))]">
                        Secret hanya ditampilkan sekali. Gunakan untuk memverifikasi bahwa payload berasal dari QashierWise.
                    </p>
                </div>

                {{-- Secret box --}}
                <div class="relative mb-5">
                    <div class="p-3 pr-12 rounded-xl bg-[hsl(var(--muted))] border border-[hsl(var(--border))] font-mono text-sm break-all select-all" x-text="newSecret"></div>
                    <button @click="copySecret()"
                            class="absolute right-2 top-2 h-8 w-8 flex items-center justify-center rounded-lg hover:bg-[hsl(var(--background))] transition-colors"
                            :title="copiedSecret ? 'Tersalin!' : 'Salin'">
                        <i class="fas text-xs" :class="copiedSecret ? 'fa-check text-emerald-600' : 'fa-copy text-[hsl(var(--muted-foreground))]'"></i>
                    </button>
                </div>

                {{-- Code examples (tabbed) --}}
                <div x-data="{ tab: 'php' }" class="mb-5">
                    <div class="flex gap-1 p-1 bg-[hsl(var(--muted))] rounded-lg mb-3">
                        <button @click="tab = 'php'"
                                class="flex-1 text-xs py-1.5 rounded-md font-medium transition-all"
                                :class="tab === 'php' ? 'bg-[hsl(var(--card))] shadow-sm' : 'text-[hsl(var(--muted-foreground))]'">PHP</button>
                        <button @click="tab = 'node'"
                                class="flex-1 text-xs py-1.5 rounded-md font-medium transition-all"
                                :class="tab === 'node' ? 'bg-[hsl(var(--card))] shadow-sm' : 'text-[hsl(var(--muted-foreground))]'">Node.js</button>
                    </div>
                    <div x-show="tab === 'php'" class="rounded-xl bg-slate-900 p-4 overflow-x-auto">
<pre class="text-xs text-slate-300 leading-relaxed">$secret = 'YOUR_SECRET';
$payload = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $sig)) {
    http_response_code(401); exit;
}
$data = json_decode($payload, true);</pre>
                    </div>
                    <div x-show="tab === 'node'" x-cloak class="rounded-xl bg-slate-900 p-4 overflow-x-auto">
<pre class="text-xs text-slate-300 leading-relaxed">const crypto = require('crypto');
const secret = 'YOUR_SECRET';

const sig = req.headers['x-webhook-signature'];
const expected = 'sha256=' + crypto
  .createHmac('sha256', secret)
  .update(JSON.stringify(req.body))
  .digest('hex');

if (!crypto.timingSafeEqual(
  Buffer.from(expected), Buffer.from(sig)
)) return res.status(401).end();</pre>
                    </div>
                </div>

                <button @click="showSecretModal = false" class="btn btn-primary w-full">
                    Saya sudah menyimpan secret ini
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════
         MODAL: DELIVERY LOG
    ══════════════════════════════ --}}
    <div x-show="showDeliveriesModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showDeliveriesModal = false">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showDeliveriesModal = false"></div>
        <div class="relative bg-[hsl(var(--card))] rounded-2xl shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col"
             @click.stop x-transition>
            <div class="flex items-center justify-between p-5 border-b border-[hsl(var(--border))] flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-slate-100 flex items-center justify-center">
                        <i class="fas fa-list-check text-slate-600"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-[hsl(var(--foreground))]">Log Pengiriman</h3>
                        <p class="text-xs text-[hsl(var(--muted-foreground))] font-mono" x-text="selectedWebhook?.name"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="refreshDeliveries()" class="btn btn-ghost p-2 rounded-lg" title="Refresh">
                        <i class="fas fa-rotate-right text-sm" :class="deliveriesLoading ? 'animate-spin' : ''"></i>
                    </button>
                    <button @click="showDeliveriesModal = false" class="btn btn-ghost p-2 rounded-lg">
                        <i class="fas fa-xmark text-[hsl(var(--muted-foreground))]"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto flex-1 p-5">
                {{-- Loading deliveries --}}
                <div x-show="deliveriesLoading && deliveries.length === 0" class="flex items-center justify-center py-12 gap-3">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600"></div>
                    <span class="text-sm text-[hsl(var(--muted-foreground))]">Memuat log...</span>
                </div>

                {{-- Empty deliveries --}}
                <div x-show="!deliveriesLoading && deliveries.length === 0" class="text-center py-12">
                    <i class="fas fa-inbox text-3xl text-[hsl(var(--muted-foreground))] mb-3"></i>
                    <p class="text-sm text-[hsl(var(--muted-foreground))]">Belum ada pengiriman tercatat.</p>
                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">Klik Test pada webhook untuk memulai percobaan.</p>
                </div>

                {{-- Deliveries table --}}
                <div x-show="deliveries.length > 0" class="space-y-2">
                    <template x-for="d in deliveries" :key="d.id">
                        <div class="p-3 rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.2)]"
                             x-data="{ expand: false }">
                            <div class="flex items-center gap-3 cursor-pointer" @click="expand = !expand">
                                {{-- Status badge --}}
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                                      :class="{
                                        'bg-emerald-100 text-emerald-700': d.status === 'success',
                                        'bg-red-100 text-red-700': d.status === 'failed',
                                        'bg-amber-100 text-amber-700': d.status === 'pending',
                                      }">
                                    <i class="fas text-[8px] mr-1"
                                       :class="{
                                         'fa-check': d.status === 'success',
                                         'fa-xmark': d.status === 'failed',
                                         'fa-clock': d.status === 'pending',
                                       }"></i>
                                    <span x-text="d.status"></span>
                                </span>

                                {{-- HTTP code --}}
                                <span class="text-xs font-mono text-[hsl(var(--muted-foreground))] flex-shrink-0"
                                      x-text="d.response_code ? 'HTTP ' + d.response_code : '—'"></span>

                                {{-- Event --}}
                                <span class="text-xs font-mono truncate flex-1 text-[hsl(var(--foreground))]" x-text="d.event_type"></span>

                                {{-- Attempts --}}
                                <span class="text-xs text-[hsl(var(--muted-foreground))] flex-shrink-0"
                                      x-text="d.attempts + 'x'"></span>

                                {{-- Time --}}
                                <span class="text-xs text-[hsl(var(--muted-foreground))] flex-shrink-0"
                                      x-text="formatDate(d.created_at)"></span>

                                <i class="fas fa-chevron-down text-xs text-[hsl(var(--muted-foreground))] transition-transform flex-shrink-0"
                                   :class="expand ? 'rotate-180' : ''"></i>
                            </div>

                            {{-- Expanded: response body --}}
                            <div x-show="expand" x-cloak x-transition class="mt-3 pt-3 border-t border-[hsl(var(--border))]">
                                <p class="text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1.5">Response Body</p>
                                <pre class="text-xs bg-slate-900 text-slate-300 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap break-all max-h-32"
                                     x-text="d.response_body || '(kosong)'"></pre>
                            </div>
                        </div>
                    </template>

                    {{-- Load more --}}
                    <div x-show="deliveriesHasMore" class="text-center pt-2">
                        <button @click="loadMoreDeliveries()"
                                :disabled="deliveriesLoading"
                                class="btn btn-outline text-sm gap-2">
                            <i class="fas fa-spinner animate-spin" x-show="deliveriesLoading"></i>
                            <span>Muat Lebih Banyak</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════
         MODAL: KONFIRMASI HAPUS
    ══════════════════════════════ --}}
    <div x-show="showDeleteModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showDeleteModal = false">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showDeleteModal = false"></div>
        <div class="relative bg-[hsl(var(--card))] rounded-2xl shadow-xl w-full max-w-sm p-6"
             @click.stop x-transition>
            <div class="text-center mb-5">
                <div class="h-14 w-14 rounded-2xl bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash text-2xl text-red-600"></i>
                </div>
                <h3 class="font-semibold text-lg mb-1">Hapus Webhook?</h3>
                <p class="text-sm text-[hsl(var(--muted-foreground))]">
                    Webhook <strong x-text="deletingWebhook?.name" class="text-[hsl(var(--foreground))]"></strong> akan dihapus permanen beserta seluruh log pengirimannya.
                </p>
            </div>
            <div class="flex gap-3">
                <button @click="showDeleteModal = false" class="btn btn-outline flex-1">Batal</button>
                <button @click="deleteWebhook()"
                        :disabled="deleting"
                        class="btn flex-1 gap-2 bg-red-600 hover:bg-red-700 text-white border-red-600">
                    <i class="fas fa-spinner animate-spin" x-show="deleting"></i>
                    <span x-text="deleting ? 'Menghapus...' : 'Hapus'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════
         TOAST NOTIFICATION
    ══════════════════════════════ --}}
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] flex items-center gap-3 px-5 py-3 rounded-2xl shadow-xl text-white text-sm font-medium"
         :class="toast.type === 'success' ? 'bg-emerald-600' : (toast.type === 'error' ? 'bg-red-600' : 'bg-slate-800')">
        <i class="fas" :class="toast.type === 'success' ? 'fa-check-circle' : (toast.type === 'error' ? 'fa-circle-xmark' : 'fa-circle-info')"></i>
        <span x-text="toast.message"></span>
    </div>

</div>
@endsection

@push('scripts')
<script>
function developerWebhooksApp() {
    return {
        ...dashboardBase(),

        API: window.location.origin + '/api/developer/webhooks',

        loading: true,
        webhooks: [],

        // Create modal
        showCreateModal: false,
        creating: false,
        form: { name: '', url: '', events: ['whatsapp.message.received'] },

        // Edit modal
        showEditModal: false,
        editingWebhook: null,
        editForm: { name: '', url: '', events: [] },
        updating: false,

        // Secret modal
        showSecretModal: false,
        newSecret: '',
        copiedSecret: false,

        // Delivery log modal
        showDeliveriesModal: false,
        selectedWebhook: null,
        deliveries: [],
        deliveriesLoading: false,
        deliveriesPage: 1,
        deliveriesHasMore: false,

        // Delete modal
        showDeleteModal: false,
        deletingWebhook: null,
        deleting: false,

        // Action states
        togglingId: null,
        testingId: null,

        // Toast
        toast: { show: false, message: '', type: 'success' },

        // ─────────────────────────────────────────────
        async init() {
            this.initDashboard();
            await this.loadWebhooks();
        },

        headers() {
            const token = localStorage.getItem('token');
            return {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            };
        },

        // ─────────────────────────────────────────────
        async loadWebhooks() {
            this.loading = true;
            try {
                const res = await fetch(this.API, { headers: this.headers() });
                const data = await res.json();
                this.webhooks = data.data || [];
            } catch {
                this.showToast('Gagal memuat webhook.', 'error');
            } finally {
                this.loading = false;
            }
        },

        // ─────────────────────────────────────────────
        openCreateModal() {
            this.form = { name: '', url: '', events: ['whatsapp.message.received'] };
            this.showCreateModal = true;
        },

        async createWebhook() {
            this.creating = true;
            try {
                const res = await fetch(this.API, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (data.success) {
                    this.webhooks.unshift(data.data);
                    this.showCreateModal = false;
                    this.newSecret = data.data.secret;
                    this.copiedSecret = false;
                    this.showSecretModal = true;
                    this.showToast('Webhook berhasil dibuat!');
                } else {
                    this.showToast(data.message || 'Gagal membuat webhook.', 'error');
                }
            } catch {
                this.showToast('Terjadi kesalahan. Coba lagi.', 'error');
            } finally {
                this.creating = false;
            }
        },

        // ─────────────────────────────────────────────
        openEdit(webhook) {
            this.editingWebhook = webhook;
            this.editForm = {
                name: webhook.name,
                url: webhook.url,
                events: [...(webhook.events || [])],
            };
            this.showEditModal = true;
        },

        async updateWebhook() {
            this.updating = true;
            try {
                const res = await fetch(`${this.API}/${this.editingWebhook.id}`, {
                    method: 'PUT',
                    headers: this.headers(),
                    body: JSON.stringify(this.editForm),
                });
                const data = await res.json();
                if (data.success) {
                    const idx = this.webhooks.findIndex(w => w.id === this.editingWebhook.id);
                    if (idx !== -1) this.webhooks[idx] = data.data;
                    this.showEditModal = false;
                    this.showToast('Webhook diperbarui!');
                } else {
                    this.showToast(data.message || 'Gagal memperbarui webhook.', 'error');
                }
            } catch {
                this.showToast('Terjadi kesalahan. Coba lagi.', 'error');
            } finally {
                this.updating = false;
            }
        },

        // ─────────────────────────────────────────────
        confirmDelete(webhook) {
            this.deletingWebhook = webhook;
            this.showDeleteModal = true;
        },

        async deleteWebhook() {
            this.deleting = true;
            try {
                const res = await fetch(`${this.API}/${this.deletingWebhook.id}`, {
                    method: 'DELETE',
                    headers: this.headers(),
                });
                const data = await res.json();
                if (data.success) {
                    this.webhooks = this.webhooks.filter(w => w.id !== this.deletingWebhook.id);
                    this.showDeleteModal = false;
                    this.showToast('Webhook dihapus.');
                } else {
                    this.showToast(data.message || 'Gagal menghapus webhook.', 'error');
                }
            } catch {
                this.showToast('Terjadi kesalahan. Coba lagi.', 'error');
            } finally {
                this.deleting = false;
            }
        },

        // ─────────────────────────────────────────────
        async toggleWebhook(webhook) {
            this.togglingId = webhook.id;
            try {
                const res = await fetch(`${this.API}/${webhook.id}/toggle`, {
                    method: 'POST',
                    headers: this.headers(),
                });
                const data = await res.json();
                if (data.success) {
                    const idx = this.webhooks.findIndex(w => w.id === webhook.id);
                    if (idx !== -1) this.webhooks[idx] = data.data;
                    this.showToast(data.data.is_active ? 'Webhook diaktifkan.' : 'Webhook dinonaktifkan.');
                } else {
                    this.showToast(data.message || 'Gagal mengubah status.', 'error');
                }
            } catch {
                this.showToast('Terjadi kesalahan. Coba lagi.', 'error');
            } finally {
                this.togglingId = null;
            }
        },

        // ─────────────────────────────────────────────
        async testWebhook(webhook) {
            this.testingId = webhook.id;
            try {
                const res = await fetch(`${this.API}/${webhook.id}/test`, {
                    method: 'POST',
                    headers: this.headers(),
                });
                const data = await res.json();
                if (data.success) {
                    this.showToast('Test payload diantrekan untuk dikirim!');
                } else {
                    this.showToast(data.message || 'Gagal mengirim test.', 'error');
                }
            } catch {
                this.showToast('Terjadi kesalahan. Coba lagi.', 'error');
            } finally {
                setTimeout(() => { this.testingId = null; }, 1500);
            }
        },

        // ─────────────────────────────────────────────
        async regenerateSecret(webhook) {
            const res = await fetch(`${this.API}/${webhook.id}/regenerate-secret`, {
                method: 'POST',
                headers: this.headers(),
            });
            const data = await res.json();
            if (data.success) {
                this.newSecret = data.data.secret;
                this.copiedSecret = false;
                this.showSecretModal = true;
            } else {
                this.showToast(data.message || 'Gagal mengganti secret.', 'error');
            }
        },

        // ─────────────────────────────────────────────
        openDeliveries(webhook) {
            this.selectedWebhook = webhook;
            this.deliveries = [];
            this.deliveriesPage = 1;
            this.deliveriesHasMore = false;
            this.showDeliveriesModal = true;
            this.loadDeliveries(webhook.id, 1);
        },

        refreshDeliveries() {
            if (!this.selectedWebhook) return;
            this.deliveries = [];
            this.deliveriesPage = 1;
            this.loadDeliveries(this.selectedWebhook.id, 1);
        },

        loadMoreDeliveries() {
            if (!this.selectedWebhook) return;
            this.deliveriesPage++;
            this.loadDeliveries(this.selectedWebhook.id, this.deliveriesPage);
        },

        async loadDeliveries(webhookId, page) {
            this.deliveriesLoading = true;
            try {
                const res = await fetch(`${this.API}/${webhookId}/deliveries?page=${page}`, {
                    headers: this.headers(),
                });
                const data = await res.json();
                if (data.success) {
                    const items = data.data?.data || [];
                    if (page === 1) {
                        this.deliveries = items;
                    } else {
                        this.deliveries = [...this.deliveries, ...items];
                    }
                    this.deliveriesHasMore = !!data.data?.next_page_url;
                }
            } catch {
                this.showToast('Gagal memuat log pengiriman.', 'error');
            } finally {
                this.deliveriesLoading = false;
            }
        },

        // ─────────────────────────────────────────────
        copySecret() {
            navigator.clipboard.writeText(this.newSecret).then(() => {
                this.copiedSecret = true;
                setTimeout(() => { this.copiedSecret = false; }, 2500);
            });
        },

        // ─────────────────────────────────────────────
        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },

        // ─────────────────────────────────────────────
        formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', {
                day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
            });
        },
    };
}
</script>
@endpush
