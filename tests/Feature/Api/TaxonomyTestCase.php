<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared behaviour of the category and tag endpoints, which follow the
 * same rules: any verified account may read, create and update; only
 * administrators may delete.
 */
abstract class TaxonomyTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * The resource name in the URL: "category" or "tag".
     */
    abstract protected function resource(): string;

    /**
     * The name used in the response messages: "Category" or "Tag".
     */
    abstract protected function label(): string;

    public function test_crud_and_input_validation(): void
    {
        $this->actingAsAdmin();
        $url = '/api/'.$this->resource();
        $label = $this->label();

        $this->postJson($url, ['name' => ['unexpected' => 'array']])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $created = $this->assertApiSuccess($this->postJson($url, ['name' => 'Initial', 'description' => 'Details']), "{$label} Created Successfully")
            ->assertJsonPath('data.name', 'Initial');
        $id = $this->jsonInt($created, 'data.id');
        $this->assertApiSuccess($this->getJson($url), "{$label} list");
        $this->assertApiSuccess($this->getJson($url.'/list'), "{$label} list");
        $this->assertApiSuccess($this->getJson($url.'/'.$id), "{$label} Details");
        $this->assertApiSuccess($this->putJson($url.'/'.$id, ['name' => 'Updated']), "{$label} Information has been updated")
            ->assertJsonPath('data.name', 'Updated');
        $this->assertApiSuccess($this->deleteJson($url.'/'.$id), "{$label} has been Deleted");
        $this->assertGenericNotFound($this->getJson($url.'/'.$id));
    }

    public function test_unknown_ids_return_404(): void
    {
        $this->actingAsAdmin();
        $url = '/api/'.$this->resource().'/999999';

        $this->assertGenericNotFound($this->getJson($url));
        $this->assertGenericNotFound($this->putJson($url, ['name' => 'Updated']));
        $this->assertGenericNotFound($this->deleteJson($url));
    }

    public function test_non_admin_cannot_delete_but_can_maintain(): void
    {
        $this->actingAsUser();
        $url = '/api/'.$this->resource();

        $response = $this->postJson($url, ['name' => 'Shared catalogue'])->assertOk();
        $id = $this->jsonInt($response, 'data.id');
        $this->putJson($url.'/'.$id, ['name' => 'Renamed'])->assertOk();
        $this->deleteJson($url.'/'.$id)->assertForbidden();
    }

    public function test_guests_cannot_access(): void
    {
        $this->getJson('/api/'.$this->resource())->assertUnauthorized();
        $this->postJson('/api/'.$this->resource(), ['name' => 'Guest'])->assertUnauthorized();
    }
}
