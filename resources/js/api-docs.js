// Entry for /docs: renders the OpenAPI document (docs/api/openapi.yaml) with
// Swagger UI. The viewer is bundled by Vite and served from this origin, and
// it needs no eval and no inline scripts, so the page works under the
// application's Content-Security-Policy without loosening it.
import SwaggerUI from 'swagger-ui-dist/swagger-ui-es-bundle.js';
import 'swagger-ui-dist/swagger-ui.css';

const root = document.getElementById('api-docs');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function isSameOrigin(url) {
    return new URL(url, window.location.href).origin === window.location.origin;
}

if (root) {
    SwaggerUI({
        domNode: root,
        url: root.dataset.specUrl,
        deepLinking: true,
        // Never send the document to the public validator service.
        validatorUrl: null,
        // "Try it out" from a signed-in browser reuses the session: the
        // browser sends Passport's laravel_token cookie, and the API also
        // needs the CSRF token. A bearer token entered with "Authorize" is
        // kept in memory only and is used instead when present.
        requestInterceptor(request) {
            if (csrfToken && isSameOrigin(request.url)) {
                request.headers['X-CSRF-TOKEN'] = csrfToken;
                request.headers['X-Requested-With'] = 'XMLHttpRequest';
            }
            return request;
        },
    });
}
