/**
 * Initialize Alpine permissions store
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('permissions', {
        isAdmin: true,
        userPermissions: [],
        loaded: false,

        async init() {
            await this.fetchUserPermissions();
            this.loaded = true;
        },

        async fetchUserPermissions() {
            try {
                const token = localStorage.getItem('token');
                if (!token) {
                    this.isAdmin = true;
                    this.userPermissions = [];
                    return;
                }

                const res = await fetch(`${window.location.origin}/api/user/permissions`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.success) {
                        this.isAdmin = data.data.is_admin || false;
                        this.userPermissions = data.data.permissions || [];
                    } else {
                        // API error, default to admin
                        this.isAdmin = true;
                        this.userPermissions = [];
                    }
                } else {
                    // HTTP error, default to admin
                    this.isAdmin = true;
                    this.userPermissions = [];
                }
            } catch (e) {
                console.error('Failed to fetch permissions:', e);
                this.isAdmin = true;
                this.userPermissions = [];
            }
        },

        hasPermission(permission) {
            // If not loaded yet, show everything (default to admin)
            if (!this.loaded) return true;
            // Admin has all permissions
            if (this.isAdmin) return true;
            // Check for wildcard permission
            if (this.userPermissions.includes('*')) return true;
            // Check for specific permission
            return this.userPermissions.includes(permission);
        }
    });
});

// Initialize permissions when Alpine starts
document.addEventListener('alpine:initialized', () => {
    Alpine.store('permissions').init();
});

/**
 * Base Alpine.js data for all dashboard pages
 * Provides common functionality like notifications, sidebar, and user management
 */
function dashboardBase() {
    return {
        // Sidebar state
        sidebarOpen: true,
        isMobile: window.innerWidth < 768,

        // User data
        user: null,

        // Permissions
        userPermissions: [],
        isAdmin: true,

        // Notifications
        notifications: [],

        /**
         * Initialize dashboard base functionality
         */
        initDashboard() {
            // Set initial sidebar state based on viewport
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile) {
                this.sidebarOpen = false;
            } else {
                this.sidebarOpen = true;
                localStorage.setItem('sidebarOpen', 'true');
            }

            // Watch sidebar state changes (only save on desktop)
            this.$watch('sidebarOpen', v => {
                if (!this.isMobile) localStorage.setItem('sidebarOpen', JSON.stringify(v));
            });

            // Handle resize events with debounce
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth < 768;

                    // Auto-adjust sidebar when crossing breakpoint
                    if (wasMobile && !this.isMobile) {
                        let savedState = localStorage.getItem('sidebarOpen');
                        this.sidebarOpen = savedState !== null ? JSON.parse(savedState) : true;
                    } else if (!wasMobile && this.isMobile) {
                        this.sidebarOpen = false;
                    }
                }, 150);
            });

            // Load user info
            let storedUser = localStorage.getItem('user');
            if (storedUser) {
                try {
                    this.user = JSON.parse(storedUser);
                } catch (e) {
                    this.user = { name: 'User', email: 'user@example.com' };
                }
            } else {
                this.user = { name: 'User', email: 'user@example.com' };
            }

            // Load saved notifications
            let savedNotifs = localStorage.getItem('notifications');
            if (savedNotifs) {
                try {
                    this.notifications = JSON.parse(savedNotifs);
                } catch (e) {
                    this.notifications = [];
                }
            }

            // Fetch user permissions
            this.fetchUserPermissions();

            // Listen for WhatsApp message events
            window.addEventListener('whatsapp-message-received', (e) => {
                this.addNotification({
                    type: 'message',
                    icon: 'fa-whatsapp',
                    color: 'blue',
                    title: 'New WhatsApp Message',
                    message: `From ${e.detail.contact?.name || e.detail.contact?.phone_number || 'Unknown'}`,
                    time: new Date().toISOString()
                });
            });
        },

        /**
         * Fetch user permissions from API
         */
        async fetchUserPermissions() {
            try {
                const token = localStorage.getItem('token');
                if (!token) {
                    this.isAdmin = true;
                    this.userPermissions = [];
                    return;
                }

                const res = await fetch(`${window.location.origin}/api/user/permissions`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
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
                console.error('Failed to fetch permissions:', e);
                // Default to admin if fetch fails to avoid breaking UI
                this.isAdmin = true;
                this.userPermissions = [];
            }
        },

        /**
         * Check if user has a specific permission
         */
        hasPermission(permission) {
            // Admin has all permissions
            if (this.isAdmin) return true;

            // Check for wildcard permission
            if (this.userPermissions.includes('*')) return true;

            // Check for specific permission
            return this.userPermissions.includes(permission);
        },

        /**
         * Add a new notification
         */
        addNotification(notif) {
            notif.id = Date.now() + Math.random();
            this.notifications.unshift(notif);

            // Keep only last 50 notifications
            if (this.notifications.length > 50) {
                this.notifications = this.notifications.slice(0, 50);
            }

            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        /**
         * Clear all notifications
         */
        clearNotifications() {
            this.notifications = [];
            localStorage.removeItem('notifications');
        },

        /**
         * Remove a specific notification by ID
         */
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
            localStorage.setItem('notifications', JSON.stringify(this.notifications));
        },

        /**
         * Format notification timestamp to relative time
         */
        formatNotificationTime(timestamp) {
            if (!timestamp) return '';

            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffSecs = Math.floor(diffMs / 1000);

            if (diffSecs < 60) return 'Just now';

            const diffMins = Math.floor(diffSecs / 60);
            if (diffMins < 60) return `${diffMins}m ago`;

            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h ago`;

            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return `${diffDays}d ago`;

            return date.toLocaleDateString();
        },

        /**
         * Logout user
         */
        logout() {
            const token = localStorage.getItem('token');
            if (token) {
                fetch(`${window.location.origin}/api/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                }).finally(() => this.clearAndRedirect());
            } else {
                this.clearAndRedirect();
            }
        },

        /**
         * Clear local storage and redirect to login
         */
        clearAndRedirect() {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            localStorage.removeItem('sidebarOpen');
            localStorage.removeItem('notifications');
            window.location.href = '/login';
        }
    };
}
