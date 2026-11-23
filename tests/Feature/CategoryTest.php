<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_taxonomy_crud_and_input_validation(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $this->actingAs($admin, 'api');
        foreach (['category', 'tag'] as $resource) {
            $this->postJson('/api/'.$resource, ['name' => ['unexpected' => 'array']])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
            $created = $this->postJson('/api/'.$resource, ['name' => 'Initial', 'description' => 'Details'])
                ->assertOk()->assertJsonPath('data.name', 'Initial');
            $id = $this->jsonInt($created, 'data.id');
            $this->getJson('/api/'.$resource)->assertOk();
            $this->getJson('/api/'.$resource.'/list')->assertOk();
            $this->getJson('/api/'.$resource.'/'.$id)->assertOk();
            $this->putJson('/api/'.$resource.'/'.$id, ['name' => 'Updated'])
                ->assertOk()->assertJsonPath('data.name', 'Updated');
            $this->deleteJson('/api/'.$resource.'/'.$id)->assertOk();
            $this->getJson('/api/'.$resource.'/'.$id)->assertNotFound();
        }
    }

    public function test_non_admin_cannot_delete_taxonomy_but_can_maintain_it(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        foreach (['category', 'tag'] as $resource) {
            $response = $this->postJson('/api/'.$resource, ['name' => 'Shared catalogue'])->assertOk();
            $this->deleteJson('/api/'.$resource.'/'.$this->jsonInt($response, 'data.id'))->assertForbidden();
        }
    }

    public function test_guests_cannot_access_taxonomy(): void
    {
        foreach (['category', 'tag'] as $resource) {
            $this->getJson('/api/'.$resource)->assertUnauthorized();
            $this->postJson('/api/'.$resource, ['name' => 'Guest'])->assertUnauthorized();
        }
    }
}
