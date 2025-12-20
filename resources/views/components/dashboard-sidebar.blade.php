@props(['activePage' => 'dashboard'])

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
        <a href="/dashboard" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'dashboard' ? 'active' : '' }}">
            <i class="fas fa-home w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Dashboard</span>
        </a>
        <a href="/dashboard/contacts" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'contacts' ? 'active' : '' }}">
            <i class="fas fa-address-book w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Contacts</span>
        </a>
        <a href="/dashboard/messages" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'messages' ? 'active' : '' }}">
            <i class="fas fa-comments w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Messages</span>
        </a>
        <a href="/dashboard/templates" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'templates' ? 'active' : '' }}">
            <i class="fas fa-file-alt w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Templates</span>
        </a>
        <a href="/dashboard/profile" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'profile' ? 'active' : '' }}">
            <i class="fas fa-building w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Business Profile</span>
        </a>
        <a href="/dashboard/whatsapp-account" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'whatsapp-account' ? 'active' : '' }}">
            <i class="fab fa-whatsapp w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>WhatsApp Account</span>
        </a>
        <a href="/dashboard/ai-agent" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'ai-agent' ? 'active' : '' }}">
            <i class="fas fa-robot w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>AI Agent</span>
        </a>

        <!-- POS Section - Orders & Payment -->
        <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
            <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                Point of Sale
            </div>
        </div>
        <a href="/dashboard/pos/orders" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-orders' ? 'active' : '' }}">
            <i class="fas fa-shopping-cart w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Orders</span>
        </a>
        <a href="/dashboard/pos/payment" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-payment' ? 'active' : '' }}">
            <i class="fas fa-credit-card w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Payment</span>
        </a>

        <!-- POS Inventory Section -->
        <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
            <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                Inventory
            </div>
        </div>
        <a href="/dashboard/pos/products" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-products' ? 'active' : '' }}">
            <i class="fas fa-box w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Products</span>
        </a>
        <a href="/dashboard/pos/categories" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-categories' ? 'active' : '' }}">
            <i class="fas fa-tags w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Categories</span>
        </a>

        <!-- POS Operations Section -->
        <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
            <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                Operations
            </div>
        </div>
        <a href="/dashboard/pos/stores" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-stores' ? 'active' : '' }}">
            <i class="fas fa-store w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Stores</span>
        </a>
        <a href="/dashboard/pos/tables" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-tables' ? 'active' : '' }}">
            <i class="fas fa-chair w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Tables</span>
        </a>
        <a href="/dashboard/pos/users" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-users' ? 'active' : '' }}">
            <i class="fas fa-users w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Users</span>
        </a>

        <!-- POS Analytics Section -->
        <div x-show="sidebarOpen || isMobile" x-transition class="pt-4">
            <div class="px-3 py-2 text-xs font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wider">
                Analytics
            </div>
        </div>
        <a href="/dashboard/pos/reports" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-reports' ? 'active' : '' }}">
            <i class="fas fa-chart-bar w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Reports</span>
        </a>
        <a href="/dashboard/pos/transactions" @click="if(isMobile) sidebarOpen = false" class="sidebar-nav-item {{ $activePage === 'pos-transactions' ? 'active' : '' }}">
            <i class="fas fa-receipt w-5 text-center"></i>
            <span x-show="sidebarOpen || isMobile" x-transition>Transactions</span>
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
                :title="!(sidebarOpen || isMobile) ? 'Logout' : ''"
            >
                <i class="fas fa-sign-out-alt"></i>
                <span x-show="sidebarOpen || isMobile">Logout</span>
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
