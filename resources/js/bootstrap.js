import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Alpine.js - Self-hosted via npm
 * Loaded in main bundle — used on every page.
 */
import Alpine from 'alpinejs';
window.Alpine = Alpine;
