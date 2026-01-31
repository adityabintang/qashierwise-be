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

        <!-- Success Alert -->
        <div x-show="successMessage" x-cloak x-transition class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="successMessage"></span>
        </div>

        <!-- Error Alert -->
        <div x-show="errorMessage" x-cloak x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="errorMessage"></span>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4 md:space-y-5">
            <div>
                <label for="name" class="text-sm font-medium mb-1.5 block">{{ __('auth.username') }}</label>
                <input x-model="formData.name" id="name" type="text" required class="input w-full touch-target-input" :class="{'border-red-500': errors.name}" placeholder="{{ __('auth.username_placeholder') }}">
                <p x-show="errors.name" x-text="errors.name" class="mt-1 text-sm text-red-600" x-cloak></p>
            </div>

            <div>
                <label for="email" class="text-sm font-medium mb-1.5 block">{{ __('auth.email') }}</label>
                <input x-model="formData.email" id="email" type="email" required class="input w-full touch-target-input" :class="{'border-red-500': errors.email}" placeholder="{{ __('auth.email_placeholder') }}">
                <p x-show="errors.email" x-text="errors.email" class="mt-1 text-sm text-red-600" x-cloak></p>
            </div>

            <div>
                <label for="store_name" class="text-sm font-medium mb-1.5 block">
                    {{ __('auth.store_name') }}
                </label>
                <input x-model="formData.store_name" id="store_name" type="text" required class="input w-full touch-target-input" :class="{'border-red-500': errors.store_name}" placeholder="{{ __('auth.store_name_placeholder') }}">
                <p x-show="errors.store_name" x-text="errors.store_name" class="mt-1 text-sm text-red-600" x-cloak></p>
                <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ __('auth.store_name_help') }}</p>
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

            <!-- Password Strength Indicator -->
            <div x-show="formData.password.length > 0" x-cloak class="space-y-1">
                <div class="flex gap-1">
                    <div class="h-1 flex-1 rounded" :class="{'bg-red-500': passwordStrength < 1, 'bg-yellow-500': passwordStrength === 1, 'bg-green-500': passwordStrength >= 2}"></div>
                    <div class="h-1 flex-1 rounded" :class="{'bg-gray-200': passwordStrength < 2, 'bg-yellow-500': passwordStrength === 2, 'bg-green-500': passwordStrength >= 3}"></div>
                    <div class="h-1 flex-1 rounded" :class="{'bg-gray-200': passwordStrength < 3, 'bg-green-500': passwordStrength >= 3}"></div>
                </div>
                <p class="text-xs" :class="passwordStrengthClass" x-text="passwordStrengthText"></p>
            </div>

            <button type="submit" :disabled="loading || passwordStrength < 2" class="btn btn-primary w-full h-11 md:h-12 touch-target">
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
        formData: {
            name: '',
            email: '',
            store_name: '',
            password: '',
            password_confirmation: ''
        },
        showPassword: false,
        showPasswordConfirm: false,
        loading: false,
        successMessage: '',
        errorMessage: '',
        errors: {},

        get passwordStrength() {
            let strength = 0;
            if (this.formData.password.length >= 8) strength++;
            if (/[A-Z]/.test(this.formData.password)) strength++;
            if (/[0-9]/.test(this.formData.password)) strength++;
            if (/[^A-Za-z0-9]/.test(this.formData.password)) strength++;
            return strength;
        },

        get passwordStrengthClass() {
            if (this.passwordStrength < 2) return 'text-red-600';
            if (this.passwordStrength < 3) return 'text-yellow-600';
            return 'text-green-600';
        },

        get passwordStrengthText() {
            if (this.passwordStrength < 2) return '{{ __("auth.password_weak") }}';
            if (this.passwordStrength < 3) return '{{ __("auth.password_medium") }}';
            return '{{ __("auth.password_strong") }}';
        },

        async handleSubmit() {
            this.loading = true;
            this.errorMessage = '';
            this.errors = {};
            this.successMessage = '';

            try {
                const response = await fetch('/api/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.formData)
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Store token and user data
                    localStorage.setItem('token', data.data.access_token);
                    localStorage.setItem('user', JSON.stringify(data.data.user));
                    localStorage.setItem('verification_email', data.data.user.email);
                    localStorage.setItem('just_registered', 'true');

                    // Show success message with store info if created
                    if (data.data.store) {
                        this.successMessage = '{{ __("auth.register_success_with_store") }}'.replace(':store', data.data.store.name);
                    } else {
                        this.successMessage = '{{ __("auth.register_success") }}';
                    }

                    // Redirect after short delay
                    setTimeout(() => {
                        window.location.href = '/verify-email';
                    }, 2000);
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
                console.error('Registration error:', e);
                this.errorMessage = '{{ __("auth.network_error") }}';
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection
