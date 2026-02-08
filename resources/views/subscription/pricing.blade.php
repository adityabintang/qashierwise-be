@extends('layouts.app')

@section('title', 'Pricing Plans - QashierWise')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-purple-50 via-white to-blue-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                Choose Your Plan
            </h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Select the perfect plan for your business needs. All plans include 14-day free trial.
            </p>
        </div>

        <!-- Pricing Cards -->
        <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto" x-data="pricingPage()">
            <!-- Standard Plan -->
            <div class="bg-white rounded-2xl shadow-lg p-8 border-2 border-gray-200 hover:border-blue-500 transition-all">
                <div class="flex items-center justify-between mb-6">
                    <div class="h-12 w-12 rounded-xl bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-star text-2xl text-blue-600"></i>
                    </div>
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                        POPULAR
                    </span>
                </div>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Standard</h3>
                <div class="mb-6">
                    <span class="text-4xl font-bold text-gray-900">Rp 350,000</span>
                    <span class="text-gray-600">/month</span>
                </div>

                <!-- Duration Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Durasi</label>
                    <select x-model="standardDuration" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="1_month">1 Bulan - Rp 350,000</option>
                        <option value="3_months">3 Bulan - Rp 1,050,000</option>
                        <option value="1_year">1 Tahun - Rp 3,780,000 (Hemat 10%)</option>
                    </select>
                </div>

                <ul class="space-y-4 mb-8">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <span class="text-gray-700">Manajemen kontak unlimited</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <span class="text-gray-700">Template pesan kustom</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <span class="text-gray-700">Laporan analitik dasar</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <span class="text-gray-700">Hingga 2 outlet</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <span class="text-gray-700">QRIS unlimited</span>
                    </li>
                </ul>

                <form action="{{ route('subscription.checkout') }}" method="POST">
                    @csrf
                    <input type="hidden" name="plan_id" value="standard">
                    <input type="hidden" name="duration" :value="standardDuration">
                    <button type="submit" 
                            :disabled="loading"
                            class="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!loading">Get Started</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <i class="fas fa-spinner fa-spin"></i>
                            Processing...
                        </span>
                    </button>
                </form>
            </div>

            <!-- Pro Plan -->
            <div class="bg-gradient-to-br from-purple-600 to-indigo-600 rounded-2xl shadow-xl p-8 text-white transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between mb-6">
                    <div class="h-12 w-12 rounded-xl bg-white/20 flex items-center justify-center">
                        <i class="fas fa-crown text-2xl text-white"></i>
                    </div>
                    <span class="px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full">
                        BEST VALUE
                    </span>
                </div>
                
                <h3 class="text-2xl font-bold mb-2">Pro</h3>
                <div class="mb-6">
                    <span class="text-4xl font-bold">Rp 500,000</span>
                    <span class="text-white/80">/month</span>
                </div>

                <!-- Duration Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-white/90 mb-2">Pilih Durasi</label>
                    <select x-model="proDuration" class="w-full px-4 py-3 border border-white/20 rounded-lg bg-white/10 text-white focus:ring-2 focus:ring-white/50 focus:border-transparent">
                        <option value="1_month" class="text-gray-900">1 Bulan - Rp 500,000</option>
                        <option value="3_months" class="text-gray-900">3 Bulan - Rp 1,500,000</option>
                        <option value="1_year" class="text-gray-900">1 Tahun - Rp 5,400,000 (Hemat 10%)</option>
                    </select>
                </div>

                <ul class="space-y-4 mb-8">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-white mt-1"></i>
                        <span class="text-white/90">Semua fitur Standard</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-white mt-1"></i>
                        <span class="text-white/90">API akses penuh</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-white mt-1"></i>
                        <span class="text-white/90">Laporan analitik lanjutan</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-white mt-1"></i>
                        <span class="text-white/90">Dukungan prioritas</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-white mt-1"></i>
                        <span class="text-white/90">Unlimited outlet</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-white mt-1"></i>
                        <span class="text-white/90">Custom domain</span>
                    </li>
                </ul>

                <form action="{{ route('subscription.checkout') }}" method="POST">
                    @csrf
                    <input type="hidden" name="plan_id" value="pro">
                    <input type="hidden" name="duration" :value="proDuration">
                    <button type="submit"
                            :disabled="loading"
                            class="w-full py-3 bg-white text-purple-600 rounded-xl font-semibold hover:bg-gray-100 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!loading">Get Started</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <i class="fas fa-spinner fa-spin"></i>
                            Processing...
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Features Comparison -->
        <div class="mt-16 max-w-5xl mx-auto">
            <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">Compare Plans</h2>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Feature</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Standard</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Pro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">Messages per month</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700">1,000</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">WhatsApp Business API</td>
                            <td class="px-6 py-4 text-center"><i class="fas fa-check text-green-500"></i></td>
                            <td class="px-6 py-4 text-center"><i class="fas fa-check text-green-500"></i></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">AI Agent</td>
                            <td class="px-6 py-4 text-center"><i class="fas fa-check text-green-500"></i></td>
                            <td class="px-6 py-4 text-center"><i class="fas fa-check text-green-500"></i></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">Analytics</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700">Basic</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700">Advanced</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">Support</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700">Email</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700">Priority</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">Custom Integrations</td>
                            <td class="px-6 py-4 text-center"><i class="fas fa-times text-gray-400"></i></td>
                            <td class="px-6 py-4 text-center"><i class="fas fa-check text-green-500"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="mt-16 max-w-3xl mx-auto">
            <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">Frequently Asked Questions</h2>
            <div class="space-y-4">
                <div class="bg-white rounded-xl shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">Can I cancel anytime?</h3>
                    <p class="text-gray-600">Yes, you can cancel your subscription at any time. Your access will continue until the end of your billing period.</p>
                </div>
                <div class="bg-white rounded-xl shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">What payment methods do you accept?</h3>
                    <p class="text-gray-600">We accept credit cards, bank transfers, e-wallets, and other payment methods through Midtrans.</p>
                </div>
                <div class="bg-white rounded-xl shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">Is there a free trial?</h3>
                    <p class="text-gray-600">Yes! All new users get a 14-day free trial with full access to all features.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function pricingPage() {
        return {
            loading: false,
            standardDuration: '1_month',
            proDuration: '1_month'
        }
    }
</script>
@endpush
@endsection
