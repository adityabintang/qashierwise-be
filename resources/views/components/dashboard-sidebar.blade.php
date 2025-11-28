@props(['activePage' => 'dashboard'])

<!-- Sidebar -->
<aside 
    :class="sidebarOpen ? 'w-64' : 'w-[70px]'" 
    class="sidebar flex flex-col fixed lg:static inset-y-0 left-0 z-50 transition-all duration-300 ease-in-out"
>
    <!-- Logo -->
    <div class="h-16 flex items-center px-4 border-b border-[hsl(var(--sidebar-border))]">
        <div x-show="sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" class="h-8 w-8 rounded-lg" alt="Logo">
            <span class="text-lg font-semibold tracking-tight">QashierWise</span>
        </div>
        <img x-show="!sidebarOpen" src="{{ asset('images/logo.png') }}" class="h-8 w-8 rounded-lg mx-auto" alt="Logo">
    </div>

    <!-- Navigation -->
    <nav class="flex-1 py-4 px-2 space-y-1 overflow-y-auto scroll-area">
        <a href="/dashboard" class="sidebar-nav-item {{ $activePage === 'dashboard' ? 'active' : '' }}">
            <i class="fas fa-home w-5 text-center"></i>
            <span x-show="sidebarOpen" x-transition>Dashboard</span>
        </a>
        <a href="/dashboard/contacts" class="sidebar-nav-item {{ $activePage === 'contacts' ? 'active' : '' }}">
            <i class="fas fa-address-book w-5 text-center"></i>
            <span x-show="sidebarOpen" x-transition>Contacts</span>
        </a>
        <a href="/dashboard/messages" class="sidebar-nav-item {{ $activePage === 'messages' ? 'active' : '' }}">
            <i class="fas fa-comments w-5 text-center"></i>
            <span x-show="sidebarOpen" x-transition>Messages</span>
        </a>
        <a href="/dashboard/templates" class="sidebar-nav-item {{ $activePage === 'templates' ? 'active' : '' }}">
            <i class="fas fa-file-alt w-5 text-center"></i>
            <span x-show="sidebarOpen" x-transition>Templates</span>
        </a>
        <a href="/dashboard/profile" class="sidebar-nav-item {{ $activePage === 'profile' ? 'active' : '' }}">
            <i class="fas fa-building w-5 text-center"></i>
            <span x-show="sidebarOpen" x-transition>Business Profile</span>
        </a>
    </nav>

    <!-- User Section -->
    <div class="p-3 border-t border-[hsl(var(--sidebar-border))]">
        <!-- User Info (expanded) -->
        <div x-show="sidebarOpen" x-transition class="mb-3">
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
        <div class="flex items-center gap-2" :class="sidebarOpen ? 'justify-between' : 'justify-center flex-col'">
            <button 
                @click="logout()" 
                class="btn btn-ghost btn-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive)/0.1)]"
                :class="sidebarOpen ? '' : 'btn-icon'"
                :title="!sidebarOpen ? 'Logout' : ''"
            >
                <i class="fas fa-sign-out-alt"></i>
                <span x-show="sidebarOpen">Logout</span>
            </button>
            <button 
                @click="sidebarOpen = !sidebarOpen" 
                class="btn btn-ghost btn-icon"
            >
                <i class="fas transition-transform duration-200" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
            </button>
        </div>
    </div>
</aside>
