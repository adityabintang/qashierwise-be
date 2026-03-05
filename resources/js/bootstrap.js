import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Alpine.js - Self-hosted via npm
 */
import Alpine from 'alpinejs';
window.Alpine = Alpine;

/**
 * Pusher & Laravel Echo - Self-hosted via npm
 */
import Pusher from 'pusher-js';
window.Pusher = Pusher;

import Echo from 'laravel-echo';
window.Echo = Echo;

/**
 * ApexCharts - Self-hosted via npm (lazy-loadable via window.ApexCharts)
 */
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
