@extends('layouts.app')

@section('title', 'Subscription Successful - QashierWise')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-green-50 via-white to-blue-50 flex items-center justify-center px-4">
    <div class="max-w-2xl w-full">
        <!-- Success Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12 text-center">
            <!-- Success Icon -->
            <div class="mb-6">
                <div class="h-20 w-20 rounded-full bg-green-100 flex items-center justify-center mx-auto animate-bounce">
                    <i class="fas fa-check text-4xl text-green-600"></i>
                </div>
            </div>

            <!-- Success Message -->
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Payment Successful!
            </h1>
            <p class="text-lg text-gray-600 mb-8">
                Thank you for subscribing to QashierWise. Your subscription is now active.
            </p>

            <!-- Subscription Details -->
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl p-6 mb-8">
                <div class="flex items-center justify-center gap-4 mb-4">
                    <div class="h-12 w-12 rounded-xl bg-purple-100 flex items-center justify-center">
                        @if(request('plan') === 'pro')
                            <i class="fas fa-crown text-2xl text-purple-600"></i>
                        @else
                            <i class="fas fa-star text-2xl text-blue-600"></i>
                        @endif
                    </div>
                    <div class="text-left">
                        <p class="text-sm text-gray-600">You're now on</p>
                        <p class="text-2xl font-bold text-gray-900 capitalize">
                            {{ request('plan', 'Standard') }} Plan
                        </p>
                    </div>
                </div>
                <p class="text-sm text-gray-600">
                    You now have access to all premium features
                </p>
            </div>

            <!-- What's Next -->
            <div class="text-left mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">What's Next?</h2>
                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-check text-blue-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Access Your Dashboard</p>
                            <p class="text-sm text-gray-600">Start managing your WhatsApp communications</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="h-8 w-8 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-check text-purple-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Connect WhatsApp</p>
                            <p class="text-sm text-gray-600">Link your WhatsApp Business account</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="h-8 w-8 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Setup AI Agent</p>
                            <p class="text-sm text-gray-600">Configure your automated assistant</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <a href="{{ route('dashboard') }}" 
                   class="block w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all">
                    <i class="fas fa-home mr-2"></i>
                    Go to Dashboard
                </a>
                <a href="{{ route('subscription.manage') }}" 
                   class="block w-full py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all">
                    <i class="fas fa-cog mr-2"></i>
                    Manage Subscription
                </a>
            </div>

            <!-- Support Info -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    Need help getting started? 
                    <a href="mailto:support@qashierwise.com" class="text-purple-600 hover:text-purple-700 font-semibold">
                        Contact Support
                    </a>
                </p>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                A confirmation email has been sent to your registered email address.
            </p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Confetti animation on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Simple confetti effect (optional - requires confetti library)
        console.log('Payment successful!');
    });
</script>
@endpush
@endsection
