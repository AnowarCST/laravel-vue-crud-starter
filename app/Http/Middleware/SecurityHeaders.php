<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a strict Content-Security-Policy (with a per-request script nonce that
 * the @vite directive picks up automatically) and other hardening headers.
 *
 * Contract with the frontend: Blade templates contain no inline <script>
 * blocks or inline event handlers; bootstrap data is passed via data-*
 * attributes (e.g. data-user on #spa-app).
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;

        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    protected function contentSecurityPolicy(string $nonce): string
    {
        $dev = $this->viteDevServerSources();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$dev['http']],
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev['http']],
            'img-src' => ["'self'", 'data:', 'blob:', ...$dev['http']],
            'font-src' => ["'self'", 'data:', ...$dev['http']],
            'connect-src' => ["'self'", ...$dev['http'], ...$dev['ws']],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return implode('; ', array_map(
            fn (string $directive, array $sources) => $directive.' '.implode(' ', $sources),
            array_keys($directives),
            $directives,
        ));
    }

    /**
     * While `npm run dev` is running locally, Vite serves modules (and HMR over
     * a websocket) from its own origin, recorded in public/hot.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    protected function viteDevServerSources(): array
    {
        $none = ['http' => [], 'ws' => []];

        if (! app()->environment('local') || ! is_file($hotFile = Vite::hotFile())) {
            return $none;
        }

        $parts = parse_url(trim((string) file_get_contents($hotFile)));

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            return $none;
        }

        $host = str_contains($parts['host'], ':') && ! str_starts_with($parts['host'], '[')
            ? '['.$parts['host'].']'
            : $parts['host'];
        $authority = $host.(isset($parts['port']) ? ':'.$parts['port'] : '');
        $wsScheme = $parts['scheme'] === 'https' ? 'wss' : 'ws';

        return [
            'http' => [$parts['scheme'].'://'.$authority],
            'ws' => [$wsScheme.'://'.$authority],
        ];
    }
}
