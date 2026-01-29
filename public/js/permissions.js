// Add to your dashboard layout or Alpine.js initialization
// Helper function to check if user has permission
window.hasPermission = function(permission) {
    const permissions = window.userPermissions || [];
    const isAdmin = window.isAdmin || false;

    // Admins have all permissions
    if (isAdmin || permissions.includes('*')) {
        return true;
    }

    // Check if user has the specific permission
    return permissions.includes(permission);
};

// Fetch user permissions on page load
async function fetchUserPermissions() {
    try {
        const token = localStorage.getItem('token');
        if (!token) return;

        const response = await fetch(window.location.origin + '/api/user/permissions', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        if (data.success) {
            window.isAdmin = data.data.is_admin || false;
            window.userPermissions = data.data.permissions || [];
        }
    } catch (e) {
        console.error('Failed to fetch permissions:', e);
    }
}

// Run on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fetchUserPermissions);
} else {
    fetchUserPermissions();
}
