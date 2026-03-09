import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/welcome.js',
                'resources/js/alpine-loader.js',
                'resources/js/echo.js',
                'resources/js/apexcharts.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('alpinejs')) return 'alpine';
                        if (id.includes('axios')) return 'vendor';
                    }
                }
            }
        },
        minify: 'esbuild'
    },
    server: {
        https: false,
        host: 'localhost',
    },
});
