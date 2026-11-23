<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Search, filters, sorting and pagination of GET /api/product, the stats
 * endpoint, and validation of SKU, stock, status and price.
 */
class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(), 'api');
        $this->category = Category::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes): Product
    {
        return Product::factory()->for($this->category)->create($attributes);
    }

    /**
     * Names in the order returned by GET /api/product with the given query.
     *
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function names(array $query): array
    {
        $response = $this->getJson('/api/product?'.http_build_query($query))->assertOk();
        $rows = $response->json('data.data');
        $this->assertIsArray($rows);

        return array_values(array_map(fn (mixed $row): string => is_array($row) && is_string($row['name'] ?? null) ? $row['name'] : '', $rows));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Desk Lamp', 'sku' => 'LAMP-1', 'description' => 'A lamp.', 'price' => '34.90',
            'stock' => 10, 'status' => 'active', 'category_id' => $this->category->id, ...$overrides,
        ];
    }

    public function test_list_keeps_the_paginator_envelope_and_defaults_to_newest_first(): void
    {
        $this->product(['name' => 'Old', 'created_at' => now()->subDay()]);
        $this->product(['name' => 'New']);

        $this->getJson('/api/product')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.total', 2)
            ->assertJsonStructure(['data' => ['current_page', 'data' => [['id', 'name', 'sku', 'price', 'stock', 'status', 'photo_url', 'category', 'tags']], 'last_page', 'total']]);
        $this->assertSame(['New', 'Old'], $this->names([]));
        $this->assertSame(['Old', 'New'], $this->names(['sort' => 'oldest']));
    }

    public function test_search_matches_name_sku_and_description_and_escapes_wildcards(): void
    {
        $this->product(['name' => 'Wireless Mouse', 'sku' => 'EL-1001', 'description' => 'Silent click.']);
        $this->product(['name' => 'Gel Pen Set', 'sku' => 'OF-4003', 'description' => 'Ten pens, 100% smudge free.']);
        $this->product(['name' => 'Yoga_Mat', 'sku' => 'SP-2002', 'description' => 'Rubber.']);

        $this->assertSame(['Wireless Mouse'], $this->names(['search' => 'mouse']));
        $this->assertSame(['Gel Pen Set'], $this->names(['search' => 'of-40']));
        $this->assertSame(['Gel Pen Set'], $this->names(['search' => 'smudge']));
        // "%" and "_" are literal characters, not LIKE wildcards.
        $this->assertSame(['Gel Pen Set'], $this->names(['search' => '100%']));
        $this->assertSame(['Gel Pen Set'], $this->names(['search' => '%']));
        $this->assertSame(['Yoga_Mat'], $this->names(['search' => '_']));
        // As a wildcard, "_" would match the space in "Pen Set".
        $this->assertSame([], $this->names(['search' => 'n_s']));
        $this->assertSame([], $this->names(['search' => '!']));
        $this->assertSame([], $this->names(['search' => "' or 1=1 --"]));
    }

    public function test_filters_by_category_tag_status_and_stock_level(): void
    {
        $other = Category::factory()->create();
        $sale = Tag::create(['name' => 'On sale']);
        $out = $this->product(['name' => 'Out', 'stock' => 0, 'status' => 'active']);
        $this->product(['name' => 'Low one', 'stock' => 1, 'status' => 'draft']);
        $this->product(['name' => 'Low five', 'stock' => 5, 'status' => 'active']);
        $in = Product::factory()->for($other)->create(['name' => 'Plenty', 'stock' => 6, 'status' => 'archived']);
        $out->tags()->attach($sale->id);
        $in->tags()->attach($sale->id);

        $this->assertSame(['Plenty'], $this->names(['category_id' => $other->id]));
        $this->assertEqualsCanonicalizing(['Out', 'Plenty'], $this->names(['tag' => $sale->id]));
        $this->assertSame(['Low one'], $this->names(['status' => 'draft']));
        $this->assertSame(['Plenty'], $this->names(['status' => 'archived']));
        $this->assertSame(['Out'], $this->names(['stock' => 'out']));
        $this->assertEqualsCanonicalizing(['Low one', 'Low five'], $this->names(['stock' => 'low']));
        $this->assertEqualsCanonicalizing(['Low one', 'Low five', 'Plenty'], $this->names(['stock' => 'in']));
        $this->assertSame(['Out'], $this->names(['tag' => $sale->id, 'category_id' => $this->category->id, 'stock' => 'out']));
    }

    public function test_sorts_by_name_price_and_stock_in_both_directions(): void
    {
        $this->product(['name' => 'Bravo', 'price' => '9.50', 'stock' => 30]);
        $this->product(['name' => 'Alpha', 'price' => '100.00', 'stock' => 2]);
        $this->product(['name' => 'Charlie', 'price' => '25.00', 'stock' => 0]);

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->names(['sort' => 'name']));
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->names(['sort' => '-name']));
        // Numeric, not string, ordering: 9.50 < 25.00 < 100.00.
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->names(['sort' => 'price']));
        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->names(['sort' => '-price']));
        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->names(['sort' => 'stock']));
        $this->assertSame(['Bravo', 'Alpha', 'Charlie'], $this->names(['sort' => '-stock']));
    }

    public function test_per_page_is_limited_and_links_keep_the_query_string(): void
    {
        Product::factory()->count(12)->for($this->category)->create(['status' => 'draft']);

        $this->getJson('/api/product?per_page=5&status=draft')->assertOk()
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.last_page', 3)
            ->assertJsonPath('data.next_page_url', 'http://localhost/api/product?per_page=5&status=draft&page=2');
        $this->getJson('/api/product?per_page=50')->assertOk()->assertJsonPath('data.per_page', 50);
        $this->assertCount(2, $this->names(['per_page' => 5, 'page' => 3]));
    }

    public function test_invalid_list_parameters_are_rejected(): void
    {
        foreach ([
            'sort' => ['price; drop table products', 'created_at', 'random'],
            'status' => ['deleted', 'ACTIVE'],
            'stock' => ['none', '5'],
            'per_page' => ['0', '51', 'all'],
            'category_id' => ['abc', '0'],
            'tag' => ['first', '-1'],
            'page' => ['0'],
            'search' => [str_repeat('x', 101)],
        ] as $parameter => $values) {
            foreach ($values as $value) {
                $this->getJson('/api/product?'.http_build_query([$parameter => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($parameter);
            }
        }

        $this->getJson('/api/product?search[]=x')->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_list_loads_categories_and_tags_without_a_query_per_product(): void
    {
        $tag = Tag::create(['name' => 'Bestseller']);
        foreach (Product::factory()->count(8)->create() as $product) {
            $product->tags()->attach($tag->id);
        }

        DB::enableQueryLog();
        $this->getJson('/api/product')->assertOk()->assertJsonPath('data.data.0.tags.0.text', 'Bestseller');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Authentication, count, page, categories and tags: a fixed number
        // of queries, whatever the number of products.
        $this->assertLessThanOrEqual(8, $queries);
    }

    public function test_stats_endpoint_counts_statuses_and_stock_levels(): void
    {
        $this->product(['stock' => 0, 'status' => 'active']);
        $this->product(['stock' => 3, 'status' => 'active']);
        $this->product(['stock' => 5, 'status' => 'draft']);
        $this->product(['stock' => 6, 'status' => 'archived']);
        $this->product(['stock' => 100, 'status' => 'active']);
        $this->product(['stock' => 0, 'status' => 'active'])->delete();

        $this->getJson('/api/product/stats')->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Product statistics',
            'data' => ['total' => 5, 'active' => 3, 'draft' => 1, 'archived' => 1, 'low_stock' => 2, 'out_of_stock' => 1],
        ]);
    }

    public function test_stats_endpoint_requires_a_verified_login(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/product/stats')->assertUnauthorized();

        $this->actingAs(User::factory()->unverified()->create(), 'api')
            ->getJson('/api/product/stats')->assertForbidden();
    }

    public function test_sku_is_required_on_create_upper_cased_and_unique(): void
    {
        $this->postJson('/api/product', $this->payload(['sku' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sku' => 'The SKU field is required.']);

        $id = $this->jsonInt($this->postJson('/api/product', $this->payload(['sku' => ' lamp-1 ']))
            ->assertOk()->assertJsonPath('data.sku', 'LAMP-1'), 'data.id');

        $this->postJson('/api/product', $this->payload(['sku' => 'Lamp-1']))
            ->assertUnprocessable()->assertJsonValidationErrors(['sku' => 'The SKU has already been taken.']);

        // Updating a product with its own SKU is allowed.
        $this->putJson('/api/product/'.$id, $this->payload(['name' => 'Renamed']))->assertOk();

        // A deleted product still owns its SKU (the column is unique).
        Product::findOrFail($id)->delete();
        $this->postJson('/api/product', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('sku');

        foreach (['AB 12', 'AB_12', 'ÄB-1', 'AB/1', str_repeat('A', 65), ['AB']] as $invalid) {
            $this->postJson('/api/product', $this->payload(['sku' => $invalid]))
                ->assertUnprocessable()->assertJsonValidationErrors('sku');
        }
    }

    public function test_sku_may_be_left_out_when_updating_a_product_that_has_none(): void
    {
        $legacy = $this->product(['sku' => null]);
        $taken = $this->product(['sku' => 'TAKEN-1']);
        $data = $this->payload();
        unset($data['sku']);

        $this->putJson('/api/product/'.$legacy->id, $data)->assertOk()->assertJsonPath('data.sku', null);
        $this->putJson('/api/product/'.$legacy->id, [...$data, 'sku' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->putJson('/api/product/'.$legacy->id, [...$data, 'sku' => $taken->sku])
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
    }

    public function test_stock_status_and_price_are_validated(): void
    {
        foreach ([
            'stock' => [-1, 1000001, 1.5, 'many', null],
            'status' => ['deleted', 'Active', '', ['active']],
            'price' => [-0.01, 100000000, '99999999.999', '12.345', '1e3', 'free'],
        ] as $field => $values) {
            foreach ($values as $value) {
                $this->postJson('/api/product', $this->payload([$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }

        $this->postJson('/api/product', $this->payload(['stock' => 1000000, 'status' => 'draft', 'price' => '99999999.99']))
            ->assertOk()->assertJsonPath('data.stock', 1000000)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.price', '99999999.99');
    }

    public function test_stock_and_status_default_to_zero_and_active(): void
    {
        $data = $this->payload(['price' => 5]);
        unset($data['stock'], $data['status']);

        $response = $this->postJson('/api/product', $data)->assertOk()
            ->assertJsonPath('data.stock', 0)->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.price', '5.00');
        $this->assertDatabaseHas('products', ['id' => $this->jsonInt($response, 'data.id'), 'stock' => 0, 'status' => 'active']);
    }
}
