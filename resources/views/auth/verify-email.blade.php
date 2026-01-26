@extends('layouts.app')

@section('title', __('auth.verify_email') . ' - QashierWise')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-8 md:py-12 px-4" x-data="verifyEmailForm()">
    <!-- Logo -->
    <div class="flex items-center gap-2 mb-6 md:mb-8">
        <img src="{{ asset('images/logo-64.png') }}" class="h-8 md:h-10 rounded-xl" alt="Logo" width="40" height="40" loading="eager">
        <span class="text-xl md:text-2xl font-bold text-[hsl(var(--primary))]">QashierWise</span>
    </div>

    <!-- Verification Card -->
    <div class="card w-full max-w-md p-5 md:p-8">
        <div class="mb-5 md:mb-6 text-center">
            <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-envelope text-2xl text-emerald-600"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-bold">{{ __('auth.verify_email') }}</h2>
            <p class="text-xs md:text-sm text-[hsl(var(--muted-foreground))] mt-2">
                {{ __('auth.verify_email_subtitle') }}
            </p>
            <p class="text-sm font-medium text-[hsl(var(--foreground))] mt-2" x-text="email"></p>
        </div>

        <!-- Success Alert -->
        <div x-show="successMessage" x-cloak x-transition class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="successMessage"></span>
        </div>

        <!-- Error Alert -->
        <div x-show="errorMessage" x-cloak x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="errorMessage"></span>
        </div>

        <form @submit.prevent="handleVerify" class="space-y-5">
            <div>
                <label for="otp" class="text-sm font-medium mb-2 block text-center">{{ __('auth.enter_verification_code') }}</label>
                <input
                    x-model="otp"
                    id="otp"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    required
                    class="input w-full text-center text-2xl tracking-widest font-mono touch-target-input"
                    :class="{'border-red-500': errors.otp}"
                    placeholder="000000"
                    @input="otp = otp.replace(/[^0-9]/g, '')"
                    autofocus>
                <p x-show="errors.otp" x-text="errors.otp" class="mt-1 text-sm text-red-600 text-center" x-cloak></p>
            </div>

            <button type="submit" :disabled="loading || otp.length !== 6" class="btn btn-primary w-full h-11 md:h-12 touch-target">
                <span x-show="!loading">{{ __('auth.verify_email_button') }}</span>
                <span x-show="loading" class="flex items-center justify-center">
                    <i class="fas fa-spinner animate-spin mr-2"></i> {{ __('auth.verifying') }}
                </span>
            </button>
        </form>

        <!-- Resend OTP -->
        <div class="mt-6 text-center">
            <p class="text-sm text-[hsl(var(--muted-foreground))]">
                {{ __('auth.didnt_receive_code') }}
            </p>
            <button
                @click="handleResend"
                :disabled="resendLoading || resendTimer > 0"
                class="mt-2 text-sm font-medium text-[hsl(var(--primary))] hover:underline disabled:opacity-50 disabled:cursor-not-allowed touch-target">
                <span x-show="!resendLoading && resendTimer === 0">{{ __('auth.resend_code') }}</span>
                <span x-show="resendLoading">
                    <i class="fas fa-spinner animate-spin mr-1"></i> {{ __('auth.sending') }}
                </span>
                <span x-show="!resendLoading && resendTimer > 0" x-text="'{{ __('auth.resend_in') }} ' + resendTimer + 's'"></span>
            </button>
        </div>
    </div>

    <!-- Back to Login -->
    <a href="/login" class="mt-5 md:mt-6 text-sm text-[hsl(var(--primary))] hover:underline flex items-center gap-2 touch-target">
        <i class="fas fa-arrow-left"></i>
        {{ __('auth.back_to_login') }}
    </a>
</div>

<script>
function verifyEmailForm() {
    return {
        email: localStorage.getItem('verification_email') || '',
        otp: '',
        loading: false,
        resendLoading: false,
        resendTimer: 0,
        successMessage: '',
        errorMessage: '',
        errors: {},
        token: localStorage.getItem('token') || '',

        init() {
            // If no email or token, redirect to register
            if (!this.email || !this.token) {
                window.location.href = '/register';
            }

            // Show success message if just registered
            if (localStorage.getItem('just_registered') === 'true') {
                this.successMessage = '{{ __("auth.verification_code_sent") }}';
                localStorage.removeItem('just_registered');
            }
        },

        async handleVerify() {
            this.loading = true;
            this.errorMessage = '';
            this.errors = {};

            try {
                const response = await fetch('/api/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this.token}`
                    },
                    body: JSON.stringify({
                        email: this.email,
                        code: this.otp,
                        type: 'email_verification'
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Update user in localStorage
                    if (data.data && data.data.user) {
                        localStorage.setItem('user', JSON.stringify(data.data.user));
                    }

                    // Clean up
                    localStorage.removeItem('verification_email');

                    // Show success and redirect
                    this.successMessage = '{{ __("auth.email_verified_success") }}';
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 1500);
                } else {
                    if (data.errors) {
                        this.errors = data.errors;
                    }
                    this.errorMessage = data.message || '{{ __("auth.verification_failed") }}';
                    this.otp = ''; // Clear OTP on error
                }
            } catch (e) {
                this.errorMessage = '{{ __("auth.network_error") }}';
                this.otp = '';
            } finally {
                this.loading = false;
            }
        },

        async handleResend() {
            this.resendLoading = true;
            this.errorMessage = '';
            this.successMessage = '';

            try {
                const response = await fetch('/api/resend-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: this.email,
                        type: 'email_verification'
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.successMessage = '{{ __("auth.verification_code_resent") }}';
                    this.startResendTimer();
                } else {
                    this.errorMessage = data.message || '{{ __("auth.resend_failed") }}';
                }
            } catch (e) {
                this.errorMessage = '{{ __("auth.network_error") }}';
            } finally {
                this.resendLoading = false;
            }
        },

        startResendTimer() {
            this.resendTimer = 60;
            const timer = setInterval(() => {
                this.resendTimer--;
                if (this.resendTimer <= 0) {
                    clearInterval(timer);
                }
            }, 1000);
        }
    }
}
</script>
@endsection
