<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionTest extends TestCase
{
    /**
     * Test APP Version
     * Simple test to validate everything is working
     *
     * @return void
     */
    public function test_app_version()
    {
        $response = $this->getJson('/api/version');
        $response
            ->assertStatus(200)
            ->assertJson([
                'version' => true,
            ]);
    }
}
