<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Access rules for /docs and /docs/openapi.yaml (config/api-docs.php,
 * App\Http\Middleware\AuthorizeApiDocs).
 */
class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    private const URLS = ['/docs', '/docs/openapi.yaml'];

    public function test_docs_are_disabled_by_default_outside_the_local_environment(): void
    {
        $this->assertFalse(config('api-docs.enabled'));

        $this->assertDocsReturn(404);
        $this->actingAs(User::factory()->admin()->create());
        $this->assertDocsReturn(404);
    }

    public function test_disabled_docs_return_404_even_in_the_local_environment(): void
    {
        config(['api-docs.enabled' => false]);
        $this->app->detectEnvironment(fn () => 'local');

        $this->assertDocsReturn(404);
    }

    public function test_local_environment_serves_docs_to_guests(): void
    {
        config(['api-docs.enabled' => true]);
        $this->app->detectEnvironment(fn () => 'local');

        $this->assertDocsReturn(200);
    }

    public function test_outside_local_guests_are_sent_to_login(): void
    {
        config(['api-docs.enabled' => true]);

        foreach (self::URLS as $url) {
            $this->get($url)->assertRedirect('/login');
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_outside_local_regular_users_are_forbidden(): void
    {
        config(['api-docs.enabled' => true]);
        $this->actingAs(User::factory()->create());

        $this->assertDocsReturn(403);
    }

    public function test_outside_local_admins_must_verify_their_email_first(): void
    {
        config(['api-docs.enabled' => true]);
        $this->actingAs(User::factory()->admin()->unverified()->create());

        foreach (self::URLS as $url) {
            $this->get($url)->assertRedirect('/email/verify');
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_outside_local_verified_admins_can_open_the_docs(): void
    {
        config(['api-docs.enabled' => true]);
        $this->actingAs(User::factory()->admin()->create());

        $this->assertDocsReturn(200);
    }

    public function test_viewer_page_keeps_the_strict_csp_and_has_no_inline_script(): void
    {
        config(['api-docs.enabled' => true]);
        $this->withVite();
        Vite::useHotFile($hot = (string) tempnam(sys_get_temp_dir(), 'hot'));
        file_put_contents($hot, 'http://localhost:5173');

        try {
            $response = $this->actingAs(User::factory()->admin()->create())->get('/docs')->assertOk();
        } finally {
            @unlink($hot);
        }

        $nonce = Vite::cspNonce();
        $this->assertSame(
            "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; "
            ."base-uri 'self'; form-action 'self'; object-src 'none'",
            $response->headers->get('Content-Security-Policy'),
        );
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $html = (string) $response->getContent();
        $response->assertSee('data-spec-url="/docs/openapi.yaml"', false);
        $this->assertStringContainsString('resources/js/api-docs.js', $html);

        // Every <script> tag loads a file and carries the nonce; none is inline.
        preg_match_all('~<script\b[^>]*>~i', $html, $scripts);
        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[0] as $tag) {
            $this->assertStringContainsString('src=', $tag);
            $this->assertStringContainsString('nonce="'.$nonce.'"', $tag);
        }
        $this->assertDoesNotMatchRegularExpression('~<script\b[^>]*>\s*[^<\s]~i', $html);
    }

    public function test_spec_endpoint_returns_the_openapi_document(): void
    {
        config(['api-docs.enabled' => true]);

        $response = $this->actingAs(User::factory()->admin()->create())->get('/docs/openapi.yaml')->assertOk();

        $this->assertStringStartsWith('application/yaml', (string) $response->headers->get('Content-Type'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $content = (string) $response->streamedContent();
        $this->assertSame((string) file_get_contents(base_path('docs/api/openapi.yaml')), $content);

        $document = Yaml::parse($content);
        $this->assertIsArray($document);
        $this->assertIsString($document['openapi'] ?? null);
        $this->assertMatchesRegularExpression('/^3\.1\.\d+$/', $document['openapi']);
        $this->assertIsArray($document['paths'] ?? null);
        $this->assertArrayHasKey('/version', $document['paths']);
    }

    private function assertDocsReturn(int $status): void
    {
        foreach (self::URLS as $url) {
            $this->get($url)->assertStatus($status);
        }
    }
}
