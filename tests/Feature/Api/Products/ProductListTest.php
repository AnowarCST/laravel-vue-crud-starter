<?php

namespace Tests\Feature\Api\Products;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Search, filters, sorting and pagination of GET /api/product, and the
 * stats endpoint.
 */
class ProductListTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUser();
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

    public function test_list_keeps_the_paginator_envelope_and_defaults_to_newest_first(): void
    {
        $this->product(['name' => 'Old', 'created_at' => now()->subDay()]);
        $this->product(['name' => 'New']);

        $this->assertApiSuccess($this->getJson('/api/product'), 'Product list')
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.total', 2)
            ->assertJsonStructure(['data' => ['current_page', 'data' => [['id', 'name', 'sku', 'price', 'stock', 'status', 'photo_url', 'category', 'tags']], 'last_page', 'total']]);
        $this->assertSame(['New', 'Old'], $this->names([]));
        $this->assertSame(['Old', 'New'], $this->names(['sort' => 'oldest']));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function searches(): iterable
    {
        yield 'name' => ['mouse', ['Wireless Mouse']];
        yield 'SKU' => ['of-40', ['Gel Pen Set']];
        yield 'description' => ['smudge', ['Gel Pen Set']];
        // "%" and "_" are literal characters, not LIKE wildcards.
        yield 'literal percent in text' => ['100%', ['Gel Pen Set']];
        yield 'percent alone' => ['%', ['Gel Pen Set']];
        yield 'underscore alone' => ['_', ['Yoga_Mat']];
        // As a wildcard, "_" would match the space in "Pen Set".
        yield 'underscore between letters' => ['n_s', []];
        yield 'escape character' => ['!', []];
        yield 'SQL injection attempt' => ["' or 1=1 --", []];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('searches')]
    public function test_search_matches_name_sku_and_description_and_escapes_wildcards(string $search, array $expected): void
    {
        $this->product(['name' => 'Wireless Mouse', 'sku' => 'EL-1001', 'description' => 'Silent click.']);
        $this->product(['name' => 'Gel Pen Set', 'sku' => 'OF-4003', 'description' => 'Ten pens, 100% smudge free.']);
        $this->product(['name' => 'Yoga_Mat', 'sku' => 'SP-2002', 'description' => 'Rubber.']);

        $this->assertSame($expected, $this->names(['search' => $search]));
    }

    public function test_filters_by_category_tag_status_and_stock_level(): void
    {
        $other = Category::factory()->create();
        $sale = Tag::factory()->create(['name' => 'On sale']);
        $out = $this->product(['name' => 'Out', 'stock' => 0, 'status' => ProductStatus::Active]);
        $this->product(['name' => 'Low one', 'stock' => 1, 'status' => ProductStatus::Draft]);
        $this->product(['name' => 'Low five', 'stock' => 5, 'status' => ProductStatus::Active]);
        $in = Product::factory()->for($other)->archived()->create(['name' => 'Plenty', 'stock' => 6]);
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

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function sorts(): iterable
    {
        yield 'name' => ['name', ['Alpha', 'Bravo', 'Charlie']];
        yield '-name' => ['-name', ['Charlie', 'Bravo', 'Alpha']];
        // Numeric, not string, ordering: 9.50 < 25.00 < 100.00.
        yield 'price' => ['price', ['Bravo', 'Charlie', 'Alpha']];
        yield '-price' => ['-price', ['Alpha', 'Charlie', 'Bravo']];
        yield 'stock' => ['stock', ['Charlie', 'Alpha', 'Bravo']];
        yield '-stock' => ['-stock', ['Bravo', 'Alpha', 'Charlie']];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('sorts')]
    public function test_sorts_by_name_price_and_stock_in_both_directions(string $sort, array $expected): void
    {
        $this->product(['name' => 'Bravo', 'price' => '9.50', 'stock' => 30]);
        $this->product(['name' => 'Alpha', 'price' => '100.00', 'stock' => 2]);
        $this->product(['name' => 'Charlie', 'price' => '25.00', 'stock' => 0]);

        $this->assertSame($expected, $this->names(['sort' => $sort]));
    }

    public function test_per_page_is_limited_and_links_keep_the_query_string(): void
    {
        Product::factory()->count(12)->for($this->category)->draft()->create();

        $this->getJson('/api/product?per_page=5&status=draft')->assertOk()
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.last_page', 3)
            ->assertJsonPath('data.next_page_url', 'http://localhost/api/product?per_page=5&status=draft&page=2');
        $this->getJson('/api/product?per_page=50')->assertOk()->assertJsonPath('data.per_page', 50);
        $this->assertCount(2, $this->names(['per_page' => 5, 'page' => 3]));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidListParameters(): iterable
    {
        foreach ([
            'sort' => ['price; drop table products', 'created_at', 'random'],
            'status' => ['deleted', 'ACTIVE'],
            'stock' => ['none', '5'],
            'per_page' => ['0', '51', 'all'],
            'category_id' => ['abc', '0'],
            'tag' => ['first', '-1'],
            'page' => ['0'],
            'search' => [str_repeat('x', 101), ['x']],
        ] as $parameter => $values) {
            foreach ($values as $value) {
                yield $parameter.'='.(is_string($value) ? substr($value, 0, 30) : 'array') => [$parameter, $value];
            }
        }
    }

    #[DataProvider('invalidListParameters')]
    public function test_invalid_list_parameters_are_rejected(string $parameter, mixed $value): void
    {
        $this->getJson('/api/product?'.http_build_query([$parameter => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($parameter);
    }

    public function test_list_loads_categories_and_tags_without_a_query_per_product(): void
    {
        $tag = Tag::factory()->create(['name' => 'Bestseller']);
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
        $this->product(['stock' => 0, 'status' => ProductStatus::Active]);
        $this->product(['stock' => 3, 'status' => ProductStatus::Active]);
        $this->product(['stock' => 5, 'status' => ProductStatus::Draft]);
        $this->product(['stock' => 6, 'status' => ProductStatus::Archived]);
        $this->product(['stock' => 100, 'status' => ProductStatus::Active]);
        $this->product(['stock' => 0, 'status' => ProductStatus::Active])->delete();

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

        $this->actingAsUnverifiedUser();
        $this->assertEmailNotVerified($this->getJson('/api/product/stats'));
    }
}
