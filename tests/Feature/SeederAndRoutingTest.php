<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederAndRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_is_idempotent_and_creates_no_accounts(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(0, User::count());
        $this->assertSame(6, Category::count());
        $this->assertSame(2, Tag::count());
        $this->assertSame(7, Product::count());
    }

    public function test_spa_routes_require_login_and_home_redirects_to_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/home')->assertRedirect('/dashboard');

        $this->actingAs(User::factory()->create());
        $this->get('/dashboard')->assertOk();
        $this->get('/products/12')->assertOk();
    }

    public function test_unknown_api_paths_return_json_404_instead_of_the_spa(): void
    {
        $this->getJson('/api/does-not-exist')->assertNotFound()->assertExactJson(['message' => 'Not Found.']);
        $this->actingAs(User::factory()->create())->get('/api/does-not-exist')->assertNotFound();
    }
}
