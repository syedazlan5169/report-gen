import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const vitePort = Number(process.env.VITE_PORT || 5173);
const viteHost = process.env.VITE_HMR_HOST || 'localhost';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: vitePort,
        strictPort: true,
        origin: `http://${viteHost}:${vitePort}`,
        hmr: {
            host: viteHost,
            port: vitePort,
        },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
        },
    },
});
