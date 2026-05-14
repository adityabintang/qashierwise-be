@props(['activePage' => 'dashboard'])

@php
    $isUsersManagementPage = request()->is('dashboard/pos/roles*') || request()->is('dashboard/pos/users*');
@endphp

<!-- Mobile Backdrop Overlay -->
<div
    x-show="isMobile && sidebarOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="sidebarOpen = false"
    class="fixed inset-0 bg-black/50 z-40 lg:hidden"
    x-cloak
></div>

<!-- Sidebar -->
<!-- Requirements: 5.2 - Collapsed sidebar (icons only) on tablet by default -->
<aside
    x-data="{ usersManagementOpen: {{ $isUsersManagementPage ? 'true' : 'false' }} }"
    :class="[
        sidebarOpen ? 'w-64' : (isMobile ? 'w-64' : 'w-[70px]'),
        isMobile ? (sidebarOpen ? 'translate-x-0' : '-translate-x-full') : 'translate-x-0'
    ]"
    class="sidebar flex flex-col fixed inset-y-0 left-0 z-50 transition-all duration-300 ease-in-out lg:static"
>
    <!-- Logo -->
    <div class="h-16 flex items-center px-4 border-b border-[hsl(var(--sidebar-border))]">
        <div x-show="sidebarOpen || isMobile" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="flex items-center gap-3">
            <img src="{{ asset('images/logo-64.png') }}" class="h-8 w-8 rounded-lg" alt="Logo" width="32" height="32" loading="eager">
            <span class="text-lg font-semibold tracking-tight">QashierWise</span>
        </div>
        <img x-show="!sidebarOpen && !isMobile" src="{{ asset('images/logo-64.png') }}" class="h-8 w-8 rounded-lg mx-auto" alt="Logo" width="32" height="32" loading="eager">
    </div>

    <!-- Navigation -->
    <nav class="flex-1 py-4 px-2 space-y-1 overflow-y-auto scroll-area">
        <a href="/dashboard" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'dashboard' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('dashboard')">
            <i class="fas fa-home w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_dashboard') }}</span>
        </a>
        <a href="/dashboard/contacts" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'contacts' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('contacts')">
            <i class="fas fa-address-book w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_contacts') }}</span>
        </a>
        <a href="/dashboard/messages" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'messages' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('messages')">
            <i class="fas fa-comments w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_messages') }}</span>
        </a>
        <a href="/dashboard/templates" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'templates' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('templates')">
            <i class="fas fa-file-alt w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_templates') }}</span>
        </a>
        <a href="/dashboard/profile" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'profile' ? 'active' : '' }}">
            <i class="fas fa-building w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_business_profile') }}</span>
        </a>
        <a href="/dashboard/whatsapp-account" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'whatsapp-account' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('whatsapp-account')">
            <i class="fab fa-whatsapp w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_whatsapp_account') }}</span>
        </a>
        <a href="/dashboard/ai-agent" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'ai-agent' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('ai-agent')">
            <i class="fas fa-robot w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_ai_agent') }}</span>
        </a>
        <a href="/dashboard/customer-tags" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'customer-tags' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('customer-tags')">
            <i class="fas fa-tags w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_customer_tags') }}</span>
        </a>

        <!-- Reservation Section -->
        <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
            <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                Reservasi
            </div>
        </div>
        <a href="/dashboard/reservations" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'reservations' ? 'active' : '' }}">
            <i class="fas fa-calendar-check w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Daftar Reservasi</span>
        </a>
        <a href="/dashboard/reservations/calendar" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'reservations-calendar' ? 'active' : '' }}">
            <i class="fas fa-calendar-alt w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Kalender</span>
        </a>
        <a href="/dashboard/reservations/config" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'reservations-config' ? 'active' : '' }}">
            <i class="fas fa-cog w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Konfigurasi</span>
        </a>

        <!-- POS Section - Orders & Payment -->
        <template x-if="$store.permissions.hasPermission('pos.orders') || $store.permissions.hasPermission('pos.payment')">
            <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
                <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                    {{ __('dashboard.section_point_of_sale') }}
                </div>
            </div>
        </template>
        <a href="/dashboard/pos/orders" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-orders' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.orders')">
            <i class="fas fa-shopping-cart w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_orders') }}</span>
        </a>
        <a href="/dashboard/pos/payment" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-payment' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.payment')">
            <i class="fas fa-credit-card w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_payment') }}</span>
        </a>

        <!-- POS Inventory Section -->
        <template x-if="$store.permissions.hasPermission('pos.products') || $store.permissions.hasPermission('pos.categories')">
            <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
                <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                    {{ __('dashboard.section_inventory') }}
                </div>
            </div>
        </template>
        <a href="/dashboard/pos/products" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-products' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.products')">
            <i class="fas fa-box w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_products') }}</span>
        </a>
        <a href="/dashboard/meta-catalog" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'meta-catalog' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.products')">
            <i class="fab fa-facebook w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_meta_catalog') }}</span>
        </a>
        <a href="/dashboard/pos/categories" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-categories' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.categories')">
            <i class="fas fa-tags w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_categories') }}</span>
        </a>

        <!-- POS Operations Section -->
        <template x-if="$store.permissions.hasPermission('pos.stores') || $store.permissions.hasPermission('pos.tables') || $store.permissions.hasPermission('pos.users')">
            <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
                <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                    {{ __('dashboard.section_operations') }}
                </div>
            </div>
        </template>
        <a href="/dashboard/pos/stores" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-stores' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.stores')">
            <i class="fas fa-store w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_stores') }}</span>
        </a>
        <a href="/dashboard/pos/tables" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-tables' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.tables')">
            <i class="fas fa-chair w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_tables') }}</span>
        </a>

        <!-- Users Management Section -->
        <template x-if="$store.permissions.hasPermission('pos.users')">
            <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
                <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider flex items-center justify-between cursor-pointer" @click="usersManagementOpen = !usersManagementOpen">
                    <span>Users Management</span>
                    <i class="fas transition-transform duration-200" :class="usersManagementOpen ? 'fa-chevron-down' : 'fa-chevron-right'" x-show="sidebarOpen || isMobile"></i>
                </div>
            </div>
        </template>
        <div x-show="usersManagementOpen && (sidebarOpen || isMobile) && $store.permissions.hasPermission('pos.users')" x-transition class="space-y-1">
            <a href="/dashboard/pos/roles" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-roles' ? 'active' : '' }}">
                <i class="fas fa-shield-alt w-5 text-center"></i>
                <span x-show="sidebarOpen || isMobile" x-transition>Roles</span>
            </a>
            <a href="/dashboard/pos/users" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-users' ? 'active' : '' }}">
                <i class="fas fa-users w-5 text-center"></i>
                <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_users') }}</span>
            </a>
        </div>

        <!-- POS Analytics Section -->
        <template x-if="$store.permissions.hasPermission('pos.reports') || $store.permissions.hasPermission('pos.transactions')">
            <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
                <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                    {{ __('dashboard.section_analytics') }}
                </div>
            </div>
        </template>
        <a href="/dashboard/pos/reports" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-reports' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.reports')">
            <i class="fas fa-chart-bar w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_reports') }}</span>
        </a>
        <a href="/dashboard/pos/transactions" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-transactions' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('pos.transactions')">
            <i class="fas fa-receipt w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_transactions') }}</span>
        </a>

        <!-- Sub-Merchant Section -->
        <template x-if="$store.permissions.hasPermission('sub-merchant')">
            <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
                <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                    QRIS Payment
                </div>
            </div>
        </template>
        <a href="/dashboard/sub-merchant/" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'sub-merchant' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('sub-merchant')">
            <i class="fas fa-store w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Pengaturan QRIS</span>
        </a>
        <a href="/dashboard/sub-merchant/qris" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'sub-merchant-qris' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('sub-merchant')">
            <i class="fas fa-qrcode w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_generate_qris') }}</span>
        </a>
        <a href="/dashboard/sub-merchant/balance" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'sub-merchant-balance' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('sub-merchant')">
            <i class="fas fa-wallet w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>{{ __('dashboard.menu_balance') }}</span>
        </a>
        <a href="/dashboard/sub-merchant/bank-account" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'sub-merchant-bank-account' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('sub-merchant')">
            <i class="fas fa-university w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Rekening Bank</span>
        </a>
        <a href="/dashboard/sub-merchant/withdrawals" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'sub-merchant-withdrawals' ? 'active' : '' }}" x-show="$store.permissions.hasPermission('sub-merchant')">
            <i class="fas fa-money-bill-wave w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Withdrawal</span>
        </a>
    </nav>

    <!-- User Section -->
    <div class="p-3 border-t border-[hsl(var(--sidebar-border))]">
        <!-- User Info (expanded) -->
        <div x-show="sidebarOpen || isMobile" x-transition class="mb-3">
            <div class="flex items-center gap-3 p-2 rounded-lg bg-[hsl(var(--muted))]">
                <img
                    :src="`https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(user?.name || 'User')}&backgroundColor=a855f7`"
                    :alt="user?.name || 'User'"
                    class="avatar avatar-sm"
                >
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate" x-text="user ? user.name : 'User'"></p>
                    <p class="text-xs text-[hsl(var(--muted-foreground))] truncate" x-text="user ? user.email : ''"></p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2" :class="(sidebarOpen || isMobile) ? 'justify-between' : 'justify-center flex-col'">
            <button
                @click="logout()"
                class="btn btn-ghost btn-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive)/0.1)]"
                :class="(sidebarOpen || isMobile) ? '' : 'btn-icon'"
                :title="!(sidebarOpen || isMobile) ? '{{ __('dashboard.logout') }}' : ''"
            >
                <i class="fas fa-sign-out-alt"></i>
                <span x-show="sidebarOpen || isMobile">{{ __('dashboard.logout') }}</span>
            </button>
            <button
                @click="sidebarOpen = !sidebarOpen"
                class="btn btn-ghost btn-icon hidden lg:flex"
            >
                <i class="fas transition-transform duration-200" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
            </button>
        </div>
    </div>
</aside>
