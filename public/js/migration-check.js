/**
 * Migration Check Utility
 * Checks if user needs to migrate to BYOK system and shows prompt
 */

class MigrationChecker {
    constructor() {
        this.checkInterval = null;
        this.hasChecked = false;
    }

    /**
     * Check if user needs migration
     */
    async checkMigrationStatus() {
        // Only check once per page load
        if (this.hasChecked) {
            return;
        }

        try {
            const token = localStorage.getItem('token');
            if (!token) {
                return;
            }

            const response = await fetch('/api/sub-merchant/migration/status', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            this.hasChecked = true;

            if (data.needs_migration) {
                this.showMigrationPrompt(data);
            }
        } catch (error) {
            console.error('Migration check error:', error);
        }
    }

    /**
     * Show migration prompt modal
     */
    showMigrationPrompt(data) {
        // Check if user has dismissed the prompt recently
        const dismissedUntil = localStorage.getItem('migration_prompt_dismissed');
        if (dismissedUntil) {
            const dismissTime = new Date(dismissedUntil);
            if (dismissTime > new Date()) {
                return; // Still within dismiss period
            }
        }

        // Create modal HTML
        const modal = document.createElement('div');
        modal.id = 'migration-prompt-modal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 animate-fade-in">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                </div>
                
                <h3 class="text-xl font-bold text-center mb-2">Action Required: Migrate to BYOK</h3>
                <p class="text-gray-600 text-center mb-4">
                    ${data.message || 'You need to configure your own payment provider credentials to continue using QRIS.'}
                </p>
                
                <div class="bg-blue-50 border-l-4 border-blue-500 p-3 mb-4 rounded">
                    <p class="text-sm text-blue-800">
                        <strong>Enhanced Security:</strong> Your credentials will be encrypted with AES-256 and only you can access them.
                    </p>
                </div>
                
                ${data.transaction_count ? `
                <div class="text-sm text-gray-600 mb-4 text-center">
                    You have <strong>${data.transaction_count}</strong> existing transaction(s) that will be preserved.
                </div>
                ` : ''}
                
                <div class="flex gap-3">
                    <button onclick="migrationChecker.dismissPrompt()" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Remind Me Later
                    </button>
                    <button onclick="migrationChecker.goToMigration()" 
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Migrate Now
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Add fade-in animation
        setTimeout(() => {
            modal.querySelector('div').classList.add('scale-100');
        }, 10);
    }

    /**
     * Dismiss the prompt for 24 hours
     */
    dismissPrompt() {
        const dismissUntil = new Date();
        dismissUntil.setHours(dismissUntil.getHours() + 24);
        localStorage.setItem('migration_prompt_dismissed', dismissUntil.toISOString());
        
        this.closeModal();
    }

    /**
     * Navigate to migration page
     */
    goToMigration() {
        window.location.href = '/dashboard/sub-merchant/migrate';
    }

    /**
     * Close the modal
     */
    closeModal() {
        const modal = document.getElementById('migration-prompt-modal');
        if (modal) {
            modal.remove();
        }
    }

    /**
     * Initialize migration checker
     */
    init() {
        // Check on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => this.checkMigrationStatus(), 1000);
            });
        } else {
            setTimeout(() => this.checkMigrationStatus(), 1000);
        }
    }
}

// Create global instance
const migrationChecker = new MigrationChecker();

// Auto-initialize
migrationChecker.init();
