/**
 * ApexCharts - Lazy-loaded module
 *
 * Only loaded on pages that display charts (dashboard, reports, balance).
 * Loaded via @vite('resources/js/apexcharts.js') in views that need it.
 */
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

console.log('📊 ApexCharts loaded.');
