<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Keeps docs/api/openapi.yaml in step with routes/api.php: adding, removing
 * or renaming an /api route without updating the document fails here.
 */
class OpenApiDocumentTest extends TestCase
{
    private const HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function test_every_api_route_is_documented(): void
    {
        $documented = $this->documentedOperations();
        $missing = [];

        foreach ($this->apiRoutes() as $route) {
            $path = $this->documentPath($route);
            $methods = $this->routeMethods($route);

            foreach ($methods as $method) {
                // Resource routes answer both PUT and PATCH with the same
                // action; documenting PUT (and saying PATCH works too) is
                // enough.
                if ($method === 'PATCH' && in_array('PUT', $methods, true)) {
                    continue;
                }

                if (! in_array($method.' '.$path, $documented, true)) {
                    $missing[] = $method.' /'.$route->uri();
                }
            }
        }

        $this->assertSame([], $missing, 'These API routes are missing from docs/api/openapi.yaml.');
    }

    public function test_every_documented_operation_exists(): void
    {
        $routes = [];
        foreach ($this->apiRoutes() as $route) {
            foreach ($this->routeMethods($route) as $method) {
                $routes[] = $method.' '.$this->documentPath($route);
            }
        }

        $stale = array_values(array_diff($this->documentedOperations(), $routes));

        $this->assertSame([], $stale, 'docs/api/openapi.yaml documents operations that have no route.');
    }

    public function test_document_targets_openapi_3_1_and_the_api_prefix(): void
    {
        $document = $this->document();

        $this->assertIsString($document['openapi'] ?? null);
        $this->assertMatchesRegularExpression('/^3\.1\.\d+$/', $document['openapi']);
        // documentPath() relies on every path being relative to /api.
        $servers = $document['servers'] ?? null;
        $this->assertIsArray($servers);
        $this->assertSame(['/api'], array_column($servers, 'url'));
    }

    /**
     * @return list<RoutingRoute>
     */
    private function apiRoutes(): array
    {
        $routes = array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/'),
        ));

        $this->assertNotEmpty($routes);

        return $routes;
    }

    /**
     * Route methods without the implicit HEAD that Laravel adds to GET routes.
     *
     * @return list<string>
     */
    private function routeMethods(RoutingRoute $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD']));
    }

    /**
     * "api/user/{user}" becomes "/user/{user}": the document's server URL is
     * /api and its path parameters use the route parameter names.
     */
    private function documentPath(RoutingRoute $route): string
    {
        return '/'.substr($route->uri(), strlen('api/'));
    }

    /**
     * Every operation in the document, as "METHOD /path".
     *
     * @return list<string>
     */
    private function documentedOperations(): array
    {
        $paths = $this->document()['paths'] ?? null;
        $this->assertIsArray($paths);

        $operations = [];
        foreach ($paths as $path => $item) {
            $this->assertIsArray($item, "Path item {$path} is not a map.");

            foreach (array_keys($item) as $key) {
                if (in_array($key, self::HTTP_METHODS, true)) {
                    $operations[] = strtoupper($key).' '.$path;
                }
            }
        }

        return $operations;
    }

    /**
     * @return array<mixed>
     */
    private function document(): array
    {
        $document = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        $this->assertIsArray($document);

        return $document;
    }
}
