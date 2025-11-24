@extends('layouts.app')

@section('title', 'Register - WhatsApp Business API')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-50 to-blue-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white rounded-2xl shadow-2xl p-8">
        <!-- Header -->
        <div>
            <div class="mx-auto h-16 w-16 bg-green-600 rounded-full flex items-center justify-center">
                <i class="fab fa-whatsapp text-white text-3xl"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Create your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="/login" class="font-medium text-green-600 hover:text-green-500 transition">
                    sign in to your account
                </a>
            </p>
        </div>

        <!-- Form -->
        <form class="mt-8 space-y-6" x-data="registerForm()" @submit.prevent="handleSubmit">
            <!-- Error Alert -->
            <div x-show="errorMessage" x-cloak class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline" x-text="errorMessage"></span>
            </div>

            <div class="rounded-md shadow-sm space-y-4">
                <!-- Name Input -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            x-model="formData.name"
                            class="appearance-none rounded-lg relative block w-full pl-10 pr-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            :class="{'border-red-500': errors.name}"
                            placeholder="Enter your full name">
                    </div>
                    <p x-show="errors.name" x-text="errors.name" class="mt-1 text-sm text-red-600" x-cloak></p>
                </div>

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            x-model="formData.email"
                            class="appearance-none rounded-lg relative block w-full pl-10 pr-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            :class="{'border-red-500': errors.email}"
                            placeholder="Enter your email">
                    </div>
                    <p x-show="errors.email" x-text="errors.email" class="mt-1 text-sm text-red-600" x-cloak></p>
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            x-model="formData.password"
                            class="appearance-none rounded-lg relative block w-full pl-10 pr-10 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            :class="{'border-red-500': errors.password}"
                            placeholder="Create a password">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" @click="showPassword = !showPassword" class="text-gray-400 hover:text-gray-600">
                                <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                            </button>
                        </div>
                    </div>
                    <p x-show="errors.password" x-text="errors.password" class="mt-1 text-sm text-red-600" x-cloak></p>
                </div>

                <!-- Password Confirmation Input -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            x-model="formData.password_confirmation"
                            class="appearance-none rounded-lg relative block w-full pl-10 pr-10 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                            placeholder="Confirm your password">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" @click="showPasswordConfirmation = !showPasswordConfirmation" class="text-gray-400 hover:text-gray-600">
                                <i :class="showPasswordConfirmation ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terms Checkbox -->
            <div class="flex items-center">
                <input
                    id="terms"
                    name="terms"
                    type="checkbox"
                    required
                    class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                <label for="terms" class="ml-2 block text-sm text-gray-900">
                    I agree to the <a href="#" class="text-green-600 hover:text-green-500">Terms and Conditions</a>
                </label>
            </div>

            <!-- Submit Button -->
            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="loading">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-user-plus text-green-500 group-hover:text-green-400" :class="{'fa-spin fa-spinner': loading}"></i>
                    </span>
                    <span x-text="loading ? 'Creating account...' : 'Create Account'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function registerForm() {
        return {
            formData: {
                name: '',
                email: '',
                password: '',
                password_confirmation: ''
            },
            showPassword: false,
            showPasswordConfirmation: false,
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
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.formData)
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Store token
                        localStorage.setItem('token', data.data.access_token);
                        localStorage.setItem('user', JSON.stringify(data.data.user));

                        // Redirect to dashboard
                        window.location.href = '/dashboard';
                    } else {
                        if (data.errors) {
                            this.errors = data.errors;
                            this.errorMessage = 'Please fix the validation errors.';
                        } else {
                            this.errorMessage = data.message || 'Registration failed. Please try again.';
                        }
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    this.errorMessage = 'An error occurred. Please check your connection and try again.';
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
@endsection
