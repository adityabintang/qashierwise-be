<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - QashierWise</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6214ed',
                        dark: '#0f172a',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-white min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8" x-data="authForm()">
        <!-- Logo -->
        <div class="flex items-center space-x-2 mb-8">
            <img src="{{ asset('images/logo.png') }}" class="h-10 rounded-xl" alt="Logo">
            <span class="text-2xl font-bold text-primary">QashierWise</span>
        </div>

        <!-- Tab Switcher -->
        <div class="w-full max-w-md mb-6">
            <div class="flex bg-gray-100 rounded-lg p-1">
                <a href="/register" class="flex-1 py-2.5 text-center text-sm font-medium text-gray-500 hover:text-gray-700 transition rounded-md">
                    Daftar
                </a>
                <button class="flex-1 py-2.5 text-center text-sm font-medium bg-white text-gray-900 shadow-sm rounded-md">
                    Masuk
                </button>
            </div>
        </div>

        <!-- Login Card -->
        <div class="w-full max-w-md bg-white border border-gray-200 rounded-xl shadow-sm p-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Selamat Datang Kembali</h2>
                <p class="text-sm text-primary mt-1">Masuk ke akun QashierWise Anda</p>
            </div>

            <!-- Error Alert -->
            <div x-show="error" x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm" role="alert">
                <span x-text="error"></span>
            </div>

            <!-- Success Alert -->
            <div x-show="success" x-transition class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm" role="alert">
                <span x-text="success"></span>
            </div>

            <form @submit.prevent="login" class="space-y-5">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input x-model="formData.email" id="email" name="email" type="email" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                        placeholder="nama@email.com">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative">
                        <input x-model="formData.password" id="password" name="password" :type="showPassword ? 'text' : 'password'" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition pr-12"
                            placeholder="Masukkan password">
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                            <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" :disabled="loading"
                    class="w-full py-3.5 px-4 bg-dark text-white font-medium rounded-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-dark disabled:opacity-50 disabled:cursor-not-allowed transition">
                    <span x-show="!loading">Masuk</span>
                    <span x-show="loading" class="flex items-center justify-center">
                        <i class="fas fa-spinner fa-spin mr-2"></i> Memproses...
                    </span>
                </button>
            </form>
        </div>

        <!-- Back to Home -->
        <a href="/" class="mt-6 text-sm text-primary hover:text-primary/80 transition flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Kembali ke beranda
        </a>
    </div>

    <script>
        function authForm() {
            return {
                formData: {
                    email: '',
                    password: '',
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
                            localStorage.setItem('token', data.data.access_token);
                            if (data.data.user) {
                                localStorage.setItem('user', JSON.stringify(data.data.user));
                            }
                            this.success = 'Login berhasil! Mengalihkan...';
                            setTimeout(() => {
                                window.location.href = '/dashboard';
                            }, 1000);
                        } else {
                            this.error = data.message || 'Login gagal. Periksa kredensial Anda.';
                        }
                    } catch (error) {
                        this.error = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
                        console.error('Login error:', error);
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
