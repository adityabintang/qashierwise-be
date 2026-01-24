@extends('layouts.app')

@section('title', 'Payment Cancelled - QashierWise')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-orange-50 via-white to-gray-50 flex items-center justify-center px-4">
    <div class="max-w-2xl w-full">
        <!-- Cancel Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12 text-center">
            <!-- Cancel Icon -->
            <div class="mb-6">
                <div class="h-20 w-20 rounded-full bg-orange-100 flex items-center justify-center mx-auto">
                    <i class="fas fa-times text-4xl text-orange-600"></i>
                </div>
            </div>

            <!-- Cancel Message -->
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Payment Cancelled
            </h1>
            <p class="text-lg text-gray-600 mb-8">
                Your subscription payment was cancelled. No charges have been made to your account.
            </p>

            <!-- Info Box -->
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-6 mb-8 text-left">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-orange-600 text-xl mt-1"></i>
                    <div>
                        <p class="font-semibold text-orange-900 mb-2">What happened?</p>
                        <p class="text-sm text-orange-800">
                            You cancelled the payment process before completing it. Your subscription status remains unchanged.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Current Status -->
            <div class="bg-gray-50 rounded-xl p-6 mb-8">
                <p class="text-sm text-gray-600 mb-2">Your Current Status</p>
                <p class="text-xl font-bold text-gray-900">
                    @if(auth()->user()->subscription && auth()->user()->subscription->status === 'active')
                        Active Subscription
                    @elseif(auth()->user()->subscription && auth()->user()->subscription->status === 'trial')
                        Free Trial
                    @else
                        No Active Subscription
                    @endif
                </p>
            </div>

            <!-- What Would You Like To Do? -->
            <div class="text-left mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">What would you like to do?</h2>
                <div class="space-y-3">
                    <a href="{{ route('subscription.pricing') }}" 
                       class="flex items-center justify-between p-4 bg-purple-50 hover:bg-purple-100 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-purple-600 flex items-center justify-center">
                                <i class="fas fa-redo text-white"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Try Again</p>
                                <p class="text-sm text-gray-600">Complete your subscription</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </a>

                    <a href="{{ route('dashboard') }}" 
                       class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-gray-600 flex items-center justify-center">
                                <i class="fas fa-home text-white"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Go to Dashboard</p>
                                <p class="text-sm text-gray-600">Return to your account</p>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                    </a>
                </div>
            </div>

            <!-- Why Subscribe? -->
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl p-6 text-left">
                <h3 class="font-bold text-gray-900 mb-3">Why subscribe to QashierWise?</h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-check text-green-600 mt-1"></i>
                        <span>Automate WhatsApp communications with AI</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-check text-green-600 mt-1"></i>
                        <span>Manage unlimited contacts and messages</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-check text-green-600 mt-1"></i>
                        <span>Advanced analytics and reporting</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-check text-green-600 mt-1"></i>
                        <span>Priority customer support</span>
                    </li>
                </ul>
            </div>

            <!-- Support Info -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    Have questions or need help? 
                    <a href="mailto:support@qashierwise.com" class="text-purple-600 hover:text-purple-700 font-semibold">
                        Contact Support
                    </a>
                </p>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                You can try subscribing again anytime from your dashboard.
            </p>
        </div>
    </div>
</div>
@endsection
