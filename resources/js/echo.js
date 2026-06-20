/**
 * Pusher & Laravel Echo - Lazy-loaded module
 *
 * Only loaded on dashboard pages that need real-time messaging.
 * Loaded via @vite('resources/js/echo.js') in layouts that need it.
 */
import Pusher from 'pusher-js';
window.Pusher = Pusher;

import Echo from 'laravel-echo';
window.Echo = Echo;

console.log('📡 Pusher & Echo loaded.');
