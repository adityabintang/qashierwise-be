<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reservasi - {{ $merchant->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8" x-data="reservationForm()">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">📅 Buat Reservasi</h1>
                <p class="text-gray-600">{{ $merchant->name }}</p>
            </div>

            <!-- Form -->
            <div class="bg-white rounded-lg shadow-lg p-8" x-show="!showPayment">
                <form @submit.prevent="submitReservation">
                    <!-- Customer Information -->
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">👤 Informasi Pemesan</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                                <input
                                    type="text"
                                    x-model="formData.customer_name"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Masukkan nama lengkap"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor WhatsApp *</label>
                                <input
                                    type="tel"
                                    x-model="formData.phone"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="08xxxxxxxxxx"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input
                                    type="email"
                                    x-model="formData.email"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="email@example.com"
                                >
                                <p class="text-xs text-gray-500 mt-1">Undangan kalender akan dikirim ke email ini</p>
                            </div>
                        </div>
                    </div>

                    <!-- Reservation Details -->
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">📅 Detail Reservasi</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Reservasi *</label>
                                <select
                                    x-model="formData.reservation_date"
                                    @change="loadAvailableTables()"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Pilih Tanggal</option>
                                    @foreach($availableDates as $date)
                                        <option value="{{ $date['id'] }}" {{ !$date['available'] ? 'disabled' : '' }}>
                                            {{ $date['title'] }} {{ $date['available'] ? '(Available)' : '(Unavailable)' }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-2">
                                    Tanggal dengan label "Available" memiliki meja kosong untuk dipesan
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Meja *</label>
                                <select
                                    x-model="formData.table_id"
                                    @change="updateGuestCountFromTable()"
                                    required
                                    :disabled="!formData.reservation_date || loadingTables"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Pilih meja...</option>
                                    <template x-for="table in availableTables" :key="table.id">
                                        <option :value="table.id" x-text="table.label"></option>
                                    </template>
                                </select>
                                <p class="text-xs text-gray-500 mt-2">💡 Pilih meja sesuai dengan jumlah tamu Anda. Kapasitas meja akan otomatis terisi. Gunakan catatan di bawah jika perlu penyesuaian.</p>
                                <p class="text-xs text-gray-500 mt-1" x-show="loadingTables">Memuat meja tersedia...</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan untuk Restoran (Opsional)</label>
                                <textarea
                                    x-model="formData.notes"
                                    rows="3"
                                    placeholder="Contoh: Saya butuh untuk 3 orang tapi hanya tersedia meja untuk 4 orang. Atau permintaan khusus lainnya..."
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                ></textarea>
                                <p class="text-xs text-gray-500 mt-1">Tuliskan permintaan khusus atau penyesuaian yang Anda butuhkan di sini.</p>
                            </div>

                            @if($config->enable_menu_selection)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pre-order Makanan (Opsional)</label>
                                <button
                                    type="button"
                                    @click="loadProducts()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-left"
                                >
                                    <span x-show="getSelectedProductCount() === 0">Pilih makanan...</span>
                                    <span x-show="getSelectedProductCount() > 0" x-text="`${getSelectedProductCount()} item dipilih`"></span>
                                </button>
                                <div x-show="showProductList" class="mt-2 border border-gray-300 rounded-lg max-h-60 overflow-y-auto">
                                    <template x-for="product in availableProducts" :key="product.id">
                                        <div class="flex items-center justify-between gap-3 p-3 hover:bg-gray-50">
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-800" x-text="product.name"></p>
                                                <p class="text-xs text-gray-500" x-text="formatCurrency(product.price)"></p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    @click="decreaseProduct(product.id)"
                                                    class="h-8 w-8 rounded-full border border-gray-300 text-gray-600 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                                                    :disabled="getProductQuantity(product.id) === 0"
                                                >
                                                    -
                                                </button>
                                                <span class="w-6 text-center text-sm font-medium" x-text="getProductQuantity(product.id)"></span>
                                                <button
                                                    type="button"
                                                    @click="increaseProduct(product)"
                                                    class="h-8 w-8 rounded-full border border-gray-300 text-gray-600 hover:bg-gray-100"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="availableProducts.length === 0" class="p-3 text-sm text-gray-500">
                                        Tidak ada pilihan makanan tersedia.
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Payment Type -->
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">💳 Pembayaran</h2>

                        <div class="space-y-3">
                            @if($config->allow_dp_payment)
                            <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input
                                    type="radio"
                                    name="payment_type"
                                    value="dp"
                                    x-model="formData.payment_type"
                                    class="mt-1"
                                >
                                <div>
                                    <div class="font-medium">DP ({{ number_format($config->dp_percentage, 0) }}%)</div>
                                    <div class="text-sm text-gray-600">Bayar uang muka, pelunasan di tempat</div>
                                </div>
                            </label>
                            @endif

                            @if($config->allow_full_payment)
                            <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input
                                    type="radio"
                                    name="payment_type"
                                    value="full"
                                    x-model="formData.payment_type"
                                    class="mt-1"
                                >
                                <div>
                                    <div class="font-medium">Bayar Lunas</div>
                                    <div class="text-sm text-gray-600">Bayar penuh sekarang</div>
                                </div>
                            </label>
                            @endif
                        </div>

                        <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Metode Pembayaran:</span>
                                <span class="font-bold text-blue-600">QRIS</span>
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                            <div class="text-sm text-gray-600 mb-2">Ringkasan Biaya</div>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div class="flex justify-between">
                                    <span>Biaya reservasi</span>
                                    <span x-text="formatCurrency(reservationFee)"></span>
                                </div>
                                <div class="flex justify-between" x-show="getSelectedProductTotal() > 0">
                                    <span>Menu dipilih</span>
                                    <span x-text="formatCurrency(getSelectedProductTotal())"></span>
                                </div>
                                <div class="flex justify-between border-t border-blue-200 pt-2 mt-2">
                                    <span class="font-medium">Total</span>
                                    <span class="font-medium" x-text="formatCurrency(getTotalAmount())"></span>
                                </div>
                                <template x-if="formData.payment_type === 'dp'">
                                    <div class="space-y-1">
                                        <div class="flex justify-between">
                                            <span>DP ({{ number_format($config->dp_percentage, 0) }}%)</span>
                                            <span x-text="formatCurrency(getDpAmount())"></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Sisa pembayaran</span>
                                            <span x-text="formatCurrency(getRemainingAmount())"></span>
                                        </div>
                                    </div>
                                </template>
                                <div class="flex justify-between border-t border-blue-200 pt-2 mt-2">
                                    <span class="font-medium">Total bayar sekarang</span>
                                    <span class="font-medium" x-text="formatCurrency(formData.payment_type === 'dp' ? getDpAmount() : getTotalAmount())"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div x-show="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-red-600 text-sm" x-text="error"></p>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition"
                    >
                        <span x-show="!submitting">Lanjutkan ke Pembayaran</span>
                        <span x-show="submitting">Memproses...</span>
                    </button>
                </form>
            </div>

            <!-- Payment Modal -->
            <div x-show="showPayment" class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Scan QRIS untuk Bayar</h2>
                    <p class="text-gray-600 mb-6">Order ID: <span class="font-mono font-bold" x-text="paymentData.order_id"></span></p>

                    <!-- QR Code -->
                    <div class="mb-6 flex justify-center">
                        <img :src="paymentData.qr_code_url" alt="QRIS Code" class="w-64 h-64 border-4 border-gray-300 rounded-lg">
                    </div>

                    <!-- Amount -->
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                        <div class="text-sm text-gray-600 mb-1">Jumlah Pembayaran</div>
                        <div class="text-3xl font-bold text-blue-600" x-text="formatCurrency(paymentData.amount)"></div>
                        <div class="text-sm text-gray-600 mt-1" x-text="paymentData.payment_type === 'dp' ? 'DP ' + dpPercentage + '%' : 'Lunas'"></div>

                        <div class="mt-4 text-left text-sm text-gray-600 space-y-1">
                            <div class="flex justify-between">
                                <span>Biaya reservasi</span>
                                <span x-text="formatCurrency(reservationFee)"></span>
                            </div>
                            <div class="flex justify-between" x-show="getSelectedProductTotal() > 0">
                                <span>Menu dipilih</span>
                                <span x-text="formatCurrency(getSelectedProductTotal())"></span>
                            </div>
                            <div class="flex justify-between border-t border-blue-200 pt-2 mt-2">
                                <span class="font-medium">Total</span>
                                <span class="font-medium" x-text="formatCurrency(getTotalAmount())"></span>
                            </div>
                            <template x-if="paymentData.payment_type === 'dp'">
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span>DP ({{ number_format($config->dp_percentage, 0) }}%)</span>
                                        <span x-text="formatCurrency(getDpAmount())"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Sisa pembayaran</span>
                                        <span x-text="formatCurrency(getRemainingAmount())"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Timer -->
                    <div class="mb-6">
                        <p class="text-sm text-gray-600 mb-2">Waktu tersisa:</p>
                        <p class="text-2xl font-bold" :class="timeRemaining < 300 ? 'text-red-600' : 'text-gray-900'" x-text="formatTime(timeRemaining)"></p>
                    </div>

                    <!-- Status -->
                    <div class="mb-6">
                        <div x-show="paymentStatus === 'checking'" class="flex items-center justify-center gap-2 text-blue-600">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Menunggu pembayaran...</span>
                        </div>

                        <div x-show="paymentStatus === 'success'" class="text-green-600">
                            <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="font-bold text-lg">Pembayaran Berhasil!</p>
                            <p class="text-sm text-gray-600 mt-2">Notifikasi telah dikirim ke WhatsApp Anda</p>
                        </div>
                    </div>

                    <button
                        @click="cancelReservation()"
                        class="text-red-600 hover:text-red-700 text-sm underline"
                        x-show="paymentStatus === 'checking'"
                    >
                        Batalkan Reservasi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function reservationForm() {
            return {
                merchantName: new URLSearchParams(window.location.search).get('merchantName') || '',
                reservationFee: @json($config->reservation_fee ?? 0),
                dpPercentage: @json((float) ($config->dp_percentage ?? 0)),
                formData: {
                    customer_name: '',
                    phone: '',
                    email: '',
                    reservation_date: '',
                    guest_count: 1,
                    table_id: '',
                    notes: '',
                    selected_products: [],
                    payment_type: @json($config->allow_full_payment ? 'full' : ($config->allow_dp_payment ? 'dp' : '')),
                    store_id: @json($defaultStoreId),
                },
                availableTables: [],
                availableProducts: [],
                loadingTables: false,
                showProductList: false,
                submitting: false,
                error: '',
                showPayment: false,
                paymentData: {},
                paymentStatus: 'checking',
                timeRemaining: 1800,
                statusInterval: null,
                timerInterval: null,

                async loadAvailableTables() {
                    if (!this.formData.reservation_date || !this.formData.store_id) return;

                    this.loadingTables = true;
                    try {
                        const params = new URLSearchParams({
                            merchantName: this.merchantName,
                            store_id: String(this.formData.store_id),
                            reservation_date: this.formData.reservation_date,
                        });
                        const response = await fetch(`/reservations/tables?${params.toString()}`);
                        const data = await response.json();
                        this.availableTables = data.success ? (data.data || []) : [];
                    } catch (error) {
                        console.error('Failed to load tables:', error);
                    } finally {
                        this.loadingTables = false;
                    }
                },

                updateGuestCountFromTable() {
                    const selectedTable = this.availableTables.find(t => t.id == this.formData.table_id);
                    if (selectedTable && selectedTable.capacity) {
                        this.formData.guest_count = selectedTable.capacity;
                    }
                },

                async loadProducts() {
                    this.showProductList = !this.showProductList;
                    if (this.availableProducts.length > 0) return;

                    try {
                        const params = new URLSearchParams({
                            merchantName: this.merchantName,
                        });
                        if (this.formData.store_id) {
                            params.set('store_id', String(this.formData.store_id));
                        }

                        const response = await fetch(`/reservations/products?${params.toString()}`);
                        const data = await response.json();
                        this.availableProducts = data.success ? (data.data || []) : [];
                    } catch (error) {
                        console.error('Failed to load products:', error);
                    }
                },

                getSelectedProductIndex(productId) {
                    return this.formData.selected_products.findIndex(item => item.id === productId);
                },

                getProductQuantity(productId) {
                    const selected = this.formData.selected_products.find(item => item.id === productId);
                    return selected ? Number(selected.quantity || 0) : 0;
                },

                getSelectedProductCount() {
                    return this.formData.selected_products
                        .reduce((total, item) => total + Number(item.quantity || 0), 0);
                },

                increaseProduct(product) {
                    const index = this.getSelectedProductIndex(product.id);
                    if (index === -1) {
                        this.formData.selected_products.push({
                            id: product.id,
                            quantity: 1,
                        });
                        return;
                    }

                    this.formData.selected_products[index].quantity += 1;
                },

                decreaseProduct(productId) {
                    const index = this.getSelectedProductIndex(productId);
                    if (index === -1) return;

                    const currentQty = Number(this.formData.selected_products[index].quantity || 0);
                    if (currentQty <= 1) {
                        this.formData.selected_products.splice(index, 1);
                        return;
                    }

                    this.formData.selected_products[index].quantity = currentQty - 1;
                },

                async submitReservation() {
                    this.submitting = true;
                    this.error = '';

                    try {
                        const url = new URL('/reservations/form/submit', window.location.origin);
                        url.searchParams.set('merchantName', this.merchantName);
                        const response = await fetch(url.toString(), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.formData)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.paymentData = data.data;
                            this.showPayment = true;
                            this.startPaymentMonitoring();
                        } else {
                            this.error = data.message;
                        }
                    } catch (error) {
                        this.error = 'Terjadi kesalahan. Silakan coba lagi.';
                        console.error('Submission error:', error);
                    } finally {
                        this.submitting = false;
                    }
                },

                startPaymentMonitoring() {
                    // Poll payment status every 5 seconds
                    this.statusInterval = setInterval(() => this.checkPaymentStatus(), 5000);

                    // Update timer every second
                    this.timerInterval = setInterval(() => {
                        this.timeRemaining--;
                        if (this.timeRemaining <= 0) {
                            this.stopMonitoring();
                            this.paymentStatus = 'expired';
                        }
                    }, 1000);
                },

                async checkPaymentStatus() {
                    try {
                        const url = new URL(`/reservations/form/status/${this.paymentData.order_id}`, window.location.origin);
                        url.searchParams.set('merchantName', this.merchantName);
                        const response = await fetch(url.toString());
                        const data = await response.json();

                        if (data.success && data.data.is_confirmed) {
                            this.paymentStatus = 'success';
                            this.stopMonitoring();
                        }
                    } catch (error) {
                        console.error('Status check error:', error);
                    }
                },

                stopMonitoring() {
                    if (this.statusInterval) clearInterval(this.statusInterval);
                    if (this.timerInterval) clearInterval(this.timerInterval);
                },

                cancelReservation() {
                    this.stopMonitoring();
                    this.showPayment = false;
                    this.paymentData = {};
                    this.paymentStatus = 'checking';
                    this.timeRemaining = 1800;
                },

                formatCurrency(amount) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
                },

                getSelectedProductTotal() {
                    return this.formData.selected_products.reduce((total, item) => {
                        const product = this.availableProducts.find((entry) => entry.id === item.id);
                        if (!product) return total;

                        return total + (Number(product.price || 0) * Number(item.quantity || 0));
                    }, 0);
                },

                getTotalAmount() {
                    return Number(this.reservationFee || 0) + this.getSelectedProductTotal();
                },

                getDpAmount() {
                    return Math.round(this.getTotalAmount() * (Number(this.dpPercentage || 0) / 100));
                },

                getRemainingAmount() {
                    return this.getTotalAmount() - this.getDpAmount();
                },

                formatTime(seconds) {
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    return `${mins}:${secs.toString().padStart(2, '0')}`;
                }
            }
        }
    </script>
</body>
</html>
