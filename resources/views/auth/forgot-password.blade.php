@extends('layouts.app')

@section('title', __('auth.forgot_password') . ' - QashierWise')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-8 md:py-12 px-4" x-data="forgotPasswordForm()">
    <!-- Logo - Responsive sizing -->
    <div class="flex items-center gap-2 mb-6 md:mb-8">
        <img src="{{ asset('images/logo-64.png') }}" class="h-8 md:h-10 rounded-xl" alt="Logo" width="40" height="40" loading="eager">
        <span class="text-xl md:text-2xl font-bold text-[hsl(var(--primary))]">QashierWise</span>
    </div>

    <!-- Forgot Password Card -->
    <div class="card w-full max-w-md p-5 md:p-8">
        <div class="mb-5 md:mb-6">
            <h2 class="text-xl md:text-2xl font-bold">{{ __('auth.forgot_password') }}</h2>
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

        <form @submit.prevent="forgotPassword" class="space-y-4 md:space-y-5">
            <div>
                <label for="email" class="text-sm font-medium mb-1.5 block">{{ __('auth.email') }}</label>
                <input x-model="formData.email" id="email" type="email" required class="input w-full touch-target-input" placeholder="{{ __('auth.email_placeholder') }}">
            </div>

            <button type="submit" :disabled="loading" class="btn btn-primary w-full h-11 md:h-12 touch-target">
                <span x-show="!loading">{{ __('auth.send_reset_link') }}</span>
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
function forgotPasswordForm() {
    return {
        formData: { email: '' },
        loading: false,
        error: '',
        success: '',

        async forgotPassword() {
            this.loading = true;
            this.error = '';
            this.success = '';

            try {
                const response = await fetch('/api/forgot-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.formData)
                });

                const data = await response.json();

                if (data.success) {
                    this.success = data.message || '{{ __("auth.reset_link_sent") }}';
                    this.formData.email = '';
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
