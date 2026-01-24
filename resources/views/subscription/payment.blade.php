@extends('layouts.app')

@section('title', 'Complete Payment - QashierWise')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 p-4">
    <div class="max-w-md w-full">
        <!-- Payment Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="h-16 w-16 rounded-full bg-gradient-to-r from-purple-600 to-indigo-600 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-credit-card text-3xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Complete Your Payment</h1>
                <p class="text-gray-600">You're subscribing to {{ $plan['name'] ?? 'Unknown Plan' }}</p>
            </div>

            <!-- Plan Details -->
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-gray-700 font-medium">Plan</span>
                    <span class="text-gray-900 font-semibold">{{ $plan['name'] ?? 'Unknown' }}</span>
                </div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-gray-700 font-medium">Duration</span>
                    <span class="text-gray-900 font-semibold">{{ $durationDetails['name'] ?? 'Unknown' }}</span>
                </div>
                @if(isset($durationDetails['discount']) && $durationDetails['discount'] > 0)
                <div class="flex items-center justify-between mb-4">
                    <span class="text-gray-700 font-medium">Discount</span>
                    <span class="text-green-600 font-semibold">{{ $durationDetails['discount'] }}%</span>
                </div>
                @endif
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-semibold text-gray-900">Total</span>
                        <span class="text-2xl font-bold text-purple-600">
                            Rp {{ number_format($durationDetails['price'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 text-right mt-1">
                        Rp {{ number_format($durationDetails['price_per_month'] ?? 0, 0, ',', '.') }}/month
                    </p>
                </div>
            </div>

            <!-- Pay Button -->
            <button 
                id="pay-button"
                class="w-full py-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center justify-center gap-2"
            >
                <i class="fas fa-lock"></i>
                <span>Proceed to Payment</span>
            </button>

            <!-- Security Notice -->
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    <i class="fas fa-shield-alt text-green-600"></i>
                    Secure payment powered by Midtrans
                </p>
            </div>

            <!-- Cancel Link -->
            <div class="mt-4 text-center">
                <a href="{{ route('subscription.pricing') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left"></i>
                    Back to pricing
                </a>
            </div>
        </div>

        <!-- Features List -->
        <div class="mt-8 bg-white rounded-2xl shadow-lg p-6">
            <h3 class="font-semibold text-gray-900 mb-4">What you'll get:</h3>
            <ul class="space-y-3">
                @if(isset($plan['features']) && is_array($plan['features']))
                    @foreach($plan['features'] as $feature)
                        <li class="flex items-start gap-3">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <span class="text-gray-700">{{ $feature }}</span>
                        </li>
                    @endforeach
                @endif
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<!-- Midtrans Snap JS -->
@if(config('midtrans.is_production'))
<script src="https://app.midtrans.com/snap/snap.js" data-client-key="{{ $clientKey }}"></script>
@else
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ $clientKey }}"></script>
@endif

<script>
    document.getElementById('pay-button').addEventListener('click', function() {
        // Disable button to prevent double clicks
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Loading...</span>';
        
        // Trigger Snap payment
        snap.pay('{{ $snapToken }}', {
            onSuccess: function(result) {
                console.log('Payment success:', result);
                // Clear any cached subscription data to force refresh
                sessionStorage.removeItem('subscription_cache');
                localStorage.removeItem('subscription_cache');
                window.location.href = '{{ route('subscription.success') }}';
            },
            onPending: function(result) {
                console.log('Payment pending:', result);
                window.location.href = '{{ route('subscription.manage') }}';
            },
            onError: function(result) {
                console.error('Payment error:', result);
                window.location.href = '{{ route('subscription.error') }}';
            },
            onClose: function() {
                console.log('Payment popup closed');
                // Re-enable button
                document.getElementById('pay-button').disabled = false;
                document.getElementById('pay-button').innerHTML = '<i class="fas fa-lock"></i> <span>Proceed to Payment</span>';
            }
        });
    });
</script>
@endpush
@endsection
