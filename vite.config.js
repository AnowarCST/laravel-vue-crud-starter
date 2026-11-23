import { readFileSync } from 'node:fs';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Licence notices that Vite's `build.license` file does not include: the
// Apache-2.0 NOTICE file of swagger-ui-dist, and the licence list of the
// third-party code inside its pre-built bundle. The bundle's preserved
// comment points to the second file by name, so it goes next to the chunk.
const extraLicenceFiles = {
    'swagger-ui-dist-NOTICE.txt': 'node_modules/swagger-ui-dist/NOTICE',
    'assets/swagger-ui-es-bundle.js.LICENSE.txt': 'node_modules/swagger-ui-dist/swagger-ui-es-bundle.js.LICENSE.txt',
};

function emitLicenceFiles(files) {
    return {
        name: 'emit-licence-files',
        apply: 'build',
        generateBundle() {
            for (const [fileName, source] of Object.entries(files)) {
                this.emitFile({ type: 'asset', fileName, source: readFileSync(source, 'utf8') });
            }
        },
    };
}

export default defineConfig(({ mode }) => {
    // Values from .env, overridden by real environment variables.
    const env = loadEnv(mode, process.cwd(), '');

    // Dev server port. Docker publishes the same number on the host
    // (127.0.0.1:${VITE_PORT}:${VITE_PORT}), so the browser-facing URL uses it too.
    const port = Number(env.VITE_PORT || 5173);

    // Pages are served by Laravel (APP_URL, e.g. http://localhost:8000) but load
    // scripts from the Vite dev server, which is a different origin. The dev
    // server must allow that origin through CORS, or the browser blocks every
    // module and the pages render without scripts or styles.
    const allowedOrigins = [/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/];
    if (env.APP_URL) {
        allowedOrigins.push(new URL(env.APP_URL).origin);
    }

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.js', 'resources/js/auth.js', 'resources/js/api-docs.js'],
                refresh: true,
            }),
            vue({
                template: {
                    // Let Vite rewrite relative asset URLs in SFC templates, but leave
                    // absolute paths (e.g. /images/logo.svg, /storage/...) untouched.
                    transformAssetUrls: { base: null, includeAbsolute: false },
                },
            }),
            emitLicenceFiles(extraLicenceFiles),
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
            port,
            strictPort: true,
            // Browser-facing origin (also what laravel-vite-plugin writes to public/hot
            // and what the CSP allows); never 0.0.0.0.
            origin: `http://localhost:${port}`,
            // Set explicitly: when `origin` is set, laravel-vite-plugin would
            // otherwise allow only the dev server's own origin.
            cors: { origin: allowedOrigins },
            hmr: { host: 'localhost' },
        },
        build: {
            sourcemap: false,
            // public/build/licenses.txt: every bundled npm package with the
            // text of its LICENSE file (Markdown; Apache denies *.md files).
            license: { fileName: 'licenses.txt' },
            // Keep legal comments (@license, /*! ... */) in the minified
            // JavaScript; Vite drops them by default when it minifies. The CSS
            // minifier removes them from the CSS, so licenses.txt covers it.
            rolldownOptions: { output: { comments: { legal: true } } },
        },
    };
});
