import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5273,
        strictPort: true,
        // In the dev container the browser reaches HMR via the mapped host port.
        hmr: {
            host: 'localhost',
            clientPort: 5273,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
