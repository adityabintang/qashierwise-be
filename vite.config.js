import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/static.js',
                'resources/js/welcome.js',
                'resources/js/alpine-loader.js',
                'resources/js/echo.js',
                'resources/js/apexcharts.js',
                // React island: public landing + legal pages (ported from Next.js)
                'resources/js/next.js/main.tsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            // `@/...` imports inside the ported Next.js code resolve here.
            '@': fileURLToPath(new URL('./resources/js/next.js', import.meta.url)),
        },
    },
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
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
    },
});
