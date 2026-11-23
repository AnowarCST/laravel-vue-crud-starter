<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\RejectStaleApiTokenCookie;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Tests\TestCase;

/**
 * RejectStaleApiTokenCookie is added to the "api" middleware group. A route
 * that uses the "api" guard outside that group would accept laravel_token
 * cookies issued before a password change, so every such route must also
 * run the middleware.
 */
final class ApiGuardMiddlewareTest extends TestCase
{
    public function test_every_route_using_the_api_guard_rejects_stale_api_cookies(): void
    {
        // Creating the HTTP kernel registers the middleware groups and aliases
        // on the router, which resolveMiddleware() needs.
        $this->app->make(Kernel::class);
        $router = $this->app->make(Router::class);
        $checked = 0;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            // Expand groups ("api") and aliases ("auth:api") into class names.
            $middleware = array_filter($router->resolveMiddleware($route->gatherMiddleware()), is_string(...));

            if (! $this->usesApiGuard($middleware)) {
                continue;
            }

            $checked++;
            $this->assertContains(
                RejectStaleApiTokenCookie::class,
                $middleware,
                sprintf('Route [%s %s] uses the api guard without %s.', implode('|', $route->methods()), $route->uri(), RejectStaleApiTokenCookie::class),
            );
        }

        $this->assertGreaterThan(0, $checked, 'No route uses the api guard; this test would prove nothing.');
    }

    /**
     * @param  array<array-key, string>  $middleware
     */
    private function usesApiGuard(array $middleware): bool
    {
        foreach ($middleware as $name) {
            [$class, $parameters] = array_pad(explode(':', $name, 2), 2, '');

            if (is_a($class, Authenticate::class, true) && in_array('api', explode(',', $parameters), true)) {
                return true;
            }
        }

        return false;
    }
}
