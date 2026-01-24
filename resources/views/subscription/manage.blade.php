@extends('layouts.app')

@section('title', 'Manage Subscription - QashierWise')

@section('content')
<div x-data="manageSubscription()" x-init="init()" class="min-h-screen flex bg-[hsl(var(--muted)/0.4)]">
    @include('components.dashboard-sidebar', ['activePage' => 'subscription'])

    <div class="flex-1 flex flex-col min-h-screen">
        @include('components.dashboard-header', ['title' => 'Manage Subscription', 'description' => 'View and manage your subscription plan'])

        <main class="flex-1 p-4 md:p-6">
            <div class="max-w-4xl mx-auto space-y-6">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <p class="text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-circle text-red-600 mt-1"></i>
                            <p class="text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                @if(session('info'))
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                            <p class="text-blue-800">{{ session('info') }}</p>
                        </div>
                    </div>
                @endif

                <!-- Loading State -->
                <div x-show="loading" class="space-y-6">
                    <div class="card p-6">
                        <div class="animate-pulse space-y-4">
                            <div class="h-6 bg-gray-200 rounded w-1/4"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                            <div class="h-32 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </div>

                <!-- No Subscription State -->
                @if($subscription === null)
                <div x-show="!loading" class="card">
                    <div class="card-content text-center py-12">
                        <div class="h-20 w-20 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-rocket text-4xl text-purple-600"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">No Active Subscription</h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            You don't have an active subscription yet. Choose a plan to unlock all premium features and grow your business.
                        </p>
                        <a href="{{ route('subscription.pricing') }}" 
                           class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all">
                            <i class="fas fa-star"></i>
                            View Plans & Pricing
                        </a>
                    </div>
                </div>
                @else
                <!-- Subscription Details Card -->
                <div x-show="!loading" class="card">
                    <div class="card-header">
                        <h2 class="card-title">Current Plan</h2>
                        <p class="card-description">Your subscription details and billing information</p>
                    </div>
                    <div class="card-content">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 p-6 bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 rounded-xl flex items-center justify-center"
                                     :class="{
                                         'bg-gray-100': subscription.plan_name === 'free_trial',
                                         'bg-blue-100': subscription.plan_name === 'standard',
                                         'bg-purple-100': subscription.plan_name === 'pro'
                                     }">
                                    <i class="fas text-3xl"
                                       :class="{
                                           'fa-gift text-gray-600': subscription.plan_name === 'free_trial',
                                           'fa-star text-blue-600': subscription.plan_name === 'standard',
                                           'fa-crown text-purple-600': subscription.plan_name === 'pro'
                                       }"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold capitalize" x-text="getPlanDisplayName()">Loading...</h3>
                                    <p class="text-sm text-gray-600" x-text="getStatusText()">Active</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-gray-900" x-show="subscription.plan_name !== 'free_trial'">
                                    <span x-text="formatPrice(subscription.amount)">Rp 0</span>
                                </p>
                                <p class="text-sm text-gray-600" x-show="subscription.plan_name !== 'free_trial'">per month</p>
                                <p class="text-lg font-semibold text-gray-700" x-show="subscription.plan_name === 'free_trial'">
                                    Free Trial
                                </p>
                            </div>
                        </div>

                        <!-- Subscription Info -->
                        <div class="mt-6 grid md:grid-cols-2 gap-4">
                            <!-- Status -->
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">Status</p>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full"
                                          :class="{
                                              'bg-green-500': subscription.status === 'active',
                                              'bg-yellow-500': subscription.status === 'trial',
                                              'bg-orange-500': subscription.status === 'cancelled',
                                              'bg-red-500': subscription.status === 'expired' || subscription.status === 'trial_expired'
                                          }"></span>
                                    <span class="font-semibold capitalize" x-text="subscription.status">-</span>
                                </div>
                            </div>

                            <!-- Current Period -->
                            <div class="p-4 bg-gray-50 rounded-lg" x-show="subscription.period_start">
                                <p class="text-sm text-gray-600 mb-1">Current Period</p>
                                <p class="font-semibold">
                                    <span x-text="formatDate(subscription.period_start)">-</span>
                                    <span class="text-gray-500">to</span>
                                    <span x-text="formatDate(subscription.period_end)">-</span>
                                </p>
                            </div>

                            <!-- Next Billing Date -->
                            <div class="p-4 bg-gray-50 rounded-lg" x-show="subscription.status === 'active' && subscription.period_end">
                                <p class="text-sm text-gray-600 mb-1">Next Billing Date</p>
                                <p class="font-semibold" x-text="formatDate(subscription.period_end)">-</p>
                            </div>

                            <!-- Trial Days Remaining -->
                            <div class="p-4 bg-amber-50 rounded-lg" x-show="subscription.status === 'trial'">
                                <p class="text-sm text-amber-700 mb-1">Trial Days Remaining</p>
                                <p class="font-semibold text-amber-900">
                                    <span x-text="subscription.trial_days_remaining || 0">0</span> days
                                </p>
                            </div>

                            <!-- Cancelled At -->
                            <div class="p-4 bg-orange-50 rounded-lg" x-show="subscription.cancelled_at">
                                <p class="text-sm text-orange-700 mb-1">Cancelled On</p>
                                <p class="font-semibold text-orange-900" x-text="formatDate(subscription.cancelled_at)">-</p>
                            </div>
                        </div>

                        <!-- Alerts -->
                        <div class="mt-6 space-y-3">
                            <!-- Trial Expiring Soon -->
                            <div x-show="subscription.status === 'trial' && subscription.trial_days_remaining <= 3" 
                                 class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-exclamation-triangle text-amber-600 mt-1"></i>
                                    <div>
                                        <p class="font-semibold text-amber-900">Trial Ending Soon</p>
                                        <p class="text-sm text-amber-700 mt-1">
                                            Your trial will expire in <span x-text="subscription.trial_days_remaining">0</span> days. 
                                            Upgrade now to continue using all features.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Subscription Cancelled -->
                            <div x-show="subscription.status === 'cancelled'" 
                                 class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-info-circle text-orange-600 mt-1"></i>
                                    <div>
                                        <p class="font-semibold text-orange-900">Subscription Cancelled</p>
                                        <p class="text-sm text-orange-700 mt-1">
                                            Your subscription has been cancelled. You'll have access until 
                                            <span x-text="formatDate(subscription.period_end)">-</span>.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Trial Expired -->
                            <div x-show="subscription.status === 'trial_expired'" 
                                 class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-times-circle text-red-600 mt-1"></i>
                                    <div>
                                        <p class="font-semibold text-red-900">Trial Expired</p>
                                        <p class="text-sm text-red-700 mt-1">
                                            Your trial has expired. Please upgrade to a paid plan to continue using QashierWise.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Actions Card -->
                <div x-show="!loading" class="card">
                    <div class="card-header">
                        <h2 class="card-title">Actions</h2>
                        <p class="card-description">Manage your subscription</p>
                    </div>
                    <div class="card-content space-y-3">
                        <!-- Upgrade Button (for trial/expired users) -->
                        <a x-show="subscription.status === 'trial' || subscription.status === 'trial_expired' || subscription.status === 'expired'"
                           href="{{ route('subscription.pricing') }}"
                           class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-rocket text-2xl"></i>
                                <div>
                                    <p class="font-semibold">Upgrade to Premium</p>
                                    <p class="text-sm text-white/80">Get access to all features</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right"></i>
                        </a>

                        <!-- View Pricing -->
                        <a href="{{ route('subscription.pricing') }}"
                           class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-tags text-xl text-gray-600"></i>
                                <div>
                                    <p class="font-semibold text-gray-900">View All Plans</p>
                                    <p class="text-sm text-gray-600">Compare features and pricing</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-gray-400"></i>
                        </a>

                        <!-- Cancel Subscription (for active subscriptions) -->
                        <button x-show="subscription.status === 'active' && subscription.plan_name !== 'free_trial'"
                                @click="showCancelModal = true"
                                class="flex items-center justify-between w-full p-4 bg-red-50 hover:bg-red-100 rounded-xl transition-colors text-left">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-times-circle text-xl text-red-600"></i>
                                <div>
                                    <p class="font-semibold text-red-900">Cancel Subscription</p>
                                    <p class="text-sm text-red-700">End your subscription</p>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-red-400"></i>
                        </button>
                    </div>
                </div>

                <!-- Billing History Card -->
                <div x-show="!loading && subscription.plan_name !== 'free_trial'" class="card">
                    <div class="card-header">
                        <h2 class="card-title">Billing History</h2>
                        <p class="card-description">Your payment history</p>
                    </div>
                    <div class="card-content">
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-receipt text-4xl mb-3"></i>
                            <p>No billing history available yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div x-show="showCancelModal" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showCancelModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" @click="showCancelModal = false"></div>
            
            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                <div class="text-center mb-6">
                    <div class="h-16 w-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Cancel Subscription?</h3>
                    <p class="text-gray-600">
                        Are you sure you want to cancel your subscription? You'll lose access to all premium features 
                        at the end of your billing period.
                    </p>
                </div>

                <div class="space-y-3">
                    <form action="{{ route('subscription.cancel.post') }}" method="POST">
                        @csrf
                        <button type="submit"
                                :disabled="cancelLoading"
                                class="w-full py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition-all disabled:opacity-50">
                            <span x-show="!cancelLoading">Yes, Cancel Subscription</span>
                            <span x-show="cancelLoading" class="flex items-center justify-center gap-2">
                                <i class="fas fa-spinner fa-spin"></i>
                                Cancelling...
                            </span>
                        </button>
                    </form>
                    <button @click="showCancelModal = false"
                            class="w-full py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all">
                        Keep Subscription
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function manageSubscription() {
        return {
            loading: true,
            subscription: {},
            showCancelModal: false,
            cancelLoading: false,

            async init() {
                await this.fetchSubscription();
            },

            async fetchSubscription() {
                try {
                    const response = await fetch('/api/subscription/status', {
                        headers: {
                            'Authorization': `Bearer ${localStorage.getItem('token')}`,
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        this.subscription = data.subscription || {};
                    }
                } catch (error) {
                    console.error('Failed to fetch subscription:', error);
                } finally {
                    this.loading = false;
                }
            },

            getPlanDisplayName() {
                const names = {
                    'free_trial': 'Free Trial',
                    'standard': 'Standard Plan',
                    'pro': 'Pro Plan'
                };
                return names[this.subscription.plan_name] || 'Unknown Plan';
            },

            getStatusText() {
                const statuses = {
                    'active': 'Active',
                    'trial': 'Trial Period',
                    'cancelled': 'Cancelled',
                    'expired': 'Expired',
                    'trial_expired': 'Trial Expired'
                };
                return statuses[this.subscription.status] || 'Unknown';
            },

            formatDate(dateString) {
                if (!dateString) return '-';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
            },

            formatPrice(amount) {
                if (!amount) return 'Rp 0';
                return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
            }
        }
    }
</script>
@endpush
@endsection
