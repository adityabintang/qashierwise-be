@extends('layouts.app')

@section('title', 'Login - QashierWise')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-50 to-green-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-600 rounded-full mb-4">
                <i class="fab fa-whatsapp text-4xl text-white"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900">QashierWise</h2>
            <p class="mt-2 text-sm text-gray-600">WhatsApp Business Account Manager</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl px-8 py-10" x-data="loginForm()">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Sign in to your account</h3>

            <div x-show="error" x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline" x-text="error"></span>
            </div>

            <div x-show="success" x-transition class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline" x-text="success"></span>
            </div>

            <form @submit.prevent="login" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input x-model="formData.email" id="email" name="email" type="email" required
                               class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500"
                               placeholder="you@example.com">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input x-model="formData.password" id="password" name="password" :type="showPassword ? 'text' : 'password'" required
                               class="appearance-none block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500"
                               placeholder="••••••••">
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas text-gray-400"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input x-model="formData.remember" id="remember" type="checkbox"
                               class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
                    </div>
                </div>

                <div>
                    <button type="submit" :disabled="loading"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        <span x-show="!loading">Sign in</span>
                        <span x-show="loading" class="flex items-center">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Signing in...
                        </span>
                    </button>
                </div>
            </form>

            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Don't have an account?</span>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="/register" class="w-full flex justify-center py-3 px-4 border-2 border-green-600 rounded-lg shadow-sm text-sm font-medium text-green-600 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                        Create new account
                    </a>
                </div>
            </div>
        </div>

        <p class="mt-8 text-center text-sm text-gray-600">
            © 2025 QashierWise. All rights reserved.
        </p>
    </div>
</div>

<script>
    function loginForm() {
        return {
            formData: {
                email: '',
                password: '',
                remember: false
            },
            showPassword: false,
            loading: false,
            error: '',
            success: '',

            async login() {
                this.loading = true;
                this.error = '';
                this.success = '';

                try {
                    const response = await fetch('{{ config('app.url') }}/api/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(this.formData)
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Save token
                        localStorage.setItem('token', data.data.access_token);

                        // Save user object
                        if (data.data.user) {
                            localStorage.setItem('user', JSON.stringify(data.data.user));
                        }

                        this.success = 'Login successful! Redirecting...';
                        setTimeout(() => {
                            window.location.href = '/dashboard';
                        }, 1000);
                    } else {
                        this.error = data.message || 'Login failed. Please check your credentials.';
                    }
                } catch (error) {
                    this.error = 'Network error. Please try again.';
                    console.error('Login error:', error);
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
@endsection
