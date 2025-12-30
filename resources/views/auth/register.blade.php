@extends('layouts.app')

@section('title', __('auth.register') . ' - QashierWise')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-8 md:py-12 px-4 overflow-y-auto" x-data="registerForm()">
    <!-- Logo - Responsive sizing -->
    <div class="flex items-center gap-2 mb-6 md:mb-8 flex-shrink-0">
        <img src="{{ asset('images/logo-64.png') }}" class="h-8 md:h-10 rounded-xl" alt="Logo" width="40" height="40" loading="eager">
        <span class="text-xl md:text-2xl font-bold text-[hsl(var(--primary))]">QashierWise</span>
    </div>

    <!-- Tab Switcher - Full width on mobile -->
    <div class="w-full max-w-md mb-4 md:mb-6 px-0 flex-shrink-0">
        <div class="flex bg-[hsl(var(--muted))] rounded-lg p-1">
            <button class="flex-1 py-2.5 md:py-2.5 text-center text-sm font-medium bg-white text-[hsl(var(--foreground))] shadow-sm rounded-md touch-target">
                {{ __('auth.register') }}
            </button>
            <a href="/login" class="flex-1 py-2.5 md:py-2.5 text-center text-sm font-medium text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition rounded-md touch-target">
                {{ __('auth.login') }}
            </a>
        </div>
    </div>

    <!-- Register Card - Full width minus padding on mobile, scrollable when keyboard is open -->
    <div class="card w-full max-w-md p-5 md:p-8 flex-shrink-0">
        <div class="mb-5 md:mb-6">
            <h2 class="text-xl md:text-2xl font-bold">{{ __('auth.free_trial') }}</h2>
            <p class="text-xs md:text-sm text-[hsl(var(--muted-foreground))] mt-1">{{ __('auth.register_subtitle') }}</p>
        </div>

        <!-- Error Alert -->
        <div x-show="errorMessage" x-cloak x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="errorMessage"></span>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4 md:space-y-5">
            <div>
                <label for="name" class="text-sm font-medium mb-1.5 block">{{ __('auth.business_name') }}</label>
                <input x-model="formData.name" id="name" type="text" required class="input w-full touch-target-input" :class="{'border-red-500': errors.name}" placeholder="{{ __('auth.business_name_placeholder') }}">
                <p x-show="errors.name" x-text="errors.name" class="mt-1 text-sm text-red-600" x-cloak></p>
            </div>

            <div>
                <label for="email" class="text-sm font-medium mb-1.5 block">{{ __('auth.email') }}</label>
                <input x-model="formData.email" id="email" type="email" required class="input w-full touch-target-input" :class="{'border-red-500': errors.email}" placeholder="{{ __('auth.email_placeholder') }}">
                <p x-show="errors.email" x-text="errors.email" class="mt-1 text-sm text-red-600" x-cloak></p>
            </div>

            <div>
                <label for="password" class="text-sm font-medium mb-1.5 block">{{ __('auth.password') }}</label>
                <div class="relative">
                    <input x-model="formData.password" id="password" :type="showPassword ? 'text' : 'password'" required class="input w-full pr-12 touch-target-input" :class="{'border-red-500': errors.password}" placeholder="{{ __('auth.password_min') }}">
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] touch-target">
                        <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                    </button>
                </div>
                <p x-show="errors.password" x-text="errors.password" class="mt-1 text-sm text-red-600" x-cloak></p>
            </div>

            <div>
                <label for="password_confirmation" class="text-sm font-medium mb-1.5 block">{{ __('auth.confirm_password') }}</label>
                <div class="relative">
                    <input x-model="formData.password_confirmation" id="password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" required class="input w-full pr-12 touch-target-input" placeholder="{{ __('auth.confirm_password_placeholder') }}">
                    <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] touch-target">
                        <i :class="showPasswordConfirm ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                    </button>
                </div>
            </div>

            <button type="submit" :disabled="loading" class="btn btn-primary w-full h-11 md:h-12 touch-target">
                <span x-show="!loading">{{ __('auth.register_now') }}</span>
                <span x-show="loading" class="flex items-center justify-center">
                    <i class="fas fa-spinner animate-spin mr-2"></i> {{ __('auth.processing') }}
                </span>
            </button>
        </form>
    </div>

    <!-- Back to Home -->
    <a href="/" class="mt-5 md:mt-6 text-sm text-[hsl(var(--primary))] hover:underline flex items-center gap-2 touch-target flex-shrink-0">
        <i class="fas fa-arrow-left"></i>
        {{ __('auth.back_to_home') }}
    </a>
</div>

<script>
function registerForm() {
    return {
        formData: { name: '', email: '', password: '', password_confirmation: '' },
        showPassword: false,
        showPasswordConfirm: false,
        loading: false,
        errorMessage: '',
        errors: {},

        async handleSubmit() {
            this.loading = true;
            this.errorMessage = '';
            this.errors = {};

            try {
                const response = await fetch('/api/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.formData)
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    localStorage.setItem('token', data.data.access_token);
                    localStorage.setItem('user', JSON.stringify(data.data.user));
                    window.location.href = '/dashboard';
                } else {
                    if (data.errors) {
                        this.errors = data.errors;
                        this.errorMessage = '{{ __("auth.validation_error") }}';
                    } else {
                        // Handle both error formats: {message: ...} and {error_code: ..., message: ...}
                        this.errorMessage = data.message || data.error_code || '{{ __("auth.register_failed") }}';
                    }
                }
            } catch (e) {
                this.errorMessage = '{{ __("auth.network_error") }}';
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection
