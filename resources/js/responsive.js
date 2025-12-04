/**
 * Responsive State Management Utilities for Alpine.js
 * 
 * Provides shared viewport detection logic and resize event debouncing
 * for responsive UI components across the QashierWise dashboard.
 * 
 * Requirements: 3.1, 4.1
 */

// Breakpoint constants matching CSS breakpoints
export const BREAKPOINTS = {
    mobile: 768,   // < 768px
    tablet: 1024,  // 768px - 1024px
    desktop: 1024  // > 1024px
};

/**
 * Debounce utility function
 * Delays function execution until after wait milliseconds have elapsed
 * since the last time the debounced function was invoked.
 * 
 * @param {Function} func - The function to debounce
 * @param {number} wait - The number of milliseconds to delay
 * @returns {Function} - The debounced function
 */
export function debounce(func, wait = 150) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Get current viewport type based on window width
 * 
 * @returns {string} - 'mobile', 'tablet', or 'desktop'
 */
export function getViewportType() {
    const width = window.innerWidth;
    if (width < BREAKPOINTS.mobile) {
        return 'mobile';
    } else if (width < BREAKPOINTS.tablet) {
        return 'tablet';
    }
    return 'desktop';
}

/**
 * Check if current viewport is mobile
 * 
 * @returns {boolean}
 */
export function isMobileViewport() {
    return window.innerWidth < BREAKPOINTS.mobile;
}

/**
 * Check if current viewport is tablet
 * 
 * @returns {boolean}
 */
export function isTabletViewport() {
    const width = window.innerWidth;
    return width >= BREAKPOINTS.mobile && width < BREAKPOINTS.tablet;
}

/**
 * Check if current viewport is desktop
 * 
 * @returns {boolean}
 */
export function isDesktopViewport() {
    return window.innerWidth >= BREAKPOINTS.desktop;
}

/**
 * Alpine.js data factory for responsive navigation
 * Used in Welcome page and Dashboard navigation
 * 
 * Requirements: 1.1, 1.2, 3.1, 3.2
 * 
 * @returns {Object} Alpine.js component data
 */
export function responsiveNav() {
    return {
        menuOpen: false,
        isMobile: isMobileViewport(),
        isTablet: isTabletViewport(),
        viewportType: getViewportType(),
        
        init() {
            this._handleResize = debounce(() => {
                this.isMobile = isMobileViewport();
                this.isTablet = isTabletViewport();
                this.viewportType = getViewportType();
                
                // Auto-close menu when switching to desktop
                if (!this.isMobile) {
                    this.menuOpen = false;
                }
            }, 150);
            
            window.addEventListener('resize', this._handleResize);
        },
        
        destroy() {
            if (this._handleResize) {
                window.removeEventListener('resize', this._handleResize);
            }
        },
        
        toggleMenu() {
            this.menuOpen = !this.menuOpen;
        },
        
        closeMenu() {
            this.menuOpen = false;
        }
    };
}

/**
 * Alpine.js data factory for dashboard sidebar
 * Handles sidebar visibility and state across different viewports
 * 
 * Requirements: 3.1, 3.3, 3.4, 3.5, 5.2
 * 
 * @returns {Object} Alpine.js component data
 */
export function dashboardSidebar() {
    return {
        sidebarOpen: !isMobileViewport(),
        isMobile: isMobileViewport(),
        isTablet: isTabletViewport(),
        viewportType: getViewportType(),
        
        init() {
            // Set initial sidebar state based on viewport
            this._updateSidebarState();
            
            this._handleResize = debounce(() => {
                const wasMobile = this.isMobile;
                
                this.isMobile = isMobileViewport();
                this.isTablet = isTabletViewport();
                this.viewportType = getViewportType();
                
                // Auto-adjust sidebar state when viewport changes
                if (wasMobile !== this.isMobile) {
                    this._updateSidebarState();
                }
            }, 150);
            
            window.addEventListener('resize', this._handleResize);
        },
        
        destroy() {
            if (this._handleResize) {
                window.removeEventListener('resize', this._handleResize);
            }
        },
        
        _updateSidebarState() {
            if (this.isMobile) {
                // Hidden by default on mobile (slide-in overlay)
                this.sidebarOpen = false;
            } else if (this.isTablet) {
                // Collapsed by default on tablet (icons only) - Requirements: 5.2
                this.sidebarOpen = false;
            } else {
                // Open by default on desktop (full sidebar)
                this.sidebarOpen = true;
            }
        },
        
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        
        closeSidebar() {
            this.sidebarOpen = false;
        },
        
        // Close sidebar when clicking outside on mobile
        handleBackdropClick() {
            if (this.isMobile && this.sidebarOpen) {
                this.closeSidebar();
            }
        },
        
        // Close sidebar after navigation on mobile
        handleNavigation() {
            if (this.isMobile) {
                this.closeSidebar();
            }
        }
    };
}

/**
 * Alpine.js data factory for mobile messages view
 * Handles the contacts list / chat area toggle on mobile
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4
 * 
 * @returns {Object} Alpine.js component data
 */
export function mobileMessagesView() {
    return {
        mobileView: 'contacts', // 'contacts' | 'chat'
        isMobile: isMobileViewport(),
        selectedContact: null,
        
        init() {
            this._handleResize = debounce(() => {
                const wasMobile = this.isMobile;
                this.isMobile = isMobileViewport();
                
                // Reset to contacts view when switching from mobile to larger viewport
                if (wasMobile && !this.isMobile) {
                    this.mobileView = 'contacts';
                }
            }, 150);
            
            window.addEventListener('resize', this._handleResize);
        },
        
        destroy() {
            if (this._handleResize) {
                window.removeEventListener('resize', this._handleResize);
            }
        },
        
        selectContact(contact) {
            this.selectedContact = contact;
            if (this.isMobile) {
                this.mobileView = 'chat';
            }
        },
        
        backToContacts() {
            this.mobileView = 'contacts';
        },
        
        // Check if contacts list should be visible
        get showContactsList() {
            if (!this.isMobile) return true;
            return this.mobileView === 'contacts';
        },
        
        // Check if chat area should be visible
        get showChatArea() {
            if (!this.isMobile) return true;
            return this.mobileView === 'chat';
        }
    };
}

/**
 * Alpine.js data factory for responsive viewport state
 * Generic utility for components that need viewport awareness
 * 
 * @returns {Object} Alpine.js component data
 */
export function responsiveViewport() {
    return {
        isMobile: isMobileViewport(),
        isTablet: isTabletViewport(),
        isDesktop: isDesktopViewport(),
        viewportType: getViewportType(),
        windowWidth: window.innerWidth,
        
        init() {
            this._handleResize = debounce(() => {
                this.isMobile = isMobileViewport();
                this.isTablet = isTabletViewport();
                this.isDesktop = isDesktopViewport();
                this.viewportType = getViewportType();
                this.windowWidth = window.innerWidth;
            }, 150);
            
            window.addEventListener('resize', this._handleResize);
        },
        
        destroy() {
            if (this._handleResize) {
                window.removeEventListener('resize', this._handleResize);
            }
        }
    };
}

// Make utilities available globally for inline Alpine.js usage
if (typeof window !== 'undefined') {
    window.ResponsiveUtils = {
        BREAKPOINTS,
        debounce,
        getViewportType,
        isMobileViewport,
        isTabletViewport,
        isDesktopViewport,
        responsiveNav,
        dashboardSidebar,
        mobileMessagesView,
        responsiveViewport
    };
}
