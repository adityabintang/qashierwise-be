@extends('layouts.app')

@section('title', 'Enter Card Details - QashierWise')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 p-4">
    <div class="max-w-md w-full">
        <!-- Card Tokenization Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="h-16 w-16 rounded-full bg-gradient-to-r from-purple-600 to-indigo-600 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-credit-card text-3xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Enter Card Details</h1>
                <p class="text-gray-600">Your card will be securely saved for subscription</p>
            </div>

            <!-- Plan Details -->
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl p-4 mb-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-700 font-medium">Plan</span>
                    <span class="text-gray-900 font-semibold">{{ $plan['name'] ?? 'Unknown' }}</span>
                </div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-700 font-medium">Duration</span>
                    <span class="text-gray-900 font-semibold">{{ $durationDetails['name'] ?? 'Unknown' }}</span>
                </div>
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-900 font-semibold">Total</span>
                        <span class="text-xl font-bold text-purple-600">
                            Rp {{ number_format($durationDetails['price'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card Form -->
            <form id="card-form" action="{{ route('subscription.create-subscription') }}" method="POST">
                @csrf
                <input type="hidden" name="card_token" id="card_token">

                <!-- Card Number -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                    <div class="relative">
                        <input type="text" id="card-number" maxlength="19"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            placeholder="4811 1111 1111 1114">
                        <div class="absolute right-3 top-3 flex space-x-1">
                            <i class="fab fa-cc-visa text-2xl text-gray-400"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Supported: Visa, Mastercard, JCB, Amex</p>
                </div>

                <!-- Expiry and CVV -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                        <input type="text" id="card-expiry" maxlength="7"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            placeholder="MM/YYYY">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                        <input type="password" id="card-cvv" maxlength="4"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            placeholder="123">
                    </div>
                </div>

                <!-- Error Message -->
                <div id="error-message" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600"></p>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submit-btn"
                    class="w-full py-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-lock"></i>
                    <span>Subscribe Now</span>
                </button>
            </form>

            <!-- Security Notice -->
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500 mb-2">
                    <i class="fas fa-shield-alt text-green-600"></i>
                    Your card is secured with PCI DSS compliant encryption
                </p>
                <p class="text-xs text-gray-400">
                    Powered by Midtrans. We never store your full card details.
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

        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                <div>
                    <h4 class="font-medium text-blue-900 mb-1">Subscription Info</h4>
                    <p class="text-sm text-blue-800">
                        Your subscription will automatically renew every {{ strtolower($durationDetails['name'] ?? 'month') }}.
                        You can cancel anytime from your subscription management page.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://js.midtrans.com/v2/assets/js/midtrans.min.js"></script>
<script>
    // Initialize Midtrans
    MidtransNew3ds.setup({
        sandbox: {{ config('midtrans.is_production') ? 'false' : 'true' }},
        client_key: '{{ $clientKey }}'
    });

    const cardForm = document.getElementById('card-form');
    const submitBtn = document.getElementById('submit-btn');
    const errorMessage = document.getElementById('error-message');
    const cardTokenInput = document.getElementById('card_token');

    // Format card number with spaces
    document.getElementById('card-number').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;
    });

    // Format expiry date
    document.getElementById('card-expiry').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 6);
        }
        e.target.value = value;
    });

    cardForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Processing...</span>';
        errorMessage.classList.add('hidden');

        const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
        const cardExpiry = document.getElementById('card-expiry').value;
        const cardCvv = document.getElementById('card-cvv').value;

        // Parse expiry
        const [expMonth, expYear] = cardExpiry.split('/');

        // Validate
        if (!cardNumber || !expMonth || !expYear || !cardCvv) {
            showError('Please fill in all card details');
            return;
        }

        try {
            // Get card token from Midtrans
            const response = await MidtransNew3ds.getCardToken({
                card_number: cardNumber,
                card_exp_month: expMonth,
                card_exp_year: expYear,
                card_cvv: cardCvv
            });

            if (response.status_code === '200' && response.token_id) {
                // Set token and submit form
                cardTokenInput.value = response.token_id;
                cardForm.submit();
            } else {
                showError(response.status_message || 'Failed to tokenize card. Please check your card details.');
            }
        } catch (error) {
            console.error('Tokenization error:', error);
            showError('An error occurred while processing your card. Please try again.');
        }
    });

    function showError(message) {
        errorMessage.querySelector('p').textContent = message;
        errorMessage.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-lock"></i> <span>Subscribe Now</span>';
    }
</script>
@endpush
@endsection
