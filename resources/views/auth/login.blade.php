@extends('layouts.app')

@section('title', __('auth.login') . ' - QashierWise')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-8 md:py-12 px-4" x-data="authForm()">
    <!-- Logo - Responsive sizing -->
    <div class="flex items-center gap-2 mb-6 md:mb-8">
        <img src="{{ asset('images/logo-64.png') }}" class="h-8 md:h-10 rounded-xl" alt="Logo" width="40" height="40" loading="eager">
        <span class="text-xl md:text-2xl font-bold text-[hsl(var(--primary))]">QashierWise</span>
    </div>

    <!-- Tab Switcher - Full width on mobile -->
    <div class="w-full max-w-md mb-4 md:mb-6 px-0">
        <div class="flex bg-[hsl(var(--muted))] rounded-lg p-1">
            <a href="/register" class="flex-1 py-2.5 md:py-2.5 text-center text-sm font-medium text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition rounded-md touch-target">
                {{ __('auth.register') }}
            </a>
            <button class="flex-1 py-2.5 md:py-2.5 text-center text-sm font-medium bg-white text-[hsl(var(--foreground))] shadow-sm rounded-md touch-target">
                {{ __('auth.login') }}
            </button>
        </div>
    </div>

    <!-- Login Card - Full width minus padding on mobile -->
    <div class="card w-full max-w-md p-5 md:p-8">
        <div class="mb-5 md:mb-6">
            <h2 class="text-xl md:text-2xl font-bold">{{ __('auth.welcome_back') }}</h2>
            <p class="text-xs md:text-sm text-[hsl(var(--primary))] mt-1">{{ __('auth.login_subtitle') }}</p>
        </div>

        <!-- Error Alert -->
        <div x-show="error" x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="error"></span>
        </div>

        <!-- Success Alert -->
        <div x-show="success" x-transition class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="success"></span>
        </div>

        <form @submit.prevent="login" class="space-y-4 md:space-y-5">
            <div>
                <label for="email" class="text-sm font-medium mb-1.5 block">{{ __('auth.email') }}</label>
                <input x-model="formData.email" id="email" type="email" required class="input w-full touch-target-input" placeholder="{{ __('auth.email_placeholder') }}">
            </div>

            <div>
                <label for="password" class="text-sm font-medium mb-1.5 block">{{ __('auth.password') }}</label>
                <div class="relative">
                    <input x-model="formData.password" id="password" :type="showPassword ? 'text' : 'password'" required class="input w-full pr-12 touch-target-input" placeholder="{{ __('auth.password_placeholder') }}">
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] touch-target">
                        <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                    </button>
                </div>
                <div class="mt-2 text-right">
                    <a href="/forgot-password" class="text-sm text-[hsl(var(--primary))] hover:underline touch-target">{{ __('auth.forgot_password') }}</a>
                </div>
            </div>

            <button type="submit" :disabled="loading" class="btn btn-primary w-full h-11 md:h-12 touch-target">
                <span x-show="!loading">{{ __('auth.sign_in') }}</span>
                <span x-show="loading" class="flex items-center justify-center">
                    <i class="fas fa-spinner animate-spin mr-2"></i> {{ __('auth.processing') }}
                </span>
            </button>
        </form>
    </div>

    <!-- Back to Home -->
    <a href="/" class="mt-5 md:mt-6 text-sm text-[hsl(var(--primary))] hover:underline flex items-center gap-2 touch-target">
        <i class="fas fa-arrow-left"></i>
        {{ __('auth.back_to_home') }}
    </a>
</div>

<script>
function authForm() {
    return {
        formData: { email: '', password: '' },
        showPassword: false,
        loading: false,
        error: '',
        success: '',

        getRedirectUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            const redirect = urlParams.get('redirect');
            const plan = urlParams.get('plan');
            
            // If redirect=pricing and plan is specified, go to welcome page pricing section
            // and trigger checkout after login
            if (redirect === 'pricing' && plan) {
                return `/?plan=${plan}&checkout=true#pricing`;
            }
            
            return '/dashboard';
        },

        async login() {
            this.loading = true;
            this.error = '';
            this.success = '';

            try {
                const response = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.formData)
                });

                const data = await response.json();

                if (data.success) {
                    localStorage.setItem('token', data.data.access_token);
                    if (data.data.user) localStorage.setItem('user', JSON.stringify(data.data.user));
                    this.success = '{{ __("auth.login_success") }}';
                    
                    const redirectUrl = this.getRedirectUrl();
                    setTimeout(() => window.location.href = redirectUrl, 1000);
                } else {
                    this.error = data.message || '{{ __("auth.login_failed") }}';
                }
            } catch (e) {
                this.error = '{{ __("auth.network_error") }}';
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection
