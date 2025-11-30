@extends('layouts.app')

@section('title', 'Masuk - QashierWise')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-12 px-4" x-data="authForm()">
    <!-- Logo -->
    <div class="flex items-center gap-2 mb-8">
        <img src="{{ asset('images/logo.png') }}" class="h-10 rounded-xl" alt="Logo">
        <span class="text-2xl font-bold text-[hsl(var(--primary))]">QashierWise</span>
    </div>

    <!-- Tab Switcher -->
    <div class="w-full max-w-md mb-6">
        <div class="flex bg-[hsl(var(--muted))] rounded-lg p-1">
            <a href="/register" class="flex-1 py-2.5 text-center text-sm font-medium text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition rounded-md">
                Daftar
            </a>
            <button class="flex-1 py-2.5 text-center text-sm font-medium bg-white text-[hsl(var(--foreground))] shadow-sm rounded-md">
                Masuk
            </button>
        </div>
    </div>

    <!-- Login Card -->
    <div class="card w-full max-w-md p-8">
        <div class="mb-6">
            <h2 class="text-2xl font-bold">Selamat Datang Kembali</h2>
            <p class="text-sm text-[hsl(var(--primary))] mt-1">Masuk ke akun QashierWise Anda</p>
        </div>

        <!-- Error Alert -->
        <div x-show="error" x-transition class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="error"></span>
        </div>

        <!-- Success Alert -->
        <div x-show="success" x-transition class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
            <span x-text="success"></span>
        </div>

        <form @submit.prevent="login" class="space-y-5">
            <div>
                <label for="email" class="text-sm font-medium mb-1.5 block">Email</label>
                <input x-model="formData.email" id="email" type="email" required class="input w-full" placeholder="nama@email.com">
            </div>

            <div>
                <label for="password" class="text-sm font-medium mb-1.5 block">Password</label>
                <div class="relative">
                    <input x-model="formData.password" id="password" :type="showPassword ? 'text' : 'password'" required class="input w-full pr-12" placeholder="Masukkan password">
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
                        <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                    </button>
                </div>
            </div>

            <button type="submit" :disabled="loading" class="btn btn-primary w-full h-12">
                <span x-show="!loading">Masuk</span>
                <span x-show="loading" class="flex items-center justify-center">
                    <i class="fas fa-spinner animate-spin mr-2"></i> Memproses...
                </span>
            </button>
        </form>
    </div>

    <!-- Back to Home -->
    <a href="/" class="mt-6 text-sm text-[hsl(var(--primary))] hover:underline flex items-center gap-2">
        <i class="fas fa-arrow-left"></i>
        Kembali ke beranda
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
                    this.success = 'Login berhasil! Mengalihkan...';
                    setTimeout(() => window.location.href = '/dashboard', 1000);
                } else {
                    this.error = data.message || 'Login gagal. Periksa kredensial Anda.';
                }
            } catch (e) {
                this.error = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection
