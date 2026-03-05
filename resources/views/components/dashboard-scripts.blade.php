{{--
    Dashboard Scripts - Include in all dashboard views.
    Loads Pusher/Echo, permissions store, echo-setup, and dashboard-base.
    These are NOT loaded on public pages (welcome, login, policy pages).
--}}

@push('head-scripts')
    <!-- Pusher & Laravel Echo (self-hosted, only on dashboard) -->
    @vite('resources/js/echo.js')

    <!-- Alpine Permissions Store - MUST load before Alpine starts -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('permissions', {
                isAdmin: true,
                userPermissions: ['*'],
                loaded: false,

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
                    if (this.isAdmin || this.userPermissions.includes('*')) {
                        return true;
                    }
                    if (this.dashboardPermissions.includes(permission)) {
                        const apiPermission = this.mapToApiPermission(permission);
                        if (apiPermission) {
                            return this.userPermissions.includes(apiPermission);
                        }
                        return true;
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
@endpush
