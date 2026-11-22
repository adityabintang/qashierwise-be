@extends('layouts.app')

@section('title', __('auth.reset_password') . ' - QashierWise')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-8 md:py-12 px-4" x-data="resetPasswordForm()">
    <!-- Logo - Responsive sizing -->
    <div class="flex items-center gap-2 mb-6 md:mb-8">
        <img src="{{ asset('images/logo-64.png') }}" class="h-8 md:h-10 rounded-xl" alt="Logo" width="40" height="40" loading="eager">
        <span class="text-xl md:text-2xl font-bold text-[hsl(var(--primary))]">QashierWise</span>
    </div>

    <!-- Loading State -->
    <div x-show="verifying" x-transition class="card w-full max-w-md p-5 md:p-8">
        <div class="flex flex-col items-center justify-center py-8">
            <i class="fas fa-spinner animate-spin text-2xl text-[hsl(var(--primary))] mb-4"></i>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ __('auth.processing') }}...</p>
        </div>
    </div>

    <!-- Expired Token State -->
    <div x-show="expired" x-transition x-cloak class="card w-full max-w-md p-5 md:p-8">
        <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mb-4">
                <i class="fas fa-clock text-2xl text-red-500"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-bold mb-2">{{ __('auth.reset_link_expired') }}</h2>
            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-6">{{ __('auth.reset_link_expired_description') }}</p>
            <a href="/forgot-password" class="btn btn-primary w-full h-11 md:h-12 touch-target flex items-center justify-center">
                <i class="fas fa-envelope mr-2"></i>
                {{ __('auth.request_new_link') }}
            </a>
        </div>
    </div>

    <!-- Reset Password Card -->
    <div x-show="!verifying && !expired" x-transition x-cloak class="card w-full max-w-md p-5 md:p-8">
        <div class="mb-5 md:mb-6">
            <h2 class="text-xl md:text-2xl font-bold">{{ __('auth.reset_password') }}</h2>
            <p class="text-xs md:text-sm text-[hsl(var(--primary))] mt-1">{{ __('auth.reset_password_subtitle') }}</p>
        </div>

        <!-- Error Alert -->
        <div x-show="error" x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="error"></span>
        </div>

        <!-- Success Alert -->
        <div x-show="success" x-transition class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="success"></span>
        </div>

        <form @submit.prevent="resetPassword" class="space-y-4 md:space-y-5">
            <div>
                <label for="password" class="text-sm font-medium mb-1.5 block">{{ __('auth.new_password') }}</label>
                <div class="relative">
                    <input x-model="formData.password" id="password" :type="showPassword ? 'text' : 'password'" required minlength="8" class="input w-full pr-12 touch-target-input" placeholder="{{ __('auth.password_placeholder') }}">
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] touch-target">
                        <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                    </button>
                </div>
            </div>

            <div>
                <label for="password_confirmation" class="text-sm font-medium mb-1.5 block">{{ __('auth.confirm_new_password') }}</label>
                <div class="relative">
                    <input x-model="formData.password_confirmation" id="password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" required minlength="8" class="input w-full pr-12 touch-target-input" placeholder="{{ __('auth.confirm_password_placeholder') }}">
                    <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] touch-target">
                        <i :class="showConfirmPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                    </button>
                </div>
            </div>

            <button type="submit" :disabled="loading" class="btn btn-primary w-full h-11 md:h-12 touch-target">
                <span x-show="!loading">{{ __('auth.reset_password_button') }}</span>
                <span x-show="loading" class="flex items-center justify-center">
                    <i class="fas fa-spinner animate-spin mr-2"></i> {{ __('auth.processing') }}
                </span>
            </button>
        </form>
    </div>

    <!-- Back to Login -->
    <a href="/login" class="mt-5 md:mt-6 text-sm text-[hsl(var(--primary))] hover:underline flex items-center gap-2 touch-target">
        <i class="fas fa-arrow-left"></i>
        {{ __('auth.back_to_login') }}
    </a>
</div>

<script>
function resetPasswordForm() {
    return {
        formData: { password: '', password_confirmation: '' },
        token: '',
        showPassword: false,
        showConfirmPassword: false,
        loading: false,
        verifying: true,
        expired: false,
        error: '',
        success: '',

        async init() {
            const urlParams = new URLSearchParams(window.location.search);
            this.token = urlParams.get('token');

            if (!this.token) {
                this.expired = true;
                this.verifying = false;
                return;
            }

            try {
                const response = await fetch('/api/verify-reset-token', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ token: this.token })
                });

                const data = await response.json();

                if (!data.success) {
                    this.expired = true;
                }
            } catch (e) {
                this.error = '{{ __("auth.network_error") }}';
            } finally {
                this.verifying = false;
            }
        },

        async resetPassword() {
            this.loading = true;
            this.error = '';
            this.success = '';

            try {
                const response = await fetch('/api/reset-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        ...this.formData,
                        token: this.token
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.success = data.message || '{{ __("auth.password_reset_success") }}';
                    setTimeout(() => window.location.href = '/login', 2000);
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
