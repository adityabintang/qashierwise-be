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
