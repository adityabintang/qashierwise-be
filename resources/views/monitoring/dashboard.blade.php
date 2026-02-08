@extends('layouts.app')

@section('title', 'Subscription Monitoring Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Subscription Monitoring</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Real-time monitoring of subscription system health and performance</p>
    </div>

    <!-- Health Status -->
    <div class="mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">System Health</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Last updated: {{ $health['timestamp'] }}</p>
                </div>
                <div class="flex items-center">
                    @if($health['status'] === 'healthy')
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Healthy
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Degraded
                        </span>
                    @endif
                </div>
            </div>

            @if(!empty($health['issues']))
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Active Issues</h3>
                    <div class="space-y-3">
                        @foreach($health['issues'] as $issue)
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                            {{ ucwords(str_replace('_', ' ', $issue['type'])) }}
                                        </h4>
                                        <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                            <p>Component: <strong>{{ $issue['component'] }}</strong></p>
                                            <p>Severity: <strong>{{ ucfirst($issue['severity']) }}</strong></p>
                                            @foreach($issue as $key => $value)
                                                @if(!in_array($key, ['type', 'component', 'severity']))
                                                    <p>{{ ucwords(str_replace('_', ' ', $key)) }}: <strong>{{ $value }}</strong></p>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Subscription Creation -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Subscription Creation</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Successes</span>
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $metrics['subscription_creation']['successes'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Errors</span>
                    <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ $metrics['subscription_creation']['errors'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Error Rate</span>
                    <span class="text-lg font-bold {{ $metrics['subscription_creation']['error_rate'] > 0.1 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format($metrics['subscription_creation']['error_rate'] * 100, 2) }}%
                    </span>
                </div>
            </div>
        </div>

        <!-- Webhook Processing -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Webhook Processing</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Payment Errors</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $metrics['webhook_processing']['payment_errors'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Recurring Errors</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $metrics['webhook_processing']['recurring_errors'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Pay Account Errors</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $metrics['webhook_processing']['pay_account_errors'] }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Failures -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Failures</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Count</span>
                    <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ $metrics['payment_failures']['count'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Error Rate</span>
                    <span class="text-lg font-bold {{ $metrics['payment_failures']['error_rate'] > 0.1 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format($metrics['payment_failures']['error_rate'] * 100, 2) }}%
                    </span>
                </div>
            </div>
        </div>

        <!-- API Performance -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">API Performance</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Timeouts</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $metrics['api_timeouts']['count'] }}</span>
                </div>
            </div>
        </div>

        <!-- Security -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Security</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Invalid Signatures</span>
                    <span class="text-lg font-bold {{ $metrics['security']['invalid_signatures'] > 5 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                        {{ $metrics['security']['invalid_signatures'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-refresh notice -->
    <div class="text-center text-sm text-gray-600 dark:text-gray-400">
        <p>Dashboard auto-refreshes every 30 seconds</p>
    </div>
</div>

@push('scripts')
<script>
    // Auto-refresh metrics every 30 seconds
    setInterval(() => {
        window.location.reload();
    }, 30000);
</script>
@endpush
@endsection
