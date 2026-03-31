<div class="space-y-3 text-sm">
    <p class="font-semibold text-gray-900 dark:text-gray-100">
        Status <span class="text-red-600 dark:text-red-400">Published</span> tidak boleh memiliki tanggal publikasi di masa depan.
    </p>
    
    <div class="space-y-2">
        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border border-red-200 dark:border-red-800">
            <p class="font-medium text-red-900 dark:text-red-100 mb-1">📅 Tanggal yang Anda pilih:</p>
            <ul class="space-y-1 text-red-800 dark:text-red-200 ml-4">
                <li><strong>WIB:</strong> {{ $jakartaInput }}</li>
                <li><strong>UTC:</strong> {{ $utcInput }}</li>
            </ul>
        </div>
        
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
            <p class="font-medium text-gray-900 dark:text-gray-100 mb-1">⏰ Waktu sekarang:</p>
            <ul class="space-y-1 text-gray-700 dark:text-gray-300 ml-4">
                <li><strong>WIB:</strong> {{ $jakartaNow }}</li>
                <li><strong>UTC:</strong> {{ $utcNow }}</li>
            </ul>
        </div>
        
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
            <p class="font-medium text-blue-900 dark:text-blue-100 mb-1">💡 Solusi:</p>
            <ul class="list-disc list-inside space-y-1 text-blue-800 dark:text-blue-200 ml-2">
                <li>Pilih tanggal publikasi yang sudah lewat, atau</li>
                <li>Ubah status menjadi <strong>Scheduled</strong> untuk publikasi terjadwal</li>
            </ul>
        </div>
    </div>
</div>
