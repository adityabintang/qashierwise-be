import './bootstrap';
import './responsive';

/**
 * Start Alpine.js
 * Must be called after all Alpine plugins, stores, and components are registered.
 * Blade templates can register stores via alpine:init event before this runs.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Small delay to ensure all alpine:init listeners have been registered
    window.Alpine.start();
});
