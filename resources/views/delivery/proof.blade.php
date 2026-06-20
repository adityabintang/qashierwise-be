<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pengantaran - #{{ $order->order_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Leaflet via cdnjs (already allowlisted in the app CSP for script-src + style-src).
         Free, no API key, interactive tap-to-pin map (reliable on mobile). --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-start justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden my-4 ring-1 ring-purple-100">
        <!-- Header -->
        <div class="bg-gradient-to-br from-purple-600 to-purple-700 text-white p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white/15 mb-2 text-2xl">🛵</div>
            <h1 class="text-xl font-bold">Pengantaran Pesanan</h1>
            <p class="text-purple-100 text-sm mt-1">#{{ $order->order_number }} • {{ $order->store->name ?? 'Toko' }}</p>
        </div>

        <div class="p-6 space-y-5">
            {{-- Flash messages --}}
            @if(session('success'))
                <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Customer / order info --}}
            <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Pembeli</span><span class="font-medium text-right">{{ $order->customer_name ?: '-' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">No. HP</span><span class="font-medium text-right">{{ $order->customer_phone ?: '-' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-gray-500 shrink-0">Alamat</span><span class="font-medium text-right">{{ $order->alamat ?: '-' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Total</span><span class="font-semibold text-right">Rp {{ number_format((float) $order->total, 0, ',', '.') }}</span></div>
                @if($order->catatan)
                    <div class="flex justify-between gap-3"><span class="text-gray-500 shrink-0">Catatan</span><span class="font-medium text-right">{{ $order->catatan }}</span></div>
                @endif
            </div>

            {{-- Quick actions --}}
            <div class="grid grid-cols-2 gap-3">
                @php
                    $mapsUrl = ($order->customer_lat !== null && $order->customer_lng !== null)
                        ? 'https://maps.google.com/?q=' . $order->customer_lat . ',' . $order->customer_lng
                        : ($order->alamat ? 'https://maps.google.com/?q=' . urlencode($order->alamat) : null);
                    $waPhone = preg_replace('/[^0-9]/', '', (string) $order->customer_phone);
                @endphp
                @if($mapsUrl)
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 h-11 rounded-lg bg-blue-50 text-blue-700 font-medium text-sm">
                        📍 Buka Lokasi
                    </a>
                @endif
                @if($waPhone)
                    <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 h-11 rounded-lg bg-purple-50 text-purple-700 font-medium text-sm">
                        💬 Chat Pembeli
                    </a>
                @endif
            </div>

            @if($done)
                {{-- Terminal state --}}
                <div class="text-center py-6">
                    @if($order->fulfillment_status === \App\Models\Order::FULFILLMENT_DELIVERED)
                        <div class="text-5xl mb-3">🎉</div>
                        <h2 class="text-lg font-bold text-emerald-600">Pesanan Telah Diterima</h2>
                        <p class="text-gray-500 text-sm mt-1">Terima kasih, tugas pengantaran selesai.</p>
                    @else
                        <div class="text-5xl mb-3">⚠️</div>
                        <h2 class="text-lg font-bold text-red-600">Ada Komplain</h2>
                        <p class="text-gray-500 text-sm mt-1">Pesanan ini sedang ditangani oleh petugas toko.</p>
                    @endif
                </div>
            @elseif($awaitingCustomer)
                {{-- Proof already sent, waiting for buyer confirmation --}}
                <div class="text-center py-6">
                    <div class="text-5xl mb-3">⏳</div>
                    <h2 class="text-lg font-bold text-amber-600">Menunggu Konfirmasi Pembeli</h2>
                    <p class="text-gray-500 text-sm mt-1">Bukti sudah terkirim. Pembeli sedang diminta mengonfirmasi penerimaan.</p>
                    @if($order->proof_image_url)
                        <img src="{{ $order->proof_image_url }}" alt="Bukti" class="mt-4 rounded-lg w-full object-cover max-h-64">
                    @endif
                </div>
            @else
                {{-- Upload form: proof photo + map location + confirm --}}
                <form x-data="proofForm()" action="{{ route('delivery.proof.submit', $token) }}" method="POST" enctype="multipart/form-data" class="space-y-5" @submit="onSubmit($event)">
                    @csrf

                    {{-- 1. Proof photo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">1. Foto Bukti Pengantaran @if($proofRequired)<span class="text-red-500">*</span>@else<span class="text-gray-400 text-xs font-normal">(opsional)</span>@endif</label>
                        {{-- No `capture` attribute: on mobile this lets the driver choose camera OR gallery
                             (with `capture` the OS forces the camera and hides the gallery). Desktop ignores it. --}}
                        <input type="file" name="proof" accept="image/*" @if($proofRequired) required @endif x-ref="proof"
                               class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-600 file:text-white file:text-sm file:font-medium">
                        <p class="text-xs text-gray-400 mt-1">Foto barang saat diserahkan ke pembeli — bisa ambil foto langsung atau pilih dari galeri. Foto otomatis dikompres sebelum dikirim.</p>
                    </div>

                    {{-- 2. Delivery location (interactive map) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">2. Lokasi Pengantaran @if($addressRequired)<span class="text-red-500">*</span>@else<span class="text-gray-400 text-xs font-normal">(opsional)</span>@endif</label>

                        <div class="relative mb-2">
                            <input type="text" x-model="searchQuery" @input.debounce.500ms="searchLocation()"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Cari alamat / tempat...">
                            <div x-show="results.length" class="absolute z-[1000] left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg max-h-40 overflow-y-auto shadow">
                                <template x-for="r in results" :key="r.place_id">
                                    <div @click="select(r)" class="p-2 text-sm hover:bg-gray-50 cursor-pointer border-b last:border-0">
                                        <p class="font-medium" x-text="r.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="r.formatted_address"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <button type="button" @click="useMyLocation()" :disabled="locating"
                                class="w-full h-10 rounded-lg bg-blue-50 text-blue-700 font-medium text-sm flex items-center justify-center gap-2 mb-2">
                            <span x-show="!locating">📍 Gunakan Lokasi Saya</span>
                            <span x-show="locating">Mengambil lokasi...</span>
                        </button>

                        {{-- Interactive map: tap anywhere or drag the pin to set the point.
                             Works even when GPS is denied/unavailable. --}}
                        <div id="map" class="rounded-lg border border-gray-200" style="height: 240px; z-index: 0;"></div>
                        <p class="text-xs text-gray-400 mt-1">Ketuk peta atau geser pin untuk menandai titik pengantaran.</p>
                        <p x-show="lat && lng" class="text-xs text-gray-600 mt-1" x-text="address || (Number(lat).toFixed(6) + ', ' + Number(lng).toFixed(6))"></p>
                        <p x-show="geoError" class="text-xs text-amber-600 mt-1" x-text="geoError"></p>
                        <p x-show="addressRequired && (!lat || !lng)" class="text-xs text-amber-600 mt-1">Tandai lokasi terlebih dahulu.</p>

                        <input type="hidden" name="latitude" :value="lat">
                        <input type="hidden" name="longitude" :value="lng">
                    </div>

                    {{-- 3. Confirm --}}
                    <button type="submit" :disabled="submitting || (addressRequired && (!lat || !lng))"
                            class="w-full h-12 rounded-xl bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white font-semibold transition-colors shadow-sm">
                        <span x-show="!submitting">✅ Konfirmasi Pesanan Telah Diantarkan</span>
                        <span x-show="submitting">Mengirim...</span>
                    </button>
                    <p class="text-xs text-center text-gray-400">
                        Setelah dikonfirmasi, pembeli akan diminta memverifikasi penerimaan barang.
                    </p>
                </form>

                <script>
                function proofForm() {
                    return {
                        searchQuery: '', results: [], lat: '', lng: '', address: '', locating: false, geoError: '',
                        map: null, marker: null, submitting: false,
                        proofRequired: @json($proofRequired),
                        addressRequired: @json($addressRequired),
                        initialAddress: @json($order->alamat ?? ''),

                        init() {
                            // Default center: Indonesia. Refined below if the buyer's
                            // address can be geocoded, or when GPS / search is used.
                            this.$nextTick(() => {
                                this.map = L.map('map').setView([-2.5, 118], 5);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    maxZoom: 19, attribution: '&copy; OpenStreetMap'
                                }).addTo(this.map);

                                // Tap-to-pin.
                                this.map.on('click', (e) => this.setPoint(e.latlng.lat, e.latlng.lng, true));

                                // Try to center near the delivery address as a helpful start.
                                this.geocodeInitial();
                                // Fix tile rendering inside a flex/card container.
                                setTimeout(() => this.map.invalidateSize(), 200);
                            });
                        },

                        setPoint(lat, lng, reverse = false) {
                            this.lat = lat; this.lng = lng; this.geoError = '';
                            if (!this.marker) {
                                this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                                this.marker.on('dragend', (e) => {
                                    const p = e.target.getLatLng();
                                    this.setPoint(p.lat, p.lng, true);
                                });
                            } else {
                                this.marker.setLatLng([lat, lng]);
                            }
                            if (reverse) this.reverse(lat, lng);
                        },

                        useMyLocation() {
                            this.geoError = '';
                            if (!window.isSecureContext) {
                                this.geoError = 'Lokasi GPS butuh koneksi aman (HTTPS). Ketuk peta untuk menandai lokasi secara manual.';
                                return;
                            }
                            if (!navigator.geolocation) {
                                this.geoError = 'Perangkat tidak mendukung GPS. Ketuk peta atau cari alamat.';
                                return;
                            }
                            this.locating = true;
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    this.setPoint(pos.coords.latitude, pos.coords.longitude, true);
                                    this.map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                                    this.locating = false;
                                },
                                (err) => {
                                    const msg = {
                                        1: 'Izin lokasi ditolak. Aktifkan izin lokasi di browser, atau ketuk peta untuk menandai manual.',
                                        2: 'Lokasi tidak tersedia saat ini. Ketuk peta untuk menandai manual.',
                                        3: 'Pengambilan lokasi terlalu lama. Ketuk peta untuk menandai manual.'
                                    }[err.code] || 'Tidak bisa mengambil lokasi. Ketuk peta untuk menandai manual.';
                                    this.geoError = msg;
                                    this.locating = false;
                                },
                                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                            );
                        },

                        async searchLocation() {
                            if (!this.searchQuery || this.searchQuery.length < 3) { this.results = []; return; }
                            try {
                                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(this.searchQuery)}&limit=5`);
                                const data = await res.json();
                                this.results = data.map(i => ({ place_id: i.place_id, name: i.display_name.split(',')[0], formatted_address: i.display_name, lat: parseFloat(i.lat), lon: parseFloat(i.lon) }));
                            } catch (e) { this.results = []; }
                        },
                        select(r) {
                            this.address = r.formatted_address; this.results = []; this.searchQuery = '';
                            this.setPoint(r.lat, r.lon, false);
                            this.map.setView([r.lat, r.lon], 17);
                        },
                        async geocodeInitial() {
                            if (!this.initialAddress) return;
                            try {
                                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(this.initialAddress)}&limit=1`);
                                const data = await res.json();
                                if (data && data[0]) this.map.setView([parseFloat(data[0].lat), parseFloat(data[0].lon)], 15);
                            } catch (e) {}
                        },
                        async reverse(lat, lon) {
                            try {
                                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
                                const data = await res.json();
                                if (data.display_name) this.address = data.display_name;
                            } catch (e) {}
                        },
                        // Compress the photo in-browser before upload so it never
                        // hits PHP's upload_max_filesize (phone photos are 2-8MB;
                        // the server limit is often 2MB → "failed to upload").
                        compressImage(file, maxDim = 1600, quality = 0.7) {
                            return new Promise((resolve) => {
                                const url = URL.createObjectURL(file);
                                const img = new Image();
                                img.onload = () => {
                                    let w = img.width, h = img.height;
                                    if (w > maxDim || h > maxDim) {
                                        if (w >= h) { h = Math.round(h * maxDim / w); w = maxDim; }
                                        else { w = Math.round(w * maxDim / h); h = maxDim; }
                                    }
                                    const canvas = document.createElement('canvas');
                                    canvas.width = w; canvas.height = h;
                                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                                    URL.revokeObjectURL(url);
                                    canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
                                };
                                img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
                                img.src = url;
                            });
                        },

                        async onSubmit(e) {
                            e.preventDefault();
                            if (this.submitting) return;

                            if (this.addressRequired && (!this.lat || !this.lng)) {
                                alert('Tandai lokasi pengantaran terlebih dahulu (ketuk peta atau gunakan GPS).');
                                return;
                            }

                            const input = this.$refs.proof;
                            const hasFile = input.files && input.files[0];
                            if (this.proofRequired && !hasFile) {
                                alert('Unggah foto bukti pengantaran terlebih dahulu.');
                                return;
                            }

                            this.submitting = true;
                            try {
                                if (hasFile && input.files[0].type && input.files[0].type.startsWith('image/')) {
                                    const blob = await this.compressImage(input.files[0]);
                                    if (blob && window.DataTransfer) {
                                        const compressed = new File([blob], 'bukti-pengantaran.jpg', { type: 'image/jpeg' });
                                        const dt = new DataTransfer();
                                        dt.items.add(compressed);
                                        input.files = dt.files;
                                    }
                                }
                            } catch (err) {
                                // Fall back to the original file if compression fails.
                                console.error('compress failed', err);
                            }
                            // Native submit bypasses this @submit handler (no recursion).
                            e.target.submit();
                        },
                    };
                }
                </script>
            @endif
        </div>
    </div>
</body>
</html>
