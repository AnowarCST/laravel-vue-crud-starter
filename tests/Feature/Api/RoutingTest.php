<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Routes under /api that are not tied to one resource.
 */
class RoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_is_public_and_not_wrapped_in_the_envelope(): void
    {
        $this->getJson('/api/version')
            ->assertOk()
            ->assertExactJson(['version' => config('app.version')]);
    }

    public function test_unknown_api_paths_return_json_404_instead_of_the_spa(): void
    {
        $this->assertGenericNotFound($this->getJson('/api/does-not-exist'));

        $this->actingAs(User::factory()->create())->get('/api/does-not-exist')->assertNotFound();
    }
}
