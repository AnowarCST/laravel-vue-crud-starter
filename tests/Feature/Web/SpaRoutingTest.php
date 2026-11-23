<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SPA catch-all route in routes/web.php.
 */
class SpaRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_routes_require_login_and_home_redirects_to_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/home')->assertRedirect('/dashboard');

        $this->actingAs(User::factory()->create());
        $this->get('/dashboard')->assertOk();
        $this->get('/products/12')->assertOk();
    }
}
