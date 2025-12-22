<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran QRIS - {{ $transaction->order_id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .qris-container { max-width: 400px; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="qris-container w-full bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-purple-600 text-white p-6 text-center">
            <h1 class="text-xl font-bold">Pembayaran QRIS</h1>
            <p class="text-purple-200 text-sm mt-1">{{ $transaction->subMerchant->business_name ?? 'Merchant' }}</p>
        </div>

        <div class="p-6">
            @if($isPaid)
                <!-- Payment Success -->
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-green-600 mb-2">Pembayaran Berhasil!</h2>
                    <p class="text-gray-600">Terima kasih atas pembayaran Anda</p>
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-gray-800">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</p>
                    </div>
                </div>

            @elseif($isExpired)
                <!-- Payment Expired -->
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-red-600 mb-2">Pembayaran Kedaluwarsa</h2>
                    <p class="text-gray-600">Waktu pembayaran telah habis</p>
                </div>

            @elseif($isCancelled)
                <!-- Payment Cancelled -->
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-600 mb-2">Pembayaran Dibatalkan</h2>
                    <p class="text-gray-600">Transaksi ini telah dibatalkan</p>
                </div>

            @else
                <!-- QR Code Display -->
                <div class="text-center">
                    <!-- Amount -->
                    <div class="mb-4">
                        <p class="text-gray-500 text-sm">Total Pembayaran</p>
                        <p class="text-3xl font-bold text-gray-800">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</p>
                    </div>

                    <!-- QR Code -->
                    <div class="bg-white p-4 rounded-xl border-2 border-gray-200 inline-block mb-4">
                        @if($transaction->qr_code_url)
                            <img src="{{ $transaction->qr_code_url }}" alt="QRIS Code" class="w-64 h-64 mx-auto">
                        @else
                            <div class="w-64 h-64 bg-gray-200 flex items-center justify-center">
                                <p class="text-gray-500">QR Code tidak tersedia</p>
                            </div>
                        @endif
                    </div>

                    <!-- Timer -->
                    <div class="mb-4">
                        <p class="text-gray-500 text-sm">Berlaku hingga</p>
                        <p class="text-lg font-semibold text-orange-500" id="countdown">
                            {{ $transaction->expires_at->format('H:i:s') }}
                        </p>
                    </div>

                    <!-- Instructions -->
                    <div class="text-left bg-gray-50 rounded-lg p-4 mt-4">
                        <p class="font-semibold text-gray-700 mb-2">Cara Pembayaran:</p>
                        <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                            <li>Buka aplikasi e-wallet atau mobile banking</li>
                            <li>Pilih menu Scan QR atau QRIS</li>
                            <li>Scan kode QR di atas</li>
                            <li>Konfirmasi pembayaran</li>
                        </ol>
                    </div>

                    <!-- Order ID -->
                    <div class="mt-4 text-xs text-gray-400">
                        Order ID: {{ $transaction->order_id }}
                    </div>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 p-4 text-center border-t">
            <p class="text-xs text-gray-500">Powered by QashierWise</p>
        </div>
    </div>

    @if(!$isPaid && !$isExpired && !$isCancelled)
    <script>
        // Countdown timer
        const expiresAt = new Date('{{ $transaction->expires_at->toIso8601String() }}');
        
        function updateCountdown() {
            const now = new Date();
            const diff = expiresAt - now;
            
            if (diff <= 0) {
                document.getElementById('countdown').textContent = 'Kedaluwarsa';
                location.reload();
                return;
            }
            
            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            document.getElementById('countdown').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);

        // Auto refresh to check payment status
        setInterval(() => {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(() => location.reload());
        }, 10000);
    </script>
    @endif
</body>
</html>
