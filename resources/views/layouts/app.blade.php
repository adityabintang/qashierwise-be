<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QashierWise')</title>

    <!-- Vite Assets (Tailwind CSS v4 + JS bundle with Alpine, Pusher, Echo, ApexCharts, Font Awesome, Inter font) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine Permissions Store - MUST load before Alpine starts -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('permissions', {
                isAdmin: true,
                userPermissions: ['*'],
                loaded: false,

                // Dashboard permissions that are always allowed for main users
                dashboardPermissions: [
                    'dashboard', 'contacts', 'messages', 'templates',
                    'whatsapp-account', 'ai-agent', 'profile',
                    'pos.orders', 'pos.payment', 'pos.products', 'pos.categories',
                    'pos.stores', 'pos.tables', 'pos.users', 'pos.roles',
                    'pos.reports', 'pos.transactions'
                ],

                init() {
                    this.fetchPermissions();
                },

                async fetchPermissions() {
                    try {
                        const token = localStorage.getItem('token');
                        if (!token) {
                            this.loaded = true;
                            return;
                        }
                        const res = await fetch('/api/user/permissions', {
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Accept': 'application/json'
                            }
                        });
                        if (res.ok) {
                            const data = await res.json();
                            if (data.success) {
                                this.isAdmin = data.data.is_admin || false;
                                this.userPermissions = data.data.permissions || [];
                            }
                        }
                    } catch (e) {
                        console.error('Permissions fetch error:', e);
                    }
                    this.loaded = true;
                },

                hasPermission(permission) {
                    // Admin has all permissions
                    if (this.isAdmin || this.userPermissions.includes('*')) {
                        return true;
                    }
                    // Check dashboard permissions (sidebar visibility)
                    if (this.dashboardPermissions.includes(permission)) {
                        // Map dashboard permission to API permission
                        const apiPermission = this.mapToApiPermission(permission);
                        if (apiPermission) {
                            return this.userPermissions.includes(apiPermission);
                        }
                        return true; // Allow basic dashboard access
                    }
                    return this.userPermissions.includes(permission);
                },

                mapToApiPermission(dashboardPerm) {
                    const map = {
                        'pos.orders': 'view_orders',
                        'pos.payment': 'process_payment',
                        'pos.products': 'view_products',
                        'pos.categories': 'view_categories',
                        'pos.stores': 'view_stores',
                        'pos.tables': 'view_tables',
                        'pos.users': 'view_users',
                        'pos.roles': 'view_roles',
                        'pos.reports': 'view_reports',
                        'pos.transactions': 'view_transactions'
                    };
                    return map[dashboardPerm] || null;
                }
            });
        });
    </script>

    <!-- Echo Setup Script - Deferred -->
    <script defer src="{{ asset('js/echo-setup.js') }}"></script>

    <!-- Dashboard Base - Shared functionality for dashboard pages -->
    <script defer src="{{ asset('js/dashboard-base.js') }}"></script>

    <style>
        [x-cloak] { display: none !important; }
        /* Critical CSS fallback */
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    </style>

    @stack('styles')

    <!-- Google Analytics 4 -->
    <x-google-analytics />
</head>
<body class="min-h-screen bg-[hsl(var(--background))] font-sans antialiased">
    @yield('content')

    @stack('scripts')

    <!-- Cookie Consent Banner -->
    <x-cookie-consent />
</body>
</html>
