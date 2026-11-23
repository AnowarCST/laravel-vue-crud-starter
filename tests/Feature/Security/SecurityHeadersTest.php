<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_responses_carry_a_nonce_based_csp_and_hardening_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $nonce = Vite::cspNonce();
        $this->assertNotEmpty($nonce);
        $this->assertSame(
            "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; "
            ."base-uri 'self'; form-action 'self'; object-src 'none'",
            $csp,
        );
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_nonce_changes_per_request(): void
    {
        $first = $this->get('/')->headers->get('Content-Security-Policy');
        $second = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_api_and_error_responses_also_carry_headers(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');

        $this->actingAs(User::factory()->create(), 'api')
            ->getJson('/api/version')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_vite_dev_server_origin_is_allowed_only_in_local_environment(): void
    {
        $hot = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($hot, 'http://localhost:5173');
        $this->withVite();
        Vite::useHotFile($hot);

        try {
            $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
            $this->assertStringNotContainsString('localhost:5173', $csp);

            $this->app->detectEnvironment(fn () => 'local');
            $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
            $this->assertMatchesRegularExpression("~script-src 'self' 'nonce-[^']+' http://localhost:5173;~", $csp);
            $this->assertStringContainsString("style-src 'self' 'unsafe-inline' http://localhost:5173;", $csp);
            $this->assertStringContainsString("connect-src 'self' http://localhost:5173 ws://localhost:5173;", $csp);
        } finally {
            @unlink($hot);
        }
    }

    public function test_json_errors_do_not_leak_exception_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/__boom', fn () => throw new \RuntimeException('secret detail /var/www'));

        $response = $this->getJson('/api/__boom')->assertStatus(500);
        $response->assertExactJson(['message' => 'Server Error']);
        $this->assertStringNotContainsString('secret detail', (string) $response->getContent());
    }

    public function test_cors_only_allows_the_application_origin(): void
    {
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/version', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNotSame('https://evil.example', $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('http://localhost', $preflight('http://localhost')->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($preflight('http://localhost')->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
