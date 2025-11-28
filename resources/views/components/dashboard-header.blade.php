@props(['title' => 'Dashboard', 'description' => ''])

<!-- Header -->
<header class="sticky top-0 z-40 bg-[hsl(var(--background))]/95 backdrop-blur supports-[backdrop-filter]:bg-[hsl(var(--background))]/60 border-b border-[hsl(var(--border))]">
    <div class="flex h-16 items-center justify-between px-6">
        <!-- Title -->
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ $title }}</h1>
            @if($description)
                <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ $description }}</p>
            @endif
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
            <!-- Notifications -->
            <div x-data="{ open: false }" class="relative">
                <button 
                    @click="open = !open" 
                    class="btn btn-ghost btn-icon relative"
                >
                    <i class="fas fa-bell text-lg"></i>
                    <span 
                        x-show="notifications.length > 0" 
                        x-transition
                        class="notification-badge"
                        x-text="notifications.length > 9 ? '9+' : notifications.length"
                    ></span>
                </button>

                <!-- Notifications Dropdown -->
                <div 
                    x-show="open" 
                    x-cloak
                    @click.away="open = false" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-1"
                    class="dropdown-content absolute right-0 mt-2 w-80 max-h-96 overflow-hidden"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 border-b border-[hsl(var(--border))]">
                        <h3 class="font-semibold text-sm">Notifications</h3>
                        <button 
                            x-show="notifications.length > 0" 
                            @click="clearNotifications()" 
                            class="text-xs text-[hsl(var(--destructive))] hover:underline"
                        >
                            Clear all
                        </button>
                    </div>

                    <!-- Notifications List -->
                    <div class="max-h-72 overflow-y-auto scroll-area">
                        <!-- Empty State -->
                        <template x-if="notifications.length === 0">
                            <div class="empty-state py-8">
                                <div class="empty-state-icon">
                                    <i class="fas fa-bell-slash"></i>
                                </div>
                                <p class="text-sm text-[hsl(var(--muted-foreground))]">No notifications</p>
                            </div>
                        </template>

                        <!-- Notification Items -->
                        <template x-for="notif in notifications" :key="notif.id">
                            <div class="flex items-start gap-3 px-4 py-3 hover:bg-[hsl(var(--accent))] transition-colors group border-b border-[hsl(var(--border))] last:border-0">
                                <div 
                                    class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                                    :class="{
                                        'bg-[hsl(var(--primary)/0.1)] text-[hsl(var(--primary))]': notif.color === 'purple',
                                        'bg-blue-100 text-blue-600': notif.color === 'blue',
                                        'bg-amber-100 text-amber-600': notif.color === 'yellow',
                                        'bg-red-100 text-red-600': notif.color === 'red',
                                        'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]': !notif.color
                                    }"
                                >
                                    <i class="fas text-sm" :class="notif.icon || 'fa-bell'"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium" x-text="notif.title || 'Notification'"></p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] truncate" x-text="notif.message"></p>
                                    <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1" x-text="formatNotificationTime(notif.time)"></p>
                                </div>
                                <button 
                                    @click.stop="removeNotification(notif.id)" 
                                    class="opacity-0 group-hover:opacity-100 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--destructive))] transition-opacity"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
