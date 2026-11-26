@extends('layouts.app')
@include('components.dashboard-scripts')

@section('title', 'TrackCTWA')

@section('content')
<div x-data="trackCtwaPage()" class="h-screen flex overflow-hidden bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'track-ctwa'])

    <div class="flex-1 min-w-0 overflow-y-auto">
        @include('components.dashboard-header', ['title' => 'TrackCTWA', 'description' => 'Pantau chat iklan WhatsApp, order lunas, dan pengiriman Purchase ke Meta.'])

        <main class="mx-auto max-w-7xl p-4 md:p-6 space-y-6" x-cloak>
            <p role="alert" class="card p-4 text-red-700" x-show="error" x-text="error"></p>
            <p role="status" class="text-sm" x-show="notice" x-text="notice"></p>
            <p role="status" class="text-sm" x-show="busy">Memuat…</p>

            <section class="card p-4 space-y-4" x-show="loaded && phones.length">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="grid gap-1 text-sm">Nomor WhatsApp
                        <select class="input" x-model="phoneId" @change="pickPhone()" :disabled="busy">
                            <template x-for="phone in phones" :key="phone.id">
                                <option :value="phone.id" x-text="phone.display_phone_number || phone.name || phone.phone_number_id"></option>
                            </template>
                        </select>
                    </label>
                    <span class="text-sm" x-text="phone?.tracking?.enabled_at && phone?.is_active ? 'Aktif' : 'Nonaktif'"></span>
                    <span class="text-sm" x-show="phone?.coexistence_enabled">Coexistence</span>
                </div>
                <form class="flex flex-wrap items-end gap-3" x-show="access.configure" @submit.prevent="configurePhone(true)">
                    <label class="grid gap-1 text-sm">ID dataset WABA
                        <input class="input" type="text" inputmode="numeric" x-model.trim="datasetId" required>
                    </label>
                    <label class="grid gap-1 text-sm">Token Conversions API
                        <input class="input" type="password" x-model="eventsToken" autocomplete="off" placeholder="Opsional jika token sudah tersedia">
                    </label>
                    <button type="submit" class="btn btn-primary" :disabled="busy || !phone?.is_active">Validasi dan aktifkan</button>
                    <button type="button" class="btn btn-outline" x-show="phone?.tracking?.enabled_at" :disabled="busy" @click="configurePhone(false)">Nonaktifkan</button>
                </form>
            </section>
            <p class="card p-4 text-sm" x-show="loaded && !phones.length">Hubungkan nomor WhatsApp untuk mulai mencatat rekap.</p>

            <section class="card p-4 space-y-3" x-show="access.configure">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Akun iklan Meta</h2>
                        <p class="text-sm text-[hsl(var(--muted-foreground))] mt-1">Berikan akses Ads Insights melalui Meta untuk menampilkan biaya iklan dan ROAS.</p>
                    </div>
                    <button type="button" class="btn btn-primary" :disabled="busy || connectingAds || !adsSdkReady" @click="connectAds()"
                        x-text="connectingAds ? 'Menghubungkan…' : (adAccounts.length ? 'Hubungkan akun lain' : 'Hubungkan dengan Meta')"></button>
                </div>
                <p class="text-sm text-[hsl(var(--muted-foreground))]" x-show="!adsLoginConfig?.config_id || !adsLoginConfig?.app_id">Embedded Signup Meta belum dikonfigurasi. Hubungi administrator QashierWise.</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]" x-show="adAccounts.length">Jika akses Meta berakhir, hubungkan kembali untuk memperbarui izin.</p>
                <div class="flex flex-wrap gap-2" x-show="adAccounts.length">
                    <template x-for="account in adAccounts" :key="account.id">
                        <span class="rounded-lg border px-3 py-1.5 text-sm" x-text="account.name + ' · ' + account.account_id"></span>
                    </template>
                </div>
            </section>

            <section class="card p-4 space-y-4" x-show="access.review && phone?.tracking">
                <h2 class="font-semibold">Perlu ditinjau</h2>
                <p class="text-sm" x-show="!recaps.data.length">Belum ada rekap yang perlu ditinjau.</p>
                <template x-for="recap in recaps.data" :key="recap.id">
                    <article class="border-t pt-3 space-y-2">
                        <p class="text-sm font-medium" x-text="recap.contact + ' · ' + (recap.channel === 'inbox' ? 'Inbox' : 'WhatsApp Business App') + ' · ' + dateLabel(recap.sent_at)"></p>
                        <p class="text-sm" x-text="reviewLabels[recap.review_reason]"></p>
                        <pre class="text-sm whitespace-pre-wrap break-words" x-text="recap.body"></pre>
                        <form class="flex flex-wrap items-end gap-2" @submit.prevent="review(recap, 'resolve')">
                            <label class="grid gap-1 text-sm">Nomor order
                                <input class="input" x-model.trim="recap.order_number" required maxlength="80">
                            </label>
                            <label class="grid gap-1 text-sm">Total (IDR)
                                <input class="input" type="number" min="0.01" step="0.01" x-model="recap.total" required>
                            </label>
                            <button class="btn btn-primary" :disabled="busy">Simpan rekap</button>
                            <button type="button" class="btn btn-outline" :disabled="busy" @click="review(recap, 'dismiss')">Abaikan</button>
                        </form>
                    </article>
                </template>
                <div class="flex gap-2" x-show="recaps.last_page > 1">
                    <button class="btn btn-outline" :disabled="busy || recaps.current_page <= 1" @click="paginate('recaps', -1)">Sebelumnya</button>
                    <button class="btn btn-outline" :disabled="busy || recaps.current_page >= recaps.last_page" @click="paginate('recaps', 1)">Berikutnya</button>
                </div>
            </section>

            <section class="card p-4 space-y-4" x-show="phone?.tracking">
                <h2 class="font-semibold">Order dan pengiriman Purchase</h2>
                <p class="text-sm">Status Diterima Meta mengonfirmasi event masuk ke dataset. Lihat atribusi iklannya di Ads Manager.</p>
                <p class="text-sm" x-show="!orders.data.length">Belum ada order dari rekap.</p>
                <div class="overflow-x-auto" x-show="orders.data.length">
                    <table class="w-full text-sm text-left">
                        <thead><tr>
                            <th class="p-2">Order</th><th class="p-2">Total</th><th class="p-2">Pembayaran</th><th class="p-2">Pengiriman Meta</th>
                        </tr></thead>
                        <tbody>
                            <template x-for="order in orders.data" :key="order.id">
                                <tr class="border-t align-top">
                                    <td class="p-2">
                                        <div class="font-medium" x-text="order.order_number"></div>
                                        <div x-text="order.contact"></div>
                                        <div x-show="order.click?.source_id" x-text="order.click?.source_id ? 'Iklan ' + order.click.source_id : ''"></div>
                                    </td>
                                    <td class="p-2" x-text="money(order.paid_total ?? order.recap_total)"></td>
                                    <td class="p-2 min-w-52">
                                        <div x-text="paymentStatus(order)"></div>
                                        <button class="btn btn-outline mt-2" x-show="access.confirm && phone?.tracking?.enabled_at && !order.paid_at && !order.pos_order_id && !order.has_pos_reference"
                                            :disabled="busy" @click="confirmation = { id: order.id, total: '', paidAt: '' }">Konfirmasi eksternal</button>
                                    </td>
                                    <td class="p-2">
                                        <div x-text="statusLabels[order.delivery?.status] || '—'"></div>
                                        <div class="mt-1 text-xs" x-show="order.delivery?.error" x-text="order.delivery?.error"></div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <form class="flex flex-wrap items-end gap-3 border-t pt-3" x-show="confirmation.id" @submit.prevent="confirmPayment()">
                    <label class="grid gap-1 text-sm">Nominal yang sudah diterima (IDR)
                        <input class="input" type="number" min="0.01" step="0.01" x-model="confirmation.total" required>
                    </label>
                    <label class="grid gap-1 text-sm">Waktu pelunasan (zona waktu perangkat)
                        <input class="input" type="datetime-local" step="1" x-model="confirmation.paidAt" required>
                    </label>
                    <button class="btn btn-primary" :disabled="busy">Konfirmasi lunas</button>
                    <button type="button" class="btn btn-outline" @click="confirmation = {}" :disabled="busy">Batal</button>
                </form>
                <div class="flex gap-2" x-show="orders.last_page > 1">
                    <button class="btn btn-outline" :disabled="busy || orders.current_page <= 1" @click="paginate('orders', -1)">Sebelumnya</button>
                    <button class="btn btn-outline" :disabled="busy || orders.current_page >= orders.last_page" @click="paginate('orders', 1)">Berikutnya</button>
                </div>
            </section>

            <section class="card p-4 space-y-4" x-show="access.report">
                <h2 class="font-semibold">Laporan iklan</h2>
                <p class="text-sm" x-show="!adAccounts.length">Belum ada akun iklan terhubung.</p>
                <form class="flex flex-wrap items-end gap-3" x-show="adAccounts.length" @submit.prevent="loadReport()">
                    <label class="grid gap-1 text-sm">Akun iklan
                        <select class="input" x-model="adAccountId" @change="pickAdAccount()" :disabled="busy">
                            <template x-for="account in adAccounts" :key="account.id">
                                <option :value="account.id" x-text="account.name + ' (' + account.account_id + ')'"></option>
                            </template>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">Mulai
                        <input class="input" type="date" x-model="filters.from" required>
                    </label>
                    <label class="grid gap-1 text-sm">Sampai
                        <input class="input" type="date" x-model="filters.to" required>
                    </label>
                    <label class="grid gap-1 text-sm">Tingkat laporan
                        <select class="input" x-model="filters.level">
                            <option value="account">Akun iklan</option><option value="campaign">Campaign</option><option value="adset">Ad set</option><option value="ad">Iklan</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">ID campaign
                        <input class="input w-36" inputmode="numeric" x-model.trim="filters.campaign_id">
                    </label>
                    <label class="grid gap-1 text-sm">ID ad set
                        <input class="input w-36" inputmode="numeric" x-model.trim="filters.adset_id">
                    </label>
                    <label class="grid gap-1 text-sm">ID iklan
                        <input class="input w-36" inputmode="numeric" x-model.trim="filters.ad_id">
                    </label>
                    <button class="btn btn-primary" :disabled="busy">Tampilkan</button>
                </form>
                <div x-show="report" class="space-y-2">
                    <p class="text-sm" x-text="report ? 'Zona waktu akun iklan ' + report.timezone : ''"></p>
                    <p class="text-sm" x-show="report && !report.insights_available">Sebagian data Ads Insights sedang tidak tersedia.</p>
                    <p class="text-sm" x-show="report && !report.has_tracking_coverage">Periode ini mencakup waktu sebelum TrackCTWA aktif.</p>
                    <p class="text-sm" x-show="report && !report.rows.length">Tidak ada data untuk filter ini.</p>
                    <div class="overflow-x-auto" x-show="report?.rows.length">
                        <table class="w-full text-sm text-left">
                            <thead><tr>
                                <th class="p-2">Nama</th><th class="p-2">Chat</th><th class="p-2">Order lunas</th>
                                <th class="p-2">Omzet WhatsApp</th><th class="p-2">Biaya iklan</th><th class="p-2">ROAS QashierWise</th>
                                <th class="p-2">ROAS Meta · Semua sumber</th><th class="p-2">Pengiriman Meta</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="row in (report?.rows || [])" :key="row.ad_id || row.adset_id || row.campaign_id || row.account_id">
                                    <tr class="border-t align-top">
                                        <td class="p-2 min-w-40">
                                            <span x-show="!row.campaign_id" x-text="row.account_name"></span>
                                            <button class="underline text-left" x-show="row.campaign_id" x-text="row.campaign_name || row.campaign_id" @click="drill(row, 'campaign')" :disabled="busy"></button>
                                            <button class="block underline text-left" x-show="row.adset_id" x-text="row.adset_name || row.adset_id" @click="drill(row, 'adset')" :disabled="busy"></button>
                                            <span class="block" x-show="row.ad_id" x-text="row.ad_name || row.ad_id"></span>
                                        </td>
                                        <td class="p-2" x-text="row.chats"></td><td class="p-2" x-text="row.paid_orders"></td>
                                        <td class="p-2" x-text="money(row.revenue)"></td><td class="p-2" x-text="money(row.spend, row.currency)"></td>
                                        <td class="p-2" x-text="roas(row.qashierwise_roas)"></td><td class="p-2" x-text="roas(row.meta_roas)"></td>
                                        <td class="p-2 min-w-40">
                                            <div x-text="'Menunggu ' + row.delivery.pending"></div>
                                            <div x-text="'Diterima Meta ' + row.delivery.accepted"></div>
                                            <div x-text="'Gagal ' + row.delivery.failed"></div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
