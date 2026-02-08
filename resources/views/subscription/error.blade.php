@extends('layouts.app')

@section('title', 'Payment Error - QashierWise')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-red-50 via-white to-gray-50 flex items-center justify-center px-4">
    <div class="max-w-2xl w-full">
        <!-- Error Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12 text-center">
            <!-- Error Icon -->
            <div class="mb-6">
                <div class="h-20 w-20 rounded-full bg-red-100 flex items-center justify-center mx-auto">
                    <i class="fas fa-exclamation-circle text-4xl text-red-600"></i>
                </div>
            </div>

            <!-- Error Message -->
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Payment Error
            </h1>
            <p class="text-lg text-gray-600 mb-8">
                We encountered an error while processing your subscription payment.
            </p>

            <!-- Error Details -->
            @if(session('error'))
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8 text-left">
                <div class="flex items-start gap-3">
                    <i class="fas fa-times-circle text-red-600 text-xl mt-1"></i>
                    <div>
                        <p class="font-semibold text-red-900 mb-2">Error Details</p>
                        <p class="text-sm text-red-800">
                            {{ session('error') }}
                        </p>
                    </div>
                </div>
            </div>
            @else
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8 text-left">
                <div class="flex items-start gap-3">
                    <i class="fas fa-times-circle text-red-600 text-xl mt-1"></i>
                    <div>
                        <p class="font-semibold text-red-900 mb-2">What went wrong?</p>
                        <p class="text-sm text-red-800">
                            There was an issue processing your payment. This could be due to:
                        </p>
                        <ul class="mt-2 space-y-1 text-sm text-red-800">
                            <li>• Insufficient funds</li>
                            <li>• Payment method declined</li>
                            <li>• Network connectivity issues</li>
                            <li>• Technical error on our end</li>
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            <!-- What To Do Next -->
            <div class="text-left mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">What should you do?</h2>
                <div class="space-y-3">
                    <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                        <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-blue-600 font-bold">1</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Check Your Payment Method</p>
                            <p class="text-sm text-gray-600">Ensure your payment method has sufficient funds and is valid</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                        <div class="h-8 w-8 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-purple-600 font-bold">2</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Try Again</p>
                            <p class="text-sm text-gray-600">Return to pricing page and attempt the payment again</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                        <div class="h-8 w-8 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-green-600 font-bold">3</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">Contact Support</p>
                            <p class="text-sm text-gray-600">If the problem persists, reach out to our support team</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <a href="{{ route('subscription.pricing') }}" 
                   class="block w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all">
                    <i class="fas fa-redo mr-2"></i>
                    Try Again
                </a>
                <a href="{{ route('dashboard') }}" 
                   class="block w-full py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all">
                    <i class="fas fa-home mr-2"></i>
                    Go to Dashboard
                </a>
            </div>

            <!-- Support Contact -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <div class="bg-blue-50 rounded-xl p-4">
                    <p class="font-semibold text-blue-900 mb-2">Need Immediate Help?</p>
                    <p class="text-sm text-blue-800 mb-3">
                        Our support team is here to assist you with any payment issues.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="mailto:support@qashierwise.com" 
                           class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all text-sm font-semibold">
                            <i class="fas fa-envelope"></i>
                            Email Support
                        </a>
                        <a href="https://wa.me/6281234567890" 
                           target="_blank"
                           class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all text-sm font-semibold">
                            <i class="fab fa-whatsapp"></i>
                            WhatsApp Support
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                No charges were made to your account. You can safely try again.
            </p>
        </div>

        <!-- Error Reference -->
        @if(request('error_code'))
        <div class="mt-4 text-center">
            <p class="text-xs text-gray-500">
                Error Reference: {{ request('error_code') }}
            </p>
        </div>
        @endif
    </div>
</div>
@endsection
