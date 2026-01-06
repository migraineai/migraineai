import { defineConfig } from 'vite';
import dns from 'node:dns';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

dns.setDefaultResultOrder('verbatim');

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5174,
        strictPort: true,
        hmr: process.env.NODE_ENV === 'production' ? false : true,
        allowedHosts: ['localhost', '127.0.0.1', '[::1]'],
    },
});