function trackCtwaPage() {
    return {
        sidebarOpen: window.innerWidth >= 1024,
        isMobile: window.innerWidth < 1024,
        access: {}, phones: [], adAccounts: [], phoneId: '', adAccountId: '',
        loaded: false, busy: false, error: '', notice: '',
        datasetId: '', eventsToken: '', adsLoginConfig: null, adsSdkReady: false, connectingAds: false,
        recaps: { data: [] }, orders: { data: [] }, confirmation: {},
        filters: { from: '', to: '', level: 'ad', campaign_id: '', adset_id: '', ad_id: '' },
        report: null,
        statusLabels: { pending: 'Menunggu', accepted: 'Diterima Meta', failed: 'Gagal', unattributed: 'Tanpa atribusi' },
        reviewLabels: {
            invalid_format: 'Nomor order atau total tidak dapat dibaca.',
            contact_conflict: 'Nomor order sudah tercatat untuk kontak lain.',
            total_conflict: 'Total berbeda dari rekap sebelumnya.'
        },
        get phone() { return this.phones.find(phone => String(phone.id) === String(this.phoneId)); },
        async init() {
            window.addEventListener('resize', () => { this.isMobile = window.innerWidth < 1024; });
            this.loadAdsLoginConfig();
            await this.run(async () => {
                await this.loadSettings();
                this.resetPhone();
                this.pickAdAccount();
                await this.loadPhone();
                this.loaded = true;
            });
        },
        async api(path = '', method = 'GET', data) {
            const response = await fetch('/api/track-ctwa' + path, {
                method,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + localStorage.getItem('token') },
                body: data === undefined ? undefined : JSON.stringify(data)
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Permintaan TrackCTWA gagal.');
            return result;
        },
        async run(action) {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            this.notice = '';
            try { await action(); }
            catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async loadSettings() {
            const result = await this.api();
            this.access = result.access;
            this.phones = result.phones;
            this.adAccounts = result.ad_accounts;
            if (!this.phone) this.phoneId = String(this.phones[0]?.id ?? '');
            if (!this.adAccountId) this.adAccountId = String(this.adAccounts[0]?.id ?? '');
        },
        resetPhone() {
            this.datasetId = this.phone?.tracking?.dataset_id || '';
            this.eventsToken = '';
            this.confirmation = {};
            this.recaps = { data: [] };
            this.orders = { data: [] };
        },
        pickPhone() {
            this.resetPhone();
            return this.run(() => this.loadPhone());
        },
        async loadPhone() {
            if (!this.phone?.tracking) return;
            const path = '/phones/' + this.phoneId;
            const [orders, recaps] = await Promise.all([
                this.api(path + '/orders'),
                this.access.review ? this.api(path + '/recaps') : null
            ]);
            this.orders = orders;
            if (recaps) this.recaps = recaps;
        },
        paginate(list, step) {
            return this.run(async () => {
                this[list] = await this.api('/phones/' + this.phoneId + '/' + list + '?page=' + (this[list].current_page + step));
            });
        },
        configurePhone(enabled) {
            return this.run(async () => {
                await this.api('/phones/' + this.phoneId, 'PUT', {
                    enabled, dataset_id: enabled ? this.datasetId : null, events_token: enabled ? this.eventsToken : null
                });
                await this.loadSettings();
                this.resetPhone();
                await this.loadPhone();
                this.notice = enabled ? 'Dataset terverifikasi. TrackCTWA aktif.' : 'TrackCTWA dinonaktifkan.';
            });
        },
        async loadAdsLoginConfig() {
            try {
                this.adsLoginConfig = await this.api('/ads-login/config');
                if (this.adsLoginConfig.app_id && this.adsLoginConfig.config_id) {
                    await new Promise((resolve, reject) => {
                        if (window.FB) return resolve();
                        const script = document.createElement('script');
                        script.src = 'https://connect.facebook.net/en_US/sdk.js';
                        script.async = true;
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                    window.FB.init({ appId: this.adsLoginConfig.app_id, version: this.adsLoginConfig.api_version, xfbml: false, cookie: true });
                    this.adsSdkReady = true;
                }
            } catch (error) {
                this.error = 'Koneksi Meta belum siap. Muat ulang halaman untuk mencoba lagi.';
            }
        },
        connectAds() {
            if (!window.FB || !this.adsLoginConfig?.config_id || this.connectingAds) return;
            this.error = '';
            this.connectingAds = true;
            window.FB.login(async (response) => {
                try {
                    if (!response.authResponse?.code) return;
                    const result = await this.api('/ads-login/callback', 'POST', { code: response.authResponse.code });
                    await this.loadSettings();
                    this.adAccountId = String(result.accounts[0].id);
                    this.pickAdAccount();
                    this.notice = result.accounts.length + ' akun iklan terhubung.';
                } catch (error) {
                    this.error = error.message;
                } finally {
                    this.connectingAds = false;
                }
            }, {
                config_id: this.adsLoginConfig.config_id,
                response_type: 'code',
                override_default_response_type: true,
            });
        },
        review(recap, action) {
            return this.run(async () => {
                const data = action === 'resolve' ? { action, order_number: recap.order_number, total: recap.total } : { action };
                await this.api('/phones/' + this.phoneId + '/recaps/' + recap.id, 'PATCH', data);
                await this.loadPhone();
                this.notice = action === 'resolve' ? 'Rekap disimpan.' : 'Rekap diabaikan.';
            });
        },
        confirmPayment() {
            return this.run(async () => {
                const date = new Date(this.confirmation.paidAt).toISOString().replace(/\.\d{3}Z$/, '+00:00');
                await this.api('/phones/' + this.phoneId + '/orders/' + this.confirmation.id + '/confirm', 'POST', { total: this.confirmation.total, paid_at: date });
                this.confirmation = {};
                await this.loadPhone();
                this.notice = 'Pembayaran dikonfirmasi.';
            });
        },
        pickAdAccount() {
            this.report = null;
            this.filters.campaign_id = this.filters.adset_id = this.filters.ad_id = '';
            const account = this.adAccounts.find(item => String(item.id) === String(this.adAccountId));
            if (!account) return;
            const parts = new Intl.DateTimeFormat('en', { timeZone: account.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
            const date = Object.fromEntries(parts.map(part => [part.type, part.value]));
            this.filters.to = [date.year, date.month, date.day].join('-');
            const from = new Date(this.filters.to + 'T00:00:00Z');
            from.setUTCDate(from.getUTCDate() - 6);
            this.filters.from = from.toISOString().slice(0, 10);
        },
        loadReport() {
            return this.run(async () => {
                this.report = null;
                this.report = await this.api('/ad-accounts/' + this.adAccountId + '/report?' + new URLSearchParams(this.filters));
            });
        },
        drill(row, level) {
            this.filters.campaign_id = row.campaign_id || '';
            this.filters.adset_id = level === 'adset' ? row.adset_id : '';
            this.filters.ad_id = '';
            this.filters.level = level === 'adset' ? 'ad' : 'adset';
            return this.loadReport();
        },
        dateLabel(value) { return new Date(value).toLocaleString('id-ID'); },
        paymentStatus(order) {
            if (order.paid_at) return 'Lunas · ' + this.dateLabel(order.paid_at);
            if (order.pos_order_id) return 'Menunggu pelunasan POS';
            if (order.has_pos_reference) return 'Periksa kecocokan order dan pelanggan POS';
            return 'Belum lunas';
        },
        money(value, currency = 'IDR') {
            return value == null ? '—' : new Intl.NumberFormat('id-ID', { style: 'currency', currency }).format(value);
        },
        roas(value) {
            return value == null ? '—' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value) + '×';
        }
    };
}
</script>
@endpush
