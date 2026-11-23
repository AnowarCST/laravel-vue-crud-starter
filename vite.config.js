import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.js', 'resources/js/auth.js'], refresh: true }),
        vue({
            template: {
                // Let Vite rewrite relative asset URLs in SFC templates, but leave
                // absolute paths (e.g. /images/logo.png, /storage/...) untouched.
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
    define: {
        // Runtime-only Vue build: templates are precompiled at build time and no
        // template compiler ships to the browser. Disable prod devtools hooks.
        __VUE_OPTIONS_API__: 'true',
        __VUE_PROD_DEVTOOLS__: 'false',
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
    },
    server: {
        host: '0.0.0.0', // reachable from the Docker host
        port: 5173,
        strictPort: true,
        // Browser-facing origin (also what laravel-vite-plugin writes to public/hot
        // and what the CSP allows); never 0.0.0.0.
        origin: 'http://localhost:5173',
        hmr: { host: 'localhost' },
    },
    build: { sourcemap: false },
});
