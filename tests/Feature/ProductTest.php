<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use CategoriesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use ProductsTableSeeder;
use TagsTableSeeder;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This will run all your migration
        Artisan::call('migrate');

        // This will seed your database
        Artisan::call('db:seed');

        // If you wan to seed a specific table
        // Artisan::call('db:seed', ['--class' => 'ProductsTableSeeder', '--database' => 'testing']);
    }

    /**
     * Test Product List
     *
     * @return void
     */
    public function testProductList()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/product');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => true,
            ]);
    }

    /**
     * Store Product
     *
     * @return void
     */
    public function testStoreProduct()
    {
        $user = factory(User::class)->create();

        $data = [
            'name' => $this->faker->word,
            'description' => $this->faker->text,
            'price' => $this->faker->numberBetween($min = 100000, $max = 900000),
            'category_id' => 5,
            'tags' => [['id' => 2]],
            'photo' => 'images/cars/cima_1912_top_01.jpg.ximg.l_full_m.smart.jpg',
        ];


        $response = $this->actingAs($user, 'api')
            ->postJson('/api/product', $data);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /**
     * Show Product
     *
     * @return void
     */
    public function testShowProduct()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/product/1');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => true,
            ]);
    }

    /**
     * Update Product
     *
     * @return void
     */
    public function testUpdateProduct()
    {
        $user = factory(User::class)->create();

        $data = [
            'name' => $this->faker->word,
            'description' => $this->faker->text,
            'price' => $this->faker->numberBetween($min = 100000, $max = 900000),
            'category_id' => 5,
            'tags' => [['id' => 2]],
            'photo' => 'images/cars/cima_1912_top_01.jpg.ximg.l_full_m.smart.jpg',
        ];

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/product/1', $data);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /**
     * Product Validation Error
     *
     * @return void
     */
    public function testUpdateProductValidationError()
    {
        $user = factory(User::class)->create();

        $data = [
            'name' => $this->faker->word,
        ];

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/product/1', $data);


        $responseUpdate = $this->actingAs($user, 'api')
            ->postJson('/api/product', $data);

        $response->assertStatus(422);
        $responseUpdate->assertStatus(422);
    }

    /**
     * Destroy Product
     *
     * @return void
     */
    public function testDestroyProduct()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user, 'api')
            ->deleteJson('/api/product/1');

        $response->assertStatus(200);
    }
}
